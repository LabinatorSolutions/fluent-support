<?php

namespace FluentSupport\App\Http\Policies;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

class AgentTicketPolicy extends Policy
{
    /**
     * Reads need view permission, writes need manage permission.
     *
     * Use getMethod(), not method(). method() returns the raw REQUEST_METHOD, but
     * WordPress dispatches on the _method / X-HTTP-Method-Override value, so a
     * write could pass as a GET.
     *
     * @param \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        // @phpstan-ignore-next-line -- proxied to WP_REST_Request by Request::__call()
        if ($request->getMethod() === 'GET') {
            $status = PermissionManager::userCan([
                'fst_view_tickets', 
                'fst_draft_reply', 
                'fst_manage_own_tickets',
                'fst_manage_unassigned_tickets', 
                'fst_manage_other_tickets'
            ]);
        } else {
            // Unknown verbs land here too. Writes open to draft/view-only agents
            // have their own policy methods below.
            $status = PermissionManager::canManageTickets();
        }

        return apply_filters('fluent_support/agent_has_access', $status, $request);
    }

    /**
     * Backs the Add Ticket flow.
     */
    public function searchContact(Request $request)
    {
        return PermissionManager::canManageTickets();
    }

    /**
     * Same gates as the single-ticket routes. Both assign actions write agent_id,
     * so both need fst_assign_agents.
     */
    public function doBulkActions(Request $request)
    {
        $action = $request->getSafe('bulk_action', 'sanitize_text_field');

        if ($action === 'delete_tickets') {
            return PermissionManager::currentUserCan('fst_delete_tickets')
                && PermissionManager::canManageTickets();
        }

        if ($action === 'assign_agent' || $action === 'assign_agent_group') {
            return PermissionManager::currentUserCan('fst_assign_agents')
                && PermissionManager::canManageTickets();
        }

        return PermissionManager::canManageTickets();
    }

    /**
     * View-only and draft agents see every ticket, so fst_delete_tickets alone
     * would let them delete any of them.
     */
    public function deleteTicket(Request $request)
    {
        return PermissionManager::currentUserCan('fst_delete_tickets')
            && PermissionManager::canManageTickets();
    }

    /**
     * Same gate as deleteTicket. The controller checks per-ticket access.
     */
    public function deleteResponse(Request $request)
    {
        return PermissionManager::currentUserCan('fst_delete_tickets')
            && PermissionManager::canManageTickets();
    }

    /**
     * Author, type and per-ticket access are checked in the controller.
     */
    public function updateResponse(Request $request)
    {
        return PermissionManager::canManageTickets();
    }

    public function createResponse(Request $request)
    {
        return PermissionManager::canManageTickets()
            || PermissionManager::currentUserCan('fst_draft_reply');
    }

    public function createOrUpdatDraft(Request $request)
    {
        return PermissionManager::canManageTickets()
            || PermissionManager::currentUserCan('fst_draft_reply');
    }

    /**
     * Saved label searches are personal, so any agent with ticket access may write them.
     */
    public function storeOrUpdateLabelSearch(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function deleteLabelSearch(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function removeLiveActivity(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function updateTicketProperty(Request $request)
    {
        return PermissionManager::canManageTickets();
    }

    /**
     * Editing what the customer wrote is for site admins only (removing sensitive
     * data). The controller checks per-ticket access.
     */
    public function updateTicketContent(Request $request)
    {
        return current_user_can('manage_options') && PermissionManager::canManageTickets();
    }

    public function addTicketWatchers(Request $request)
    {
        return PermissionManager::canManageTickets();
    }

    public function syncTicketWatchers(Request $request)
    {
        return PermissionManager::canManageTickets();
    }


    public function approveDraftResponse(Request $request)
    {
        return PermissionManager::currentUserCan('fst_approve_draft_reply')
            && PermissionManager::canAccessTicketRoutes();
    }

    public function deleteDraft(Request $request)
    {
        return PermissionManager::canManageTickets()
            || PermissionManager::currentUserCan('fst_draft_reply');
    }

    // AI-assist actions (Pro's ai/* routes).
    public function generateResponse(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function getTicketSummary(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function getTicketTone(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    // In-app notifications.
    public function markRead(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }

    public function markAllRead(Request $request)
    {
        return PermissionManager::canAccessTicketRoutes();
    }
}
