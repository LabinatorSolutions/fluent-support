<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
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
        if ($userId === null) {
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
            'open'   => [__('new', 'fluent-support'), __('active', 'fluent-support')],
            'active' => [__('active', 'fluent-support')],
            'closed' => [__('closed', 'fluent-support')],
            'new'    => [__('new', 'fluent-support')],
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

    public static function getOption($key, $default = '')
    {
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

    public static function updateOption($key, $value)
    {
        $data = Meta::where('object_type', 'option')
            ->where('key', $key)
            ->first();

        if ($data) {
            return Meta::where('id', $data->id)
                ->update([
                    'value' => maybe_serialize($value)
                ]);
        }

        return Meta::insert([
            'object_type' => 'option',
            'key'         => $key,
            'value'       => maybe_serialize($value)
        ]);
    }

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

    public static function getFluentCrmContactData($customer)
    {
        if (!defined('FLUENTCRM')) {
            return false;
        }
        $contact = \FluentCrmApi('contacts')->getContactByUserRef($customer->email);
        if ($contact) {
            $tags = $contact->tags;
            $urlBase = apply_filters('fluentcrm_menu_url_base', admin_url('admin.php?page=fluentcrm-admin#/'));
            $crmProfileUrl = $urlBase . 'subscribers/' . $contact->id;

            $contactData = [
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

            return $contactData;
        }

        return false;
    }

    public static function getAdvancedFilterOptions()
    {
        $groups = [
            'customer' => [
                'label'    => 'Customer',
                'value'    => 'customer',
                'children' => [
                    [
                        'label' => 'First Name',
                        'value' => 'first_name',
                    ],
                    [
                        'label' => 'Last Name',
                        'value' => 'last_name',
                    ],
                    [
                        'label' => 'Email',
                        'value' => 'email',
                    ],
                    [
                        'label' => 'Address Line 1',
                        'value' => 'address_line_1',
                    ],
                    [
                        'label' => 'Address Line 2',
                        'value' => 'address_line_2',
                    ],
                    [
                        'label' => 'City',
                        'value' => 'city',
                    ],
                    [
                        'label' => 'State',
                        'value' => 'state',
                    ],
                    [
                        'label' => 'Postal Code',
                        'value' => 'postal_code',
                    ],
                    [
                        'label' => 'Country',
                        'value' => 'country',
                        'type' => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'countries',
                        'is_multiple' => true,
                        'is_singular_value' => true
                    ],
                    [
                        'label' => 'Phone',
                        'value' => 'phone',
                    ],
                ],
            ],
            'agent' => [
                'label'    => 'Agent',
                'value'    => 'agent',
                'children' => [
                    [
                        'label' => 'First Name',
                        'value' => 'first_name',
                    ],
                    [
                        'label' => 'Last Name',
                        'value' => 'last_name',
                    ],
                    [
                        'label' => 'Email',
                        'value' => 'email',
                    ],
                    [
                        'label' => 'Address Line 1',
                        'value' => 'address_line_1',
                    ],
                    [
                        'label' => 'Address Line 2',
                        'value' => 'address_line_2',
                    ],
                    [
                        'label' => 'City',
                        'value' => 'city',
                    ],
                    [
                        'label' => 'State',
                        'value' => 'state',
                    ],
                    [
                        'label' => 'Postal Code',
                        'value' => 'postal_code',
                    ],
                    [
                        'label' => 'Country',
                        'value' => 'country',
                        'type' => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'countries',
                        'is_multiple' => true,
                        'is_singular_value' => true
                    ],
                    [
                        'label' => 'Phone',
                        'value' => 'phone',
                    ],
                ],
            ],
            'segment'    => [
                'label'    => 'Ticket Segment',
                'value'    => 'segment',
                'children' => [
                    [
                        'label'       => 'Status',
                        'value'       => 'status',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'statuses',
                        'is_multiple' => true,
                        'is_singular_value' => true
                    ],
                    [
                        'label'       => 'Client Priority',
                        'value'       => 'client_priority',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'client_priority',
                        'is_multiple' => false,
                        'is_singular_value' => true
                    ],
                    [
                        'label'       => 'Agent Priority',
                        'value'       => 'priority',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'priority',
                        'is_multiple' => false,
                        'is_singular_value' => true
                    ],
                    [
                        'label'       => 'Tags',
                        'value'       => 'tags',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'tags',
                        'is_multiple' => true,
                    ]
                ],
            ]
        ];

        $groups = apply_filters('fluent_support/advanced_filter_options', $groups);

        return array_values($groups);
    }

}
