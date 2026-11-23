<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Models\TicketTag;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\UploadService;
use FluentSupport\App\Services\Storage\StorageManager;
use FluentSupport\Framework\Support\Arr;

class TicketService
{
    /**
     * ticket id => what fireCreatedHooks() needs for a ticket created silently:
     * the agent the data named, and the creating agent for storeTicket().
     */
    private $pendingCreated = [];

    /**
     * The ticket fields change() sets. Mailbox moves go through
     * MailBoxService::moveTickets(); the agent through assignAgent().
     */
    const CHANGEABLE = ['title', 'status', 'priority', 'client_priority', 'product_id', 'customer_id'];

    /**
     * Change title, status, priority, client priority, product or customer: the one
     * write path for these fields (the property endpoint, bulk actions, MCP,
     * workflows). Status 'closed' closes the ticket and closed -> another status
     * reopens it, through close() / reopen(). Unknown or unchanged fields are left
     * alone. The caller checks the permission; values are checked here.
     *
     * Fires fluent_support/ticket_status_changed ($ticket, $old, $new, $by; close()
     * and reopen() fire it too, so it covers every status change that isn't silent),
     * fluent_support/ticket_priority_changed ($ticket, $old, $new, $by) and, for any
     * change, fluent_support/ticket_updated ($ticket, [field => [from, to]], $by).
     *
     * @param Ticket $ticket
     * @param array $changes field => new value
     * @param \FluentSupport\App\Models\Person|null $by Null only for an automation with no agent
     *                                                   that does not close or reopen the ticket.
     * @return array field => [from, to], the fields that changed
     * @throws ApiException 422 when a value is invalid
     */
    public function change($ticket, array $changes, $by)
    {
        $changes = $this->checkChanges(array_intersect_key($changes, array_flip(self::CHANGEABLE)));
        $oldStatus = $ticket->status;
        $newStatus = Arr::get($changes, 'status', $oldStatus);
        unset($changes['status']);

        $changed = [];
        foreach ($changes as $field => $value) {
            if ($ticket->{$field} != $value) {
                $changed[$field] = [$ticket->{$field}, $value];
                $ticket->{$field} = $value;
            }
        }

        // close() and reopen() save the other fields too, and write their own activity line.
        if ($newStatus !== $oldStatus) {
            if ($newStatus === 'closed') {
                $this->close($ticket, $by);
            } elseif ($oldStatus === 'closed') {
                $this->reopen($ticket, $by);
                // reopen() makes it active; another open status is a second change.
                if ($newStatus !== 'active') {
                    $ticket->status = $newStatus;
                    $ticket->save();
                    do_action('fluent_support/ticket_status_changed', $ticket, 'active', $newStatus, $by);
                }
            } else {
                $ticket->status = $newStatus;
                $ticket->save();
                $statuses = Helper::ticketStatuses();
                ResponseService::addActivity($ticket, $by, sprintf(
                    /* translators: an activity line shown after the person's name ("Sarah changed the status from New to Active"); 1: previous status, 2: new status */
                    __('changed the status from %1$s to %2$s', 'fluent-support'),
                    esc_html($statuses[$oldStatus] ?? $oldStatus),
                    esc_html($statuses[$newStatus] ?? $newStatus)
                ));
            }
            $changed['status'] = [$oldStatus, $ticket->status];
        } elseif ($changed) {
            $ticket->save();
        }

        // close() and reopen() fire it with their own hooks.
        if (isset($changed['status']) && $oldStatus !== 'closed' && $ticket->status !== 'closed') {
            do_action('fluent_support/ticket_status_changed', $ticket, $oldStatus, $ticket->status, $by);
        }
        if (isset($changed['priority'])) {
            do_action('fluent_support/ticket_priority_changed', $ticket, $changed['priority'][0], $changed['priority'][1], $by);
        }
        if ($changed) {
            do_action('fluent_support/ticket_updated', $ticket, $changed, $by);
        }

        return $changed;
    }

