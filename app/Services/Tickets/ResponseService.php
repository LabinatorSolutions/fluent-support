<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
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

        $mentionedAgent = $this->get_mentioned_agent($content);

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

        /*
         * If support staff mentioned
         * */
        if(!empty($mentionedAgent)) {
            $agentSerialize = maybe_serialize($mentionedAgent);

            $_data = Meta::where('object_type', 'ticket_meta')
                ->where('key', '_mentioned_agent_to_ticket')
                ->where('object_id', $ticket->id)
                ->first();

            if ($_data) {
                $_data->value = $agentSerialize;
                $_data->save();
            } else {
                $_mentionedData = [
                    'object_type' => 'ticket_meta',
                    'object_id' => $ticket->id,
                    'key' => '_mentioned_agent_to_ticket',
                    'value' => $agentSerialize
                ];

                Meta::create($_mentionedData);
            }
        }

        if ($agentAdded) {
            $assigner = Helper::getCurrentAgent();
            $ticket->load('agent');

            /*
             * Action on ticket assign to an agent
             *
             * @since v1.0.0
             * @param object $person
             * @param object $ticket
             * @param object $assigner
             */
            do_action('fluent_support/agent_assigned_to_ticket', $person, $ticket, $assigner);
            $updateData['agent'] = $ticket->agent;
        }

        /*
         * Action on conversation
         *
         * @since v1.0.0
         * @param string $convoType
         * @param string $personType
         * @param object $createdResponse
         * @param object $ticket
         * @param object $person
         */
        do_action('fluent_support/' . $convoType . '_added_by_' . $person->person_type, $createdResponse, $ticket, $person);


        if ($closed) {
            /*
             * Action on ticket close
             *
             * @since v1.0.0
             * @param object $ticket
             * @param object $person
             */
            do_action('fluent_support/ticket_closed', $ticket, $person);

            /*
             * Action on ticket close
             *
             * @since v1.0.0
             * @param string $personType
             * @param object $ticket
             * @param object $person
             */
            do_action('fluent_support/ticket_closed_by_' . $person->person_type, $ticket, $person);
        }

        return [
            'response'    => $createdResponse,
            'ticket'      => $ticket,
            'update_data' => $updateData
        ];
    }

    public function get_mentioned_agent($content){
        $mentionedAgent = [];
        $tempArr = explode("\"#", $content);

        if(is_array($tempArr) && !empty($tempArr)){
            foreach ($tempArr as $arr){
                $newArr = explode("\"", $arr);

                if(isset($newArr[0]) && is_numeric($newArr[0])){
                    $mentionedAgent[] = $newArr[0];
                }
            }
        }

        return $mentionedAgent;
    }
}
