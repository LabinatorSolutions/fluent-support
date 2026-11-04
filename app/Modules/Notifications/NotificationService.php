<?php

namespace FluentSupport\App\Modules\Notifications;

use FluentSupport\App\Models\Notification;
use FluentSupport\App\Models\NotificationUser;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Database\Migrations\NotificationsMigrator;
use FluentSupport\Database\Migrations\NotificationUsersMigrator;
use FluentSupport\Framework\Support\Arr;

/**
 * In-app notifications for agents: settings, creating them, reading them.
 * Other code (MCP, the settings screen, the admin menu, email claiming) calls this.
 */
class NotificationService
{
    const OPTION_KEY = '_internal_notification_settings';

    const AGENT_MENTIONED = 'agent_mentioned';
    const AGENT_REPLIED = 'agent_replied';
    const TICKET_ASSIGNED = 'ticket_assigned';
    const TICKET_REASSIGNED = 'ticket_reassigned';
    const CUSTOMER_REPLIED = 'customer_replied';
    const TICKET_CLOSED = 'ticket_closed';
    const TICKET_REOPENED = 'ticket_reopened';
    const WORKFLOW_TRIGGERED = 'workflow_triggered';

    const MENTIONS = 'mentions';
    const TICKET_ACTIVITY = 'ticket_activity';
    const AUTOMATION_TRIGGERS = 'automation_triggers';

    /**
     * Every event: [category, the setting that turns it off (or null), icon].
     */
    const EVENTS = [
        self::AGENT_MENTIONED    => [self::MENTIONS, null, 'reply'],
        self::AGENT_REPLIED      => [self::TICKET_ACTIVITY, 'agent_reply_notifications', 'reply'],
        self::TICKET_ASSIGNED    => [self::TICKET_ACTIVITY, 'assignment_notifications', 'agent'],
        self::TICKET_REASSIGNED  => [self::TICKET_ACTIVITY, 'assignment_notifications', 'agent'],
        self::CUSTOMER_REPLIED   => [self::TICKET_ACTIVITY, 'customer_reply_notifications', 'reply'],
        self::TICKET_CLOSED      => [self::TICKET_ACTIVITY, 'status_notifications', 'closeTicket'],
        self::TICKET_REOPENED    => [self::TICKET_ACTIVITY, 'status_notifications', 'refresh'],
        self::WORKFLOW_TRIGGERED => [self::AUTOMATION_TRIGGERS, 'workflow_notifications', 'workflow'],
    ];

    /**
     * Rows written before the event names were settled.
     */
    const LEGACY_EVENTS = [
        'fluent_support/response_added_by_agent' => self::AGENT_REPLIED,
        'fluent_support/note_added_by_agent'     => self::AGENT_MENTIONED,
    ];

    /**
     * Notify $recipients (agent person IDs) of $event on a ticket. Nothing happens
     * while the feature or the event is off; the actor is left out unless self
     * notifications are allowed.
     *
     * @param string   $event One of the event constants.
     * @param array    $data  ticket_id, actor_id, conversation_id, payload.
     * @param int[]    $recipients
     * @return Notification|null
     */
    public static function notify($event, array $data, array $recipients)
    {
        $settings = self::settings();

        if ($settings['enabled'] !== 'yes' || !self::isEventEnabled($event, $settings)) {
            return null;
        }

        $recipients = array_values(array_unique(array_filter(array_map('intval', $recipients))));
        $actorId = (int) Arr::get($data, 'actor_id');

        if ($actorId && $settings['self_notifications'] !== 'yes') {
            $recipients = array_values(array_diff($recipients, [$actorId]));
        }

        if (!$recipients || !self::tablesReady(true)) {
            return null;
        }

        return (new Notification())->getConnection()->transaction(function () use ($event, $data, $recipients) {
            $notification = Notification::create([
                'actor_id'        => Arr::get($data, 'actor_id'),
                'ticket_id'       => Arr::get($data, 'ticket_id'),
                'conversation_id' => Arr::get($data, 'conversation_id'),
                'event_type'      => $event,
                'category'        => self::EVENTS[$event][0],
                'payload'         => Arr::get($data, 'payload', [])
            ]);

            $now = current_time('mysql');
            NotificationUser::insertOrIgnore(array_map(function ($personId) use ($notification, $now) {
                return ['notification_id' => $notification->id, 'user_id' => $personId, 'channel' => 'web', 'is_read' => 0, 'created_at' => $now, 'updated_at' => $now];
            }, $recipients));

            return $notification;
        });
    }

