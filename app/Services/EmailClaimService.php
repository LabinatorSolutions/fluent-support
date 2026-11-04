<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Notification;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Notifications\NotificationService;

/**
 * Moves a customer record onto its WordPress account's new email address once
 * the customer proves they own it.
 *
 * ProfileInfoService won't follow an unverified email change, so the record
 * keeps the old address. The confirmation link goes to the new address and
 * only works for the signed-in account that requested it, so neither the inbox
 * nor the account can move the record alone.
 */
class EmailClaimService
{
    /**
     * How long a claim link stays valid. A fresh one can always be requested
     * from the portal.
     */
    const TTL_SECONDS = DAY_IN_SECONDS;

    /**
     * Mixed into the signature so these tokens can't be replayed against
     * another feature that signs with the same salt.
     */
    const SIGNING_CONTEXT = 'fluent_support_email_claim_v1';

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        /*
         * Filter whether customers may move their support address to their
         * WordPress account address from the portal.
         *
         * @since v2.4.1
         * @param bool $enabled
         */
        return (bool) apply_filters('fluent_support/enable_email_claim', true);
    }

    /**
     * Describe how the signed-in account's address differs from its customer
     * record, or null when there is nothing to reconcile.
     *
     * An address already held by another linked record also returns null. That
     * is a conflict between two accounts and is left for an agent.
     *
     * Pass the customer if you already have it. The portal calls this on every
     * page load.
     *
     * @param \FluentSupport\App\Models\Customer|null $customer
     * @return array|null
     */
    public static function getDivergence($customer = null)
    {
        if (!self::isEnabled()) {
            return null;
        }

        $userId = (int) get_current_user_id();

        if (!$userId) {
            return null;
        }

        $user = get_user_by('ID', $userId);

        if (!$user || !$user->user_email) {
            return null;
        }

        $customer = $customer ?: Helper::getCurrentCustomer();

        if (!$customer || (int) $customer->user_id !== $userId) {
            return null;
        }

        if (self::isSame($customer->email, $user->user_email)) {
            return null;
        }

        if (self::heldByLinkedCustomer($user->user_email, $customer->id)) {
            return null;
        }

        // Stray records on the new address aren't loaded here. apply() reads
        // them when it acts.
        return [
            'customer' => $customer,
            'from'     => $customer->email,
            'to'       => $user->user_email
        ];
    }

    /**
     * Mail a confirmation link to the address being claimed.
     *
     * @param array $divergence As returned by getDivergence()
     * @return string '' on success, otherwise an error slug
     */
    public static function issue($divergence)
    {
        $customer = $divergence['customer'];
        $target   = $divergence['to'];

        // Per account, to stop one account mailing many inboxes. Per address,
        // to stop many accounts mailing one inbox.
        $accountKey = 'fs_email_claim_user_' . (int) $customer->user_id;
        $targetKey  = 'fs_email_claim_to_' . wp_hash(self::normalize($target));

        if (Helper::hitRateLimit($accountKey, 5, HOUR_IN_SECONDS)
            || Helper::hitRateLimit($targetKey, 5, HOUR_IN_SECONDS)
        ) {
            return 'throttled';
        }

        $link = self::buildConfirmUrl($customer, $target);

        if (!$link) {
            return 'no_portal';
        }

        $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $subject = apply_filters(
            'fluent_support/email_claim_mail_subject',
            // translators: %s is the site name
            sprintf(__('[%s] Confirm your support email address', 'fluent-support'), $siteName),
            $customer
        );

        $pStart = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        $body = $pStart . sprintf(
                // translators: %s is the customer's first name
                __('Hello %s,', 'fluent-support'),
                // WordPress doesn't sanitize first_name on save.
                esc_html($customer->first_name)
            ) . '</p>' .
            $pStart . sprintf(
                // translators: 1: site name, 2: the email address being confirmed
                __('Someone asked to use this address for support messages on %1$s. Confirming will send future support notifications to %2$s and bring any tickets opened from it into your account.', 'fluent-support'),
                $siteName,
                $target
            ) . '</p>' .
            $pStart . '<a style="display: inline-block; background: #2271b1; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 3px;" href="' . esc_url($link) . '">' .
            esc_html__('Confirm this address', 'fluent-support') . '</a></p>' .
            $pStart . __('You will be asked to sign in first, so this link only works for the account that requested it.', 'fluent-support') . '</p>' .
            $pStart . __('If you did not ask for this, no action is needed and nothing has changed. Your support messages will keep going to the address they go to now.', 'fluent-support') . '</p>';

        /*
         * Filter the body of the support address confirmation email.
         *
         * @since v2.4.1
         * @param string $body
         * @param \FluentSupport\App\Models\Customer $customer
         * @param string $target The address being confirmed
         * @param string $link
         */
        $body = apply_filters('fluent_support/email_claim_mail_body', $body, $customer, $target, $link);

        $message = Helper::loadView('notification', [
            'body'        => $body,
            'pre_header'  => __('Confirm your support email address', 'fluent-support'),
            'show_footer' => false
        ]);

        $sent = wp_mail($target, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

        if (!$sent) {
            return 'send_failed';
        }

        return '';
    }

    /**
     * Validate a confirmation token against current state.
     *
     * The token binds the record's current address, so applying a claim retires
     * the link. It is not strictly single-use: if the record is moved back to
     * that address before expiry, the link works again.
     *
     * @param string $token
     * @return array ['status' => slug, 'customer' => Customer|null, 'email' => string]
     */
    public static function resolveClaim($token)
    {
        if (!self::isEnabled()) {
            return ['status' => 'disabled'];
        }

        $claim = self::parseToken($token);

        if (!$claim) {
            return ['status' => 'invalid'];
        }

        if ($claim['expires'] < time()) {
            return ['status' => 'expired'];
        }

        // Inbox access alone isn't enough. The claiming account must be signed in.
        if ((int) get_current_user_id() !== $claim['user_id']) {
            return ['status' => 'wrong_account'];
        }

        $customer = Customer::where('id', $claim['customer_id'])->first();

        if (!$customer || (int) $customer->user_id !== $claim['user_id']) {
            return ['status' => 'invalid'];
        }

        if (!self::isSame($customer->email, $claim['from'])) {
            return ['status' => 'stale'];
        }

        $user = get_user_by('ID', $claim['user_id']);

        if (!$user || !self::isSame($user->user_email, $claim['to'])) {
            return ['status' => 'stale'];
        }

        if (self::heldByLinkedCustomer($claim['to'], $customer->id)) {
            return ['status' => 'conflict'];
        }

        return [
            'status'   => 'ok',
            'customer' => $customer,
            'email'    => $claim['to']
        ];
    }

    /**
     * Move the record onto the confirmed address and absorb any unlinked record
     * that was already collecting tickets there.
     *
     * @param \FluentSupport\App\Models\Customer $customer
     * @param string $email
     * @return array ['merged' => int, 'tickets' => int]
     */
    public static function apply($customer, $email)
    {
        $previousEmail = $customer->email;

        $merged = 0;
        $moved  = 0;

        foreach (self::unlinkedRecordsHolding($email, $customer->id) as $duplicate) {
            $moved += self::absorb($duplicate, $customer);
            $merged++;
        }

        $customer->email = $email;
        $customer->save();

        // Shared with the profile_update path: rotates ticket hashes and logs activity.
        ProfileInfoService::onProvenEmailChange($customer, $previousEmail, 'claimed');

        return ['merged' => $merged, 'tickets' => $moved];
    }

    /**
     * Reparent everything hanging off one customer record onto another, then
     * remove the record it came from.
     *
     * The source is deleted only if a re-read shows it empty. The tables may be
     * MyISAM, so a transaction can't be relied on.
     *
     * @param \FluentSupport\App\Models\Customer $source
     * @param \FluentSupport\App\Models\Customer $target
     * @return int Number of tickets moved
     */
    protected static function absorb($source, $target)
    {
        $tickets = Ticket::where('customer_id', $source->id)->get();

        foreach ($tickets as $ticket) {
            $ticket->customer_id = $target->id;
            // Saved one by one so Ticket's updating event rotates the hash.
            // A query-builder update would skip it.
            $ticket->save();
        }

        Conversation::where('person_id', $source->id)->update(['person_id' => $target->id]);
        Attachment::where('person_id', $source->id)->update(['person_id' => $target->id]);

        Activity::where('person_type', 'customer')
            ->where('person_id', $source->id)
            ->update(['person_id' => $target->id]);

        Activity::where('object_type', 'customer')
            ->where('object_id', $source->id)
            ->update(['object_id' => $target->id]);

        // Notification tables only exist once internal notifications have been
        // enabled, so check before querying them.
        $hasNotifications = NotificationService::tablesExist();

        if ($hasNotifications) {
            Notification::where('actor_id', $source->id)
                ->update(['actor_id' => $target->id]);
        }

        self::mergeMeta($source, $target);

        /*
         * Fires while one customer record is merged into another, after core
         * rows have moved and before the source record is removed. Move any
         * rows keyed by person id here. Pro moves time tracking on this hook.
         *
         * @since v2.4.1
         * @param \FluentSupport\App\Models\Customer $source Record being absorbed
         * @param \FluentSupport\App\Models\Customer $target Record it is folded into
         */
        do_action('fluent_support/merging_customer_records', $source, $target);

        $sourceId      = $source->id;
        $sourceEmail   = $source->email;
        $ticketsMoved  = count($tickets);

        $remaining = Ticket::where('customer_id', $sourceId)->count()
            + Conversation::where('person_id', $sourceId)->count()
            + Attachment::where('person_id', $sourceId)->count();

        if ($hasNotifications) {
            $remaining += Notification::where('actor_id', $sourceId)->count();
        }

        if ($remaining === 0) {
            $source->deleteAllMeta();
            $source->delete();
        }

        Activity::create([
            'event_type'  => 'fluent_support/customer_records_merged',
            'person_id'   => $target->id,
            'person_type' => 'customer',
            'object_id'   => $target->id,
            'object_type' => 'customer',
            'description' => $remaining === 0
                ? sprintf(
                    // translators: 1: merged customer record id, 2: that record's email address, 3: number of tickets moved
                    _n(
                        'Merged customer record #%1$s (%2$s) into this one after the address was confirmed. %3$s ticket moved and its shared link was reissued.',
                        'Merged customer record #%1$s (%2$s) into this one after the address was confirmed. %3$s tickets moved and their shared links were reissued.',
                        $ticketsMoved,
                        'fluent-support'
                    ),
                    $sourceId,
                    $sourceEmail,
                    $ticketsMoved
                )
                : sprintf(
                    // translators: 1: customer record id, 2: that record's email address, 3: number of tickets moved
                    _n(
                        'Moved %3$s ticket from customer record #%1$s (%2$s) into this one after the address was confirmed. That record still holds other data and was kept.',
                        'Moved %3$s tickets from customer record #%1$s (%2$s) into this one after the address was confirmed. That record still holds other data and was kept.',
                        $ticketsMoved,
                        'fluent-support'
                    ),
                    $sourceId,
                    $sourceEmail,
                    $ticketsMoved
                )
        ]);

        return $ticketsMoved;
    }

    /**
     * Move person meta the target doesn't already have. The target's own keys win.
     *
     * @param \FluentSupport\App\Models\Customer $source
     * @param \FluentSupport\App\Models\Customer $target
     * @return void
     */
    protected static function mergeMeta($source, $target)
    {
        $sourceMeta = Meta::where('object_type', 'person_meta')
            ->where('object_id', $source->id)
            ->get();

        if (!$sourceMeta || count($sourceMeta) === 0) {
            return;
        }

        $existing = Meta::where('object_type', 'person_meta')
            ->where('object_id', $target->id)
            ->get()
            ->pluck('key')
            ->toArray();

        foreach ($sourceMeta as $meta) {
            if (in_array($meta->key, $existing, true)) {
                continue;
            }

            $meta->object_id = $target->id;
            $meta->save();
        }
    }

    /**
     * @param \FluentSupport\App\Models\Customer $customer
     * @param string $target
     * @return string
     */
    public static function buildConfirmUrl($customer, $target)
    {
        $baseUrl = Helper::getPortalBaseUrl();

        if (!$baseUrl) {
            return '';
        }

        $token = self::buildToken(
            $customer->id,
            $customer->user_id,
            $customer->email,
            $target,
            time() + self::TTL_SECONDS
        );

        return add_query_arg([
            'fs_view'  => 'email_claim',
            'fs_claim' => $token
        ], $baseUrl);
    }

    /**
     * URL the portal notice points at to request a confirmation email.
     *
     * Nonced because following it sends mail.
     *
     * @return string
     */
    public static function buildRequestUrl()
    {
        $baseUrl = Helper::getPortalBaseUrl();

        if (!$baseUrl) {
            return '';
        }

        return add_query_arg([
            'fs_view'         => 'email_claim',
            'fs_claim_action' => 'send',
            '_wpnonce'        => wp_create_nonce('fs_email_claim_send')
        ], $baseUrl);
    }

    /**
     * @param int $customerId
     * @param int $userId
     * @param string $from Address the record holds now
     * @param string $to Address being claimed
     * @param int $expires
     * @return string
     */
    public static function buildToken($customerId, $userId, $from, $to, $expires)
    {
        // Encoded because is_email() accepts '|' in the local part.
        $payload = implode('|', [
            (int) $customerId,
            (int) $userId,
            rawurlencode(self::normalize($from)),
            rawurlencode(self::normalize($to)),
            (int) $expires
        ]);

        $raw = $payload . '|' . self::sign($payload);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param string $token
     * @return array|null
     */
    protected static function parseToken($token)
    {
        $token = (string) $token;

        if (!$token || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        $raw = base64_decode(strtr($token, '-_', '+/'), true);

        if (!$raw) {
            return null;
        }

        $parts = explode('|', $raw);

        if (count($parts) !== 6) {
            return null;
        }

        list($customerId, $userId, $from, $to, $expires, $signature) = $parts;

        $payload = implode('|', [$customerId, $userId, $from, $to, $expires]);

        if (!hash_equals(self::sign($payload), $signature)) {
            return null;
        }

        // Decode only after verifying the signature over the encoded string.
        return [
            'customer_id' => (int) $customerId,
            'user_id'     => (int) $userId,
            'from'        => rawurldecode($from),
            'to'          => rawurldecode($to),
            'expires'     => (int) $expires
        ];
    }

    /**
     * @param string $payload
     * @return string
     */
    protected static function sign($payload)
    {
        return hash_hmac('sha256', self::SIGNING_CONTEXT . '|' . $payload, wp_salt('auth'));
    }

    /**
     * Records on this address with no WordPress account. Only these may be
     * absorbed by a claim.
     *
     * @param string $email
     * @param int $excludeId
     * @return \FluentSupport\Framework\Support\Collection
     */
    protected static function unlinkedRecordsHolding($email, $excludeId)
    {
        return Customer::where('email', $email)
            ->where('id', '!=', $excludeId)
            ->unclaimed()
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Whether another account's customer record already holds this address.
     *
     * @param string $email
     * @param int $excludeId
     * @return bool
     */
    protected static function heldByLinkedCustomer($email, $excludeId)
    {
        if (!$email) {
            return true;
        }

        return (bool) Customer::where('email', $email)
            ->where('id', '!=', $excludeId)
            ->claimed()
            ->first();
    }

    /**
     * @param string $email
     * @return string
     */
    protected static function normalize($email)
    {
        return strtolower(trim((string) $email));
    }

    /**
     * @param string $left
     * @param string $right
     * @return bool
     */
    protected static function isSame($left, $right)
    {
        return self::normalize($left) === self::normalize($right);
    }
}
