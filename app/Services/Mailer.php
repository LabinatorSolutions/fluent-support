<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\App;
use FluentSupport\App\Services\Storage\MailAttachments;
use FluentSupport\Framework\Support\Arr;

class Mailer
{
    public static function send($to, $subject, $body, $extraHeader = [], $attachments = [])
    {
        $headers = self::getHeaders();

        if($extraHeader) {
            foreach ($extraHeader as $header) {
                $headers[] = $header;
            }
        }

        return wp_mail($to, $subject, $body, $headers, $attachments);
    }

    /**
     * Send with ticket attachments. Remote files are fetched when needed; files that
     * can't be attached, or are over the size limit, are listed as links in the body.
     *
     * @param iterable|null $files Attachment models.
     * @param bool $toCustomer False for emails to agents and admins.
     */
    public static function sendWithFiles($to, $subject, $body, $extraHeader = [], $files = null, $toCustomer = true)
    {
        $mailFiles = new MailAttachments($files, $toCustomer);

        try {
            return self::send($to, $subject, $mailFiles->addLinksToBody($body), $extraHeader, $mailFiles->getPaths());
        } finally {
            $mailFiles->cleanup();
        }
    }

    public static function getHeaders()
    {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
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
