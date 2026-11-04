<?php

namespace FluentSupport\App\Modules\MCP\Tools;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\MCP\Support\CustomerMetaEnricher;
use FluentSupport\App\Modules\MCP\Support\TicketAccessGuard;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\MailerInbox\MailBoxService;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\TicketQuery;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

class TicketTools
{
    const MAX_RESPONSES = 50;

    public static function listTickets($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $args = self::listArgs($params);
        if (is_wp_error($args)) {
            return $args;
        }

        // Sorted below, for the keyset cursor.
        $query = (new TicketQuery($args + ['sort_by' => false]))->query()->with([
            'customer' => function ($q) {
                $q->select(['id', 'first_name', 'last_name', 'email']);
            },
            'agent' => function ($q) {
                $q->select(['id', 'first_name', 'last_name', 'email']);
            },
            'product',
            'tags',
        ]);

        // Sort columns that keyset pagination supports. waiting_since, updated_at and
        // response_count have composite (col,id) indexes (TicketsMigrator); the
        // low-cardinality columns make do with their single-column index.
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'waiting_since', 'status', 'priority', 'client_priority', 'response_count'];
        $sortBy   = sanitize_text_field($params['sort_by'] ?? 'id');
        if (!in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'id';
        }
        $sortType = Helper::sanitizeOrderValue($params['sort_type'] ?? $params['order'] ?? 'DESC');

        ['page' => $page, 'per_page' => $perPage] = MCPHelper::pagination($params);

        // The id tiebreaker follows the sort direction so the order is total,
        // which the keyset cursor needs to resume correctly.
        $query->orderBy($sortBy, $sortType)->orderBy('id', $sortType);

        $cursor = sanitize_text_field($params['cursor'] ?? '');
        if ($cursor !== '') {
            $decoded = self::decodeListCursor($cursor);
            if (!$decoded || $decoded['sort_by'] !== $sortBy || $decoded['sort_type'] !== $sortType) {
                return MCPHelper::error('invalid_param', __('Invalid or expired cursor, or it was generated with a different sort_by/sort_type than this request', 'fluent-support'), ['fields' => ['cursor'], 'next_step' => 'Use the same sort_by/sort_type as the call that produced this cursor, or omit cursor to start a fresh sweep']);
            }

            self::applyCursorWhere($query, $sortBy, $sortType, $decoded);
        }

        // Keyset mode: fetch one extra row to detect has_more without a COUNT.
        if ($cursor !== '') {
            $rows     = $query->limit($perPage + 1)->get();
            $hasMore  = $rows->count() > $perPage;
            $rows     = $rows->slice(0, $perPage)->values();
            $nextCursor = $hasMore && $rows->isNotEmpty()
                ? self::encodeListCursor($rows->last(), $sortBy, $sortType)
                : null;

            $summary = sprintf(_n('Found %d ticket', 'Found %d tickets', $rows->count(), 'fluent-support'), $rows->count());

            $customerMeta = CustomerMetaEnricher::resolve($rows, [
                'surface'  => 'ticket_list',
                'agent_id' => $agent->id,
            ]);

            return MCPHelper::envelope($summary, ['tickets' => MCPHelper::formatTicketList($rows, $customerMeta)], [
                'paging' => [
                    'per_page'    => $perPage,
                    'has_more'    => $hasMore,
                    'next_cursor' => $nextCursor,
                ],
            ]);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $total   = $paginated->total();
        $summary = sprintf(
            /* translators: %1$d is the number of tickets found, %2$d is the current page, %3$d is the last page */
            _n('Found %1$d ticket (page %2$d of %3$d)', 'Found %1$d tickets (page %2$d of %3$d)', $total, 'fluent-support'),
            $total,
            $paginated->currentPage(),
            $paginated->lastPage()
        );

        $items = $paginated->items();

        $customerMeta = CustomerMetaEnricher::resolve($items, [
            'surface'  => 'ticket_list',
            'agent_id' => $agent->id,
        ]);

        $meta = MCPHelper::pagingMeta($paginated);
        // Lets a sweep switch from page/offset to keyset pagination.
        $meta['paging']['next_cursor'] = !empty($items)
            ? self::encodeListCursor(end($items), $sortBy, $sortType)
            : null;

        return MCPHelper::envelope($summary, ['tickets' => MCPHelper::formatTicketList($items, $customerMeta)], $meta);
    }

