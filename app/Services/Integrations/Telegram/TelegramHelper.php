<?php

namespace FluentSupport\App\Services\Integrations\Telegram;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class TelegramHelper
{
    public static function getBotToken()
    {
        $settings = self::getSettings();

        return $settings['bot_token'];
    }

    public static function getWebhookToken()
    {
        $token = Helper::getIntegrationOption('_telegram_webhook_token', false);

        if(!$token) {
            $token = substr(wp_generate_uuid4(), 0, 12);
            Helper::updateIntegrationOption('_telegram_webhook_token', $token);
        }

        return $token;
    }

    public static function getSettings()
    {
        $settings = Helper::getIntegrationOption('telegram_settings', []);

        $defaults = [
            'bot_token'           => '',
            'chat_id'             => '',
            'notification_events' => [],
            'test_message'        => '',
            'status'              => 'no'
        ];


        if(!$settings) {
            return $defaults;
        }

        return wp_parse_args($settings, $defaults);
    }

    public static function parseTelegramBotPayload($payload)
    {

        if(!is_array($payload) || !Arr::get($payload, 'message.text')) {
            return new \WP_Error('invalid', 'Invalid Payload');
        }

        $replyTo = Arr::get($payload, 'message.reply_to_message');
        if(!$replyTo) {
            return new \WP_Error('no_reply_found', 'Not a reply to message');
        }

        $replyToText = Arr::get($replyTo, 'text');

        if(!$replyToText) {
            return new \WP_Error('no_reply_text', 'No reply text found');
        }

        $senderId = Arr::get($payload, 'message.from.id');

        if(!$senderId) {
            return new \WP_Error('no_agent', 'No Matched Agent found');
        }

        $personMeta = Meta::where('object_type', 'person_meta')
            ->where('key', 'telegram_chat_id')
            ->where('value', $senderId)
            ->first();

        if(!$personMeta) {
            return new \WP_Error('no_agent', 'No Matched Agent found on database telegram settings');
        }

        $agent_id = $personMeta->object_id;

        preg_match('/#(.?[0-9]*)\\n/', $replyToText, $matches);

        $ticketId = false;
        if(count($matches) >=2) {
            $ticketId = $matches[1];
        }

        if(!$ticketId) {
            return new \WP_Error('no_ticket_id', 'No Ticket ID found from Payload');
        }

        return [
            'ticket_id' => $ticketId,
            'response_text' => wpautop(Arr::get($payload, 'message.text')),
            'agent_id' => $agent_id
        ];

    }
}
