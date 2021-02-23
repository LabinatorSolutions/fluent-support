<?php

namespace FluentSupport\App\Services\EmailNotification;

use FluentSupport\App\Services\Helper;

class Settings
{
    public function get($settingsKey)
    {
        if($settingsKey == 'global_email_settings') {
            return [
                'settings' => $this->globalEmailSettings(),
                'fields' => $this->getGlobalEmailSettingsFields()
            ];
        } else if($settingsKey == 'global_business_settings') {
            return [
                'settings' => $this->globalBusinessSettings(),
                'fields' => $this->getGlobalBusinessSettingsFields()
            ];
        }

        return [
            'settings' => [],
            'fields' => []
        ];
    }

    public function save($settingsKey, $settings)
    {
        return Helper::updateOption($settingsKey, $settings);
    }

    public function globalBusinessSettings()
    {
        $defaults = [
            'business_name' => '',
            'portal_page_id' => ''
        ];

        $existingSettings = Helper::getOption('global_business_settings', []);

        if(!$existingSettings) {
            return $defaults;
        }

        return wp_parse_args($existingSettings, $defaults);
    }

    private function getGlobalBusinessSettingsFields()
    {
        return [
            'business_name' => [
                'type' => 'input-text',
                'data-type' => 'text',
                'placeholder' => 'Business Name',
                'label' => 'Business Name'
            ],
            'portal_page_id' => [
                'type' => 'input-text',
                'data-type' => 'number',
                'label' => 'Portal Page ID'
            ]
        ];
    }

    public function globalEmailSettings()
    {
        $defaults = [
            'send_as_html' => 'yes',
            'template' => 'minimal',
            'header' => '',
            'footer' => '',
            'sender_name' => '',
            'sender_email' => '',
            'reply_to_email' => ''
        ];

        $existingSettings = Helper::getOption('global_email_settings', []);

        if(!$existingSettings) {
            return $defaults;
        }

        return wp_parse_args($existingSettings, $defaults);
    }

    private function getGlobalEmailSettingsFields()
    {
        return [
            'template' => [
                'type' => 'input-radio',
                'label' => 'Email Template Type',
                'options' => [
                    [
                        'id' => 'minimal',
                        'label' => 'Minimal'
                    ],
                    [
                        'id' => 'boxed',
                        'label' => 'Boxed'
                    ],
                    [
                        'id' => 'centered',
                        'label' => 'Plain Centered'
                    ]
                ],

                'inline_help' => 'Your Email Template Type'
            ],
            'sender_name' => [
                'type' => 'input-text',
                'data_type' => 'text',
                'placeholder' => 'From Email Name',
                'label' => 'From Email Name'
            ],
            'sender_email' => [
                'type' => 'input-text',
                'data_type' => 'email',
                'placeholder' => 'From Email',
                'label' => 'From Email'
            ],
            'reply_to_email' => [
                'type' => 'input-text',
                'data_type' => 'email',
                'placeholder' => 'Reply To Email',
                'label' => 'Reply To Email'
            ]
        ];
    }
}
