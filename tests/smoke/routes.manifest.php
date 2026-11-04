<?php
/**
 * REST smoke-test manifest — every GET route of Fluent Support (core + pro),
 * plus the query-parameter variations the Vue admin app actually sends.
 *
 * Generated: 2026-08-08
 *
 * Sources of truth:
 *   - Core routes : app/Http/Routes/api.php                     (95 GET routes)
 *   - Pro routes  : ../fluent-support-pro/app/Http/routes.php   (29 GET routes)
 *   - Query params: resources/admin/**  (Vue 3 admin SPA; $get()/Rest.js call sites).
 *                   The pro plugin ships no `resources/` directory — all pro-gated
 *                   UI (workflows, timesheets, license, pro settings) lives in the
 *                   free plugin's admin app.
 *
 * All routes are relative to the REST namespace `fluent-support/v2`.
 *
 * Entry shape:
 *   label             unique, greppable description
 *   route             path after the namespace, leading slash, `{token}` placeholders
 *   params            optional query params (names copied verbatim from the frontend)
 *   needs             optional fixture resolver; supplies the `{token}` values
 *   ok                optional accepted status codes; defaults to [200, 201, 204]
 *   requires_constant present on every pro-plugin route
 *   skip              present when the entry cannot be exercised yet (reason given)
 *
 * Read-only mindset: nothing below mutates state. Two notes on that:
 *   - `notifications/*` are read-only; the frontend's read-marking is a separate
 *     POST (`/mark-all-read`, `/{id}/mark-read`) and is deliberately absent here.
 *   - `tickets/{id}/live_activity` GET registers agent presence server-side on some
 *     builds. It is kept (the admin app polls it constantly) but flagged here.
 */

$dateRange = ['2026-08-02', '2026-08-08'];

