<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

/**
 * Turns customer search box text into a targeted query against fs_persons.
 * Ported from FluentCRM's ContactSearch so both plugins read the same syntax.
 *
 * Syntax understood by parse():
 *   timothy cook           every word must match first name, last name or email
 *   "timothy cook"         the phrase must appear in the full name or email
 *   jane@acme.com          email (exact match when it is a full address)
 *   #123                   customer ID
 *   email:acme.com         scoped to one field; see FIELDS for the full list
 *   city:"new york"        quoted value for a scoped field
 *   status:blocked         exact status (active, blocked)
 *   tickets:>2  open:0     ticket counts, compared with = > >= < <=
 *   created:2026-05        created at (year, month or day; or >date, <date); since: is an alias
 *   activity:<2026-01-01   last activity date, same forms as created:
 *
 * Scoped terms and free text combine with AND. A `key:value` whose key is not a
 * known field is treated as ordinary text, so URLs and times still search.
 */
class CustomerSearch
{
    /**
     * Scoped search keys => the field they search. Aliases share a target.
     */
    const FIELDS = [
        'id'          => 'id',
        'email'       => 'email',
        'name'        => 'name',
        'first_name'  => 'first_name',
        'first'       => 'first_name',
        'last_name'   => 'last_name',
        'last'        => 'last_name',
        'title'       => 'title',
        'address'     => 'address',
        'city'        => 'city',
        'state'       => 'state',
        'zip'         => 'zip',
        'postal_code' => 'zip',
        'country'     => 'country',
        'status'      => 'status',
        'user'        => 'user_id',
        'user_id'     => 'user_id',
        'tickets'     => 'tickets',
        'open'        => 'open_tickets',
        'created'     => 'created_at',
        'since'       => 'created_at',
        'activity'    => 'last_response_at',
    ];

    /**
     * Most terms read from one search. Each term is a LIKE scan or a subquery.
     */
    const MAX_TERMS = 20;

    /**
     * Parse search text into a plan. Pure: reads nothing from the database.
     *
     * @param string $raw The text from the search box.
     * @return array{
     *     mode: string,
     *     text: string,
     *     words: string[],
     *     phrases: string[],
     *     ids: int[],
     *     scoped: array<int, array{field: string, key: string, value: string}>,
     *     unknown: string[]
     * } `mode` is one of: empty, words, email, id, scoped.
     */
    public static function parse($raw)
    {
        $plan = [
            'mode'    => 'empty',
            'text'    => '',
            'words'   => [],
            'phrases' => [],
            'ids'     => [],
            'scoped'  => [],
            'unknown' => [],
        ];

        // sanitize_text_field() encodes a lone "<" (tickets:<2); values only reach bound parameters.
        $raw = trim(str_replace(['&lt;', '&gt;'], ['<', '>'], (string) $raw));
        if ($raw === '') {
            return $plan;
        }

        // key:"quoted value" | key:value (value may be empty) | "quoted phrase" | bare token.
        // Keys allow hyphens and non-ASCII letters.
        $matched = preg_match_all('/([^\s:"#][^\s:"]*):"([^"]*)"?|([^\s:"#][^\s:"]*):(\S*)|"([^"]*)"?|(\S+)/u', $raw, $matches, PREG_SET_ORDER);

        // Invalid UTF-8 fails the /u regex: match nothing rather than drop the search.
        if ($matched === false) {
            $plan['mode'] = 'scoped';
            $plan['scoped'][] = ['field' => 'id', 'key' => 'id', 'value' => '-1'];
            return $plan;
        }

        $matches = array_slice($matches, 0, self::MAX_TERMS);

        $freeParts = [];
        $count = count($matches);
        for ($i = 0; $i < $count; $i++) {
            $match = $matches[$i];
            $key = '';
            $value = '';
            $quoted = false;
            if (isset($match[1]) && $match[1] !== '') {
                $key = $match[1];
                $value = $match[2];
                $quoted = true;
            } elseif (isset($match[3]) && $match[3] !== '') {
                $key = $match[3];
                $value = $match[4];
            }

            if ($key !== '') {
                $lowerKey = self::lower($key);
                $value = trim($value);

                if (!isset(self::FIELDS[$lowerKey])) {
                    // Unknown key: search as plain text and report it. Times and URLs are not reported.
                    if (preg_match('/\p{L}/u', $lowerKey) && strpos($value, '//') !== 0) {
                        $plan['unknown'][] = $lowerKey;
                    }
                    $freeParts[] = ['type' => 'word', 'value' => $match[0]];
                    continue;
                }

                // "city: berlin": the value is the next token.
                if ($value === '' && !$quoted && $i + 1 < $count && self::isPlainToken($matches[$i + 1])) {
                    $next = $matches[++$i];
                    $value = trim(isset($next[6]) && $next[6] !== '' ? $next[6] : $next[5]);
                }

                // An empty value matches nothing, so select-all bulk actions never hit every customer.
                $plan['scoped'][] = ['field' => self::FIELDS[$lowerKey], 'key' => $lowerKey, 'value' => $value];
                continue;
            }

            if (isset($match[5]) && $match[0][0] === '"') {
                $phrase = trim($match[5]);
                if ($phrase !== '') {
                    $freeParts[] = ['type' => strpos($phrase, ' ') === false ? 'word' : 'phrase', 'value' => $phrase];
                }
                continue;
            }

            $token = $match[6];
            if (preg_match('/^#(\d+)$/', $token, $idMatch)) {
                $plan['ids'][] = (int) $idMatch[1];
                continue;
            }

            $freeParts[] = ['type' => 'word', 'value' => $token];
        }

        $plan['text'] = trim(implode(' ', array_column($freeParts, 'value')));
        foreach ($freeParts as $part) {
            $plan[$part['type'] === 'phrase' ? 'phrases' : 'words'][] = $part['value'];
        }

        if ($plan['text'] !== '') {
            $plan['mode'] = (!$plan['phrases'] && strpos($plan['text'], ' ') === false && strpos($plan['text'], '@') !== false)
                ? 'email'
                : 'words';
        } elseif ($plan['ids']) {
            $plan['mode'] = 'id';
        } elseif ($plan['scoped']) {
            $plan['mode'] = 'scoped';
        }

        return $plan;
    }

