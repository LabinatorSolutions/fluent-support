<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Requests\TicketResponseRequest;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketFormatter;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;

/**
 * A ticket's thread for agents: replies, notes and approval drafts, edits and
 * deletes, the reply box's autosave draft, and bulk reply. Every write goes
 * through ResponseService; the person fields sent are TicketFormatter's.
 */
class ResponseController extends Controller
{
    /**
     * @param TicketResponseRequest $request
     * @param int $ticket_id
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function createResponse(TicketResponseRequest $request, $ticket_id)
    {
        $data = $request->sanitize();

        try {
            $convoType = Arr::get($data, 'conversation_type', 'response');
            $isDraft = $convoType === 'draft_response';

            // close_ticket always needs manage rights, even on an otherwise-permitted draft save.
            if (!$isDraft || Arr::get($data, 'close_ticket') == 'yes') {
                $this->ensureCanManageTickets();
            }

            $agent = Helper::getAgentByUserId();

            if (!$agent) {
                return $this->sendError([
                    'message' => __('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support')
                ]);
            }

            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            if ($convoType === 'response' && $request->getSafe('force_send', 'sanitize_text_field') !== 'yes') {
                $lastSeenId = $request->get('last_conversation_id');
                $newerResponses = $lastSeenId === null || $lastSeenId === '' ? [] : $this->responsesAddedSince($ticket, (int) $lastSeenId, $agent);
                if ($newerResponses) {
                    return $this->sendError([
                        'code'          => 'fs_newer_responses',
                        'message'       => __('Someone replied to this ticket while you were writing.', 'fluent-support'),
                        'new_responses' => $newerResponses
                    ], 409);
                }
            }

            $scheduledAt = (int) Arr::get($data, 'scheduled_at');

            if ($convoType === 'schedule') {
                throw new \Exception(esc_html__('Use scheduled_at to schedule a reply.', 'fluent-support'));
            }

            if ($scheduledAt) {
                if ($convoType !== 'response') {
                    throw new \Exception(esc_html__('Only replies can be scheduled.', 'fluent-support'));
                }

                unset($data['scheduled_at']);

                /**
                 * Schedule the reply instead of sending it. Fluent Support Pro answers this.
                 *
                 * @param array|null $responseData Null until a handler schedules the reply
                 * @param array $data The reply payload
                 * @param \FluentSupport\App\Models\Agent $agent
                 * @param Ticket $ticket
                 * @param int $scheduledAt Unix timestamp to send at
                 */
                $responseData = apply_filters('fluent_support/schedule_response', null, $data, $agent, $ticket, $scheduledAt);

