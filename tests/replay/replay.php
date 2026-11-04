<?php
// Replays every REST route against the seeded data set and prints one REC line
// per call: status, body (dates masked) and the state of the seeded rows after
// it. Run once on develop and once on full-refactor from the same DB snapshot,
// then diff. Resumable: start=N; a call that ends the process prints DIED.
global $wpdb;
$ids = get_option('fs_replay_ids');
if (!$ids) {
    fwrite(STDERR, "no fs_replay_ids\n");
    return;
}
$start = 0;
foreach ((isset($args) ? $args : []) as $arg) {
    if (strpos($arg, 'start=') === 0) {
        $start = (int) substr($arg, 6);
    }
}

add_filter('pre_http_request', fn() => new WP_Error('replay_blocked', 'HTTP blocked in replay'), 1);
add_filter('pre_wp_mail', '__return_true', 1);

$from = '2026-09-01';
$to = '2026-10-31';
$range = ['date_range' => [$from, $to]];

// ---- GET routes the admin UI calls (smoke manifest), with its params ----
$calls = [];
$manifest = require ABSPATH . 'wp-content/plugins/fluent-support/tests/lab/tmp/replay/routes.manifest.php';
foreach (($manifest['routes'] ?? $manifest) as $entry) {
    if (!is_array($entry) || !isset($entry['route'])) {
        continue;
    }
    $route = $entry['route'];
    $idKey = strpos($route, '/saved-replies') === 0 ? 'saved_reply' : 'mailbox';
    $map = ['{id}' => '{' . $idKey . '}', '{ticket_id}' => '{ticket}', '{customer_id}' => '{customer}', '{mailbox_id}' => '{mailbox}', '{product_id}' => '{product}', '{tag_id}' => '{tag}', '{agent_id}' => '{agent}', '{group_id}' => '{group}', '{workflow_id}' => '{workflow}', '{box_id}' => '{mailbox}', '{board_id}' => '1'];
    $as = (strpos($route, '/customer-portal') === 0 || $route === '/me') ? 'customer' : 'admin';
    if (strpos($route, '/customer-portal/tickets/{ticket_id}') === 0) {
        $map['{ticket_id}'] = '{ticket_portal_serial}';
    }
    $params = json_decode(strtr(wp_json_encode($entry['params'] ?? []), $map), true);
    $calls[] = [$as, 'GET', strtr($route, $map), $params];
}

// ---- GET routes not in the manifest ----
$get = fn($route, array $params = [], $as = 'admin') => [$as, 'GET', $route, $params];
array_push($calls,
    $get('/advanced-reports/ticket-overview', ['from' => $from, 'to' => $to]),
    $get('/advanced-reports/response-activity', ['from' => $from, 'to' => $to]),
    $get('/advanced-reports/product-insights', ['from' => $from, 'to' => $to]),
    $get('/advanced-reports/workload-health', ['from' => $from, 'to' => $to]),
    $get('/advanced-reports/performance-overview', ['from' => $from, 'to' => $to]),
    $get('/advanced-reports/performance-overview/filters'),
    $get('/advanced-reports/snapshots'),
    $get('/advanced-reports/audit/results', ['from' => $from, 'to' => $to]),
    $get('/reports/timesheet/by-agents', ['date_range' => [$from, $to]]),
    $get('/reports/timesheet/by-customers', ['date_range' => [$from, $to]]),
    $get('/reports/timesheet/by-tickets', ['date_range' => [$from, $to]]),
    $get('/time-tracks/{ticket}'),
    $get('/ai/preset-prompts'),
    $get('/agent-groups/{group}'),
    $get('/customer-portal/tickets/{ticket_portal_serial}', [], 'customer'),
    $get('/customer-portal/tickets/{ticket_other_customer_serial}', [], 'customer'),
    $get('/customer-portal/tickets', ['filter_type' => 'all', 'per_page' => 20], 'customer'),
    $get('/tickets/{ticket}', [], 'agent'),
    $get('/tickets', ['filter_type' => 'simple', 'filters' => ['status_type' => 'all'], 'per_page' => 100], 'agent'),
    $get('/tickets/customer_tickets/{customer}', ['per_page' => 20]),
    $get('/mailboxes/{mailbox}/tickets', ['filters' => ['customer_id' => '{customer}']])
);

