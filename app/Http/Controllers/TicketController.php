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
            'customer', 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }
        ]);

        if ($customerData = $request->get('customer')) {

            $customer = Customer::getCustomerFromData($customerData);
            return $customer;

            if (!$customer) {
                return $this->sendError([
                    'message'    => __('No Customer found', 'fluent-support'),
                    'error_type' => 'customer_does_not_exist'
                ]);
            }

            $ticketsQuery = $ticketsQuery->where('customer_id', $customer->id);
        } else if ($customerId = $request->get('customer_id')) {
            $ticketsQuery = $ticketsQuery->where('customer_id', $customerId);
        }

        $tickets = $ticketsQuery->paginate();
        foreach ($tickets as $ticket) {
            $ticket->excerpt = $ticket->getExcerpt();
        }

        return [
            'tickets' => $tickets
        ];
    }

    public function create(Request $request)
    {
        $data = $request->all();

        $this->validate($data, [
            'title'          => 'required',
            'content'        => 'required',
            'customer.email' => 'required|email'
        ]);

        $customerData = Arr::get($data, 'customer');
        $customer = Customer::maybeCreateCustomer($customerData);

        $data['customer_id'] = $customer->id;

        $data = apply_filters('fluent_support/create_ticket_data', $data, $customer);
        do_action('fluent_support/before_ticket_create', $data, $customer);

        $createdTicket = Ticket::create($data);
        $ticket = Ticket::find($createdTicket->id);

        do_action('fluent_support/ticket_created', $ticket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $ticket
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        $ticketWith = $request->get('with', ['customer', 'agent']);
        $responseWith = $request->get('response_with', ['person']);

        $ticket = Ticket::where('id', $ticketId)
            ->with($ticketWith)
            ->first();

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

        $ticket->response_count += 1;
        $ticket->save();

        $createdResponse->load(['person']);

        do_action('fluent_support/' . $convoType . '_added_by_agent', $createdResponse, $ticket);

        return [
            'message'  => __('Response has been added'),
            'response' => $createdResponse,
            'ticket'   => $ticket
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

}
