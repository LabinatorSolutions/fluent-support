<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\Framework\Support\Arr;

/**
 * Every ticket list: the admin list, the sidebar counts, the widgets, the
 * mailbox screen, MCP and Pro. The viewer's access scope (visibility and
 * mailbox restrictions) is always applied, so no caller can forget it.
 *
 *   TicketQuery::fromRequest($request->all())->paginate();   // the admin list
 *   (new TicketQuery(['customer_id' => 5]))->query();         // a scoped builder
 *
 * Arguments (all optional):
 * - Simple filters, as the list sends them: status_type (a status group),
 *   agent_id (an id, a list of ids, or 'unassigned' in it), watcher ('watcher':
 *   agent_id means the tickets they watch), agent_group, ticket_tags, product_id,
 *   mailbox_id, priority, client_priority (a value or a list), waiting_for_reply
 *   ('yes').
 * - customer_id, exclude_id, unassigned (bool), waiting_on_customer (bool),
 *   needs_first_response (bool), created_after / created_before (Y-m-d H:i:s).
 * - search: title, content, number or `column:value`; deep_search (bool) also
 *   searches the customer's name and email and the replies.
 * - advanced: filter groups from the advanced filter (OR between groups, AND in
 *   a group); simple filters are then ignored, as in the list.
 * - sort_by (one of SORTABLE, else id; false for none), sort_type (ASC/DESC).
 */
class TicketQuery
{
    const SORTABLE = [
        'id', 'created_at', 'updated_at', 'title', 'status', 'priority', 'client_priority', 'product_id',
        'waiting_since', 'last_agent_response', 'last_customer_response', 'response_count',
    ];

    const SIMPLE_COLUMNS = ['product_id', 'client_priority', 'priority', 'mailbox_id'];

    /**
     * The person fields the advanced filter offers, per provider: filter key =>
     * column. Anything else matches no ticket, so hidden columns (hash,
     * remote_uid, ...) can't be probed through result counts.
     */
    const PERSON_FILTER_FIELDS = [
        'customer' => [
            'first_name'     => 'first_name',
            'last_name'      => 'last_name',
            'email'          => 'email',
            'address_line_1' => 'address_line_1',
            'address_line_2' => 'address_line_2',
            'city'           => 'city',
            'state'          => 'state',
            'postal_code'    => 'zip',
            'zip'            => 'zip',
            'country'        => 'country',
        ],
        'agent'    => [
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'email'      => 'email',
        ],
    ];

    private $args;

    private $query;

    /**
     * The admin list's request: filter_type, filters, search, customer_id,
     * advanced_filters (JSON), order_by, order_type.
     */
    public static function fromRequest(array $params)
    {
        $args = [
            'sort_by'   => sanitize_text_field(Arr::get($params, 'order_by', 'id')),
            'sort_type' => Arr::get($params, 'order_type') === 'ASC' ? 'ASC' : 'DESC',
        ];

        if (Arr::get($params, 'filter_type') === 'advanced') {
            $raw = map_deep(Arr::get($params, 'advanced_filters', []), 'sanitize_text_field');
            $args['advanced'] = is_string($raw) ? (array) json_decode($raw, true) : (array) $raw;
            return new static($args);
        }

        $filters = map_deep((array) Arr::get($params, 'filters', []), 'sanitize_text_field');

        return new static($args + $filters + [
            'search'      => trim(sanitize_text_field(Arr::get($params, 'search', ''))),
            'customer_id' => (int) Arr::get($params, 'customer_id', 0),
        ]);
    }

