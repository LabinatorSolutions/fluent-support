<?php

namespace FluentSupport\App\Services\EmailNotification;

use FluentSupport\App\Services\Helper;

class Settings
{

    public function getEmailSettingsKeys()
    {
        return [
            'ticket_created_email_to_customer',
            'ticket_replied_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer',
            'ticket_created_email_to_admin',
            'ticket_replied_by_customer_email_to_admin'
        ];
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
        return Helper::updateOption($settingsKey, $settings);
    }

    public function globalBusinessSettings()
    {
        $defaults = [
            'portal_page_id' => '',
            'login_message'  => __('<p>Please login to access the Customer Support Portal</p> [fluent_support_login]', 'fluent-support'),
            'disable_public_ticket' => 'no'
        ];

        $existingSettings = Helper::getOption('global_business_settings', []);

        if (!$existingSettings) {
            return $defaults;
        }

        return wp_parse_args($existingSettings, $defaults);
    }

    private function getGlobalBusinessSettingsFields()
    {
        return [
            'portal_page_id' => [
                'type'        => 'input-options',
                'label'       => 'Portal Page',
                'show_id'     => true,
                'placeholder' => __('Select Portal Page', 'fluent-support'),
                'options'     => Helper::getWPPages(),
                'inline_help' => __('Please provide the page id where you want to show the tickets for your customers. Use shortcode <code>[fluent_support_portal]</code> in that page','fluent-support')
            ],
            'login_message'  => [
                'type'        => 'wp-editor',
                'label'       => __('Message for non logged in users', 'fluent-support'),
                'inline_help' => __('Please provide message for not logged in users. You can place login shortcode too Use shortcode <code>[fluent_support_login]</code> to show built-in login form', 'fluent-support')
            ],
            'disable_public_ticket' => [
                'type' => 'inline-checkbox',
                'true_label' => 'yes',
                'false-label' => 'no',
                'checkbox_label' => 'Disable Public Ticket interaction',
                'inline_help' => 'If you enable this then only logged in user can reply the tickets. Otherwise, url will be signed and intended user can reply without logging in'
            ]
        ];
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

        $strictSubjectKeys = apply_filters('fluent_support_strict_subjects', [
            'ticket_replied_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer',
            'ticket_closed_by_agent_email_to_customer'
        ]);

        $settingsDefaults = [
            'ticket_created_email_to_customer'          => [
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'no'
            ],
            'ticket_replied_by_agent_email_to_customer' => [
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_closed_by_agent_email_to_customer'  => [
                'email_subject'  => 'Re: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_created_email_to_admin'             => [
                'email_subject'  => 'New Ticket: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ],
            'ticket_replied_by_customer_email_to_admin' => [
                'email_subject'  => 'New Response: {{ticket.title}} #{{ticket.id}}',
                'default_status' => 'yes'
            ]
        ];

        if (!isset($settingsDefaults[$emailKey])) {
            return false;
        }

        $savedSettings = (array)$box->getMeta('_email_' . $emailKey, []);

        if (!$savedSettings) {
            return [
                'email_subject'    => $settingsDefaults[$emailKey]['email_subject'],
                'email_body'       => $this->getDefaultEmailBody($emailKey, $box->box_type),
                'status'           => $settingsDefaults[$emailKey]['default_status'],
                'can_edit_subject' => (in_array($emailKey, $strictSubjectKeys) && $box->box_type == 'email') ? 'no' : 'yes'
            ];
        }

        if ($box->box_type == 'email' && in_array($emailKey, $strictSubjectKeys)) {
            $savedSettings['email_subject'] = $settingsDefaults[$emailKey]['email_subject'];
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
                return '<p>Hi <strong><em>{{customer.full_name}}</em>,</strong></p><p>An agent just replied to your ticket "<strong>{{ticket.title}}</strong>" (<a href="{{ticket.public_url}}">#{ticket.id}</a>). To view his reply or add additional comments, click the button below:</p><h4><a href="{{ticket.public_url}}">View Ticket</a></h4><p>or follow this link: {{ticket.public_url}}</p><hr /><p>Regards,<br />{business.name}</p>';
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
