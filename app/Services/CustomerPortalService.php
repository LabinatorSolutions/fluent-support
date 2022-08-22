<?php
namespace FluentSupport\App\Services;

use Exception;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;

class CustomerPortalService
{
    /**
     * This `getTickets` method is responsible for getting tickets for customer
     * @param object $customer
     * @param array $requestedStatus
     * @since 1.5.7
     * @return object
     * @throws Exception
     */
    public function getTickets ($customer, $requestedStatus )
    {
        $this->validateCustomer($customer);

        $statuses = $this->getTicketStatues( $requestedStatus );

        return $this->ticketsAdditionalData( $customer, $statuses );
    }

    /**
     * getTicket method will get the ticket information with customer and agent as well as response in a ticket by ticket id
     * @param object $request
     * @param int $ticketId
     * @since 1.5.7
     * @return array
     */
    public function getTicket ($request, $ticketId)
    {
        $ticket = $this->getTicketByID($ticketId);

        $customer = $this->getCustomer( $request, $ticket );

        $this->checkCustomerTicketAccess($customer, $ticket);

        return [
            'ticket' => $this->syncTicketAdditionData($ticket),
            'responses' => $this->getResponses($ticketId),
            'sign_on_id' => $ticket->customer_id
        ];
    }

    /**
     * This `createTicket` method is responsible for creating ticket for customer
     * @param object $customer
     * @param array $data
     * @param \FluentSupport\Framework\Request\Request $request
     * @return Ticket
     * @throws Exception
     */
    public function createTicket ( $customer, $data, $request )
    {
        $this->validateCustomer( $customer );

        $data['title'] = sanitize_text_field( wp_unslash( $data['title'] ) );
        $data['content'] = wp_specialchars_decode( wp_unslash( wp_kses_post( $data['content'] ) ) );
        $data['customer_id'] = $customer->id;
        $data['product_source'] = 'local';
        $data['mailbox_id'] =  $this->resolveMailboxId( $request );
        $data['source'] = 'web';

        $disabledFields = apply_filters( 'fluent_support/disabled_ticket_fields', [] );
        $this->validateDisabledFields( $data, $disabledFields );
        return $this->storeTicket( $data, $customer, $disabledFields );
    }


    /**
     * This `createResponse` method is responsible for creating response by customer in a ticket by ticket id, and data
     * @param \FluentSupport\Framework\Request\Request $request
     * @param int $ticketId
     * @param array $data
     * @since 1.5.7
     * @return array
     * @throws Exception
     */
    public function createResponse ($request, $ticketId, $data )
    {
        $data['content'] = wp_specialchars_decode( wp_unslash($data['content'] ));
        $data['conversation_type'] = 'response';

        $ticket = Ticket::with( ['customer'] )->findOrFail( $ticketId );
        $customer = $this->getCustomer( $request, $ticket );

        $this->checkCustomerTicketAccess( $customer, $ticket, 'response' );

        $responseData = (new ResponseService())->createResponse($data, $customer, $ticket);

        return [
            'message'  => __('Reply has been added', 'fluent-support'),
            'response' => $responseData['response'],
            'ticket'   => $responseData['ticket']
        ];
    }


