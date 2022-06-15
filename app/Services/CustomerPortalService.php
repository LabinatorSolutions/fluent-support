<?php
namespace FluentSupport\App\Services;

use Exception;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Support\Arr;

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
     * This `createTicket` method is responsible for creating ticket for customer
     * @param object $customer
     * @param array $data
     * @param int $mailbox
     * @return Ticket
     * @throws Exception
     */
    public function createTicket ( $customer, $data, $mailbox )
    {
        $this->validateCustomer( $customer );

        $data['title'] = sanitize_text_field( wp_unslash( $data['title'] ) );
        $data['content'] = wp_unslash( wp_kses_post( $data['content'] ) );
        $data['customer_id'] = $customer->id;
        $data['product_source'] = 'local';
        $data['mailbox_id'] =  $mailbox;
        $data['source'] = 'web';

        $disabledFields = apply_filters( 'fluent_support/disabled_ticket_fields', [] );
        $this->validateDisabledFields( $data, $disabledFields );
        return $this->storeTicket( $data, $customer, $disabledFields );
    }


    /**
     * This `validateDisabledFields` method is responsible for validating disabled fields
     * @param array $data
     * @param array $disabledFields
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

        return $tickets;
    }
}
