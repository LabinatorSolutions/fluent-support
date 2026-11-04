<?php

namespace FluentSupport\App\Modules\MCP\Support;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;

/**
 * MCP on/off state and the transport gate. Permissions reuse PermissionManager,
 * the same fst_* checks the admin REST routes use. Per-ability checks run on top.
 */
class PermissionGate
{
    const OPTION_KEY = '_mcp_settings';

    /**
     * Runs on every MCP request: MCP must be on and the user must have ticket access.
     *
     * @param mixed $request
     * @return true|\WP_Error
     */
    public static function transport($request = null)
    {
        if (!self::isEnabled()) {
            return new \WP_Error(
                'fluent_support_mcp_disabled',
                __('Fluent Support MCP is disabled. Enable it in Settings → MCP.', 'fluent-support')
            );
        }

        // canAccessTicketRoutes() covers this too, but a separate code tells the
        // agent "not logged in" apart from "no Fluent Support access".
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'fluent_support_mcp_unauthorized',
                __('Authentication required to access the Fluent Support MCP server.', 'fluent-support')
            );
        }

        // Same check AgentTicketPolicy uses for ticket routes.
        if (!PermissionManager::canAccessTicketRoutes()) {
            return new \WP_Error(
                'fluent_support_mcp_forbidden',
                __('Your account does not have Fluent Support access.', 'fluent-support')
            );
        }

        return true;
    }

    /**
     * Off by default.
     */
    public static function isEnabled()
    {
        $settings = Helper::getOption(self::OPTION_KEY, []);

        return is_array($settings) && isset($settings['active']) && $settings['active'] === 'yes';
    }

    /**
     * Re-checks manage_options because the FluentHub toggle path leaves auth to
     * another plugin.
     *
     * @param bool $enabled
     * @return bool
     */
    public static function setEnabled($enabled)
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $settings = Helper::getOption(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['active'] = $enabled ? 'yes' : 'no';
        Helper::updateOption(self::OPTION_KEY, $settings);

        return (bool) $enabled;
    }

    /**
     * SLA thresholds used by getSupportContext and getSupportInsights.
     *
     * @return array{first_response_hours: int, resolution_hours: int}
     */
    public static function getSlaSettings()
    {
        $settings = Helper::getOption(self::OPTION_KEY, []);
        $sla      = isset($settings['sla']) && is_array($settings['sla']) ? $settings['sla'] : [];

        return [
            'first_response_hours' => min(max((int) ($sla['first_response_hours'] ?? 4), 1), 720),
            'resolution_hours'     => min(max((int) ($sla['resolution_hours'] ?? 24), 1), 8760),
        ];
    }

    /**
     * AI guidelines for getSupportContext. Sanitized on read, since older
     * values may have been stored raw.
     *
     * @return string Up to 2000 characters, no HTML.
     */
    public static function getAiGuidelines()
    {
        $settings = Helper::getOption(self::OPTION_KEY, []);
        $raw      = isset($settings['ai_guidelines']) ? (string) $settings['ai_guidelines'] : '';

        return mb_substr(wp_strip_all_tags($raw), 0, 2000);
    }
}
