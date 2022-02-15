<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

class PrivacyHandler
{
    public function init()
    {
        add_filter( 'wp_privacy_personal_data_exporters', [$this, 'wpRegisterExporter'] );
    }
    public function exportPersonalData($user_email, $page = 1)
    {
        $customer = Customer::where('email', $user_email)->select(['id'])->first();

//        $responseWith = $request->get('response_with', ['person', 'attachments']);
        $tickets = Ticket::with(['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments'])->where('customer_id', $customer->id)->get();
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
                        'value' => $ticket->title,
                    ],
                    [
                        'name' => 'Ticket Content',
                        'value' => $ticket->content,
                    ],
                    [
                        'name' => 'Ticket Attachments',
                        'value' => $ticket->attachments,
                    ],
                    [
                        'name' => 'Ticket Product',
                        'value' => $ticket->product,
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
    public function wpRegisterExporter( $exporters ) {
        $exporters['fluent-support'] = array(
            'exporter_friendly_name' => __( 'Fluent Support Personal Data Exporter', 'fluent-support' ),
            'callback'               => [ $this, 'exportPersonalData' ],
        );
        return $exporters;
    }
}
