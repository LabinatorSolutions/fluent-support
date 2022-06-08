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

/**
 * CustomerPortalController class for REST API
 * This class is responsible for getting data for all request related to customer and customer portal
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */

class CustomerPortalController extends Controller
{
    /**
     * getTickets will generate ticket information with customer and agents by customer
     * @param Request $request
     * @return array
     */
    public function getTickets(Request $request)
    {
        //Get customer information
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

        //get tickets with customer and agent
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
            $ticket->human_date = sprintf(__('%s ago', 'fluent-support'), human_time_diff(strtotime($ticket->created_at), current_time('timestamp')));
            $ticket->preview_response = $ticket->getLastResponse();
        }

        return [
            'tickets' => $tickets
        ];
    }

    /**
     * createTicket method will create ticket submitted by customers
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
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

        $disabledFields = apply_filters('fluent_support/disabled_ticket_fields', []);

        if(!in_array('priority', $disabledFields)) {
            $data['priority'] = sanitize_text_field($data['client_priority']);
            $data['client_priority'] = sanitize_text_field($data['client_priority']);
        }

        if(in_array('product_services', $disabledFields)) {
            unset($data['product_id']);
        }

        $data['source'] = 'web';

        /*
         * Filter ticket data
         *
         * @since v1.0.0
         * @param array  $data
         * @param object $customer
         */
        $data = apply_filters('fluent_support/create_ticket_data', $data, $customer);

        /*
         * Action before ticket create
         *
         * @since v1.0.0
         * @param array  $data
         * @param object $customer
         */
        do_action('fluent_support/before_ticket_create', $data, $customer);
        $ticket = Ticket::create($data);

        if (($attachments = Arr::get($data, 'attachments')) && !in_array('file_upload', $disabledFields)) {

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

        /*
         * Action on ticket create
         *
         * @since v1.0.0
         * @param object $ticket
         * @param object $customer
         */
        do_action('fluent_support/ticket_created', $ticket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $ticket
        ];
    }

    /**
     * getTicket method will get the ticket information with customer and agent as well as response in a ticket by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getTicket(Request $request, $ticketId)
    {
        //Get ticket by id with customer, agent, product and attachments
        $ticket = Ticket::where('id', $ticketId)
            ->with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title', 'avatar']);
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

        //Get responses in a ticket by
        $responses = Conversation::where('ticket_id', $ticketId)
            ->with([
                'person' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title', 'avatar']);
                },
                'attachments'
            ])
            ->filterByType(['response', 'ticket_merge_activity'])
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

        if(defined('FLUENTSUPPORTPRO')) {
            $ticket->custom_fields = $ticket->customData('public', true);
        }

        return [
            'ticket'     => $ticket,
            'responses'  => $responses,
            'sign_on_id' => $ticket->customer_id
        ];
    }

    /**
     * createResponse method will create response by customer in a ticket by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
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

    /**
     * closeTicket method will close a ticket by customer using ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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

    /**
     * closeTicket method will re-open a ticket by customer using ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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

    /**
     * resolveCustomer method will create and return or only return existing customer
     * This method will het customer id or customer info or option to force create as parameter.
     * @param $request
     * @param false $forceCreate
     * @return false|Customer
     */
    private function resolveCustomer($request, $forceCreate = false)
    {
        $onBehalf = $request->get('on_behalf');

        if (!$onBehalf) {
            $user = get_user_by('ID', get_current_user_id());
            if (!$user) {
                return false;
            }
            $onBehalf = [
                'user_id'         => $user->ID,
                'email'           => $user->user_email,
                'last_ip_address' => $request->getIp()
            ];
        }

        if ($forceCreate) {
            return Customer::maybeCreateCustomer($onBehalf);
        }

        return Customer::getCustomerFromData($onBehalf);
    }

    /**
     * getPublicOptions method will return the list of product and customer priorities
     * @return array
     */
    public function getPublicOptions()
    {
        $products = Product::select(['id', 'title'])->get();

        return [
            'support_products'           => $products,
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ];
    }

    /**
     * getCustomFieldsRender method will return the list of custom fields
     * @return array|array[]
     */
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

    /**
     * resolveMailboxId method will either get information of the mailbox added by user or default and return the id
     * @param $request
     * @return null
     */
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


    /**
     * logout method will logout the customer
     * @return mixed
     */
    public function logout()
    {
        wp_logout();

        if(is_wp_error(wp_logout())) {
            return $this->sendError([
                'message' => __('Sorry! Something went wrong', 'fluent-support')
            ]);
        } else {
            return $this->sendSuccess([
                'message' => __('You have been logged out', 'fluent-support')
            ]);
        }
    }
}
