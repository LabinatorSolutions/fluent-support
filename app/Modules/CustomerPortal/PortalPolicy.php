<?php

namespace FluentSupport\App\Modules\CustomerPortal;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

/**
 * Who may call the customer portal routes (app/Modules/CustomerPortal/routes.php):
 * a logged-in customer, a remote portal acting on_behalf of one, or a signed
 * ticket link for its own ticket. Which ticket they may open is checked in
 * PortalController::findTicket().
 */
class PortalPolicy extends Policy
{
    /**
     * Whether the current user may act on_behalf of a customer. See verifyRequest().
     *
     * @return bool
     */
    public static function canActOnBehalf()
    {
        return current_user_can('manage_options') || (
            PermissionManager::userCan('fst_sensitive_data')
            && PermissionManager::userCan('fst_manage_other_tickets')
            && PermissionManager::userCan('fst_manage_settings')
            && Helper::getAgentByUserId()
        );
    }

    /**
     * @param \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        // on_behalf lets a remote API client act as a customer: a site admin (who need not
        // be an agent), or a senior agent who manages customers, all tickets and settings. An agent is
        // still held to their own ticket access in PortalService::assertCanAccess().
        // The agent record is required because mailbox restrictions live on it; leftover
        // permission meta without one would otherwise carry no restrictions at all.
        if ($request->get('on_behalf')) {
            return self::canActOnBehalf();
        }

        $hasAccess = !!get_current_user_id();

        if (!$hasAccess) {
            return false;
        }

        /*
         * Filter portal access settings
         *
         * @since v1.0.0
         * @param array $canAccess
         */
        $canAccess = apply_filters('fluent_support/user_portal_access_config', [
            'status'  => true,
            'message' => __('You do not have permission', 'fluent-support')
        ]);

        return $canAccess['status'];
    }

    // A signed ticket link may view, reply to, close and re-open its own ticket
    // without logging in.
    public function show(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function reply(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function close(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function reopen(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    /**
     * Agents need a write capability here: the ticket ones mirror replying,
     * the settings pair covers the editor's paste-to-upload.
     *
     * @param \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function uploadTicketFiles(Request $request)
    {
        // An on_behalf upload can create or update a customer, so it needs the same
        // rights as every other on_behalf portal call, not just upload rights.
        if ($request->get('on_behalf')) {
            return self::canActOnBehalf();
        }

        if (Helper::getCurrentAgent()) {
            return PermissionManager::canManageTickets()
                || PermissionManager::userCan([
                    'fst_draft_reply',
                    'fst_manage_settings',
                    'fst_manage_saved_replies'
                ]);
        }

        return $this->maybePublicSignedRequest($request);
    }

    public function getCountries(Request $request)
    {
        if ($person = Helper::getCurrentAgent()) {
            return true;
        }

        return $this->maybePublicSignedRequest($request);
    }

    /**
     * @param \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    protected function maybePublicSignedRequest($request)
    {
        $ticketHash = $request->getSafe('intended_ticket_hash', 'sanitize_text_field');

        if ($ticketHash && Helper::isPublicSignedTicketEnabled()) {
            $urlParams = (array) $request->get_url_params();
            $ticketId  = isset($urlParams['ticket_id']) ? (int) $urlParams['ticket_id'] : $request->getSafe('ticket_id', 'intval');
            return !!Ticket::where('hash', $ticketHash)
                ->wherePublicIdentifier($ticketId)
                ->first();
        }

        // With signed links off, a hash only comes from a remote portal relaying an
        // anonymous visitor. Falling through would make them the API user's customer.
        if ($ticketHash && !$request->get('on_behalf') && self::canActOnBehalf()) {
            return false;
        }

        return $this->verifyRequest($request);
    }

}
