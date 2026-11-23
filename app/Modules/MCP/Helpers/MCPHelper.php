<?php

namespace FluentSupport\App\Modules\MCP\Helpers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Support\TicketAccessGuard;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Parser\Parsedown;
use FluentSupport\App\Services\Tickets\TicketService;

class MCPHelper
{
    /** Default per_page ceiling. Individual tools may raise their own limit up to HARD_MAX_PER_PAGE. */
    const MAX_PER_PAGE = 100;

    /** Ceiling no tool can exceed. */
    const HARD_MAX_PER_PAGE = 200;

    /**
     * Version of the shape of tool results, sent as meta.schema_version so scripts
     * can tell when it changed. Bump it when a result field is renamed or removed
     * or changes meaning; adding a field does not need a bump.
     *
     * 2 (2026-10-07): last_reply_by became waiting_on; reply_email shows the
     * template instead of guessed greeting/sign-off flags; uploads lists
     * allowed_extensions; the customer email can no longer be changed.
     *
     * 3 (2026-11-16): get-support-context lists hold ticket ids, with each row once
     * under tickets; agents, products, mailboxes, tags, views and uploads are
     * returned only when include asks for them.
     */
    const SCHEMA_VERSION = 3;

    /** Max chars in a content preview. */
    const PREVIEW_CHARS = 150;

