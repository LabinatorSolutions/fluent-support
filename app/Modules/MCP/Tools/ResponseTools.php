<?php

namespace FluentSupport\App\Modules\MCP\Tools;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\SavedReply;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\MCP\Support\AbilityGuard;
use FluentSupport\App\Modules\MCP\Support\AttachmentStager;
use FluentSupport\App\Modules\MCP\Support\TicketAccessGuard;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

class ResponseTools
{
    /** Seconds within which an identical MCP reply is treated as a retry, not a new reply. */
    const DUPLICATE_WINDOW = 600;

    public static function listSavedReplies($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', 'No agent found for current user');
        }

        $query = SavedReply::where('created_by', $agent->id);

        if (!empty($params['search'])) {
            $search = sanitize_text_field($params['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($params['product_id'])) {
            $query->where('product_id', (int) $params['product_id']);
        }

        $replies = $query->orderBy('title', 'ASC')->get();

        $count = $replies->count();

        return MCPHelper::envelope(
            sprintf((int) $count === 1 ? 'Found %d saved reply' : 'Found %d saved replies', $count),
            ['saved_replies' => $replies->map(function ($reply) {
                return self::formatSavedReply($reply);
            })->toArray()],
            ['total' => $count]
        );
    }

    private static function formatSavedReply($reply)
    {
        return [
            'id'         => $reply->id,
            'title'      => $reply->title,
            'content'    => MCPHelper::htmlToMarkdown($reply->content),
            'product_id' => $reply->product_id,
        ];
    }

    public static function replyToTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', 'No agent found for current user');
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', 'ticket_id is required', ['fields' => ['ticket_id']]);
        }

        $content = '';

        if (!empty($params['saved_reply_id'])) {
            $savedReply = SavedReply::where('id', (int) $params['saved_reply_id'])
                ->where('created_by', $agent->id)
                ->first();
            if (!$savedReply) {
                return MCPHelper::error('not_found', 'Saved reply not found', ['next_step' => 'Use list-saved-replies to find valid IDs']);
            }
            $content = wp_kses_post(Helper::decodeNonMarkupEntities($savedReply->content));
        }