    /**
     * The values change() accepts, normalized.
     *
     * @throws ApiException 422 for an invalid value
     */
    private function checkChanges(array $changes)
    {
        if (array_key_exists('title', $changes)) {
            $changes['title'] = trim(sanitize_text_field((string) $changes['title']));
            if ($changes['title'] === '') {
                throw ApiException::invalid(__('Ticket title cannot be empty.', 'fluent-support'));
            }
        }
        if (array_key_exists('status', $changes) && !array_key_exists($changes['status'], Helper::ticketStatuses())) {
            throw ApiException::invalid(__('Invalid ticket status.', 'fluent-support'));
        }
        if (array_key_exists('priority', $changes) && !array_key_exists($changes['priority'], Helper::adminTicketPriorities())) {
            throw ApiException::invalid(__('Invalid ticket priority.', 'fluent-support'));
        }
        if (array_key_exists('client_priority', $changes) && !array_key_exists($changes['client_priority'], Helper::customerTicketPriorities())) {
            throw ApiException::invalid(__('Invalid client priority.', 'fluent-support'));
        }
        if (array_key_exists('product_id', $changes)) {
            // 0 or empty clears the product.
            $changes['product_id'] = (int) $changes['product_id'] ?: null;
            if ($changes['product_id'] && !Product::where('id', $changes['product_id'])->exists()) {
                throw ApiException::invalid(__('Invalid product.', 'fluent-support'));
            }
        }
        if (array_key_exists('customer_id', $changes)) {
            $changes['customer_id'] = (int) $changes['customer_id'];
            if (!Customer::where('id', $changes['customer_id'])->exists()) {
                throw ApiException::invalid(__('Invalid customer selected.', 'fluent-support'));
            }
        }

        return $changes;
    }

    /**
     * Close a ticket: the one write path for every close (admin, bulk, portal, MCP,
     * chat commands, workflows, auto-close). A reply that closes the ticket uses the
     * same steps from ResponseService. Nothing happens when it is already closed.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person Who closed it.
     * @param string $internalNote The activity line; "closed this ticket" when empty.
     * @param string $silently     'yes' skips the close hooks.
     * @param string $source       Marks the activity line; 'automatic' or 'automation' shows it
     *                             as a system event instead of something $person did.
     * @return \FluentSupport\App\Models\Ticket
     */
    public function close($ticket, $person, $internalNote = '', $silently = 'no', $source = '')
    {
        if ($ticket->status == 'closed') {
            return $ticket;
        }

        $oldStatus = $ticket->status;
        $this->applyClose($ticket, $person);
        $ticket->save();
        $this->logClose($ticket, $person, $internalNote, $source);

        // Silent only when asked ('yes' or true): a missing flag sends the close email.
        if ($silently !== 'yes' && $silently !== true) {
            $this->fireCloseHooks($ticket, $person, $oldStatus);
        }

        return $ticket;
    }

    /**
     * Set the closed fields. The caller saves the ticket.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @return void
     */
    public function applyClose($ticket, $person)
    {
        $ticket->status = 'closed';
        $ticket->resolved_at = current_time('mysql');
        $ticket->closed_by = $person->id;
        $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
    }

    /**
     * The "closed this ticket" activity line.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @param string $internalNote
     * @param string $source
     * @return void
     */
    public function logClose($ticket, $person, $internalNote = '', $source = '')
    {
        if (!$internalNote) {
            /* translators: an activity line; the ticket thread shows the person's name in front of it ("Sarah closed this ticket") */
            $internalNote = __('closed this ticket', 'fluent-support');
        }

        /*
         * The close activity line. Empty skips it.
         *
         * @param string $internalNote
         * @param object $ticket
         */
        $internalNote = apply_filters('fluent_support/ticket_close_internal_note', $internalNote, $ticket);

        /*
         * Source of the close activity line. 'automatic' or 'automation' shows it as a system
         * event rather than something the closing person did.
         *
         * @param string $source Empty for a close by the person.
         * @param object $ticket
         */
        $source = apply_filters('fluent_support/ticket_close_activity_source', $source, $ticket);

        if (!$internalNote) {
            return;
        }

        ResponseService::addActivity($ticket, $person, $internalNote, $source ? ['source' => $source] : []);
    }

    /**
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param \FluentSupport\App\Models\Person $person
     * @return void
     */
    public function fireCloseHooks($ticket, $person, $oldStatus = 'active')
    {
        /*
         * Fires when a ticket is closed.
         *
         * @since v1.0.0
         * @param object $ticket
         * @param object $person
         */
        do_action('fluent_support/ticket_closed', $ticket, $person);

        /*
         * Fires when a ticket is closed, per closer type.
         *
         * @since v1.0.0
         * @param object $ticket
         * @param object $person
         */
        do_action('fluent_support/ticket_closed_by_' . $person->person_type, $ticket, $person);

        do_action('fluent_support/ticket_status_changed', $ticket, $oldStatus, 'closed', $person);
    }

