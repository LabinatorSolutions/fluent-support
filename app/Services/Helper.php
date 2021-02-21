<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;

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
}
