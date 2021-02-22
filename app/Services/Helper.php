<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\Framework\Support\Arr;

class Helper
{
    public static function FluentSupport($module = null)
    {
        return App::getInstance($module);
    }

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

    public static function ticketAcceptedFileMiles()
    {
        return apply_filters('fluent_support/accepted_ticket_mimes', [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'text/anytext',
            'application/octet-stream',
            'application/txt',
            'image/gif',
            'image/ief',
            'image/jpeg',
            'image/pjpeg',
            'image/ktx',
            'image/png',
            'application/pdf',
            'application/json',
            'application/jsonml+json'
        ]);
    }
}
