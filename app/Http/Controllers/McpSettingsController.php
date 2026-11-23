<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Modules\MCP\MCPInit;
use FluentSupport\App\Modules\MCP\Support\PermissionGate;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;

/**
 * Settings → MCP for AI Agents: enable toggle, FluentHub install, client snippets.
 * SLA and AI guidelines live in _mcp_settings and are read by PermissionGate, not here.
 */
class McpSettingsController extends Controller
{
    const TOOLKIT_PLUGIN_FILE    = 'fluent-toolkit/fluent-toolkit.php';
    const TOOLKIT_DOWNLOAD_URL   = 'https://github.com/WPManageNinja/fluent-toolkit';

    /** 64 - strlen('mcp____') - 35, the longest tool name (fluent-support-get-customer-tickets). */
    const CONNECTION_NAME_MAX    = 22;

    /**
     * GET settings/mcp. Everything the settings page needs.
     */
    public function getStatus(Request $request)
    {
        $user = wp_get_current_user();

        return [
            'mcp_enabled'          => PermissionGate::isEnabled(),
            'adapter_available'    => MCPInit::adapterAvailable(),
            'toolkit_installed'    => $this->isToolkitPresent(),
            'toolkit_active'       => $this->isToolkitLoaded(),
            'toolkit_version'      => $this->detectToolkitVersion(),
            // The standalone MCP Adapter plugin, when it serves the endpoint without FluentHub.
            'adapter_version'      => defined('WP_MCP_VERSION') ? (string) WP_MCP_VERSION : null,
            'abilities_api'        => function_exists('wp_register_ability'),
            'can_auto_install'     => (bool) apply_filters('fluent_toolkit/can_auto_install', false),
            'toolkit_download_url' => self::TOOLKIT_DOWNLOAD_URL,
            'endpoint_url'         => MCPInit::getEndpointUrl(),
            'server_name'          => self::connectionName(),
            'tools_count'          => MCPInit::toolsCount(),
            'app_passwords_url'    => admin_url('profile.php#application-passwords-section'),
            'plugins_url'          => admin_url('plugins.php'),
            'current_user_login'   => ($user && $user->exists()) ? $user->user_login : '',
            'is_local_dev'         => $this->isLocalDev(),
            // FluentHub's one URL for every Fluent plugin; null when FluentHub is absent.
            'hub'                  => $this->hubState(),
        ];
    }

    /**
     * What FluentHub says about its one-URL connection, for the card at the top of
     * the screen. Read through FluentHub's filter, never its classes (contract:
     * fluent-toolkit docs/mcp-product-connect.md). Null when FluentHub is not
     * active, is older than the contract, or answers with a version this code
     * does not know: the screen then stays as it is without FluentHub.
     *
     * @return array|null
     */
    private function hubState()
    {
        $state = apply_filters('fluent_kit/mcp_hub_state', null, MCPInit::SERVER_ID); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- FluentHub's hook

        if (!is_array($state) || (int) Arr::get($state, 'version') !== 1 || empty($state['hub']) || empty($state['sign_in']) || empty($state['product'])) {
            return null;
        }

        // The per-app instructions are printed as FluentHub wrote them.
        $clients = [];
        foreach ((array) Arr::get($state, 'clients', []) as $client) {
            if (!is_array($client) || empty($client['key']) || empty($client['snippet'])) {
                continue;
            }

            $clients[] = [
                'key'     => sanitize_key($client['key']),
                'label'   => sanitize_text_field((string) Arr::get($client, 'label', $client['key'])),
                'note'    => sanitize_text_field((string) Arr::get($client, 'note', '')),
                'snippet' => (string) $client['snippet'],
            ];
        }

        $products = [];
        foreach ((array) Arr::get($state, 'products', []) as $product) {
            if (!is_array($product) || empty($product['slug'])) {
                continue;
            }

            $products[] = [
                'slug'       => sanitize_key($product['slug']),
                'name'       => sanitize_text_field((string) Arr::get($product, 'name', $product['slug'])),
                'served'     => !empty($product['served']),
                'can_enable' => !empty($product['can_enable']),
            ];
        }

        return [
            'ready'            => !empty($state['ready']) && $clients,
            'endpoint_url'     => esc_url_raw((string) Arr::get($state, 'hub.endpoint_url', '')),
            'hub_reason'       => sanitize_key((string) Arr::get($state, 'hub.reason', '')),
            'sign_in_reason'   => sanitize_key((string) Arr::get($state, 'sign_in.reason', '')),
            'included'         => !empty($state['product']['included']),
            'user_can_connect' => !empty($state['user_can_connect']),
            'can_manage'       => !empty($state['can_manage']),
            'manage_url'       => esc_url_raw((string) Arr::get($state, 'manage_url', '')),
            'profile_url'      => esc_url_raw((string) Arr::get($state, 'profile_url', '')),
            'clients'          => $clients,
            'products'         => $products,
        ];
    }

