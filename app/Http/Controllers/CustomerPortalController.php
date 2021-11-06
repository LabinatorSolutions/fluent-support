<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalController extends Controller
{
    public function getTickets(Request $request)
    {
        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message'    => __('No Customer Found', 'fluent-support'),
                'error_type' => 'no_customer'
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
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

        return [
            'tickets' => $tickets
        ];
    }

    public function createTicket(Request $request)
    {
        $data = $request->all();

        $this->validate($data, [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $data['title'] = sanitize_text_field(wp_unslash($data['title']));

        $data['content'] = wp_unslash(wp_kses_post($data['content']));

        $customer = $this->resolveCustomer($request, true);

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }

        if (!$customer) {
            return $this->sendError([
                'message'    => __('No Customer Found', 'fluent-support'),
                'error_type' => 'no_customer'
            ]);
        }

        $data['customer_id'] = $customer->id;
        $data['product_source'] = 'local';
        $data['mailbox_id'] = $this->resolveMailboxId($request);
        $data['priority'] = sanitize_text_field($data['client_priority']);
        $data['client_priority'] = sanitize_text_field($data['client_priority']);
        $data['source'] = 'web';

        $data = apply_filters('fluent_support/create_ticket_data', $data, $customer);
        do_action('fluent_support/before_ticket_create', $data, $customer);
        $ticket = Ticket::create($data);

        if ($attachments = Arr::get($data, 'attachments')) {
            Attachment::whereNull('ticket_id')
                ->whereIn('file_hash', $attachments)
                ->whereNull('ticket_id')
                ->update([
                    'ticket_id' => $ticket->id,
                    'person_id' => $customer->id,
                    'status'    => 'active'
                ]);
            $ticket->load('attachments');
        }


        if(defined('FLUENTSUPPORTPRO')) {
            $customData = Arr::get($data, 'custom_data');
            if($customData) {
                $customData = wp_unslash($customData);
                $ticket->syncCustomFields($customData);
            }
        }

        do_action('fluent_support/ticket_created', $ticket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $ticket
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::where('id', $ticketId)
            ->with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title']);
                },
                'product',
                'attachments'
            ])
            ->first();

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if (!$customer) {
            return $this->sendError([
                'message'    => __('Sorry, You do not have permission to this support ticket', 'fluent-support'),
                'error_type' => 'no_customer'
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message'    => __('You do not have permission to view this support ticket', 'fluent-support'),
                'error_type' => 'permission_error'
            ]);
        }

        $responses = Conversation::where('ticket_id', $ticketId)
            ->with([
                'person' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title']);
                },
                'attachments'
            ])
            ->filterByType('response')
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($responses as $response) {
            $response->content = make_clickable($response->content);
            if ($response->person) {
                $response->person->setHidden(['email']);
            }
        }

        $ticket->content = make_clickable($ticket->content);

        if ($ticket->customer) {
            $ticket->customer->setHidden(['email']);
        }

        if ($ticket->agent) {
            $ticket->agent->setHidden(['email']);
        }

        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
            if ($ticket->closed_by_person) {
                $ticket->closed_by_person->setVisible(['first_name', 'last_name', 'id', 'full_name', 'photo']);
            }
        }

        return [
            'ticket'     => $ticket,
            'responses'  => $responses,
            'sign_on_id' => $ticket->customer_id
        ];
    }

    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();
        $this->validate($data, [
            'content' => 'required'
        ]);

        $data['content'] = wp_unslash(wp_kses_post($data['content']));


        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not reply to this ticket', 'fluent-support')
            ]);
        }

        $data['conversation_type'] = 'response';
        $responseData = (new ResponseService())->createResponse($data, $customer, $ticket);

        return [
            'message'  => __('Reply has been added', 'fluent-support'),
            'response' => $responseData['response'],
            'ticket'   => $responseData['ticket']
        ];
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => (new TicketService())->close($ticket, $customer)
        ];
    }

    public function reOpenTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }


        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => (new TicketService())->reopen($ticket, $customer)
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
                'user_id'         => $user->id,
                'email'           => $user->user_email,
                'last_ip_address' => $request->getIp()
            ];
        }

        if ($forceCreate) {
            return Customer::maybeCreateCustomer($onBehalf);
        }

        return Customer::getCustomerFromData($onBehalf);
    }

    public function getPublicOptions()
    {
        $products = Product::select(['id', 'title'])->get();

        return [
            'support_products'           => $products,
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ];
    }

    public function getCustomFieldsRender()
    {
        if(!defined('FLUENTSUPPORTPRO')) {
            return [
                'custom_fields_rendered' => []
            ];
        }

        return [
            'custom_fields_rendered' =>  \FluentSupportPro\App\Services\CustomFieldsService::getRenderedPublicFields()
        ];

    }

    private function resolveMailboxId($request)
    {
        if ($mailboxId = $request->get('mailbox_id')) {
            $mailbox = MailBox::find($mailboxId);
            if ($mailbox) {
                return $mailbox->id;
            }
        }

        $mailbox = Helper::getDefaultMailBox();

        if ($mailbox) {
            return $mailbox->id;
        }
        return null;
    }
}
