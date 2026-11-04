<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

class ProfileInfoService
{
    /**
     * @param object|array $customer
     * @param array $context See widgetContext().
     * @return array
     */
    public static function getProfileExtraWidgets($customer, $context = [])
    {
        $widgets = [];
        /*
         * Filter customer profile widgets
         *
         * Integrations should build only what $context asks for:
         *   - format 'html' (agent UI): stats / sections (preferred) or body_html, no 'mcp' key.
         *   - format 'mcp' (AI agents via MCP): an 'mcp' array of plain data, no body_html.
         *     body_html still works; it is converted to Markdown.
         *   - keys: when set, only these widget keys are wanted. Skip the queries for any other.
         * Use ProfileInfoService::wantsWidget() and isMcpWidgetRequest() to check.
         *
         * Structured widgets are drawn by the sidebar as badges, tiles, rows and lists,
         * all as plain text. Every key is optional. Dates are ISO 8601 and shown
         * relative ("9 months ago"); URLs must be absolute http(s):
         *
         *   $widgets['my_shop'] = [
         *       'title'    => 'My Shop',
         *       'icon'     => 'ShoppingBag', // see WIDGET_ICONS in _StructuredWidget.vue
         *       'badges'   => [['text' => 'Subscribed', 'type' => 'success']], // success|warning|danger|info|primary
         *       'stats_style' => 'summary', // optional: first stat as a hero figure, the rest on one line
         *       'stats'    => [ // tiles, two per row (or the summary above)
         *           ['label' => 'Lifetime Value', 'value' => '$748.00', 'url' => '...'],
         *           ['label' => 'Last Order', 'value' => '2026-01-09T08:30:00+00:00', 'type' => 'date'],
         *       ],
         *       'facts'    => [ // compact label / value rows
         *           ['label' => 'Location', 'value' => 'Berlin, Germany'],
         *       ],
         *       'sections' => [
         *           ['label' => 'Recent Purchases', 'items' => [
         *               ['title' => 'Pro Plan', 'subtitle' => '5 Sites', 'detail' => '3 renewals', 'url' => '...',
         *                'meta' => '2026-01-09T08:30:00+00:00', 'meta_type' => 'date',
         *                'subtitle_meta' => '2026-01-09T08:30:00+00:00', 'subtitle_meta_type' => 'date', // right of the subtitle, absolute date
         *                'reference' => ['text' => '#WPMN-254380', 'url' => '...'],    // invoice / order reference, before the badge
         *                'badge' => ['text' => 'active', 'type' => 'success'],
         *                'info' => 'Next renewal Oct 27, 2026',   // tooltip shown when the item is hovered
         *                'refunded' => true,                       // strikes the price through; pair with a danger badge
         *                'related' => []],                       // orders folded under this one (renewals): title, meta, subtitle, subtitle_meta, reference, badge
         *           ]],
         *           ['label' => 'Tags', 'tags' => ['VIP', 'Agency']],
         *       ],
         *       'links'    => [['label' => 'View in My Shop', 'url' => '...']],
         *   ];
         *
         * @since v1.0.0
         * @since v2.4.1 $context, structured widgets
         * @param array $widgets
         * @param object|array  $customer
         * @param array $context ['format' => 'html'|'mcp', 'keys' => string[]|null]
         *
         * @return array
         */
        $widgets = apply_filters('fluent_support/customer_extra_widgets', $widgets, $customer, self::widgetContext($context));
        return $widgets;
    }

    /**
     * Normalized context for fluent_support/customer_extra_widgets.
     *
     * @param array $context
     * @return array ['format' => 'html'|'mcp', 'keys' => string[]|null]
     */
    public static function widgetContext($context = [])
    {
        $format = isset($context['format']) && $context['format'] === 'mcp' ? 'mcp' : 'html';
        $keys   = isset($context['keys']) && is_array($context['keys'])
            ? array_values(array_map('strval', $context['keys']))
            : null;

        return ['format' => $format, 'keys' => $keys];
    }

    /**
     * Whether the caller wants this widget. Call it before running the widget's queries.
     *
     * @param array $context The filter's third argument.
     * @param string $key Widget key, e.g. 'woo_purchases'.
     * @return bool
     */
    public static function wantsWidget($context, $key)
    {
        return !is_array($context) || empty($context['keys']) || in_array((string) $key, $context['keys'], true);
    }