return [

    // ---- mailboxes ----
    [
        'label' => 'mailboxes: index',
        'route' => '/mailboxes',
    ],
    [
        'label' => 'mailboxes: get one',
        'route' => '/mailboxes/{id}',
        'needs' => 'mailbox',
    ],
    [
        'label' => 'mailboxes: tickets of a mailbox',
        'route' => '/mailboxes/{id}/tickets',
        'needs' => 'mailbox',
    ],
    [
        // MoveTicket.vue sends the full simple-filter payload against this route.
        'label'  => 'mailboxes: tickets of a mailbox (MoveTicket simple filters)',
        'route'  => '/mailboxes/{id}/tickets',
        'needs'  => 'mailbox',
        'params' => [
            'order_by'    => 'id',
            'order_type'  => 'DESC',
            'filter_type' => 'simple',
            'filters'     => [
                'status_type'  => '',
                'product_id'   => '',
                'mailbox_id'   => '{mailbox_id}',
                'customer_id'  => '',
                'ticket_title' => '',
                'notes'        => '',
            ],
            'page'     => 1,
            'per_page' => 10,
        ],
    ],
    [
        'label'  => 'mailboxes: email settings (single email type)',
        'route'  => '/mailboxes/{id}/email_settings',
        'needs'  => 'mailbox',
        'params' => ['email_type' => 'ticket_created_email_to_customer'],
    ],
    [
        'label' => 'mailboxes: email configs',
        'route' => '/mailboxes/{id}/email_configs',
        'needs' => 'mailbox',
    ],

    // ---- tickets ----
    [
        'label' => 'tickets: my stats',
        'route' => '/tickets/my_stats',
    ],
    [
        'label'  => 'tickets: my stats (dashboard with-widgets)',
        'route'  => '/tickets/my_stats',
        'params' => [
            'with' => [
                'suggested_tickets',
                'overall_stats',
                'individual_stat',
                'ticket_to_watch',
                'tickets_by_products',
            ],
        ],
    ],
    [
        'label'  => 'tickets: my stats (dashboard incl. agent_today_stats)',
        'route'  => '/tickets/my_stats',
        'params' => [
            'with' => [
                'suggested_tickets',
                'overall_stats',
                'individual_stat',
                'ticket_to_watch',
                'tickets_by_products',
                'agent_today_stats',
            ],
        ],
    ],
    [
        'label' => 'tickets: agent performance',
        'route' => '/tickets/agent_performance',
    ],
    [
        'label' => 'tickets: index',
        'route' => '/tickets',
    ],
    [
        'label'  => 'tickets: index (simple filter defaults)',
        'route'  => '/tickets',
        'params' => [
            'page'        => 1,
            'per_page'    => 10,
            'order_by'    => 'last_customer_response',
            'order_type'  => 'ASC',
            'filter_type' => 'simple',
            'search'      => '',
            'filters'     => [
                'status_type'       => 'open',
                'product_id'        => [],
                'agent_id'          => [],
                'agent_group'       => [],
                'priority'          => [],
                'client_priority'   => [],
                'waiting_for_reply' => '',
                'ticket_tags'       => [],
                'mailbox_id'        => [],
                'watcher'           => '',
            ],
        ],
    ],
    [
        'label'  => 'tickets: index (simple filter, closed tickets)',
        'route'  => '/tickets',
        'params' => [
            'page'        => 1,
            'per_page'    => 10,
            'filter_type' => 'simple',
            'filters'     => ['status_type' => 'closed'],
        ],
    ],
    [
        'label'  => 'tickets: index (simple filter by product + priority)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'filters'     => [
                'status_type' => 'open',
                'product_id'  => ['{product_id}'],
                'priority'    => ['critical'],
            ],
        ],
        'needs'  => 'product',
    ],
    [
        'label'  => 'tickets: index (simple filter by agent + mailbox)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'filters'     => [
                'status_type' => 'open',
                'agent_id'    => ['{agent_id}'],
            ],
        ],
        'needs'  => 'agent',
    ],
    [
        'label'  => 'tickets: index (simple filter by tag)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'filters'     => [
                'status_type' => 'open',
                'ticket_tags' => ['{tag_id}'],
            ],
        ],
        'needs'  => 'tag',
    ],
    [
        'label'  => 'tickets: index (simple filter, waiting for reply)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'filters'     => [
                'status_type'       => 'open',
                'waiting_for_reply' => 'yes',
            ],
        ],
    ],
    [
        'label'  => 'tickets: index (search term)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'search'      => 'test',
            'page'        => 1,
            'per_page'    => 10,
        ],
    ],
    [
        'label'  => 'tickets: index (page 2, large per_page)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'page'        => 2,
            'per_page'    => 50,
        ],
    ],
    [
        'label'  => 'tickets: index (sort by created_at DESC)',
        'route'  => '/tickets',
        'params' => [
            'filter_type' => 'simple',
            'order_by'    => 'created_at',
            'order_type'  => 'DESC',
        ],
    ],
    [
        // advanced_filters is a JSON *string*, not an array — the pro-only mode.
        'label'  => 'tickets: index (advanced filter mode, empty group)',
        'route'  => '/tickets',
        'params' => [
            'page'             => 1,
            'per_page'         => 10,
            'order_by'         => 'last_customer_response',
            'order_type'       => 'ASC',
            'filter_type'      => 'advanced',
            'advanced_filters' => '[[]]',
        ],
    ],
    [
        'label' => 'tickets: sidebar view counts',
        'route' => '/tickets/sidebar-counts',
    ],
    [
        'label' => 'tickets: label search list',
        'route' => '/tickets/label-search',
    ],
    [
        'label' => 'tickets: mentionable agents',
        'route' => '/tickets/{ticket_id}/mentionable-agents',
        'needs' => 'ticket',
    ],
    [
        'label'  => 'tickets: mentionable agents (mention query)',
        'route'  => '/tickets/{ticket_id}/mentionable-agents',
        'needs'  => 'ticket',
        'params' => ['search' => 'ad', 'limit' => 50],
    ],
    [
        'label' => 'tickets: get single ticket',
        'route' => '/tickets/{ticket_id}',
        'needs' => 'ticket',
    ],
    [
        'label'  => 'tickets: get single ticket (with fluentcrm profile)',
        'route'  => '/tickets/{ticket_id}',
        'needs'  => 'ticket',
        'params' => [
            'customer_id' => '{customer_id}',
            'with_data'   => ['fluentcrm_profile'],
        ],
    ],
    [
        'label' => 'tickets: sidebar widgets',
        'route' => '/tickets/{ticket_id}/widgets',
        'needs' => 'ticket',
    ],
    [
        'label'  => 'tickets: sidebar widgets (other tickets + extras)',
        'route'  => '/tickets/{ticket_id}/widgets',
        'needs'  => 'ticket',
        'params' => ['with' => ['other_tickets', 'extra_widgets']],
    ],
    [
        'label'  => 'tickets: sidebar widgets (other tickets page 2)',
        'route'  => '/tickets/{ticket_id}/widgets',
        'needs'  => 'ticket',
        'params' => ['page' => 2],
    ],
    [
        'label' => 'tickets: get draft response',
        'route' => '/tickets/{ticket_id}/draft',
        'needs' => 'ticket',
    ],
    [
        // NOTE: this GET records agent presence on some builds — the closest thing
        // to a write in this manifest. Kept because the admin app polls it.
        'label' => 'tickets: live activity (agent presence poll)',
        'route' => '/tickets/{ticket_id}/live_activity',
        'needs' => 'ticket',
    ],
    [
        'label' => 'tickets: custom data',
        'route' => '/tickets/{ticket_id}/custom-data',
        'needs' => 'ticket',
    ],
    [
        'label'  => 'tickets: custom data (rendered fields)',
        'route'  => '/tickets/{ticket_id}/custom-data',
        'needs'  => 'ticket',
        'params' => ['with' => ['rendered_fields']],
    ],
    [
        'label' => 'tickets: fluent-booking event types',
        'route' => '/tickets/fluent-booking/event-types',
        'ok'    => [200, 404],
    ],
    [
        'label' => 'tickets: fluent-booking availability',
        'route' => '/tickets/{ticket_id}/fluent-booking/availability',
        'needs' => 'ticket',
        'ok'    => [200, 404, 422],
    ],
    [
        'label'  => 'tickets: fluent-booking availability (next_3_days range)',
        'route'  => '/tickets/{ticket_id}/fluent-booking/availability',
        'needs'  => 'ticket',
        'params' => ['event_type_id' => 1, 'range' => 'next_3_days'],
        'ok'     => [200, 404, 422],
    ],
    [
        'label'  => 'tickets: fluent-booking availability (calendar month view)',
        'route'  => '/tickets/{ticket_id}/fluent-booking/availability',
        'needs'  => 'ticket',
        'params' => [
            'event_type_id'  => 1,
            'range'          => 'next_3_days',
            'calendar_month' => '2026-08',
        ],
        'ok'     => [200, 404, 422],
    ],
    [
        'label'  => 'tickets: fluent-booking availability (specific dates)',
        'route'  => '/tickets/{ticket_id}/fluent-booking/availability',
        'needs'  => 'ticket',
        'params' => [
            'event_type_id'  => 1,
            'range'          => 'specific_dates',
            'selected_dates' => ['2026-08-08', '2026-08-09'],
        ],
        'ok'     => [200, 404, 422],
    ],
    [
        'label' => 'tickets: fluent-booking meetings',
        'route' => '/tickets/{ticket_id}/fluent-booking/meetings',
        'needs' => 'ticket',
        'ok'    => [200, 404],
    ],
    [
        'label' => 'tickets: fluent-boards boards',
        'route' => '/tickets/fluent-boards/boards',
        // 400 with "Fluent Boards plugin is not installed" when the external
        // plugin is absent from the lab.
        'ok'    => [200, 400, 404],
    ],
    [
        'label' => 'tickets: fluent-boards stages of a board',
        'route' => '/tickets/fluent-boards/stages/{board_id}',
        'skip'  => 'no resolver for fluent_boards_board yet (external plugin fixture)',
    ],
    [
        'label' => 'tickets: search contact',
        'route' => '/tickets/search-contact',
        // 422 "Please provide search string" is the contract without a term;
        // the query-term variation below exercises the 200 path.
        'ok'    => [200, 422],
    ],
    [
        'label'  => 'tickets: search contact (query term)',
        'route'  => '/tickets/search-contact',
        'params' => ['search' => 'john'],
    ],
    [
        'label' => 'tickets: ping',
        'route' => '/tickets/ping',
    ],

    // ---- widgets ----
    [
        'label' => 'widgets: dynamic widget templates',
        'route' => '/widgets',
        // 422 "Invalid filter name" is the contract without a filter param;
        // the ticket-sidebar variation below exercises the 200 path.
        'ok'    => [200, 422],
    ],
    [
        'label'  => 'widgets: dynamic widget templates (ticket sidebar filter)',
        'route'  => '/widgets',
        'needs'  => 'ticket',
        'params' => [
            'filter' => 'ticket_sidebar',
            'data'   => ['ticket_id' => '{ticket_id}'],
        ],
    ],

    // ---- notifications ----
    [
        'label' => 'notifications: index',
        'route' => '/notifications',
    ],
    [
        'label'  => 'notifications: index (category/status/pagination)',
        'route'  => '/notifications',
        'params' => [
            'category' => 'all',
            'status'   => 'all',
            'page'     => 1,
            'per_page' => 10,
        ],
    ],
    [
        'label' => 'notifications: unread',
        'route' => '/notifications/unread',
    ],
    [
        'label'  => 'notifications: unread (category + limit)',
        'route'  => '/notifications/unread',
        'params' => ['category' => 'all', 'limit' => 8],
    ],
    [
        'label' => 'notifications: unread count',
        'route' => '/notifications/unread-count',
    ],

    // ---- products ----
    [
        'label' => 'products: index',
        'route' => '/products',
    ],
    [
        'label'  => 'products: index (paginated search)',
        'route'  => '/products',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => ''],
    ],
    [
        'label'  => 'products: index (RemoteSelector search)',
        'route'  => '/products',
        'params' => ['search' => 'pro', 'order_by' => 'id', 'order_type' => 'ASC'],
    ],
    [
        'label' => 'products: get one',
        'route' => '/products/{product_id}',
        'needs' => 'product',
    ],

    // ---- me / options ----
    [
        'label' => 'me: current user',
        'route' => '/me',
    ],
    [
        'label' => 'options: countries',
        'route' => '/options/countries',
    ],

    // ---- settings ----
    [
        'label'  => 'settings: global business settings (with fields)',
        'route'  => '/settings',
        'params' => ['settings_key' => 'global_business_settings', 'with' => ['fields']],
    ],
    [
        'label'  => 'settings: internal notification settings (with fields)',
        'route'  => '/settings',
        'params' => ['settings_key' => '_internal_notification_settings', 'with' => ['fields']],
    ],
    [
        'label' => 'settings: integration settings list',
        'route' => '/settings/integration-settings',
    ],
    [
        'label'  => 'settings: single integration (slack key)',
        'route'  => '/settings/integration',
        'params' => ['integration_key' => 'slack_settings'],
    ],
    [
        'label' => 'settings: pages list',
        'route' => '/settings/pages',
    ],
    [
        'label' => 'settings: recaptcha settings',
        'route' => '/settings/recaptcha-settings',
    ],
    [
        'label' => 'settings: integration statuses',
        'route' => '/settings/integration-statuses',
    ],
    [
        'label' => 'settings: fluentcrm settings',
        'route' => '/settings/fluentcrm-settings',
    ],
    [
        'label' => 'settings: remote upload settings',
        'route' => '/settings/remote-upload-settings',
    ],
    [
        'label' => 'settings: AI provider settings',
        'route' => '/settings/ai-integration',
    ],
    [
        'label' => 'settings: settings menu',
        'route' => '/settings/settings-menu',
    ],
    [
        'label' => 'settings: MCP status',
        'route' => '/settings/mcp',
    ],
    [
        'label' => 'settings: MCP config snippets',
        'route' => '/settings/mcp/config-snippets',
    ],
    [
        'label'  => 'settings: MCP config snippets (local dev)',
        'route'  => '/settings/mcp/config-snippets',
        'params' => ['local_dev' => 'yes'],
    ],

    // ---- system tools ----
    [
        'label' => 'system tools: cron status',
        'route' => '/system-tools/cron',
    ],
    [
        'label' => 'system tools: database health',
        'route' => '/system-tools/database',
    ],
    [
        'label'  => 'system tools: cleanup counts',
        'route'  => '/system-tools/cleanup',
        'params' => ['days_before' => 30, 'selected_logs' => ['activity_logs']],
    ],

    // ---- agents ----
    [
        'label' => 'agents: index',
        'route' => '/agents',
    ],
    [
        'label'  => 'agents: index (paginated search)',
        'route'  => '/agents',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => ''],
    ],
    [
        // Fallback-agent picker sends per_page + search with no page key.
        'label'  => 'agents: index (fallback picker, no page key)',
        'route'  => '/agents',
        'params' => ['per_page' => 50, 'search' => 'adm'],
    ],

    // ---- agent-groups ----
    [
        'label' => 'agent-groups: index',
        'route' => '/agent-groups',
    ],
    [
        'label'  => 'agent-groups: index (paginated search)',
        'route'  => '/agent-groups',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => ''],
    ],
    [
        'label' => 'agent-groups: get one',
        'route' => '/agent-groups/{group_id}',
        'skip'  => 'no resolver for agent_group yet',
    ],

    // ---- reports ----
    [
        'label' => 'reports: agents summary',
        'route' => '/reports/agents-summary',
    ],
    [
        'label'  => 'reports: agents summary (from/to)',
        'route'  => '/reports/agents-summary',
        'params' => ['from' => '2026-08-02', 'to' => '2026-08-08'],
    ],
    [
        'label' => 'reports: day time stats',
        'route' => '/reports/day-time-stats',
    ],
    [
        'label'  => 'reports: day time stats (ticket report type)',
        'route'  => '/reports/day-time-stats',
        'params' => [
            'report_type' => 'ticket',
            'agent_id'    => '',
            'date_range'  => $dateRange,
        ],
    ],
    [
        'label'  => 'reports: day time stats (response report type, per agent)',
        'route'  => '/reports/day-time-stats',
        'needs'  => 'agent',
        'params' => [
            'report_type' => 'response',
            'agent_id'    => '{agent_id}',
            'date_range'  => $dateRange,
        ],
    ],
    [
        'label' => 'reports: stats',
        'route' => '/reports/stats',
    ],
    [
        'label'  => 'reports: stats (sidebar, date range only)',
        'route'  => '/reports/stats',
        'params' => ['date_range' => $dateRange],
    ],
    [
        'label'  => 'reports: stats (sidebar, filtered by mailbox)',
        'route'  => '/reports/stats',
        'needs'  => 'mailbox',
        'params' => ['mailbox_id' => '{mailbox_id}', 'date_range' => $dateRange],
    ],
    [
        'label'  => 'reports: stats (sidebar, filtered by product)',
        'route'  => '/reports/stats',
        'needs'  => 'product',
        'params' => ['product_id' => '{product_id}', 'date_range' => $dateRange],
    ],
    [
        'label'  => 'reports: stats (personal report, filtered by agent)',
        'route'  => '/reports/stats',
        'needs'  => 'agent',
        'params' => ['agent_id' => '{agent_id}', 'date_range' => $dateRange],
    ],

    // ---- my-reports ----
    [
        'label' => 'my-reports: tickets resolve growth',
        'route' => '/my-reports/tickets-resolve-growth',
    ],
    [
        'label'  => 'my-reports: tickets resolve growth (date range)',
        'route'  => '/my-reports/tickets-resolve-growth',
        'params' => ['date_range' => $dateRange],
    ],
    [
        'label' => 'my-reports: response growth',
        'route' => '/my-reports/response-growth',
    ],
    [
        'label'  => 'my-reports: response growth (date range)',
        'route'  => '/my-reports/response-growth',
        'params' => ['date_range' => $dateRange],
    ],
    [
        'label' => 'my-reports: personal summary',
        'route' => '/my-reports/my-summary',
    ],
    [
        'label'  => 'my-reports: personal summary (from/to)',
        'route'  => '/my-reports/my-summary',
        'params' => ['from' => '2026-08-02', 'to' => '2026-08-08'],
    ],

    // ---- product-reports ----

    // ---- mailbox-reports ----
    [
        'label' => 'mailbox-reports: tickets growth',
        'route' => '/mailbox-reports/tickets-growth',
    ],
    [
        'label'  => 'mailbox-reports: tickets growth (date range + mailbox)',
        'route'  => '/mailbox-reports/tickets-growth',
        'needs'  => 'mailbox',
        'params' => ['date_range' => $dateRange, 'mailbox_id' => '{mailbox_id}', 'type' => 'mailbox'],
    ],
    [
        'label' => 'mailbox-reports: tickets resolve growth',
        'route' => '/mailbox-reports/tickets-resolve-growth',
    ],
    [
        'label'  => 'mailbox-reports: tickets resolve growth (date range + mailbox)',
        'route'  => '/mailbox-reports/tickets-resolve-growth',
        'needs'  => 'mailbox',
        'params' => ['date_range' => $dateRange, 'mailbox_id' => '{mailbox_id}', 'type' => 'mailbox'],
    ],
    [
        'label' => 'mailbox-reports: response growth',
        'route' => '/mailbox-reports/response-growth',
    ],
    [
        'label'  => 'mailbox-reports: response growth (date range + mailbox)',
        'route'  => '/mailbox-reports/response-growth',
        'needs'  => 'mailbox',
        'params' => ['date_range' => $dateRange, 'mailbox_id' => '{mailbox_id}', 'type' => 'mailbox'],
    ],

    // ---- agent-group-reports ----

    // ---- customers ----
    [
        'label' => 'customers: index',
        'route' => '/customers',
    ],
    [
        'label'  => 'customers: index (paginated, empty search/status)',
        'route'  => '/customers',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'status' => ''],
    ],
    [
        'label'  => 'customers: index (status filter)',
        'route'  => '/customers',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'status' => 'active'],
    ],
    [
        'label'  => 'customers: index (search term)',
        'route'  => '/customers',
        'params' => ['per_page' => 10, 'page' => 1, 'search' => 'john', 'status' => ''],
    ],
    [
        'label'  => 'customers: index (RemoteSelector search + ordering)',
        'route'  => '/customers',
        'params' => ['search' => 'john', 'order_by' => 'id', 'order_type' => 'ASC'],
    ],
    [
        'label'  => 'customers: index (sorted by last activity)',
        'route'  => '/customers',
        'params' => ['per_page' => 20, 'page' => 1, 'sort_by' => 'last_response_at', 'sort_type' => 'ASC'],
    ],
    [
        'label'  => 'customers: index (has open tickets)',
        'route'  => '/customers',
        'params' => ['per_page' => 20, 'page' => 1, 'has_open_tickets' => 'yes', 'status' => 'active'],
    ],
    [
        'label' => 'customers: customer fields',
        'route' => '/customers/customerField/{customer_id}',
        'needs' => 'customer',
    ],
    [
        'label'  => 'customers: customer fields (with user_id)',
        'route'  => '/customers/customerField/{customer_id}',
        'needs'  => 'customer',
        'params' => ['user_id' => 0],
    ],
    [
        'label' => 'customers: get one',
        'route' => '/customers/{customer_id}',
        'needs' => 'customer',
    ],
    [
        'label'  => 'customers: get one (with widgets, tickets, crm profile)',
        'route'  => '/customers/{customer_id}',
        'needs'  => 'customer',
        'params' => ['with' => ['widgets', 'tickets', 'fluentcrm_profile']],
    ],
    [
        'label'  => 'customers: get one (with ticket_stats)',
        'route'  => '/customers/{customer_id}',
        'needs'  => 'customer',
        'params' => ['with' => ['ticket_stats']],
    ],

    // ---- customer-portal ----
    [
        'label' => 'customer-portal: public options',
        'route' => '/customer-portal/public_options',
    ],
    [
        'label' => 'customer-portal: rendered custom fields',
        'route' => '/customer-portal/custom-fields-rendered',
    ],
    [
        'label' => 'customer-portal: tickets',
        'route' => '/customer-portal/tickets',
    ],
    [
        'label'  => 'customer-portal: tickets (paginated + status filter)',
        'route'  => '/customer-portal/tickets',
        'params' => ['page' => 1, 'per_page' => 10, 'status' => 'active'],
    ],
    [
        'label' => 'customer-portal: single ticket',
        'route' => '/customer-portal/tickets/{ticket_id}',
        'needs' => 'ticket',
        // The smoke identity is the lab admin, who has no fs_persons customer
        // row; the portal answers 404, as for any ticket the caller can't see.
        'ok'    => [200, 404],
    ],
    [
        'label' => 'customer-portal: me',
        'route' => '/customer-portal/me',
    ],

    // ---- public ----
    [
        // OAuth callback for Help Scout; returns an error payload without a code.
        'label' => 'public: helpscout authorize callback',
        'route' => '/public/authorize',
        'ok'    => [200, 302, 400, 401, 403, 422],
    ],

    // ---- activity-logger ----
    [
        'label' => 'activity-logger: activities',
        'route' => '/activity-logger',
    ],
    [
        'label'  => 'activity-logger: activities (paginated, empty filters/date)',
        'route'  => '/activity-logger',
        'params' => [
            'per_page' => 10,
            'page'     => 1,
            'filters'  => [],
            'from'     => '',
            'to'       => '',
        ],
    ],
    [
        'label'  => 'activity-logger: activities (date range)',
        'route'  => '/activity-logger',
        'params' => [
            'per_page' => 10,
            'page'     => 1,
            'filters'  => [],
            'from'     => '2026-08-02',
            'to'       => '2026-08-08',
        ],
    ],
    [
        'label' => 'activity-logger: settings',
        'route' => '/activity-logger/settings',
    ],

    // ---- ai-activity-logger ----
    [
        'label' => 'ai-activity-logger: activities',
        'route' => '/ai-activity-logger',
    ],
    [
        'label'  => 'ai-activity-logger: activities (paginated + date range)',
        'route'  => '/ai-activity-logger',
        'params' => [
            'per_page' => 10,
            'page'     => 1,
            'filters'  => [],
            'from'     => '2026-08-02',
            'to'       => '2026-08-08',
        ],
    ],
    [
        'label' => 'ai-activity-logger: settings',
        'route' => '/ai-activity-logger/settings',
    ],

    // ---- ticket_importer ----
    [
        'label' => 'ticket_importer: import stats',
        'route' => '/ticket_importer',
    ],

    /*
     |--------------------------------------------------------------------------
     | PRO ROUTES — fluent-support-pro/app/Http/routes.php
     | Same REST namespace (fluent-support/v2); every entry below is gated on
     | the FLUENTSUPPORTPRO constant.
     |--------------------------------------------------------------------------
     */

    // ---- ticket-tags (pro) ----
    [
        'label'             => 'ticket-tags: index',
        'route'             => '/ticket-tags',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ticket-tags: index (paginated search)',
        'route'             => '/ticket-tags',
        'params'            => ['per_page' => 10, 'page' => 1, 'search' => ''],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ticket-tags: index (search term, page 2)',
        'route'             => '/ticket-tags',
        'params'            => ['per_page' => 10, 'page' => 2, 'search' => 'bug'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ticket-tags: options',
        'route'             => '/ticket-tags/options',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ticket-tags: get one',
        'route'             => '/ticket-tags/{tag_id}',
        // TicketTag is fs_taggables scoped to tag_type = 'ticket_tag' — a
        // plain tag id 404s here, so this needs its own resolver + seed row.
        'needs'             => 'ticket_tag',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- saved-replies (pro) ----
    [
        'label'             => 'saved-replies: index',
        'route'             => '/saved-replies',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'saved-replies: index (paginated search)',
        'route'             => '/saved-replies',
        'params'            => ['page' => 1, 'per_page' => 10, 'search' => ''],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'saved-replies: index (template inserter, scoped to product)',
        'route'             => '/saved-replies',
        'needs'             => 'product',
        'params'            => [
            'page'       => 1,
            'per_page'   => 10,
            'search'     => '',
            'product_id' => '{product_id}',
        ],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'saved-replies: get one',
        'route'             => '/saved-replies/{id}',
        'needs'             => 'saved_reply',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- ticket-custom-fields (pro) ----
    [
        'label'             => 'ticket-custom-fields: index',
        'route'             => '/ticket-custom-fields',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ticket-custom-fields: index (with field types, paginated)',
        'route'             => '/ticket-custom-fields',
        'params'            => ['with' => ['field_types'], 'per_page' => 10, 'page' => 1],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- workflows (pro) ----
    [
        'label'             => 'workflows: index',
        'route'             => '/workflows',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: index (paginated search)',
        'route'             => '/workflows',
        'params'            => ['per_page' => 10, 'page' => 1, 'search' => ''],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: index (search term)',
        'route'             => '/workflows',
        'params'            => ['per_page' => 10, 'page' => 1, 'search' => 'auto'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: options',
        'route'             => '/workflows/options',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: commerce options',
        'route'             => '/workflows/commerce-options',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: commerce options (provider search shape)',
        'route'             => '/workflows/commerce-options',
        'params'            => ['provider' => 'woo', 'type' => 'products', 'search' => ''],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        // Hydration shape: `ids` is a comma-joined string, not an array.
        'label'             => 'workflows: commerce options (id hydration shape)',
        'route'             => '/workflows/commerce-options',
        'params'            => ['provider' => 'woo', 'type' => 'products', 'ids' => '1,2,3'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: get one',
        'route'             => '/workflows/{workflow_id}',
        'needs'             => 'workflow',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: get one (with action + trigger fields)',
        'route'             => '/workflows/{workflow_id}',
        'needs'             => 'workflow',
        'params'            => ['with' => ['action_fields', 'trigger_fields']],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: actions of a workflow',
        'route'             => '/workflows/{workflow_id}/actions',
        'needs'             => 'workflow',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'workflows: latest runs of a workflow',
        'route'             => '/workflows/{workflow_id}/runs',
        'needs'             => 'workflow',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- email-box (pro) ----
    [
        'label'             => 'email-box: pipe status',
        'route'             => '/email-box/{box_id}/status',
        'needs'             => 'mailbox',
        // The lab's seeded mailbox is box_type 'web'; the endpoint answers 422
        // "no email piping is available" for web inboxes.
        'ok'                => [200, 422],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- customer-portal doc search (pro) ----
    [
        'label'             => 'customer-portal: doc suggestion search',
        'route'             => '/customer-portal/search-doc',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'customer-portal: doc suggestion search (query term)',
        'route'             => '/customer-portal/search-doc',
        'params'            => ['search' => 'login'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- pro ----
    [
        'label'             => 'pro: license status',
        'route'             => '/pro/license',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        // verify=true makes the endpoint call the remote licensing API.
        'label'             => 'pro: license status (remote verify)',
        'route'             => '/pro/license',
        'params'            => ['verify' => true],
        'ok'                => [200, 400, 422],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'pro: ticket form settings',
        'route'             => '/pro/form-settings',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'pro: ticket form settings (with fields)',
        'route'             => '/pro/form-settings',
        'params'            => ['with' => ['fields']],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- settings (pro) ----
    [
        'label'             => 'settings: incoming webhook',
        'route'             => '/settings/incoming-webhook',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: auto close',
        'route'             => '/settings/auto-close',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: remote portal',
        'route'             => '/settings/remote-portal',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'remote portal: ping (connector only)',
        'route'             => '/remote-portal/ping',
        // Only the user who connected the main site passes RemotePortalPolicy; the smoke admin is not that user.
        'ok'                => [403],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'remote portal: portal config (connector only)',
        'route'             => '/remote-portal/portal-config',
        'ok'                => [403],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: auto close (settings_key + fields)',
        'route'             => '/settings/auto-close',
        'params'            => ['settings_key' => 'auto_close_settings', 'with' => ['fields']],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: upload integration',
        'route'             => '/settings/upload_integration',
        // 422 "Invalid integration key." without ?integration_key=; the
        // dropbox/google_drive variations below exercise the 200 path.
        'ok'                => [200, 422],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: upload integration (dropbox driver)',
        'route'             => '/settings/upload_integration',
        'params'            => ['integration_key' => 'dropbox'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: upload integration (google drive driver)',
        'route'             => '/settings/upload_integration',
        'params'            => ['integration_key' => 'google_drive'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: upload integration (amazon s3 driver)',
        'route'             => '/settings/upload_integration',
        'params'            => ['integration_key' => 'amazon_s3'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'settings: upload integration (s3-compatible driver)',
        'route'             => '/settings/upload_integration',
        'params'            => ['integration_key' => 's3_compatible'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- public (pro) ----
    [
        'label'             => 'public: dropbox oauth callback',
        'route'             => '/public/dropbox_auth',
        'ok'                => [200, 302, 400, 401, 403, 422],
        'requires_constant' => 'FLUENTSUPPORTPRO',
        'skip'              => 'controller calls wp_redirect unconditionally; under WP-CLI the ' .
            'redirect handler terminates the whole runner process (browser OAuth flow, not ' .
            'smoke-testable in-process)',
    ],
    [
        'label'             => 'public: google drive oauth callback',
        'route'             => '/public/google_auth',
        'ok'                => [200, 302, 400, 401, 403, 422],
        'requires_constant' => 'FLUENTSUPPORTPRO',
        'skip'              => 'controller calls wp_redirect unconditionally; under WP-CLI the ' .
            'redirect handler terminates the whole runner process (browser OAuth flow, not ' .
            'smoke-testable in-process)',
    ],

    // ---- integrations/fluentcrm (pro) ----

    // ---- tickets (pro) ----
    [
        'label'             => 'tickets: customer tickets',
        'route'             => '/tickets/customer_tickets/{customer_id}',
        'needs'             => 'customer',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'tickets: customer tickets (paginated, excluding current)',
        'route'             => '/tickets/customer_tickets/{customer_id}',
        'needs'             => 'customer',
        'params'            => [
            'exclude_ticket_id' => '{ticket_id}',
            'page'              => 1,
            'per_page'          => 10,
        ],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- time-tracks (pro) ----
    [
        'label'             => 'time-tracks: tracks of a ticket',
        'route'             => '/time-tracks/{ticket_id}',
        'needs'             => 'ticket',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- ai (pro) ----
    [
        'label'             => 'ai: preset prompts',
        'route'             => '/ai/preset-prompts',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ai: preset prompts (createResponse)',
        'route'             => '/ai/preset-prompts',
        'params'            => ['type' => 'createResponse'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'ai: preset prompts (modifyResponse)',
        'route'             => '/ai/preset-prompts',
        'params'            => ['type' => 'modifyResponse'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- reports/timesheet (pro) ----
    [
        'label'             => 'reports: timesheet by tickets',
        'route'             => '/reports/timesheet/by-tickets',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by tickets (date range, all mailboxes)',
        'route'             => '/reports/timesheet/by-tickets',
        'params'            => ['mailbox_id' => [], 'date_range' => $dateRange],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by tickets (date range, one mailbox)',
        'route'             => '/reports/timesheet/by-tickets',
        'needs'             => 'mailbox',
        'params'            => ['mailbox_id' => ['{mailbox_id}'], 'date_range' => $dateRange],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by agents',
        'route'             => '/reports/timesheet/by-agents',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by agents (date range, one agent)',
        'route'             => '/reports/timesheet/by-agents',
        'needs'             => 'agent',
        'params'            => ['agent_id' => ['{agent_id}'], 'date_range' => $dateRange],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by customers',
        'route'             => '/reports/timesheet/by-customers',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'reports: timesheet by customers (date range, one customer)',
        'route'             => '/reports/timesheet/by-customers',
        'needs'             => 'customer',
        'params'            => ['customer_id' => ['{customer_id}'], 'date_range' => $dateRange],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],

    // ---- advanced-reports (pro) ----
    // from/to are Y-m-d dates; the audit endpoints also need from <= to and a
    // span of at most one year.
    [
        'label'             => 'advanced-reports: ticket overview',
        'route'             => '/advanced-reports/ticket-overview',
        'params'            => ['from' => $dateRange[0], 'to' => $dateRange[1]],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: response activity',
        'route'             => '/advanced-reports/response-activity',
        'params'            => ['from' => $dateRange[0], 'to' => $dateRange[1]],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: product insights',
        'route'             => '/advanced-reports/product-insights',
        'params'            => ['from' => $dateRange[0], 'to' => $dateRange[1]],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: workload health',
        'route'             => '/advanced-reports/workload-health',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: performance overview filters',
        'route'             => '/advanced-reports/performance-overview/filters',
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: performance overview',
        'route'             => '/advanced-reports/performance-overview',
        'params'            => ['from' => $dateRange[0], 'to' => $dateRange[1]],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        // report_type is required and must be one of SnapshotService::REPORT_TYPES.
        'label'             => 'advanced-reports: snapshots index',
        'route'             => '/advanced-reports/snapshots',
        'params'            => ['report_type' => 'ticket_overview'],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
    [
        'label'             => 'advanced-reports: snapshot by id',
        'route'             => '/advanced-reports/snapshots/{id}',
        'requires_constant' => 'FLUENTSUPPORTPRO',
        'skip'              => 'no snapshot fixture resolver — snapshots are written by cron, not by the suite',
    ],
    [
        'label'             => 'advanced-reports: audit results',
        'route'             => '/advanced-reports/audit/results',
        'params'            => ['from' => $dateRange[0], 'to' => $dateRange[1]],
        'requires_constant' => 'FLUENTSUPPORTPRO',
    ],
];
