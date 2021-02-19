<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class ConversationController extends Controller
{
    public function createCustomerReply(Request $request, $ticketId)
    {
        $data = $request->all();
        $this->validate($data, [
            'content' => 'required',
            'customer.email' => 'required|email'
        ]);

        $customer = Customer::getCustomerFromData(Arr::get($data, 'customer'));

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
            'response' => $response
        ];

    }
}
