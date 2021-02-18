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
    public function index(Request $request)
    {
        $tickets = Ticket::with(['customer', 'agent'])->paginate();
        foreach ($tickets as $ticket) {
            $ticket->excerpt = $ticket->getExcerpt();
        }

        return [
            'tickets'   => $tickets
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        return [
            'ticket'    => Ticket::where('id', $ticketId)->with(['customer', 'agent'])->first(),
            'responses' => Response::where('ticket_id', $ticketId)
                ->with('person')
                ->orderBy('id', 'DESC')
                ->get()
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

        $createdResponse = Response::create($response);

        if ($ticket->status == 'new') {
            $ticket->status = 'active';
            $ticket->first_response_time = current_time('timestamp') - strtotime($ticket->created_at);
        }

        $ticket->response_count += 1;
        $ticket->save();

        $createdResponse->load(['person']);

        do_action('fluent_support/' . $convoType . '_added_by_by_agent', $createdResponse);

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
