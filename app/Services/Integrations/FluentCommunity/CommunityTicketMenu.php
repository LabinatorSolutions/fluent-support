<?php

namespace FluentSupport\App\Services\Integrations\FluentCommunity;

use FluentSupport\App\Modules\CommunityTicket\CommunityTicketPolicy;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Vite;

/**
 * Adds "Create Support Ticket" to the FluentCommunity post and comment menus for the
 * moderators who may use it. FluentCommunity loads the script between its own start
 * script and the app, so the menu filters exist before the first render.
 */
class CommunityTicketMenu
{
    const SCRIPT_HANDLE = 'fluent_support_community_ticket';

    public function boot()
    {
        add_filter('fluent_community/module_scripts', [$this, 'addScript'], 10, 2);
        add_action('fluent_community/portal_footer', [$this, 'printConfig']);
    }

    public function addScript($scripts, $runtime = 'portal')
    {
        if ($runtime === 'portal' && CommunityTicketPolicy::currentUserCan()) {
            $scripts[self::SCRIPT_HANDLE] = [
                'url' => Vite::getEnqueuePath('admin/js/community_ticket_menu.js'),
            ];
        }

        return $scripts;
    }

    /**
     * Printed before the module scripts run, so it needs nothing from FluentCommunity.
     */
    public function printConfig()
    {
        if (!CommunityTicketPolicy::currentUserCan()) {
            return;
        }

        $config = [
            'url'            => esc_url_raw(rest_url('fluent-support/v2/community/ticket')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'label'          => __('Create Support Ticket', 'fluent-support'),
            /* translators: %s: name of the post or comment author */
            'confirm'        => __('Create a support ticket for %s?', 'fluent-support'),
            'notify_label'   => __('Email the author about the new ticket', 'fluent-support'),
            'confirm_button' => __('Create Ticket', 'fluent-support'),
            'cancel_button'  => __('Cancel', 'fluent-support'),
            /* translators: %s: support ticket number, as the admin shows it */
            'created'        => __('Support ticket #%s created. Click here to open it.', 'fluent-support'),
            /* translators: %s: support ticket number, as the admin shows it */
            'exists'         => __('Support ticket #%s already exists for this post or comment. Click here to open it.', 'fluent-support'),
            'error'          => __('The support ticket could not be created.', 'fluent-support'),
            /* translators: %s: support ticket number, as the admin shows it */
            'view'           => __('View Ticket #%s', 'fluent-support'),
            'exists_label'   => __('Support ticket exists', 'fluent-support'),
            /* translators: %s: support ticket number, as the admin shows it */
            'created_locked' => __("Support ticket #%s created. You don't have access to open it.", 'fluent-support'),
            'can_create'     => $this->canCreateInDefaultMailbox(),
        ];

        echo '<script>window.fsCommunityTicket = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * The endpoint puts new tickets in the default mailbox, so an agent restricted from it
     * gets no Create item instead of a 403 after the click. The server still checks.
     */
    private function canCreateInDefaultMailbox()
    {
        $mailbox = Helper::getDefaultMailBox();
        if (!$mailbox) {
            return true;
        }

        return !in_array((int) $mailbox->id, (new AgentTicketAccess())->getRestrictedMailboxIds(), true);
    }
}
