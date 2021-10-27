<?php

namespace FluentSupport\App\Services\Integrations\Slack;

use FluentSupport\App\Services\Helper;

class SlackHelper
{
    public static function getBotToken()
    {
        $settings = self::getSettings();

        return $settings['bot_token'];
    }

    public static function getChannel()
    {
        $settings = self::getSettings();

        return $settings['channel'];
    }

    public static function getSettings()
    {
        $settings = Helper::getIntegrationOption('slack_settings', []);

        $defaults = [
            'bot_token'           => '',
            'channel'             => '',
            'notification_events' => [],
            'status'              => 'no',
        ];


        if(!$settings) {
            return $defaults;
        }

        return wp_parse_args($settings, $defaults);
    }
}
