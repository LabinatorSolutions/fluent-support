<?php

namespace FluentSupport\App\Services\EmailNotification;

use FluentSupport\App\Services\Helper;

class Settings
{

    public function getEmailSettingsKeys()
    {
        $key = apply_filters('fluent_support/email_setting_keys', [
            'ticket_created_email_to_customer',
            'ticket_replied_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer',
            'ticket_created_email_to_admin',
            'ticket_replied_by_customer_email_to_admin'
        ]);
        return $key;
    }

    public function get($settingsKey)
    {
        if ($settingsKey == 'global_business_settings') {
            return [
                'settings' => $this->globalBusinessSettings(),
                'fields'   => $this->getGlobalBusinessSettingsFields()
            ];
        }

        return [
            'settings' => [],
            'fields'   => []
        ];
    }

    public function save($settingsKey, $settings)
    {
        if ($settingsKey == 'global_business_settings' && empty($settings['accepted_file_types'])) {
            $settings['accepted_file_types'] = [];
        }

        return Helper::updateOption($settingsKey, $settings);
    }

    public function globalBusinessSettings($cached = true)
    {
        static $settings;

        if($cached && $settings) {
            return $settings;
        }

        $defaults = [
            'portal_page_id'        => '',
            'login_message'         => sprintf(__('%1sPlease login or create an account to access the Customer Support Portal%2s [fluent_support_auh]', 'fluent-support'), '<p>', '</p>'),
            'disable_public_ticket' => 'no',
            'accepted_file_types'   => ['images', 'csv', 'documents', 'zip', 'json'],
            'max_file_size'         => 2
        ];

        $existingSettings = Helper::getOption('global_business_settings', []);

        if (!$existingSettings) {
            $settings = $defaults;
            return $settings;
        }

        $settings = wp_parse_args($existingSettings, $defaults);

        return $settings;
    }

    private function getGlobalBusinessSettingsFields()
    {

        $mimeGroups = Helper::getMimeGroups();

        $formattedMimeGroups = [];

        foreach ($mimeGroups as $mimeGroup => $mime) {
            $formattedMimeGroups[$mimeGroup] = $mime['title'];
        }


        $fields = [
            'portal_page_id'        => [
                'type'        => 'input-options',
                'label'       => __('Portal Page', 'fluent-support'),
                'show_id'     => true,
                'placeholder' => __('Select Portal Page', 'fluent-support'),
                'options'     => Helper::getWPPages(),
                'inline_help' => __('Please provide the page id where you want to show the tickets for your customers. Use shortcode <code>[fluent_support_portal]</code> in that page', 'fluent-support')
            ],
            'login_message'         => [
                'type'        => 'wp-editor',
                'label'       => __('Message for non logged in users', 'fluent-support'),
                'inline_help' => 'Please provide message for not logged in users. You can place login shortcode too Use shortcode <code>[fluent_support_login]</code> to show built-in login form. For the user registration use this shortcode <code>[fluent_support_signup]</code> and for both form please use <code>[fluent_support_auth]</code> in your page '
            ],
            'disable_public_ticket' => [
                'type'           => 'inline-checkbox',
                'true_label'     => 'yes',
                'false-label'    => 'no',
                'checkbox_label' => __('Disable Public Ticket interaction', 'fluent-support'),
                'inline_help'    => __('If you enable this then only logged in user can reply the tickets. Otherwise, url will be signed and intended user can reply without logging in', 'fluent-support')
            ],
            'accepted_file_types'   => [
                'wrapper_class' => 'fs_half_field',
                'type'    => 'checkbox-group',
                'label'   => 'Accepted File Types',
                'options' => $formattedMimeGroups
            ],
            'max_file_size' => [
                'wrapper_class' => 'fs_half_field',
                'type'    => 'input-text',
                'data_type' => 'number',
                'label'   => 'Max File Size (in MegaByte)',
            ]
        ];

        return $fields;
    }

    public function saveBoxEmailSettings($box, $emailKey, $settings)
    {
        return $box->saveMeta('_email_' . $emailKey, $settings);
    }

