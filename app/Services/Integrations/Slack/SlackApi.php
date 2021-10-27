<?php

namespace FluentSupport\App\Services\Integrations\Slack;

use FluentSupport\App\Services\Integrations\Slack\SlackHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SlackApi
{
    public static function send($message)
    {
        $curl = curl_init("https://slack.com/api/chat.postMessage");
        $data = http_build_query([
            "token" => SlackHelper::getBotToken(),
            "channel" => SlackHelper::getChannel(),
            "attachments" => json_encode($message),
        ]);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Connection: Keep-Alive'
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}
