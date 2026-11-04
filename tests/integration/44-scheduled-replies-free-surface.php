<?php
/**
 * Scheduled replies are a Pro feature. The free plugin only hands `scheduled_at` to Pro
 * through a filter, and keeps unsent `schedule` rows out of every thread it returns.
 *
 * The lab runs with Pro active, so "without Pro" cases unhook Pro's filter callbacks for the case.
 */

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\ResponseService;

return function () {

    $agent = Helper::getAgentByUserId();

    // Runs $fn with every callback on $hook removed, then puts them back
    $withoutCallbacks = function ($hook, callable $fn) {
        global $wp_filter;
        $saved = isset($wp_filter[$hook]) ? $wp_filter[$hook] : null;
        unset($wp_filter[$hook]);

        try {
            return $fn();
        } finally {
            if ($saved) {
                $wp_filter[$hook] = $saved;
            }
        }
    };

    // An unsent scheduled reply as Pro stores it
    $scheduledRow = function (Ticket $ticket) use ($agent) {
        return Conversation::create([
            'ticket_id'         => $ticket->id,
            'person_id'         => $agent->id,
            'conversation_type' => 'schedule',
            'content'           => '<p>Unsent ' . FsFactory::marker() . '</p>',
        ]);
    };

    FsTest::case('scheduled_at_with_no_scheduler_is_refused_and_nothing_is_sent', function () use ($withoutCallbacks) {
        $ticket = FsFactory::ticket();
        $before = Conversation::where('ticket_id', $ticket->id)->count();

        $result = $withoutCallbacks('fluent_support/schedule_response', function () use ($ticket) {
            return FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                'content'           => '<p>Later ' . FsFactory::marker() . '</p>',
                'conversation_type' => 'response',
                'scheduled_at'      => time() + HOUR_IN_SECONDS,
            ]);
        });

        FsTest::assert($result['status'] >= 400, 'a reply meant for later must not go out now when nothing can schedule it');
        FsTest::assertSame($before, Conversation::where('ticket_id', $ticket->id)->count(), 'no conversation is stored');
        FsTest::assertSame(0, (int) Ticket::find($ticket->id)->response_count, 'nothing counts as a response');
    });

    FsTest::case('ticket_view_leaves_scheduled_rows_out_of_the_thread', function () use ($scheduledRow) {
        $ticket = FsFactory::ticket();
        $row = $scheduledRow($ticket);

        $passed = [];
        $capture = function ($data, $viewedTicket, $scheduledRows) use (&$passed) {
            foreach ($scheduledRows as $scheduled) {
                $passed[] = (int) $scheduled->id;
            }
            return $data;
        };
        add_filter('fluent_support/ticket_view_data', $capture, 1, 3);

        try {
            $result = FsTest::rest('GET', '/tickets/' . $ticket->id);
        } finally {
            remove_filter('fluent_support/ticket_view_data', $capture, 1);
        }

        FsTest::assertHealthy($result, 'GET /tickets/{id}');
        $data = json_decode(wp_json_encode($result['data']), true);

        FsTest::assert(!in_array((int) $row->id, array_map('intval', array_column((array) $data['responses'], 'id')), true),
            'responses must not contain the unsent reply');
        FsTest::assert(!in_array((int) $row->id, array_map('intval', array_column((array) ($data['ticket']['responses'] ?? []), 'id')), true),
            'ticket.responses must not contain the unsent reply');
        FsTest::assertSame([(int) $row->id], $passed, 'the filter receives the left-out row so Pro can show it apart');
    });

    FsTest::case('response_delete_route_refuses_a_scheduled_row', function () use ($scheduledRow) {
        $ticket = FsFactory::ticket();
        $row = $scheduledRow($ticket);

        $result = FsTest::rest('DELETE', '/tickets/' . $ticket->id . '/responses/' . $row->id);

        FsTest::assert($result['status'] >= 400, 'a scheduled reply is cancelled through its own route, not deleted as a response');
        FsTest::assert((bool) Conversation::find($row->id), 'the row is still there');
    });

    FsTest::case('mcp_ticket_thread_leaves_out_scheduled_rows', function () use ($scheduledRow) {
        $ticket = FsFactory::ticket();
        $row = $scheduledRow($ticket);

        $result = TicketTools::getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (is_wp_error($result)) {
            FsTest::fail('get-ticket returned an error: ' . $result->get_error_message());
            return;
        }

        $data = json_decode(wp_json_encode($result['data']), true);
        FsTest::assert(!in_array((int) $row->id, array_map('intval', array_column((array) ($data['responses'] ?? []), 'id')), true),
            'an AI agent must not read an unsent reply as sent');
    });

    FsTest::case('responses_relation_leaves_out_scheduled_rows', function () use ($scheduledRow) {
        $ticket = FsFactory::ticket();
        $row = $scheduledRow($ticket);
        $unsentText = wp_strip_all_tags($row->content);

        FsTest::assert(!in_array((int) $row->id, array_map('intval', Ticket::find($ticket->id)->responses->pluck('id')->toArray()), true),
            'every reader of the thread (AI, audit, search) goes through this relation');

        $matched = Ticket::where('id', $ticket->id)->whereHas('responses', function ($q) use ($unsentText) {
            $q->where('content', 'LIKE', '%' . $unsentText . '%');
        })->count();
        FsTest::assertSame(0, $matched, 'ticket search must not match text that was never sent');
    });

    FsTest::case('mcp_latest_response_id_ignores_the_unsent_row', function () use ($scheduledRow, $agent) {
        $ticket = FsFactory::ticket();
        $sent = Conversation::create([
            'ticket_id'         => $ticket->id,
            'person_id'         => $agent->id,
            'conversation_type' => 'response',
            'content'           => '<p>Sent ' . FsFactory::marker() . '</p>',
        ]);
        $scheduledRow($ticket);

        $result = TicketTools::getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (is_wp_error($result)) {
            FsTest::fail('get-ticket returned an error: ' . $result->get_error_message());
            return;
        }

        $data = json_decode(wp_json_encode($result['data']), true);
        // A cursor past the unsent row would skip it; it gets a new id only when it is sent
        FsTest::assertSame((int) $sent->id, (int) ($data['responses_meta']['latest_response_id'] ?? 0), 'the cursor stops at the last sent conversation');
    });

    FsTest::case('a_published_held_reply_gets_an_id_newer_than_the_messages_before_it', function () use ($scheduledRow, $agent) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'active']);
        $held = $scheduledRow($ticket);
        $oldId = (int) $held->id;
        $attachment = Attachment::create([
            'ticket_id'       => $ticket->id,
            'conversation_id' => $oldId,
            'file_type'       => 'text/plain',
            'title'           => 'fixture-' . FsFactory::marker() . '.txt',
            'driver'          => 'local',
            'status'          => 'active',
        ]);
        $held->updateSettingsValue('cc_email', ['cc@example.invalid']);
        // The customer wrote after the reply was held back
        $customerReply = Conversation::create([
            'ticket_id'         => $ticket->id,
            'person_id'         => $customer->id,
            'conversation_type' => 'response',
            'content'           => '<p>Customer ' . FsFactory::marker() . '</p>',
        ]);

        $published = (new ResponseService())->publishPendingConversation($ticket, $held, $agent);
        $newId = (int) $published->id;

        FsTest::assert($newId > (int) $customerReply->id, 'the sent reply must sort after the customer reply by id, got ' . $newId);
        FsTest::assertSame($newId, (int) $held->id, 'the caller\'s object carries the new id');
        FsTest::assertSame(null, Conversation::find($oldId), 'the held row is gone');
        FsTest::assertSame('response', Conversation::find($newId)->conversation_type, 'the new row is a response');
        FsTest::assertSame((string) $held->content, (string) Conversation::find($newId)->content, 'content kept');
        FsTest::assertSame($newId, (int) Attachment::find($attachment->id)->conversation_id, 'attachment follows the reply');
        FsTest::assertSame(0, Meta::where('object_type', 'response')->where('object_id', $oldId)->count(), 'no meta left on the old id');
        FsTest::assertSame(['cc@example.invalid'], (array) Conversation::find($newId)->getSettingsValue('cc_email', []), 'settings follow the reply');
        FsTest::assertSame($newId, (int) Ticket::find($ticket->id)->getLastResponse()->id, 'the last response is the reply just sent');
    });

    FsTest::case('publishing_a_held_reply_works_while_the_notification_tables_do_not_exist', function () use ($scheduledRow, $agent) {
        global $wpdb;
        // The tables are only created when internal notifications are switched on
        $table = $wpdb->prefix . 'fs_notifications';
        $parked = $table . '_fstest_parked';
        $existed = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;

        $ticket = FsFactory::ticket(['status' => 'active']);
        $held = $scheduledRow($ticket);

        if ($existed) {
            $wpdb->query("RENAME TABLE `{$table}` TO `{$parked}`");
        }

        try {
            $published = (new ResponseService())->publishPendingConversation($ticket, $held, $agent);
            $newId = (int) $published->id;
        } finally {
            if ($existed) {
                $wpdb->query("RENAME TABLE `{$parked}` TO `{$table}`");
            }
        }

        FsTest::assertSame('response', Conversation::find($newId)->conversation_type, 'the held reply was sent');
        FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count(), 'exactly one sent reply on the ticket');
    });

    FsTest::case('publishing_a_held_reply_moves_its_notifications_to_the_new_id', function () use ($scheduledRow, $agent) {
        global $wpdb;
        $table = $wpdb->prefix . 'fs_notifications';
        $created = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table;

        \FluentSupport\Database\Migrations\NotificationsMigrator::migrate();
        \FluentSupport\Database\Migrations\NotificationUsersMigrator::migrate();

        $ticket = FsFactory::ticket(['status' => 'active']);
        $held = $scheduledRow($ticket);
        $oldId = (int) $held->id;
        $wpdb->insert($table, [
            'event_type'      => 'fstest_seed',
            'category'        => 'fstest',
            'ticket_id'       => $ticket->id,
            'conversation_id' => $oldId,
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        ]);
        $notificationId = (int) $wpdb->insert_id;

        try {
            $published = (new ResponseService())->publishPendingConversation($ticket, $held, $agent);

            FsTest::assertSame((int) $published->id, (int) $wpdb->get_var($wpdb->prepare("SELECT conversation_id FROM {$table} WHERE id = %d", $notificationId)), 'the notification follows the reply');
        } finally {
            $wpdb->delete($table, ['id' => $notificationId]);
            if ($created) {
                $wpdb->query("DROP TABLE IF EXISTS `{$table}`, `{$wpdb->prefix}fs_notification_users`");
            }
        }
    });

    FsTest::case('posting_a_schedule_type_directly_is_refused', function () {
        $ticket = FsFactory::ticket();
        $before = Conversation::where('ticket_id', $ticket->id)->count();

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => '<p>Bare ' . FsFactory::marker() . '</p>',
            'conversation_type' => 'schedule',
        ]);

        FsTest::assert($result['status'] >= 400, 'a schedule row without a send time or job would block scheduling on the ticket');
        FsTest::assertSame($before, Conversation::where('ticket_id', $ticket->id)->count(), 'no row is stored');
    });

    FsTest::case('ticket_list_runs_the_tickets_list_filter', function () {
        FsFactory::ticket();

        $seen = null;
        $capture = function ($tickets) use (&$seen) {
            $seen = $tickets;
            return $tickets;
        };
        add_filter('fluent_support/tickets_list', $capture, 1);

        try {
            $result = FsTest::rest('GET', '/tickets', ['per_page' => 15]);
        } finally {
            remove_filter('fluent_support/tickets_list', $capture, 1);
        }

        FsTest::assertHealthy($result, 'GET /tickets');
        FsTest::assert($seen instanceof \FluentSupport\Framework\Pagination\LengthAwarePaginator, 'the filter receives the page of tickets');
    });
};
