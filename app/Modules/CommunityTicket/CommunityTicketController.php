<?php

namespace FluentSupport\App\Modules\CommunityTicket;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Creates a ticket for the author of a FluentCommunity post or comment.
 */
class CommunityTicketController extends Controller
{
    const SOURCE = 'fluent_community';

    // A lock older than this belongs to a request that died.
    const LOCK_TTL = 60;

    // Customer emails a new ticket can trigger; held back for a silent create.
    const CUSTOMER_CREATED_EMAILS = [
        'ticket_created_email_to_customer',
        'ticket_created_by_agent_email_to_customer',
        'ticket_created_by_agent_on_behalf_email_to_customer',
    ];

    /** @var TicketService|null */
    private $ticketService;

    public function create(Request $request)
    {
        $type = $request->getSafe('type', 'sanitize_text_field');
        $id = $request->getInt('id');
        $notify = filter_var($request->get('notify', true), FILTER_VALIDATE_BOOLEAN);

        $source = $this->loadSource($type, $id);
        $metaKey = '_fcom_' . $type . '_' . $id;

        if (!$this->acquireLock($metaKey)) {
            throw new ApiException(__('A ticket is already being created for this item. Please try again in a moment.', 'fluent-support'), 409);
        }

        try {
            $existing = $this->findExistingTicket($metaKey);
            if ($existing) {
                $this->ensureCanAccessTicket($existing);

                return $this->ticketResult($existing, true);
            }

            $customer = $this->resolveCustomer($source['author']);
            $ticket = $this->storeWithMeta($source, $customer, $metaKey);
            $this->fireCreatedHooks($ticket, $customer, $notify);

            // Re-read: a created hook (an assignment workflow) may have assigned it to this agent.
            return $this->ticketResult(Ticket::find($ticket->id) ?: $ticket, false);
        } finally {
            $this->releaseLock($metaKey);
        }
    }

    /**
     * The visible post or comment, with its author and the ticket text.
     *
     * @return array{author: \WP_User, title: string, content: string, permalink: string}
     */
    private function loadSource($type, $id)
    {
        if (!in_array($type, ['feed', 'comment'], true) || !$id) {
            throw ApiException::invalid(__('Invalid post or comment.', 'fluent-support'));
        }

        if ($type === 'feed') {
            $item = \FluentCommunity\App\Models\Feed::where('status', 'published')->where('id', $id)->first();
            $feed = $item;
        } else {
            $item = \FluentCommunity\App\Models\Comment::where('status', 'published')->where('id', $id)->first();
            $feed = $item ? \FluentCommunity\App\Models\Feed::where('status', 'published')->where('id', $item->post_id)->first() : null;
        }

        // Main-feed posts have no space; a post in a space needs the space to exist.
        if (!$item || !$feed || ($feed->space_id && !$feed->space)) {
            throw ApiException::notFound();
        }

        $author = get_userdata((int) $item->user_id);
        $profile = $item->xprofile;
        if (!$author || !is_email($author->user_email)) {
            throw ApiException::invalid(__('The author of this item has no email address.', 'fluent-support'));
        }
        if (!$profile || $profile->status !== 'active') {
            throw ApiException::invalid(__('The author of this item is not an active community member.', 'fluent-support'));
        }

        $text = (string) $item->message_rendered;
        // A comment's ticket is titled after its post, so the agent sees what it is about.
        $title = trim((string) $feed->title);
        if (!$title) {
            $title = wp_html_excerpt(trim(wp_strip_all_tags($text)), 80, '…');
        }

        $permalink = $feed->getPermalink();
        $content = Helper::decodeNonMarkupEntities($text);
        /* translators: %s: link to the post in the community */
        $content .= '<p>' . sprintf(__('Original community post: %s', 'fluent-support'), '<a href="' . esc_url($permalink) . '">' . esc_url($permalink) . '</a>') . '</p>';

        return [
            'author'    => $author,
            'title'     => $title ?: __('Community post', 'fluent-support'),
            'content'   => wp_kses_post($content),
            'permalink' => $permalink,
        ];
    }

