<?php

namespace FluentSupport\App\Http\Policies;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

class PortalPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  \FluentSupport\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        if($request->get('on_behalf')) {
            return PermissionManager::currentUserCan('fst_sensitive_data') || PermissionManager::currentUserCan('fst_manage_other_tickets');
        }

        return !!get_current_user_id();
    }

}