    /**
     * Whether the caller wants structured 'mcp' data instead of body_html.
     *
     * @param array $context The filter's third argument.
     * @return bool
     */
    public static function isMcpWidgetRequest($context)
    {
        return is_array($context) && isset($context['format']) && $context['format'] === 'mcp';
    }

    // Hooked to 'profile_update'.
    public function onWPProfileUpdate($userId, $userOldData, $userUpdatedData)
    {
        if (!$userId || !is_array($userUpdatedData)) {
            return false;
        }

        // Match on user_id only, so an account can only rewrite customer rows already linked to it.
        $customers = Customer::where('user_id', $userId)->get();

        if (!$customers || count($customers) === 0) {
            return false;
        }

        $keys = ['first_name', 'last_name', 'user_email'];

        if (array_diff_key(array_flip($keys), $userUpdatedData)) {
            return false;
        }

        // profile_update passes wp_insert_user()'s slashed data (O\'Brien).
        $userUpdatedData = wp_unslash($userUpdatedData);

        $email = $userUpdatedData['user_email'];

        // Only check authority when this update actually changed the account email.
        // Otherwise an admin editing a name would carry over an unverified address
        // the account already had. Core slashes the old email, so unslash it too.
        $previousAccountEmail = is_object($userOldData) && isset($userOldData->user_email)
            ? wp_unslash($userOldData->user_email)
            : '';

        // Unknown previous address: run the full check.
        $accountEmailMoved = !$previousAccountEmail
            || !self::isSameEmail($previousAccountEmail, $email);

        // Only a proven change may move the email; names always sync.
        $changeReason = $accountEmailMoved ? self::provenEmailChangeReason($userId, $email) : '';
        $emailIsProven = (bool) $changeReason;

        foreach ($customers as $customer) {
            $data = [
                'first_name' => $userUpdatedData['first_name'],
                'last_name'  => $userUpdatedData['last_name'],
            ];

            $previousEmail = $customer->email;

            // Don't take an address another customer holds. Duplicates make
            // email matching (e.g. mail piping) ambiguous.
            $emailMoved = $emailIsProven
                && !self::isSameEmail($previousEmail, $email)
                && !self::isEmailHeldByAnotherCustomer($email, $customer->id);

            if ($emailMoved) {
                $data['email'] = $email;
            }

            $customer->fill($data);
            $customer->save();

            if ($emailMoved) {
                self::onProvenEmailChange($customer, $previousEmail, $changeReason);
            }
        }

        return true;
    }

    /**
     * How the current email change was authorised: the owner confirmed it through
     * WordPress's link, or someone who can edit the account made it. '' otherwise.
     *
     * @param int $userId
     * @param string $newEmail
     * @return string 'verified', 'administrator', or ''
     */
    public static function provenEmailChangeReason($userId, $newEmail)
    {
        if (self::isWpConfirmedEmailChange($userId, $newEmail)) {
            return 'verified';
        }

        if (self::isAdminAuthorizedEmailChange($userId)) {
            return 'administrator';
        }

        return '';
    }

    /**
     * Whether someone else who can edit this WordPress account is changing its email.
     * Editing your own account never counts.
     *
     * On WooCommerce, shop_manager has edit_user over 'customer' accounts, so it passes
     * this without any Fluent Support permission. Requiring fst_sensitive_data would close that.
     *
     * @param int $userId
     * @return bool
     */
    public static function isAdminAuthorizedEmailChange($userId)
    {
        $actorId = get_current_user_id();

        if (!$actorId || $actorId === (int) $userId) {
            return false;
        }

        return current_user_can('edit_user', (int) $userId);
    }

    /**
     * Whether this email change came from WordPress's confirmation link.
     *
     * _new_email is still readable while profile_update fires, but the profile form
     * writes it without any click. Only the matching hash in the request proves the
     * link was opened.
     *
     * @param int $userId
     * @param string $newEmail
     * @return bool
     */
    public static function isWpConfirmedEmailChange($userId, $newEmail)
    {
        // Core only applies a confirmed change on the user's own profile page
        // (IS_PROFILE_PAGE). This stops a replayed hash via REST, WP-CLI or another admin.
        if (!defined('IS_PROFILE_PAGE') || !IS_PROFILE_PAGE) {
            return false;
        }

        $pending = get_user_meta($userId, '_new_email', true);

        if (!is_array($pending) || empty($pending['hash']) || empty($pending['newemail'])) {
            return false;
        }

        if (!self::isSameEmail($pending['newemail'], $newEmail)) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this compares a secret delivered to the user's own inbox; it does not act on request state
        $submitted = isset($_GET['newuseremail'])
            ? sanitize_text_field(wp_unslash($_GET['newuseremail']))
            : '';

        return $submitted && hash_equals($pending['hash'], $submitted);
    }