    public function getBoxEmailSettings($box, $emailKey)
    {
        if (!$box) {
            return false;
        }

        $strictSubjectKeys = apply_filters('fluent_support/strict_subjects', [
            'ticket_replied_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer'
        ]);

        $settingsDefaults = [
            'ticket_created_email_to_customer'          => [
                'key'            => 'ticket_created_email_to_customer',
                'title'          => __('Ticket Created (To Customer)', 'fluent-support'),
                'description'    => __('This email will be sent when a customer submit a support ticket', 'fluent-support'),
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'no'
            ],
            'ticket_replied_by_agent_email_to_customer' => [
                'key'            => 'ticket_replied_by_agent_email_to_customer',
                'title'          => __('Replied by Agent (To Customer)', 'fluent-support'),
                'description'    => __('This email will be sent when an agent reply to a ticket', 'fluent-support'),
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_closed_by_agent_email_to_customer'  => [
                'key'            => 'ticket_closed_by_agent_email_to_customer',
                'title'          => __('Ticket Closed by Agent (To Customer)', 'fluent-support'),
                'description'    => __('This email will be sent when an agent close a ticket', 'fluent-support'),
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_created_email_to_admin'             => [
                'key'            => 'ticket_created_email_to_admin',
                'title'          => __('Ticket Created (To Admin)', 'fluent-support'),
                'description'    => __('This email will be sent when the business when a new ticket has been submitted', 'fluent-support'),
                'email_subject'  => 'New Ticket: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_replied_by_customer_email_to_admin' => [
                'key'            => 'ticket_replied_by_customer_email_to_admin',
                'title'          => __('Replied by Customer (To Agent/Admin)', 'fluent-support'),
                'description'    => __('This email will be sent to Assigned Agent or Admin when a customer reply to a ticket', 'fluent-support'),
                'email_subject'  => 'New Response: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ]
        ];

        if (!isset($settingsDefaults[$emailKey])) {
            return false;
        }

        $savedSettings = (array)$box->getMeta('_email_' . $emailKey, []);


        if (!$savedSettings) {
            $savedSettings = [
                'key'              => $settingsDefaults[$emailKey]['key'],
                'title'            => $settingsDefaults[$emailKey]['title'],
                'email_subject'    => $settingsDefaults[$emailKey]['email_subject'],
                'email_body'       => $this->getDefaultEmailBody($emailKey, $box->box_type),
                'status'           => $settingsDefaults[$emailKey]['default_status'],
                'can_edit_subject' => (in_array($emailKey, $strictSubjectKeys) && $box->box_type == 'email') ? 'no' : 'yes'
            ];

            if ($box->box_type == 'email' && in_array($emailKey, $strictSubjectKeys)) {
                $savedSettings['email_subject'] = 'Re: {{ticket.title}}';
                $savedSettings['can_edit_subject'] = 'no';
            }

            return $savedSettings;
        }

        $savedSettings['key'] = $settingsDefaults[$emailKey]['key'];
        $savedSettings['title'] = $settingsDefaults[$emailKey]['title'];
        $savedSettings['description'] = $settingsDefaults[$emailKey]['description'];

        if ($box->box_type == 'email' && in_array($emailKey, $strictSubjectKeys)) {
            $savedSettings['email_subject'] = 'Re: {{ticket.title}}';
            $savedSettings['can_edit_subject'] = 'no';
        }

        if (empty($savedSettings['email_subject'])) {
            $savedSettings['email_subject'] = $settingsDefaults[$emailKey]['email_subject'];
        }

        if (empty($savedSettings['status'])) {
            $savedSettings['status'] = $settingsDefaults[$emailKey]['default_status'];
        }

        if (empty($savedSettings['email_body'])) {
            $savedSettings['email_body'] = $this->getDefaultEmailBody($emailKey, $box->box_type);
        }

        return $savedSettings;
    }

    private function getDefaultEmailBody($emailKey, $type = 'web')
    {
        if ($emailKey == 'ticket_created_email_to_customer') {
            if ($type == 'web') {
                return '<p>Hi <strong><em>{{customer.full_name}}</em>,</strong></p><p>Your request (<a href="{{ticket.public_url}}">#{{ticket.id}}</a>) has been received, and is being reviewed by our support staff.</p><p>To add additional comments, follow the link below:</p><h4><a href="{{ticket.public_url}}">View Ticket</a></h4><p>&nbsp;</p><p>or follow this link: {{ticket.public_url}}</p><hr /><p>{{business.name}}</p>';
            } else {
                return '<p>Hi <strong><em>{{customer.full_name}}</em>,</strong></p><p>Your request has been received, and is being reviewed by our support staff.</p><p>Our support staff will reply back to you soon</p>';
            }
        } else if ($emailKey == 'ticket_replied_by_agent_email_to_customer') {
            if ($type == 'web') {
                return '<p>Hi <strong><em>{{customer.full_name}}</em>,</strong></p><p>An agent just replied to your ticket "<strong>{{ticket.title}}</strong>" (<a href="{{ticket.public_url}}">#{{ticket.id}}</a>). To view his reply or add additional comments, click the button below:</p><h4><a href="{{ticket.public_url}}">View Ticket</a></h4><p>or follow this link: {{ticket.public_url}}</p><hr /><p>Regards,<br />{business.name}</p>';
            } else {
                return '{{response.full_content}}<p>Regards,<br />{{agent.full_name}}</p>';
            }
        } else if ($emailKey == 'ticket_closed_by_agent_email_to_customer') {
            if ($type == 'web') {
                return '<p>Hi <strong><em>{{customer.full_name}},</strong></p><p>Your ticket - {{ticket.ticket}}</p><p>We hope that the ticket was resolved to your satisfaction. If you feel that the ticket should not be closed or if the ticket has not been resolved, please reopen the ticket (<a href="{{ticket.public_url}}">#{{ticket.id}}</a>)</p><p>Regards,<br />{{business.name}}</p>';
            } else {
                return '<p>Hi <strong><em>{{customer.full_name}},</strong></p><p>Your ticket - {{ticket.ticket}}</p><p>We hope that the ticket was resolved to your satisfaction. If you feel that the ticket should not be closed or if the ticket has not been resolved, please feel free to reply back.<p>Regards,<br />{{business.name}}</p>';
            }
        } else if ($emailKey == 'ticket_created_email_to_admin') {
            return '<p>A new ticket (<a href="{{ticket.admin_url}}">{{ticket.title}}</a>) as been submitted by {{customer.full_name}}</p><h4>Ticket Body</h4><p>{{ticket.content}}</p><p><b><a href="{{ticket.admin_url}}">View Ticket</a></b></p>';
        } else if ($emailKey == 'ticket_replied_by_customer_email_to_admin') {
            return '<p>A new response has been added to "<a href="{{ticket.admin_url}}">{{ticket.title}}</a>"  by {{customer.full_name}}</p><h4>Response Body</h4><p>{{response.content}}</p><p><b><a href="{{ticket.admin_url}}">View Ticket</a></b></p>';
        }

        return '';
    }
}
