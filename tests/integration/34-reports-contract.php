<?php
/**
 * Reports: every report endpoint the admin UI calls, with exact numbers.
 *
 * The fixtures are dated in a window no other data uses (5-8 February 2001),
 * so ranged reports count only them:
 *
 *   T1 Mon 05 10:15  new     agent A  mailbox M  product P  waiting on agent
 *   T2 Mon 05 14:30  active  agent me mailbox M
 *   T3 Tue 06 09:00  closed  agent me mailbox M  resolved Wed 07 16:00
 *   T4 Thu 08 11:00  closed  agent A  mailbox M  product P  resolved Thu 08 12:00
 *
 *   replies: A on T1 Mon 05 11:00; me on T2 Mon 05 15:00, on T3 Tue 06 10:00
 *   and Wed 07 15:00; the customer on T2 Mon 05 16:00; a note by me on T2
 *   Mon 05 17:00 (never counted as a reply).
 *
 * "me" is the admin running the suite. Charts list every bucket of the range
 * (end day included); weekly buckets start at `from`, monthly ones are months.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {
    $proActive = defined('FLUENTSUPPORTPRO');
    $adminId = get_current_user_id();
    $me = Helper::getAgentByUserId($adminId);
    $from = '2001-02-05';
    $to = '2001-02-08';
    $userIds = [];

    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };

    $mailbox = FsFactory::mailbox();
    $product = FsFactory::product();
    $agentA = FsFactory::agent();
    $customer = FsFactory::customer();

    $ticket = function ($createdAt, $status, $agent, array $extra = []) use ($mailbox, $customer) {
        $ticket = FsFactory::ticket(array_merge(['mailbox_id' => $mailbox->id, 'customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => $status], $extra));
        $dates = array_merge(['created_at' => $createdAt, 'updated_at' => $createdAt], array_intersect_key($extra, array_flip(['resolved_at', 'waiting_since', 'last_customer_response', 'last_agent_response'])));
        Ticket::where('id', $ticket->id)->toBase()->update($dates);
        return Ticket::find($ticket->id);
    };
    $reply = function ($ticket, $person, $at, $type = 'response') {
        $conversation = FsFactory::conversation($ticket, ['person_id' => $person->id, 'conversation_type' => $type]);
        Conversation::where('id', $conversation->id)->toBase()->update(['created_at' => $at, 'updated_at' => $at]);
    };

    $t1 = $ticket('2001-02-05 10:15:00', 'new', $agentA, ['product_id' => $product->id, 'waiting_since' => '2001-02-05 10:15:00', 'last_customer_response' => '2001-02-05 10:15:00']);
    $t2 = $ticket('2001-02-05 14:30:00', 'active', $me);
    $t3 = $ticket('2001-02-06 09:00:00', 'closed', $me, ['resolved_at' => '2001-02-07 16:00:00']);
    $t4 = $ticket('2001-02-08 11:00:00', 'closed', $agentA, ['product_id' => $product->id, 'resolved_at' => '2001-02-08 12:00:00']);

    $reply($t1, $agentA, '2001-02-05 11:00:00');
    $reply($t2, $me, '2001-02-05 15:00:00');
    $reply($t3, $me, '2001-02-06 10:00:00');
    $reply($t3, $me, '2001-02-07 15:00:00');
    $reply($t2, $customer, '2001-02-05 16:00:00');
    $reply($t2, $me, '2001-02-05 17:00:00', 'note');

    $days = function (array $counts) {
        return array_merge(['2001-02-05' => 0, '2001-02-06' => 0, '2001-02-07' => 0, '2001-02-08' => 0], $counts);
    };
    $counts = function ($overall) {
        return array_map(fn($row) => (int) $row['count'], (array) $overall);
    };

    try {
        // ------------------------------------------------------- reports/stats

        FsTest::case('reports/stats counts a mailbox in a date range', function () use ($rest, $mailbox, $from, $to, $counts) {
            $result = $rest('GET', '/reports/stats', ['mailbox_id' => $mailbox->id, 'date_range' => [$from, $to]]);
            FsTest::assertHealthy($result, 'GET reports/stats');
            $overall = $result['data']['overall_reports'] ?? [];
            FsTest::assertSame(['new_tickets' => 1, 'active_tickets' => 1, 'closed_tickets' => 2, 'responses' => 5, 'open_tickets' => 2], $counts($overall), 'counts');
            FsTest::assertSame('Responses', $overall['responses']['title'] ?? null, 'responses label');
        });

        FsTest::case('reports/stats for one agent counts their tickets and the replies they wrote', function () use ($rest, $mailbox, $me, $from, $to, $counts) {
            $result = $rest('GET', '/reports/stats', ['mailbox_id' => $mailbox->id, 'agent_id' => $me->id, 'date_range' => [$from, $to]]);
            $overall = $result['data']['overall_reports'] ?? [];
            FsTest::assertSame(['new_tickets' => 0, 'closed_tickets' => 1, 'responses' => 3, 'open_tickets' => 1], $counts($overall), 'counts for one agent');
            FsTest::assertSame('Total Responses', $overall['responses']['title'] ?? null, 'responses label for one agent');
        });

        FsTest::case('reports/stats with a product and a one-day range', function () use ($rest, $product, $counts) {
            $result = $rest('GET', '/reports/stats', ['product_id' => $product->id, 'date_range' => ['2001-02-08', '2001-02-08']]);
            FsTest::assertSame(['new_tickets' => 0, 'active_tickets' => 0, 'closed_tickets' => 1, 'responses' => 0, 'open_tickets' => 0], $counts($result['data']['overall_reports'] ?? []), 'product counts on 8 Feb');
        });

        // ------------------------------------------------------------- charts

        FsTest::case('my-reports charts: my closed tickets and my replies per day, end day included', function () use ($rest, $from, $to, $days) {
            $resolved = $rest('GET', '/my-reports/tickets-resolve-growth', ['date_range' => [$from, $to]]);
            FsTest::assertHealthy($resolved, 'GET my-reports/tickets-resolve-growth');
            FsTest::assertSame($days(['2001-02-07' => 1]), $resolved['data']['stats'] ?? null, 'my closed tickets per day');

            $replies = $rest('GET', '/my-reports/response-growth', ['date_range' => [$from, $to]]);
            FsTest::assertSame($days(['2001-02-05' => 1, '2001-02-06' => 1, '2001-02-07' => 1]), $replies['data']['stats'] ?? null, 'my replies per day');
        });

        FsTest::case('charts bucket by hour, week and month by the date they count', function () use ($rest) {
            $hourly = $rest('GET', '/my-reports/tickets-resolve-growth', ['date_range' => ['2001-02-07', '2001-02-07']])['data']['stats'] ?? [];
            FsTest::assertSame(24, count($hourly), 'hourly buckets');
            FsTest::assertSame(1, $hourly['16:00 - 16:59'] ?? null, 'closed at 16:00 (resolved_at, not created_at)');
            FsTest::assertSame(1, array_sum($hourly), 'hourly total');

            $weekly = $rest('GET', '/my-reports/response-growth', ['date_range' => ['2001-01-01', '2001-04-30']])['data']['stats'] ?? [];
            FsTest::assertSame(18, count($weekly), 'weekly buckets for 120 days');
            FsTest::assertSame('2001-01-01', array_key_first($weekly), 'first weekly bucket');
            FsTest::assertSame(3, $weekly['2001-02-05'] ?? null, 'replies in the week of 5 Feb');
            FsTest::assertSame(3, array_sum($weekly), 'weekly total');

            $monthly = $rest('GET', '/my-reports/tickets-resolve-growth', ['date_range' => ['2001-01-01', '2001-12-31']])['data']['stats'] ?? [];
            FsTest::assertSame(12, count($monthly), 'monthly buckets');
            FsTest::assertSame(1, $monthly['Feb 2001'] ?? null, 'closed in Feb 2001');
        });

        // ------------------------------------------------------------ summary

        FsTest::case('my-reports/my-summary: my closed tickets, replies and tickets replied to', function () use ($rest, $from, $to) {
            $result = $rest('GET', '/my-reports/my-summary', ['from' => $from, 'to' => $to]);
            FsTest::assertHealthy($result, 'GET my-reports/my-summary');
            $row = $result['data']['summary'][0] ?? [];
            FsTest::assertSame(1, (int) ($row['stats']['closed'] ?? -1), 'closed');
            FsTest::assertSame(3, (int) ($row['stats']['responses'] ?? -1), 'responses');
            FsTest::assertSame(2, (int) ($row['stats']['interactions'] ?? -1), 'interactions');
            foreach (['waiting_tickets', 'average_waiting', 'max_waiting'] as $key) {
                FsTest::assert(array_key_exists($key, (array) ($row['active_stat'] ?? [])), "active_stat has no '$key'");
            }
        });

        FsTest::case('pro: reports/agents-summary row for one agent', function () use ($rest, $proActive, $agentA, $from, $to) {
            if (!$proActive) {
                FsTest::skip('Pro is not active');
                return;
            }
            $result = $rest('GET', '/reports/agents-summary', ['from' => $from, 'to' => $to]);
            FsTest::assertHealthy($result, 'GET reports/agents-summary');
            $rows = array_values(array_filter((array) ($result['data']['summary'] ?? []), fn($row) => (int) $row['id'] === (int) $agentA->id));
            $row = $rows[0] ?? [];
            $stats = array_map('intval', array_intersect_key((array) ($row['stats'] ?? []), array_flip(['closed', 'opens', 'responses', 'interactions'])));
            ksort($stats);
            FsTest::assertSame(['closed' => 1, 'interactions' => 1, 'opens' => 1, 'responses' => 1], $stats, 'agent A stats');
            FsTest::assertSame(1, (int) ($row['active_stat']['waiting_tickets'] ?? -1), 'agent A waiting tickets');
            foreach (['id', 'first_name', 'last_name', 'email', 'full_name', 'photo'] as $key) {
                FsTest::assert(array_key_exists($key, $row), "summary row has no '$key'");
            }
        });

        // ------------------------------------------------- day-time grid (pro)

        FsTest::case('pro: reports/day-time-stats counts by weekday and hour', function () use ($rest, $proActive, $agentA, $from, $to) {
            if (!$proActive) {
                FsTest::skip('Pro is not active');
                return;
            }
            $grid = function ($params) use ($rest, $from, $to) {
                return $rest('GET', '/reports/day-time-stats', $params + ['date_range' => [$from, $to]])['data']['stats'] ?? [];
            };

            $tickets = $grid(['report_type' => 'ticket']);
            FsTest::assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], array_keys($tickets), 'weekdays');
            FsTest::assertSame(24, count($tickets['Mon'] ?? []), 'hours per day');
            FsTest::assertSame([1, 1, 1, 1], [$tickets['Mon']['10:00'], $tickets['Mon']['14:00'], $tickets['Tue']['9:00'], $tickets['Thu']['11:00']], 'ticket cells');
            FsTest::assertSame(4, array_sum(array_map('array_sum', $tickets)), 'ticket total');

            $agentReplies = $grid(['report_type' => 'agent_response', 'agent_id' => $agentA->id]);
            FsTest::assertSame(1, $agentReplies['Mon']['11:00'] ?? null, 'agent A reply cell');
            FsTest::assertSame(1, array_sum(array_map('array_sum', $agentReplies)), 'agent A reply total');

            $customerReplies = $grid(['report_type' => 'customer_response']);
            FsTest::assertSame(1, $customerReplies['Mon']['16:00'] ?? null, 'customer reply cell');
            FsTest::assertSame(1, array_sum(array_map('array_sum', $customerReplies)), 'customer reply total');
        });

        // ------------------------------------------------- mailbox charts (pro)

        FsTest::case('pro: mailbox-reports charts for one mailbox (replies = agent replies written that day)', function () use ($rest, $proActive, $mailbox, $from, $to, $days) {
            if (!$proActive) {
                FsTest::skip('Pro is not active');
                return;
            }
            $chart = function ($name, $type = null) use ($rest, $mailbox, $from, $to) {
                $params = ['mailbox_id' => $mailbox->id, 'date_range' => [$from, $to]];
                if ($type) {
                    $params['type'] = $type;
                }
                $result = $rest('GET', '/mailbox-reports/' . $name, $params);
                FsTest::assertHealthy($result, 'GET mailbox-reports/' . $name);
                return $result['data']['stats'] ?? null;
            };

            FsTest::assertSame($days(['2001-02-05' => 2, '2001-02-06' => 1, '2001-02-08' => 1]), $chart('tickets-growth'), 'new tickets per day');
            FsTest::assertSame($days(['2001-02-07' => 1, '2001-02-08' => 1]), $chart('tickets-resolve-growth', 'mailbox'), 'closed tickets per day');
            FsTest::assertSame($days(['2001-02-05' => 2, '2001-02-06' => 1, '2001-02-07' => 1]), $chart('response-growth', 'mailbox'), 'agent replies per day');
        });

        // ---------------------------------------------------------- dashboard

        FsTest::case('tickets/my_stats: dashboard stats and widgets', function () use ($rest, $product) {
            $result = $rest('GET', '/tickets/my_stats', ['with' => ['overall_stats', 'tickets_by_products', 'suggested_tickets']]);
            FsTest::assertHealthy($result, 'GET tickets/my_stats');
            $data = $result['data'];
            foreach (['new_tickets', 'active_tickets', 'closed_tickets', 'responses', 'interactions'] as $key) {
                FsTest::assert(isset($data['stats'][$key]['title'], $data['stats'][$key]['count']), "stats has no '$key'");
            }
            foreach (['waiting_tickets', 'average_waiting', 'max_waiting'] as $key) {
                FsTest::assert(array_key_exists($key, (array) ($data['overall_stats'] ?? [])), "overall_stats has no '$key'");
            }
            FsTest::assertSame(1, (int) ($data['tickets_by_product'][$product->id]['count'] ?? -1), 'awaiting tickets for the fixture product');
            FsTest::assert(is_array($data['suggested_tickets'] ?? null), 'suggested_tickets');
        });

        // -------------------------------------------------------------- MCP

        FsTest::case('MCP support insights: volume, times, waiting and one agent\'s row', function () {
            $insights = fn($params = []) => json_decode(wp_json_encode(\FluentSupport\App\Modules\MCP\Tools\ManagementTools::getSupportInsights($params + ['period' => '7d'])['data']), true);
            $now = current_time('mysql');
            $ago = fn($seconds) => wp_date('Y-m-d H:i:s', time() - $seconds);

            $before = $insights();
            $agentC = FsFactory::agent(['first_name' => 'Cy']);
            $open = FsFactory::ticket(['agent_id' => $agentC->id, 'status' => 'active']);
            Ticket::where('id', $open->id)->toBase()->update(['created_at' => $ago(HOUR_IN_SECONDS * 3), 'first_response_time' => 3600, 'waiting_since' => $ago(HOUR_IN_SECONDS), 'last_customer_response' => $ago(HOUR_IN_SECONDS), 'last_agent_response' => $ago(HOUR_IN_SECONDS * 2)]);
            $closed = FsFactory::ticket(['agent_id' => $agentC->id, 'status' => 'closed']);
            Ticket::where('id', $closed->id)->toBase()->update(['created_at' => $ago(DAY_IN_SECONDS * 2), 'resolved_at' => $now, 'total_close_time' => 7200, 'first_response_time' => 0]);
            $answered = FsFactory::ticket(['status' => 'active']);
            Ticket::where('id', $answered->id)->toBase()->update(['created_at' => $ago(DAY_IN_SECONDS * 10), 'waiting_since' => $ago(HOUR_IN_SECONDS), 'last_customer_response' => $ago(HOUR_IN_SECONDS * 2), 'last_agent_response' => $ago(HOUR_IN_SECONDS)]);
            // A second ticket we answered, and one owed a reply with no agent reply
            // yet, so each waiting count differs from what a swapped rule gives.
            $answeredToo = FsFactory::ticket(['status' => 'active']);
            Ticket::where('id', $answeredToo->id)->toBase()->update(['created_at' => $ago(DAY_IN_SECONDS * 10), 'waiting_since' => $ago(HOUR_IN_SECONDS), 'last_customer_response' => $ago(HOUR_IN_SECONDS * 3), 'last_agent_response' => $ago(HOUR_IN_SECONDS)]);
            $owed = FsFactory::ticket(['status' => 'active']);
            Ticket::where('id', $owed->id)->toBase()->update(['created_at' => $ago(DAY_IN_SECONDS * 10), 'waiting_since' => $ago(HOUR_IN_SECONDS), 'last_customer_response' => $ago(HOUR_IN_SECONDS), 'last_agent_response' => null]);
            $after = $insights();

            FsTest::assertSame(['created' => $before['volume']['created'] + 2, 'closed' => $before['volume']['closed'] + 1], $after['volume'], 'volume');
            FsTest::assertSame($before['first_response_time']['count'] + 1, $after['first_response_time']['count'], 'first-response count');
            FsTest::assertSame($before['resolution_time']['count'] + 1, $after['resolution_time']['count'], 'resolution count');
            FsTest::assertSame($before['waiting']['waiting_on_agent']['count'] + 2, $after['waiting']['waiting_on_agent']['count'], 'waiting on agent');
            FsTest::assertSame($before['waiting']['waiting_on_customer']['count'] + 2, $after['waiting']['waiting_on_customer']['count'], 'waiting on customer');
            foreach (['count', 'average', 'median', 'max', 'min'] as $key) {
                FsTest::assert(array_key_exists($key, $after['resolution_time']), "resolution_time has no '$key'");
            }
            foreach (['count', 'average', 'max', 'median'] as $key) {
                FsTest::assert(array_key_exists($key, $after['waiting']['waiting_on_agent']), "waiting_on_agent has no '$key'");
            }

            $row = $insights(['agent_id' => $agentC->id])['agent_performance'][0] ?? [];
            FsTest::assertSame([
                'agent_id'           => (int) $agentC->id,
                'agent_name'         => $agentC->full_name,
                'open_tickets'       => 1,
                'closed_in_period'   => 1,
                'avg_first_response' => \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::formatDuration(3600),
                'avg_resolution'     => \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::formatDuration(7200),
            ], array_merge($row, ['agent_id' => (int) ($row['agent_id'] ?? 0)]), 'agent C row');
        });

        // ------------------------------------------------------- restrictions

        FsTest::case('a mailbox-restricted viewer gets zeros for that mailbox', function () use ($rest, $proActive, $mailbox, $from, $to, &$userIds, $adminId, $counts) {
            $login = FsTest::uniq('fs-reports-viewer');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
            $userIds[] = $userId;
            $viewer = FsFactory::agent(['user_id' => $userId]);
            $viewer->updateMeta('agent_restrictions', ['businessBoxRestrictions' => true, 'restrictedBusinessBoxes' => [(int) $mailbox->id]]);
            PermissionManager::attachPermissions($userId, ['fst_view_all_reports', 'fst_sensitive_data', 'fst_view_tickets', 'fst_manage_unassigned_tickets']);
            $unassigned = FsFactory::ticket(['mailbox_id' => $mailbox->id, 'status' => 'active']);
            $adminUnassigned = (int) ($rest('GET', '/tickets/my_stats')['data']['stats']['unassigned_tickets']['count'] ?? -1);

            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
            try {
                $dashboard = $rest('GET', '/tickets/my_stats');
                $restrictedUnassigned = Ticket::whereNull('agent_id')->where('status', '!=', 'closed')->where('mailbox_id', $mailbox->id)->count();
                FsTest::assert($restrictedUnassigned >= 1, 'fixture: an unassigned ticket in the restricted mailbox');
                FsTest::assertSame($adminUnassigned - $restrictedUnassigned, (int) ($dashboard['data']['stats']['unassigned_tickets']['count'] ?? -1), 'unassigned card leaves out the restricted mailbox');

                $stats = $rest('GET', '/reports/stats', ['mailbox_id' => $mailbox->id, 'date_range' => [$from, $to]]);
                FsTest::assertSame(200, $stats['status'], 'restricted viewer reads reports/stats');
                FsTest::assertSame(0, array_sum($counts($stats['data']['overall_reports'] ?? [])), 'restricted mailbox counts');

                if ($proActive) {
                    $chart = $rest('GET', '/mailbox-reports/tickets-growth', ['mailbox_id' => $mailbox->id, 'date_range' => [$from, $to]]);
                    FsTest::assertSame(200, $chart['status'], 'restricted viewer reads mailbox-reports/tickets-growth');
                    FsTest::assertSame(0, array_sum((array) ($chart['data']['stats'] ?? [1])), 'restricted mailbox chart');
                }
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
                AgentTicketAccess::resetCache();
            }
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