    /**
     * The agent's notifications, newest first, limited to tickets the agent can
     * still see.
     *
     * @param int   $personId
     * @param array $filters status (read, unread), category, ticket_id.
     * @return \FluentSupport\Framework\Database\Orm\Builder|null Null while the feature is off.
     */
    public static function query($personId, array $filters = [])
    {
        if (!self::tablesReady(true)) {
            return null;
        }

        $status = Arr::get($filters, 'status');
        $query = Notification::whereHas('recipients', function ($q) use ($personId, $status) {
            $q->where('user_id', $personId);
            if ($status === 'unread' || $status === 'read') {
                $q->where('is_read', $status === 'read' ? 1 : 0);
            }
        })->with([
            'actor'      => fn($q) => $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']),
            'ticket'     => fn($q) => $q->select(['id', 'title']),
            'recipients' => fn($q) => $q->select(['id', 'notification_id', 'user_id', 'is_read', 'read_at'])->where('user_id', $personId),
        ]);

        return self::scope($query, $filters)->orderBy('created_at', 'DESC');
    }

    /**
     * @param int   $personId
     * @param array $filters category, ticket_id.
     * @return int
     */
    public static function unreadCount($personId, array $filters = [])
    {
        if (!self::tablesReady(true)) {
            return 0;
        }

        return (int) NotificationUser::where('user_id', $personId)->unread()
            ->whereHas('notification', fn($q) => self::scope($q, $filters))
            ->count();
    }

