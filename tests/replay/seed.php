<?php
// One fixed data set for the develop vs full-refactor replay. Raw inserts, so
// it does not depend on either version's models. Ids go to option fs_replay_ids.
global $wpdb;
$p = $wpdb->prefix . 'fs_';
$at = '2026-10-01 10:00:00';
$ids = [];

$user = function ($login, $role) {
    $id = username_exists($login);
    if (!$id) {
        $id = wp_insert_user(['user_login' => $login, 'user_pass' => 'Replay-Pass-123!', 'user_email' => $login . '@example.test', 'role' => $role, 'first_name' => ucfirst($login), 'last_name' => 'Replay']);
    }
    return (int) $id;
};
$insert = function ($table, array $row) use ($wpdb, $p, $at) {
    $row += ['created_at' => $at, 'updated_at' => $at];
    $wpdb->insert($p . $table, $row);
    if ($wpdb->last_error) {
        throw new RuntimeException($table . ': ' . $wpdb->last_error);
    }
    return (int) $wpdb->insert_id;
};

$ids['customer_user'] = $user('replaycust', 'subscriber');
$ids['agent_user'] = $user('replayagent', 'subscriber');
$ids['admin_agent'] = (int) $wpdb->get_var("SELECT id FROM {$p}persons WHERE person_type = 'agent' AND user_id = 1");

$person = function ($type, $email, $first, $userId = null) use ($insert) {
    return $insert('persons', ['person_type' => $type, 'email' => $email, 'first_name' => $first, 'last_name' => 'Replay', 'status' => 'active', 'hash' => md5($email), 'user_id' => $userId, 'title' => $type === 'agent' ? 'Support' : null]);
};
$ids['customer'] = $person('customer', 'replaycust@example.test', 'Cust', $ids['customer_user']);
$ids['customer2'] = $person('customer', 'replaycust2@example.test', 'Second');
$ids['customer_doomed'] = $person('customer', 'replaydoomed@example.test', 'Doomed');
$ids['agent'] = $person('agent', 'replayagent@example.test', 'Agent', $ids['agent_user']);
$ids['agent_doomed'] = $person('agent', 'replayagentdoomed@example.test', 'Gone');
\FluentSupport\App\Modules\PermissionManager::attachPermissions($ids['agent_user'], ['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets']);

$box = function ($name, $default) use ($insert) {
    return $insert('mail_boxes', ['name' => $name, 'slug' => sanitize_title($name), 'box_type' => 'web', 'email' => sanitize_title($name) . '@example.test', 'settings' => maybe_serialize(['admin_email_address' => 'admin@example.test']), 'created_by' => 1, 'is_default' => $default]);
};
$ids['mailbox'] = $box('Replay Box', 'no');
$ids['mailbox2'] = $box('Replay Box Two', 'no');
$ids['mailbox_doomed'] = $box('Replay Box Doomed', 'no');

$ids['product'] = $insert('products', ['title' => 'Replay Product', 'description' => 'Replay', 'source' => 'local', 'created_by' => 1]);
$ids['product_doomed'] = $insert('products', ['title' => 'Replay Product Doomed', 'description' => 'Doomed', 'source' => 'local', 'created_by' => 1]);

$tag = function ($title) use ($insert) {
    return $insert('taggables', ['tag_type' => 'ticket_tag', 'title' => $title, 'slug' => sanitize_title($title), 'created_by' => 1]);
};
$ids['tag'] = $tag('Replay Tag');
$ids['tag2'] = $tag('Replay Tag Two');
$ids['tag_doomed'] = $tag('Replay Tag Doomed');
$ids['group'] = $insert('taggables', ['tag_type' => 'agent_group', 'title' => 'Replay Group', 'slug' => 'replay-group', 'created_by' => 1]);
$ids['group_doomed'] = $insert('taggables', ['tag_type' => 'agent_group', 'title' => 'Replay Group Doomed', 'slug' => 'replay-group-doomed', 'created_by' => 1]);
$insert('tag_pivot', ['tag_id' => $ids['group'], 'source_id' => $ids['agent'], 'source_type' => 'agent_group']);

$ticket = function ($key, array $row) use ($insert, &$ids, $wpdb, $p) {
    $row += ['customer_id' => $ids['customer'], 'mailbox_id' => $ids['mailbox'], 'privacy' => 'private', 'priority' => 'normal', 'client_priority' => 'normal', 'status' => 'active', 'source' => 'web', 'content' => '<p>Opening message for ' . $key . '</p>', 'hash' => md5('replay-' . $key), 'response_count' => 0, 'last_customer_response' => '2026-10-01 10:00:00', 'waiting_since' => '2026-10-01 10:00:00', 'created_by' => $ids['customer']];
    $row['title'] = 'Replay ' . $key;
    $row['slug'] = sanitize_title($row['title']);
    $id = $insert('tickets', $row);
    // Serial numbers apart from ids, so a lookup by id can't pass for one by serial.
    $wpdb->update($p . 'tickets', ['serial_number' => $id + 500000], ['id' => $id]);
    $ids[$key] = $id;
    $ids[$key . '_serial'] = $id + 500000;
    return $id;
};
$ticket('ticket', ['agent_id' => $ids['admin_agent'], 'product_id' => $ids['product'], 'status' => 'active', 'response_count' => 2]);
$ticket('ticket_new', ['status' => 'new']);
$ticket('ticket_closed', ['status' => 'closed', 'agent_id' => $ids['agent'], 'resolved_at' => '2026-10-02 10:00:00', 'closed_by' => $ids['agent']]);
$ticket('ticket_split', ['agent_id' => $ids['admin_agent']]);
$ticket('ticket_merge_target', []);
$ticket('ticket_merge_source', []);
$ticket('ticket_delete', []);
$ticket('ticket_bulk', ['status' => 'new']);
$ticket('ticket_portal', ['status' => 'active', 'agent_id' => $ids['agent']]);
$ticket('ticket_other_customer', ['customer_id' => $ids['customer2']]);
$ticket('ticket_box_move', ['mailbox_id' => $ids['mailbox_doomed']]);

