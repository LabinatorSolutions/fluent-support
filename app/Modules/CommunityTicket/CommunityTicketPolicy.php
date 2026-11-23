<?php

namespace FluentSupport\App\Modules\CommunityTicket;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

/**
 * A FluentCommunity moderator who can also manage tickets.
 */
class CommunityTicketPolicy extends Policy
{
    /**
     * @param \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return self::currentUserCan();
    }

    /**
     * Also used to decide whether the community menu items are offered at all.
     *
     * @return bool
     */
    public static function currentUserCan()
    {
        // No FluentCommunity class is touched before this check.
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            return false;
        }

        return PermissionManager::canManageTickets()
            && self::isEnabled()
            && \FluentCommunity\App\Services\Helper::isModerator();
    }

    /**
     * The Business Settings switch, off by default. Read fresh, not from the request cache,
     * so turning it off applies at once.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return Arr::get(Helper::getOption('global_business_settings', []), 'enable_community_tickets') === 'yes';
    }
}
