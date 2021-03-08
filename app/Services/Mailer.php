<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\Framework\Support\Arr;

class Mailer
{
    public static function send($to, $subject, $body, $extraHeader = [], $ticket = false)
    {
        $headers = self::getHeaders($ticket);

        if($extraHeader) {
            foreach ($extraHeader as $header) {
                $headers[] = $header;
            }
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    public static function getHeaders($ticket = false)
    {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        if(!$ticket) {
            return $headers;
        }
        $emailSettings = Helper::getEmailSettings($ticket);
        $fromName = Arr::get($emailSettings, 'sender_name');
        $fromEmail = Arr::get($emailSettings, 'sender_email');
        $replyTo = Arr::get($emailSettings, 'reply_to_email');

        $fromString = $fromName;
        if($fromEmail) {
            $fromString .= ' <'.$fromEmail.'>';
        }

        if ($fromString) {
            $headers[] = "From: {$fromString}";
        }

        // Set Reply-To Header
        if ($replyTo) {
            $headers[] = "Reply-To: $replyTo";
        }

        return $headers;
    }

    public static function getWithTemplate($body)
    {
        $app  = App::getInstance();
        return $app->view->make('emails.classic_template', [
            'email_body' => $body
        ]);
    }
}
