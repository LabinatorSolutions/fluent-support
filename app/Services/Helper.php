<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;
use FluentSupport\Framework\Support\Arr;

class Helper
{
    public static function getAgentByUserId($userId)
    {
        if (!$userId) {
            return false;
        }

        return Agent::where('user_id', $userId)->first();
    }

    public static function customerTicketPriorities()
    {
        return apply_filters('fluent_support/customer_ticket_priorities', [
            'normal'   => __('Normal', 'fluent-support'),
            'medium'   => __('Medium', 'fluent-support'),
            'critical' => __('Critical', 'fluent-support')
        ]);
    }

    public static function adminTicketPriorities()
    {
        return apply_filters('fluent_support/admin_ticket_priorities', [
            'normal'   => __('Normal', 'fluent-support'),
            'medium'   => __('Medium', 'fluent-support'),
            'critical' => __('Critical', 'fluent-support')
        ]);
    }

    public static function ticketStatusGroups()
    {
        return apply_filters('fluent_support/ticket_status_groups', [
            'open'   => ['new', 'active'],
            'closed' => ['closed'],
            'new' => ['new'],
            'all'    => []
        ]);
    }

    public static function getTkStatusesByGroupName($groupName)
    {
        $groups = self::ticketStatusGroups();
        return Arr::get($groups, $groupName, []);
    }
}
