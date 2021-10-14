<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\TicketType;
use FluentSupport\Framework\Request\Request;

class TicketTypeController extends Controller
{
    public function index(Request $request)
    {
        $ticketTypes = TicketType::paginate();

        return [
            'ticket_type' => $ticketTypes
        ];
    }

    public function get(Request $request, $ticketTypeId)
    {
        $ticketTypes = TicketType::findOrFail($ticketTypeId);
        return [
            'ticket_type' => $ticketTypes
        ];
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $data = wp_unslash($data);
        $data['tag_type'] = 'ticket_type';
        $ticketTypes = TicketType::create($data);

        return [
            'message' => __('Ticket Type has been successfully created', 'fluent-support'),
            'ticket_type' => $ticketTypes
        ];
    }

    public function update(Request $request, $ticketTypeId)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $ticketType = TicketType::findOrFail($ticketTypeId);
        $ticketType->fill($data);
        $ticketType->save();

        return [
            'message' => __('Ticket Type has been updated', 'fluent-support'),
            'ticket_type' => TicketType::find($ticketTypeId)
        ];
    }

    public function delete(Request $request, $ticketTypeId)
    {
        TicketType::where('id', $ticketTypeId)
            ->delete();

        return [
            'message' => __('Ticket Type has been deleted', 'fluent-support')
        ];
    }

}