    /**
     * Side effects of a proven email change. Shared by every path that can move
     * the address, including EmailClaimService.
     *
     * @param \FluentSupport\App\Models\Customer $customer
     * @param string $previousEmail
     * @param string $reason 'verified', 'administrator', 'claimed' or 'agent'
     * @return void
     */
    public static function onProvenEmailChange($customer, $previousEmail, $reason = 'verified')
    {
        // Rotate hashes so signed links sent to the old inbox stop working.
        $tickets = Ticket::where('customer_id', $customer->id)->get();

        foreach ($tickets as $ticket) {
            $ticket->hash = bin2hex(random_bytes(16));
            $ticket->save();
        }

        Activity::create([
            'event_type'  => 'fluent_support/customer_email_changed',
            'person_id'   => $customer->id,
            'person_type' => 'customer',
            'object_id'   => $customer->id,
            'object_type' => 'customer',
            'description' => sprintf(
                self::emailChangeDescription($reason),
                $previousEmail,
                $customer->email
            )
        ]);

        /*
         * Fires after a customer's contact address moves to a newly proven one.
         *
         * @since v2.4.1
         * @param \FluentSupport\App\Models\Customer $customer
         * @param string $previousEmail
         * @param string $reason
         */
        do_action('fluent_support/customer_email_verified_change', $customer, $previousEmail, $reason);
    }

    /**
     * Activity log wording for each way an address can be proven.
     *
     * @param string $reason
     * @return string A sprintf format taking the previous and new addresses
     */
    protected static function emailChangeDescription($reason)
    {
        if ($reason === 'administrator') {
            // translators: 1: previous email address, 2: new email address
            return __('Contact email changed from %1$s to %2$s by an administrator editing the WordPress account.', 'fluent-support');
        }

        if ($reason === 'agent') {
            // translators: 1: previous email address, 2: new email address
            return __('Contact email changed from %1$s to %2$s by a support agent editing the customer.', 'fluent-support');
        }

        if ($reason === 'claimed') {
            // translators: 1: previous email address, 2: new email address
            return __('Contact email changed from %1$s to %2$s, confirmed by the account holder from the support portal.', 'fluent-support');
        }

        // translators: 1: previous email address, 2: new email address
        return __('Contact email changed from %1$s to %2$s, confirmed through WordPress email verification.', 'fluent-support');
    }

    /**
     * @param string $left
     * @param string $right
     * @return bool
     */
    protected static function isSameEmail($left, $right)
    {
        return strtolower(trim((string) $left)) === strtolower(trim((string) $right));
    }

    /**
     * Link unclaimed customer rows with this email to the new account.
     *
     * Safe at registration because WordPress sends the credentials to that inbox.
     * A later email change has no such proof, so we don't link then.
     *
     * @param int $userId
     * @return void
     */
    public function onWPUserRegister($userId)
    {
        $user = $userId ? get_user_by('ID', $userId) : false;

        if (!$user || !$user->user_email) {
            return;
        }

        /*
         * Filter whether a newly registered WordPress account adopts unlinked
         * customer rows that already carry its email address. Sites that let
         * visitors register with a self-chosen password may want this off.
         *
         * @since v2.4.1
         * @param bool $shouldLink
         * @param \WP_User $user
         */
        if (!apply_filters('fluent_support/link_customer_on_user_register', true, $user)) {
            return;
        }

        Customer::where('email', $user->user_email)
            ->unclaimed()
            ->update(['user_id' => $user->ID]);
    }

    /**
     * @param string $email
     * @param int $customerId
     * @return bool
     */
    protected static function isEmailHeldByAnotherCustomer($email, $customerId)
    {
        if (!$email) {
            return true;
        }

        return (bool) Customer::where('email', $email)
            ->where('id', '!=', $customerId)
            ->first();
    }
}