// ---- writes, in an order that keeps each fixture until it is used ----
$w = fn($method, $route, array $params = [], $as = 'admin') => [$as, $method, $route, $params];
array_push($calls,
    // tickets: create, view state changes, thread
    $w('POST', '/tickets', ['ticket' => ['title' => 'Replay created', 'content' => '<p>Created by replay</p>', 'customer_id' => '{customer}', 'mailbox_id' => '{mailbox}', 'product_id' => '{product}', 'client_priority' => 'medium']]),
    $w('POST', '/tickets', ['ticket' => ['title' => 'Replay new customer', 'content' => '<p>New customer</p>', 'create_customer' => 'yes', 'mailbox_id' => '{mailbox}'], 'newCustomer' => ['email' => 'replaynewcust@example.test', 'first_name' => 'Newbie', 'last_name' => 'Replay']]),
    $w('POST', '/tickets', ['ticket' => ['title' => 'Replay assigned', 'content' => '<p>Assigned</p>', 'customer_id' => '{customer2}', 'agent_id' => '{agent}']]),
    $w('POST', '/tickets', ['ticket' => ['title' => '', 'content' => '']]),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'status', 'prop_value' => 'active']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'priority', 'prop_value' => 'critical']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'client_priority', 'prop_value' => 'medium']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'product_id', 'prop_value' => '{product}']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'title', 'prop_value' => 'Replay renamed']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'agent_id', 'prop_value' => '{agent}']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'mailbox_id', 'prop_value' => '{mailbox2}']),
    $w('PUT', '/tickets/{ticket_new}/property', ['prop_name' => 'secret_content', 'prop_value' => 'x']),
    $w('PUT', '/tickets/{ticket_new}/change-customer', ['customer' => '{customer2}']),
    $w('POST', '/tickets/{ticket_new}/tags', ['tag_id' => '{tag}']),
    $w('POST', '/tickets/{ticket_new}/tags', ['tag_id' => '{tag2}']),
    $w('DELETE', '/tickets/{ticket_new}/tags/{tag2}'),
    $w('POST', '/tickets/{ticket}/responses', ['content' => '<p>Replay reply <a href="https://example.com">link</a></p>', 'conversation_type' => 'response']),
    $w('POST', '/tickets/{ticket}/responses', ['content' => '<p>Replay note</p>', 'conversation_type' => 'note']),
    $w('POST', '/tickets/{ticket}/responses', ['content' => '<p>Replay draft</p>', 'conversation_type' => 'draft_response']),
    $w('POST', '/tickets/{ticket}/responses', ['content' => '', 'conversation_type' => 'response']),
    $w('POST', '/tickets/{ticket_portal}/responses', ['content' => '<p>Reply and close</p>', 'conversation_type' => 'response', 'close_ticket' => 'yes']),
    $w('PUT', '/tickets/{ticket}/responses/{response_agent}', ['content' => '<p>Edited agent reply</p>']),
    $w('PUT', '/tickets/{ticket}/responses/{response_customer}', ['content' => '<p>Edited customer reply</p>']),
    $w('PUT', '/tickets/{ticket}/approve_draft_response/{draft}', ['content' => '<p>Approved draft</p>']),
    $w('DELETE', '/tickets/{ticket}/responses/{response_doomed}'),
    $w('POST', '/tickets/{ticket}/draft', ['content' => '<p>Autosaved</p>']),
    $w('GET', '/tickets/{ticket}/draft'),
    $w('DELETE', '/tickets/{ticket}/draft'),
    $w('GET', '/tickets/{ticket}/live_activity'),
    $w('DELETE', '/tickets/{ticket}/live_activity'),
    $w('POST', '/tickets/{ticket}/close', ['close_ticket_silently' => 'no', 'internal_note' => 'Closed by replay']),
    $w('POST', '/tickets/{ticket}/re-open'),
    $w('POST', '/tickets/{ticket_closed}/re-open'),
    $w('POST', '/tickets/{ticket_closed}/close', ['close_ticket_silently' => 'yes']),
    $w('POST', '/tickets/label-search', ['query' => ['title' => 'Replay saved filter', 'filter_type' => 'simple', 'filters' => ['status_type' => 'open']]]),
    $w('GET', '/tickets/label-search'),
    $w('DELETE', '/tickets/1/label-search'),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'change_priority', 'ticket_ids' => ['{ticket_bulk}'], 'priority' => 'medium']),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'assign_agent', 'ticket_ids' => ['{ticket_bulk}'], 'agent_id' => '{agent}']),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'assign_tags', 'ticket_ids' => ['{ticket_bulk}'], 'tag_ids' => ['{tag}', '{tag2}']]),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'remove_tags', 'ticket_ids' => ['{ticket_bulk}'], 'tag_ids' => ['{tag2}']]),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'change_product', 'ticket_ids' => ['{ticket_bulk}'], 'product_id' => '{product}']),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'assign_agent_group', 'ticket_ids' => ['{ticket_bulk}'], 'group_id' => '{group}']),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'close_tickets', 'ticket_ids' => ['{ticket_bulk}']]),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'reopen_tickets', 'ticket_ids' => ['{ticket_bulk}']]),
    $w('POST', '/tickets/bulk-actions', ['bulk_action' => 'nope', 'ticket_ids' => ['{ticket_bulk}']]),
    $w('POST', '/tickets/bulk-reply', ['ticket_ids' => ['{ticket_bulk}'], 'content' => '<p>Bulk <b>reply</b></p>']),
    $w('POST', '/tickets/sync-fluentcrm-tags', ['contact_id' => 1, 'tags' => [1]]),
    $w('POST', '/tickets/sync-fluentcrm-lists', ['contact_id' => 1, 'lists' => [1]]),
    $w('POST', '/tickets/fluent-boards/stages', ['board_id' => 1, 'stage_id' => 1, 'ticket_id' => '{ticket}']),
    $w('POST', '/tickets/{ticket}/fluent-booking/booking-link', ['event_type_id' => 1]),
    // Pro ticket extras
    $w('POST', '/tickets/{ticket}/add_watchers', ['watchers' => ['{agent}']]),
    $w('POST', '/tickets/{ticket}/sync-watchers', ['watchers' => ['{admin_agent}']]),
    $w('POST', '/tickets/{ticket_split}/split_ticket', ['split_ticket' => ['conversation_id' => '{split_conversation}', 'customer_id' => '{customer}', 'title' => 'Replay split', 'content' => '<p>Split</p>']]),
    $w('POST', '/tickets/{ticket_merge_target}/merge_tickets', ['ticket_to_merge' => ['{ticket_merge_source}']]),
    $w('POST', '/ticket-custom-fields/{ticket}/sync', ['custom_fields' => ['cf_replay_order' => 'R-2']]),
    $w('POST', '/time-tracks/{ticket}', ['working_minutes' => 15, 'billable_minutes' => 10, 'message' => 'Replay time']),
    $w('POST', '/time-tracks/{ticket}/estimated-time', ['estimated_minutes' => 90]),
    $w('POST', '/ai/{ticket}/get-ticket-summary'),
    $w('POST', '/ai/{ticket}/get-ticket-tone'),
    $w('POST', '/ai/{ticket}/generate-response', ['prompt' => 'Be kind']),
    // portal, as the customer
    $w('POST', '/customer-portal/tickets', ['title' => 'Replay portal ticket', 'content' => '<p>From the portal</p>', 'product_id' => '{product}', 'client_priority' => 'normal'], 'customer'),
    $w('POST', '/customer-portal/tickets', ['title' => '', 'content' => ''], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_portal_serial}/re-open', [], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_portal_serial}/responses', ['content' => '<p>Portal reply</p>'], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_other_customer_serial}/responses', ['content' => '<p>Not mine</p>'], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_portal_serial}/agent-feedback', ['conversation_id' => '{response_agent}', 'feedback' => 'positive'], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_portal_serial}/close', [], 'customer'),
    $w('POST', '/customer-portal/tickets/{ticket_other_customer_serial}/close', [], 'customer'),
    // customers
    $w('POST', '/customers', ['email' => 'replaycreated@example.test', 'first_name' => 'Created', 'city' => 'Dhaka']),
    $w('POST', '/customers', ['email' => 'replaycust@example.test', 'first_name' => 'Dup']),
    $w('PUT', '/customers/{customer2}', ['customer' => ['first_name' => 'Second Renamed', 'email' => 'replaycust2@example.test', 'note' => 'VIP']]),
    $w('PUT', '/customers/bulk-status', ['customer_ids' => ['{customer2}'], 'status' => 'inactive']),
    $w('PUT', '/customers/bulk-status', ['customer_ids' => ['{customer2}'], 'status' => 'active']),
    $w('POST', '/customers/reset_avatar/{customer}'),
    $w('DELETE', '/customers/{customer_doomed}'),
    $w('DELETE', '/customers/bulk-delete', ['customer_ids' => [999999991]]),
    // agents and groups
    $w('POST', '/agents', ['email' => 'replaycust@example.test', 'first_name' => 'Taken']),
    $w('PUT', '/agents/{agent}', ['first_name' => 'Agent Renamed', 'title' => 'Lead', 'permissions' => ['fst_view_tickets', 'fst_manage_own_tickets']]),
    $w('POST', '/agents/reset_avatar/{agent}'),
    $w('DELETE', '/agents/{agent_doomed}', ['fallback_agent_id' => '{agent}']),
    $w('POST', '/agent-groups', ['title' => 'Replay Group New', 'agent_ids' => ['{agent}']]),
    $w('PUT', '/agent-groups/{group}', ['title' => 'Replay Group Renamed', 'agent_ids' => ['{agent}', '{admin_agent}']]),
    $w('DELETE', '/agent-groups/{group_doomed}', ['fallback_group_id' => '{group}']),
    // mailboxes
    $w('POST', '/mailboxes', ['business' => ['name' => 'Replay Box New', 'email' => 'replayboxnew@example.test', 'box_type' => 'web']]),
    $w('PUT', '/mailboxes/{mailbox2}', ['business' => ['name' => 'Replay Box Two Renamed', 'email' => 'replaybox2@example.test', 'box_type' => 'web', 'email_footer' => '<p>Thanks</p>']]),
    $w('PUT', '/mailboxes/{mailbox2}/email_settings', ['email_type' => 'ticket_created_email_to_customer', 'email_settings' => ['email_subject' => 'Hi {{ticket.title}}', 'email_body' => '<p>Body</p>', 'status' => 'yes']]),
    $w('PUT', '/mailboxes/{mailbox_doomed}/move_tickets', ['new_box_id' => '{mailbox2}', 'move_type' => 'All']),
    $w('PUT', '/mailboxes/{mailbox2}/set_default'),
    $w('DELETE', '/mailboxes/{mailbox_doomed}', ['fallback_id' => '{mailbox}']),
    // products, tags, saved replies, custom fields
    $w('POST', '/products', ['title' => 'Replay Product New', 'description' => 'New']),
    $w('PUT', '/products/{product}', ['title' => 'Replay Product Renamed', 'description' => 'Renamed']),
    $w('POST', '/products/{product}', ['title' => 'Replay Product Posted', 'description' => 'Posted']),
    $w('DELETE', '/products/{product_doomed}'),
    $w('POST', '/ticket-tags', ['title' => 'Replay Tag New', 'description' => 'New tag']),
    $w('PUT', '/ticket-tags/{tag}', ['title' => 'Replay Tag Renamed', 'description' => 'Renamed']),
    $w('DELETE', '/ticket-tags/{tag_doomed}'),
    $w('POST', '/saved-replies', ['title' => 'Replay Saved New', 'content' => '<p>New saved</p>', 'product_id' => '{product}']),
    $w('PUT', '/saved-replies/{saved_reply}', ['title' => 'Replay Saved Renamed', 'content' => '<p>Renamed</p>']),
    $w('DELETE', '/saved-replies/{saved_reply_doomed}'),
    $w('POST', '/ticket-custom-fields', ['fields' => wp_json_encode([['slug' => 'cf_replay_order', 'label' => 'Order', 'type' => 'text'], ['label' => 'Plan', 'type' => 'select-one', 'options' => ['Basic', 'Pro']]])]),
    // workflows
    $w('POST', '/workflows', ['title' => 'Replay Workflow New', 'trigger_type' => 'manual', 'trigger_key' => '']),
    $w('POST', '/workflows/{workflow}', ['workflow' => ['title' => 'Replay Workflow', 'trigger_type' => 'manual', 'trigger_key' => '', 'settings' => ['conditions' => []], 'status' => 'published'], 'actions' => [['id' => '{workflow_action}', 'action_name' => 'fs_action_change_priority', 'title' => 'Priority', 'settings' => ['priority' => 'medium']]]]),
    $w('POST', '/workflows/action-sequence', ['workflow_id' => '{workflow}', 'sequence' => ['{workflow_action}']]),
    $w('POST', '/workflows/duplicate/{workflow}'),
    $w('POST', '/workflows/{workflow}/run', ['ticket_ids' => ['{ticket_bulk}']]),
    $w('GET', '/workflows/{workflow}/runs'),
    $w('DELETE', '/workflows/{workflow_doomed}'),
    // notifications
    $w('POST', '/notifications/{notification}/mark-read'),
    $w('POST', '/notifications/mark-all-read'),
    // settings
    $w('POST', '/settings', ['settings_key' => '_internal_notification_settings', 'settings' => ['notify_on_reply' => 'yes']]),
    $w('POST', '/settings', ['settings_key' => 'not_a_key', 'settings' => ['a' => 'b']]),
    $w('POST', '/settings/integration', ['integration_key' => 'slack_settings', 'settings' => ['status' => 'no', 'webhook_url' => '']]),
    $w('POST', '/settings/recaptcha-settings', ['reCaptcha' => 'clear-reCaptcha-settings']),
    $w('POST', '/settings/ai-integration', ['provider' => 'other']),
    $w('POST', '/settings/remote-upload-options', ['async_upload' => 'no']),
    $w('POST', '/settings/update-remote-upload-driver', ['driver' => 'local']),
    $w('POST', '/settings/remote-upload-retry', ['driver' => 'local']),
    $w('POST', '/settings/mcp/toggle', ['enabled' => 'no']),
    $w('POST', '/settings/mcp/install-adapter'),
    $w('POST', '/settings/install-fluentcrm'),
    $w('POST', '/settings/setup-installation', ['share_essentials' => 'no', 'install_fluentform' => 'no']),
    $w('POST', '/activity-logger/settings', ['activity_settings' => ['delete_days' => 30, 'disable_logs' => 'no']]),
    $w('POST', '/ai-activity-logger/settings', ['activity_settings' => ['delete_days' => 30, 'disable_logs' => 'no']]),
    $w('POST', '/settings/auto-close', ['settings' => ['enabled' => 'no']]),
    $w('POST', '/pro/form-settings', ['settings' => ['disabled_fields' => []]]),
    $w('PUT', '/settings/incoming-webhook', ['settings' => ['status' => 'no']]),
    $w('POST', '/settings/upload_integration', ['integration_key' => 'dropbox', 'settings' => ['status' => 'no']]),
    $w('POST', '/settings/remote-portal', ['enabled' => 'no']),
    $w('POST', '/settings/remote-portal/test'),
    $w('POST', '/settings/remote-portal/disconnect'),
    $w('POST', '/pro/license', ['license_key' => 'replay-invalid']),
    $w('POST', '/email-box/{mailbox}/issue-email'),
    $w('POST', '/advanced-reports/audit/run', ['from' => $from, 'to' => $to]),
    $w('POST', '/advanced-reports/audit/process-batch', ['from' => $from, 'to' => $to, 'ticket_ids' => ['{ticket}']]),
    $w('POST', '/ticket_importer/import', ['handler' => 'freshdesk', 'page' => 1]),
    $w('POST', '/ticket_importer/helpscout/token', ['app_id' => 'a', 'code' => 'c']),
    // public and auth (anonymous)
    $w('POST', '/public/slack_response/nope', ['event' => ['type' => 'message']], 'anon'),
    $w('POST', '/public/telegram_bot_response/nope', ['message' => ['text' => 'hi']], 'anon'),
    $w('POST', '/public/twilio-response/nope', ['Body' => 'hi'], 'anon'),
    $w('POST', '/public/incoming_webhook/nope', ['title' => 'x'], 'anon'),
    $w('POST', '/mail-piping/{mailbox}/push/nope', [], 'anon'),
    $w('POST', '/remote-portal/disconnect', [], 'anon'),
    $w('POST', '/login', ['log' => 'nobody', 'pwd' => 'nothing'], 'anon'),
    $w('POST', '/signup', ['username' => 'nobody'], 'anon'),
    $w('POST', '/reset_pass', ['user_login' => 'nobody@example.test'], 'anon'),
    $w('POST', '/two_fa', ['login_hash' => 'x', 'login_passcode' => '1'], 'anon'),
    // deletes last
    $w('DELETE', '/tickets/{ticket_delete}/delete'),
    $w('DELETE', '/ticket_importer/delete', ['handler' => 'freshdesk', 'page' => 1]),
    $w('POST', '/customer-portal/logout', [], 'customer')
);

