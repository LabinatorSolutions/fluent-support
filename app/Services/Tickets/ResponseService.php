<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Response\Response;
use FluentSupport\Framework\Support\Arr;

class ResponseService
{
    /**
     * createResponse method is responsible for create responses to ticket by agent or customer
     * @param $data
     * @param $person
     * @param $ticket
     * @return array|false
     */
    public function createResponse($data, $person, $ticket)
    {
        if (empty($data['content'])) {
            return false;
        }

        $convoType = Arr::get($data, 'conversation_type', 'response');

        $content = wp_unslash(wp_kses_post($data['content']));

        $response = [
            'person_id'         => $person->id,
            'ticket_id'         => $ticket->id,
            'conversation_type' => $convoType,
            'content'           => $content,
            'source'            => Arr::get($data, 'source', 'web'),
            'content_hash'      => md5($content)
        ];

        if(!empty($data['message_id'])) {
            $response['message_id'] = sanitize_text_field($data['message_id']);
        }

        $response = apply_filters('fluent_support/new_' . $person->person_type . '_' . $convoType, $response, $ticket, $person);

        $createdResponse = \FluentSupport\App\Models\Conversation::create($response);
        $createdResponse->load('person');

        if ($attachments = Arr::get($data, 'attachments', [])) {
            Attachment::where('ticket_id', $ticket->id)
                ->whereIn('file_hash', $attachments)
                ->update([
                    'conversation_id' => $createdResponse->id,
                    'status'          => 'active'
                ]);
            $createdResponse->load('attachments');
        }

        if ($person->person_type == 'agent' && $ticket->status == 'new' && $convoType == 'response') {
            $ticket->status = 'active';
            if ($ticket->created_at) {
                $ticket->first_response_time = strtotime(current_time('mysql')) - strtotime($ticket->created_at);
            } else {
                $ticket->first_response_time = 300;
            }
        }

        $agentAdded = false;
        $updateData = [];

        if ($person->person_type == 'agent') {
            if (!$ticket->agent_id && $convoType == 'response') {
                $ticket->agent_id = $person->id;
                $agentAdded = true;
                $updateData = [
                    'agent_id' => $person->id
                ];
            }

            if ($convoType == 'response') {
                $ticket->last_agent_response = current_time('mysql');
                $ticket->waiting_since = current_time('mysql');
            }
        } else {

            if ($ticket->last_agent_response && strtotime($ticket->last_agent_response) > strtotime($ticket->last_customer_response)) {
                $ticket->waiting_since = current_time('mysql');
            }

            $ticket->last_customer_response = current_time('mysql');
        }

        $ticket->response_count += 1;

        $closed = false;
        if (Arr::get($data, 'close_ticket') == 'yes' && $ticket->status != 'closed') {
            $ticket->status = 'closed';
            $ticket->resolved_at = current_time('mysql');
            $ticket->closed_by = $person->id;
            $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
            $closed = true;
            Conversation::create([
                'ticket_id'         => $ticket->id,
                'person_id'         => $person->id,
                'conversation_type' => 'internal_info',
                'content'           => __('Ticket has been closed', 'fluent-support')
            ]);
        }

        $ticket->save();

        if ($agentAdded) {
            $assigner = Helper::getCurrentAgent();
            $ticket->load('agent');
            do_action('fluent_support/agent_assigned_to_ticket', $person, $ticket, $assigner);
            $updateData['agent'] = $ticket->agent;
        }

        do_action('fluent_support/' . $convoType . '_added_by_' . $person->person_type, $createdResponse, $ticket, $person);


        if ($closed) {
            do_action('fluent_support/ticket_closed', $ticket, $person);
            do_action('fluent_support/ticket_closed_by_' . $person->person_type, $ticket, $person);
        }

        return [
            'response'    => $createdResponse,
            'ticket'      => $ticket,
            'update_data' => $updateData
        ];
    }
}
