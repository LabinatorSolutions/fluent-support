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

    $ids = function (array $rows) {
        return array_map('intval', array_column($rows, 'id'));
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

    FsTest::case('invalidating_the_context_cache_shows_new_tickets_at_once', function () use ($ids) {
        // Prime the cache, then add a ticket the cached copy cannot know about.
        ManagementTools::invalidateSupportContextCache();
        ManagementTools::getSupportContext([]);

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
        } finally {
            ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('preview_keeps_words_apart_across_blocks_and_line_breaks', function () {
        $preview = MCPHelper::preview('<p><strong>Your Name</strong></p><p>Pedro</p><div>Email</div>Hello,<br>the<br />payment');

        FsTest::assertSame('Your Name Pedro Email Hello, the payment', $preview, 'preview');
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

    FsTest::case('results_carry_schema_version_2_and_site_time', function () {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'Asia/Dhaka');
        try {
            $meta = MCPHelper::envelope('x', [])['meta'];
            FsTest::assertSame(2, $meta['schema_version'], 'schema_version');
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
};