        if (!empty($params['content'])) {
            $format  = $params['content_format'] ?? 'markdown';
            $content = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['content'], $format)));
        }

        if (!$content) {
            return MCPHelper::error('invalid_param', 'content or saved_reply_id is required', ['fields' => ['content', 'saved_reply_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', 'Ticket not found', ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        // Draft-only agents (fst_draft_reply without manage permissions) can
        // only save drafts, the same as in the ticket view.
        $draftOnly = !PermissionManager::userCan(AbilityGuard::MANAGE_TICKET_CAPS);
        $isDraft   = $draftOnly || !empty($params['draft']);

        // The flag only applies while the reply is sent; a draft is sent later.
        if ($isDraft && !empty($params['informational'])) {
            return MCPHelper::error(
                'invalid_param',
                'A draft reply cannot be informational',
                ['fields' => ['informational', 'draft'], 'next_step' => 'Send the reply without draft, or drop informational']
            );
        }

        // Clients retry when a response is lost, and a retry must not email the
        // customer twice. Same agent, ticket and request within the window = retry.
        $requestHash = self::requestHash($ticket, $content, $params, $isDraft);
        if ($duplicate = self::findDuplicateReply($ticket, $agent, $requestHash)) {
            return MCPHelper::envelope(
                sprintf('This reply was already added to ticket #%d a moment ago, so it was not sent again.', $ticket->id),
                [
                    'response' => [
                        'id'         => $duplicate->id,
                        'content'    => MCPHelper::htmlToMarkdown($duplicate->content),
                        'type'       => $duplicate->conversation_type,
                        'created_at' => MCPHelper::toIso8601($duplicate->created_at),
                    ],
                    'ticket_status' => $ticket->status,
                    'duplicate'     => true,
                ]
            );
        }

        $attachments = AttachmentStager::stage($params['attachments'] ?? null, $ticket, $agent);
        if (is_wp_error($attachments)) {
            return $attachments;
        }

        if ($isDraft) {
            return self::saveDraftReply($ticket, $agent, $content, $params, $draftOnly, $requestHash, $attachments);
        }

        // Replying to a closed ticket reopens it, unless close_ticket is set:
        // then the reply goes out and the ticket simply stays closed.
        $reopen = $ticket->status === 'closed' && empty($params['close_ticket']);

        // Resolve assignment intent so assign + reply happen in one call.
        // Explicit assignee_id requires fst_assign_agents (resolveAssignmentTarget).
        // Otherwise, replying to an unassigned ticket takes ownership (self),
        // which needs no extra permission. A ticket already assigned to someone
        // else is never silently reassigned.
        $assignTarget = null;
        if (array_key_exists('assignee_id', $params) && $params['assignee_id'] !== null && $params['assignee_id'] !== '') {
            $assignTarget = MCPHelper::resolveAssignmentTarget($params['assignee_id'], $ticket, 'assignee_id');
            if (is_wp_error($assignTarget)) {
                return $assignTarget;
            }
        } elseif (empty($ticket->agent_id)) {
            $assignTarget = $agent; // taking ownership — no fst_assign_agents needed
        }

        $data = [
            'content'           => $content,
            'conversation_type' => 'response',
            'source'            => 'mcp',
        ];

        if (!empty($params['close_ticket'])) {
            $data['close_ticket'] = 'yes';
        }

        // A holding reply ("we've escalated this"): sent as usual, but the ticket
        // keeps waiting on an agent (waiting_on stays "agent").
        if (!empty($params['informational'])) {
            $data['informational_reply'] = true;
        }

        if ($attachments) {
            $data['attachments'] = $attachments;
        }

        // Assign + reply (+ optional close) must be atomic: a failure in the
        // reply rolls back the assignment too. Every notification side effect
        // (assignment, the reply email, close) is deferred until AFTER the
        // transaction commits — firing them inside would leak an email for a
        // reply that is then rolled back, and a retry of that reply would find
        // no request hash and email the customer again.
        $result          = null;
        $assigned        = false;
        $previousAgentId = $ticket->agent_id;
        try {
            (new Ticket())->getConnection()->transaction(function () use (&$result, &$assigned, $assignTarget, $ticket, $agent, $data, $reopen, $requestHash) {
                if ($reopen) {
                    (new TicketService())->reopen($ticket, $agent, true);
                }

                if ($assignTarget) {
                    $assigned = MCPHelper::applyAgentAssignment($ticket, $assignTarget, $agent, true);
                }

                $result = (new ResponseService())->createResponse($data, $agent, $ticket, true);

                if (!$result) {
                    throw new \Exception('reply_failed'); // roll back the reopen and assignment
                }

                $result['response']->updateSettingsValue('mcp_request_hash', $requestHash);
            });
        } catch (\Throwable $e) {
            return MCPHelper::error('failed', 'Failed to create response', ['retryable' => true]);
        }

        // Reply, reopen and assignment committed. Now fire their side effects,
        // in the order the ticket view produces them: reopened, assigned, replied, closed.
        if ($reopen) {
            (new TicketService())->fireReopenHooks($ticket, $agent);
        }

        if ($assigned) {
            $ticket->load('agent');
            MCPHelper::fireAgentAssignmentSideEffects($ticket, $agent, $previousAgentId);
        }

        (new ResponseService())->fireResponseHooks($result['response'], $result['ticket'], $agent, $result['closed']);

        $ticketStatus = $result['ticket']->status;

        $isClosed = $ticketStatus === 'closed';

        if (!$assigned) {
            $summary = $isClosed
                ? sprintf('Reply sent and ticket #%d closed', $ticketId)
                : sprintf('Reply added to ticket #%d', $ticketId);
        } elseif ((int) $assignTarget->id === (int) $agent->id) {
            $summary = $isClosed
                ? sprintf('Reply sent, ticket #%d closed and assigned to you', $ticketId)
                : sprintf('Reply added to ticket #%d and assigned to you', $ticketId);
        } else {
            $assigneeName = MCPHelper::personName($assignTarget);
            $summary = $isClosed
                ? sprintf('Reply sent, ticket #%1$d closed and assigned to %2$s', $ticketId, $assigneeName)
                : sprintf('Reply added to ticket #%1$d and assigned to %2$s', $ticketId, $assigneeName);
        }

        if ($reopen) {
            $summary = sprintf('Ticket #%d was closed and has been reopened.', $ticketId) . ' ' . $summary;
        }

        $payload = [
            'response' => [
                'id'         => $result['response']->id,
                'content'    => MCPHelper::htmlToMarkdown($result['response']->content),
                'type'       => $result['response']->conversation_type,
                'created_at' => MCPHelper::toIso8601($result['response']->created_at),
            ],
            'ticket_status' => $ticketStatus,
            'reopened'      => $reopen,
        ];

        if (!empty($params['informational'])) {
            $payload['informational'] = true;
        }

        if ($attachments) {
            $payload['response']['attachments'] = self::attachmentTitles($result['response']);
        }

        if ($assigned) {
            $payload['assigned_to'] = [
                'id'   => (int) $assignTarget->id,
                'name' => MCPHelper::personName($assignTarget),
            ];
        }

        return MCPHelper::envelope($summary, $payload);
    }

    /**
     * Save an unsent reply for a human to review and approve in the ticket view.
     * Nothing reaches the customer, and status and assignee don't change.
     */
    private static function saveDraftReply(Ticket $ticket, $agent, $content, $params, $draftOnly, $requestHash, $attachments = [])
    {
        $conflicts = array_values(array_filter(['close_ticket', 'assignee_id'], function ($field) use ($params) {
            return !empty($params[$field]);
        }));

        if ($conflicts) {
            return MCPHelper::error(
                'invalid_param',
                $draftOnly
                    ? 'You can only save draft replies, which cannot close or assign the ticket'
                    : 'A draft reply cannot close or assign the ticket',
                ['fields' => $conflicts, 'next_step' => 'Remove close_ticket and assignee_id, or send the reply without draft']
            );
        }

        $draft = [
            'content'           => $content,
            'conversation_type' => 'draft_response',
            'source'            => 'mcp',
        ];

        if ($attachments) {
            $draft['attachments'] = $attachments;
        }

        $result = (new ResponseService())->createResponse($draft, $agent, $ticket);

        if (!$result) {
            return MCPHelper::error('failed', 'Failed to save draft reply', ['retryable' => true]);
        }

        $result['response']->updateSettingsValue('mcp_request_hash', $requestHash);

        return MCPHelper::envelope(
            $draftOnly
                ? sprintf('Draft reply saved on ticket #%d. You only have the Draft Reply permission, so your replies are saved as drafts until another agent approves them.', $ticket->id)
                : sprintf('Draft reply saved on ticket #%d. It is not sent until an agent approves it.', $ticket->id),
            [
                'response' => [
                    'id'         => $result['response']->id,
                    'content'    => MCPHelper::htmlToMarkdown($result['response']->content),
                    'type'       => 'draft_response',
                    'created_at' => MCPHelper::toIso8601($result['response']->created_at),
                ],
                'ticket_status' => $ticket->status,
                'draft'         => true,
                'draft_forced'  => $draftOnly,
            ]
        );
    }

    /**
     * @param \FluentSupport\App\Models\Conversation $response
     * @return array<int, array{id:int, title:string}>
     */
    private static function attachmentTitles($response)
    {
        $response->load('attachments');

        return $response->attachments->map(function ($attachment) {
            return ['id' => (int) $attachment->id, 'title' => (string) $attachment->title];
        })->values()->toArray();
    }

    /**
     * Who a note mentions, and whether they get notified: mentions only notify when
     * internal notifications are on, which the agent can't see otherwise. Null when
     * the note mentions nobody.
     *
     * @param string $content Note HTML.
     * @return array|null
     */
    private static function mentionReport($content)
    {
        $ids = \FluentSupport\App\Modules\Notifications\NotificationService::mentionedAgentIds($content);
        if (!$ids) {
            return null;
        }

        $agents = \FluentSupport\App\Models\Agent::whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $notifies = \FluentSupport\App\Modules\Notifications\NotificationService::isEnabled()
            && \FluentSupport\App\Modules\Notifications\NotificationService::isEventEnabled(\FluentSupport\App\Modules\Notifications\NotificationService::AGENT_MENTIONED);

        return [
            'agents'   => array_map(function ($id) use ($agents) {
                $agent = $agents->get($id);
                return ['agent_id' => (int) $id, 'name' => $agent ? MCPHelper::personName($agent) : null, 'found' => (bool) $agent];
            }, $ids),
            'notified' => $notifies,
            'note'     => $notifies
                ? 'Mentioned agents who can see this ticket get an in-app notification.'
                : 'Internal notifications are turned off on this site, so nobody is notified. Assign the ticket or ask a person to tell them.',
        ];
    }

    private static function requestHash(Ticket $ticket, $content, $params, $isDraft)
    {
        return md5(wp_json_encode([
            (int) $ticket->id,
            $content,
            !empty($params['close_ticket']),
            (bool) $isDraft,
            (int) ($params['assignee_id'] ?? 0),
            !empty($params['informational']),
            AttachmentStager::fingerprint($params['attachments'] ?? null),
        ]));
    }

    /**
     * A reply this agent made through MCP on this ticket within DUPLICATE_WINDOW
     * from the same request, or null.
     *
     * @return Conversation|null
     */
    private static function findDuplicateReply(Ticket $ticket, $agent, $requestHash)
    {
        $model = new Conversation();
        $since = $model->freshTimestamp()->modify('-' . self::DUPLICATE_WINDOW . ' seconds');

        $recent = Conversation::where('ticket_id', $ticket->id)
            ->where('person_id', $agent->id)
            ->where('source', 'mcp')
            ->whereIn('conversation_type', ['response', 'draft_response'])
            ->where('created_at', '>=', $model->fromDateTime($since))
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        foreach ($recent as $conversation) {
            if ($conversation->getSettingsValue('mcp_request_hash') === $requestHash) {
                return $conversation;
            }
        }

        return null;
    }

    /** Rows update-response and delete-response may touch: drafts and notes, never sent replies. */
    const EDITABLE_TYPES = ['draft_response', 'note', 'internal_info'];

    public static function updateResponse($params)
    {
        $found = self::findOwnConversation($params, 'response_id', self::EDITABLE_TYPES, 'Draft or note', [__CLASS__, 'scopeEditable']);
        if (is_wp_error($found)) {
            return $found;
        }
        [$ticket, $row] = $found;

        $format  = $params['content_format'] ?? 'markdown';
        $content = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['content'] ?? '', $format)));
        if (!$content) {
            return MCPHelper::error('invalid_param', 'content is required', ['fields' => ['content']]);
        }

        $row = (new ResponseService())->update($row, self::parseSmartcodes($content, $ticket), MCPHelper::resolveAgent());

        $summary = $row->conversation_type === 'draft_response'
            ? sprintf('Draft reply updated on ticket #%d. It is still not sent.', $ticket->id)
            : sprintf('Internal note updated on ticket #%d.', $ticket->id);

        return MCPHelper::envelope($summary, ['response' => [
            'id'         => $row->id,
            'content'    => MCPHelper::htmlToMarkdown($row->content),
            'type'       => $row->conversation_type,
            'created_at' => MCPHelper::toIso8601($row->created_at),
        ]]);
    }

    public static function deleteResponse($params)
    {
        $found = self::findOwnConversation($params, 'response_id', self::EDITABLE_TYPES, 'Draft or note', [__CLASS__, 'scopeEditable']);
        if (is_wp_error($found)) {
            return $found;
        }
        [$ticket, $row] = $found;

        // Like the admin, deleting a note needs the delete permission. An author may
        // always discard their own unsent draft.
        if ($row->conversation_type !== 'draft_response' && !PermissionManager::currentUserCan('fst_delete_tickets')) {
            return MCPHelper::error('forbidden', 'Deleting a note needs the "Delete Tickets & Individual Responses" permission.');
        }

        $deleted = ['id' => $row->id, 'type' => $row->conversation_type];
        (new ResponseService())->delete($row, MCPHelper::resolveAgent());

        return MCPHelper::envelope(
            sprintf('Deleted from ticket #%d.', $ticket->id),
            ['deleted' => $deleted]
        );
    }

    /**
     * Fill {{customer.*}} / {{agent.*}} placeholders, as createResponse() does for new
     * replies, so edited text doesn't reach the customer with literal braces.
     */
    private static function parseSmartcodes($content, Ticket $ticket)
    {
        $content = apply_filters('fluent_support/parse_smartcode_data', $content, [
            'customer' => $ticket->customer,
            'agent'    => MCPHelper::resolveAgent(),
        ]);

        return wp_kses_post(Helper::decodeNonMarkupEntities($content));
    }

    /**
     * Notes are 'note'. Older versions of add-internal-note wrote 'internal_info',
     * which system lines ("Ticket has been closed") also use, so only MCP-made
     * rows of that type count.
     */
    public static function scopeEditable($query)
    {
        $query->where(function ($q) {
            $q->whereIn('conversation_type', ['draft_response', 'note'])
                ->orWhere(function ($q) {
                    $q->where('conversation_type', 'internal_info')->where('source', 'mcp');
                });
        });
    }

    /**
     * Send a draft to the customer. Anyone with "Approve Draft Reply" may publish
     * any draft; an agent who can manage tickets may publish their own. Draft-only
     * agents never can: their drafts exist to be reviewed by someone else.
     */
    public static function publishDraftReply($params)
    {
        $agent = MCPHelper::resolveAgent();
        $canApprove = PermissionManager::currentUserCan('fst_approve_draft_reply');
        $canManage = PermissionManager::userCan(AbilityGuard::MANAGE_TICKET_CAPS);

        $found = self::findOwnConversation(
            $params,
            'response_id',
            ['draft_response'],
            'Draft reply',
            null,
            $canApprove // an approver may publish anyone's draft
        );
        if (is_wp_error($found)) {
            return $found;
        }
        [$ticket, $draft] = $found;

        if (!$canApprove && !($canManage && $agent && (int) $draft->person_id === (int) $agent->id)) {
            return MCPHelper::error('forbidden', 'You can only save drafts. An agent with the "Approve Draft Reply" permission must publish this one.');
        }

        $content = $draft->content;
        if (!empty($params['content'])) {
            $content = self::parseSmartcodes(wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['content'], $params['content_format'] ?? 'markdown'))), $ticket);
        }

        $published = (new ResponseService())->publishDraft($ticket, $draft, $agent, $content);

        return MCPHelper::envelope(
            sprintf('Draft reply published on ticket #%d and sent to the customer.', $ticket->id),
            [
                'response' => [
                    'id'         => $published->id,
                    'content'    => MCPHelper::htmlToMarkdown($published->content),
                    'type'       => $published->conversation_type,
                    'created_at' => MCPHelper::toIso8601($published->created_at),
                ],
                'ticket_status' => $ticket->status,
            ]
        );
    }

    /**
     * Row gate for editing or deleting a conversation: it must be on a ticket the
     * agent can access, of an allowed type, and written by this agent. Anyone else's
     * draft or note is off limits here, whatever the agent's other permissions.
     *
     * @return array|\WP_Error [Ticket, Conversation]
     */
    private static function findOwnConversation($params, $idField, array $types, $label, $scope = null, $anyAuthor = false)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', 'No agent found for current user');
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        $rowId    = (int) ($params[$idField] ?? 0);
        if (!$ticketId || !$rowId) {
            return MCPHelper::error('invalid_param', sprintf('ticket_id and %s are required', $idField), ['fields' => ['ticket_id', $idField]]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', 'Ticket not found', ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        $query = Conversation::where('id', $rowId)
            ->where('ticket_id', $ticket->id)
            ->whereIn('conversation_type', $types);
        if ($scope) {
            $scope($query);
        }
        $row = $query->first();

        if (!$row && Conversation::where('id', $rowId)->where('ticket_id', $ticket->id)->where('conversation_type', 'response')->exists()) {
            return MCPHelper::error(
                'not_editable',
                'This is a sent reply. The customer already has it, so it cannot be changed or deleted here.',
                ['fields' => [$idField], 'retryable' => false]
            );
        }

        if (!$row) {
            return MCPHelper::error(
                'not_found',
                sprintf('%s not found on this ticket', $label),
                ['fields' => [$idField], 'next_step' => 'Use get-ticket to find it: your drafts are type draft_response and your notes type note. Sent replies and internal_info activity lines cannot be changed.']
            );
        }

        if (!$anyAuthor && (int) $row->person_id !== (int) $agent->id) {
            return MCPHelper::error('forbidden', 'This was written by another agent. Only its author can change or delete it.');
        }

        return [$ticket, $row];
    }

    public static function addInternalNote($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', 'No agent found for current user');
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', 'ticket_id is required', ['fields' => ['ticket_id']]);
        }

        $format  = $params['content_format'] ?? 'markdown';
        $content = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['content'] ?? '', $format)));
        if (!$content) {
            return MCPHelper::error('invalid_param', 'content is required', ['fields' => ['content']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', 'Ticket not found', ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        // 'note', as the ticket view writes it: it renders as an agent note and fires
        // note_added_by_agent, which notifies @mentioned agents and logs the activity.
        $attachments = AttachmentStager::stage($params['attachments'] ?? null, $ticket, $agent);
        if (is_wp_error($attachments)) {
            return $attachments;
        }

        $data = [
            'content'           => $content,
            'conversation_type' => 'note',
            'source'            => 'mcp',
        ];

        if ($attachments) {
            $data['attachments'] = $attachments;
        }

        $result = (new ResponseService())->createResponse($data, $agent, $ticket, true);

        if (!$result) {
            return MCPHelper::error('failed', 'Failed to create internal note', ['retryable' => true]);
        }

        (new ResponseService())->fireResponseHooks($result['response'], $result['ticket'], $agent, false);

        return MCPHelper::envelope(
            "Internal note added to ticket #{$ticketId}",
            ['note' => array_filter([
                'id'          => $result['response']->id,
                'content'     => MCPHelper::htmlToMarkdown($result['response']->content),
                'created_at'  => MCPHelper::toIso8601($result['response']->created_at),
                'attachments' => $attachments ? self::attachmentTitles($result['response']) : null,
                'mentions'    => self::mentionReport($content),
            ], function ($value) { return $value !== null; })]
        );
    }
}