    /**
     * Keyset WHERE for rows strictly after $decoded. MySQL sorts NULLs first in
     * ASC and last in DESC, and a plain `>`/`<` never matches NULL, so each
     * branch handles NULLs explicitly to avoid skipping them.
     */
    private static function applyCursorWhere($query, $sortBy, $sortType, $decoded)
    {
        $id = $decoded['id'];
        $v  = $decoded['v'];

        if ($sortType === 'ASC') {
            if ($v === null) {
                // Still among the leading NULL rows, or past them entirely.
                $query->where(function ($q) use ($sortBy, $id) {
                    $q->where(function ($q2) use ($sortBy, $id) {
                        $q2->whereNull($sortBy)->where('id', '>', $id);
                    })->orWhereNotNull($sortBy);
                });
            } else {
                // Past the NULL rows, which sort first in ASC.
                $query->where(function ($q) use ($sortBy, $v, $id) {
                    $q->where($sortBy, '>', $v)
                      ->orWhere(function ($q2) use ($sortBy, $v, $id) {
                          $q2->where($sortBy, '=', $v)->where('id', '>', $id);
                      });
                });
            }
        } else {
            if ($v === null) {
                // Already among the trailing NULL rows (they sort last in DESC).
                $query->whereNull($sortBy)->where('id', '<', $id);
            } else {
                // NULL rows come after all non-null values.
                $query->where(function ($q) use ($sortBy, $v, $id) {
                    $q->where($sortBy, '<', $v)
                      ->orWhere(function ($q2) use ($sortBy, $v, $id) {
                          $q2->where($sortBy, '=', $v)->where('id', '<', $id);
                      })
                      ->orWhereNull($sortBy);
                });
            }
        }
    }

    /**
     * Keyset cursor: last row's id and sort value, plus the sort_by/sort_type
     * it belongs to so it can't be reused against a different column.
     * Not signed; it only holds values the caller has already seen.
     */
    private static function encodeListCursor($lastRow, $sortBy, $sortType)
    {
        // Raw value, since date casts would serialize as an object and break the
        // scalar comparison in applyCursorWhere().
        $value = $lastRow->getRawOriginal($sortBy);
        return base64_encode(wp_json_encode([
            'id'        => (int) $lastRow->id,
            'v'         => $value,
            'sort_by'   => $sortBy,
            'sort_type' => $sortType,
        ]));
    }