    /**
     * POST settings/mcp/enable-hub with target: "hub" (the FluentHub MCP server)
     * or the slug of another Fluent plugin on the site (its own MCP switch).
     * FluentHub's call checks that the user may manage the site and runs the
     * plugin's own switch handler. Nothing can be turned off from this screen.
     */
    public function enableHub(Request $request)
    {
        $target = sanitize_key((string) $request->get('target', 'hub')) ?: 'hub';

        $result = apply_filters('fluent_kit/mcp_hub_enable', null, MCPInit::SERVER_ID, $target); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- FluentHub's hook
        if (!is_array($result)) {
            return $this->sendError([
                'message' => __('FluentHub is not active on this site.', 'fluent-support'),
            ]);
        }

        $status = Arr::get($result, 'status');
        $reason = (string) Arr::get($result, 'reason');

        if ($status === 'done') {
            return [
                'hub'         => $this->hubState(),
                'mcp_enabled' => PermissionGate::isEnabled(),
                'message'     => $target === 'hub'
                    ? __('The FluentHub MCP server is on.', 'fluent-support')
                    : __('On. Your AI app sees it the next time it loads its tools.', 'fluent-support'),
            ];
        }

        if ($status === 'forbidden') {
            $refusal = $reason === 'product_refused' ? sanitize_text_field((string) Arr::get($result, 'message', '')) : '';

            return $this->sendError([
                'message' => $refusal ?: __('Only a site administrator can turn this on.', 'fluent-support'),
            ], 403);
        }

        $messages = [
            'adapter_missing'  => __('FluentHub needs a newer MCP adapter before this can be turned on.', 'fluent-support'),
            'adapter_outdated' => __('FluentHub needs a newer MCP adapter before this can be turned on.', 'fluent-support'),
            'unknown_product'  => __('FluentHub does not know this plugin.', 'fluent-support'),
            'not_active'       => __('This plugin is not active on the site.', 'fluent-support'),
            'no_handler'       => __('This plugin cannot be turned on from here. Use its own MCP settings.', 'fluent-support'),
        ];

        return $this->sendError([
            'message' => isset($messages[$reason]) ? $messages[$reason] : __('This could not be turned on. Open FluentHub to see why.', 'fluent-support'),
        ]);
    }

    /**
     * The name the application-password snippets give this site's connection:
     * "fs-" and the site address, such as fs-example-com. An AI app refuses a
     * second connection with a name it already has, so one fixed name fails for
     * someone who connects two sites. The length keeps
     * mcp__{name}__{longest tool name} within the 64 characters apps allow.
     *
     * @return string
     */
    public static function connectionName()
    {
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $slug = preg_replace('/^www-/', '', trim((string) preg_replace('/[^a-z0-9]+/', '-', $host), '-'));

        return rtrim(substr('fs-' . $slug, 0, self::CONNECTION_NAME_MAX), '-') ?: 'fs';
    }