    public static function resolveAgent()
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return null;
        }

        return Helper::getAgentByUserId($userId);
    }

    /**
     * Whose turn it is on an open ticket: "agent" when the customer is owed a reply
     * (a new ticket counts, and one with no agent reply), "customer" when we answered
     * and wait for them. Null on a closed ticket. Informational replies and notes do
     * not change it.
     *
     * @param \FluentSupport\App\Models\Ticket $ticket
     * @return string|null
     */
    public static function waitingOn($ticket)
    {
        if ($ticket->status === 'closed') {
            return null;
        }

        // The same rule as Ticket::scopeWaitingOnly(), so a row agrees with the
        // waiting_for_reply and waiting_on filters. A tie counts as answered: an
        // outreach ticket has both timestamps set to its creation time.
        $agentAt    = $ticket->last_agent_response;
        $customerAt = $ticket->last_customer_response;
        if ($ticket->status === 'new' || !$agentAt || ($customerAt && strtotime($agentAt) < strtotime($customerAt))) {
            return 'agent';
        }

        return 'customer';
    }

    public static function formatTicketForMCP($ticket)
    {
        $data = [
            'id'              => $ticket->id,
            'title'           => $ticket->title,
            'status'          => $ticket->status ?: 'new',
            'priority'        => self::normalizePriority($ticket->priority),
            'client_priority' => $ticket->client_priority ?: 'normal',
            'source'          => $ticket->source,
            'response_count'  => (int) $ticket->response_count,
            'waiting_on'      => self::waitingOn($ticket),
            'created_at'      => self::toIso8601($ticket->created_at),
            'updated_at'      => self::toIso8601($ticket->updated_at),
            'waiting_since'   => self::toIso8601($ticket->waiting_since),
            'resolved_at'     => self::toIso8601($ticket->resolved_at),
        ];

        if ($ticket->relationLoaded('customer') && $ticket->customer) {
            $data['customer'] = self::formatPersonSummary($ticket->customer);
        }

        if ($ticket->relationLoaded('agent') && $ticket->agent) {
            $data['agent'] = self::formatPersonSummary($ticket->agent);
        }

        if ($ticket->relationLoaded('product') && $ticket->product) {
            $data['product'] = [
                'id'    => $ticket->product->id,
                'title' => $ticket->product->title,
            ];
        }

        if ($ticket->relationLoaded('mailbox') && $ticket->mailbox) {
            $data['mailbox'] = [
                'id'   => $ticket->mailbox->id,
                'name' => $ticket->mailbox->name,
            ];
        }

        if ($ticket->relationLoaded('tags')) {
            $data['tags'] = $ticket->tags->map(function ($tag) {
                return ['id' => $tag->id, 'title' => $tag->title];
            })->toArray();
        }

        $data['content'] = self::htmlToMarkdown($ticket->content);

        return $data;
    }

    public static function formatTicketList($paginated, $customerMeta = [])
    {
        // A paginator in page mode, a plain collection/array in keyset cursor mode.
        $items = is_object($paginated) && method_exists($paginated, 'items') ? $paginated->items() : $paginated;

        if (!is_iterable($items)) {
            $type = is_object($items) ? get_class($items) : gettype($items);
            throw new \Exception('MCPHelper::formatTicketList() expects a paginator, Collection, or array of tickets, got ' . esc_html($type));
        }

        $tickets = [];
        foreach ($items as $ticket) {
            $item = [
                'id'              => $ticket->id,
                'title'           => $ticket->title,
                'preview'         => self::preview($ticket->content),
                'status'          => $ticket->status ?: 'new',
                'priority'        => self::normalizePriority($ticket->priority),
                'client_priority' => $ticket->client_priority ?: 'normal',
                'response_count'  => (int) $ticket->response_count,
                'waiting_on'      => self::waitingOn($ticket),
                'created_at'      => self::toIso8601($ticket->created_at),
                'waiting_since'   => self::toIso8601($ticket->waiting_since),
            ];

            if ($ticket->relationLoaded('customer') && $ticket->customer) {
                $item['customer'] = self::formatPersonSummary($ticket->customer);
            }

            if ($ticket->relationLoaded('agent') && $ticket->agent) {
                $item['agent'] = self::formatPersonSummary($ticket->agent);
            }

            if ($ticket->relationLoaded('product') && $ticket->product) {
                $item['product'] = $ticket->product->title;
            }

            if ($ticket->relationLoaded('tags')) {
                $item['tags'] = $ticket->tags->pluck('title')->toArray();
            }

            // Integration-provided one-liner, gated upstream by fst_sensitive_data.
            if ($ticket->customer_id && isset($customerMeta[$ticket->customer_id])) {
                $item['customer_summary'] = $customerMeta[$ticket->customer_id];
            }

            $tickets[] = $item;
        }

        return $tickets;
    }

    /**
     * Standard tool response: a quotable one-line summary, the data, and meta
     * (schema_version, generated_at, plus caller keys such as paging).
     */
    public static function envelope($summary, $data, $meta = [])
    {
        return [
            'summary' => $summary,
            'data'    => $data,
            'meta'    => array_merge(['schema_version' => self::SCHEMA_VERSION, 'generated_at' => wp_date('c')], $meta),
        ];
    }

    /**
     * Clamped ['page' => int, 'per_page' => int] from $params.
     */
    public static function pagination($params, $default = 15, $max = null)
    {
        $max = min($max ?? self::MAX_PER_PAGE, self::HARD_MAX_PER_PAGE);
        return [
            'page'     => max((int) ($params['page'] ?? 1), 1),
            'per_page' => min(max((int) ($params['per_page'] ?? $default), 1), $max),
        ];
    }

    /**
     * Paging block for envelope()'s $meta, from a paginator.
     */
    public static function pagingMeta($paginator)
    {
        return [
            'paging' => [
                'current'  => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total'    => $paginator->total(),
                'pages'    => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @param iterable $responses   Conversations, oldest first.
     * @param int      $maxChars    Truncate each body to this many characters (0 = no limit).
     */
    public static function formatResponseThread($responses, $maxChars = 0)
    {
        $thread = [];
        foreach ($responses as $response) {
            $entry = [
                'id'        => $response->id,
                'type'      => $response->conversation_type,
                'source'    => $response->source,
                'created_at' => self::toIso8601($response->created_at),
            ];
            $entry = array_merge($entry, self::truncatedContent(self::htmlToMarkdown($response->content), $maxChars));

            if ($response->relationLoaded('attachments') && !$response->attachments->isEmpty()) {
                $entry['attachments'] = self::formatAttachments($response->attachments);
            }

            if ($response->relationLoaded('person') && $response->person) {
                $entry['person'] = [
                    'name' => self::personName($response->person),
                    'type' => $response->person->person_type,
                ];
            }

            $thread[] = $entry;
        }

        return $thread;
    }

    /**
     * ['content' => ...], plus truncated/full_length when it was cut.
     *
     * @param string $text
     * @param int    $maxChars 0 = no limit.
     * @return array
     */
    public static function truncatedContent($text, $maxChars = 0)
    {
        $maxChars = (int) $maxChars;
        $length   = mb_strlen($text);

        if ($maxChars <= 0 || $length <= $maxChars) {
            return ['content' => $text];
        }

        return [
            'content'     => rtrim(mb_substr($text, 0, $maxChars)) . "\u{2026}",
            'truncated'   => true,
            'full_length' => $length,
        ];
    }

    /**
     * Attachment metadata with a freshly signed download URL.
     *
     * @param iterable $attachments
     * @return array
     */
    public static function formatAttachments($attachments)
    {
        $items = [];
        foreach ($attachments as $attachment) {
            if ($attachment->status !== 'active') {
                continue;
            }

            $expires = \FluentSupport\App\Models\Attachment::signedUrlExpiry();
            $items[] = [
                'id'             => (int) $attachment->id,
                'title'          => $attachment->title,
                'file_type'      => $attachment->file_type,
                'file_size'      => (int) $attachment->file_size,
                'url'            => $attachment->signedUrl($expires),
                'url_expires_at' => wp_date('c', $expires),
            ];
        }

        return $items;
    }

    public static function formatCustomerForMCP($customer)
    {
        return [
            'id'         => $customer->id,
            'first_name' => $customer->first_name,
            'last_name'  => $customer->last_name,
            'email'      => $customer->email,
            'status'     => $customer->status,
            'city'       => $customer->city,
            'state'      => $customer->state,
            'country'    => $customer->country,
            'created_at' => self::toIso8601($customer->created_at),
        ];
    }

    public static function personName($model)
    {
        if (!$model) {
            return null;
        }
        $name = trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? ''));
        return $name !== '' ? $name : null;
    }

    public static function formatPersonSummary($person)
    {
        return [
            'id'    => $person->id,
            'name'  => self::personName($person),
            'email' => $person->email,
        ];
    }

    public static function normalizePriority($priority)
    {
        if (!$priority || $priority === '') {
            return 'normal';
        }

        $aliases = [
            'low'  => 'normal',
            'high' => 'critical',
        ];

        $normalized = strtolower(trim($priority));
        $normalized = $aliases[$normalized] ?? $normalized;

        $valid = ['normal', 'medium', 'critical'];
        return in_array($normalized, $valid, true) ? $normalized : 'normal';
    }

    public static function toIso8601($value)
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        try {
            if (is_object($value) && isset($value->date)) {
                // WPFluent sometimes returns a DateTime-like plain object.
                $str = (string) $value->date;
                if (self::isZeroDate($str)) {
                    return null;
                }
                $dt = new \DateTime($str, new \DateTimeZone($value->timezone ?? 'UTC'));
                if ((int) $dt->format('Y') < 1) {
                    return null;
                }
                return $dt->format('c');
            }

            if (is_string($value)) {
                if (self::isZeroDate($value)) {
                    return null;
                }
                // Stored dates are site time; PHP's default zone in WordPress is UTC.
                $dt = new \DateTime($value, wp_timezone());
                if ((int) $dt->format('Y') < 1) {
                    return null;
                }
                return $dt->format('c');
            }
        } catch (\Exception $e) {
            // Malformed date: fall through to null.
        }

        return null;
    }

    /** True for MySQL zero-dates that PHP's DateTime constructor would misparse. */
    private static function isZeroDate($str)
    {
        return $str === '' || strpos($str, '0000-00-00') === 0;
    }

    /**
     * Customer profile widgets (fluent_support/customer_extra_widgets) for MCP.
     *
     * MCP requests widgets with context format 'mcp' (see ProfileInfoService::
     * getProfileExtraWidgets()). A widget then passes an 'mcp' array instead of
     * body_html, and MCP returns it as `data`, so agents get fields, not prose:
     *
     *   if (ProfileInfoService::isMcpWidgetRequest($context)) {
     *       $widgets['my_plugin'] = ['title' => 'Account', 'mcp' => ['plan' => 'paid', 'teams' => [...]]];
     *   }
     *
     * It's untrusted integration output: only scalars are kept, tags are
     * stripped, and depth, list sizes and string lengths are capped
     * (sanitizeWidgetData()). Without 'mcp', body_html is sent as Markdown.
     *
     * @param array      $widgets
     * @param array|null $onlyKeys Widget keys to keep (null = all).
     * @return array
     */
    public static function formatExtraWidgets($widgets, $onlyKeys = null)
    {
        $formatted = [];

        foreach ($widgets as $key => $widget) {
            if (!is_array($widget) || ($onlyKeys !== null && !in_array((string) $key, $onlyKeys, true))) {
                continue;
            }

            if (isset($widget['mcp']) && is_array($widget['mcp'])) {
                $formatted[$key] = [
                    'title' => sanitize_text_field($widget['title'] ?? $widget['header'] ?? $key),
                    'data'  => self::sanitizeWidgetData($widget['mcp']),
                ];
                continue;
            }

            // Structured widgets are already plain data.
            // Same test as isStructuredWidget() in _StructuredWidget.vue: a non-empty list.
            $structuredKeys = ['badges', 'stats', 'facts', 'sections', 'links'];
            $isStructured = false;
            foreach ($structuredKeys as $structuredKey) {
                if (!empty($widget[$structuredKey]) && is_array($widget[$structuredKey])) {
                    $isStructured = true;
                    break;
                }
            }

            if ($isStructured) {
                $formatted[$key] = [
                    'title' => sanitize_text_field($widget['title'] ?? $widget['header'] ?? $key),
                    'data'  => self::sanitizeWidgetData(self::flattenItemBadges(array_intersect_key($widget, array_flip($structuredKeys)))),
                ];
                continue;
            }

            if (!empty($widget['orders'])) {
                $orders = [];
                foreach ($widget['orders'] as $order) {
                    $item = [
                        'order_id' => $order['id'] ?? $order['order_id'] ?? null,
                        'status'   => $order['status'] ?? '',
                        'total'    => $order['total'] ?? $order['amount'] ?? '',
                        'date'     => $order['date'] ?? $order['date_created'] ?? '',
                    ];
                    $orders[] = array_filter($item);
                }
                $formatted[$key] = [
                    'title'  => $widget['title'] ?? $key,
                    'orders' => $orders,
                ];
            } elseif (!empty($widget['products'])) {
                $products = [];
                foreach ($widget['products'] as $product) {
                    $item = [
                        'name'   => $product['title'] ?? $product['name'] ?? '',
                        'status' => $product['status'] ?? '',
                        'price'  => $product['price'] ?? '',
                    ];
                    $products[] = array_filter($item);
                }
                $entry = [
                    'title'    => $widget['title'] ?? $key,
                    'products' => $products,
                ];
                if (!empty($widget['summary'])) {
                    $entry['summary'] = $widget['summary'];
                }
                $formatted[$key] = $entry;
            } else {
                $skipKeys = ['title', 'header', 'render', 'content_type'];
                $data     = array_diff_key($widget, array_flip($skipKeys));

                if (isset($data['body_html'])) {
                    // Raw widget HTML is token-heavy, so send Markdown: tables and
                    // lists keep their shape, links keep their targets.
                    // A summary the integration already provides wins.
                    if (empty($data['summary'])) {
                        $data['summary'] = self::htmlToMarkdown($data['body_html']);
                    }
                    unset($data['body_html']);
                }

                $formatted[$key] = [
                    'title' => $widget['title'] ?? $key,
                    'data'  => $data,
                ];
            }
        }

        return $formatted;
    }

    /**
     * sections[].items[].badge sits one level past WIDGET_MAX_DEPTH; keep its text.
     *
     * @param array $data
     * @return array
     */
    private static function flattenItemBadges($data)
    {
        if (empty($data['sections']) || !is_array($data['sections'])) {
            return $data;
        }

        foreach ($data['sections'] as $sectionKey => $section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $itemKey => $item) {
                if (is_array($item) && isset($item['badge']) && is_array($item['badge'])) {
                    $data['sections'][$sectionKey]['items'][$itemKey]['badge'] = $item['badge']['text'] ?? null;
                }
            }
        }

        return $data;
    }

    // Counts the mcp array itself, so orders[] => items[] => {name, qty} fits.
    const WIDGET_MAX_DEPTH  = 5;
    const WIDGET_MAX_ITEMS  = 50;
    const WIDGET_MAX_STRING = 500;

    /**
     * Keep scalars only, strip tags, cap depth, list sizes and string lengths.
     * ISO 8601 dates with an offset are moved to the site's timezone.
     *
     * @param array $data
     * @param int   $depth
     * @return array
     */
    public static function sanitizeWidgetData($data, $depth = 1)
    {
        $clean = [];
        foreach (array_slice($data, 0, self::WIDGET_MAX_ITEMS, true) as $key => $value) {
            $key = is_int($key) ? $key : sanitize_key($key);
            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                if ($depth < self::WIDGET_MAX_DEPTH) {
                    $clean[$key] = self::sanitizeWidgetData($value, $depth + 1);
                }
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                // Decode first, so encoded tags are stripped too.
                $value = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));

                // Links come from other sites: web addresses only.
                if ($key === 'url' && !preg_match('#^https?://#i', $value)) {
                    continue;
                }

                // Integrations send ISO 8601 dates, most in UTC. Show the same moment
                // in site time, as every other date the tools return.
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value) && ($timestamp = strtotime($value))) {
                    $value = wp_date('c', $timestamp);
                }

                $clean[$key] = mb_substr($value, 0, self::WIDGET_MAX_STRING);
            }
        }

        return $clean;
    }

    /**
     * Truncate plain-text content to PREVIEW_CHARS with an ellipsis.
     *
     * @param string $html Raw HTML or plain text.
     * @return string
     */
    public static function preview($html, $chars = self::PREVIEW_CHARS)
    {
        $text = self::htmlToText($html);

        if (mb_strlen($text) <= $chars) {
            return $text;
        }

        return mb_substr($text, 0, $chars) . '...';
    }

    // Returns unsanitized HTML — callers must run through wp_kses_post.
    public static function processContent($content, $format = 'markdown')
    {
        if ($content === '' || $content === null) {
            return '';
        }

        switch ($format) {
            case 'html':
                return $content;
            case 'text':
                return wpautop(esc_html($content));
            default:
                return self::markdownToHtml($content);
        }
    }

    private static function markdownToHtml($content)
    {
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
        // Agents write emails: a single newline ("Thanks,\nJewel") is a line break.
        $parsedown->setBreaksEnabled(true);

        return self::escapeStrayAngleBrackets($parsedown->text($content));
    }

    // Escape a "<" that doesn't open a kses-allowed tag, so text like x<5 or List<String> isn't stripped as a tag.
    private static function escapeStrayAngleBrackets($html)
    {
        $allowedTags = wp_kses_allowed_html('post');

        return preg_replace_callback('/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)?/', function ($match) use ($allowedTags) {
            $tag = isset($match[2]) ? strtolower($match[2]) : '';

            return ($tag !== '' && isset($allowedTags[$tag])) ? $match[0] : '&lt;' . substr($match[0], 1);
        }, $html);
    }

    public static function htmlToText($html)
    {
        if (!$html) {
            return '';
        }

        // Line breaks and block boundaries separate words; stripping them alone glues
        // "Name</p><p>Pedro" into "NamePedro".
        $html = preg_replace('#<br\s*/?>|</?(p|div|li|ul|ol|tr|td|th|h[1-6]|blockquote|pre|table|section|article|header|footer)\b[^>]*>#i', ' ', $html);

        $text = wp_strip_all_tags($html);
        // ENT_HTML5: the default HTML 4.01 table leaves &apos; as it is.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // \xA0: a decoded &nbsp; is not whitespace to \s without the u flag.
        $text = preg_replace('/(?:\s|\xC2\xA0)+/', ' ', $text);

        return trim($text);
    }

    /**
     * HTML to Markdown for agents: keeps line breaks, lists, tables, code
     * blocks and link targets that htmlToText() flattens.
     *
     * @param string $html
     * @return string
     */
    public static function htmlToMarkdown($html)
    {
        return HtmlToMarkdown::convert($html);
    }

    /**
     * WP_Error whose message is a JSON error envelope. The MCP adapter forwards
     * only the message, not error_data, so the structure has to live there.
     *
     * Supported $details keys:
     *   fields    => string[]  Input parameter names that caused the error.
     *   next_step => string    What the agent should do to recover.
     *   hint      => string    Extra context.
     *   retryable => bool      Whether retrying the same call might succeed (default false).
     */
    public static function error($code, $message, $details = [])
    {
        $payload = array_merge(['code' => $code, 'message' => $message, 'retryable' => false], $details);
        return new \WP_Error($code, wp_json_encode(['error' => $payload]));
    }

    /**
     * The gate every MCP path uses before assigning a ticket to a specific agent:
     * checks fst_assign_agents, that the agent exists, and, when a ticket is given,
     * that the agent isn't restricted from its mailbox.
     *
     * @param int         $agentId
     * @param Ticket|null $ticket    Null skips the mailbox check (bulk assign checks per ticket).
     * @param string      $fieldName Input field name to report in error details.
     * @return Agent|\WP_Error  The resolved agent, or a WP_Error to return as-is.
     */
    public static function resolveAssignmentTarget($agentId, $ticket = null, $fieldName = 'agent_id')
    {
        if (!PermissionManager::currentUserCan('fst_assign_agents')) {
            return self::error('forbidden', 'You do not have permission to assign agents');
        }

        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return self::error('invalid_param', sprintf('%s must be a positive integer', $fieldName), ['fields' => [$fieldName]]);
        }

        $targetAgent = Agent::find($agentId);
        if (!$targetAgent) {
            return self::error('invalid_param', 'The specified agent does not exist', ['fields' => [$fieldName], 'next_step' => 'Use get-support-context with include=["agents"] to see available agents and their IDs']);
        }

        if ($ticket instanceof Ticket) {
            if ($err = TicketAccessGuard::assertAssignableAgent($ticket, $targetAgent)) {
                return $err;
            }
        }

        return $targetAgent;
    }

    /**
     * Assign the ticket and fire the assignment side effects if the assignee changed.
     * Always saves, so callers can stage other field changes on the model first.
     * Authorize $targetAgent first with resolveAssignmentTarget() (self-assignment may skip it).
     *
     * Inside a transaction, pass $deferSideEffects and call fireAgentAssignmentSideEffects()
     * after commit, so emails and webhooks don't fire for a rolled-back change. Capture the
     * previous agent_id before calling, since this overwrites it.
     *
     * @return bool True if the assignee changed, false if it was already $targetAgent.
     */
    public static function applyAgentAssignment(Ticket $ticket, Agent $targetAgent, Agent $actingAgent, $deferSideEffects = false)
    {
        $changed         = (int) $ticket->agent_id !== (int) $targetAgent->id;
        $previousAgentId = $ticket->agent_id;

        if ($changed) {
            $ticket->agent_id = $targetAgent->id;
        }

        $ticket->save();

        if ($changed && !$deferSideEffects) {
            $ticket->load('agent');
            self::fireAgentAssignmentSideEffects($ticket, $actingAgent, $previousAgentId);
        }

        return $changed;
    }

    /**
     * Add the assignment note and fire fluent_support/agent_assigned_to_ticket.
     * Not transactional, so call only after the assignment is committed.
     * $ticket must have the new 'agent' relation loaded.
     */
    public static function fireAgentAssignmentSideEffects(Ticket $ticket, Agent $actingAgent, $previousAgentId)
    {
        (new TicketService())->afterAgentAssigned($ticket, $actingAgent, $previousAgentId);
    }

    public static function formatDuration($seconds)
    {
        if (!$seconds) {
            return '0m';
        }
        $seconds = (int) round($seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }

        // Round to the smaller unit shown first, then split, so it never reads "60m" or "24h".
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }
        if ($minutes < 1440) {
            return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
        }

        $hours = (int) round($seconds / 3600);
        return intdiv($hours, 24) . 'd ' . ($hours % 24) . 'h';
    }
}