    /**
     * @param bool $deferHooks Skip the reopen hooks so a caller running inside a
     *                         transaction can fire them after commit (fireReopenHooks()).
     */
    public function reopen($ticket, $person, $deferHooks = false)
    {
        if ($ticket->status == 'closed') {
            $ticket->status = 'active';
            $ticket->waiting_since = current_time('mysql');
            $ticket->resolved_at = null;
            $ticket->save();

            // The activity line before the hooks, as close() does.
            /* translators: an activity line; the ticket thread shows the person's name in front of it ("Sarah reopened this ticket") */
            ResponseService::addActivity($ticket, $person, __('reopened this ticket', 'fluent-support'));

            if (!$deferHooks) {
                $this->fireReopenHooks($ticket, $person);
            }
        }

        return $ticket;
    }

    public function fireReopenHooks($ticket, $person)
    {
        /*
         * Fires when a closed ticket is reopened.
         *
         * @since v1.0.0
         * @param object $ticket
         * @param object $person
         */
        do_action('fluent_support/ticket_reopen', $ticket, $person);
        do_action('fluent_support/ticket_reopen_by_' . $person->person_type, $ticket, $person);

        do_action('fluent_support/ticket_status_changed', $ticket, 'closed', 'active', $person);
    }

    /**
     * Assign $ticket to $agent: the one write path for every assignment (admin,
     * bulk, MCP, workflows). Nothing happens when $agent already has the ticket.
     *
     * @param Ticket                               $ticket
     * @param \FluentSupport\App\Models\Agent       $agent
     * @param \FluentSupport\App\Models\Agent|null  $assigner Who assigned it; the assignee when nobody did (automation).
     * @param bool                                 $async    Queue the side effects (activity line, hooks) instead of running them now.
     * @return bool Whether the assignee changed.
     */
    public function assignAgent($ticket, $agent, $assigner = null, $async = false)
    {
        $previousAgentId = (int) $ticket->agent_id;

        if ($previousAgentId === (int) $agent->id) {
            return false;
        }

        $ticket->agent_id = $agent->id;
        $ticket->save();

        $assigner = $assigner ?: $agent;

        if ($async) {
            as_enqueue_async_action('fluent_support/async_agent_assigned_to_ticket', [
                $agent->id, $ticket->id, $assigner->id, $previousAgentId
            ], 'fluent-support');
        } else {
            $this->afterAgentAssigned($ticket, $assigner, $previousAgentId);
        }

        return true;
    }

    /**
     * Replaces the ticket's opening message, for a site admin removing sensitive
     * data (the caller checks who may). Writes an activity line without the old
     * text and fires fluent_support/ticket_content_updated ($ticket, $by).
     *
     * @param Ticket $ticket
     * @param string $content Already sanitized.
     * @param \FluentSupport\App\Models\Person $by
     * @return Ticket
     */
    public function updateContent($ticket, $content, $by)
    {
        $ticket->content = $content;
        // The old hash would still fingerprint the removed text.
        $ticket->content_hash = md5($content);
        $ticket->save();

        ResponseService::addActivity($ticket, $by, __('edited the opening message of this ticket', 'fluent-support'));

        do_action('fluent_support/ticket_content_updated', $ticket, $by);

        return $ticket;
    }

    /**
     * Takes the ticket off its agent: saves, writes the activity line (when a
     * person did it) and fires fluent_support/ticket_unassigned ($ticket,
     * $previousAgentId, $by).
     *
     * @param Ticket $ticket
     * @param \FluentSupport\App\Models\Person|null $by Null only for an automation with no agent
     *                                                   that does not close or reopen the ticket.
     * @return bool Whether the ticket had an agent.
     */
    public function unassign($ticket, $by = null)
    {
        $previousAgentId = (int) $ticket->agent_id;
        if (!$previousAgentId) {
            return false;
        }

        $ticket->agent_id = null;
        $ticket->save();

        if ($by) {
            /* translators: an activity line shown after the person's name ("Sarah unassigned this ticket") */
            ResponseService::addActivity($ticket, $by, __('unassigned this ticket', 'fluent-support'));
        }

        do_action('fluent_support/ticket_unassigned', $ticket, $previousAgentId, $by);

        return true;
    }