    /**
     * This `closeTicket` is responsible for closing ticket by ticket id
     * @param \FluentSupport\Framework\Request\Request $request
     * @param int $ticketId
     * @return array
     * @throws Exception
     */
    public function closeTicket ( $request, $ticketId )
    {
        $ticket = Ticket::with(['customer'])->findOrFail( $ticketId );
        $customer = $this->getCustomer( $request, $ticket );

        $this->checkCustomerTicketAccess( $customer, $ticket, 'close' );

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => (new TicketService())->close( $ticket, $customer )
        ];
    }

    public function reOpenTicket ( $request, $ticketId )
    {
        $ticket = Ticket::with(['customer'])->findOrFail( $ticketId );
        $customer = $this->getCustomer( $request, $ticket );

        $this->checkCustomerTicketAccess( $customer, $ticket, 'reopen' );

        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => (new TicketService())->reopen($ticket, $customer)
        ];
    }

    /**
     * This `validateDisabledFields` method is responsible for validating disabled fields
     * @param array $data
     * @param array $disabledFields
     * @since 1.5.7
     * @return array $data
     */
    private function validateDisabledFields ( $data, $disabledFields )
    {
        if(!in_array('priority', $disabledFields)) {
            $data['priority'] = sanitize_text_field( $data['client_priority'] );
            $data['client_priority'] = sanitize_text_field( $data['client_priority'] );
        }

        if(in_array('product_services', $disabledFields)) {
            unset( $data['product_id'] );
        }

        return $data;
    }


    /**
     * This `storeTicket` method is responsible for storing a ticket in Ticket Model
     * @param array $data
     * @param object $customer
     * @param array $disabledFields
     * @since 1.5.7
     * @return Ticket
     */
    private function storeTicket ( $data, $customer, $disabledFields )
    {
        /*
         * Filter ticket data
         *
         * @since v1.0.0
         * @param array  $data
         * @param object $customer
         */
        $data = apply_filters( 'fluent_support/create_ticket_data', $data, $customer );

        /*
         * Action before ticket create
         *
         * @since v1.0.0
         * @param array  $data
         * @param object $customer
         */
        do_action( 'fluent_support/before_ticket_create', $data, $customer );

        $ticket = Ticket::create( $data );

        $this->addTicketAttachments( $data, $disabledFields, $ticket, $customer );
        $this->addCustomData( $data, $ticket );
        do_action( 'fluent_support/ticket_created', $ticket, $customer );

        return $ticket;
    }


    /**
     * This `addTicketAttachments` method is responsible for adding attachments to ticket
     * @param array $data
     * @param array $disabledFields
     * @param object $ticket
     * @param object $customer
     * @since 1.5.7
     * @return Ticket
     */
    private function addTicketAttachments ( $data, $disabledFields, $ticket, $customer )
    {
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

        return $ticket;
    }


    /**
     * This `addCustomData` method is responsible for adding custom data to ticket
     * @param array $data
     * @param object $ticket
     * @return void
     */
    private function addCustomData ( $data, $ticket )
    {
        if(defined('FLUENTSUPPORTPRO')) {
            $customData = Arr::get($data, 'custom_data');
            if($customData) {
                $customData = wp_unslash( $customData );
                $ticket->syncCustomFields( $customData );
            }
        }
    }

    /**
     * This `validateCustomer` method is responsible for validating customer
     * @param object|null $customer // It can be null if there's no customer
     * @since 1.5.7
     * @throws Exception
     */
    private function validateCustomer ( $customer )
    {
        if ( !$customer ) {
            throw new Exception('Customer not found', 'no_customer');
        }

        if ( $customer->status == 'inactive' ) {
            throw new Exception('Sorry, You do not have access to customer portal', 'inactive_customer');
        }
    }

    /**
     * This `getCustomer` method is responsible for getting customer
     * @param object $request
     * @param object $ticket
     * @since 1.5.7
     * @return object $customer
     * @throws Exception
     *
     */
    private function getCustomer ( $request, $ticket )
    {
        if ($request->getSafe('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer( $request );
        }

        if ( !$customer ) {
            throw new Exception('Sorry! No customer found', 'no_customer');
        }

        return $customer;
    }

    /**
     * This `getTicketStatues` method is responsible for getting ticket statuses
     * @param array $requestedStatus
     * @since 1.5.7
     * @return array
     */
    private function getTicketStatues ( $requestedStatus )
    {
        return Arr::get([
            'open'   => ['new', 'active', 'on-hold'],
            'all'    => [],
            'closed' => ['closed']
        ], $requestedStatus);
    }

    /**
     * This `ticketsAdditionalData` method is responsible for getting tickets with additional data
     * @param object $customer
     * @param array $statuses
     * @since 1.5.7
     * @return object $tickets
     */
    private function ticketsAdditionalData ( $customer, $statuses )
    {
        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }
        ])
            ->where('customer_id', $customer->id)
            ->latest('id');

        $ticketsQuery->where('customer_id', $customer->id);

        if ($statuses) {
            $ticketsQuery->whereIn('status', $statuses);
        }

        $tickets = $ticketsQuery->paginate();

        foreach ($tickets as $ticket) {
            $ticket->human_date = sprintf(__('%s ago', 'fluent-support'), human_time_diff(strtotime($ticket->created_at), current_time('timestamp')));
            $ticket->preview_response = $ticket->getLastResponse();
        }

        return $tickets;
    }

    /**
     * `resolveCustomer` method will create and return or only return existing customer
     * This method will get customer id or customer info or option to force create as parameter.
     * @param object $request
     * @param bool $forceCreate Default: false // If true, it will create a new customer
     * @return Customer // Collection
     */
    public function resolveCustomer($request, $forceCreate = false)
    {
        $onBehalf = $request->getSafe('on_behalf', '', 'intval');

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
     * resolveMailboxId method will either get information of the mailbox added by user or default and return the id
     * @param $request
     * @return null
     */
    private function resolveMailboxId( $request )
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

    // Supportive methods for getTicket

    /**
     * This `getTicketByID` method is responsible for getting a ticket by id
     * @param $ticketId
     * @return object $ticket
     */
    private function getTicketByID ( $ticketId )
    {
        $ticket = Ticket::where('id', $ticketId)
            ->with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'avatar']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title', 'avatar']);
                },
                'product',
                'attachments'
            ])
            ->first();

        return $ticket;
    }

    /**
     * This `checkCustomerTicketAccess` method is responsible for checking customer ticket access
     * @param object $customer
     * @param object $ticket
     * @return bool true if access is granted
     * @throws Exception
     */
    private function checkCustomerTicketAccess ($customer, $ticket, $action = false)
    {
        if (!$customer) {
            throw new Exception('Sorry, You do not have permission to this support ticket', 'no_customer');
        }

        if($customer->status == 'inactive') {
            throw new Exception('Sorry, You do not have access to customer portal', 'inactive_customer');
        }

        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            if ( $action ) {
                throw new Exception("Sorry! You can not {$action} to this ticket", 'permission_error');
            } else {
                throw new Exception('You do not have permission to view this support ticket', 'permission_error');
            }
        }

        return true;
    }


    /**
     * This `getResponses` method is responsible for getting a ticket's responses by ticket id
     * @param int $ticketId
     * @return mixed
     */
    private function getResponses ( $ticketId )
    {
        $responses = Conversation::where('ticket_id', $ticketId)
            ->with([
                'person' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title', 'avatar']);
                },
                'attachments'
            ])
            ->filterByType(['response', 'ticket_merge_activity', 'ticket_split_activity'])
            ->latest('id')
            ->get();

        foreach ($responses as $response) {
            $response->content = links_add_target(make_clickable($response->content));
            if ($response->person) {
                $response->person->setHidden(['email']);
            }
        }

        return $responses;
    }

    /**
     * This `syncTicketAdditionData` method is responsible for syncing ticket additional data
     * @param object $ticket
     * @return object $ticket
     */
    private function syncTicketAdditionData ($ticket )
    {
        $ticket->content = links_add_target(make_clickable( $ticket->content ));

        if ( $ticket->customer ) {
            $ticket->customer->setHidden(['email']);
        }

        if ( $ticket->agent ) {
            $ticket->agent->setHidden(['email']);
        }

        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
            if ($ticket->closed_by_person) {
                $ticket->closed_by_person->setVisible(['first_name', 'last_name', 'id', 'full_name', 'photo']);
            }
        }

        if( defined('FLUENTSUPPORTPRO') ) {
            $ticket->custom_fields = $ticket->customData( 'public', true );
        }

        return $ticket;
    }

}
