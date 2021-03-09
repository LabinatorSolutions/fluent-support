<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class TicketController extends Controller
{
    public function me(Request $request)
    {
        $user = wp_get_current_user();

        return [
            'user_id'     => $user->id,
            'email'       => $user->user_email,
            'person'      => Helper::getAgentByUserId($user->ID),
            'permissions' => PermissionManager::currentUserPermissions(),
            'request'     => $request->all()
        ];
    }

    public function index(Request $request)
    {
        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product'
        ]);

        // apply filters by access level
        $permissionLevel = PermissionManager::currentUserTicketsPermissionLevel();
        if ($permissionLevel != 'all') {
            $agent = Helper::getAgentByUserId();
            if ($permissionLevel == 'own') {
                $ticketsQuery->where('agent_id', $agent->id);
            } else {
                $ticketsQuery->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id);
                    $q->orWhereNull('agent_id');
                });
            }
        }

        if ($customerId = $request->get('customer_id')) {
            $ticketsQuery = $ticketsQuery->where('customer_id', $customerId);
        }

        if ($filters = $request->get('filters', [])) {
            $ticketsQuery->applyFilters($filters);
        }

        if ($search = $request->get('search')) {
            $ticketsQuery->searchBy($search);
        }

        $ticketsQuery->orderBy($request->get('order_by', 'id'), $request->get('order_type', 'ASC'));

        $tickets = $ticketsQuery->paginate();

        $perPage = $request->get('per_page');

        foreach ($tickets as $ticket) {
	        if($perPage < 15) {
	            if($ticket->status != 'closed') {
	                $ticket->live_activity = TicketHelper::getActivity($ticket->id);
	            } else {
	                $ticket->live_activity = [];
	            }
        	}
        }

        return [
            'tickets' => $tickets
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();
        $ticketWith = $request->get('with', ['customer', 'agent', 'product', 'mailbox']);
        $responseWith = $request->get('response_with', ['person', 'attachments']);

        $ticket = Ticket::with($ticketWith)
            ->findOrFail($ticketId);

        if ($ticket->customer) {
            $ticket->customer->profile_edit_url = $ticket->customer->getUserProfileEditUrl();
        }

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }


        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
        }

        $responses = Response::where('ticket_id', $ticketId)
            ->with($responseWith)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($responses as $response) {
            $response->content = make_clickable($response->content);
        }

        $ticket->content = make_clickable($ticket->content);

        $ticket->live_activity = TicketHelper::getActivity($ticketId, $agent->id);

        return [
            'ticket'        => $ticket,
            'responses'     => $responses,
            'agent_id' => $agent->id
        ];
    }

    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        $agent = Helper::getAgentByUserId(get_current_user_id());

        if (!$agent) {
            return $this->sendError([
                'message' => 'Sorry, You do not have permission. Please add yourself as support agent first'
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $convoType = Arr::get($data, 'conversation_type', 'response');

        $response = [
            'person_id'         => $agent->id,
            'ticket_id'         => $ticketId,
            'conversation_type' => $convoType,
            'content'           => wp_unslash(wp_kses_post($data['content'])),
            'source'            => 'web'
        ];

        $response = apply_filters('fluent_support/new_agent_' . $convoType, $response, $ticket, $agent);

        $createdResponse = Response::create($response);

        if ($attachments = $request->get('attachments')) {
            Attachment::where('ticket_id', $ticketId)
                ->whereIn('file_hash', $attachments)
                ->update([
                    'conversation_id' => $createdResponse->id
                ]);
            $createdResponse->load('attachments');
        }

        if ($ticket->status == 'new') {
            $ticket->status = 'active';
            $ticket->first_response_time = current_time('timestamp') - strtotime($ticket->created_at);
        }

        $agentAdded = false;
        $updateData = [];
        if (!$ticket->agent_id) {
            $ticket->agent_id = $agent->id;
            $agentAdded = true;
            $ticket->load('agent');
            $updateData = [
                'agent_id' => $agent->id,
                'agent'    => $ticket->agent
            ];
        }

        $ticket->last_agent_response = current_time('mysql');
        $ticket->response_count += 1;


        $closed = false;
        if ($isClose = $request->get('close_ticket') == 'yes' && $ticket->status != 'closed') {
            $ticket->status = 'closed';
            $ticket->resolved_at = current_time('mysql');
            $ticket->closed_by = $agent->id;
            $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
            $closed = true;
        }

        $ticket->save();

        $createdResponse->load(['person']);

        do_action('fluent_support/' . $convoType . '_added_by_agent', $createdResponse, $ticket, $agent);

        if ($agentAdded) {
            do_action('fluent_support/agent_assigned_to_ticket', $agent, $ticket);
        }


        if ($closed) {
            do_action('fluent_support/ticket_closed', $ticket, $agent);
            do_action('fluent_support/ticket_closed_by_' . $agent->person_type, $ticket, $agent);
        }

        return [
            'message'     => __('Response has been added'),
            'response'    => $createdResponse,
            'ticket'      => $ticket,
            'update_data' => $updateData
        ];
    }

    public function getTicketWidgets(Request $request, $ticketId)
    {
        $ticket = Ticket::with('customer')->findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $otherTickets = Ticket::where('id', '!=', $ticketId)
            ->select(['id', 'title', 'status', 'created_at'])
            ->where('customer_id', $ticket->customer_id)
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get();

        return [
            'other_tickets' => $otherTickets,
            'extra_widgets' => ProfileInfoService::getProfileExtraWidgets($ticket->customer)
        ];
    }

    public function updateTicketProperty(Request $request, $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $propName = $request->get('prop_name');
        $propValue = $request->get('prop_value');

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $prevValue = $ticket->{$propName};
        if ($propName && $propValue && $prevValue != $propValue) {
            $ticket->{$propName} = $propValue;
            $ticket->save();
        }

        $updateData = [];

        if ($propName == 'product_id') {
            $ticket->load('product');
            $updateData['product'] = $ticket->product;
        } else if ($propName == 'agent_id') {
            $ticket->load('agent');
            $updateData['agent'] = $ticket->agent;
            if ($prevValue != $ticket->{$propName}) {
                do_action('fluent_support/agent_assigned_to_ticket', $ticket->agent, $ticket);
            }
        }

        return [
            'message'     => $propName . ' has been updated',
            'update_data' => $updateData
        ];
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId(get_current_user_id());

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        if ($ticket->status != 'closed') {
            $ticket->status = 'closed';
            $ticket->resolved_at = current_time('mysql');
            $ticket->closed_by = $agent->id;
            $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
            $ticket->save();
            do_action('fluent_support/ticket_closed', $ticket, $agent);
            do_action('fluent_support/ticket_closed_by_' . $agent->person_type, $ticket, $agent);
        }

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => $ticket
        ];
    }

    public function reOpenTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId(get_current_user_id());

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        if ($ticket->status == 'closed') {
            $ticket->status = 'active';
            $ticket->save();
            do_action('fluent_support/ticket_reopen', $ticket, $agent);
            do_action('fluent_support/ticket_reopen_by_' . $agent->person_type, $ticket, $agent);
        }

        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => $ticket
        ];
    }

    public function doBulkActions(Request $request)
    {
        $ticketIds = $request->get('ticket_ids', []);
        $action = $request->get('bulk_action');
        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');
        $agent = Helper::getAgentByUserId();
        $query = Ticket::whereIn('id', $ticketIds);

        if (!$hasAllPermission) {
            $query->where('agent_id', $agent->id);
        }

        if ($action == 'close_tickets') {
            $query->where('status', '!=', 'closed');
        }

        $tickets = $query->get();

        if ($action == 'close_tickets') {
            foreach ($tickets as $ticket) {
                $ticket->status = 'closed';
                $ticket->resolved_at = current_time('mysql');
                $ticket->save();
                do_action('fluent_support/ticket_closed', $ticket, $agent);
                do_action('fluent_support/ticket_closed_by_' . $agent->person_type, $ticket, $agent);
            }

            return [
                'message' => count($tickets) . ' tickets have been closed'
            ];
        }

        $this->sendError([
            'message' => 'Sorry no action found as available'
        ]);
    }

    public function deleteBulk(Request $request)
    {
        $ticketIds = $request->get('ticket_ids', []);

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');
        $agent = Helper::getAgentByUserId();
        $query = Ticket::whereIn('id', $ticketIds);

        if (!$hasAllPermission) {
            $query->where('agent_id', $agent->id);
        }

        $tickets = $query->get();

        foreach ($tickets as $ticket) {
            $ticket->deleteTicket();
        }

        return [
            'message' => count($tickets) . ' has been deleted successfully'
        ];
    }

    public function deleteResponse(Request $request, $ticketId, $responseId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $response = Response::findOrFail($responseId);
        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        if (!$hasAllPermission) {
            if ($ticket->agent_id != $agent->id) {
                return $this->sendError([
                    'message' => 'Sorry, You do not have permission to delete this response'
                ]);
            }
        }

        Response::where('id', $response->id)->delete();

        return [
            'message' => 'Selected response has been deleted'
        ];

    }

    public function updateResponse(Request $request, $ticketId, $responseId)
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        $ticket = Ticket::findOrFail($ticketId);
        $response = Response::findOrFail($responseId);
        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        if (!$hasAllPermission) {
            if ($ticket->agent_id != $agent->id) {
                return $this->sendError([
                    'message' => 'Sorry, You do not have permission to delete this response'
                ]);
            }
        }

        $response->content = wp_unslash(wp_kses_post($data['content']));
        $response->save();

        return [
            'message'  => 'Selected response has been updated',
            'response' => $response
        ];
    }


    public function getLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'live_activity' => TicketHelper::getActivity($ticketId, $agent->id)
        ];
    }

    public function removeLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'result' => TicketHelper::removeFromActivities($ticketId, $agent->id),
            'agent_id' => $agent->id
        ];
    }

}