    /**
     * The side effects of an assignment that is already saved: the activity line,
     * fluent_support/ticket_agent_change and fluent_support/agent_assigned_to_ticket.
     * Call it directly only when the save happened elsewhere (inside a transaction).
     *
     * @param Ticket                         $ticket With its new agent_id saved.
     * @param \FluentSupport\App\Models\Agent $assigner
     * @param int                            $previousAgentId
     * @return void
     */
    public function afterAgentAssigned($ticket, $assigner, $previousAgentId)
    {
        $ticket->load('agent');

        $this->onAgentChange($ticket, $assigner);

        /*
         * Fires when a ticket is assigned to an agent.
         *
         * @since v1.0.0
         * @param object $agent
         * @param object $ticket
         * @param object $assigner
         * @param int    $previousAgentId
         */
        do_action('fluent_support/agent_assigned_to_ticket', $ticket->agent, $ticket, $assigner, (int) $previousAgentId);
    }

    /**
     * The activity line for an assignment, and fluent_support/ticket_agent_change.
     * Use assignAgent(); kept public for add-ons.
     */
    public function onAgentChange($ticket, $person)
    {
        do_action('fluent_support/ticket_agent_change', $ticket, $person);

        // The thread shows who did it in front of the line ("Sarah assigned this ticket to Tom").
        ResponseService::addActivity($ticket, $person, $ticket->agent->user_id !== $person->user_id
            /* translators: an activity line shown after the assigner's name; %s is the assigned agent's name */
            ? sprintf(__('assigned this ticket to %s', 'fluent-support'), '<strong>' . esc_html($ticket->agent->full_name) . '</strong>')
            /* translators: an activity line shown after the agent's name ("Sarah assigned this ticket to themselves") */
            : __('assigned this ticket to themselves', 'fluent-support'));

        return $person;
    }

    /**
     * Move the ticket's inline images and pending uploads into the ticket folder.
     *
     * @param array $data
     * @param array $disabledFields
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @param object $customer
     * @param bool $onBehalf Filed by a remote portal for the customer; their uploads are the customer's.
     * @return \FluentSupport\App\Models\Ticket $ticket
     * @since 1.5.7
     */
    public static function addTicketAttachments($data, $disabledFields, $ticket, $customer, $onBehalf = false)
    {
        Helper::tempImageMoveUploadDir($ticket->id, 'ticket-create', null, $customer->id);

        if (($attachmentsHashes = Arr::get($data, 'attachments')) && !in_array('file_upload', $disabledFields)) {
            $uploader = Helper::getCurrentAgent() ?: $customer;
            $uploaderIds = [$uploader->id];

            // A remote portal acting on_behalf uploads as the customer it files for.
            if ($onBehalf) {
                $uploaderIds[] = $customer->id;
            }

            $attachments = Attachment::whereIn('file_hash', $attachmentsHashes)
                ->where('status', 'in-active')
                ->whereNull('ticket_id')
                ->whereIn('person_id', $uploaderIds)
                ->get();

            if ($attachments->isEmpty()) {
                return $ticket;
            }

            foreach ($attachments as $attachment) {
                StorageManager::finalize($attachment, $ticket->id);

                if ($attachment->driver == 'local') {
                    $newFileInfo = UploadService::copyFileTicketFolder($attachment->file_path, $ticket->id);
                    if ($newFileInfo) {
                        $attachment->file_path = $newFileInfo['file_path'];
                        $attachment->full_url = $newFileInfo['url'];
                    }
                }

                $attachment->ticket_id = $ticket->id;
                $attachment->person_id = $customer->id;
                $attachment->status = 'active';
                $attachment->save();
            }

            $ticket->load('attachments');
        }

        return $ticket;
    }

