<?php

namespace FluentSupport\App\Modules\MCP;

use FluentSupport\App\Modules\MCP\Support\AbilityGuard;

/**
 * MCP prompts: ready-made starting points a person picks in their MCP client
 * (often as a slash command). Each is an ability that returns prompt messages;
 * the adapter lists them under prompts/list. They only write instructions, so
 * the tools they point to still enforce every permission.
 *
 * Prompt text is English and not translatable, like the tool descriptions.
 */
class PromptsRegistrar
{
    const PERIODS = ['24h', '7d', '30d', '90d'];

    public static function getDefinitions(): array
    {
        return [
            'fluent-support/triage-queue' => [
                'label'       => __('Triage my queue', 'fluent-support'),
                'description' => 'Go through the tickets waiting on an agent and propose an action for each, without sending anything until you approve.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'scope' => ['type' => 'string', 'enum' => ['mine', 'team'], 'description' => 'mine: your tickets (default). team: every ticket you can see.'],
                    ],
                ],
                'execute_callback' => [self::class, 'triageQueue'],
            ],
            'fluent-support/draft-reply' => [
                'label'       => __('Draft a reply', 'fluent-support'),
                'description' => 'Read a ticket and save a draft reply for review. Nothing is sent.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'The ticket ID'],
                        'guidance'  => ['type' => 'string', 'description' => 'Optional: what the reply should say or do'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [self::class, 'draftReply'],
            ],
            'fluent-support/summarize-ticket' => [
                'label'       => __('Summarize a ticket', 'fluent-support'),
                'description' => 'Summarize a ticket for a teammate taking it over, and optionally save the summary as an internal note.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'The ticket ID'],
                        'save_note' => ['type' => 'string', 'enum' => ['no', 'yes'], 'description' => 'yes: save the summary as an internal note'],
                    ],
                    'required' => ['ticket_id'],
                ],
                'execute_callback' => [self::class, 'summarizeTicket'],
            ],
            'fluent-support/support-report' => [
                'label'       => __('Support report', 'fluent-support'),
                'description' => 'Write a short report on support volume, response times and workload for a period.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => self::PERIODS, 'description' => 'Reporting period (default 7d)'],
                    ],
                ],
                'execute_callback' => [self::class, 'supportReport'],
            ],
        ];
    }

    public static function register()
    {
        foreach (self::getDefinitions() as $name => $definition) {
            wp_register_ability($name, [
                'label'               => $definition['label'],
                'description'         => $definition['description'],
                'category'            => 'fluent-support',
                'input_schema'        => $definition['input_schema'],
                'execute_callback'    => $definition['execute_callback'],
                'permission_callback' => function () use ($name) {
                    return AbilityGuard::check($name);
                },
                'meta'                => [
                    'show_in_rest' => true,
                    'mcp'          => ['public' => true, 'type' => 'prompt'],
                    'annotations'  => ['readonly' => true],
                ],
            ]);
        }
    }

    public static function triageQueue($params)
    {
        $team = (($params['scope'] ?? 'mine') === 'team');

        $source = $team
            ? '1. Call get-support-context, then list-tickets with waiting_for_reply=true, sort_by=waiting_since, order=ASC, per_page=25. Use meta.paging.next_cursor for more pages.'
            : '1. Call get-support-context and start with my_queue.awaiting_your_reply. If awaiting_your_reply_truncated is true, call list-tickets with agent_id set to your agent ID, waiting_for_reply=true, sort_by=waiting_since, order=ASC.';

        return self::messages(implode("\n", [
            'Triage my Fluent Support queue.',
            '',
            $source,
            '2. Use each row\'s preview, customer, tags and waiting time. Open a ticket with get-ticket only when the preview is not enough to decide.',
            '3. For each ticket choose one action: reply, ask a teammate (internal note with a mention), assign, tag, close, or leave it.',
            '4. Show me a table first: ticket, customer, waiting time, proposed action and a one-line reason. Oldest first.',
            '5. Wait for my go-ahead. Then save replies as drafts (reply-to-ticket with draft=true) unless I say to send them. Never close or delete a ticket without my approval.',
        ]));
    }

    public static function draftReply($params)
    {
        $ticketId = absint($params['ticket_id'] ?? 0);
        $guidance = trim(sanitize_textarea_field((string) ($params['guidance'] ?? '')));

        $lines = [
            "Draft a reply to Fluent Support ticket #{$ticketId}.",
            '',
            "1. Call get-ticket with ticket_id={$ticketId}. Read the whole thread, the customer's previous tickets and any purchase or CRM data.",
            '2. Read the reply_email template: the customer gets it with [YOUR MESSAGE] replaced by your reply, so leave out any greeting, sign-off or signature it already has.',
            '3. Call list-saved-replies with a short search for this topic and reuse a saved answer when one fits.',
            '4. Write the reply in Markdown. Answer the customer\'s latest message directly, in their language, and ask for any detail you still need.',
            "5. Save it with reply-to-ticket, ticket_id={$ticketId}, draft=true. Do not send it. Tell me the draft ID and show me the text.",
        ];

        if ($guidance !== '') {
            $lines[] = '';
            $lines[] = 'My guidance for this reply: ' . $guidance;
        }

        return self::messages(implode("\n", $lines));
    }

    public static function summarizeTicket($params)
    {
        $ticketId = absint($params['ticket_id'] ?? 0);
        $saveNote = (($params['save_note'] ?? 'no') === 'yes');

        $lines = [
            "Summarize Fluent Support ticket #{$ticketId} for a teammate who is taking it over.",
            '',
            "1. Call get-ticket with ticket_id={$ticketId} and get-ticket-activity with ticket_id={$ticketId}.",
            '2. Cover: the problem, what has been tried, the current state, who the ticket is waiting on, and the next step.',
            '3. Keep it under 150 words. Use the customer\'s own words for the problem where you can.',
        ];

        $lines[] = $saveNote
            ? "4. Save the summary with add-internal-note on ticket {$ticketId}, then show it to me."
            : '4. Show me the summary. Do not change the ticket.';

        return self::messages(implode("\n", $lines));
    }

    public static function supportReport($params)
    {
        $period = in_array($params['period'] ?? '', self::PERIODS, true) ? $params['period'] : '7d';

        return self::messages(implode("\n", [
            "Write a Fluent Support report for the last {$period}.",
            '',
            "1. Call get-support-insights with period={$period}, and get-support-context.",
            '2. Cover ticket volume and its trend, first-response and resolution times, tickets waiting on an agent versus on the customer, and each agent\'s open and closed counts.',
            '3. List the five tickets that have waited longest on an agent.',
            '4. End with three concrete recommendations. Keep the report under 300 words and use a table for per-agent numbers.',
        ]));
    }

    private static function messages($text)
    {
        return [
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => ['type' => 'text', 'text' => $text],
                ],
            ],
        ];
    }
}
