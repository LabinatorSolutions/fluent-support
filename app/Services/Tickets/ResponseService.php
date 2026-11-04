<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Notification;
use FluentSupport\App\Modules\Notifications\NotificationService;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\UploadService;
use FluentSupport\App\Services\Storage\StorageManager;
use FluentSupport\Framework\Support\Arr;

class ResponseService
{
    /**
     * Add an agent or customer response to a ticket.
     *
     * @param array $data allow_empty_content: an emailed reply may carry only attachments.
     * @param \FluentSupport\App\Models\Person $person
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param bool $silently Skip the assignment/response/close hooks.
     * @return array|false False for empty content.
     */
    public function createResponse($data, $person, $ticket, $silently = false)
    {
        if (empty($data['content']) && empty($data['allow_empty_content'])) {
            return false;
        }

        $cc_emails = Arr::get($data, 'cc_emails', []);

        $informationalReply = Arr::get($data, 'informational_reply', false);
        if ($person->person_type == 'agent') {
            $data['content'] = apply_filters('fluent_support/parse_smartcode_data', $data['content'], [
                'customer' => $ticket->customer,
                'agent'    => $person,
                'ticket'   => $ticket
            ]);
        }

        $convoType = Arr::get($data, 'conversation_type', 'response');

        // Input is already unslashed (or was never slashed, for API/MCP callers).
        // Don't unslash again or literal backslashes are lost.
        $content = wp_kses_post(Helper::decodeNonMarkupEntities($data['content']));
        $resetWaitingSince = apply_filters('fluent_support/reset_waiting_since', true, $content);
        $content = apply_filters('fluent_support/response_content_before_use_anywhere', $content);
        $response = [
            'person_id'         => $person->id,
            'ticket_id'         => $ticket->id,
            'conversation_type' => $convoType,
            'content'           => $content,
            'source'            => Arr::get($data, 'source', 'web'),
            'content_hash'      => md5($content)
        ];

        if (!empty($data['message_id'])) {
            // An email Message-ID ("<id@host>"): hide the brackets from the tag stripper.
            $response['message_id'] = str_replace(['[', ']'], ['<', '>'], sanitize_textarea_field(str_replace(['<', '>'], ['[', ']'], $data['message_id'])));
        }

        $response = apply_filters('fluent_support/new_' . $person->person_type . '_' . $convoType, $response, $ticket, $person);

        $createdResponse = \FluentSupport\App\Models\Conversation::create($response);

        if (!empty($cc_emails)) {
            $createdResponse->updateSettingsValue('cc_email', $cc_emails);

            // Keep a running list of every CC'd address on the ticket.
            $existingCcEmails = $ticket->getSettingsValue('all_cc_email', []);
            $newData = array_merge($existingCcEmails, $cc_emails);
            $ticket->updateSettingsValue('all_cc_email', array_unique($newData));
        }

        $createdResponse->load('person');

        $previousAgentId = (int) $ticket->agent_id;
        $statusBefore = $ticket->status;
        $agentAdded = $this->applyToTicket($ticket, $person, $convoType, $resetWaitingSince && !$informationalReply);
        $updateData = $agentAdded ? ['agent_id' => $person->id] : [];

        // The status it closed from, for ticket_status_changed.
        $closed = false;
        if (Arr::get($data, 'close_ticket') == 'yes' && $ticket->status != 'closed') {
            // A reply can reopen a closed ticket before closing it again.
            $closed = $statusBefore === 'closed' ? $ticket->status : $statusBefore;
            (new TicketService())->applyClose($ticket, $person);
        }

        $ticket->save();

        if ($closed) {
            (new TicketService())->logClose($ticket, $person);
        }

        Helper::tempImageMoveUploadDir($ticket->id, 'conversation', $createdResponse->id, $person->id);

        $this->attachUploads($ticket, $createdResponse, Arr::get($data, 'attachments', []));

        if ($silently) {
            // The caller fires the hooks itself (fireResponseHooks()), e.g. after
            // its transaction commits; 'closed' / 'agent_added' tell it what happened.
            return [
                'response'          => $createdResponse,
                'ticket'            => $ticket,
                'update_data'       => $updateData,
                'closed'            => $closed,
                'agent_added'       => $agentAdded,
                'previous_agent_id' => $previousAgentId,
            ];
        }

        $updateData = $this->fireResponseHooks($createdResponse, $ticket, $person, $closed, $agentAdded, $previousAgentId, $updateData);

        return [
            'response'    => $createdResponse,
            'ticket'      => $ticket,
            'update_data' => $updateData
        ];
    }