    /**
     * Create a ticket for an agent, the API, MCP or a split: the shared steps of
     * insertTicket(), then the agent's own (created_by, agent-initiated outreach).
     * Used by TicketController::createTicket and ProTicketService::handleSplitTicket.
     *
     * @param array $ticketData Custom fields under custom_fields.
     * @param \FluentSupport\App\Models\Customer $customer
     * @param bool $silently Skip the created hooks; the caller fires them with
     *                       fireCreatedHooks() on this instance (a split, after its
     *                       transaction commits). Not for agent-initiated tickets.
     * @return \FluentSupport\App\Models\Ticket
     * @throws \Exception When the mailbox is unknown or restricted for the acting agent.
     */
    public function storeTicket($ticketData, $customer, $silently = false)
    {
        // The note needs an agent to write it; without one the text would become a visible ticket body.
        if (Arr::get($ticketData, 'start_with_note') === 'yes' && !Helper::getAgentByUserId()) {
            throw new \Exception(esc_html__('Only a support agent can start a ticket with an internal note.', 'fluent-support'), 403);
        }

        // Agents may attach files even when the customer ticket form disables uploads.
        $disabledFields = array_diff(apply_filters('fluent_support/disabled_ticket_fields', []), ['file_upload']);

        // Empty when no agent is logged in, so cron and API callers pass.
        $restrictedBoxes = (new AgentTicketAccess())->getRestrictedMailboxIds();

        // Hidden from the first insert, so the row never shows the note text to the customer,
        // even if a later step fails. The channel moves to the note.
        $isLogged = Arr::get($ticketData, 'start_with_note') === 'yes';
        if ($isLogged) {
            $loggedChannel = Arr::get($ticketData, 'source') ?: 'web';
            $ticketData['source'] = Ticket::SOURCE_AGENT_LOGGED;
        }

        $requestedAgentId = (int) Arr::get($ticketData, 'agent_id');
        list($createdTicket, $ticketData) = $this->insertTicket($ticketData, $customer, $disabledFields, $restrictedBoxes);

        $agent = Helper::getAgentByUserId();
        if ($agent) {
            $isAgentInitiated = Arr::get($ticketData, 'agent_initiated') === 'yes';
            $createdTicket->created_by = $agent->id;

            if ($isLogged) {
                // A call or visit logged for the record: the text becomes an internal note and the
                // customer sees nothing until the first response (Ticket::isHiddenFromCustomer()).
                // A filter may have changed the source; keep the ticket hidden.
                $createdTicket->source = Ticket::SOURCE_AGENT_LOGGED;

                $note = (new ResponseService())->createResponse([
                    'content'           => $createdTicket->content,
                    'conversation_type' => 'note',
                    'source'            => $loggedChannel,
                    // Content that sanitizes to nothing still gets the note, so the files below have a home.
                    'allow_empty_content' => true,
                ], $agent, $createdTicket, true)['response'];

                // Every ticket-level file (attachments and pasted images) moves to the note,
                // so the portal never shows them with the opening message.
                Attachment::where('ticket_id', $createdTicket->id)
                    ->whereNull('conversation_id')
                    ->update([
                        'person_id'       => $agent->id,
                        'conversation_id' => $note->id
                    ]);

                // The portal shows the body as the opening message, so it must not hold the note.
                /* translators: %s is the agent's name */
                $createdTicket->content = sprintf(__('%s logged this ticket', 'fluent-support'), esc_html($agent->full_name));
                $createdTicket->content_hash = md5($createdTicket->content);
            } elseif ($isAgentInitiated) {
                // The agent's first response below sends its own email. Scoped to this
                // ticket and removed after its creation hooks, so later tickets in the
                // same request (MCP, imports, workflows) still get their emails.
                $suppressCreatedEmails = function ($shouldSend, $channel, $type, $ticket = null) use ($createdTicket) {
                    if ($channel === 'email'
                        && $ticket && (int) $ticket->id === (int) $createdTicket->id
                        && in_array($type, ['ticket_created_email_to_customer', 'ticket_created_email_to_admin', 'ticket_created_by_agent_email_to_customer'], true)) {
                        return false;
                    }
                    return $shouldSend;
                };
                add_filter('fluent_support/should_send_notification', $suppressCreatedEmails, 10, 4);

                // The channel it was sent from moves to the response; the ticket's source marks it as outreach.
                $channel = $createdTicket->source ?: 'web';
                $createdTicket->source = Ticket::SOURCE_AGENT_INITIATED;

                // A placeholder only: both apps show the agent's response as the opening message.
                /* translators: %s is the agent's name */
                $initializedMessage = sprintf(__('%s started this conversation', 'fluent-support'), $agent->full_name);

                // createResponse() fills the smartcodes, once.
                $responseContent = $createdTicket->content;

                // Unset in memory when the column default supplied it on insert.
                $createdTicket->status = $createdTicket->status ?: 'new';

                // The agent's message is the first response, through the same path as
                // any reply (counters, filters); silently, since the outreach email below
                // replaces the reply email.
                $agentResponse = (new ResponseService())->createResponse([
                    'content'           => $responseContent,
                    'conversation_type' => 'response',
                    'source'            => $channel,
                ], $agent, $createdTicket, true)['response'];
                $responseContent = $agentResponse->content;

                if ($attachmentHashes = Arr::get($ticketData, 'attachments', [])) {
                    Attachment::where('ticket_id', $createdTicket->id)
                        ->whereIn('file_hash', $attachmentHashes)
                        ->where('status', 'active')
                        ->update([
                            'person_id'       => $agentResponse->person_id,
                            'conversation_id' => $agentResponse->id
                        ]);

                    $agentResponse->load('attachments');
                }

                // Opened by the agent: nothing waited for a first response.
                $createdTicket->first_response_time = 0;

                // The outreach email may render {{ticket.content}}, so expose the parsed body
                // during the hook. The ticket itself stores the "initialized" message.
                $createdTicket->content = $responseContent;

                do_action('fluent_support/agent_initiated_ticket_response', $agentResponse, $createdTicket, $agent);

                $createdTicket->content = $initializedMessage;

            }

            $createdTicket->save();
        }

        $this->pendingCreated[$createdTicket->id] = ['agent_id' => $requestedAgentId, 'by_agent' => true, 'agent' => $agent];
        if (!$silently) {
            $this->fireCreatedHooks($createdTicket, $customer);
        }

        if (!empty($suppressCreatedEmails)) {
            remove_filter('fluent_support/should_send_notification', $suppressCreatedEmails, 10);
        }

        return $createdTicket;
    }