// ---- helpers ----
$fill = function ($value) use (&$fill, $ids) {
    if (is_array($value)) {
        return array_map($fill, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    if (preg_match('/^\{(\w+)\}$/', $value, $m) && isset($ids[$m[1]])) {
        return (string) $ids[$m[1]];
    }
    return preg_replace_callback('/\{(\w+)\}/', fn($m) => isset($ids[$m[1]]) ? $ids[$m[1]] : $m[0], $value);
};
$mask = function ($value, $key = '') use (&$mask) {
    if (is_object($value)) {
        $value = json_decode(wp_json_encode($value), true);
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $mask($v, (string) $k);
        }
        return $out;
    }
    if (is_string($value)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value)) {
            return '<date>';
        }
        if (preg_match('/(nonce|token|hash|secret|_ago|human_date)$/i', $key) || preg_match('/ ago$/', $value)) {
            return '<volatile>';
        }
    }
    return $value;
};
$state = function () use ($wpdb, $ids) {
    $p = $wpdb->prefix . 'fs_';
    $counts = [];
    foreach (['tickets', 'conversations', 'persons', 'tag_pivot', 'taggables', 'meta', 'mail_boxes', 'products', 'saved_replies', 'workflows', 'workflow_actions', 'time_tracks', 'attachments', 'notifications'] as $t) {
        $counts[$t] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}{$t}");
    }
    $rows = [];
    foreach (['ticket', 'ticket_new', 'ticket_closed', 'ticket_bulk', 'ticket_portal', 'ticket_merge_target', 'ticket_split'] as $k) {
        $rows[$k] = $wpdb->get_row($wpdb->prepare("SELECT status, agent_id, customer_id, mailbox_id, product_id, priority, client_priority, title, response_count, closed_by, (resolved_at IS NULL) AS unresolved FROM {$p}tickets WHERE id = %d", $ids[$k]), ARRAY_A);
    }
    return ['counts' => $counts, 'tickets' => $rows];
};
$users = ['admin' => 1, 'customer' => $ids['customer_user'], 'agent' => $ids['agent_user'], 'anon' => 0];