    /**
     * @param array $args see the class docblock
     * @param \FluentSupport\App\Models\Agent|null $agent the viewer; the current user's agent by default
     */
    public function __construct(array $args = [], $agent = null)
    {
        $this->args = $args + ['sort_by' => 'id', 'sort_type' => 'DESC'];
        $query = Ticket::query();

        (new AgentTicketAccess())->applyListScope($query, $agent);

        if (!empty($args['advanced'])) {
            $this->applyAdvanced($query, $args['advanced']);
        } else {
            $this->applySimple($query, $args);
        }

        if ($this->args['sort_by'] !== false) {
            $sortBy = in_array($this->args['sort_by'], self::SORTABLE, true) ? $this->args['sort_by'] : 'id';
            $sortType = $this->args['sort_type'] === 'ASC' ? 'ASC' : 'DESC';
            $query->orderBy($sortBy, $sortType);
            if ($sortBy !== 'id') {
                $query->orderBy('id', $sortType);
            }
        }

        // The backslash name is the original one, kept for existing listeners. They
        // also get the argument keys of the old list query.
        $hookArgs = $this->args + [
            'filter_type'        => empty($this->args['advanced']) ? 'simple' : 'advanced',
            'simple_filters'     => empty($this->args['advanced']) ? array_diff_key($this->args, array_flip(['search', 'customer_id', 'sort_by', 'sort_type'])) : [],
            'filters_groups_raw' => $this->args['advanced'] ?? [],
            'search'             => '',
            'customer_id'        => '',
        ];
        do_action_ref_array('fluent_support\main_tickets_query', [&$query, $hookArgs]);
        do_action_ref_array('fluent_support/main_tickets_query', [&$query, $hookArgs]);

        $this->query = $query;
    }

    /**
     * The scoped, filtered and sorted builder (a copy, so callers may add to it).
     */
    public function query()
    {
        return clone $this->query;
    }

    public function paginate($perPage = null)
    {
        return $perPage ? $this->query()->paginate($perPage) : $this->query()->paginate();
    }

    public function count()
    {
        return (int) $this->query()->count();
    }

    public function ids($limit = 0)
    {
        $query = $this->query();
        if ($limit) {
            $query->limit($limit);
        }

        return array_map('intval', $query->pluck('id')->all());
    }

    private function applySimple($query, array $args)
    {
        foreach (self::SIMPLE_COLUMNS as $column) {
            $value = Arr::get($args, $column);
            if (is_array($value) && $value) {
                $query->whereIn($column, $value);
            } elseif (!is_array($value) && ($value || $value === '0' || $value === 0)) {
                $query->where($column, $value);
            }
        }

        if (!empty($args['status_type']) && ($statuses = Helper::getTkStatusesByGroupName($args['status_type']))) {
            $query->whereIn('status', $statuses);
        }

        if (Arr::get($args, 'waiting_for_reply') === 'yes') {
            $query->waitingOnly();
        }
        if (!empty($args['waiting_on_customer'])) {
            $query->waitingOnCustomer();
        }

        if (!empty($args['agent_id'])) {
            $this->applyAgent($query, (array) $args['agent_id'], Arr::get($args, 'watcher') === 'watcher');
        }
        if (!empty($args['unassigned'])) {
            $query->whereNull('agent_id');
        }

        if (!empty($args['agent_group'])) {
            $groupIds = array_filter(array_map('intval', (array) $args['agent_group']));
            $agentIds = $groupIds ? TagPivot::where('source_type', 'agent_group')->whereIn('tag_id', $groupIds)->pluck('source_id')->all() : [];
            if ($groupIds) {
                $agentIds ? $query->whereIn('agent_id', $agentIds) : $query->whereRaw('1 = 0');
            }
        }

        if (!empty($args['ticket_tags'])) {
            $tagIds = (array) $args['ticket_tags'];
            $query->whereHas('tags', fn($q) => $q->whereIn('tag_id', $tagIds));
        }

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', (int) $args['customer_id']);
        }
        if (!empty($args['exclude_id'])) {
            $query->where('id', '!=', (int) $args['exclude_id']);
        }
        if (!empty($args['needs_first_response'])) {
            $query->where('response_count', 0)->where('status', '!=', 'closed');
        }
        if (!empty($args['created_after'])) {
            $query->where('created_at', '>=', $args['created_after']);
        }
        if (!empty($args['created_before'])) {
            $query->where('created_at', '<=', $args['created_before']);
        }

