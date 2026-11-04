<?php

namespace FluentSupport\App\Modules\MCP\Support;

use FluentSupport\App\Modules\PermissionManager;

/**
 * Optional one-line customer summary for MCP lists, supplied by integrations
 * through a batch filter. It can carry billing/CRM PII, so it's gated on
 * fst_sensitive_data like AdminSensitivePolicy.
 */
class CustomerMetaEnricher
{
    const MAX_LEN = 120;

    /**
     * [customer_id => line] for one list page. One filter call per page.
     *
     * @param iterable $tickets  The page of Ticket models (customer eager-loaded).
     * @param array    $context  Extra context passed to the filter (e.g. agent_id).
     * @return array<int,string>
     */
    public static function resolve($tickets, array $context = [])
    {
        if (!PermissionManager::userCan('fst_sensitive_data')) {
            return [];
        }

        $customers = [];
        foreach ($tickets as $ticket) {
            if ($ticket->customer_id && $ticket->relationLoaded('customer') && $ticket->customer) {
                $customers[$ticket->customer_id] = $ticket->customer;
            }
        }

        if (!$customers) {
            return [];
        }

        $customerIds = array_keys($customers);

        /**
         * Filter: fluent_support/mcp_customer_list_meta
         *
         * Inject a short, plain-text status line per customer for MCP list
         * surfaces, e.g. "Paying · 8,016 sent/30d · domain verified".
         *
         * Contract:
         *   - Batch: fired once per page. Use one query for all IDs, not one per customer.
         *     Loaded Customer models are passed to save a lookup.
         *   - Cheap: runs on every list render. No remote calls; cache anything expensive.
         *   - Plain text: tags and control chars are stripped and values capped at 120 chars.
         *     Non-scalar values and unknown IDs are dropped.
         *   - Additive: merge into the incoming map, keep existing keys.
         *
         * @param array<int,string> $lines       Default []. Merge, don't overwrite.
         * @param int[]             $customerIds Deduped customer IDs on this page.
         * @param array             $customers   [customer_id => loaded Customer model].
         * @param array             $context     ['surface' => string, 'agent_id' => int|null].
         * @return array<int,string>             [customer_id => plain-text line].
         */
        $raw = apply_filters(
            'fluent_support/mcp_customer_list_meta',
            [],
            $customerIds,
            $customers,
            $context
        );

        return self::sanitize($raw, $customerIds);
    }

    /**
     * Coerce the untrusted filter output into a safe [int => plain string] map.
     *
     * @param mixed $raw
     * @param int[] $allowedIds
     * @return array<int,string>
     */
    private static function sanitize($raw, array $allowedIds)
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = array_flip(array_map('intval', $allowedIds));
        $clean   = [];

        foreach ($raw as $id => $value) {
            $id = (int) $id;

            if (!isset($allowed[$id]) || !is_scalar($value)) {
                continue;
            }

            $value = wp_strip_all_tags((string) $value, true);
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
            $value = trim(preg_replace('/\s+/u', ' ', $value));

            if ($value === '') {
                continue;
            }

            $clean[$id] = function_exists('mb_substr')
                ? mb_substr($value, 0, self::MAX_LEN)
                : substr($value, 0, self::MAX_LEN);
        }

        return $clean;
    }
}