    /**
     * Create a ticket on a customer's behalf: the portal, a remote portal acting
     * on_behalf, Fluent Forms, FluentCRM. Never marks it as agent-created, even when
     * the logged-in user is an agent. A mailbox that no longer exists falls back to
     * the default one.
     *
     * @param array $ticketData Custom fields under custom_fields.
     * @param \FluentSupport\App\Models\Customer $customer
     * @param bool $onBehalf An agent filing through a remote portal: their uploads are the
     *                       customer's, and they stay within their mailbox restrictions.
     * @return \FluentSupport\App\Models\Ticket
     * @param bool $silently Skip the created hooks; the caller fires them with
     *                       fireCreatedHooks() (email piping, after saving the attachments).
     * @throws \Exception When an agent acting on_behalf targets a restricted mailbox.
     */
    public function storeCustomerTicket($ticketData, $customer, $onBehalf = false, $silently = false)
    {
        if (!empty($ticketData['mailbox_id']) && !MailBox::find($ticketData['mailbox_id'])) {
            unset($ticketData['mailbox_id']);
        }

        $disabledFields = (array) apply_filters('fluent_support/disabled_ticket_fields', []);
        $restrictedBoxes = $onBehalf ? (new AgentTicketAccess())->getRestrictedMailboxIds() : [];

        $requestedAgentId = (int) Arr::get($ticketData, 'agent_id');
        list($ticket) = $this->insertTicket($ticketData, $customer, $disabledFields, $restrictedBoxes, $onBehalf);
        $this->pendingCreated[$ticket->id] = ['agent_id' => $requestedAgentId, 'by_agent' => false, 'agent' => Helper::getAgentByUserId()];

        if (!$silently) {
            $this->fireCreatedHooks($ticket, $customer);
        }

        return $ticket;
    }

    /**
     * The created hooks for a ticket from storeTicket() or storeCustomerTicket() on
     * this instance: fluent_support/ticket_created, ticket_created_behalf_of_customer
     * (storeTicket() only), and the assignment steps when the data named an agent.
     *
     * @param Ticket $ticket
     * @param \FluentSupport\App\Models\Customer $customer
     * @return void
     */
    public function fireCreatedHooks($ticket, $customer)
    {
        $pending = Arr::get($this->pendingCreated, $ticket->id, ['agent_id' => 0, 'by_agent' => false, 'agent' => null]);
        unset($this->pendingCreated[$ticket->id]);

        do_action('fluent_support/ticket_created', $ticket, $customer);
        if ($pending['by_agent']) {
            do_action('fluent_support/ticket_created_behalf_of_customer', $ticket, $customer, $pending['agent']);
        }
        $this->afterCreatedWithAgent($ticket, (int) $pending['agent_id'], $pending['agent']);
    }

    /**
     * A ticket created with an agent in its data (MCP, the PHP API, add-ons): the
     * same activity line and hooks as any assignment, after ticket_created.
     */
    private function afterCreatedWithAgent($ticket, $requestedAgentId, $by)
    {
        if ($requestedAgentId && (int) $ticket->agent_id === $requestedAgentId) {
            $ticket->load('agent');
            $this->afterAgentAssigned($ticket, $by ?: $ticket->agent, 0);
        }
    }

