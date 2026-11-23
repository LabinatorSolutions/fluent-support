<?php
/**
 * The FluentHub "Connect with one URL" card of the MCP settings screen:
 * what settings/mcp reports from FluentHub's fluent_kit/mcp_hub_state filter, the
 * settings/mcp/enable-hub route, and the connection name in the application
 * password snippets. FluentHub is not in the lab: the filters answer for it.
 * Contract: fluent-toolkit docs/mcp-product-connect.md.
 */

use FluentSupport\App\Http\Controllers\McpSettingsController;
use FluentSupport\App\Modules\MCP\AbilitiesRegistrar;
use FluentSupport\App\Modules\MCP\PromptsRegistrar;
use FluentSupport\App\Modules\MCP\Support\PermissionGate;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $state = function (array $over = []) {
        return array_replace_recursive([
            'version' => 1,
            'hub'     => ['enabled' => true, 'supported' => true, 'reason' => '', 'endpoint_url' => 'https://example.test/wp-json/fluent-hub/mcp', 'server_name' => 'fluent-example-test'],
            'sign_in' => ['enabled' => true, 'available' => true, 'reason' => '', 'https' => true],
            'product' => ['known' => true, 'included' => true],
            'ready'   => true,
            'user_can_connect' => true,
            'can_manage'       => true,
            'manage_url'       => 'https://example.test/wp-admin/admin.php?page=fluent-hub#/mcp',
            'profile_url'      => 'https://example.test/wp-admin/profile.php#application-passwords-section',
            'products' => [
                ['slug' => 'fluent-support', 'name' => 'Fluent Support', 'active' => true, 'mcp_enabled' => true, 'included' => true, 'served' => true, 'tools_count' => 24, 'can_enable' => false],
                ['slug' => 'fluent-crm', 'name' => 'FluentCRM <b>x</b>', 'active' => true, 'mcp_enabled' => false, 'included' => true, 'served' => false, 'tools_count' => 0, 'can_enable' => true],
                ['name' => 'no slug'],
            ],
            'clients' => [
                ['key' => 'claude-code', 'label' => 'Claude Code', 'note' => 'Run this in your terminal.', 'snippet' => 'claude mcp add --transport http fluent-example-test https://example.test/wp-json/fluent-hub/mcp'],
                ['key' => 'broken'],
            ],
        ], $over);
    };

    // settings/mcp with FluentHub answering $answer; returns the `hub` key.
    $hub = function ($answer) {
        $filter = function ($value, $slug = '') use ($answer) {
            return $slug === 'fluent-support' ? $answer : $value;
        };
        add_filter('fluent_kit/mcp_hub_state', $filter, 10, 2);
        try {
            $response = FsTest::rest('GET', '/settings/mcp');
        } finally {
            remove_filter('fluent_kit/mcp_hub_state', $filter, 10);
        }
        FsTest::assertHealthy($response, 'settings/mcp');
        $data = json_decode(wp_json_encode($response['data']), true);
        FsTest::assert(array_key_exists('hub', $data), 'settings/mcp has a hub key');

        return $data['hub'];
    };

    FsTest::case('mcp_hub_card_is_absent_without_fluenthub', function () use ($hub, $state) {
        FsTest::assertSame(null, $hub(null), 'FluentHub not active');
        FsTest::assertSame(null, $hub($state(['version' => 2])), 'a contract version this code does not know');
        FsTest::assertSame(null, $hub('yes'), 'an answer that is not an array');
        $noProduct = $state();
        unset($noProduct['product']);
        FsTest::assertSame(null, $hub($noProduct), 'a state without its product part');
    });

    FsTest::case('mcp_hub_card_state_is_read_through_the_filter', function () use ($hub, $state) {
        $ready = $hub($state());
        $keys = array_keys($ready);
        sort($keys);
        FsTest::assertSame(['can_manage', 'clients', 'endpoint_url', 'hub_reason', 'included', 'manage_url', 'products', 'profile_url', 'ready', 'sign_in_reason', 'user_can_connect'], $keys, 'keys');
        FsTest::assertSame([true, true, true, true, '', ''], [$ready['ready'], $ready['user_can_connect'], $ready['can_manage'], $ready['included'], $ready['hub_reason'], $ready['sign_in_reason']], 'ready state');

        // The app instructions are printed as FluentHub wrote them; an entry without a snippet is dropped.
        FsTest::assertSame(1, count($ready['clients']), 'one usable client');
        FsTest::assertSame('claude mcp add --transport http fluent-example-test https://example.test/wp-json/fluent-hub/mcp', $ready['clients'][0]['snippet'], 'snippet as given');

        FsTest::assertSame(
            [['slug' => 'fluent-support', 'name' => 'Fluent Support', 'served' => true, 'can_enable' => false], ['slug' => 'fluent-crm', 'name' => 'FluentCRM x', 'served' => false, 'can_enable' => true]],
            $ready['products'],
            'plugins on the URL: names are plain text, an entry without a slug is dropped'
        );

        // Hub off: the card offers "Turn on" to someone who can manage FluentHub.
        $off = $hub($state(['ready' => false, 'hub' => ['enabled' => false, 'reason' => 'off'], 'sign_in' => ['available' => false, 'reason' => 'hub_off']]));
        FsTest::assertSame([false, 'off', 'hub_off', true], [$off['ready'], $off['hub_reason'], $off['sign_in_reason'], $off['can_manage']], 'hub off');

        // Sign-in cannot run, the product is left out, the user is not allowed.
        $blocked = $hub($state(['ready' => false, 'sign_in' => ['available' => false, 'reason' => 'app_passwords_off']]));
        FsTest::assertSame([false, 'app_passwords_off'], [$blocked['ready'], $blocked['sign_in_reason']], 'application passwords off');
        FsTest::assertSame(false, $hub($state(['ready' => false, 'product' => ['included' => false]]))['included'], 'left out of the hub');
        FsTest::assertSame(false, $hub($state(['user_can_connect' => false]))['user_can_connect'], 'user not allowed');

        // Ready without any usable instruction is not shown as ready.
        $noSnippets = $state();
        $noSnippets['clients'] = [['key' => 'x'], ['key' => 'y']];
        FsTest::assertSame(false, (bool) $hub($noSnippets)['ready'], 'ready needs instructions to print');
    });

    FsTest::case('mcp_hub_enable_route_reports_what_fluenthub_answered', function () use ($state) {
        $calls = [];
        $answer = null;
        $filter = function ($value, $caller = '', $target = '') use (&$calls, &$answer) {
            $calls[] = [$caller, $target];
            return $answer;
        };
        $stateFilter = function ($value, $slug = '') use ($state) {
            return $slug === 'fluent-support' ? $state() : $value;
        };
        add_filter('fluent_kit/mcp_hub_enable', $filter, 10, 3);
        add_filter('fluent_kit/mcp_hub_state', $stateFilter, 10, 2);

        $post = function ($payload) {
            $response = FsTest::rest('POST', '/settings/mcp/enable-hub', $payload);
            $data = json_decode(wp_json_encode($response['data']), true);
            return [$response['status'], is_array($data) ? $data : []];
        };

        try {
            // FluentHub not active.
            [$status, $data] = $post(['target' => 'hub']);
            FsTest::assert($status >= 400 && strpos($data['message'] ?? '', 'not active') !== false, 'null is an error that says FluentHub is not active, got ' . $status);
            FsTest::assertSame(['fluent-support', 'hub'], end($calls), 'the caller and the target reach FluentHub');

            $answer = ['status' => 'forbidden', 'reason' => 'capability'];
            [$status, $data] = $post(['target' => 'hub']);
            FsTest::assertSame([403, 'Only a site administrator can turn this on.'], [$status, $data['message'] ?? ''], 'forbidden');

            $answer = ['status' => 'forbidden', 'reason' => 'product_refused', 'message' => 'Not on <b>this</b> site.'];
            [$status, $data] = $post(['target' => 'fluent-crm']);
            FsTest::assertSame([403, 'Not on this site.'], [$status, $data['message'] ?? ''], 'the plugin\'s own refusal, as plain text');
            FsTest::assertSame(['fluent-support', 'fluent-crm'], end($calls), 'a plugin target');

            $answer = ['status' => 'unavailable', 'reason' => 'no_handler'];
            [$status, $data] = $post(['target' => 'fluent-crm']);
            FsTest::assert($status >= 400 && $status !== 403 && strpos($data['message'] ?? '', 'own MCP settings') !== false, 'unavailable says why, got ' . $status);

            $answer = ['status' => 'done'];
            [$status, $data] = $post(['target' => 'fluent-crm']);
            FsTest::assertSame([200, 'On. Your AI app sees it the next time it loads its tools.'], [$status, $data['message'] ?? ''], 'a plugin turned on');
            FsTest::assert(!empty($data['hub']['ready']) && array_key_exists('mcp_enabled', $data), 'the fresh state comes back to redraw the card');

            [$status, $data] = $post([]);
            FsTest::assertSame([200, 'The FluentHub MCP server is on.', ['fluent-support', 'hub']], [$status, $data['message'] ?? '', end($calls)], 'no target means the hub');

            // The target is a slug: nothing else reaches FluentHub.
            $post(['target' => 'Fluent CRM/../x']);
            FsTest::assertSame('fluentcrmx', end($calls)[1], 'the target is reduced to a slug');
        } finally {
            remove_filter('fluent_kit/mcp_hub_enable', $filter, 10);
            remove_filter('fluent_kit/mcp_hub_state', $stateFilter, 10);
        }
    });

    // FluentHub admits administrators itself and asks each plugin about everyone else.
    FsTest::case('mcp_hub_admits_anyone_with_fluent_support_backend_access', function () {
        $ask = function ($allowed, $slug, $user) {
            return apply_filters('fluent_kit/mcp_hub_product_user_can_connect', $allowed, $slug, $user);
        };

        $newUser = function ($prefix) {
            $login = FsTest::uniq($prefix);
            return wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
        };
        $agentId = $newUser('fs-hub-agent');
        $outsiderId = $newUser('fs-hub-outsider');
        $noRecordId = $newUser('fs-hub-norecord');
        $settingsOnlyId = $newUser('fs-hub-settings');
        // An agent who may not work with tickets: this server's own endpoint refuses them too.
        FsFactory::agent(['user_id' => $settingsOnlyId]);
        PermissionManager::attachPermissions(get_user_by('ID', $settingsOnlyId), ['fst_view_dashboard']);
        // A view-only agent: access to the admin screens, no right to reply.
        FsFactory::agent(['user_id' => $agentId]);
        PermissionManager::attachPermissions(get_user_by('ID', $agentId), ['fst_view_dashboard', 'fst_view_tickets']);
        // Permissions without an agent record: every tool would answer "No agent found".
        PermissionManager::attachPermissions(get_user_by('ID', $noRecordId), ['fst_view_dashboard', 'fst_view_tickets']);

        $wasEnabled = PermissionGate::isEnabled();
        PermissionGate::setEnabled(true);

        try {
            FsTest::assertSame(true, $ask(false, 'fluent-support', get_user_by('ID', $agentId)), 'a user with a Fluent Support permission is admitted');
            FsTest::assertSame(true, $ask(false, 'fluent-support', $agentId), 'also when FluentHub passes the user id');
            FsTest::assertSame(false, $ask(false, 'fluent-support', get_user_by('ID', $outsiderId)), 'a user without any Fluent Support permission is not');
            FsTest::assertSame(false, $ask(false, 'fluent-support', get_user_by('ID', $noRecordId)), 'permissions without an agent record are not enough');
            FsTest::assertSame(false, $ask(false, 'fluent-support', get_user_by('ID', $settingsOnlyId)), 'an agent without a ticket permission is not offered a connection with no tools');
            FsTest::assertSame(false, $ask(false, 'fluent-support', 0), 'no user');

            // Only its own product, and a yes from elsewhere is never lowered.
            FsTest::assertSame(false, $ask(false, 'fluent-crm', get_user_by('ID', $agentId)), 'no answer for another plugin');
            FsTest::assertSame(true, $ask(true, 'fluent-crm', get_user_by('ID', $outsiderId)), 'a yes for another plugin stays');
            FsTest::assertSame(true, $ask(true, 'fluent-support', get_user_by('ID', $outsiderId)), 'a yes it was handed stays');

            PermissionGate::setEnabled(false);
            FsTest::assertSame(false, $ask(false, 'fluent-support', get_user_by('ID', $agentId)), 'nobody is admitted while Fluent Support MCP is off');
        } finally {
            PermissionGate::setEnabled($wasEnabled);
            PermissionManager::detachPermissions($agentId);
            PermissionManager::detachPermissions($noRecordId);
            PermissionManager::detachPermissions($settingsOnlyId);
            wp_delete_user($settingsOnlyId);
            wp_delete_user($agentId);
            wp_delete_user($outsiderId);
            wp_delete_user($noRecordId);
        }
    });

    FsTest::case('mcp_app_password_snippets_name_the_connection_after_the_site', function () {
        $name = McpSettingsController::connectionName();
        FsTest::assert((bool) preg_match('/^fs-[a-z0-9-]+$/', $name), 'the name is fs- and the site address, got: ' . $name);

        $response = FsTest::rest('GET', '/settings/mcp/config-snippets');
        $snippets = json_decode(wp_json_encode($response['data']['snippets']), true);
        foreach (['claude-code', 'claude-desktop', 'cursor', 'codex'] as $client) {
            FsTest::assert(strpos($snippets[$client]['snippet'], $name) !== false, $client . ' uses the name');
        }

        // An AI app joins the names as mcp__{connection}__{tool}, 64 characters at most.
        $long = function () {
            return 'https://www.a-very-long-helpdesk-site-name.support.example.com';
        };
        add_filter('home_url', $long);
        try {
            $longName = McpSettingsController::connectionName();
        } finally {
            remove_filter('home_url', $long);
        }
        FsTest::assertSame('fs-a-very-long-helpdes', $longName, 'www is dropped and the name is cut');

        $tools = array_merge(array_keys(AbilitiesRegistrar::getDefinitions()), array_keys(PromptsRegistrar::getDefinitions()));
        $longest = max(array_map(function ($tool) use ($longName) {
            return strlen('mcp__' . $longName . '__' . str_replace('/', '-', $tool));
        }, $tools));
        FsTest::assert($longest <= 64, 'the longest tool name fits in 64 characters, got ' . $longest);
    });
};
