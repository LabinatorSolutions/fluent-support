<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Support\Arr;
/**
 *  Helper - REST API Helper Class
 *
 *  App helper for REST API
 *
 * @package FluentSupport\App\Services
 *
 * @version 1.0.0
 */

class Helper
{
    public static function FluentSupport($module = null)
    {
        return App::getInstance($module);
    }

    /**
     * Get agent information by user id
     * The function will get user id as parameter or get id from session and return agent information
     * @param null $userId
     * @return false
     */
    public static function getAgentByUserId($userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        if (!$userId) {
            return false;
        }

        return Agent::where('user_id', $userId)->first();
    }

    /**
     * This function will return the list of ticket priorities list for customer
     *
     * @return mixed
     */
    public static function customerTicketPriorities()
    {
        return apply_filters('fluent_support/customer_ticket_priorities', [
            'normal'   => __('Normal', 'fluent-support'),
            'medium'   => __('Medium', 'fluent-support'),
            'critical' => __('Critical', 'fluent-support')
        ]);
    }

    /**
     * This function will return the list of ticket priorities list for Admin
     *
     * @return mixed
     */
    public static function adminTicketPriorities()
    {
        return apply_filters('fluent_support/admin_ticket_priorities', [
            'normal'   => __('Normal', 'fluent-support'),
            'medium'   => __('Medium', 'fluent-support'),
            'critical' => __('Critical', 'fluent-support')
        ]);
    }


    /**
     * This function will return ticket status group
     *
     * @return mixed
     */
    public static function ticketStatusGroups()
    {
        return apply_filters('fluent_support/ticket_status_groups', [
            'open'   => ['new', 'active'],
            'active' => ['active'],
            'closed' => ['closed'],
            'new'    => ['new'],
            'all'    => []
        ]);
    }

    /**
     * This function will return ticket status list
     *
     * @return mixed
     */
    public static function ticketStatuses()
    {
        return apply_filters('fluent_support/ticket_statuses', [
            'new'    => __('New', 'fluent-support'),
            'active'    => __('Active', 'fluent-support'),
            'closed'    => __('Closed', 'fluent-support'),
        ]);
    }

    public static function getTkStatusesByGroupName($groupName)
    {
        $groups = self::ticketStatusGroups();
        return Arr::get($groups, $groupName, []);
    }

    public static function ticketAcceptedFileMiles()
    {
        $groups = self::getMimeGroups();
        $globalSettings = (new Settings())->globalBusinessSettings();

        if (empty($globalSettings['accepted_file_types'])) {
            return apply_filters('fluent_support/accepted_ticket_mimes', []);
        }

        $mimes = [];
        $typesGroups = Arr::only($groups, $globalSettings['accepted_file_types']);
        foreach ($typesGroups as $mimesGroup) {
            $mimes = array_merge($mimes, $mimesGroup['mimes']);
        }

        return apply_filters('fluent_support/accepted_ticket_mimes', $mimes);
    }

    public static function getAcceptedMimeHeadings()
    {
        $groups = self::getMimeGroups();
        $globalSettings = (new Settings())->globalBusinessSettings();

        if (empty($globalSettings['accepted_file_types'])) {
            return [];
        }

        $mimeNames = [];
        $typesGroups = Arr::only($groups, $globalSettings['accepted_file_types']);
        foreach ($typesGroups as $mimesGroup) {
            $mimeNames[] = $mimesGroup['title'];
        }

        return $mimeNames;
    }

    public static function getFileUploadMessage()
    {
        $mimeHeadings = self::getAcceptedMimeHeadings();
        $settings = (new Settings())->globalBusinessSettings();
        $maxFileSize = absint($settings['max_file_size']);

        return sprintf(__('Supported Types: %s and max file size: %dMB', 'fluent-support'), implode(', ', $mimeHeadings), $maxFileSize);
    }