    private static function decodeListCursor($cursor)
    {
        $decoded = json_decode(base64_decode($cursor, true), true);
        if (!is_array($decoded) || !isset($decoded['id'], $decoded['sort_by'], $decoded['sort_type']) || !array_key_exists('v', $decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * The list-tickets parameters as TicketQuery arguments, or an error for a bad date.
     */
    private static function listArgs($params)
    {
        // Empty values are left out: a 0 would match "no product" or "no mailbox".
        $args = array_filter([
            'customer_id'          => (int) ($params['customer_id'] ?? 0),
            'agent_id'             => !empty($params['agent_id']) ? (int) $params['agent_id'] : null,
            'product_id'           => (int) ($params['product_id'] ?? 0),
            'mailbox_id'           => (int) ($params['mailbox_id'] ?? 0),
            'priority'             => !empty($params['priority']) ? MCPHelper::normalizePriority($params['priority']) : null,
            'client_priority'      => !empty($params['client_priority']) ? MCPHelper::normalizePriority($params['client_priority']) : null,
            'ticket_tags'          => !empty($params['tags']) ? array_map('intval', (array) $params['tags']) : null,
            'unassigned'           => !empty($params['unassigned']),
            'needs_first_response' => !empty($params['needs_first_response']),
            'search'               => sanitize_text_field($params['search'] ?? ''),
            'deep_search'          => true,
        ]);

        $waitingOn = $params['waiting_on'] ?? '';
        if (!empty($params['status'])) {
            $args['status_type'] = $params['status'];
        } elseif (!empty($params['waiting_for_reply']) || $waitingOn) {
            // Closed tickets aren't waiting on anyone, so default to open.
            $args['status_type'] = 'open';
        }
        if (!empty($params['waiting_for_reply']) || $waitingOn === 'agent') {
            $args['waiting_for_reply'] = 'yes';
        } elseif ($waitingOn === 'customer') {
            // We replied last: the ticket waits on the customer (as my_queue.awaiting_customer).
            $args['waiting_on_customer'] = true;
        }

        foreach (['created_after', 'created_before'] as $field) {
            if (empty($params[$field])) {
                continue;
            }
            try {
                $args[$field] = (new \DateTime(sanitize_text_field($params[$field])))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return MCPHelper::error('invalid_param', sprintf(__("Invalid date format for '%s'", 'fluent-support'), $field), ['fields' => [$field], 'hint' => 'Use YYYY-MM-DD or ISO 8601']);
            }
        }

        return $args;
    }

    public static function getTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', __('ticket_id is required', 'fluent-support'), ['fields' => ['ticket_id']]);
        }

        $ticket = Ticket::with(['customer', 'agent', 'product', 'mailbox', 'tags'])->find($ticketId);

        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        $maxChars = max(0, (int) ($params['max_chars_per_response'] ?? 0));

        // Re-sign inline image URLs before formatting (not saved), so the body is
        // converted to Markdown once.
        $ticket->content = Helper::refreshSignedAttachmentUrls($ticket->content, $ticket->id);
        $data = MCPHelper::formatTicketForMCP($ticket);
        $data = array_merge($data, MCPHelper::truncatedContent($data['content'], $maxChars));

        // Attachments of the opening message have no conversation.
        $openingAttachments = \FluentSupport\App\Models\Attachment::where('ticket_id', $ticket->id)
            ->whereNull('conversation_id')
            ->where('status', 'active')
            ->get();
        if (!$openingAttachments->isEmpty()) {
            $data['attachments'] = MCPHelper::formatAttachments($openingAttachments);
        }

        if ($ticket->customer) {
            $customer   = $ticket->customer;
            $customerId = $customer->id;
            // Only count and list sibling tickets this agent could open directly.
            $customerTickets = (new AgentTicketAccess())
                ->applyAccessScope(Ticket::where('customer_id', $customerId), $agent);

            $data['customer']['total_tickets'] = (clone $customerTickets)->count();
            $data['customer']['open_tickets']  = (clone $customerTickets)
                ->whereNotIn('status', ['closed'])->count();
            $data['customer']['first_seen'] = MCPHelper::toIso8601($customer->created_at);

            $prevTickets = (clone $customerTickets)
                ->where('id', '!=', $ticket->id)
                ->select(['id', 'title', 'status', 'priority', 'created_at'])
                ->orderBy('id', 'DESC')
                ->limit(10)
                ->get();

            if ($prevTickets->count()) {
                $data['previous_tickets'] = $prevTickets->map(function ($t) {
                    return [
                        'id'         => $t->id,
                        'title'      => $t->title,
                        'status'     => $t->status ?: 'new',
                        'priority'   => MCPHelper::normalizePriority($t->priority),
                        'created_at' => MCPHelper::toIso8601($t->created_at),
                    ];
                })->toArray();
            }

            // true/false, or a list of widget keys to keep.
            $withIntegrations = $params['with_integrations'] ?? true;
            $integrationKeys  = null;
            if (is_array($withIntegrations)) {
                // Widget keys are compared as given; sanitize_key() would lowercase them.
                $integrationKeys  = array_values(array_filter(array_map(function ($key) {
                    return is_scalar($key) ? sanitize_text_field((string) $key) : '';
                }, $withIntegrations)));
                $withIntegrations = (bool) $integrationKeys;
            }

            if ($withIntegrations !== false) {
                $extraWidgets = ProfileInfoService::getProfileExtraWidgets($customer, ['format' => 'mcp', 'keys' => $integrationKeys]);
                if ($extraWidgets) {
                    // Filter again for integrations that ignore the keys hint.
                    $data['integrations'] = MCPHelper::formatExtraWidgets($extraWidgets, $integrationKeys);
                }
            }

            $crmData = Helper::getFluentCrmContactData($customer);
            if ($crmData) {
                $data['crm'] = [
                    'name'   => $crmData['full_name'] ?? '',
                    'status' => $crmData['status'] ?? '',
                    'tags'   => !empty($crmData['tags']) ? $crmData['tags']->pluck('title')->toArray() : [],
                    'lists'  => !empty($crmData['lists']) ? $crmData['lists']->pluck('title')->toArray() : [],
                ];
            }
        }

        // Email templates are per inbox: say what this ticket's reply email adds
        // around the agent's text, so the reply doesn't repeat it.
        if ($ticket->mailbox) {
            $data['reply_email'] = ManagementTools::describeAgentEmail($ticket->mailbox, 'ticket_replied_by_agent_email_to_customer', $agent);
        }

        $customFields = $ticket->customData('admin', true);
        if ($customFields) {
            $data['custom_fields'] = $customFields;
        }

        $withResponses = ($params['with_responses'] ?? true) !== false;
        if ($withResponses) {
            $responsePage    = max(1, (int) ($params['response_page'] ?? 1));
            $offset          = ($responsePage - 1) * self::MAX_RESPONSES;
            $responseId      = (int) ($params['response_id'] ?? 0);
            $sinceResponseId = (int) ($params['since_response_id'] ?? 0);

            // Snapshot the newest id first and read up to it, so a reply that lands
            // mid-call is not reported in latest_response_id without being returned.
            $latestId = (int) \FluentSupport\App\Models\Conversation::where('ticket_id', $ticket->id)
                ->where('conversation_type', '!=', 'schedule')
                ->max('id');

            $query = \FluentSupport\App\Models\Conversation::where('ticket_id', $ticket->id)
                ->where('id', '<=', $latestId)
                ->where('conversation_type', '!=', 'schedule')
                ->with(['person', 'attachments']);

            if ($responseId) {
                $query->where('id', $responseId);
            } elseif ($sinceResponseId) {
                $query->where('id', '>', $sinceResponseId);
            }

            // One extra row tells us whether an older page exists.
            $rows    = $query->orderBy('id', 'desc')
                ->offset($offset)
                ->limit(self::MAX_RESPONSES + 1)
                ->get();
            $hasMore = $rows->count() > self::MAX_RESPONSES;
            if ($hasMore) {
                $rows = $rows->slice(0, self::MAX_RESPONSES);
            }

            if ($responseId && $rows->isEmpty()) {
                return MCPHelper::error('not_found', __('Response not found on this ticket', 'fluent-support'), ['fields' => ['response_id']]);
            }

            // Inline images carry signed URLs that expire; re-sign them.
            $rows     = $rows->reverse()->values();
            $contents = Helper::refreshSignedAttachmentUrlsInContents($rows->pluck('content')->all(), $ticket->id);
            foreach ($rows as $index => $row) {
                $row->content = $contents[$index];
            }

            $data['responses']      = MCPHelper::formatResponseThread($rows, $maxChars);
            $data['responses_meta'] = [
                'page'     => $responsePage,
                'per_page' => self::MAX_RESPONSES,
                'has_more' => $hasMore,
            ];

            if ($sinceResponseId) {
                $data['responses_meta']['since_response_id'] = $sinceResponseId;
            }

            // Pass this as since_response_id next time to fetch only new messages.
            $data['responses_meta']['latest_response_id'] = max($latestId, $sinceResponseId);
        }

        $summary = "Ticket #{$ticket->id}: {$ticket->title} [{$data['status']}, {$data['priority']}]";

        return MCPHelper::envelope($summary, $data);
    }

    public static function createTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $title   = sanitize_text_field($params['title'] ?? '');
        $format  = $params['content_format'] ?? 'markdown';
        $content = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['content'] ?? '', $format)));

        if (!$title || !$content) {
            return MCPHelper::error('invalid_param', __('title and content are required', 'fluent-support'), ['fields' => ['title', 'content']]);
        }

        $email = sanitize_email($params['customer_email'] ?? '');
        if (!$email || !is_email($email)) {
            return MCPHelper::error('invalid_param', __('A valid customer_email is required', 'fluent-support'), ['fields' => ['customer_email']]);
        }

        $customer = CustomerService::findOrCreate(array_filter([
            'email'      => $email,
            'first_name' => sanitize_text_field($params['customer_first_name'] ?? ''),
            'last_name'  => sanitize_text_field($params['customer_last_name'] ?? ''),
        ]));
        if (!$customer || !$customer->exists) {
            return MCPHelper::error('customer_create_failed', __('Could not create a customer record for this email', 'fluent-support'));
        }

        $ticketData = [
            'title'           => $title,
            'content'         => $content,
            'customer_id'     => $customer->id,
            'source'          => 'mcp',
            'status'          => 'new',
            'priority'        => 'normal',
            // Logged on the customer's behalf by default, so the customer gets the
            // created-by-agent email. See agent_initiated below for outreach.
        ];

        if (!empty($params['priority'])) {
            $ticketData['priority'] = MCPHelper::normalizePriority($params['priority']);
        }

        if (!empty($params['product_id'])) {
            $productId = (int) $params['product_id'];
            if (!\FluentSupport\App\Models\Product::find($productId)) {
                return MCPHelper::error('invalid_param', __('The specified product does not exist', 'fluent-support'), ['fields' => ['product_id'], 'next_step' => 'Use get-support-context to see available products and their IDs']);
            }
            $ticketData['product_id'] = $productId;
        }

        if (!empty($params['mailbox_id'])) {
            $mailboxId = (int) $params['mailbox_id'];
            if ($err = TicketAccessGuard::assertMailboxWritable($mailboxId)) {
                return $err;
            }
            if (!\FluentSupport\App\Models\MailBox::find($mailboxId)) {
                return MCPHelper::error('invalid_param', __('The specified mailbox does not exist', 'fluent-support'), ['fields' => ['mailbox_id'], 'next_step' => 'Use get-support-context to see available mailboxes and their IDs']);
            }
            $ticketData['mailbox_id'] = $mailboxId;
        }

        if (!empty($params['agent_id'])) {
            // Check the assignee's mailbox restriction against the mailbox the
            // ticket will land in, which is the default when none is given.
            $effectiveMailboxId = (int) ($ticketData['mailbox_id'] ?? 0);
            if (!$effectiveMailboxId) {
                $defaultMailbox     = Helper::getDefaultMailBox();
                $effectiveMailboxId = $defaultMailbox ? (int) $defaultMailbox->id : 0;
            }

            $probeTicket = null;
            if ($effectiveMailboxId) {
                $probeTicket             = new Ticket();
                $probeTicket->mailbox_id = $effectiveMailboxId;
            }

            $agentRecord = MCPHelper::resolveAssignmentTarget($params['agent_id'], $probeTicket, 'agent_id');
            if (is_wp_error($agentRecord)) {
                return $agentRecord;
            }
            $ticketData['agent_id'] = $agentRecord->id;
        }

        // Agent-initiated outreach: no creation emails, and `content` is posted as
        // the agent's opening reply.
        if (filter_var($params['agent_initiated'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $ticketData['agent_initiated'] = 'yes';
        }

        $ticket = (new TicketService())->storeTicket($ticketData, $customer);
        $ticket->load(['customer', 'agent', 'product', 'mailbox', 'tags']);

        $data = ['ticket' => MCPHelper::formatTicketForMCP($ticket)];

        if (
            !$ticket->agent_id &&
            !PermissionManager::currentUserCan('fst_manage_unassigned_tickets') &&
            !PermissionManager::currentUserCan('fst_manage_other_tickets')
        ) {
            $data['warning'] = __('Ticket created unassigned. Your permission level does not include access to unassigned tickets, so follow-up actions (reply, close, update) will be denied until the ticket is assigned to you by a manager.', 'fluent-support');
        }

        return MCPHelper::envelope(
            "Ticket #{$ticket->id} created: {$ticket->title}",
            $data
        );
    }

    public static function closeTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', __('ticket_id is required', 'fluent-support'), ['fields' => ['ticket_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        if ($ticket->status === 'closed') {
            $ticket->load(['customer', 'agent', 'product', 'mailbox', 'tags']);
            return MCPHelper::envelope(
                "Ticket #{$ticket->id} is already closed",
                ['ticket' => MCPHelper::formatTicketForMCP($ticket)]
            );
        }

        $format       = $params['content_format'] ?? 'markdown';
        $replyContent = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['reply_content'] ?? '', $format)));
        $internalNote = wp_kses_post(Helper::decodeNonMarkupEntities(MCPHelper::processContent($params['internal_note'] ?? '', $format)));

        // close() takes 'yes'/'no', and only the exact string 'no' notifies.
        $silently = !empty($params['silent']) ? 'yes' : 'no';

        // Atomic, so a failed close doesn't leave the reply on an open ticket.
        (new Ticket())->getConnection()->transaction(function () use ($replyContent, $internalNote, $agent, $ticket, $silently) {
            if ($replyContent) {
                $data = [
                    'content'           => $replyContent,
                    'conversation_type' => 'response',
                    'source'            => 'mcp',
                ];
                (new ResponseService())->createResponse($data, $agent, $ticket);
            }

            (new TicketService())->close($ticket, $agent, $internalNote, $silently);
        });

        $ticket->load(['customer', 'agent', 'product', 'mailbox', 'tags']);

        $summary = $replyContent
            ? "Reply sent and ticket #{$ticket->id} closed"
            : "Ticket #{$ticket->id} closed";

        if ($silently === 'yes') {
            $summary .= ' silently (no close notification or automations fired)';
        }

        return MCPHelper::envelope($summary, ['ticket' => MCPHelper::formatTicketForMCP($ticket)]);
    }

    public static function updateTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', __('ticket_id is required', 'fluent-support'), ['fields' => ['ticket_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        $updatable = ['title', 'priority', 'status', 'product_id'];

        // Validate the target mailbox (if any) up front, before any write, so
        // a bad mailbox_id/agent_id below never leaves a partial change behind.
        $targetMailboxId = null;
        if (isset($params['mailbox_id'])) {
            $targetMailboxId = (int) $params['mailbox_id'];

            // Same gate the mailbox switcher and the bulk-move endpoint use.
            // Picking a mailbox at creation stays open to every agent.
            if (!PermissionManager::currentUserCan('fst_manage_settings')) {
                return MCPHelper::error(
                    'forbidden',
                    __('You do not have permission to move this ticket to another mailbox.', 'fluent-support'),
                    ['fields' => ['mailbox_id'], 'retryable' => false]
                );
            }

            if ($err = TicketAccessGuard::assertMailboxWritable($targetMailboxId)) {
                return $err;
            }
            if (!\FluentSupport\App\Models\MailBox::find($targetMailboxId)) {
                return MCPHelper::error('invalid_param', __('The specified mailbox does not exist', 'fluent-support'), ['fields' => ['mailbox_id'], 'next_step' => 'Use get-support-context to see available mailboxes and their IDs']);
            }
        }

        // Resolve the assignee before any write. When this call also moves the
        // mailbox, probe the restriction check against the destination mailbox
        // (not the ticket's current one) — same pattern as createTicket() above.
        $assignTarget = null;
        if (isset($params['agent_id'])) {
            $probeTicket = $ticket;
            if ($targetMailboxId !== null) {
                $probeTicket             = clone $ticket;
                $probeTicket->mailbox_id = $targetMailboxId;
            }

            $assignTarget = MCPHelper::resolveAssignmentTarget($params['agent_id'], $probeTicket, 'agent_id');
            if (is_wp_error($assignTarget)) {
                return $assignTarget;
            }
        }

        // A ticket must not land in a mailbox its eventual assignee is
        // restricted from — the incoming agent if this call also reassigns,
        // otherwise whoever is assigned now.
        if ($targetMailboxId !== null) {
            $assigneeToCheck = $assignTarget ?: $ticket->agent;
            if ($assigneeToCheck && TicketAccessGuard::assertAssignableAgent($ticket, $assigneeToCheck, $targetMailboxId)) {
                return MCPHelper::error(
                    'forbidden',
                    __('The assigned agent is restricted from the selected mailbox. Reassign the ticket before moving it.', 'fluent-support'),
                    ['fields' => ['mailbox_id'], 'next_step' => 'Pass agent_id in the same call to reassign, or assign the ticket to an unrestricted agent first']
                );
            }
        }

        $changes = [];
        foreach ($updatable as $field) {
            if (!isset($params[$field])) {
                continue;
            }

            if ($field === 'product_id') {
                $pid = (int) $params['product_id'];
                if ($pid > 0 && !\FluentSupport\App\Models\Product::find($pid)) {
                    return MCPHelper::error('invalid_param', __('The specified product does not exist', 'fluent-support'), ['fields' => ['product_id'], 'next_step' => 'Use get-support-context to see available products and their IDs']);
                }
                $changes['product_id'] = $pid;
                continue;
            }

            $value = sanitize_text_field($params[$field]);

            if ($field === 'priority') {
                $value = MCPHelper::normalizePriority($value);
            }

            if ($field === 'status') {
                $allowedStatuses = ['new', 'active'];
                if (!in_array($value, $allowedStatuses, true)) {
                    return MCPHelper::error('invalid_param', sprintf(__("Invalid status '%s'", 'fluent-support'), $value), ['fields' => ['status'], 'allowed' => $allowedStatuses]);
                }
                // status=active reopens a closed ticket, as reopen in the ticket view does.
                if ($ticket->status === 'closed' && $value !== 'active') {
                    return MCPHelper::error(
                        'ticket_closed',
                        __('This ticket is closed. Pass status=active to reopen it.', 'fluent-support'),
                        ['fields' => ['status'], 'retryable' => false]
                    );
                }
            }

            $changes[$field] = $value;
        }

        // The same write path as the ticket view: activity line, hooks, reopen.
        try {
            $changed = (new TicketService())->change($ticket, $changes, $agent);
        } catch (\FluentSupport\App\Exceptions\ApiException $e) {
            return MCPHelper::error('invalid_param', $e->getMessage(), ['fields' => array_keys($changes)]);
        }
        $reopen = isset($changed['status']) && $changed['status'][0] === 'closed';

        if ($assignTarget) {
            MCPHelper::applyAgentAssignment($ticket, $assignTarget, $agent);
        }

        // Move the mailbox last, after the assignment/scalar changes are
        // durable, so MailBoxService::moveTickets()'s own restriction check
        // (which reads the ticket's agent fresh from the DB) sees the final
        // agent rather than the one being replaced in this same call.
        if ($targetMailboxId !== null) {
            $currentMailboxId = (int) $ticket->mailbox_id;

            if ($currentMailboxId !== $targetMailboxId) {
                if ($currentMailboxId) {
                    // Go through the move service so the move hooks fire, same as every other surface.
                    try {
                        (new MailBoxService())->moveTickets([
                            'new_box_id' => $targetMailboxId,
                            'ticket_ids' => [$ticket->id],
                            'move_type'  => 'Custom',
                        ], $currentMailboxId);
                    } catch (\Exception $e) {
                        return MCPHelper::error('invalid_param', $e->getMessage(), ['fields' => ['mailbox_id']]);
                    }
                } else {
                    $ticket->mailbox_id = $targetMailboxId;
                    $ticket->save();
                }

                // Keep the in-memory ticket in sync with what was just persisted.
                $ticket->mailbox_id = $targetMailboxId;
            }
        }

        $ticket->load(['customer', 'agent', 'product', 'mailbox', 'tags']);

        return MCPHelper::envelope(
            $reopen ? "Ticket #{$ticket->id} reopened and updated" : "Ticket #{$ticket->id} updated",
            ['ticket' => MCPHelper::formatTicketForMCP($ticket)]
        );
    }

    public static function deleteTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', __('ticket_id is required', 'fluent-support'), ['fields' => ['ticket_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        $ticketTitle = $ticket->title;
        (new TicketService())->deleteTicket($ticket, $agent);

        return MCPHelper::envelope(
            "Ticket #{$ticketId} permanently deleted: {$ticketTitle}",
            ['deleted_title' => sanitize_text_field($ticketTitle)]
        );
    }

    public static function getTicketActivity($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $ticketId = (int) ($params['ticket_id'] ?? 0);
        if (!$ticketId) {
            return MCPHelper::error('invalid_param', __('ticket_id is required', 'fluent-support'), ['fields' => ['ticket_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        $activities = Activity::where('object_type', 'ticket')
            ->where('object_id', $ticketId)
            ->with('person')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $count = $activities->count();

        return MCPHelper::envelope(
            "Found {$count} activity entries for ticket #{$ticketId}",
            [
                'ticket_id'  => $ticketId,
                'activities' => $activities->map(function ($a) {
                    return [
                        'id'          => $a->id,
                        'event'       => $a->event_type,
                        'description' => MCPHelper::htmlToText($a->description),
                        'person'      => MCPHelper::personName($a->person),
                        'person_type' => $a->person_type,
                        'created_at'  => MCPHelper::toIso8601($a->created_at),
                    ];
                })->toArray(),
            ],
            ['total' => $count]
        );
    }

    public static function mergeTickets($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        if (!class_exists('\FluentSupportPro\App\Services\ProTicketService')) {
            return MCPHelper::error('not_available', __('Merge tickets requires Fluent Support Pro', 'fluent-support'));
        }

        $targetId = (int) ($params['target_ticket_id'] ?? 0);
        $mergeIds = array_map('intval', (array) ($params['merge_ticket_ids'] ?? []));

        if (!$targetId || empty($mergeIds)) {
            return MCPHelper::error('invalid_param', __('target_ticket_id and merge_ticket_ids are required', 'fluent-support'), ['fields' => ['target_ticket_id', 'merge_ticket_ids']]);
        }

        $mergeIds = array_values(array_unique($mergeIds));

        if (in_array($targetId, $mergeIds, true)) {
            return MCPHelper::error(
                'invalid_param',
                __('target_ticket_id must not also appear in merge_ticket_ids', 'fluent-support'),
                ['fields' => ['target_ticket_id', 'merge_ticket_ids']]
            );
        }

        $target = Ticket::find($targetId);
        if (!$target) {
            return MCPHelper::error('not_found', __('Target ticket not found', 'fluent-support'), ['fields' => ['target_ticket_id'], 'next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($target)) {
            return $err;
        }

        $inaccessible   = [];
        $mergeTicketMap = Ticket::whereIn('id', $mergeIds)->get()->keyBy('id');
        foreach ($mergeIds as $mergeId) {
            $source = $mergeTicketMap->get($mergeId);
            if (!$source || TicketAccessGuard::assert($source)) {
                $inaccessible[] = $mergeId;
            }
        }

        if ($inaccessible) {
            return MCPHelper::error(
                'forbidden',
                sprintf(
                    /* translators: %s is a comma-separated list of ticket IDs */
                    _n('You do not have access to this ticket: %s', 'You do not have access to these tickets: %s', count($inaccessible), 'fluent-support'),
                    implode(', ', $inaccessible)
                )
            );
        }

        $proService = new \FluentSupportPro\App\Services\ProTicketService();
        $result     = $proService->mergeCustomerTickets($mergeIds, $targetId);

        if ($result === null) {
            return MCPHelper::error('merge_failed', __('Ticket merge failed or was only partially completed. Some tickets may be in an inconsistent state.', 'fluent-support'));
        }

        $target->load(['customer', 'agent', 'product', 'mailbox', 'tags']);

        $mergeCount = count($mergeIds);

        return MCPHelper::envelope(
            "{$mergeCount} ticket(s) merged into #{$targetId}",
            ['ticket' => MCPHelper::formatTicketForMCP($target)]
        );
    }

    /** Customer emails a new ticket can trigger; split-ticket holds them back by default. */
    const SPLIT_CUSTOMER_EMAILS = [
        'ticket_created_email_to_customer',
        'ticket_created_by_agent_email_to_customer',
        'ticket_created_by_agent_on_behalf_email_to_customer',
    ];

    /**
     * Move one message out of a ticket into a new ticket (Pro), as the ticket view's
     * "Split Ticket" does: the message and its attachments leave the original ticket
     * and open the new one, and both tickets get an activity line.
     */
    public static function splitTicket($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        if (!class_exists('\FluentSupportPro\App\Services\ProTicketService')) {
            return MCPHelper::error('not_available', __('Split ticket requires Fluent Support Pro', 'fluent-support'));
        }

        $ticketId   = (int) ($params['ticket_id'] ?? 0);
        $responseId = (int) ($params['response_id'] ?? 0);
        if (!$ticketId || !$responseId) {
            return MCPHelper::error('invalid_param', __('ticket_id and response_id are required', 'fluent-support'), ['fields' => ['ticket_id', 'response_id']]);
        }

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return MCPHelper::error('not_found', __('Ticket not found', 'fluent-support'), ['next_step' => 'Use list-tickets to find valid ticket IDs']);
        }

        if ($err = TicketAccessGuard::assert($ticket)) {
            return $err;
        }

        // Only a message someone sent: notes, drafts and activity lines stay put.
        $response = Conversation::where('id', $responseId)
            ->where('ticket_id', $ticket->id)
            ->where('conversation_type', 'response')
            ->first();
        if (!$response) {
            return MCPHelper::error('not_found', __('Message not found on this ticket', 'fluent-support'), [
                'fields'    => ['response_id'],
                'next_step' => 'Use get-ticket and pick a response of type "response"',
            ]);
        }

        $customerId = (int) ($params['customer_id'] ?? 0);
        if (!$customerId) {
            // The message's author when a customer wrote it, else the ticket's customer.
            $author     = Person::find($response->person_id);
            $customerId = ($author && $author->person_type === 'customer') ? (int) $author->id : (int) $ticket->customer_id;
        }
        if (!Customer::find($customerId)) {
            return MCPHelper::error('not_found', __('Customer not found', 'fluent-support'), ['fields' => ['customer_id']]);
        }

        $content = $response->content;
        if (isset($params['content']) && $params['content'] !== '') {
            $content = MCPHelper::processContent($params['content'], $params['content_format'] ?? 'markdown');
        }

        $title = sanitize_text_field((string) ($params['title'] ?? ''));
        if ($title === '') {
            /* translators: %s is the original ticket title */
            $title = sprintf(__('Re: %s', 'fluent-support'), $ticket->title);
        }

        $mailboxId = (int) $ticket->mailbox_id;
        if (!empty($params['mailbox_id']) && (int) $params['mailbox_id'] !== $mailboxId) {
            $mailboxId = (int) $params['mailbox_id'];
            if ($err = TicketAccessGuard::assertMailboxWritable($mailboxId)) {
                return $err;
            }
            if (!\FluentSupport\App\Models\MailBox::find($mailboxId)) {
                return MCPHelper::error('invalid_param', __('The specified mailbox does not exist', 'fluent-support'), ['fields' => ['mailbox_id'], 'next_step' => 'Use get-support-context to see available mailboxes and their IDs']);
            }
        }

        $data = [
            'conversation_id' => $response->id,
            'customer_id'     => $customerId,
            'mailbox_id'      => $mailboxId,
            'title'           => $title,
            'content'         => wp_kses_post(Helper::decodeNonMarkupEntities($content)),
            'client_priority' => $ticket->client_priority ?: 'normal',
        ];

        $productId = (int) ($params['product_id'] ?? ($ticket->product_id ?: 0));
        if ($productId) {
            $data['product_id'] = $productId;
        }

        // A split is housekeeping inside a conversation the customer is already in, so
        // the new ticket's "ticket created" emails to the customer are held back unless
        // asked for. Scoped to tickets created during this call; agent emails still go.
        $quiet = null;
        if (empty($params['notify_customer'])) {
            $originalId = (int) $ticket->id;
            $quiet = function ($shouldSend, $channel, $type, $created = null) use ($originalId) {
                if ($channel === 'email'
                    && $created && (int) $created->id !== $originalId
                    && in_array($type, self::SPLIT_CUSTOMER_EMAILS, true)) {
                    return false;
                }
                return $shouldSend;
            };
            add_filter('fluent_support/should_send_notification', $quiet, 10, 4);
        }

        try {
            $result = (new \FluentSupportPro\App\Services\ProTicketService())->splitToNewTicket($ticket->id, $data);
        } catch (\Throwable $e) {
            // Not Helper::getSafeErrorMessage(): it throws, which would surface as a retryable internal_error.
            return MCPHelper::error('split_failed', $e->getMessage() ?: __('Could not split the ticket', 'fluent-support'), ['retryable' => false]);
        } finally {
            if ($quiet) {
                remove_filter('fluent_support/should_send_notification', $quiet, 10);
            }
        }

        $newTicket = Ticket::with(['customer', 'agent', 'product', 'mailbox', 'tags'])->find((int) ($result['new_ticket_id'] ?? 0));
        if (!$newTicket) {
            return MCPHelper::error('split_failed', __('The new ticket could not be loaded', 'fluent-support'), ['retryable' => false]);
        }

        return MCPHelper::envelope(
            /* translators: 1: response ID, 2: original ticket ID, 3: new ticket ID */
            sprintf(__('Message #%1$d moved from ticket #%2$d to new ticket #%3$d', 'fluent-support'), $response->id, $ticket->id, $newTicket->id),
            ['ticket' => MCPHelper::formatTicketForMCP($newTicket), 'original_ticket_id' => (int) $ticket->id]
        );
    }
}
