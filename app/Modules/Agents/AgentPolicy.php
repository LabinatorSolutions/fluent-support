<?php

namespace FluentSupport\App\Modules\Agents;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

class AgentPolicy extends Policy
{
    /**
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        // Only reached by routes without their own method here (index, avatar).
        return PermissionManager::currentUserCan('fst_sensitive_data');
    }

    public function create(Request $request)
    {
        return $this->guardManageOptions();
    }

    public function update(Request $request)
    {
        return $this->guardManageOptions();
    }

    public function delete(Request $request)
    {
        return $this->guardManageOptions();
    }

    /**
     * Agent records carry plugin permissions, so editing them grants privileges.
     * Gate on manage_options, which the plugin's own permission system can't grant.
     * Throwing gives a specific message, and the code becomes the HTTP status.
     *
     * @return Boolean
     * @throws \Exception
     */
    protected function guardManageOptions()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        if (!is_user_logged_in()) {
            throw new \Exception(
                esc_html__('You must be logged in to perform this action.', 'fluent-support'),
                401
            );
        }

        throw new \Exception(
            esc_html__('Only administrators can add, edit, or delete support staff.', 'fluent-support'),
            403
        );
    }
}
