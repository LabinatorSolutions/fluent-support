<?php

namespace FluentSupport\App\Modules\SystemTools;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

/**
 * Policy for /system-tools: settings managers can read, actions are gated per method.
 */
class SystemToolsPolicy extends Policy
{
    /**
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return PermissionManager::currentUserCan('fst_manage_settings');
    }

    /**
     * Running a task can delete logs and close tickets.
     *
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function runCron(Request $request)
    {
        return $this->canRunAdminAction($request);
    }

    /**
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function repairDatabase(Request $request)
    {
        return $this->canRunAdminAction($request);
    }

    /**
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function deleteOldLogs(Request $request)
    {
        return $this->canRunAdminAction($request);
    }

    /**
     * Actions change site data, so they need a site administrator on top of settings access.
     *
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    protected function canRunAdminAction(Request $request)
    {
        // The router calls the per-method check instead of verifyRequest(), so re-apply it.
        return $this->verifyRequest($request) && current_user_can('manage_options');
    }
}