    public static function getMimeGroups()
    {
        return [
            'images'    => [
                'title' => __('Photos', 'fluent-support'),
                'mimes' => [
                    'image/gif',
                    'image/ief',
                    'image/jpeg',
                    'image/webp',
                    'image/pjpeg',
                    'image/ktx',
                    'image/png'
                ]
            ],
            'csv'       => [
                'title' => __('CSV', 'fluent-support'),
                'mimes' => [
                    'application/csv',
                    'application/txt',
                    'text/csv',
                    'text/plain',
                    'text/comma-separated-values',
                    'text/anytext',
                ]
            ],
            'documents' => [
                'title' => __('PDF/Docs', 'fluent-support'),
                'mimes' => [
                    'application/excel',
                    'application/vnd.ms-excel',
                    'application/vnd.msexcel',
                    'application/octet-stream',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]
            ],
            'zip'       => [
                'title' => __('Zip', 'fluent-support'),
                'mimes' => [
                    'application/zip'
                ]
            ],
            'json'      => [
                'title' => __('JSON', 'fluent-support'),
                'mimes' => [
                    'application/json',
                    'application/jsonml+json'
                ]
            ]
        ];
    }

    /**
     * getOption method will return settings using key
     * This method will get key as parameter, fetch data from database, beautify the data and return
     * @param $key
     * @param string $default
     * @return mixed|string
     */
    public static function getOption($key, $default = '')
    {
        //Get settings from meta table using the key
        $data = Meta::where('object_type', 'option')
            ->where('key', $key)
            ->first();

        if ($data) {
            $value = maybe_unserialize($data->value);
            if ($value) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * updateOption method will update or insert settings
     * This method will get key and value as parameter, check exists or not. If exist update value by key, else insert value for the key
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function updateOption($key, $value)
    {
        //Get settings from meta table using the key
        $data = Meta::where('object_type', 'option')
            ->where('key', $key)
            ->first();

        //If data is available, update existing data and return
        if ($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        //If newly submit, create new record and return
        return Meta::insert([
            'object_type' => 'option',
            'key'         => $key,
            'value'       => maybe_serialize($value)
        ]);
    }

    /**
     * getIntegrationOption method will return the integration settings by integration key
     * @param $key
     * @param string $default
     * @return mixed|string
     */
    public static function getIntegrationOption($key, $default = '')
    {
        $data = Meta::where('object_type', 'integration_settings')
            ->where('key', $key)
            ->first();

        if ($data) {
            $value = maybe_unserialize($data->value);
            if ($value) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * updateIntegrationOption method will update existing settings or create new settings by integration key
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function updateIntegrationOption($key, $value)
    {
        $data = Meta::where('object_type', 'integration_settings')
            ->where('key', $key)
            ->first();

        if ($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        return Meta::insert([
            'object_type' => 'integration_settings',
            'key'         => $key,
            'value'       => maybe_serialize($value)
        ]);
    }

    public static function getTicketViewUrl($ticket)
    {
        $baseUrl = self::getPortalBaseUrl();

        return $baseUrl . '/#/ticket/' . $ticket->id . '/view';
    }

    public static function getTicketViewSignedUrl($ticket)
    {
        if (!self::isPublicSignedTicketEnabled()) {
            return self::getTicketViewUrl($ticket);
        }

        $baseUrl = self::getPortalBaseUrl();

        $baseUrl = add_query_arg([
            'fs_view'      => 'ticket',
            'support_hash' => $ticket->hash,
            'ticket_id'    => $ticket->id
        ], $baseUrl);

        return $baseUrl . '#/ticket/' . $ticket->id . '/view';
    }

    public static function isPublicSignedTicketEnabled()
    {
        $businessSettings = self::getBusinessSettings();

        return (Arr::get($businessSettings, 'disable_public_ticket') != 'yes');
    }

    public static function getTicketAdminUrl($ticket)
    {
        $baseUrl = self::getPortalAdminBaseUrl();
        return $baseUrl . 'tickets/' . $ticket->id . '/view';
    }

    /**
     * getPortalBaseUrl will get the portal page id and return link of the page
     * @return mixed
     */
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

        if ($settings) {
            return $settings;
        }

        $settings = (new Settings())->globalBusinessSettings();

        return $settings;
    }

    public static function getTicketMeta($ticketId, $key, $default = '')
    {
        $data = Meta::where('object_type', 'ticket_meta')
            ->where('key', $key)
            ->where('object_id', $ticketId)
            ->first();

        if ($data) {
            $value = maybe_unserialize($data->value);
            if ($value) {
                return $value;
            }
        }

        return $default;
    }

    public static function updateTicketMeta($ticketId, $key, $value)
    {
        $data = Meta::where('object_type', 'ticket_meta')
            ->where('key', $key)
            ->where('object_id', $ticketId)
            ->first();

        if ($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        return Meta::insert([
            'object_type' => 'ticket_meta',
            'object_id'   => $ticketId,
            'key'         => $key,
            'value'       => maybe_serialize($value)
        ]);
    }

    public static function getWPPages()
    {
        $pages = (self::FluentSupport())->app->db
            ->table('posts')
            ->select(['ID', 'post_title'])
            ->where('post_type', 'page')
            ->where('post_status', 'publish')
            ->orderBy('ID', 'DESC')
            ->get();
        $formattedPages = [];
        foreach ($pages as $page) {
            $formattedPages[] = [
                'id'    => strval($page->ID),
                'title' => $page->post_title
            ];
        }
        return $formattedPages;
    }

    public static function getDefaultMailBox()
    {
        $mailbox = MailBox::where('is_default', 'yes')->first();

        if ($mailbox) {
            return $mailbox;
        }

        return MailBox::orderBy('id', 'ASC')->first();
    }

    public static function getCurrentAgent()
    {
        return Agent::where('user_id', get_current_user_id())->first();
    }

    public static function getCurrentCustomer()
    {
        return Customer::where('user_id', get_current_user_id())->first();
    }

    public static function getCurrentPerson()
    {
        return Person::where('user_id', get_current_user_id())->first();
    }

    public static function getFluentCRMTagConfig()
    {
        if (!defined('FLUENTCRM')) {
            return [
                'can_add_tags' => false,
                'tags'         => [],
                'logo'         => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluentcrm-logo.svg',
                'icon'         => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluent-crm-icon.png',
            ];
        }

        $canAddTags = \FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contacts');

        $canAddTags = apply_filters('fluent_support/can_user_add_tags_to_customer', $canAddTags);
        $crmTags = [];
        if ($canAddTags) {
            $crmTags = \FluentCrm\App\Models\Tag::select(['id', 'title'])->orderBy('title', 'ASC')->get();
        }

        return [
            'can_add_tags' => $canAddTags,
            'tags'         => $crmTags,
            'logo'         => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluentcrm-logo.svg',
            'icon'         => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluent-crm-icon.png',
        ];

    }

    /**
     * getFluentCrmContactData method will get information from fluent crm using user email
     * @param $customer
     * @return array|false
     */
    public static function getFluentCrmContactData($customer)
    {
        if (!defined('FLUENTCRM')) {
            return false;
        }
        //Get contact info from FluentCRM using customer email
        $contact = \FluentCrmApi('contacts')->getContactByUserRef($customer->email);
        if ($contact) {
            $tags = $contact->tags;
            $urlBase = apply_filters('fluentcrm_menu_url_base', admin_url('admin.php?page=fluentcrm-admin#/'));
            $crmProfileUrl = $urlBase . 'subscribers/' . $contact->id;

            //Return contact data
            return  [
                'id'            => $contact->id,
                'first_name'    => $contact->first_name,
                'last_name'     => $contact->last_name,
                'full_name'     => $contact->full_name,
                'name_mismatch' => $contact->full_name != $customer->full_name,
                'tags'          => $tags,
                'status'        => $contact->status,
                'stats'         => $contact->stats(),
                'view_url'      => $crmProfileUrl
            ];
        }

        return false;
    }

    /**
     * Update profile picture for a user in person table using person id
     * @param $model
     * @param $id
     * @param $subDir
     * @param $files
     * @return array
     */
    public static function uploadProfilePicture($model , $id, $subDir, $files){
        $model = "FluentSupport\App\Models\\".$model;
        $row = $model::findOrFail($id);

        $uploadedImage = FileSystem::setSubDir($subDir)->put($files);

        if($avatar = $uploadedImage[0]['url']){
            $row->avatar = $avatar;
            $row->save();

            return[
                'message' => __('Profile picture has been updated successfully', 'fluent-support'),
                'image'   => $row->avatar,
                'person' => $row
            ];
        }

        else{
            $controller = new FluentSupport\Framework\Http\Controller();

            return $controller->sendError([
                'message' => __('Something went wrong while updating the profile picture', 'fluent-support')
            ]);
        }
    }
}
