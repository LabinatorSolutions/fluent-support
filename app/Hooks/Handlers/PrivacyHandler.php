<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

class PrivacyHandler
{
    public function init()
    {
        add_filter( 'wp_privacy_personal_data_exporters', [$this, 'wpRegisterExporter'] );
        add_filter( 'wp_privacy_personal_data_erasers', [$this, 'wpRegisterEraser'] );
    }

    public function exportPersonalData($user_email, $page = 1)
    {
        $customer = Customer::where('email', $user_email)->select(['id'])->first();
        $tickets = Ticket::with(['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments'])
            ->where('customer_id', $customer->id)
            ->get();
        $data = [];
        foreach ($tickets as $ticket) {
            $data[] = [
                'group_id' => 'fluent-support',
                'group_label' => 'Fluent Support',
                'item_id' => 'ticket-' . $ticket->id,
                'data' => [
                    [
                        'name' => 'Ticket ID',
                        'value' => $ticket->id,
                    ],
                    [
                        'name' => 'Ticket Title',
                        'value' => sanitize_title($ticket->title),
                    ],
                    [
                        'name' => 'Ticket Content',
                        'value' => sanitize_textarea_field($ticket->content),
                    ],
                    [
                        'name' => 'Ticket Attachments',
                        'value' => $this->ticketAttachments($ticket),
                    ],
                    [
                        'name' => 'Ticket Product',
                        'value' => $ticket->product->title,
                    ],
                    [
                        'name' => 'Ticket Status',
                        'value' => $ticket->status,
                    ],
                    [
                        'name' => 'Ticket Priority',
                        'value' => $ticket->priority,
                    ],
                    [
                        'name' => 'Ticket Created At',
                        'value' => $ticket->created_at,
                    ],
                    [
                        'name' => 'Ticket Updated At',
                        'value' => $ticket->updated_at,
                    ],
                ],
            ];
        }
        return [
            'data' => $data,
            'done' => true,
        ];
    }

    /**
     * Registers all data exporters.
     *
     * @param array $exporters
     *
     * @return mixed
     */
    public function wpRegisterExporter( $exporters )
    {
        $exporters['fluent-support'] = array(
            'exporter_friendly_name' => __( 'Fluent Support Personal Data Exporter', 'fluent-support' ),
            'callback'               => [ $this, 'exportPersonalData' ],
        );
        return $exporters;
    }

    public function ticketAttachments($ticket)
    {
        $attachments = Attachment::where('ticket_id', $ticket->id)->select(['title'])->get();
        $text = '';

        foreach ($attachments as $attachment) {
            $text .= sanitize_title($attachment->title) . "\n";
        }

        return $text;
    }

    public function erasePersonalData($user_email, $page = 1)
    {
        $customer = Customer::where('email', $user_email)->select(['id'])->first();
        $tickets = Ticket::where('customer_id', $customer->id)->get();
        foreach ($tickets as $ticket) {
            $ticket->deleteTicket();
        }

        $customer->delete();
        return [
            'items_removed' => true,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }

     public function wpRegisterEraser( $erasers )
     {
         $erasers['fluent-support'] = array(
             'eraser_friendly_name' => __( 'Fluent Support Personal Data Eraser', 'fluent-support' ),
             'callback'             => [ $this, 'erasePersonalData' ],
         );
         return $erasers;
     }
}