$current = null;
register_shutdown_function(function () use (&$current) {
    if ($current !== null) {
        $out = ob_get_level() ? ob_get_clean() : '';
        $body = json_decode(trim($out), true);
        echo "\nREC " . wp_json_encode(['i' => $current[0], 'call' => $current[1], 'status' => 'DIED', 'body' => $body !== null ? $body : substr($out, 0, 500)]) . "\n";
    }
});

for ($i = $start; $i < count($calls); $i++) {
    [$as, $method, $route, $params] = $calls[$i];
    $route = $fill($route);
    $params = $fill($params);
    $label = $as . ' ' . $method . ' ' . $route;
    $current = [$i, $label];
    echo "START $i\n";

    wp_set_current_user($users[$as]);
    if (class_exists('\FluentSupport\App\Modules\PermissionManager')) {
        \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);
    }
    if (class_exists('\FluentSupport\App\Services\Tickets\AgentTicketAccess') && method_exists('\FluentSupport\App\Services\Tickets\AgentTicketAccess', 'resetCache')) {
        \FluentSupport\App\Services\Tickets\AgentTicketAccess::resetCache();
    }
    $app = \FluentSupport\App\App::getInstance();
    $requestClass = 'FluentSupport\Framework\Http\Request\Request';
    $app->instance($requestClass, new $requestClass($app, [], []));
    $wpdb->last_error = '';

    $request = new WP_REST_Request($method, rtrim('/fluent-support/v2/' . ltrim($route, '/'), '/'));
    if ($method === 'GET') {
        $request->set_query_params($params);
    } else {
        $request->set_body_params($params);
    }
    ob_start();
    $queriesBefore = $wpdb->num_queries;
    $startedAt = microtime(true);
    $response = rest_do_request($request);
    $ms = round((microtime(true) - $startedAt) * 1000, 1);
    $queries = $wpdb->num_queries - $queriesBefore;
    $stray = ob_get_clean();

    $current = null;
    echo 'REC ' . wp_json_encode([
        'i'        => $i,
        'call'     => $label,
        'params'   => $params,
        'status'   => $response->get_status(),
        'body'     => $mask($response->get_data()),
        'db_error' => $wpdb->last_error,
        'queries'  => $queries,
        'ms'       => $ms,
        'stray'    => $stray !== '' ? substr($stray, 0, 300) : null,
        'state'    => $state(),
    ]) . "\n";
}
echo "DONE " . count($calls) . "\n";