    /**
     * Mark one of the agent's notifications read, or all of them.
     *
     * @param int      $personId
     * @param int|null $notificationId
     * @return int Rows changed.
     */
    public static function markRead($personId, $notificationId = null)
    {
        if (!self::tablesReady()) {
            return 0;
        }

        $query = NotificationUser::where('user_id', $personId)->where('is_read', 0);
        if ($notificationId) {
            $query->where('notification_id', (int) $notificationId);
        }

        $now = current_time('mysql');
        return $query->update(['is_read' => 1, 'read_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Agent IDs mentioned in content: <span class="fs_agent_mention" data-mention-username="7">
     * from the rich editor, or @[7:Display Name] from the plain one.
     *
     * @param string $content
     * @return int[]
     */
    public static function mentionedAgentIds($content)
    {
        $content = (string) $content;

        preg_match_all('/<span\b[^>]*\bclass=["\'][^"\']*\bfs_agent_mention\b[^"\']*["\'][^>]*\bdata-mention-username=["\'](\d+)["\'][^>]*>/i', $content, $spans);
        preg_match_all('/@\[(\d+):[^\]]+\]/', $content, $tokens);

        return array_values(array_unique(array_filter(array_map('intval', array_merge($spans[1], $tokens[1])))));
    }

    /**
     * The saved settings, read fresh. 'enabled' follows the business setting.
     *
     * @return array
     */
    public static function settings()
    {
        $settings = wp_parse_args(Helper::getOption(self::OPTION_KEY, []), self::defaults());
        $business = Helper::getOption('global_business_settings', []);
        $settings['enabled'] = Arr::get($business, 'internal_notifications_enabled');

        foreach (self::defaults() as $key => $default) {
            $settings[$key] = Arr::get($settings, $key) === 'yes' ? 'yes' : 'no';
        }

        return Arr::only($settings, array_keys(self::defaults()));
    }

    /**
     * @param array $settings
     * @return mixed
     */
    public static function saveSettings(array $settings)
    {
        $settings = wp_parse_args($settings, self::defaults());
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = Arr::get($settings, $key) === 'yes' ? 'yes' : 'no';
        }

        if ($settings['enabled'] === 'yes') {
            self::tablesReady(true);
        }

        return Helper::updateOption(self::OPTION_KEY, Arr::only($settings, array_keys(self::defaults())));
    }

    public static function isEnabled()
    {
        return self::settings()['enabled'] === 'yes';
    }

    /**
     * @param string     $event
     * @param array|null $settings
     * @return bool
     */
    public static function isEventEnabled($event, $settings = null)
    {
        $setting = self::EVENTS[$event][1] ?? null;

        return !$setting || Arr::get($settings ?: self::settings(), $setting, 'yes') === 'yes';
    }

    /**
     * The settings screen's fields.
     *
     * @return array
     */
    public static function settingsFields()
    {
        $fields = [
            'enabled' => [
                'type'           => 'inline-checkbox',
                'true_label'     => 'yes',
                'false-label'    => 'no',
                'checkbox_label' => __('Enable Internal Notifications', 'fluent-support'),
                'inline_help'    => __('Enable the in-app notification bell, unread counts, and notification event storage for agents.', 'fluent-support')
            ]
        ];

        $toggles = [
            'assignment_notifications'     => [__('Enable Assignment Notifications', 'fluent-support'), ''],
            'customer_reply_notifications' => [__('Enable Customer Reply Notifications', 'fluent-support'), ''],
            'workflow_notifications'       => [__('Enable Workflow Notifications', 'fluent-support'), ''],
            'status_notifications'         => [__('Enable Ticket Status Notifications', 'fluent-support'), ''],
            'agent_reply_notifications'    => [__('Notify Assigned Agent When Another Agent Replies', 'fluent-support'), ''],
            'self_notifications'           => [__('Allow Self Notifications', 'fluent-support'), __('By default, agents are not notified for their own actions. Enable this only if agents should receive notifications when they are already the normal recipient for an action they performed themselves.', 'fluent-support')],
        ];

        foreach ($toggles as $key => list($label, $help)) {
            $fields[$key] = array_filter([
                'type'           => 'inline-checkbox',
                'true_label'     => 'yes',
                'false-label'    => 'no',
                'checkbox_label' => $label,
                'inline_help'    => $help,
                'dependency'     => ['depends_on' => 'enabled', 'operator' => '=', 'value' => 'yes']
            ]);
        }

        return $fields;
    }

    /**
     * The filter options for the notification list.
     *
     * @return array
     */
    public static function categories()
    {
        return [
            ['key' => 'all', 'label' => __('All', 'fluent-support')],
            ['key' => self::MENTIONS, 'label' => __('Mentions', 'fluent-support')],
            ['key' => self::TICKET_ACTIVITY, 'label' => __('Ticket Activity', 'fluent-support')],
            ['key' => self::AUTOMATION_TRIGGERS, 'label' => __('Automation Triggers', 'fluent-support')]
        ];
    }

    /**
     * Whether the notification tables are there; with $create, create them first
     * while the feature is on.
     *
     * @param bool $create
     * @return bool
     */
    public static function tablesReady($create = false)
    {
        if (!self::isEnabled()) {
            return false;
        }

        if ($create) {
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            NotificationsMigrator::migrate();
            NotificationUsersMigrator::migrate();
        }

        return self::tablesExist();
    }

    public static function tablesExist()
    {
        global $wpdb;

        foreach ([NotificationsMigrator::$tableName, NotificationUsersMigrator::$tableName] as $table) {
            $table = $wpdb->prefix . $table;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                return false;
            }
        }

        return true;
    }

    private static function defaults()
    {
        return [
            'enabled'                      => 'no',
            'assignment_notifications'     => 'yes',
            'agent_reply_notifications'    => 'yes',
            'customer_reply_notifications' => 'yes',
            'workflow_notifications'       => 'yes',
            'status_notifications'         => 'yes',
            'self_notifications'           => 'no'
        ];
    }

    /**
     * Filters shared by the list and the count, plus the ticket access scope: the
     * same hooks that limit the agent's ticket list.
     */
    private static function scope($query, array $filters)
    {
        $query->whereHas('ticket', function ($ticketQuery) {
            (new AgentTicketAccess())->applyListScope($ticketQuery);
        });

        $category = Arr::get($filters, 'category');
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($ticketId = Arr::get($filters, 'ticket_id')) {
            $query->where('ticket_id', (int) $ticketId);
        }

        return $query;
    }
}