        $search = trim((string) Arr::get($args, 'search', ''));
        if ($search !== '' && !empty($args['deep_search'])) {
            $like = '%' . $GLOBALS['wpdb']->esc_like($search) . '%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('title', 'LIKE', $like)
                    ->orWhere('content', 'LIKE', $like)
                    ->orWhere('id', '=', is_numeric($search) ? (int) $search : 0)
                    ->orWhereHas('customer', function ($customer) use ($like) {
                        $customer->where('email', 'LIKE', $like)->orWhere('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like);
                    })
                    ->orWhereHas('responses', fn($response) => $response->where('content', 'LIKE', $like));
            });
        } elseif ($search !== '') {
            $query->searchBy($search);
        }
    }

    /**
     * Agent ids, 'unassigned' among them, or (Pro, watcher mode) the tickets
     * those agents watch.
     */
    private function applyAgent($query, array $values, $watcher)
    {
        $unassigned = in_array('unassigned', $values, true);
        $agentIds = array_values(array_filter($values, fn($value) => $value !== 'unassigned'));

        if ($agentIds && !$unassigned && $watcher && defined('FLUENTSUPPORTPRO')) {
            $watched = [];
            foreach ($agentIds as $agentId) {
                $watched = array_merge($watched, TicketHelper::getWatcherTicketIds($agentId));
            }
            $query->whereIn('id', array_unique($watched) ?: [0]);
            return;
        }

        if ($unassigned && $agentIds) {
            $query->where(fn($q) => $q->whereNull('agent_id')->orWhereIn('agent_id', $agentIds));
        } elseif ($unassigned) {
            $query->whereNull('agent_id');
        } elseif ($agentIds) {
            $query->whereIn('agent_id', $agentIds);
        }
    }

    /**
     * Groups of rows ({source: [provider, property], operator, value}), OR
     * between groups and AND in a group. Each provider is a hook
     * (fluent_support/tickets_filter_{provider}; Pro registers customer, agent
     * and tickets). A group with no valid row, or a provider nothing handles,
     * matches no ticket rather than every ticket.
     */
    private function applyAdvanced($query, array $rawGroups)
    {
        $groups = [];
        foreach ($rawGroups as $rawGroup) {
            $group = [];
            foreach ((array) $rawGroup as $row) {
                $source = Arr::get($row, 'source', []);
                if (!is_array($source) || count($source) != 2 || empty($source[0]) || empty($source[1]) || empty($row['operator'])) {
                    continue;
                }
                $group[$source[0]][] = ['property' => $source[1], 'operator' => $row['operator'], 'value' => Arr::get($row, 'value')];
            }
            if ($rawGroup) {
                $groups[] = $group;
            }
        }

        // One closure keeps the OR chain from swallowing the access scope.
        $query->where(function ($groupsQuery) use ($groups) {
            foreach ($groups as $index => $group) {
                $groupsQuery->{$index ? 'orWhere' : 'where'}(function ($q) use ($group) {
                    if (!$group) {
                        $q->whereRaw('1 = 0');
                    }
                    foreach ($group as $provider => $items) {
                        if (!has_action('fluent_support\tickets_filter_' . $provider) && !has_action('fluent_support/tickets_filter_' . $provider)) {
                            $q->whereRaw('1 = 0');
                            continue;
                        }
                        do_action_ref_array('fluent_support\tickets_filter_' . $provider, [&$q, $items]);
                        do_action_ref_array('fluent_support/tickets_filter_' . $provider, [&$q, $items]);
                    }
                });
            }
        });
    }

    /**
     * The "tickets" advanced filter provider: title, content and replies,
     * source, dates, and the ticket properties.
     */
    public static function filterTickets($query, array $items)
    {
        $esc = fn($value) => '%' . $GLOBALS['wpdb']->esc_like((string) $value) . '%';

        foreach ($items as $item) {
            $property = $item['property'];
            $operator = $item['operator'];
            $value = $item['value'];

            if (in_array($property, ['title', 'content'], true) && in_array($operator, ['contains', 'not_contains'], true)) {
                $query->where($property, $operator === 'contains' ? 'LIKE' : 'NOT LIKE', $esc($value));
            } elseif ($property === 'conversation_content' && in_array($operator, ['contains', 'not_contains'], true)) {
                $query->whereHas('responses', fn($q) => $q->where('content', $operator === 'contains' ? 'LIKE' : 'NOT LIKE', $esc($value)));
            } elseif ($property === 'source') {
                self::filterSource($query, $operator, $value);
            } elseif (in_array($property, ['created_at', 'updated_at', 'waiting_since', 'last_agent_response', 'last_customer_response'], true)) {
                self::filterDate($query, $property, $operator, $value);
            } elseif (in_array($property, ['tags', 'product'], true)) {
                self::filterRelation($query, $property, $operator, $value);
            } elseif ($property === 'waiting_for_reply') {
                if (($value == 'yes' && $operator == 'in') || ($value == 'no' && $operator == 'not_in')) {
                    $query->waitingOnly();
                } else {
                    $query->whereColumn('last_customer_response', '<', 'last_agent_response');
                }
            } elseif (in_array($property, ['status', 'client_priority', 'priority', 'agent_id', 'mailbox_id'], true)) {
                $query->{$operator == 'in' ? 'whereIn' : 'whereNotIn'}($property, (array) $value);
            }
        }

        return $query;
    }

    /**
     * The "customer" and "agent" advanced filter providers: fields of the
     * related person (PERSON_FILTER_FIELDS only).
     */
    public static function filterPerson($provider, $query, array $items)
    {
        $operators = ['in' => 'whereIn', 'not_in' => 'whereNotIn', 'contains' => 'LIKE', 'not_contains' => 'NOT LIKE', '=' => '=', '!=' => '!='];

        foreach ($items as $item) {
            $column = self::PERSON_FILTER_FIELDS[$provider][$item['property']] ?? null;
            $operator = $operators[$item['operator']] ?? null;
            if (!$column || !$operator) {
                $query->whereRaw('1 = 0');
                continue;
            }

            $query->whereHas($provider, function ($q) use ($column, $operator, $item) {
                if ($operator === 'whereIn' || $operator === 'whereNotIn') {
                    $q->{$operator}($column, (array) $item['value']);
                } elseif ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                    $q->where($column, $operator, '%' . $GLOBALS['wpdb']->esc_like((string) $item['value']) . '%');
                } else {
                    $q->where($column, $operator, $item['value']);
                }
            });
        }

        return $query;
    }

    /**
     * Source "in" / "not in". Ticket::SOURCE_NONE stands for NULL or empty,
     * which SQL's IN and NOT IN would otherwise skip.
     */
    private static function filterSource($query, $operator, $value)
    {
        $values = array_values(array_filter(array_map('sanitize_text_field', (array) $value), 'strlen'));
        if (!$values) {
            return;
        }

        $none = in_array(Ticket::SOURCE_NONE, $values, true);
        $sources = array_values(array_diff($values, [Ticket::SOURCE_NONE]));

        if ($operator === 'not_in') {
            $query->where(function ($q) use ($sources, $none) {
                if ($none) {
                    $q->whereNotNull('source')->where('source', '!=', '');
                    if ($sources) {
                        $q->whereNotIn('source', $sources);
                    }
                } else {
                    $q->whereNotIn('source', $sources)->orWhereNull('source');
                }
            });
            return;
        }

        $query->where(function ($q) use ($sources, $none) {
            if ($sources) {
                $q->whereIn('source', $sources);
            }
            if ($none) {
                $q->orWhereNull('source')->orWhere('source', '');
            }
        });
    }

    /**
     * before / after / date_equal a day, days_before / days_within N days, or a date_range.
     */
    private static function filterDate($query, $column, $operator, $value)
    {
        switch ($operator) {
            case 'before':
                $query->where($column, '<', $value . ' 23:59:59');
                break;
            case 'after':
                $query->where($column, '>', $value . ' 23:59:59');
                break;
            case 'date_equal':
                $query->where($column, 'LIKE', '%' . $value . '%');
                break;
            case 'days_before':
                $query->where($column, '<', gmdate('Y-m-d', time() - (int) $value * DAY_IN_SECONDS));
                break;
            case 'days_within':
                $query->whereBetween($column, [gmdate('Y-m-d', time() - (int) $value * DAY_IN_SECONDS), gmdate('Y-m-d') . ' 23:59:59']);
                break;
            case 'date_range':
                $range = array_values((array) $value);
                $query->whereBetween($column, [($range[0] ?? '') . ' 00:00:00', ($range[1] ?? '') . ' 23:59:59']);
                break;
            default:
                $query->where($column, $operator, $value);
        }
    }

    /**
     * tags / product: in (any of), not_in (none of), in_all (all of), not_in_all (not all of).
     */
    private static function filterRelation($query, $relation, $operator, $value)
    {
        $column = $relation === 'tags' ? 'tag_id' : 'product_id';
        $method = in_array($operator, ['not_in', 'not_in_all'], true) ? 'whereDoesntHave' : 'whereHas';

        if (in_array($operator, ['in_all', 'not_in_all'], true) && $value) {
            foreach ((array) $value as $item) {
                $query->{$method}($relation, fn($q) => $q->where($column, $item));
            }
            return;
        }

        $query->{$method}($relation, fn($q) => $q->whereIn($column, (array) $value));
    }
}