    /**
     * Fire the hooks createResponse() fires after a response is stored.
     *
     * Split out so a caller that wrapped createResponse(..., $silently = true) in
     * a transaction can fire them once the rows are committed: the customer
     * email must not leave for a reply that is about to be rolled back.
     *
     * @param Conversation $createdResponse
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @param string|false $closed The status the response closed the ticket from, or false.
     * @param bool $agentAdded The response assigned the ticket to $person.
     * @param int $previousAgentId
     * @param array $updateData
     * @return array $updateData, with 'agent' when the assignment hook fired.
     */
    public function fireResponseHooks($createdResponse, $ticket, $person, $closed, $agentAdded = false, $previousAgentId = 0, $updateData = [])
    {
        $convoType = $createdResponse->conversation_type;

        if ($agentAdded) {
            // The same activity line and hooks as any other assignment.
            (new TicketService())->afterAgentAssigned($ticket, Helper::getCurrentAgent() ?: $person, $previousAgentId);
            $updateData['agent'] = $ticket->agent;
        }

        /*
         * Fires after a response or note is added.
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
            (new TicketService())->fireCloseHooks($ticket, $person, is_string($closed) ? $closed : 'active');
        }

        return $updateData;
    }

    /**
     * Publish a draft reply: it becomes a sent response and the customer is notified.
     * Shared by the admin "Approve" action and MCP's publish-draft-reply.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Conversation $response The draft.
     * @param \FluentSupport\App\Models\Person $person The agent publishing it.
     * @param string $content Final content, already sanitized.
     * @return \FluentSupport\App\Models\Conversation
     */
    public function publishDraft($ticket, $response, $person, $content)
    {
        $response->content = $content;

        return $this->publishPendingConversation($ticket, $response, $person);
    }

    /**
     * Publish a held-back agent conversation (approved draft, due scheduled reply) with a normal reply's ticket bookkeeping.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param Conversation $response
     * @param \FluentSupport\App\Models\Person $person The agent publishing it.
     * @param bool $informational Informational replies do not reset the waiting clock
     * @return Conversation
     */
    public function publishPendingConversation($ticket, $response, $person, $informational = false)
    {
        $resetWaitingSince = apply_filters('fluent_support/reset_waiting_since', true, $response->content);

        $response->conversation_type = 'response';
        $response->created_at = current_time('mysql');
        $this->moveToNewId($response);

        $this->applyToTicket($ticket, $person, 'response', $resetWaitingSince && !$informational, false);
        $ticket->save();

        $author = $response->person_id ? Agent::find($response->person_id) : null;
        $sender = $author ?: $person;

        do_action('fluent_support/response_added_by_' . $sender->person_type, $response, $ticket, $sender);

        return $response;
    }

    /**
     * Saves new content for a reply, note or draft (the caller checks who may edit it).
     * Editing a customer's reply leaves an activity line, so the thread shows the
     * message was changed. The line never holds the old text.
     *
     * @param Conversation $response
     * @param string $content Already sanitized.
     * @param \FluentSupport\App\Models\Person $by
     * @return Conversation
     */
    public function update($response, $content, $by)
    {
        $isCustomerMessage = $response->person && $response->person->person_type !== 'agent';

        $response->content = $content;
        if ($isCustomerMessage) {
            // The old hash would still fingerprint the removed text.
            $response->content_hash = md5($content);
        }
        $response->save();

        if ($isCustomerMessage) {
            self::addActivity($response->ticket_id, $by, __('edited a message from the customer', 'fluent-support'));
        }

        /*
         * Fires after a reply, note or draft is edited.
         * @param Conversation $response
         * @param \FluentSupport\App\Models\Ticket $ticket
         * @param \FluentSupport\App\Models\Person $by
         */
        // Loaded apart from $response, so the caller's response keeps its shape.
        do_action('fluent_support/response_updated', $response, Ticket::find($response->ticket_id), $by);

        return $response;
    }

    /**
     * Deletes a reply, note or draft and its CC info (the caller checks who may).
     *
     * @param Conversation $response
     * @param \FluentSupport\App\Models\Person $by
     * @return void
     */
    public function delete($response, $by)
    {
        $ticket = Ticket::find($response->ticket_id);
        $response->delete();
        $response->ccinfo()->delete();

        /*
         * Fires after a reply, note or draft is deleted.
         * @param Conversation $response The deleted row.
         * @param \FluentSupport\App\Models\Ticket $ticket
         * @param \FluentSupport\App\Models\Person $by
         */
        do_action('fluent_support/response_deleted', $response, $ticket, $by);
    }

    /**
     * An activity line in the thread ("closed this ticket", "assigned this ticket
     * to Tom"). The thread shows the person's name in front of $content.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @param string $content
     * @param array $extra Other columns (source, message_id, conversation_type).
     * @return Conversation
     */
    public static function addActivity($ticket, $person, $content, array $extra = [])
    {
        return Conversation::create(array_merge([
            'ticket_id'         => is_object($ticket) ? $ticket->id : (int) $ticket,
            'person_id'         => is_object($person) ? $person->id : (int) $person,
            'conversation_type' => 'internal_info',
            'content'           => $content,
        ], $extra));
    }

    /**
     * Saves a held-back conversation under a new id. Readers treat the highest id as the
     * newest message (last response, MCP since_response_id, the newer-replies check), so a
     * reply held since before the customer's last message must not keep its old id.
     * The id is changed on $response itself, so callers that still hold it see the new one.
     *
     * @param Conversation $response
     * @return void
     */
    private function moveToNewId($response)
    {
        $oldId = (int) $response->id;
        $row = $response->getAttributes();
        unset($row['id']);
        $row['updated_at'] = current_time('mysql');

        $newId = (int) Conversation::insertGetId($row);
        if (!$newId) {
            $response->save();
            return;
        }

        Attachment::where('conversation_id', $oldId)->update(['conversation_id' => $newId]);
        // The notification tables only exist once the feature has been switched on
        if (NotificationService::tablesExist()) {
            Notification::where('conversation_id', $oldId)->update(['conversation_id' => $newId]);
        }
        Meta::whereIn('object_type', ['response', 'conversation_meta'])
            ->where('object_id', $oldId)
            ->update(['object_id' => $newId]);

        // A query delete, not $model->delete(): the model's deleting event removes the attachments
        Conversation::where('id', $oldId)->delete();

        $row['id'] = $newId;
        $response->setRawAttributes($row, true);
    }

    /**
     * What a new reply or note changes on its ticket: status new -> active and the
     * first response time (an agent's first reply), whose turn it is, the reply
     * count, and the agent (an agent replying to an unassigned ticket takes it).
     * The caller saves the ticket.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @param string $convoType
     * @param bool $resetsWaiting An agent reply restarts the waiting clock (not an informational one).
     * @param bool $assign Let an agent's reply take an unassigned ticket.
     * @return bool Whether the reply assigned the ticket.
     */
    private function applyToTicket($ticket, $person, $convoType, $resetsWaiting, $assign = true)
    {
        $now = current_time('mysql');

        if ($person->person_type != 'agent') {
            if ($ticket->last_agent_response && strtotime($ticket->last_agent_response) > strtotime($ticket->last_customer_response)) {
                $ticket->waiting_since = $now;
            }
            $ticket->last_customer_response = $now;
        } elseif ($convoType == 'response') {
            if ($ticket->status == 'new') {
                $ticket->status = 'active';
                $ticket->first_response_time = $ticket->created_at ? strtotime($now) - strtotime($ticket->created_at) : 300;
            }
            if ($resetsWaiting) {
                $ticket->last_agent_response = $now;
                $ticket->waiting_since = $now;
            }
        }

        if ($convoType == 'response') {
            $ticket->response_count += 1;
        }

        if ($assign && $person->person_type == 'agent' && $convoType == 'response' && !$ticket->agent_id) {
            $ticket->agent_id = $person->id;
            return true;
        }

        return false;
    }

    /**
     * Finalize files uploaded ahead of a reply (status in-active) onto that conversation.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param Conversation $conversation
     * @param array $attachmentHashes
     * @return void
     */
    public function attachUploads($ticket, $conversation, $attachmentHashes)
    {
        if (!$attachmentHashes) {
            return;
        }

        $attachments = Attachment::where('ticket_id', $ticket->id)
            ->whereIn('file_hash', (array) $attachmentHashes)
            ->where('status', 'in-active')
            ->get();

        if ($attachments->isEmpty()) {
            return;
        }

        foreach ($attachments as $attachment) {
            StorageManager::finalize($attachment, $ticket->id);

            if ($attachment->driver == 'local') {
                $newFileInfo = UploadService::copyFileTicketFolder($attachment->file_path, $ticket->id);
                if ($newFileInfo && !empty($newFileInfo['file_path'])) {
                    $attachment->file_path = $newFileInfo['file_path'];
                    $attachment->full_url = $newFileInfo['url'];
                }
            }

            $attachment->ticket_id = $ticket->id;
            $attachment->person_id = $conversation->person_id;
            $attachment->conversation_id = $conversation->id;
            $attachment->status = 'active';
            $attachment->save();
        }

        $conversation->load('attachments');
    }

    /**
     * @deprecated 2.4.1 Replies no longer carry the signature. Email templates add it
     *             with {{agent.signature}}, so the template decides the sign-off.
     *
     * @param string $content
     * @param object $person
     * @return string
     */
    public function maybeAppendSignature($content, $person)
    {
        $signature = self::getAppendedSignature($person);

        if ($signature === '') {
            return $content;
        }

        return $content . '<div class="fs_agent_signature">' . $signature . '</div>';
    }

    /**
     * The signature appended to this agent's replies, or '' when none is.
     *
     * @param \FluentSupport\App\Models\Person $person
     * @return string HTML
     */
    public static function getAppendedSignature($person)
    {
        if (!$person || $person->getMeta('agent_signature_enabled', 'no') !== 'yes') {
            return '';
        }

        $signature = (string) $person->getMeta('agent_signature', '');
        $hasText = !empty(trim(strip_tags($signature)));
        $hasImage = (bool) preg_match('/<img[\s>]/i', $signature);

        return ($hasText || $hasImage) ? $signature : '';
    }
}