    /**
     * Apply the search to a Customer query, wrapped in one AND group so it
     * can never widen other filters (status tab, open-tickets view).
     *
     * @param \FluentSupport\Framework\Database\Orm\Builder $query Customer query.
     * @param string|array $search Raw search text, or a plan from parse().
     * @return \FluentSupport\Framework\Database\Orm\Builder
     */
    public static function apply($query, $search)
    {
        $plan = is_array($search) ? $search : self::parse($search);
        if ($plan['mode'] === 'empty') {
            return $query;
        }

        return $query->where(function ($group) use ($plan) {
            if ($plan['ids']) {
                $group->whereIn('id', $plan['ids']);
            }

            foreach ($plan['scoped'] as $term) {
                self::applyScopedTerm($group, $term);
            }

            if ($plan['text'] !== '') {
                self::applyFreeText($group, $plan);
            }
        });
    }

    /**
     * Whether orderByRelevance() would rank this search. Searches under 3 characters,
     * ID and scoped-only searches keep the chosen column order.
     *
     * @param string|array $search
     * @return bool
     */
    public static function isRankable($search)
    {
        $plan = is_array($search) ? $search : self::parse($search);
        $length = function_exists('mb_strlen') ? mb_strlen($plan['text']) : strlen($plan['text']);

        return $length >= 3 && in_array($plan['mode'], ['words', 'email'], true);
    }

    /**
     * Order a searched query by match quality, then newest first.
     *
     * @param \FluentSupport\Framework\Database\Orm\Builder $query
     * @param string|array $search Raw search text, or a plan from parse().
     * @return \FluentSupport\Framework\Database\Orm\Builder
     */
    public static function orderByRelevance($query, $search)
    {
        $plan = is_array($search) ? $search : self::parse($search);

        if (!self::isRankable($plan)) {
            return $query->orderBy('id', 'DESC');
        }

        global $wpdb;
        $text = trim(str_replace('"', '', $plan['text']));
        $words = array_merge($plan['words'], $plan['phrases']);
        $firstWord = $words ? $words[0] : $text;
        $textPrefix = $wpdb->esc_like($text) . '%';
        $wordPrefix = $wpdb->esc_like($firstWord) . '%';

        $query->orderByRaw(
            "CASE
                WHEN email = ? THEN 0
                WHEN CONCAT_WS(' ', first_name, last_name) = ? THEN 1
                WHEN CONCAT_WS(' ', last_name, first_name) = ? THEN 1
                WHEN CONCAT_WS(' ', first_name, last_name) LIKE ? THEN 2
                WHEN first_name LIKE ? OR last_name LIKE ? OR email LIKE ? THEN 3
                ELSE 4
            END",
            [$text, $text, $text, $textPrefix, $wordPrefix, $wordPrefix, $textPrefix]
        );

        return $query->orderBy('id', 'DESC');
    }

