<?php

namespace FluentSupport\App\Http\Policies;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

class PortalPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  \FluentSupport\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        if($request->get('on_behalf')) {
            return PermissionManager::currentUserCan('fst_sensitive_data') || PermissionManager::currentUserCan('fst_manage_other_tickets');
        }

        return !!get_current_user_id();
    }

    /**
     * @param  \FluentSupport\Framework\Request\Request $request
     * @return Boolean
     */
    public function getTicket(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function createResponse(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function closeTicket(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function reOpenTicket(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    public function uploadTicketFiles(Request $request)
    {
        return $this->maybePublicSignedRequest($request);
    }

    /**
     * @param  \FluentSupport\Framework\Request\Request $request
     * @return Boolean
     */

    protected function maybePublicSignedRequest($request)
    {
        if($ticketHash = $request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $ticketHash = sanitize_text_field($ticketHash);
            $ticketId = absint($request->get('ticket_id'));
            return !! Ticket::where('hash', $ticketHash)->where('id', $ticketId)->count();
        }

        return $this->verifyRequest($request);
    }
}