$convo = function ($ticketKey, $personId, $type, $content) use ($insert, $ids) {
    return $insert('conversations', ['ticket_id' => $ids[$ticketKey], 'person_id' => $personId, 'conversation_type' => $type, 'content' => $content, 'source' => 'web', 'content_hash' => md5($content)]);
};
$ids['response_customer'] = $convo('ticket', $ids['customer'], 'response', '<p>Customer reply</p>');
$ids['response_agent'] = $convo('ticket', $ids['admin_agent'], 'response', '<p>Agent reply</p>');
$ids['note'] = $convo('ticket', $ids['admin_agent'], 'note', '<p>Internal note</p>');
$ids['draft'] = $convo('ticket', $ids['admin_agent'], 'draft_response', '<p>Draft for approval</p>');
$ids['response_doomed'] = $convo('ticket', $ids['admin_agent'], 'response', '<p>To delete</p>');
$ids['split_conversation'] = $convo('ticket_split', $ids['customer'], 'response', '<p>Split me</p>');
$convo('ticket_merge_source', $ids['customer'], 'response', '<p>Merge me</p>');
$convo('ticket_portal', $ids['agent'], 'response', '<p>Agent answer in portal</p>');

$pivot = function ($tagKey, $ticketKey, $type = 'ticket_tag') use ($insert, $ids) {
    return $insert('tag_pivot', ['tag_id' => $ids[$tagKey], 'source_id' => $ids[$ticketKey], 'source_type' => $type]);
};
$pivot('tag', 'ticket');
$pivot('tag2', 'ticket');
$insert('tag_pivot', ['tag_id' => $ids['agent'], 'source_id' => $ids['ticket'], 'source_type' => 'ticket_watcher']);

$ids['saved_reply'] = $insert('saved_replies', ['created_by' => $ids['admin_agent'], 'title' => 'Replay Saved', 'content' => '<p>Saved reply</p>']);
$ids['saved_reply_doomed'] = $insert('saved_replies', ['created_by' => $ids['admin_agent'], 'title' => 'Replay Saved Doomed', 'content' => '<p>Gone</p>']);

$ids['workflow'] = $insert('workflows', ['created_by' => 1, 'priority' => 10, 'title' => 'Replay Workflow', 'trigger_key' => '', 'trigger_type' => 'manual', 'settings' => maybe_serialize(['conditions' => []]), 'status' => 'published']);
$ids['workflow_action'] = $insert('workflow_actions', ['title' => 'Priority', 'action_name' => 'fs_action_change_priority', 'workflow_id' => $ids['workflow'], 'settings' => maybe_serialize(['priority' => 'medium'])]);
$insert('meta', ['object_type' => 'workflow_meta', 'object_id' => $ids['workflow'], 'key' => '_fs_workflow_action_sequence', 'value' => maybe_serialize([$ids['workflow_action']])]);
$ids['workflow_doomed'] = $insert('workflows', ['created_by' => 1, 'priority' => 10, 'title' => 'Replay Workflow Doomed', 'trigger_key' => '', 'trigger_type' => 'manual', 'settings' => maybe_serialize(['conditions' => []]), 'status' => 'draft']);

$ids['time_track'] = $insert('time_tracks', ['agent_id' => $ids['admin_agent'], 'customer_id' => $ids['customer'], 'ticket_id' => $ids['ticket'], 'mailbox_id' => $ids['mailbox'], 'started_at' => '2026-10-01 10:00:00', 'completed_at' => '2026-10-01 10:30:00', 'message' => 'Replay work', 'status' => 'commited', 'working_minutes' => 30, 'billable_minutes' => 30, 'is_manual' => 'yes']);

$ids['notification'] = $insert('notifications', ['actor_id' => $ids['agent'], 'ticket_id' => $ids['ticket'], 'event_type' => 'agent_mentioned', 'category' => 'mention', 'payload' => wp_json_encode(['title' => 'Replay'])]);
$insert('notification_users', ['notification_id' => $ids['notification'], 'user_id' => 1, 'channel' => 'web', 'is_read' => 0]);

$wpdb->delete($p . 'meta', ['object_type' => 'search_meta', 'object_id' => 1, 'key' => 'label_search']);
$ids['label_search'] = $insert('meta', ['object_type' => 'search_meta', 'object_id' => 1, 'key' => 'label_search', 'value' => maybe_serialize([['id' => 1, 'title' => 'Replay Search', 'filters' => ['filter_type' => 'simple', 'filters' => ['status' => 'active']]]])]);

\FluentSupport\App\Services\Helper::updateOption('_ticket_custom_fields', [['slug' => 'cf_replay_order', 'label' => 'Order', 'type' => 'text', 'admin_only' => 'no']]);
$insert('meta', ['object_type' => 'ticket_meta', 'object_id' => $ids['ticket'], 'key' => 'cf_replay_order', 'value' => 'R-1']);

update_option('fs_replay_ids', $ids, 'no');
echo wp_json_encode($ids) . "\n";
