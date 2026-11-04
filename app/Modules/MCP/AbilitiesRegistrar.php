<?php

namespace FluentSupport\App\Modules\MCP;

use FluentSupport\App\Modules\MCP\Support\AbilityGuard;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\MCP\Tools\ResponseTools;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;
use FluentSupport\App\Modules\MCP\Tools\CustomerTools;

/**
 * Tool descriptions are English and not translatable: the model reads them, and they
 * name exact parameters and values a translation could break. Labels stay
 * translatable because people see them in the admin and in client approval prompts.
 */
class AbilitiesRegistrar
{
    /** Tools that need Fluent Support Pro; the server only lists them when Pro is active. */
    const PRO_ONLY = [
        'fluent-support/merge-tickets',
        'fluent-support/split-ticket',
    ];

    private static $definitions = null;

    public static function getDefinitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            'fluent-support/list-tickets' => [
                'label'       => __('List Support Tickets', 'fluent-support'),
                'description' => 'List and filter tickets. Each row has a content preview, customer, agent, product, tags, waiting_on and response_count, so you can triage a page without opening tickets. For triage: waiting_for_reply=true, sort_by=waiting_since, order=ASC. For stale tickets: waiting_on=customer with the same sort. customer_summary appears only when an integration provides it. For one customer\'s tickets, use get-customer-tickets. When you reply to or close tickets during a multi-page sweep, pass meta.paging.next_cursor as cursor instead of incrementing page, because acting on tickets reorders the list.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'               => ['type' => 'string', 'enum' => ['open', 'closed', 'new', 'active', 'all'], 'description' => 'Filter by status: open (new and active), new, active, closed or all'],
                        'agent_id'             => ['type' => 'integer', 'description' => 'Filter by assigned agent ID'],
                        'product_id'           => ['type' => 'integer', 'description' => 'Filter by product ID'],
                        'mailbox_id'           => ['type' => 'integer', 'description' => 'Filter by mailbox ID'],
                        'priority'             => ['type' => 'string', 'enum' => ['normal', 'medium', 'critical'], 'description' => 'Filter by the priority agents set'],
                        'client_priority'      => ['type' => 'string', 'enum' => ['normal', 'medium', 'critical'], 'description' => 'Filter by the priority the customer chose when submitting the ticket'],
                        'tags'                 => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Filter by tag IDs'],
                        'waiting_for_reply'    => ['type' => 'boolean', 'description' => 'Only tickets waiting on an agent reply (waiting_on="agent"). Implies status=open unless status is set.'],
                        'waiting_on'           => ['type' => 'string', 'enum' => ['agent', 'customer'], 'description' => 'Only open tickets waiting on an agent (same as waiting_for_reply=true) or on the customer. waiting_on=customer with sort_by=waiting_since and order=ASC lists the tickets idle longest since our reply.'],
                        'unassigned'           => ['type' => 'boolean', 'description' => 'Only show tickets with no agent assigned'],
                        'needs_first_response' => ['type' => 'boolean', 'description' => 'Only show open tickets that have never had a reply (response_count=0). Combine with unassigned=true for a new-and-untouched queue view.'],
                        'customer_id'          => ['type' => 'integer', 'description' => 'Filter by customer ID'],
                        'search'               => ['type' => 'string', 'description' => 'Full-text search across ticket title, content, conversation messages, and customer name/email'],
                        'created_after'        => ['type' => 'string', 'description' => 'Only tickets created on or after this date (YYYY-MM-DD or ISO 8601). A bare date is interpreted as midnight in the site\'s configured timezone (Settings > General), not UTC; an explicit UTC offset in the value is not converted before comparison, so omit it to match site time.'],
                        'created_before'       => ['type' => 'string', 'description' => 'Only tickets created on or before this date (YYYY-MM-DD or ISO 8601). A bare date is interpreted as midnight in the site\'s configured timezone (Settings > General), not UTC; an explicit UTC offset in the value is not converted before comparison, so omit it to match site time.'],
                        'page'                 => ['type' => 'integer', 'default' => 1, 'description' => 'Page number'],
                        'per_page'             => ['type' => 'integer', 'default' => 15, 'description' => 'Results per page (max 100)'],
                        'sort_by'              => ['type' => 'string', 'default' => 'id', 'enum' => ['id', 'created_at', 'updated_at', 'waiting_since', 'status', 'priority', 'client_priority', 'response_count'], 'description' => 'Sort column'],
                        'sort_type'            => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC', 'description' => 'Sort direction. Alias: order'],
                        'order'                => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'description' => 'Alias for sort_type'],
                        'cursor'               => ['type' => 'string', 'description' => 'Opaque continuation token from a previous response\'s meta.paging.next_cursor. When set, returns rows strictly after that position using the same sort_by/sort_type — stable even if earlier rows\' waiting_since changed. Overrides page when both are set.'],
                    ],
                ],
                'execute_callback' => [TicketTools::class, 'listTickets'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-ticket' => [
                'label'       => __('Get Ticket Details', 'fluent-support'),
                'description' => 'Get a ticket with its conversation, the customer\'s history and previous tickets, custom fields, and purchase and CRM data from integrations. Content is Markdown; the customer\'s opening message is in content. The conversation comes in pages of 50 messages: page 1 holds the latest 50, and each page reads oldest to newest, like a chat. Use response_page=2 for the 50 before those when responses_meta.has_more is true, or since_response_id to get only messages newer than one you have read. Response types: response (seen by the customer; person.type says who wrote it), note (agents only), internal_info (activity lines such as assignments and closures), draft_response (unsent). Attachment links expire at url_expires_at. reply_email is the email the customer gets when you reply from this inbox.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'         => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'with_responses'    => ['type' => 'boolean', 'default' => true, 'description' => 'Include conversation thread (default: true). Set false to get only ticket metadata.'],
                        'response_page'     => ['type' => 'integer', 'default' => 1, 'description' => 'Page of the conversation: 1 is the latest 50 messages, 2 the 50 before those. Use it when responses_meta.has_more is true.'],
                        'since_response_id' => ['type' => 'integer', 'description' => 'Only return responses newer than this ID. Pass responses_meta.latest_response_id from your last call to read only new messages.'],
                        'response_id'       => ['type' => 'integer', 'description' => 'Return only this response, e.g. to read a truncated one in full.'],
                        'max_chars_per_response' => ['type' => 'integer', 'description' => 'Truncate each body, including the opening message, to this many characters. Truncated entries have truncated=true and full_length. Default: no limit.'],
                        'with_integrations' => ['type' => ['boolean', 'array'], 'items' => ['type' => 'string'], 'default' => true, 'description' => 'Integration data such as purchase history. true (default), false to skip, or a list of widget keys (the keys of integrations, e.g. ["woo_purchases"]) to get only those.'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [TicketTools::class, 'getTicket'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/create-ticket' => [
                'label'       => __('Create Support Ticket', 'fluent-support'),
                'description' => 'Create a new support ticket on behalf of a customer. Requires title, content, and customer_email. Automatically creates the customer if the email is new. By default the ticket is logged on the customer\'s behalf ("created by agent") — the customer gets a notification that a ticket was opened for them. Set agent_initiated=true only when the agent is proactively reaching out to start a conversation (e.g. billing follow-up, onboarding, account review); then the content is sent to the customer as the agent\'s first reply instead. Use get-support-context first to get valid product_id, mailbox_id, and agent_id values.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'title'               => ['type' => 'string', 'description' => 'Ticket title/subject'],
                        'content'             => ['type' => 'string', 'description' => 'Ticket message content. Format controlled by content_format (default: markdown).'],
                        'content_format'      => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                        'customer_email'      => ['type' => 'string', 'format' => 'email', 'description' => 'Customer email address'],
                        'customer_first_name' => ['type' => 'string', 'description' => 'Customer first name (for new customers)'],
                        'customer_last_name'  => ['type' => 'string', 'description' => 'Customer last name (for new customers)'],
                        'priority'            => ['type' => 'string', 'enum' => ['normal', 'medium', 'critical'], 'description' => 'Ticket priority'],
                        'product_id'          => ['type' => 'integer', 'description' => 'Product ID'],
                        'mailbox_id'          => ['type' => 'integer', 'description' => 'Mailbox ID'],
                        'agent_id'            => ['type' => 'integer', 'description' => 'Assign to agent ID'],
                        'agent_initiated'     => ['type' => 'boolean', 'description' => 'Optional, default false. Set true ONLY when the agent is proactively starting the conversation (the content is then sent to the customer as the agent\'s first reply, and the "ticket created by agent" email is skipped). Leave false/unset to log a ticket on the customer\'s behalf.'],
                    ],
                    'required' => ['title', 'content', 'customer_email'],
                ],
                'execute_callback' => [TicketTools::class, 'createTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => false, 'openWorldHint' => true],
            ],

            'fluent-support/reply-to-ticket' => [
                'label'       => __('Reply to Ticket', 'fluent-support'),
                'description' => 'Send a customer-visible reply. An unassigned ticket is assigned to you unless assignee_id is set. A closed ticket is reopened by the reply (reopened=true). Set draft=true when a human should review the reply before it is sent. Set informational=true for a holding message that does not answer the customer. Attach files with attachments. Before writing, read the reply_email template on get-ticket (templates are per inbox) and leave out any greeting, sign-off or signature it already has. To close without replying, use close-ticket.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'content'        => ['type' => 'string', 'description' => 'Response message content (visible to customer). Format controlled by content_format (default: markdown). Not required if saved_reply_id is provided.'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                        'saved_reply_id' => ['type' => 'integer', 'description' => 'Use a saved reply by ID (use list-saved-replies to find IDs). Content overrides saved reply if both provided.'],
                        'assignee_id'    => ['type' => 'integer', 'description' => 'Optional. Assign the ticket to this agent as part of the reply (requires the "Assign Agents" permission; use get-support-context for agent IDs). If omitted and the ticket is unassigned, it is auto-assigned to you.'],
                        'close_ticket'   => ['type' => 'boolean', 'default' => false, 'description' => 'Close the ticket after replying. A closed ticket stays closed. Not allowed with draft.'],
                        'draft'          => ['type' => 'boolean', 'default' => false, 'description' => 'Save as an unsent draft for a human to approve in the ticket view. Status and assignee are unchanged; close_ticket and assignee_id are not allowed.'],
                        'informational'  => ['type' => 'boolean', 'default' => false, 'description' => 'A holding reply that does not answer the customer yet, such as "I have passed this to our billing team": it is sent as usual, but waiting_on stays "agent". Not allowed with draft.'],
                        'attachments'    => self::attachmentsSchema(),
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [ResponseTools::class, 'replyToTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => false, 'openWorldHint' => true],
            ],

            'fluent-support/close-ticket' => [
                'label'       => __('Close Ticket', 'fluent-support'),
                'description' => 'Close a ticket, optionally with a final reply to the customer and an internal note in the same call. Use this instead of reply-to-ticket when closing is the goal. Closing a ticket that is already closed changes nothing and sends nothing, so retrying is safe.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'reply_content'  => ['type' => 'string', 'description' => 'Optional final reply visible to the customer (sent before closing). Format controlled by content_format (default: markdown).'],
                        'internal_note'  => ['type' => 'string', 'description' => 'Optional internal note for the closure (visible to agents only). Format controlled by content_format (default: markdown).'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of reply_content and internal_note: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                        'silent'         => ['type' => 'boolean', 'default' => false, 'description' => 'Skip the "ticket closed" email and the automations that run on closing, like "Close Silently" in the ticket view. Use it whenever the customer should not get a closing email, for example spam, duplicates or a ticket already resolved elsewhere. A reply_content in the same call is still sent.'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [TicketTools::class, 'closeTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => true, 'openWorldHint' => true],
            ],

            'fluent-support/update-ticket' => [
                'label'       => __('Update Ticket Properties', 'fluent-support'),
                'description' => 'Change a ticket: title, priority, status, product, mailbox or assigned agent, several at once. Assigning notifies the agent as in the ticket view. Setting status=active on a closed ticket reopens it. To close a ticket, use close-ticket. IDs come from get-support-context.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'  => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'title'      => ['type' => 'string', 'description' => 'New ticket title'],
                        'priority'   => ['type' => 'string', 'enum' => ['normal', 'medium', 'critical'], 'description' => 'New priority'],
                        'status'     => ['type' => 'string', 'enum' => ['new', 'active'], 'description' => 'New status: new or active. On a closed ticket, active reopens it. To close a ticket, use close-ticket.'],
                        'product_id' => ['type' => 'integer', 'description' => 'New product ID (use get-support-context to find IDs)'],
                        'mailbox_id' => ['type' => 'integer', 'description' => 'Move the ticket to this mailbox (use get-support-context to find IDs, requires the "Manage Overall Settings" permission)'],
                        'agent_id'   => ['type' => 'integer', 'description' => 'Assign to agent ID (use get-support-context to find IDs, requires the "Assign Agents" permission)'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [TicketTools::class, 'updateTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/delete-ticket' => [
                'label'       => __('Delete Ticket', 'fluent-support'),
                'description' => 'Permanently delete a ticket with all its messages, attachments and data. This cannot be undone. To remove spam or duplicates from the queue, close them with close-ticket silent=true instead; delete only when a person asks you to.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer', 'description' => 'The ticket ID'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [TicketTools::class, 'deleteTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-ticket-activity' => [
                'label'       => __('Get Ticket Activity Log', 'fluent-support'),
                'description' => 'Get the activity timeline for a ticket — who did what and when. Shows ticket creation, responses, status changes, agent assignments, and closures. Use to understand ticket history without reading the full conversation.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer', 'description' => 'The ticket ID'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [TicketTools::class, 'getTicketActivity'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/merge-tickets' => [
                'label'       => __('Merge Tickets', 'fluent-support'),
                'description' => 'Merge duplicate tickets into one. All conversations and attachments from the merged tickets are moved into the target ticket. The merged tickets are deleted.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'target_ticket_id' => ['type' => 'integer', 'description' => 'The ticket to merge into (this one survives)'],
                        'merge_ticket_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ticket IDs to merge into the target (these get deleted)'],
                    ],
                    'required' => ['target_ticket_id', 'merge_ticket_ids'],
                ],
                'execute_callback' => [TicketTools::class, 'mergeTickets'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => false, 'openWorldHint' => false],
            ],

            'fluent-support/split-ticket' => [
                'label'       => __('Split Ticket', 'fluent-support'),
                'description' => 'Move one message out of a ticket into a new ticket, for example when a customer raises a second, unrelated issue in the same thread. The message and its attachments leave the original ticket and become the opening message of the new one; both tickets get an activity line. The new ticket goes to the same mailbox unless you pass mailbox_id, and the site\'s rules for new tickets (assignment, automations) apply to it; the original keeps its status. notify_customer=false (the default) holds back the inbox\'s new-ticket emails to the customer; automations set up for new tickets still run. Only messages of type response can be split.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket that holds the message'],
                        'response_id'    => ['type' => 'integer', 'description' => 'The message to move (a response ID from get-ticket)'],
                        'title'          => ['type' => 'string', 'description' => 'Title of the new ticket. Default: "Re: " and the original title.'],
                        'content'        => ['type' => 'string', 'description' => 'Opening message of the new ticket. Default: the moved message as written.'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content, if provided.'],
                        'customer_id'    => ['type' => 'integer', 'description' => 'Customer of the new ticket. Default: the message author when a customer wrote it, else the ticket customer.'],
                        'mailbox_id'     => ['type' => 'integer', 'description' => 'Mailbox of the new ticket. Default: the original ticket mailbox.'],
                        'product_id'     => ['type' => 'integer', 'description' => 'Product of the new ticket. Default: the original ticket product.'],
                        'notify_customer' => ['type' => 'boolean', 'default' => false, 'description' => 'Send the customer the inbox\'s new-ticket emails for the new ticket. Default false: the split is silent for the customer.'],
                    ],
                    'required' => ['ticket_id', 'response_id'],
                ],
                'execute_callback' => [TicketTools::class, 'splitTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => false, 'openWorldHint' => true],
            ],

            'fluent-support/add-internal-note' => [
                'label'       => __('Add Internal Note', 'fluent-support'),
                'description' => 'Add an internal note to a ticket, visible to agents only. To notify an agent, mention them as @[agent_id:Name], e.g. @[12:Sam Lee], with the ID from get-support-context. Attach files with attachments. The result\'s mentions says who you mentioned and whether they get notified.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'content'        => ['type' => 'string', 'description' => 'Note content. Format controlled by content_format (default: markdown).'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                        'attachments'    => self::attachmentsSchema(),
                    ],
                    'required' => ['ticket_id', 'content'],
                ],
                'execute_callback' => [ResponseTools::class, 'addInternalNote'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => false, 'openWorldHint' => false],
            ],

            'fluent-support/update-response' => [
                'label'       => __('Update Draft or Note', 'fluent-support'),
                'description' => 'Replace the text of a draft reply or internal note you wrote. Nothing is sent; to send a draft, use publish-draft-reply. Sent replies and activity lines (type internal_info) cannot be changed.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'response_id'    => ['type' => 'integer', 'description' => 'ID of your draft (type draft_response) or note (type note), from get-ticket.'],
                        'content'        => ['type' => 'string', 'description' => 'New content. Format controlled by content_format (default: markdown).'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                    ],
                    'required' => ['ticket_id', 'response_id', 'content'],
                ],
                'execute_callback' => [ResponseTools::class, 'updateResponse'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/delete-response' => [
                'label'       => __('Delete Draft or Note', 'fluent-support'),
                'description' => 'Delete a draft reply or internal note you wrote. Sent replies and activity lines (type internal_info) cannot be deleted.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'   => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'response_id' => ['type' => 'integer', 'description' => 'ID of your draft (type draft_response) or note (type note), from get-ticket.'],
                    ],
                    'required' => ['ticket_id', 'response_id'],
                ],
                'execute_callback' => [ResponseTools::class, 'deleteResponse'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/publish-draft-reply' => [
                'label'       => __('Publish Draft Reply', 'fluent-support'),
                'description' => 'Send a draft reply to the customer, the same as Approve in the ticket view. Optionally pass content to edit it first. The email goes out under the name of the agent who wrote the draft (From name and signature), matching the thread. Publishing does not reopen a closed ticket.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'response_id'    => ['type' => 'integer', 'description' => 'The draft reply ID (type draft_response in get-ticket)'],
                        'content'        => ['type' => 'string', 'description' => 'Optional. Replace the draft content before sending. Format controlled by content_format (default: markdown).'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                    ],
                    'required' => ['ticket_id', 'response_id'],
                ],
                'execute_callback' => [ResponseTools::class, 'publishDraftReply'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => false, 'openWorldHint' => true],
            ],

            'fluent-support/tag-ticket' => [
                'label'       => __('Add or Remove Ticket Tags', 'fluent-support'),
                'description' => 'Add or remove tags on a ticket. Pass existing tag IDs, or tag names, which are matched to existing tags ignoring case; tags are created by people in the admin, so an unknown name is refused with the list of tags. For many tickets at once, use bulk-action with action=tag.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id'      => ['type' => 'integer', 'description' => 'The ticket ID'],
                        'add_tag_ids'    => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Existing tag IDs to add'],
                        'add_tag_names'  => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 20, 'description' => 'Names of existing tags to add, matched ignoring case (max 20).'],
                        'remove_tag_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Tag IDs to remove'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [ManagementTools::class, 'tagTicket'],
                'annotations'      => ['readonly' => false, 'destructive' => false, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-customer-tickets' => [
                'label'       => __('Get Customer Tickets', 'fluent-support'),
                'description' => 'Get a customer\'s tickets by email, name, or ID — no need to search for the customer first. Email is the most reliable. Best tool when someone asks "show me tickets from [customer]".',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'email'       => ['type' => 'string', 'format' => 'email', 'description' => 'Customer email address (recommended — exact match)'],
                        'name'        => ['type' => 'string', 'description' => 'Customer name (partial match on first/last name)'],
                        'customer_id' => ['type' => 'integer', 'description' => 'Customer ID (if already known)'],
                        'status'      => ['type' => 'string', 'enum' => ['open', 'closed', 'new', 'active', 'all'], 'description' => 'Filter by status: open (new and active), new, active, closed or all'],
                        'page'        => ['type' => 'integer', 'default' => 1],
                        'per_page'    => ['type' => 'integer', 'default' => 15],
                    ],
                ],
                'execute_callback' => [CustomerTools::class, 'getCustomerTickets'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-support-context' => [
                'label'       => __('Get Support Context', 'fluent-support'),
                'description' => 'START HERE: call this once at the start of a session. Returns your agent identity, whether your replies are sent or saved as drafts (replies), your signature, your queue, tickets that need attention, reference data with IDs (agents, products, mailboxes, tags), ticket counts, valid priority and status values, and guidelines. views lists the ticket queues (assigned to you, unassigned, waiting for reply, each open priority, each status) with their counts and the list-tickets arguments that open each one; get-ticket-views refreshes them. Each mailbox shows the templates of its reply and outreach emails, and you.signature shows your signature. my_queue.awaiting_your_reply lists your tickets waiting on you, oldest first (first 10); my_queue.awaiting_customer lists the ones waiting on the customer (first 5); each has a _total and a _truncated flag. needs_attention lists open tickets that are unassigned or critical, plus the ones waiting longest on an agent and those waiting on an agent past the SLA targets. Rows carry waiting_on and response_count, so open a ticket only when you need its conversation.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
                'execute_callback' => [ManagementTools::class, 'getSupportContext'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-ticket-views' => [
                'label'       => __('Get Ticket Views', 'fluent-support'),
                'description' => 'Get current ticket counts for each queue, the same views as the ticket list sidebar: assigned to you, unassigned, waiting for reply, each open priority (critical, medium, normal) and each status (open, new, active, closed, all). Each view has a count and list_tickets, the exact list-tickets arguments that return its tickets. Counts only include tickets you can see. Use it to refresh counts during a session; get-support-context includes the same views.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
                'execute_callback' => [ManagementTools::class, 'getTicketViews'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/list-saved-replies' => [
                'label'       => __('List Saved Replies', 'fluent-support'),
                'description' => 'List your saved/canned replies. Only returns replies you created. Optionally filter by search text or product_id. Use the returned ID with reply-to-ticket\'s saved_reply_id param. Content may contain placeholders like {{customer.first_name}} or {{agent.first_name}}, expanded automatically when the reply is sent.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'     => ['type' => 'string', 'description' => 'Search in title and content'],
                        'product_id' => ['type' => 'integer', 'description' => 'Filter by product ID'],
                    ],
                ],
                'execute_callback' => [ResponseTools::class, 'listSavedReplies'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-support-insights' => [
                'label'       => __('Get Support Insights', 'fluent-support'),
                'description' => 'Get team performance for a period: tickets created and closed, first-response and resolution times, and per-agent numbers. from and to give the exact window. agent_performance lists agents by open tickets, most first, up to 50 (agent_performance_total gives the full count); pass agent_id for one agent. waiting and each agent\'s open tickets describe right now, not the period. waiting is split into waiting_on_agent (a reply is owed) and waiting_on_customer (we answered and the customer has not come back); report them separately, since a blended average mostly measures customer silence. Durations read like "2h 30m". For simple ticket counts, use get-support-context.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => ['24h', '7d', '30d', '90d'], 'default' => '7d', 'description' => 'Time period for volume and response time stats'],
                        'agent_id' => ['type' => 'integer', 'description' => 'Only this agent in agent_performance'],
                    ],
                ],
                'execute_callback' => [ManagementTools::class, 'getSupportInsights'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/search-customers' => [
                'label'       => __('Search Customers', 'fluent-support'),
                'description' => 'Search for customers by name or email. Returns customer profiles with location and account age. Use get-customer-tickets if you already know which customer and want their tickets. Use this when you need to find or identify a customer first.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'   => ['type' => 'string', 'description' => 'Search by name or email (partial match)'],
                        'page'     => ['type' => 'integer', 'default' => 1],
                        'per_page' => ['type' => 'integer', 'default' => 15, 'description' => 'Results per page (max 100)'],
                    ],
                ],
                'execute_callback' => [CustomerTools::class, 'searchCustomers'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/update-customer' => [
                'label'       => __('Update Customer', 'fluent-support'),
                'description' => 'Change a customer\'s profile: name, title, note or address. Only the fields you pass change; an empty string clears a field, except first_name. The email address cannot be changed here: if a customer asks to change it, or to send their emails somewhere else, leave an internal note and ask a person on your team to do it.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'customer_id'    => ['type' => 'integer', 'description' => 'The customer ID (from search-customers or get-ticket)'],
                        'first_name'     => ['type' => 'string'],
                        'last_name'      => ['type' => 'string'],
                        'title'          => ['type' => 'string', 'description' => 'Job title'],
                        'note'           => ['type' => 'string', 'description' => 'Note about the customer that agents see on the profile. Replaces the current note.'],
                        'address_line_1' => ['type' => 'string'],
                        'address_line_2' => ['type' => 'string'],
                        'city'           => ['type' => 'string'],
                        'state'          => ['type' => 'string'],
                        'zip'            => ['type' => 'string'],
                        'country'        => ['type' => 'string', 'description' => 'Two-letter country code, e.g. US'],
                    ],
                    'required' => ['customer_id'],
                ],
                'execute_callback' => [CustomerTools::class, 'updateCustomer'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => true, 'openWorldHint' => false],
            ],

            'fluent-support/get-mentions' => [
                'label'       => __('Get Agent Mentions', 'fluent-support'),
                'description' => 'Get tickets where you have been @mentioned in internal notes or replies. Returns mention notifications with the ticket context, who mentioned you, and a content preview. Filter by read/unread status or scope to a specific ticket. Requires internal notifications to be enabled in settings.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'    => ['type' => 'string', 'enum' => ['all', 'unread', 'read'], 'default' => 'all', 'description' => 'Filter by read status'],
                        'ticket_id' => ['type' => 'integer', 'description' => 'Scope mentions to a specific ticket ID'],
                        'page'      => ['type' => 'integer', 'default' => 1, 'description' => 'Page number'],
                        'per_page'  => ['type' => 'integer', 'default' => 15, 'description' => 'Results per page (max 100)'],
                    ],
                ],
                'execute_callback' => [ManagementTools::class, 'getMentions'],
                'annotations'      => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-support/bulk-action' => [
                'label'       => __('Bulk Ticket Action', 'fluent-support'),
                'description' => 'Act on multiple tickets at once: close, assign, tag or reply. action=close emails every customer the closing email unless you set silent=true. More efficient than calling individual tools in a loop. Returns per-ticket results showing what succeeded or failed. reply sends the same content to every ticket, each exactly as reply-to-ticket would (closed tickets reopen, draft and close_ticket apply to all); placeholders such as {{customer.first_name}} are filled per customer. Maximum 50 ticket IDs per call; split larger sets into multiple requests.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 50, 'description' => 'Array of ticket IDs (max 50 per request)'],
                        'action'     => ['type' => 'string', 'enum' => ['close', 'assign', 'tag', 'reply'], 'description' => 'Action to perform'],
                        'agent_id'   => ['type' => 'integer', 'description' => 'Agent ID (required for assign action)'],
                        'tag_ids'    => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Tag IDs (required for tag action)'],
                        'content'        => ['type' => 'string', 'description' => 'Reply content (reply action; or use saved_reply_id).'],
                        'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text'], 'default' => 'markdown', 'description' => 'Format of content: markdown (default), html (as-is) or text (plain text, wrapped in paragraphs).'],
                        'saved_reply_id' => ['type' => 'integer', 'description' => 'Saved reply to send (reply action).'],
                        'close_ticket'   => ['type' => 'boolean', 'default' => false, 'description' => 'Close each ticket after replying (reply action).'],
                        'draft'          => ['type' => 'boolean', 'default' => false, 'description' => 'Save each reply as an unsent draft for a human to approve (reply action).'],
                        'silent'         => ['type' => 'boolean', 'default' => false, 'description' => 'Close without the "ticket closed" email and closing automations, as close-ticket silent=true (close action). Without it, every customer gets the closing email.'],
                    ],
                    'required' => ['ticket_ids', 'action'],
                ],
                'execute_callback' => [ManagementTools::class, 'bulkAction'],
                'annotations'      => ['readonly' => false, 'destructive' => true, 'idempotent' => false, 'openWorldHint' => true],
            ],
        ];

        return self::$definitions;
    }

    /** Input schema for files sent with a reply or note. */
    private static function attachmentsSchema()
    {
        return [
            'type'        => 'array',
            'maxItems'    => \FluentSupport\App\Modules\MCP\Support\AttachmentStager::maxFiles(),
            'items'       => [
                'type'       => 'object',
                'properties' => [
                    'filename'       => ['type' => 'string', 'description' => 'File name with extension, e.g. invoice.pdf'],
                    'content_base64' => ['type' => 'string', 'description' => 'File content, base64-encoded'],
                ],
                'required'   => ['filename', 'content_base64'],
            ],
            'description' => 'Files to attach, base64-encoded. get-support-context lists the limits under uploads: how many files, the size of each, and the allowed extensions.',
        ];
    }

    public static function register()
    {
        foreach (self::getDefinitions() as $name => $definition) {
            $args = [
                'label'               => $definition['label'],
                'description'         => $definition['description'],
                'category'            => 'fluent-support',
                'execute_callback'    => self::wrapExecuteCallback($name, $definition['execute_callback']),
                'permission_callback' => AbilityGuard::callbackFor($name),
                'meta'                => [
                    'show_in_rest' => true,
                    'mcp'          => ['public' => true],
                ],
            ];

            if (!empty($definition['input_schema'])) {
                $args['input_schema'] = $definition['input_schema'];
            }

            if (!empty($definition['annotations'])) {
                $args['meta']['annotations'] = $definition['annotations'];
            }

            wp_register_ability($name, $args);
        }
    }

    /**
     * Wrap an execute_callback so that:
     * 1. Capability checks produce structured JSON (not the adapter's plain
     *    "Permission denied" string that fires when permission_callback returns false).
     * 2. Uncaught exceptions become structured WP_Error responses.
     */
    private static function wrapExecuteCallback($abilityName, $callback)
    {
        return function ($params) use ($abilityName, $callback) {
            if (!AbilityGuard::check($abilityName)) {
                return \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::error(
                    'forbidden',
                    __('You do not have permission to perform this action.', 'fluent-support'),
                    ['retryable' => false]
                );
            }

            try {
                return call_user_func($callback, $params);
            } catch (\Throwable $e) {
                do_action('fluent_support/mcp_tool_exception', [
                    'exception' => $e,
                    'tool'      => $abilityName,
                    'params'    => $params,
                ]);

                return \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::error(
                    'internal_error',
                    __('An unexpected error occurred. Please try again or contact support.', 'fluent-support'),
                    ['retryable' => true]
                );
            }
        };
    }
}
