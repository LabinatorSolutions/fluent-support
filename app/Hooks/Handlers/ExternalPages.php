<?php

namespace FluentSupport\App\Hooks\Handlers;


use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class ExternalPages
{

    public function route()
    {
        $route = sanitize_text_field($_REQUEST['fs_view']);

        $methodMaps = [
            'ticket' => 'handleTicketView'
        ];

        if (isset($methodMaps[$route])) {
            $this->{$methodMaps[$route]}();
        }

    }

    public function handleTicketView()
    {

        if (!Helper::isPublicSignedTicketEnabled()) {
            $ticketId = absint(Arr::get($_REQUEST, 'ticket_id'));
            $ticket = Ticket::where('id', $ticketId)->first();

            if (!$ticket) {
                // Sorry no ticket found;
                echo '<h3 style="text-align: center; margin: 50px 0;">' . __('Invalid Support Portal URL', 'fluent-support') . '</h3>';
                die();
            }

            $redirectUrl = Helper::getTicketViewUrl($ticket);
            wp_redirect($redirectUrl, 307);
            exit();
        }

        $ticketHash = sanitize_text_field(Arr::get($_REQUEST, 'support_hash'));
        $ticketId = absint(Arr::get($_REQUEST, 'ticket_id'));
        $ticket = Ticket::where('hash', $ticketHash)->where('id', $ticketId)->first();

        if (!$ticket) {
            // Sorry no ticket found;
            echo '<h3 style="text-align: center; margin: 50px 0;">' . __('Invalid Support Portal URL', 'fluent-support') . '</h3>';
            die();
        }

        if (get_current_user_id()) {
            // We have to re-route the URL
            $redirectUrl = Helper::getTicketViewUrl($ticket);
            wp_redirect($redirectUrl, 307);
            exit();
        }

    }

    /**
     * Display the attachment.
     *
     * Uses the new rewrite endpoint to get an attachment ID
     * and display the attachment if the currently logged in user
     * has the authorization to.
     *
     * @return void
     * @since 3.2.0
     */
    public function view_attachment()
    {

        $attachmentHash = sanitize_text_field($_REQUEST['fst_file']);


        if (!empty($attachmentHash)) {

            $attachment = Attachment::where('file_hash', $attachmentHash)->first();

            if (!$attachment) {
                die('Invalid Attachment Hash');
            }

            // check signature hash
            $sign = md5($attachment->id . date('YmdH'));
            if ($sign != $_REQUEST['secure_sign']) {
                $dieMessage = __('Sorry, Your secure sign is invalid, Please reload the previous page and get new signed url', 'fluent-support');
                die($dieMessage);
            }

            if ('local' !== $attachment->driver) {
                wp_redirect($attachment->full_url, 307);
            }

            if (!file_exists($attachment->file_path)) {
                die('File could not be found');
            }

            ob_get_clean();
            ini_set('user_agent', 'Fluent Support/' . FLUENT_SUPPORT_VERSION . '; ' . get_bloginfo('url'));
            header("Content-Type: $attachment->file_type");
            header("Content-Disposition: inline; filename=\"$attachment->title\"");
            echo readfile($attachment->file_path);
            die();
        }

    }


}