    /**
     * How the search was understood, for the UI. Null for an empty search.
     *
     * @param string $raw
     * @param bool $ranked Whether the results were ordered by best match.
     * @return array|null
     */
    public static function describe($raw, $ranked = false)
    {
        $plan = self::parse($raw);
        if ($plan['mode'] === 'empty') {
            return null;
        }

        return [
            'mode'    => $plan['mode'],
            'text'    => $plan['text'],
            'words'   => array_values(array_merge($plan['words'], $plan['phrases'])),
            'ids'     => $plan['ids'],
            'scoped'  => array_map(function ($term) {
                return ['key' => $term['key'], 'field' => $term['field'], 'value' => $term['value']];
            }, $plan['scoped']),
            'unknown' => array_values(array_unique($plan['unknown'])),
            'ranked'  => (bool) $ranked,
        ];
    }

    /**
     * Free text with no scope.
     */
    private static function applyFreeText($query, $plan)
    {
        $text = $plan['text'];

        if ($plan['mode'] === 'email') {
            if (is_email($text)) {
                $query->where('email', $text);
            } else {
                $query->where('email', 'LIKE', self::contains($text));
            }
            return;
        }

        foreach ($plan['words'] as $word) {
            $like = self::contains($word);
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                    ->orWhere('last_name', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like);
            });
        }

        foreach ($plan['phrases'] as $phrase) {
            $like = self::contains($phrase);
            $query->where(function ($q) use ($like) {
                $q->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like])
                    ->orWhere('email', 'LIKE', $like);
            });
        }
    }

    /**
     * One `key:value` term. Every scoped term narrows the result (AND).
     */
    private static function applyScopedTerm($query, $term)
    {
        $value = $term['value'];

        if ($value === '') {
            // "city:" with nothing after it: match nothing rather than ignore it.
            $query->whereRaw('1 = 0');
            return;
        }

        switch ($term['field']) {
            case 'id':
                $ids = array_filter(array_map('absint', explode(',', $value)));
                if ($ids) {
                    $query->whereIn('id', $ids);
                } else {
                    $query->whereRaw('1 = 0');
                }
                return;
            case 'name':
                foreach (preg_split('/\s+/', $value) as $word) {
                    $like = self::contains($word);
                    $query->where(function ($q) use ($like) {
                        $q->where('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like);
                    });
                }
                return;
            case 'address':
                $like = self::contains($value);
                $query->where(function ($q) use ($like) {
                    $q->where('address_line_1', 'LIKE', $like)->orWhere('address_line_2', 'LIKE', $like);
                });
                return;
            case 'email':
                if (is_email($value)) {
                    $query->where('email', $value);
                } else {
                    $query->where('email', 'LIKE', self::contains($value));
                }
                return;
            case 'status':
                $status = self::statusValue($value);
                if ($status) {
                    $query->where('status', $status);
                } else {
                    $query->whereRaw('1 = 0');
                }
                return;
            case 'user_id':
                $userId = absint(ltrim($value, '#'));
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->whereRaw('1 = 0');
                }
                return;
            case 'tickets':
            case 'open_tickets':
                self::applyTicketCount($query, $value, $term['field'] === 'open_tickets');
                return;
            case 'created_at':
            case 'last_response_at':
                self::applyDate($query, $term['field'], $value);
                return;
            default:
                // Plain column; the key was whitelisted by FIELDS in parse().
                $query->where($term['field'], 'LIKE', self::contains($value));
        }
    }

    /**
     * Ticket-count terms: `tickets:3`, `tickets:>2`, `open:0`, `open:<=1`.
     * Counts only tickets the current agent may open, like the list's columns.
     */
    private static function applyTicketCount($query, $value, $openOnly)
    {
        $comparison = self::parseComparison($value);
        if (!$comparison || !ctype_digit($comparison[1])) {
            $query->whereRaw('1 = 0');
            return;
        }

        list($operator, $number) = $comparison;
        $number = (int) $number;

        // Does a customer with no (visible) tickets satisfy the term? Then select by
        // exclusion: everyone minus the customers whose count fails it.
        $zeroMatches = self::compare(0, $operator, $number);
        $havingOperator = $zeroMatches ? self::negate($operator) : $operator;

        // A NULL in the NOT IN list below would make it match nothing
        $counts = Ticket::select('customer_id')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) ' . $havingOperator . ' ?', [$number]);

        if ($openOnly) {
            $counts->whereIn('status', Helper::getTkStatusesByGroupName('open') ?: ['new', 'active']);
        }

        (new AgentTicketAccess())->applyAccessScope($counts);

        if ($zeroMatches) {
            $query->whereNotIn('id', $counts);
        } else {
            $query->whereIn('id', $counts);
        }
    }

    /**
     * Date terms: `created:2026`, `created:2026-05`, `created:2026-05-14`, `created:>2026-01-01`.
     * A bare date matches that whole year, month or day; a comparison uses its first day.
     */
    private static function applyDate($query, $column, $value)
    {
        $comparison = self::parseComparison($value);
        if (!$comparison || !preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $comparison[1], $parts)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $year = (int) $parts[1];
        $month = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : 0;
        $day = isset($parts[3]) && $parts[3] !== '' ? (int) $parts[3] : 0;

        if (($month && !checkdate($month, $day ?: 1, $year)) || (!$month && $day)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $start = sprintf('%04d-%02d-%02d 00:00:00', $year, $month ?: 1, $day ?: 1);
        if ($day) {
            $end = gmdate('Y-m-d 00:00:00', strtotime($start . ' +1 day'));
        } elseif ($month) {
            $end = gmdate('Y-m-d 00:00:00', strtotime($start . ' +1 month'));
        } else {
            $end = sprintf('%04d-01-01 00:00:00', $year + 1);
        }

        switch ($comparison[0]) {
            case '>':
                $query->where($column, '>=', $end);
                return;
            case '>=':
                $query->where($column, '>=', $start);
                return;
            case '<':
                $query->where($column, '<', $start);
                return;
            case '<=':
                $query->where($column, '<', $end);
                return;
            default:
                $query->where($column, '>=', $start)->where($column, '<', $end);
        }
    }

    /**
     * `>=5` => ['>=', '5']; `5` => ['=', '5']. Null when nothing follows the operator.
     */
    private static function parseComparison($value)
    {
        if (!preg_match('/^(>=|<=|>|<|=)?\s*(.+)$/', trim($value), $match)) {
            return null;
        }

        return [$match[1] !== '' ? $match[1] : '=', trim($match[2])];
    }

    private static function compare($left, $operator, $right)
    {
        switch ($operator) {
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case '<':
                return $left < $right;
            case '<=':
                return $left <= $right;
            default:
                return $left === $right;
        }
    }

    private static function negate($operator)
    {
        $opposites = ['>' => '<=', '>=' => '<', '<' => '>=', '<=' => '>', '=' => '!='];

        return $opposites[$operator];
    }

    /**
     * The stored status for a typed one; "blocked" is what the UI calls inactive.
     */
    private static function statusValue($value)
    {
        $map = [
            'active'   => 'active',
            'blocked'  => 'inactive',
            'inactive' => 'inactive',
        ];

        $value = self::lower($value);

        return isset($map[$value]) ? $map[$value] : null;
    }

    /**
     * Whether a parsed match is a bare token or quoted phrase that can serve
     * as the value of a preceding `key:` (not itself a `key:value`).
     */
    private static function isPlainToken($match)
    {
        return (isset($match[6]) && $match[6] !== '' && !preg_match('/^#\d+$/', $match[6]))
            || (isset($match[5]) && $match[0] !== '' && $match[0][0] === '"');
    }

    /**
     * Lowercase that keeps non-ASCII keys intact.
     */
    private static function lower($value)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * `%value%` with LIKE wildcards in the value escaped.
     */
    private static function contains($value)
    {
        global $wpdb;
        return '%' . $wpdb->esc_like($value) . '%';
    }
}
