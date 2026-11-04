<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Modules\MCP\MCPInit;
use FluentSupport\App\Modules\MCP\Support\PermissionGate;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Settings → MCP for AI Agents: enable toggle, FluentHub install, client snippets.
 * SLA and AI guidelines live in _mcp_settings and are read by PermissionGate, not here.
 */
class McpSettingsController extends Controller
{
    const TOOLKIT_PLUGIN_FILE    = 'fluent-toolkit/fluent-toolkit.php';
    const TOOLKIT_DOWNLOAD_URL   = 'https://github.com/WPManageNinja/fluent-toolkit';

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
            'can_auto_install'     => (bool) apply_filters('fluent_toolkit/can_auto_install', false),
            'toolkit_download_url' => self::TOOLKIT_DOWNLOAD_URL,
            'endpoint_url'         => MCPInit::getEndpointUrl(),
            'tools_count'          => MCPInit::toolsCount(),
            'app_passwords_url'    => admin_url('profile.php#application-passwords-section'),
            'plugins_url'          => admin_url('plugins.php'),
            'current_user_login'   => ($user && $user->exists()) ? $user->user_login : '',
            'is_local_dev'         => $this->isLocalDev(),
        ];
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
                $localDevNote = $isLocalDev
                    ? ' ' . __('Local dev mode is on, so NODE_TLS_REJECT_UNAUTHORIZED is included — the npx proxy needs it to talk to self-signed SSL.', 'fluent-support')
                    : '';
                return [
                    'client'       => $client,
                    'snippet'      => wp_json_encode([
                        'mcpServers' => [
                            'fluent-support' => [
                                'command' => 'npx',
                                'args'    => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                                'env'     => $env,
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'instructions' => __('Add this to your Claude Desktop config (Settings → Developer → Edit Config), fill in your username + application password, then restart Claude Desktop.', 'fluent-support') . $localDevNote,
                ];

            case 'cursor':
                return [
                    'client'       => $client,
                    'snippet'      => wp_json_encode([
                        'mcpServers' => [
                            'fluent-support' => [
                                'url'     => $endpoint,
                                'type'    => 'http',
                                'headers' => ['Authorization' => 'Basic ' . $basic],
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'instructions' => __("Add to Cursor's mcp.json, replacing the placeholder with base64 of \"username:application-password\".", 'fluent-support'),
                ];

            case 'codex':
                return [
                    'client'       => $client,
                    'snippet'      => "Settings → Connect to a custom MCP\n\n"
                        . "Name:       fluent-support\n"
                        . "Transport:  Streamable HTTP\n"
                        . "URL:        {$endpoint}\n\n"
                        . "Header:\n  Key:    Authorization\n  Value:  Basic {$basic}",
                    'instructions' => __('In Codex, add a custom MCP server with Streamable HTTP transport and the Authorization header above.', 'fluent-support'),
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
                    'instructions' => __('Any MCP client that speaks Streamable HTTP can connect using this URL and a Basic auth header.', 'fluent-support'),
                ];

            case 'claude-code':
            default:
                return [
                    'client'       => 'claude-code',
                    'snippet'      => "claude mcp add \\\n"
                        . "  --transport http \\\n"
                        . "  fluent-support {$endpoint} \\\n"
                        . "  --header \"Authorization: Basic {$basic}\"",
                    'instructions' => __('Run this in your terminal where Claude Code is installed, replacing the placeholder with base64 of "username:application-password".', 'fluent-support'),
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
