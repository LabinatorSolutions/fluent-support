<?php
/**
 * In-app agent notifications: which ticket events notify whom, and the shape
 * of the five notifications/* endpoints the bell and the notifications page
 * read (resources/admin/stores/notification.store.js).
 *
 * - A customer reply, another agent's reply, a close and a re-open notify the
 *   assigned agent; an assignment notifies the new assignee ("reassigned" when
 *   someone had it before); a mention notifies the mentioned agents who can
 *   see the ticket.
 * - Agents aren't notified of their own actions, a turned-off event creates
 *   nothing, and turning the feature off creates nothing.
 * - Lists and counts only include tickets the agent can still see.
 *
 * Settings are read fresh: run this alone to check old code, which cached them.
 * The agent users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Notification;
use FluentSupport\App\Models\NotificationUser;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    $adminId = get_current_user_id();
    $originalBusiness = Helper::getOption('global_business_settings', []);
    $originalSettings = Helper::getOption('_internal_notification_settings', []);

    $setNotifications = function ($enabled, array $settings = []) use ($originalBusiness) {
        Helper::updateOption('global_business_settings', array_merge((array) $originalBusiness, ['internal_notifications_enabled' => $enabled ? 'yes' : 'no']));
        Helper::updateOption('_internal_notification_settings', array_merge([
            'enabled'                      => $enabled ? 'yes' : 'no',
            'assignment_notifications'     => 'yes',
            'agent_reply_notifications'    => 'yes',
            'customer_reply_notifications' => 'yes',
            'workflow_notifications'       => 'yes',
            'status_notifications'         => 'yes',
            'self_notifications'           => 'no',
        ], $settings));
    };

    $userIds = [];
    $makeAgent = function ($slug, array $permissions) use (&$userIds) {
        $login = FsTest::uniq('fs-notify-' . $slug);
        $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create agent user: ' . $userId->get_error_message());
        }
        $userIds[] = $userId;
        $agent = FsFactory::agent(['user_id' => $userId, 'first_name' => ucfirst($slug)]);
        PermissionManager::attachPermissions($userId, $permissions);
        return $agent;
    };

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // [event_type, recipient person ids] of the notifications for $ticketId, oldest first.
    $notificationsFor = function ($ticketId) {
        $rows = [];
        foreach (Notification::where('ticket_id', $ticketId)->orderBy('id')->get() as $notification) {
            $recipients = array_map('intval', NotificationUser::where('notification_id', $notification->id)->pluck('user_id')->toArray());
            sort($recipients);
            $rows[] = [$notification->event_type, $recipients];
        }
        return $rows;
    };

    $keysOf = function ($array) {
        $keys = array_keys((array) $array);
        sort($keys);
        return $keys;
    };

    $managePerms = ['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_other_tickets', 'fst_manage_unassigned_tickets'];

    try {
        $setNotifications(true);
        (new \FluentSupport\Database\Migrations\NotificationsMigrator())::migrate();
        (new \FluentSupport\Database\Migrations\NotificationUsersMigrator())::migrate();

        $owner = $makeAgent('owner', $managePerms);
        $other = $makeAgent('other', $managePerms);
        $restrictedBox = FsFactory::mailbox();
        $restricted = $makeAgent('restricted', $managePerms);
        $restricted->updateMeta('agent_restrictions', ['businessBoxRestrictions' => true, 'restrictedBusinessBoxes' => [(int) $restrictedBox->id]]);

        $customer = FsFactory::customer();

        FsTest::case('notify_events_reach_the_right_agents', function () use ($owner, $other, $restricted, $customer, $restrictedBox, $notificationsFor, $runAs, $adminId) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id]);
            $inRestrictedBox = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id, 'agent_id' => $owner->id]);

            $runAs($adminId);
            (new TicketService())->assignAgent(Ticket::find($ticket->id), $owner, $other);
            (new TicketService())->assignAgent(Ticket::find($ticket->id), $other, $owner);
            (new TicketService())->assignAgent(Ticket::find($ticket->id), $owner, $other);

            (new ResponseService())->createResponse(['content' => '<p>Customer says hi</p>'], $customer, Ticket::find($ticket->id));
            (new ResponseService())->createResponse(['content' => '<p>Another agent answers</p>'], $other, Ticket::find($ticket->id));
            (new ResponseService())->createResponse(['content' => '<p>My own reply</p>'], $owner, Ticket::find($ticket->id));
            (new ResponseService())->createResponse([
                'content'           => '<p>Heads up @[' . $other->id . ':Other] and @[' . $restricted->id . ':Restricted]</p>',
                'conversation_type' => 'note'
            ], $owner, Ticket::find($ticket->id));

            (new TicketService())->close(Ticket::find($ticket->id), $other);
            (new TicketService())->reopen(Ticket::find($ticket->id), $other);

            // A mention on a ticket in a mailbox the mentioned agent can't see notifies nobody.
            (new ResponseService())->createResponse([
                'content'           => '<p>See this @[' . $restricted->id . ':Restricted]</p>',
                'conversation_type' => 'note'
            ], $owner, Ticket::find($inRestrictedBox->id));

            FsTest::assertSame([
                ['ticket_assigned', [(int) $owner->id]],
                ['ticket_reassigned', [(int) $other->id]],
                ['ticket_reassigned', [(int) $owner->id]],
                ['customer_replied', [(int) $owner->id]],
                ['agent_replied', [(int) $owner->id]],
                // The restricted agent can see this ticket: it is in the default mailbox.
                ['agent_mentioned', [(int) $other->id, (int) $restricted->id]],
                ['ticket_closed', [(int) $owner->id]],
                ['ticket_reopened', [(int) $owner->id]],
            ], $notificationsFor($ticket->id), 'events and recipients (the own reply notifies nobody)');
            FsTest::assertSame([], $notificationsFor($inRestrictedBox->id), 'no mention for an agent who cannot see the ticket');
        });

        FsTest::case('notify_respects_the_settings', function () use ($owner, $other, $customer, $notificationsFor, $setNotifications) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $owner->id]);

            $setNotifications(true, ['customer_reply_notifications' => 'no']);
            (new ResponseService())->createResponse(['content' => '<p>Event off</p>'], $customer, Ticket::find($ticket->id));

            $setNotifications(false);
            (new ResponseService())->createResponse(['content' => '<p>Feature off</p>'], $customer, Ticket::find($ticket->id));
            (new TicketService())->close(Ticket::find($ticket->id), $other);

            $setNotifications(true, ['self_notifications' => 'yes']);
            (new ResponseService())->createResponse(['content' => '<p>Self on</p>'], $owner, Ticket::find($ticket->id));
            $setNotifications(true);

            FsTest::assertSame([['agent_replied', [(int) $owner->id]]], $notificationsFor($ticket->id), 'only the self-notification once allowed');
        });

        FsTest::case('notify_endpoints_contract', function () use ($owner, $other, $customer, $restrictedBox, $runAs, $adminId, $keysOf) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $owner->id]);
            (new ResponseService())->createResponse(['content' => '<p>Customer asks</p>'], $customer, Ticket::find($ticket->id));
            (new ResponseService())->createResponse(['content' => '<p>Mention @[' . $owner->id . ':Owner]</p>', 'conversation_type' => 'note'], $other, Ticket::find($ticket->id));

            $runAs($owner->user_id);
            try {
                $list = FsTest::rest('GET', '/notifications', ['per_page' => 50, 'ticket_id' => $ticket->id]);
                FsTest::assertHealthy($list, 'list');
                FsTest::assertSame(['meta', 'notification_categories', 'notifications', 'unread_count'], $keysOf($list['data']), 'list keys');
                FsTest::assertSame(['categories'], $keysOf($list['data']['meta']), 'meta keys');
                FsTest::assertSame(['all', 'mentions', 'ticket_activity', 'automation_triggers'], array_column($list['data']['notification_categories'], 'key'), 'categories');

                $page = json_decode(wp_json_encode($list['data']['notifications']), true);
                FsTest::assertSame(2, count($page['data']), 'two notifications on the ticket');
                FsTest::assertSame(2, (int) $page['total'], 'paginated total');

                $mention = $page['data'][0];
                FsTest::assertSame(['actor', 'actor_id', 'category', 'conversation_id', 'created_at', 'event_icon', 'event_label', 'event_type', 'id', 'payload', 'recipients', 'summary', 'ticket', 'ticket_id', 'updated_at'], $keysOf($mention), 'item keys');
                FsTest::assertSame('agent_mentioned', $mention['event_type'], 'newest first');
                FsTest::assertSame('mentions', $mention['category'], 'category');
                FsTest::assertSame(['full_name', 'id', 'photo'], $keysOf($mention['actor']), 'actor keys');
                FsTest::assertSame(['id', 'title'], $keysOf($mention['ticket']), 'ticket keys');
                FsTest::assertSame(['id', 'is_read', 'read_at', 'user_id'], $keysOf($mention['recipients'][0]), 'recipient keys');
                FsTest::assertSame(['content_preview', 'conversation_type', 'mentioned_handles', 'ticket_title'], $keysOf($mention['payload']), 'mention payload keys');
                FsTest::assertSame('mentioned you', $mention['event_label'], 'label');
                FsTest::assertSame('reply', $mention['event_icon'], 'icon');
                FsTest::assertSame('Mention @[' . $owner->id . ':Owner]', $mention['payload']['content_preview'], 'preview');

                $reply = $page['data'][1];
                FsTest::assertSame(['content_preview', 'conversation_type', 'customer', 'ticket_priority', 'ticket_title'], $keysOf($reply['payload']), 'customer reply payload keys');
                FsTest::assertSame(['id', 'name', 'person_type'], $keysOf($reply['payload']['customer']), 'payload person keys: no email');

                $unread = FsTest::rest('GET', '/notifications/unread', ['limit' => 1]);
                FsTest::assertHealthy($unread, 'unread');
                FsTest::assertSame(['meta', 'notification_categories', 'notifications', 'unread_count'], $keysOf($unread['data']), 'unread keys');
                FsTest::assertSame(1, count($unread['data']['notifications']), 'limit');

                $count = FsTest::rest('GET', '/notifications/unread-count', ['category' => 'mentions', 'ticket_id' => $ticket->id]);
                FsTest::assertSame(['count' => 1], $count['data'], 'unread mentions on the ticket');

                $markOne = FsTest::rest('POST', '/notifications/' . $mention['id'] . '/mark-read');
                FsTest::assertSame(['unread_count', 'updated'], $keysOf($markOne['data']), 'mark-read keys');
                FsTest::assertSame(1, (int) $markOne['data']['updated'], 'one marked');
                FsTest::assertSame(1, (int) FsTest::rest('GET', '/notifications/unread-count', ['ticket_id' => $ticket->id])['data']['count'], 'one left on the ticket');

                $markAll = FsTest::rest('POST', '/notifications/mark-all-read');
                FsTest::assertSame(['unread_count', 'updated'], $keysOf($markAll['data']), 'mark-all-read keys');
                FsTest::assertSame(0, (int) $markAll['data']['unread_count'], 'nothing unread');

                // Another agent's notification can't be marked read by this agent.
                $othersId = (int) NotificationUser::where('user_id', $other->id)->value('notification_id');
                if ($othersId) {
                    FsTest::assertSame(0, (int) FsTest::rest('POST', '/notifications/' . $othersId . '/mark-read')['data']['updated'], 'not mine to mark');
                }
            } finally {
                $runAs($adminId);
            }
        });

        FsTest::case('notify_lists_only_tickets_the_agent_can_see', function () use ($restricted, $owner, $customer, $restrictedBox, $runAs, $adminId) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $restricted->id]);
            (new ResponseService())->createResponse(['content' => '<p>Before the move</p>'], $customer, Ticket::find($ticket->id));
            Ticket::where('id', $ticket->id)->update(['mailbox_id' => $restrictedBox->id]);

            $runAs($restricted->user_id);
            try {
                $list = FsTest::rest('GET', '/notifications', ['ticket_id' => $ticket->id]);
                $count = FsTest::rest('GET', '/notifications/unread-count', ['ticket_id' => $ticket->id]);
            } finally {
                $runAs($adminId);
            }

            FsTest::assertSame(0, (int) json_decode(wp_json_encode($list['data']['notifications']), true)['total'], 'hidden in the list');
            FsTest::assertSame(0, (int) $count['data']['count'], 'not counted');
        });

        FsTest::case('notify_endpoints_need_an_agent', function () use ($runAs, $adminId) {
            $login = FsTest::uniq('fs-notify-plain');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'administrator']);
            $runAs($userId);
            try {
                $result = FsTest::rest('GET', '/notifications');
            } finally {
                $runAs($adminId);
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($userId);
            }
            FsTest::assertSame(422, (int) $result['status'], 'status');
            FsTest::assertSame('Sorry, You do not have permission. Please add yourself as support agent first', $result['message'], 'message');
        });
    } finally {
        $runAs($adminId);
        Helper::updateOption('global_business_settings', $originalBusiness);
        Helper::updateOption('_internal_notification_settings', $originalSettings);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
