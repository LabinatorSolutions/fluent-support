<?php
/**
 * MCP triage and discovery: the needs-attention lists only show tickets waiting
 * on an agent, list previews keep words apart, the inbox email summary shows the
 * real template with the agent's message and signature marked, and every tool
 * declares its safety hints.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\AbilitiesRegistrar;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\MCP\MCPInit;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;

return function () {

    $context = function () {
        ManagementTools::invalidateSupportContextCache();
        return json_decode(wp_json_encode(ManagementTools::getSupportContext([])['data']), true);
    };

    // The context lists hold ticket ids; the rows are under tickets.
    $ids = function (array $list) {
        return array_map('intval', $list);
    };

    FsTest::case('needs_attention_lists_skip_tickets_waiting_on_the_customer', function () use ($context, $ids) {
        // Dates older than any real row, so both fixtures sort to the top of each list.
        // Set with a query update: the model does not mass-assign these columns.
        $at = function (array $times) {
            $ticket = FsFactory::ticket();
            Ticket::where('id', $ticket->id)->update(array_merge(['created_at' => '1990-01-01 00:00:00'], $times));
            return $ticket;
        };
        $answered = $at([
            'waiting_since'          => '1990-01-02 00:00:00',
            'last_customer_response' => '1990-01-01 00:00:00',
            'last_agent_response'    => '1990-01-02 00:00:00',
        ]);
        $waiting = $at([
            'waiting_since'          => '1990-01-03 00:00:00',
            'last_customer_response' => '1990-01-03 00:00:00',
            'last_agent_response'    => '1990-01-02 00:00:00',
        ]);

        try {
            $attention = $context()['needs_attention'];

            $longest = $ids($attention['longest_waiting']);
            FsTest::assert(!in_array((int) $answered->id, $longest, true), 'longest_waiting must skip a ticket we already answered');
            FsTest::assert(in_array((int) $waiting->id, $longest, true), 'longest_waiting must list a ticket waiting on an agent');

            $overdue = $ids($attention['sla_breach']['overdue_resolution']);
            FsTest::assert(!in_array((int) $answered->id, $overdue, true), 'overdue_resolution must skip a ticket waiting on the customer');
            FsTest::assert(in_array((int) $waiting->id, $overdue, true), 'overdue_resolution must list a ticket waiting on an agent');
        } finally {
            ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('context_returns_each_ticket_row_once_and_other_sections_on_request', function () {
        // Oldest waiting ticket, past every SLA target: it is in longest_waiting and overdue_resolution.
        $ticket = FsFactory::ticket();
        Ticket::where('id', $ticket->id)->update([
            'created_at'             => '1990-01-01 00:00:00',
            'waiting_since'          => '1990-01-01 00:00:00',
            'last_customer_response' => '1990-01-01 00:00:00',
            'last_agent_response'    => null,
        ]);

        $get = function ($params) {
            ManagementTools::invalidateSupportContextCache();
            $result = ManagementTools::getSupportContext($params);
            return is_wp_error($result) ? $result : json_decode(wp_json_encode($result['data']), true);
        };

        try {
            $data = $get([]);
            $id = (int) $ticket->id;

            FsTest::assert(in_array($id, $data['needs_attention']['longest_waiting'], true), 'longest_waiting holds the ticket id');
            FsTest::assert(in_array($id, $data['needs_attention']['sla_breach']['overdue_resolution'], true), 'overdue_resolution holds the ticket id');

            $rowIds = array_map('intval', array_column($data['tickets'], 'id'));
            FsTest::assertSame(1, count(array_keys($rowIds, $id, true)), 'the ticket row is returned once');
            FsTest::assertSame(count($rowIds), count(array_unique($rowIds)), 'no ticket row is repeated');

            $listed = array_merge(
                $data['my_queue']['awaiting_your_reply'],
                $data['my_queue']['awaiting_customer'],
                $data['needs_attention']['unassigned'],
                $data['needs_attention']['longest_waiting'],
                $data['needs_attention']['critical'],
                $data['needs_attention']['sla_breach']['no_first_response'],
                $data['needs_attention']['sla_breach']['overdue_resolution']
            );
            FsTest::assertSame([], array_values(array_diff($listed, $rowIds)), 'every listed id has its row under tickets');

            // The default result is the triage set; reference data is opt-in.
            $optIn = ['views', 'agents', 'products', 'mailboxes', 'tags', 'uploads'];
            FsTest::assertSame([], array_values(array_intersect($optIn, array_keys($data))), 'opt-in sections are not in the default result');
            FsTest::assertSame($optIn, $data['sections']['available'], 'the result names the sections it left out');
            FsTest::assert(strpos($data['sections']['how'], 'include=') !== false, 'and says how to get them');
            // A client that does not show the server instructions still gets the safety rules.
            $rules = implode(' ', $data['rules'] ?? []);
            foreach (['untrusted', 'never guess', 'real customers', '[YOUR MESSAGE]', 'only when a person asks'] as $needle) {
                FsTest::assert(strpos($rules, $needle) !== false, 'rules mention: ' . $needle);
            }
            FsTest::assertSame($data['rules'], $get(['include' => []])['rules'], 'rules come with every section set');

            foreach (['you', 'stats', 'my_queue', 'needs_attention', 'guidelines', 'site_timezone'] as $key) {
                FsTest::assert(array_key_exists($key, $data), 'default result has ' . $key);
            }

            $all = $get(['include' => ['all']]);
            FsTest::assertSame([], array_values(array_diff(ManagementTools::CONTEXT_SECTIONS, array_keys($all))), 'include=["all"] returns every section');

            $write = $get(['include' => ['mailboxes', 'agents']]);
            FsTest::assert(isset($write['mailboxes'], $write['agents']), 'asked sections are returned');
            FsTest::assert(!isset($write['my_queue']) && !isset($write['tickets']), 'sections not asked for are left out');

            $bad = $get(['include' => ['nope']]);
            FsTest::assertSame('invalid_param', is_wp_error($bad) ? $bad->get_error_code() : 'returned data', 'unknown section is refused');
        } finally {
            ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('invalidating_the_context_cache_shows_new_tickets_at_once', function () use ($ids) {
        // Prime the cache, then add a ticket the cached copy cannot know about.
        ManagementTools::invalidateSupportContextCache();
        // Each section set has its own cache entry; one invalidation clears them all.
        $sets = [[], ['include' => ['all']], ['include' => ['needs_attention']]];
        foreach ($sets as $set) {
            ManagementTools::getSupportContext($set);
        }

        $ticket = FsFactory::ticket();
        Ticket::where('id', $ticket->id)->update([
            'waiting_since'          => '1990-01-01 00:00:00',
            'last_customer_response' => '1990-01-01 00:00:00',
        ]);

        try {
            ManagementTools::invalidateSupportContextCache();
            $data = json_decode(wp_json_encode(ManagementTools::getSupportContext([])['data']), true);

            FsTest::assert(in_array((int) $ticket->id, $ids($data['needs_attention']['longest_waiting']), true),
                'after invalidation the context must be rebuilt, not served from cache');

            foreach ($sets as $set) {
                $data = json_decode(wp_json_encode(ManagementTools::getSupportContext($set)['data']), true);
                FsTest::assert(in_array((int) $ticket->id, $ids($data['needs_attention']['longest_waiting']), true),
                    'rebuilt for ' . wp_json_encode($set));
            }
        } finally {
            ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('preview_keeps_words_apart_across_blocks_and_line_breaks', function () {
        $preview = MCPHelper::preview('<p><strong>Your Name</strong></p><p>Pedro</p><div>Email</div>Hello,<br>the<br />payment');

        FsTest::assertSame('Your Name Pedro Email Hello, the payment', $preview, 'preview');
        FsTest::assertSame('one two', MCPHelper::preview('<p>one&nbsp; &nbsp;two</p>'), 'a non-breaking space is one space');
    });

    FsTest::case('preview_and_plain_text_decode_html5_entities', function () {
        // &apos; is not in the HTML 4.01 table that html_entity_decode() uses by default.
        FsTest::assertSame('It\'s "fine" & done', MCPHelper::preview('<p>It&apos;s &quot;fine&quot; &amp; done</p>'), 'preview');
        FsTest::assertSame('It\'s plain & simple', MCPHelper::htmlToMarkdown('It&apos;s plain &amp; simple'), 'plain text content');
    });

    FsTest::case('duration_never_shows_60_minutes_or_24_hours', function () {
        $expected = [
            59     => '59s',
            3599   => '1h 0m',
            7170   => '2h 0m',
            86399  => '1d 0h',
            90000  => '1d 1h',
            430199 => '4d 23h',
            430200 => '5d 0h',
        ];

        $actual = [];
        foreach (array_keys($expected) as $seconds) {
            $actual[$seconds] = MCPHelper::formatDuration($seconds);
        }

        FsTest::assertSame($expected, $actual, 'durations');
    });

    FsTest::case('inbox_email_summary_shows_the_template_the_customer_gets', function () {
        $agent = MCPHelper::resolveAgent();
        $box = FsFactory::mailbox(['box_type' => 'email']);
        $key = 'ticket_replied_by_agent_email_to_customer';

        $box->saveMeta('_email_' . $key, [
            'status'        => 'yes',
            'email_subject' => 'Re: {{ticket.title}}',
            'email_body'    => '<p>{{response.content}}</p><p>{{agent.signature}}</p><p>------</p><p>To add comments, <a href="{{ticket.public_url}}">click here</a></p>',
        ]);

        $signed = ManagementTools::describeAgentEmail($box, $key, $agent, '<p>Jewel, Support Lead</p>');
        FsTest::assert(strpos($signed['template'], '[YOUR MESSAGE]') !== false, 'marks where the message goes, got: ' . $signed['template']);
        FsTest::assert(strpos($signed['template'], '[YOUR SIGNATURE]') !== false, 'marks where the signature goes');
        FsTest::assert(strpos($signed['template'], 'click here') !== false, 'keeps the footer text');
        FsTest::assertSame(true, $signed['adds_signature'], 'adds the signature');
        FsTest::assert(!array_key_exists('adds_sign_off', $signed), 'no guessed sign-off field');

        FsTest::assert(strpos($signed['signature'] ?? '', 'Support Lead') !== false, 'the signature text comes with the email');
        FsTest::assert(strpos($signed['note'], 'added automatically') !== false, 'note says the signature is added');
        FsTest::assert(strpos($signed['note'], 'never type') !== false, 'note says the markers are for reading only');

        // The context call (compact) shows the signature once, under you.signature, and the
        // shared rule once, in the server instructions: no per-mailbox copies of either.
        $inContext = ManagementTools::describeAgentEmail($box, $key, $agent, '<p>Jewel, Support Lead</p>', true);
        FsTest::assert(!array_key_exists('signature', $inContext), 'no per-mailbox copy of the signature');
        FsTest::assert(!array_key_exists('note', $inContext), 'no per-mailbox copy of the shared note');
        FsTest::assertSame(true, $inContext['adds_signature'], 'the per-mailbox fact stays');

        // No signature set, or no {{agent.signature}} in the template: the agent hears the same thing.
        $unsigned = ManagementTools::describeAgentEmail($box, $key, $agent, '');

        $box->saveMeta('_email_' . $key, [
            'status'        => 'yes',
            'email_subject' => 'Re: {{ticket.title}}',
            'email_body'    => '<p>{{response.content}}</p><p>Regards,<br>{{agent.full_name}}</p>',
        ]);
        $noSlot = ManagementTools::describeAgentEmail($box, $key, $agent, '<p>Jewel, Support Lead</p>');

        foreach (['no signature set' => $unsigned, 'no signature slot' => $noSlot] as $label => $email) {
            FsTest::assertSame(false, $email['adds_signature'], $label . ': adds_signature');
            FsTest::assert(!array_key_exists('signature', $email), $label . ': no signature text');
            FsTest::assert(strpos($email['template'], '[YOUR SIGNATURE]') === false, $label . ': no signature marker');
            FsTest::assert(strpos($email['note'], 'No signature is added automatically.') !== false, $label . ': plain note, got: ' . $email['note']);
        }
    });

    FsTest::case('outreach_email_summary_sees_the_agent_text_in_ticket_content', function () {
        $agent = MCPHelper::resolveAgent();
        $box = FsFactory::mailbox(['box_type' => 'web']);
        $key = 'ticket_created_by_agent_on_behalf_email_to_customer';

        // The shape of the shipped web outreach template.
        $box->saveMeta('_email_' . $key, [
            'status'        => 'yes',
            'email_subject' => 'Your ticket has been created (#{{ticket.id}})',
            'email_body'    => '<p>Hi <strong>{{customer.full_name}}</strong>,</p><p>A support agent has created a ticket for you.</p><p>{{ticket.content}}</p><p>Best regards,<br>{{agent.full_name}}</p>',
        ]);
        $outreach = ManagementTools::describeAgentEmail($box, $key, $agent, '');

        FsTest::assertSame(true, $outreach['includes_reply'], 'the outreach email carries the agent text');
        FsTest::assert(strpos($outreach['template'], 'Hi') !== false && strpos($outreach['template'], 'Best regards') !== false, 'greeting and sign-off are visible in the template');
        FsTest::assert(strpos($outreach['template'], '[YOUR MESSAGE]') !== false, 'ticket.content is marked as the agent message');
    });

    FsTest::case('every_mcp_tool_declares_read_only_and_destructive_hints', function () {
        foreach (AbilitiesRegistrar::getDefinitions() as $name => $definition) {
            $hints = $definition['annotations'] ?? [];
            FsTest::assert(array_key_exists('readonly', $hints), $name . ' must declare readonly');
            FsTest::assert(!empty($hints['readonly']) || array_key_exists('destructive', $hints), $name . ' must declare destructive');
            FsTest::assert(array_key_exists('openWorldHint', $hints), $name . ' must declare openWorldHint');
        }
    });

    FsTest::case('pro_only_tools_are_listed_only_when_pro_is_active', function () {
        $without = MCPInit::exposedAbilityNames(false);
        $with = MCPInit::exposedAbilityNames(true);

        foreach (AbilitiesRegistrar::PRO_ONLY as $name) {
            FsTest::assert(!in_array($name, $without, true), $name . ' must be hidden without Pro');
            FsTest::assert(in_array($name, $with, true), $name . ' must be listed with Pro');
        }
        FsTest::assert(in_array('fluent-support/list-tickets', $without, true), 'free tools stay listed');
    });

    FsTest::case('failed_fluenthub_install_is_reported_not_claimed', function () {
        if (is_dir(WP_PLUGIN_DIR . '/fluent-toolkit')) {
            FsTest::skip('FluentHub is installed in this lab');
            return;
        }

        // Stand in for Pro's installer: never download anything in a test.
        global $wp_filter;
        $saved = isset($wp_filter['fluent_toolkit/do_auto_install']) ? $wp_filter['fluent_toolkit/do_auto_install'] : null;
        remove_all_actions('fluent_toolkit/do_auto_install');
        $canInstall = function () {
            return true;
        };
        $failingInstall = function () {
            do_action('fluent_toolkit/auto_install_failed', new WP_Error('download_failed', 'Download timed out.'));
        };
        add_filter('fluent_toolkit/can_auto_install', $canInstall, 99);
        add_action('fluent_toolkit/do_auto_install', $failingInstall);

        try {
            $response = FsTest::rest('POST', '/settings/mcp/install-adapter');
            $message = (string) ($response['data']['message'] ?? $response['message'] ?? '');

            FsTest::assert($response['status'] >= 400, 'a failed install is an error, got status ' . $response['status']);
            FsTest::assert(strpos($message, 'Download timed out.') !== false, 'the reason reaches the admin, got: ' . $message);
            FsTest::assert(stripos($message, 'was installed') === false, 'never claims FluentHub was installed');
        } finally {
            remove_filter('fluent_toolkit/can_auto_install', $canInstall, 99);
            remove_all_actions('fluent_toolkit/do_auto_install');
            if ($saved) {
                $wp_filter['fluent_toolkit/do_auto_install'] = $saved;
            }
        }
    });

    FsTest::case('agent_who_can_send_replies_gets_no_permission_note', function () use ($context) {
        $you = $context()['you'];

        FsTest::assertSame('send', $you['replies'] ?? null, 'replies mode');
        FsTest::assert(!array_key_exists('replies_note', $you), 'no note for an agent with full reply access');
    });

    FsTest::case('insights_window_uses_site_time', function () use ($context) {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'Asia/Dhaka');

        try {
            $insights = function () {
                return json_decode(wp_json_encode(ManagementTools::getSupportInsights(['period' => '7d'])['data']), true);
            };
            $createdAt = function ($secondsAgo) {
                $ticket = FsFactory::ticket();
                Ticket::where('id', $ticket->id)->update(['created_at' => wp_date('Y-m-d H:i:s', time() - $secondsAgo)]);
                return $ticket;
            };

            $before = $insights();
            $createdAt(7 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS); // just outside, in site time
            $afterOutside = $insights();
            $createdAt(7 * DAY_IN_SECONDS - 3 * HOUR_IN_SECONDS); // just inside
            $afterInside = $insights();

            FsTest::assertSame($before['volume']['created'], $afterOutside['volume']['created'], 'a ticket 3h before the window is not counted');
            FsTest::assertSame($before['volume']['created'] + 1, $afterInside['volume']['created'], 'a ticket 3h inside the window is counted');

            FsTest::assert(substr((string) $afterInside['from'], -6) === '+06:00', 'from is in site time, got: ' . $afterInside['from']);
            FsTest::assert(substr((string) $afterInside['to'], -6) === '+06:00', 'to is in site time, got: ' . $afterInside['to']);
            FsTest::assertSame('Asia/Dhaka', $context()['site_timezone'] ?? null, 'context names the site timezone');
        } finally {
            update_option('timezone_string', $previous[0]);
            update_option('gmt_offset', $previous[1]);
            ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('results_carry_schema_version_3_and_site_time', function () {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'Asia/Dhaka');
        try {
            $meta = MCPHelper::envelope('x', [])['meta'];
            FsTest::assertSame(3, $meta['schema_version'], 'schema_version');
            FsTest::assert(substr($meta['generated_at'], -6) === '+06:00', 'generated_at is in site time, got: ' . $meta['generated_at']);
        } finally {
            update_option('timezone_string', $previous[0]);
            update_option('gmt_offset', $previous[1]);
        }
    });

    FsTest::case('server_instructions_tell_the_agent_where_to_start', function () {
        $instructions = MCPInit::serverInstructions();

        FsTest::assert(strpos($instructions, 'get-support-context') !== false, 'instructions name the starting tool');
        FsTest::assert(strpos($instructions, 'waiting_on') !== false, 'instructions define waiting_on');
        FsTest::assert(strpos($instructions, 'never type them yourself') !== false, 'instructions explain the template markers once');
        FsTest::assert(strpos($instructions, 'critical tickets first') !== false, 'instructions give the triage order');
        FsTest::assert(strpos($instructions, 'untrusted') !== false, 'instructions warn that ticket content is untrusted');
        FsTest::assert(strpos($instructions, "site's timezone") !== false, 'instructions say dates are in site time');

        // The default guidelines are about tone only, so they cannot contradict the triage order.
        $settings = \FluentSupport\App\Services\Helper::getOption('_mcp_settings', []);
        if (empty($settings['ai_guidelines'])) {
            ManagementTools::invalidateSupportContextCache();
            $guidelines = ManagementTools::getSupportContext([])['data']['guidelines'];
            FsTest::assert(stripos($guidelines, 'triage') === false && stripos($guidelines, 'longest waiting') === false, 'default guidelines carry no triage order, got: ' . $guidelines);
        }
    });

    FsTest::case('created_date_filters_read_offsets_and_include_the_end_day', function () {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'Asia/Dhaka');

        $ticket = FsFactory::ticket();
        Ticket::where('id', $ticket->id)->update(['created_at' => '2031-03-10 15:00:00']);

        $found = function ($args) use ($ticket) {
            $result = \FluentSupport\App\Modules\MCP\Tools\TicketTools::listTickets($args + ['customer_id' => $ticket->customer_id, 'status' => 'all']);
            if (is_wp_error($result)) {
                return $result->get_error_code();
            }
            return in_array((int) $ticket->id, array_map('intval', array_column(json_decode(wp_json_encode($result['data']['tickets']), true), 'id')), true);
        };

        try {
            FsTest::assertSame(true, $found(['created_before' => '2031-03-10']), 'a bare end date includes that day');
            FsTest::assertSame(false, $found(['created_before' => '2031-03-09']), 'the day before does not');
            FsTest::assertSame(true, $found(['created_after' => '2031-03-10']), 'a bare start date is the start of the day');
            // 15:00 in Dhaka is 09:00 UTC.
            FsTest::assertSame(true, $found(['created_after' => '2031-03-10T08:59:00Z', 'created_before' => '2031-03-10T09:01:00Z']), 'a UTC value is converted to site time');
            FsTest::assertSame(false, $found(['created_after' => '2031-03-10T09:01:00Z']), 'a UTC value after the ticket leaves it out');
            FsTest::assertSame(true, $found(['created_after' => '2031-03-10T14:59:00+06:00']), 'a site-offset value as the tools return it');
            foreach (['not a date', 'x', 'tomorrow', '2031-13-45', '2031-02-30', '2031-02-30T10:00:00Z', '10/03/2031', '1893456000', '2031-03-05T24:30:00+00:00', '2031-03-05 10:75:00', '2031-03-05T10:00:61Z', '2031-03-05T10:00:00+25:00'] as $bad) {
                FsTest::assertSame(['invalid_param', 'invalid_param'], [$found(['created_after' => $bad]), $found(['created_before' => $bad])], 'a bad date is refused: ' . $bad);
            }
            FsTest::assertSame(true, $found(['created_after' => '2031-03-10 14:59:00']), 'a date with a time and no offset is site time');
            FsTest::assertSame('invalid_param', $found(['created_after' => '2031-03-11', 'created_before' => '2031-03-09']), 'a reversed range is refused');
        } finally {
            update_option('timezone_string', $previous[0]);
            update_option('gmt_offset', $previous[1]);
        }
    });

    FsTest::case('a_ticket_row_and_the_waiting_filters_agree_on_whose_turn_it_is', function () {
        // An outreach ticket: the agent wrote the opening message, so both timestamps tie.
        $tie = FsFactory::ticket(['status' => 'active']);
        Ticket::where('id', $tie->id)->update(['status' => 'active', 'last_customer_response' => '2031-01-01 10:00:00', 'last_agent_response' => '2031-01-01 10:00:00']);
        $new = FsFactory::ticket();
        Ticket::where('id', $new->id)->update(['status' => 'new', 'last_agent_response' => null]);

        $listed = function ($ticket, $waitingOn) {
            $result = \FluentSupport\App\Modules\MCP\Tools\TicketTools::listTickets(['waiting_on' => $waitingOn, 'customer_id' => $ticket->customer_id]);
            return in_array((int) $ticket->id, array_map('intval', array_column(json_decode(wp_json_encode($result['data']['tickets']), true), 'id')), true);
        };

        // An old or imported row with no customer timestamp: we answered, so it waits on the customer.
        $noCustomerTime = FsFactory::ticket(['status' => 'active']);
        Ticket::where('id', $noCustomerTime->id)->update(['status' => 'active', 'last_customer_response' => null, 'last_agent_response' => '2031-01-01 10:00:00']);

        foreach ([[$tie, 'customer'], [$new, 'agent'], [$noCustomerTime, 'customer']] as [$ticket, $expected]) {
            $row = MCPHelper::waitingOn(Ticket::find($ticket->id));
            FsTest::assertSame($expected, $row, 'row waiting_on');
            FsTest::assertSame([$expected === 'agent', $expected === 'customer'], [$listed($ticket, 'agent'), $listed($ticket, 'customer')], 'listed by waiting_on=' . $expected . ' only');
        }

        foreach ([['waiting_on' => 'customer', 'status' => 'closed'], ['waiting_for_reply' => true, 'status' => 'all']] as $args) {
            $result = \FluentSupport\App\Modules\MCP\Tools\TicketTools::listTickets($args);
            FsTest::assertSame('invalid_param', is_wp_error($result) ? $result->get_error_code() : 'returned data', 'a waiting filter with ' . $args['status'] . ' tickets is refused');
        }

        Ticket::where('id', $tie->id)->update(['status' => 'closed']);
        FsTest::assertSame(null, MCPHelper::waitingOn(Ticket::find($tie->id)), 'closed ticket');
    });

    FsTest::case('integration_dates_are_shown_in_site_time', function () {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'Asia/Dhaka');
        try {
            $clean = MCPHelper::sanitizeWidgetData([
                'orders' => [['date' => '2031-03-10T09:00:00+00:00', 'zulu' => '2031-03-10T09:00:00Z', 'day' => '2031-03-10', 'number' => '2031-03-10T09:00:00 is in a sentence']],
            ]);
            FsTest::assertSame(
                ['date' => '2031-03-10T15:00:00+06:00', 'zulu' => '2031-03-10T15:00:00+06:00', 'day' => '2031-03-10', 'number' => '2031-03-10T09:00:00 is in a sentence'],
                $clean['orders'][0],
                'a UTC date becomes the same moment in site time; other strings are untouched'
            );
        } finally {
            update_option('timezone_string', $previous[0]);
            update_option('gmt_offset', $previous[1]);
        }
    });

    FsTest::case('the_last_page_of_a_list_has_no_cursor', function () {
        $customer = FsFactory::customer();
        FsFactory::ticket(['customer_id' => $customer->id]);
        FsFactory::ticket(['customer_id' => $customer->id]);

        $paging = function ($args) use ($customer) {
            return \FluentSupport\App\Modules\MCP\Tools\TicketTools::listTickets($args + ['customer_id' => $customer->id, 'status' => 'all'])['meta']['paging'];
        };

        $first = $paging(['per_page' => 1]);
        FsTest::assert($first['has_more'] === true && !empty($first['next_cursor']), 'a page with more after it has a cursor');
        $last = $paging(['per_page' => 2]);
        FsTest::assertSame([false, null], [$last['has_more'], $last['next_cursor']], 'the last page has none');
    });

    FsTest::case('a_tool_result_goes_out_once_on_the_mcp_route', function () {
        $text   = wp_json_encode(['summary' => 'x', 'data' => ['tags' => [], 'you' => new \stdClass()]]);
        $result = ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false, 'structuredContent' => json_decode($text)]];
        $send   = function ($data, $route) {
            $response = MCPInit::sendResultOnce(new \WP_REST_Response($data), null, new \WP_REST_Request('POST', $route));
            return json_decode(wp_json_encode($response->get_data()), true);
        };

        $once = $send($result, '/fluent-support/mcp');
        FsTest::assert(!array_key_exists('structuredContent', $once['result']), 'the second copy is removed');
        FsTest::assertSame([$text, false], [$once['result']['content'][0]['text'], $once['result']['isError']], 'the text and the rest of the result are unchanged');

        FsTest::assert(array_key_exists('structuredContent', $send($result, '/fluent-support/v2/tickets')['result']), 'other routes are left alone');

        // tools/list has no content block: nothing to remove, and {} must stay {}.
        $list     = ['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]]]]];
        $response = MCPInit::sendResultOnce(new \WP_REST_Response($list), null, new \WP_REST_Request('POST', '/fluent-support/mcp'));
        FsTest::assert(strpos(wp_json_encode($response->get_data()), '"properties":{}') !== false, 'an empty object is still an object');

        // An error result has only text; a result without text keeps its structured copy.
        $noText = $result;
        $noText['result']['content'] = [];
        FsTest::assert(array_key_exists('structuredContent', $send($noText, '/fluent-support/mcp')['result']), 'a result without text keeps structuredContent');

        // The response filter is hooked only after one of this server's tools ran.
        $server = function ($id) {
            return new class($id) {
                private $id;
                public function __construct($id) { $this->id = $id; }
                public function get_server_id() { return $this->id; }
            };
        };
        $armed = function () {
            return (bool) has_filter('rest_post_dispatch', [MCPInit::class, 'sendResultOnce']);
        };

        remove_filter('rest_post_dispatch', [MCPInit::class, 'sendResultOnce'], 20);
        try {
            FsTest::assertSame(['r' => 1], MCPInit::armSingleResultCopy(['r' => 1], [], 'x', null, $server('another-server')), 'the tool result is returned unchanged');
            FsTest::assertSame(false, $armed(), 'another server\'s tool does not hook the filter');

            add_filter('fluent_support/mcp_single_result_copy', '__return_false');
            MCPInit::armSingleResultCopy([], [], 'x', null, $server(MCPInit::SERVER_ID));
            remove_filter('fluent_support/mcp_single_result_copy', '__return_false');
            FsTest::assertSame(false, $armed(), 'the opt-out filter keeps it off');

            MCPInit::armSingleResultCopy([], [], 'x', null, $server(MCPInit::SERVER_ID));
            MCPInit::armSingleResultCopy([], [], 'x', null, $server(MCPInit::SERVER_ID));
            FsTest::assertSame(true, $armed(), 'a Fluent Support tool hooks it');
            $hooked = array_filter(array_keys($GLOBALS['wp_filter']['rest_post_dispatch']->callbacks[20] ?? []), function ($key) {
                return strpos($key, 'sendResultOnce') !== false;
            });
            FsTest::assertSame(1, count($hooked), 'and only once');

            // The hub runs our tools too; its response is on its own route and is left to the hub.
            FsTest::assert(array_key_exists('structuredContent', $send($result, '/fluent-hub/mcp')['result']), 'another server\'s response is not touched');
        } finally {
            remove_filter('fluent_support/mcp_single_result_copy', '__return_false');
            remove_filter('rest_post_dispatch', [MCPInit::class, 'sendResultOnce'], 20);
        }
    });

    FsTest::case('list_tickets_search_reads_underscore_and_percent_as_text', function () {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Error snake_case 50% off ' . FsFactory::marker()]);
        $other = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Error snakeXcase 50 off ' . FsFactory::marker()]);

        $found = function ($search) use ($customer) {
            $result = \FluentSupport\App\Modules\MCP\Tools\TicketTools::listTickets(['search' => $search, 'customer_id' => $customer->id, 'status' => 'all']);
            $ids = array_map('intval', array_column(json_decode(wp_json_encode($result['data']['tickets']), true), 'id'));
            sort($ids);
            return $ids;
        };

        // The text is escaped once: it finds its ticket, and _ and % match only themselves.
        FsTest::assertSame([(int) $ticket->id], $found('snake_case'), 'a search with an underscore finds its ticket and not the look-alike');
        FsTest::assertSame([(int) $ticket->id], $found('50% off'), 'a search with a percent sign');
        FsTest::assertSame([], $found('snake%case'), 'a percent sign is not a wildcard');
        $both = [(int) $ticket->id, (int) $other->id];
        sort($both);
        FsTest::assertSame($both, $found('Error sna'), 'plain text still finds both');
    });

    FsTest::case('a_registered_tool_warns_about_a_clamped_per_page', function () {
        // Through the callback the tool is registered with, so the wrapper is part of the test.
        $definitions = AbilitiesRegistrar::getDefinitions();
        $callback = AbilitiesRegistrar::abilityArgs('fluent-support/search-customers', $definitions['fluent-support/search-customers'])['execute_callback'];

        $clamped = json_decode(wp_json_encode($callback(['per_page' => 500])), true);
        FsTest::assertSame(MCPHelper::MAX_PER_PAGE, $clamped['meta']['paging']['per_page'] ?? null, 'per_page is clamped');
        FsTest::assert(strpos($clamped['meta']['warnings'][0] ?? '', '500') !== false, 'the registered tool reports it');
        FsTest::assert(!array_key_exists('warnings', json_decode(wp_json_encode($callback(['per_page' => 5])), true)['meta']), 'no warning within the maximum');
    });

    FsTest::case('insights_refuse_an_unknown_agent', function () {
        $result = ManagementTools::getSupportInsights(['agent_id' => 999999999]);
        FsTest::assertSame('not_found', is_wp_error($result) ? $result->get_error_code() : 'returned data', 'unknown agent');
    });

    FsTest::case('every_tool_refuses_unknown_parameters', function () {
        foreach (AbilitiesRegistrar::getDefinitions() as $name => $definition) {
            // The schema the tool is registered with, not the helper on its own.
            $schema = AbilitiesRegistrar::abilityArgs($name, $definition)['input_schema'];
            FsTest::assertSame(false, $schema['additionalProperties'] ?? null, $name . ' is closed');
            // An object here makes the WordPress validator throw as soon as an argument is sent.
            FsTest::assert(is_array($schema['properties'] ?? null), $name . ' properties is an array');
        }
    });

    FsTest::case('input_the_schema_refuses_gets_the_tool_error_envelope', function () {
        $definitions = AbilitiesRegistrar::getDefinitions();
        $error = function ($name, $input) use ($definitions) {
            $result = AbilitiesRegistrar::validationError($input, AbilitiesRegistrar::abilityArgs($name, $definitions[$name])['input_schema']);
            return $result ? json_decode($result->get_error_message(), true)['error'] : true;
        };

        $enum = $error('fluent-support/list-tickets', ['status' => 'bogus']);
        FsTest::assertSame(['invalid_param', ['status'], false], [$enum['code'] ?? null, $enum['fields'] ?? null, $enum['retryable'] ?? null], 'a bad enum value');
        FsTest::assert(strpos($enum['message'], 'status is not one of') === 0, 'the message names the field, got: ' . $enum['message']);

        $unknown = $error('fluent-support/list-tickets', ['not_a_param' => 1]);
        FsTest::assertSame(['unknown_param', ['not_a_param']], [$unknown['code'] ?? null, $unknown['fields'] ?? null], 'an unknown parameter');
        FsTest::assert(in_array('status', $unknown['allowed'] ?? [], true), 'the error lists the parameters the tool has');
        FsTest::assertSame('not_a_param is not a parameter of this tool. See allowed.', $unknown['message'], 'the message names the parameter');

        $required = $error('fluent-support/get-ticket', []);
        FsTest::assertSame(['invalid_param', ['ticket_id']], [$required['code'] ?? null, $required['fields'] ?? null], 'a missing required parameter');

        $format = $error('fluent-support/get-customer-tickets', ['email' => 'not-an-email']);
        FsTest::assertSame(['invalid_param', ['email']], [$format['code'] ?? null, $format['fields'] ?? null], 'a reason that does not name the parameter');

        $zero = $error('fluent-support/list-tickets', ['per_page' => 0]);
        FsTest::assertSame(['invalid_param', ['per_page']], [$zero['code'] ?? null, $zero['fields'] ?? null], 'per_page below 1');

        $noArgs = $error('fluent-support/get-ticket-views', ['x' => 1]);
        FsTest::assertSame(['unknown_param', ['x'], []], [$noArgs['code'] ?? null, $noArgs['fields'] ?? null, $noArgs['allowed'] ?? null], 'a tool with no parameters');

        FsTest::assertSame(true, $error('fluent-support/list-tickets', ['status' => 'open', 'per_page' => 5]), 'valid input passes');

        // update-customer declares email so the call reaches the tool's own refusal and its next step.
        FsTest::assertSame(true, $error('fluent-support/update-customer', ['customer_id' => 1, 'email' => 'new@example.test']), 'email passes the schema');
        $refusal = \FluentSupport\App\Modules\MCP\Tools\CustomerTools::updateCustomer(['customer_id' => FsFactory::customer()->id, 'email' => 'new@example.test']);
        FsTest::assertSame('not_allowed', is_wp_error($refusal) ? $refusal->get_error_code() : 'updated', 'and the tool refuses it');
    });
};
