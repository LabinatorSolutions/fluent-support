<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalController extends Controller
{
    public function getTickets(Request $request)
    {
        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message'       => 'No Customer Found',
                'error_type' => 'no_customer'
            ]);
        }

        $statuses = Arr::get([
            'open'   => ['new', 'active', 'on-hold'],
            'all'    => [],
            'closed' => ['closed']
        ], $request->get('filter_type', ''));

        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }
        ])
            ->where('customer_id', $customer->id)
            ->orderBy('id', 'DESC');

        $ticketsQuery->where('customer_id', $customer->id);

        if ($statuses) {
            $ticketsQuery->whereIn('status', $statuses);
        }

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
        $ticket = Ticket::where('id', $ticketId)
            ->with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'last_name', 'id']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'last_name', 'id']);
                }
            ])
            ->first();

        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message'       => 'No Customer Found',
                'error_type' => 'no_customer'
            ]);
        }

        if($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => 'You do not have permission to view this ticket',
                'error_type' => 'permission_error'
            ]);
        }

        $responses = Response::where('ticket_id', $ticketId)
            ->with([
                'person' => function ($query) {
                    $query->select(['first_name', 'last_name', 'id']);
                }
            ])
            ->orderBy('id', 'DESC')
            ->get();

        return [
            'ticket'    => $ticket,
            'responses' => $responses,
            'sign_on_id' => $customer->id
        ];
    }

    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();
        $this->validate($data, [
            'content' => 'required'
        ]);

        $customer = $this->resolveCustomer($request);

        if(!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not reply to this ticket', 'fluent-support')
            ]);
        }

        $responseData = [
            'person_id'         => $customer->id,
            'ticket_id'         => $ticketId,
            'conversation_type' => 'response',
            'content'           => wp_unslash(wp_kses_post($data['content'])),
            'source'            => (isset($data['source'])) ? $data['source'] : 'web'
        ];

        $createdResponse = Response::create($responseData);

        if ($ticket->status == 'new') {
            $ticket->status = 'active';
            $ticket->first_response_time = current_time('timestamp') - strtotime($ticket->created_at);
        }

        $ticket->response_count += 1;
        $ticket->save();

        $response = Response::with('person')->find($createdResponse->id);
        do_action('fluent_support/response_added_by_customer', $response, $ticket);

        return [
            'message' => __('Reply has been added', 'fluent-support'),
            'response' => $response,
            'ticket' => $ticket
        ];
    }

    private function resolveCustomer($request, $forceCreate = false)
    {
        $onBehalf = $request->get('on_behalf');

        if (!$onBehalf) {
            $user = get_user_by('ID', get_current_user_id());
            if (!$user) {
                return false;
            }
            $onBehalf = [
                'user_id' => $user->id,
                'email'   => $user->user_email
            ];
        }

        if ($forceCreate) {
            return Customer::maybeCreateCustomer($onBehalf);
        }

        return Customer::getCustomerFromData($onBehalf);
    }
}