    /**
     * The steps every new ticket shares: mailbox, sanitizing, the create_ticket_data
     * filter, before_ticket_create, the insert, attachments and custom fields. The
     * caller fires ticket_created.
     *
     * @param array $ticketData
     * @param \FluentSupport\App\Models\Customer $customer
     * @param array $disabledFields  Ticket form fields whose input is ignored (file_upload).
     * @param array $restrictedBoxes Mailboxes the ticket may not go into (the acting agent's).
     * @param bool $onBehalf
     * @return array [Ticket, array] The ticket and the data after the create_ticket_data filter.
     * @throws \Exception
     */
    private function insertTicket($ticketData, $customer, array $disabledFields, array $restrictedBoxes, $onBehalf = false)
    {
        if (empty($ticketData['mailbox_id'])) {
            $mailbox = Helper::getDefaultMailBox();
            if ($mailbox) {
                $ticketData['mailbox_id'] = $mailbox->id;
            }
        } else {
            $mailbox = MailBox::findOrFail($ticketData['mailbox_id']);
        }

        $this->assertMailboxAllowed($ticketData, $restrictedBoxes);

        if (!empty($ticketData['product_id'])) {
            $ticketData['product_source'] = 'local';
        }

        // Already unslashed at the request boundary. Don't unslash again or literal backslashes are lost.
        $ticketData['title'] = sanitize_text_field($ticketData['title']);
        $ticketData['content'] = wp_kses_post(Helper::decodeNonMarkupEntities($ticketData['content']));

        if (!empty($ticketData['priority'])) {
            $ticketData['priority'] = sanitize_text_field($ticketData['priority']);
        }

        if (!empty($ticketData['client_priority'])) {
            $ticketData['client_priority'] = sanitize_text_field($ticketData['client_priority']);
        }

        $ticketData = apply_filters('fluent_support/create_ticket_data', $ticketData, $customer);

        // Check again: a filter may have routed the ticket into a restricted box.
        $this->assertMailboxAllowed($ticketData, $restrictedBoxes);

        do_action('fluent_support/before_ticket_create', $ticketData, $customer);

        $createdTicket = Ticket::create($ticketData);

        self::addTicketAttachments($ticketData, $disabledFields, $createdTicket, $customer, $onBehalf);

        if (defined('FLUENTSUPPORTPRO') && !empty($ticketData['custom_fields'])) {
            $createdTicket->syncCustomFields($ticketData['custom_fields']);
        }

        return [$createdTicket, $ticketData];
    }

    /**
     * Adds ticket tags it doesn't have yet (other ids are ignored) and fires
     * fluent_support/ticket_tag_added ($tagId, $ticket) for each one added.
     *
     * @param Ticket $ticket
     * @param int|int[] $tagIds
     * @return int[] The tag ids added.
     */
    public function addTags($ticket, $tagIds)
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', (array) $tagIds))));
        $added = $tagIds ? array_diff(
            array_map('intval', TicketTag::whereIn('id', $tagIds)->pluck('id')->all()),
            $this->pivotIds($ticket, 'ticket_tag')
        ) : [];

        foreach ($added as $tagId) {
            TagPivot::create(['tag_id' => $tagId, 'source_id' => $ticket->id, 'source_type' => 'ticket_tag']);
            /*
             * Fires after a tag is added to a ticket.
             *
             * @since v1.0.0
             * @param int    $tagId
             * @param object $ticket
             */
            do_action('fluent_support/ticket_tag_added', $tagId, $ticket);
        }
        $ticket->unsetRelation('tags');