    private function findExistingTicket($metaKey)
    {
        $meta = Meta::where('object_type', 'ticket')->where('key', $metaKey)->first();

        return $meta ? Ticket::find($meta->object_id) : null;
    }

    /**
     * The customer linked to the user, else the one with the user's email, else a new one.
     * CustomerService::find() ignores the email when a user id is given, so look both up here.
     */
    private function resolveCustomer(\WP_User $author)
    {
        $customer = Customer::where('user_id', $author->ID)->orderBy('id', 'ASC')->first()
            ?: Customer::where('email', $author->user_email)->orderBy('id', 'ASC')->first();

        if (!$customer) {
            $customer = CustomerService::findOrCreate([
                'user_id' => $author->ID,
                'email'   => $author->user_email,
            ]);
        }

        if (!$customer || !$customer->exists) {
            throw ApiException::invalid(__('Customer could not be created', 'fluent-support'));
        }

        return $customer;
    }

    /**
     * The ticket and its lookup meta are one transaction, so a failure never leaves a
     * ticket the retry cannot find. Hooks run after the commit (see fireCreatedHooks()).
     */
    private function storeWithMeta(array $source, $customer, $metaKey)
    {
        global $wpdb;

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Could not start database transaction: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        try {
            $ticket = $this->ticketService()->storeTicket([
                'customer_id' => $customer->id,
                'title'       => $source['title'],
                'content'     => $source['content'],
                'source'      => self::SOURCE,
            ], $customer, true);

            Meta::create([
                'object_type' => 'ticket',
                'object_id'   => $ticket->id,
                'key'         => $metaKey,
                'value'       => $source['permalink'],
            ]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        return $ticket;
    }

    /**
     * Only after the commit, so no listener sees a ticket the rollback would remove.
     * A silent create holds back the customer's created emails for this ticket only.
     */
    private function fireCreatedHooks($ticket, $customer, $notify)
    {
        $quiet = null;
        if (!$notify) {
            $quiet = function ($shouldSend, $channel, $type, $created = null) use ($ticket) {
                if ($channel === 'email'
                    && $created && (int) $created->id === (int) $ticket->id
                    && in_array($type, self::CUSTOMER_CREATED_EMAILS, true)) {
                    return false;
                }

                return $shouldSend;
            };
            add_filter('fluent_support/should_send_notification', $quiet, 10, 4);
        }

        try {
            $this->ticketService()->fireCreatedHooks($ticket, $customer);
        } finally {
            if ($quiet) {
                remove_filter('fluent_support/should_send_notification', $quiet, 10);
            }
        }
    }

    /**
     * No URL when the agent may not open the ticket: one who manages only their own tickets
     * can create a ticket here but not open it while it is unassigned.
     */
    private function ticketResult($ticket, $alreadyExists)
    {
        return [
            'ticket_id'      => (int) $ticket->id,
            // The number the admin shows (prefix and serial), not the row id.
            'ticket_number'  => (string) $ticket->display_ticket_number,
            'ticket_url'     => PermissionManager::canAccessTicket($ticket)
                ? admin_url('admin.php?page=fluent-support#/tickets/' . (int) $ticket->id . '/view')
                : null,
            'already_exists' => $alreadyExists,
        ];
    }

    /**
     * The same instance must store the ticket and fire its hooks: it holds the pending state.
     */
    private function ticketService()
    {
        if (!$this->ticketService) {
            $this->ticketService = new TicketService();
        }

        return $this->ticketService;
    }

    /**
     * An options-table row as the lock, as WP_Upgrader::create_lock() does: INSERT IGNORE
     * is atomic, and a stale row is taken over by a conditional UPDATE that only one
     * request can win.
     */
    private function acquireLock($metaKey)
    {
        global $wpdb;

        $name = '_fcom_lock' . $metaKey;
        $now = time();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')", $name, $now))) {
            return true;
        }

        $held = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", $name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ($held !== '' && (int) $held > $now - self::LOCK_TTL) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->query($wpdb->prepare("UPDATE `$wpdb->options` SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, $name, $held));
    }

    private function releaseLock($metaKey)
    {
        global $wpdb;

        $wpdb->delete($wpdb->options, ['option_name' => '_fcom_lock' . $metaKey]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}