    /**
     * POST settings/mcp/toggle with mcp_enabled: true|'yes'|1|'on'.
     */
    public function toggle(Request $request)
    {
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => __('Sorry, you do not have permission to change the MCP setting.', 'fluent-support'),
            ];
        }

        $value   = $request->get('mcp_enabled');
        $enabled = is_string($value)
            ? in_array(strtolower($value), ['yes', 'true', '1', 'on'], true)
            : (bool) $value;

        PermissionGate::setEnabled($enabled);

        // Re-read so the UI never shows a state that failed to save.
        $stored = PermissionGate::isEnabled();

        return [
            'mcp_enabled' => $stored,
            'message'     => $stored
                ? __('MCP enabled. AI agents with a valid application password can now reach the Fluent Support tools.', 'fluent-support')
                : __('MCP disabled. The endpoint will reject requests until re-enabled.', 'fluent-support'),
        ];
    }

    /**
     * POST settings/mcp/install-adapter. Needs a Fluent Pro plugin hooked into
     * fluent_toolkit/can_auto_install and fluent_toolkit/do_auto_install.
     */
    public function installAdapter(Request $request)
    {
        if (!current_user_can('install_plugins')) {
            return $this->sendError([
                'message' => __('Sorry, you do not have permission to install plugins.', 'fluent-support'),
            ]);
        }

        $canAutoInstall = (bool) apply_filters('fluent_toolkit/can_auto_install', false);
        if (!$canAutoInstall) {
            return $this->sendError([
                'message'              => __('Automatic install needs a Fluent Pro plugin active. Install FluentHub manually, then reload this page to connect Fluent Support with AI agents.', 'fluent-support'),
                'toolkit_download_url' => self::TOOLKIT_DOWNLOAD_URL,
            ]);
        }

        // The installer reports why it failed through this action.
        $failure = null;
        $onFailure = function ($error) use (&$failure) {
            $failure = $error;
        };
        add_action('fluent_toolkit/auto_install_failed', $onFailure);

        do_action('fluent_toolkit/do_auto_install');

        remove_action('fluent_toolkit/auto_install_failed', $onFailure);
        wp_clean_plugins_cache();

        if (!$this->isToolkitPresent()) {
            $reason = is_wp_error($failure) ? $failure->get_error_message() : '';

            return $this->sendError([
                'message'              => trim(__('FluentHub could not be installed automatically.', 'fluent-support') . ' ' . $reason . ' ' . __('Install it manually, then reload this page.', 'fluent-support')),
                'toolkit_download_url' => self::TOOLKIT_DOWNLOAD_URL,
            ]);
        }

        $adapterAvailable = MCPInit::adapterAvailable();

        return $this->sendSuccess([
            'adapter_available' => $adapterAvailable,
            'toolkit_installed' => $this->isToolkitPresent(),
            'message'           => $adapterAvailable
                ? __('FluentHub installed and activated. The MCP endpoint is ready.', 'fluent-support')
                : (is_wp_error($failure)
                    ? $failure->get_error_message()
                    : __('FluentHub was installed. Please reload this page to finish connecting the MCP endpoint.', 'fluent-support')),
        ]);
    }

    /**
     * GET settings/mcp/config-snippets. local_dev=yes|no overrides isLocalDev().
     */
    public function getConfigSnippets(Request $request)
    {
        $endpoint      = MCPInit::getEndpointUrl();
        $localDevParam = $request->get('local_dev');
        $isLocalDev    = ($localDevParam === null || $localDevParam === '')
            ? $this->isLocalDev()
            : in_array(strtolower((string) $localDevParam), ['yes', 'true', '1', 'on'], true);

        $snippets = [];
        foreach (['claude-code', 'claude-desktop', 'cursor', 'codex', 'generic'] as $client) {
            $snippets[$client] = $this->buildSnippet($client, $endpoint, $isLocalDev);
        }

        return [
            'snippets'          => $snippets,
            'endpoint'          => $endpoint,
            'app_passwords_url' => admin_url('profile.php#application-passwords-section'),
            'is_local_dev'      => $isLocalDev,
        ];
    }

    private function buildSnippet($client, $endpoint, $isLocalDev)
    {
        $name  = self::connectionName();
        $basic = '<base64(your-username:application-password)>';
        $user  = '<your-username>';
        $pass  = '<your-application-password>';

        switch ($client) {
            case 'claude-desktop':
                $env = [
                    'WP_API_URL'      => $endpoint,
                    'WP_API_USERNAME' => $user,
                    'WP_API_PASSWORD' => $pass,
                    'OAUTH_ENABLED'   => 'false',
                ];
                if ($isLocalDev) {
                    $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
                }
                return [
                    'client'       => $client,
                    'snippet'      => wp_json_encode([
                        'mcpServers' => [
                            $name => [
                                'command' => 'npx',
                                'args'    => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                                'env'     => $env,
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'instructions' => __('Paste into claude_desktop_config.json, then restart Claude Desktop. On macOS the file is in ~/Library/Application Support/Claude.', 'fluent-support'),
                ];

            case 'cursor':
                return [
                    'client'       => $client,
                    'snippet'      => wp_json_encode([
                        'mcpServers' => [
                            $name => [
                                'url'     => $endpoint,
                                'type'    => 'http',
                                'headers' => ['Authorization' => 'Basic ' . $basic],
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'instructions' => __('Paste into Cursor → Settings → MCP, then restart Cursor.', 'fluent-support'),
                ];

            case 'codex':
                return [
                    'client'       => $client,
                    'snippet'      => "Settings → Connect to a custom MCP\n\n"
                        . "Name:       {$name}\n"
                        . "Transport:  Streamable HTTP\n"
                        . "URL:        {$endpoint}\n\n"
                        . "Header:\n  Key:    Authorization\n  Value:  Basic {$basic}",
                    'instructions' => __('Open OpenAI Codex → Settings → Connect to a custom MCP, choose the Streamable HTTP tab, then copy these values and save.', 'fluent-support'),
                ];

            case 'generic':
                return [
                    'client'       => $client,
                    'snippet'      => "URL:   {$endpoint}\n"
                        . "Auth:  Authorization: Basic {$basic}\n\n"
                        . "# Quick test (curl base64-encodes for you):\n"
                        . "curl -s -u '{$user}:{$pass}' \\\n"
                        . "  -X POST {$endpoint} \\\n"
                        . "  -H 'Content-Type: application/json' \\\n"
                        . "  -H 'Accept: application/json, text/event-stream' \\\n"
                        . '  -d \'{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"c","version":"1.0"}}}\'',
                    'instructions' => __('Use the URL and the Basic authorization header with any MCP client that supports HTTP.', 'fluent-support'),
                ];

            case 'claude-code':
            default:
                return [
                    'client'       => 'claude-code',
                    'snippet'      => "claude mcp add \\\n"
                        . "  --transport http \\\n"
                        . "  {$name} {$endpoint} \\\n"
                        . "  --header \"Authorization: Basic {$basic}\"",
                    'instructions' => __('Enter your username and application password above, then paste the command into your terminal. Run claude and the Fluent Support tools appear under MCP servers.', 'fluent-support'),
                ];
        }
    }

    private function isToolkitLoaded()
    {
        return defined('FLUENT_TOOLKIT_VERSION');
    }

    private function isToolkitPresent()
    {
        return $this->isToolkitLoaded() || $this->isPluginPresent(self::TOOLKIT_PLUGIN_FILE);
    }

    private function detectToolkitVersion()
    {
        if ($this->isToolkitLoaded()) {
            return (string) FLUENT_TOOLKIT_VERSION;
        }

        return $this->detectPluginVersion(self::TOOLKIT_PLUGIN_FILE);
    }

    private function isPluginPresent($pluginFile)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return isset(get_plugins()[$pluginFile]);
    }

    private function detectPluginVersion($pluginFile)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        return $plugins[$pluginFile]['Version'] ?? null;
    }

    /**
     * Dev TLD, loopback host, or private/reserved IP. The snippet disables TLS
     * checks on local installs, so .dev is excluded: it's a real public gTLD.
     */
    private function isLocalDev()
    {
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

        $isLocal = false;

        foreach (['.test', '.local', '.localhost', '.lab', '.docker'] as $tld) {
            if ($tld !== '' && substr($host, -strlen($tld)) === $tld) {
                $isLocal = true;
                break;
            }
        }

        if (!$isLocal && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $isLocal = true;
        }

        if (!$isLocal && filter_var($host, FILTER_VALIDATE_IP)) {
            $isLocal = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return (bool) apply_filters('fluent_support/mcp_is_local_dev', $isLocal, $host);
    }
}
