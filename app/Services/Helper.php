<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Support\Arr;

class Helper
{
    public static function FluentSupport($module = null)
    {
        return App::getInstance($module);
    }

    public static function getAgentByUserId($userId = null)
    {
        if($userId === null) {
            $userId = get_current_user_id();
        }
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
            'active' => ['active'],
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
            'application/zip',
            'application/json',
            'application/jsonml+json'
        ]);
    }

    public static function getOption($key, $default = '')
    {
        $data = Meta::where('object_type', 'option')
                    ->where('key', $key)
                    ->first();

        if($data) {
            $value = maybe_unserialize($data->value);
            if($value) {
                return $value;
            }
        }

        return $default;
    }

    public static function updateOption($key, $value)
    {
        $data = Meta::where('object_type', 'option')
            ->where('key', $key)
            ->first();

        if($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        return Meta::insert([
            'object_type' => 'option',
            'key' => $key,
            'value' => maybe_serialize($value)
        ]);
    }

    public static function getTicketViewUrl($ticket)
    {
        $baseUrl = self::getPortalBaseUrl();
        return $baseUrl.'/#/ticket/'.$ticket->id.'/view';
    }

    public static function getTicketAdminUrl($ticket)
    {
        $baseUrl = self::getPortalAdminBaseUrl();
        return $baseUrl.'tickets/'.$ticket->id.'/view';
    }

    public static function getPortalBaseUrl()
    {
        $businessSettings = self::getBusinessSettings();
        $baseUrl = get_permalink($businessSettings['portal_page_id']);
        $baseUrl = rtrim($baseUrl, '/\\');
        return apply_filters('fluent_support/portal_base_url', $baseUrl);
    }

    public static function getPortalAdminBaseUrl()
    {
        return apply_filters('fluent_support/portal_admin_base_url', admin_url('admin.php?page=fluent-support/#/'));
    }

    public static function getBusinessSettings()
    {
        static $settings;

        if($settings) {
            return $settings;
        }

        $settings = (new Settings())->globalBusinessSettings();

        return $settings;
    }

    public static function getTicketMeta($ticketId, $key, $default = '')
    {
        $data = Meta::where('object_type', 'ticket_mata')
            ->where('key', $key)
            ->where('object_id', $ticketId)
            ->first();

        if($data) {
            $value = maybe_unserialize($data->value);
            if($value) {
                return $value;
            }
        }

        return $default;
    }

    public static function updateTicketMeta($ticketId, $key, $value)
    {
        $data = Meta::where('object_type', 'ticket_mata')
            ->where('key', $key)
            ->where('object_id', $ticketId)
            ->first();

        if($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        return Meta::insert([
            'object_type' => 'ticket_mata',
            'object_id' => $ticketId,
            'key' => $key,
            'value' => maybe_serialize($value)
        ]);
    }
}