                if (!$responseData) {
                    throw new \Exception(esc_html__('Scheduling replies needs Fluent Support Pro.', 'fluent-support'));
                }
            } else {
                $responseData = (new ResponseService())->createResponse($data, $agent, $ticket);
                // Content that sanitizes to nothing (an empty tag) is no reply.
                if (!$responseData) {
                    throw ApiException::invalid(__('Please write a reply.', 'fluent-support'));
                }
            }

            $responseData['response']->content = Helper::refreshSignedAttachmentUrls($responseData['response']->content, $ticket->id);
            $responseData['response']->load([
                'attachments' => function ($q) {
                    $q->where('status', 'active');
                }
            ]);
            $responseData['response']->content = wpautop($responseData['response']->content, false);
            TicketFormatter::person($responseData['response']->person);
            TicketFormatter::closedBy($responseData['ticket']);

            return [
                'message'     => $scheduledAt ? __('Reply has been scheduled', 'fluent-support') : __('Response has been added', 'fluent-support'),
                'response'    => $responseData['response'],
                'ticket'      => $responseData['ticket'],
                'update_data' => $responseData['update_data']
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param TicketResponseRequest $request
     * @param int $ticket_id
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function createOrUpdatDraft(TicketResponseRequest $request, $ticket_id)
    {
        $data = $request->sanitize();

        try {
            $agent = Helper::getAgentByUserId();

            if (!$agent) {
                return $this->sendError([
                    'message' => __('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support')
                ]);
            }

            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $key = 'ticket_no_' . $ticket_id . '_agent_id_' . $agent->id . '_response_draft';
            $previousDraft = Meta::where('key', $key)->first();

            // Update only a draft that exists: a stale draftID (discarded, or from another ticket) would
            // otherwise update nothing and the draft would never be saved.
            if ($previousDraft) {
                Meta::where('key', $key)->update([
                    'value' => maybe_serialize($data)
                ]);

                return [
                    'message' => __('Draft has been updated', 'fluent-support'),
                    // Return the stored ID so a box that lost it (remounted before its first save returned) gets it back.
                    'draftID' => $previousDraft->id
                ];
            }

            $draftID = Meta::insertGetId([
                'object_type' => '_fs_auto_draft',
                'object_id'   => $ticket_id,
                'key'         => $key,
                'value'       => maybe_serialize($data)
            ]);

            return [
                'message' => __('Draft has been added', 'fluent-support'),
                'draftID' => $draftID
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function getDraft($ticket_id)
    {
        try {
            $agent = Helper::getAgentByUserId();

            if (!$agent) {
                return $this->sendError([
                    'message' => __('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support')
                ]);
            }

            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $key = 'ticket_no_' . $ticket_id . '_agent_id_' . $agent->id . '_response_draft';

            $draft = Meta::where([
                'object_type' => '_fs_auto_draft',
                'key'         => $key,
            ])->first();

            if ($draft) {
                $draft->value = Helper::safeUnserialize($draft->value);
            }

            return [
                'draft' => $draft
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function deleteDraft($draft_id)
    {
        $draft_id = intval($draft_id);

        try {
            $agent = Helper::getAgentByUserId();

            if (!$agent) {
                return $this->sendError([
                    'message' => __('You do not have permission to perform this action', 'fluent-support'),
                ]);
            }

            $draft = Meta::where('id', $draft_id)
                ->where('object_type', '_fs_auto_draft')
                ->first();

            if (!$draft) {
                return $this->sendError([
                    'message' => __('Draft not found', 'fluent-support'),
                ]);
            }

            // Managers can delete other agents' drafts, but only on tickets they can access.
            $ticket = Ticket::findOrFail($draft->object_id);

            $this->ensureCanAccessTicket($ticket);

            // The draft key embeds the owning agent's id.
            $isOwnDraft = strpos($draft->key, '_agent_id_' . $agent->id . '_') !== false;

            if (!$isOwnDraft && !PermissionManager::canManageTickets()) {
                return $this->sendError([
                    'message' => __('You do not have permission to delete this draft', 'fluent-support'),
                ]);
            }

            $draft->delete();

            return [
                'message' => __('Discard draft successfully', 'fluent-support'),
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function doBulkReplies(Request $request)
    {
        try {
            $requestData = $request->all();
            $data = [];
            foreach ($requestData as $key => $value) {
                if ($key === 'content') {
                    // HTML: sanitize_text_field would strip the formatting and links.
                    $data[$key] = is_string($value) ? wp_kses_post(Helper::decodeNonMarkupEntities($value)) : '';
                } elseif (is_array($value)) {
                    $data[$key] = $key === 'ticket_ids' ? array_map('intval', $value) : map_deep($value, 'sanitize_text_field');
                } else {
                    $data[$key] = sanitize_text_field($value);
                }
            }

            $this->validate($data, [
                'content'    => 'required',
                'ticket_ids' => 'required|array'
            ]);

            $agent = Helper::getAgentByUserId();
            $ticketIds = array_filter($data['ticket_ids'], 'absint');

            $query = Ticket::whereIn('id', $ticketIds)->where('status', '!=', 'closed');

            (new AgentTicketAccess())->applyAccessScope($query, $agent);

            $tickets = $query->get();

            if ($tickets->isEmpty()) {
                throw new \Exception(esc_html__('Sorry no tickets found based on your filter and bulk actions', 'fluent-support'));
            }

            $responseData = [
                'content'           => wp_kses_post(Helper::decodeNonMarkupEntities(Arr::get($data, 'content', ''))),
                'conversation_type' => 'response',
                'close_ticket'      => Arr::get($data, 'close_ticket'),
            ];

            $attachmentHashes = Arr::get($data, 'attachments', []);
            $attachments = false;
            if ($attachmentHashes) {
                // Only this agent's pending uploads (not rows waiting for the delete job).
                $attachments = Attachment::whereNull('ticket_id')
                    ->where('status', 'in-active')
                    ->where('person_id', $agent->id)
                    ->orderBy('id', 'asc')
                    ->whereIn('file_hash', $attachmentHashes)
                    ->get();
            }

            $responseService = new ResponseService();

            foreach ($tickets as $ticket) {
                if ($attachments) {
                    $responseData['attachments'] = [];
                    $attachmentRecords = [];
                    foreach ($attachments as $attachment) {
                        $fileHash = bin2hex(random_bytes(16));
                        // Raw insert: settings (local_temp_path, remote keys) must be serialized here.
                        $attachmentRecords[] = [
                            'ticket_id'  => $ticket->id,
                            'person_id'  => $attachment->person_id,
                            'file_type'  => $attachment->file_type,
                            'file_path'  => $attachment->file_path,
                            'full_url'   => $attachment->full_url,
                            'title'      => $attachment->title,
                            'driver'     => $attachment->driver,
                            'file_size'  => $attachment->file_size,
                            'status'     => $attachment->status,
                            'file_hash'  => $fileHash,
                            'settings'   => maybe_serialize($attachment->settings),
                        ];
                        $responseData['attachments'][] = $fileHash;
                    }
                    if ($attachmentRecords) {
                        Attachment::insert($attachmentRecords);
                    }
                }

                $responseService->createResponse($responseData, $agent, $ticket);
            }

            return [
                'message' => __('Response has been added to the selected tickets', 'fluent-support')
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param int $ticket_id
     * @param int $response_id
     * @return array
     */
    public function deleteResponse($ticket_id, $response_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            if (in_array($ticket->mailbox_id, PermissionManager::getRestrictedMailboxIds())) {
                throw new \Exception(esc_html__('Ticket cannot be fetched due to restricted mailbox', 'fluent-support'), 403);
            }

            $this->ensureCanAccessTicket($ticket);

            // Being assigned to the ticket isn't enough to delete a response.
            if (!PermissionManager::currentUserCan('fst_delete_tickets')) {
                throw new \Exception(
                    esc_html__('Sorry, you do not have permission to delete this response.', 'fluent-support')
                );
            }

            $response = Conversation::where('id', $response_id)
                ->where('ticket_id', $ticket_id)
                ->where('conversation_type', '!=', 'schedule')
                ->firstOrFail();

            (new ResponseService())->delete($response, Helper::getAgentByUserId());

            return [
                'message' => __('Selected response has been deleted', 'fluent-support')
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param TicketResponseRequest $request
     * @param int $ticket_id
     * @param int $response_id
     * @return array
     * @throws \Exception
     */
    public function updateResponse(TicketResponseRequest $request, $ticket_id, $response_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            if (in_array($ticket->mailbox_id, PermissionManager::getRestrictedMailboxIds())) {
                throw new \Exception(esc_html__('Ticket cannot be fetched due to restricted mailbox', 'fluent-support'), 403);
            }

            $this->ensureCanAccessTicket($ticket);

            $response = Conversation::where('id', $response_id)
                ->where('ticket_id', $ticket_id)
                ->with('person')
                ->firstOrFail();
            $agent = Helper::getAgentByUserId();

            $editableTypes = ['response', 'draft_response', 'note', 'internal_info'];
            // System activity ("Ticket has been closed") is a record, not text to reword. Older MCP
            // versions saved notes as internal_info with source 'mcp'; those stay editable.
            $isSystemActivity = $response->conversation_type === 'internal_info' && $response->source !== 'mcp';
            if (!in_array($response->conversation_type, $editableTypes, true) || $isSystemActivity) {
                throw new \Exception(
                    esc_html__('This response type cannot be edited.', 'fluent-support')
                );
            }

            // Customer replies also use the 'response' type. Only a site admin may edit
            // one, to remove sensitive data the customer sent (passwords, card numbers).
            $isCustomerMessage = $response->person && $response->person->person_type !== 'agent';
            if ($isCustomerMessage && !current_user_can('manage_options')) {
                throw new \Exception(
                    esc_html__('Sorry, you do not have permission to update this response.', 'fluent-support')
                );
            }

            $isDraft = $response->conversation_type == 'draft_response';
            $isAuthor = (int) $response->person_id === (int) $agent->id;
            $canApproveDraft = PermissionManager::currentUserCan('fst_approve_draft_reply');

            if ($isDraft && !$isAuthor) {
                if (!$canApproveDraft) {
                    throw new \Exception(
                        esc_html__('Sorry, You do not have permission to approve this draft response', 'fluent-support')
                    );
                }
            } elseif (!$isAuthor && !PermissionManager::currentUserCan('fst_manage_other_tickets')) {
                throw new \Exception(
                    esc_html__('Sorry, you do not have permission to update this response.', 'fluent-support')
                );
            }

            // Input is already unslashed. Unslashing again strips real backslashes.
            $content = wp_kses_post(Helper::decodeNonMarkupEntities($request->get('content')));

            if ($isDraft && !$isAuthor && $canApproveDraft) {
                $response = $this->approveDraftConversation($ticket, $response, $agent, $content);
            } else {
                $response = (new ResponseService())->update($response, $content, $agent);
            }

            if ($response->relationLoaded('person')) {
                TicketFormatter::person($response->person);
            }

            return [
                'message'  => __('Selected response has been updated', 'fluent-support'),
                'response' => $response
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function approveDraftResponse(TicketResponseRequest $request, $ticket_id, $response_id)
    {
        try {
            if (!PermissionManager::currentUserCan('fst_approve_draft_reply')) {
                throw new \Exception(
                    esc_html__('You do not have permission to approve draft responses.', 'fluent-support')
                );
            }

            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $response = Conversation::where('id', $response_id)
                ->where('ticket_id', $ticket_id)
                ->where('conversation_type', 'draft_response')
                ->firstOrFail();

            $person = Helper::getAgentByUserId();

            $response = $this->approveDraftConversation(
                $ticket,
                $response,
                $person,
                wp_kses_post(Helper::decodeNonMarkupEntities($request->get('content')))
            );

            if ($response->relationLoaded('person')) {
                TicketFormatter::person($response->person);
            }

            return [
                'message'  => __('Draft response has been successfully approved.', 'fluent-support'),
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    protected function approveDraftConversation($ticket, $response, $person, $content)
    {
        return (new ResponseService())->publishDraft($ticket, $response, $person, $content);
    }

    /**
     * Replies by anyone else (the customer or another agent) added after the
     * conversation the agent last saw. The admin reply box sends that id so a
     * reply written against an outdated thread is held back; callers that send
     * no id (MCP, API, bulk reply) are never checked.
     *
     * @param Ticket $ticket
     * @param int $lastSeenId
     * @param \FluentSupport\App\Models\Agent $agent
     * @return array
     */
    protected function responsesAddedSince(Ticket $ticket, $lastSeenId, $agent)
    {
        if ($lastSeenId < 0) {
            return [];
        }

        $newer = Conversation::where('ticket_id', $ticket->id)
            ->where('id', '>', $lastSeenId)
            ->where('conversation_type', 'response')
            ->where('person_id', '!=', $agent->id)
            ->with(['person' => function ($query) {
                $query->select(['id', 'first_name', 'last_name', 'person_type']);
            }])
            ->orderBy('id', 'ASC')
            ->limit(10)
            ->get();

        return $newer->map(function ($conversation) {
            $person = $conversation->person;
            return [
                'id'          => (int) $conversation->id,
                'person_name' => $person ? trim($person->first_name . ' ' . $person->last_name) : '',
                'person_type' => $person ? $person->person_type : '',
                'created_at'  => (string) $conversation->created_at,
            ];
        })->all();
    }
}
