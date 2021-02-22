<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class TicketController extends Controller
{
    public function me(Request $request)
    {
        $user = get_user_by('ID', get_current_user_id());

        return [
            'me'      => $user,
            'request' => $request->all()
        ];
    }

    public function index(Request $request)
    {
        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product'
        ]);

        if ($customerId = $request->get('customer_id')) {
            $ticketsQuery = $ticketsQuery->where('customer_id', $customerId);
        }

        if ($filters = $request->get('filters', [])) {
            $ticketsQuery->applyFilters($filters);
        }

        if($search = $request->get('search')) {
            $ticketsQuery->searchBy($search);
        }

        $ticketsQuery->orderBy($request->get('order_by', 'id'), $request->get('order_type', 'ASC'));

        $tickets = $ticketsQuery->paginate();
        foreach ($tickets as $ticket) {
            $ticket->excerpt = $ticket->getExcerpt();
        }

        return [
            'tickets' => $tickets
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        $ticketWith = $request->get('with', ['customer', 'agent', 'product']);
        $responseWith = $request->get('response_with', ['person']);

        $ticket = Ticket::with($ticketWith)
            ->findOrFail($ticketId);

        if ($ticket->customer) {
            $ticket->customer->profile_edit_url = $ticket->customer->getUserProfileEditUrl();
        }

        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
        }

        $responses = Response::where('ticket_id', $ticketId)
            ->with($responseWith)
            ->orderBy('id', 'DESC')
            ->get();

        return [
            'ticket'    => $ticket,
            'responses' => $responses
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
        $ticket->save();

        $createdResponse->load(['person']);

        do_action('fluent_support/' . $convoType . '_added_by_agent', $createdResponse, $ticket);

        if ($agentAdded) {
            do_action('fluent_support/agent_assigned_to_ticket', $agent, $ticket);
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

}