        return array_values($added);
    }

    /**
     * Removes tags the ticket has and fires fluent_support/ticket_tag_removed
     * ($tagId, $ticket) for each one removed.
     *
     * @param Ticket $ticket
     * @param int|int[] $tagIds
     * @return int[] The tag ids removed.
     */
    public function removeTags($ticket, $tagIds)
    {
        $removed = array_values(array_intersect(array_map('intval', (array) $tagIds), $this->pivotIds($ticket, 'ticket_tag')));

        foreach ($removed as $tagId) {
            TagPivot::where('source_type', 'ticket_tag')->where('source_id', $ticket->id)->where('tag_id', $tagId)->delete();
            /*
             * Fires after a tag is removed from a ticket.
             *
             * @since v1.0.0
             * @param int    $tagId
             * @param object $ticket
             */
            do_action('fluent_support/ticket_tag_removed', $tagId, $ticket);
        }
        $ticket->unsetRelation('tags');

        return $removed;
    }

    /**
     * Adds agents who don't watch the ticket yet (other ids are ignored); fires
     * fluent_support/ticket_watchers_added ($ticket, $agentIds, $by) when any were added.
     *
     * @param Ticket $ticket
     * @param int[] $agentIds
     * @param \FluentSupport\App\Models\Person|null $by
     * @return int[] The agent ids added.
     */
    public function addWatchers($ticket, array $agentIds, $by = null)
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        $added = $agentIds ? array_values(array_diff(
            array_map('intval', Agent::whereIn('id', $agentIds)->pluck('id')->all()),
            $this->pivotIds($ticket, 'ticket_watcher')
        )) : [];

        foreach ($added as $agentId) {
            TagPivot::create(['tag_id' => $agentId, 'source_id' => $ticket->id, 'source_type' => 'ticket_watcher']);
        }
        if ($added) {
            do_action('fluent_support/ticket_watchers_added', $ticket, $added, $by);
        }

        return $added;
    }

    /**
     * Removes agents who watch the ticket; fires fluent_support/ticket_watchers_removed
     * ($ticket, $agentIds, $by) when any were removed.
     *
     * @return int[] The agent ids removed.
     */
    public function removeWatchers($ticket, array $agentIds, $by = null)
    {
        $removed = array_values(array_intersect(array_map('intval', $agentIds), $this->pivotIds($ticket, 'ticket_watcher')));

        if ($removed) {
            TagPivot::where('source_type', 'ticket_watcher')->where('source_id', $ticket->id)->whereIn('tag_id', $removed)->delete();
            do_action('fluent_support/ticket_watchers_removed', $ticket, $removed, $by);
        }

        return $removed;
    }

    /**
     * Makes the watchers exactly $agentIds.
     *
     * @return array ['added' => int[], 'removed' => int[]]
     */
    public function syncWatchers($ticket, array $agentIds, $by = null)
    {
        $agentIds = array_map('intval', $agentIds);

        return [
            'removed' => $this->removeWatchers($ticket, array_diff($this->pivotIds($ticket, 'ticket_watcher'), $agentIds), $by),
            'added'   => $this->addWatchers($ticket, $agentIds, $by),
        ];
    }

    /**
     * The tag ids (or watcher agent ids) on a ticket, read fresh.
     */
    private function pivotIds($ticket, $sourceType)
    {
        return array_map('intval', TagPivot::where('source_type', $sourceType)->where('source_id', $ticket->id)->pluck('tag_id')->all());
    }

    /**
     * Block ticket creation in a business box the acting agent is restricted from.
     *
     * @param array $ticketData
     * @param array $restrictedBoxes Already int-normalized by AgentTicketAccess
     * @return void
     * @throws ApiException 403.
     */
    protected function assertMailboxAllowed($ticketData, $restrictedBoxes)
    {
        if (empty($restrictedBoxes) || empty($ticketData['mailbox_id'])) {
            return;
        }

        if (in_array((int) $ticketData['mailbox_id'], $restrictedBoxes, true)) {
            throw ApiException::forbidden(
                esc_html__('Sorry, you do not have permission to create a ticket in this business box', 'fluent-support')
            );
        }
    }

    // No auth check here: callers handle it, and it also runs with no current user (API, GDPR erasure, cron).
    public function deleteTicket($ticket, $agent = null, $silently = false)
    {
        if (!$agent) {
            $agent = Helper::getAgentByUserId();
        }

        $ticketData = [
            'id'    => $ticket->id,
            'title' => $ticket->title
        ];

        // Before the rows go: its listeners clean up what belongs to the ticket.
        do_action('fluent_support/deleting_ticket', $ticket);
        $ticket->delete();

        if (!$silently) {
            $this->fireDeletedHooks($agent, $ticketData);
        }

        return $ticketData;
    }

    /**
     * fluent_support/ticket_deleted ($agent, ['id', 'title']), for a delete made
     * with $silently (a merge, after its transaction commits).
     */
    public function fireDeletedHooks($agent, array $ticketData)
    {
        do_action('fluent_support/ticket_deleted', $agent, $ticketData);
    }

}
