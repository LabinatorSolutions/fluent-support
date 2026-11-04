<?php
// E2E fixtures: a customer, an agent, two mailboxes, a product, tags and a set of
// tickets whose titles carry the run marker. Ids go to option fs_e2e_ids, which
// teardown.php reads. Raw inserts, so the data does not depend on the code under test.
global $wpdb;
$p = $wpdb->prefix . 'fs_';
$marker = 'e2e' . substr(md5(uniqid('', true)), 0, 6);
$at = gmdate('Y-m-d H:i:s', time() - 3600);
$ids = ['marker' => $marker];

$insert = function ($table, array $row) use ($wpdb, $p, $at) {
    $row += ['created_at' => $at, 'updated_at' => $at];
    $wpdb->insert($p . $table, $row);
    if ($wpdb->last_error) {
        throw new RuntimeException($table . ': ' . $wpdb->last_error);
    }
    return (int) $wpdb->insert_id;
};

$ids['admin_agent'] = (int) $wpdb->get_var("SELECT id FROM {$p}persons WHERE person_type = 'agent' AND user_id = 1");
$person = function ($type, $first, $last) use ($insert, $marker) {
    $email = strtolower($first) . '-' . $marker . '@example.test';
    return $insert('persons', ['person_type' => $type, 'email' => $email, 'first_name' => $first, 'last_name' => $last, 'status' => 'active', 'hash' => md5($email)]);
};
$ids['customer'] = $person('customer', 'Carla', 'Customer');
$ids['customer2'] = $person('customer', 'Bruno', 'Buyer');
$ids['agent'] = $person('agent', 'Ava', 'Agent');

$box = function ($name) use ($insert, $marker) {
    return $insert('mail_boxes', ['name' => $name . ' ' . $marker, 'slug' => sanitize_title($name . '-' . $marker), 'box_type' => 'web', 'email' => sanitize_title($name) . '-' . $marker . '@example.test', 'settings' => maybe_serialize(['admin_email_address' => 'admin@example.test']), 'created_by' => 1, 'is_default' => 'no']);
};
$ids['mailbox'] = $box('E2E Box');
$ids['mailbox2'] = $box('E2E Second Box');
$ids['product'] = $insert('products', ['title' => 'E2E Product ' . $marker, 'source' => 'local', 'created_by' => 1]);

$tag = function ($title) use ($insert, $marker) {
    return $insert('taggables', ['tag_type' => 'ticket_tag', 'title' => $title . ' ' . $marker, 'slug' => sanitize_title($title . '-' . $marker), 'created_by' => 1]);
};
$ids['tag'] = $tag('Billing');
$ids['tag2'] = $tag('Urgent');

$tickets = [
    'view'      => ['status' => 'active', 'agent_id' => $ids['admin_agent'], 'product_id' => $ids['product'], 'priority' => 'normal', 'response_count' => 1],
    'new'       => ['status' => 'new', 'priority' => 'normal'],
    'new2'      => ['status' => 'new', 'priority' => 'normal', 'customer_id' => $ids['customer2']],
    'closed'    => ['status' => 'closed', 'agent_id' => $ids['agent'], 'resolved_at' => $at, 'closed_by' => $ids['agent']],
    'critical'  => ['status' => 'active', 'priority' => 'critical', 'agent_id' => $ids['agent']],
    'other_box' => ['status' => 'active', 'mailbox_id' => $ids['mailbox2']],
];
foreach ($tickets as $key => $row) {
    $row += ['customer_id' => $ids['customer'], 'mailbox_id' => $ids['mailbox'], 'privacy' => 'private', 'client_priority' => 'normal', 'source' => 'web', 'response_count' => 0, 'last_customer_response' => $at, 'waiting_since' => $at, 'created_by' => $ids['customer']];
    $row['title'] = ucfirst(str_replace('_', ' ', $key)) . ' ticket ' . $marker;
    $row['slug'] = sanitize_title($row['title']);
    $row['content'] = '<p>Opening message of the ' . $key . ' ticket.</p>';
    $row['hash'] = md5($row['title']);
    $ids['ticket_' . $key] = $id = $insert('tickets', $row);
    $wpdb->update($p . 'tickets', ['serial_number' => $id], ['id' => $id]);
}

$convo = function ($ticketKey, $personId, $type, $content) use ($insert, $ids) {
    return $insert('conversations', ['ticket_id' => $ids['ticket_' . $ticketKey], 'person_id' => $personId, 'conversation_type' => $type, 'content' => $content, 'source' => 'web', 'content_hash' => md5($content . microtime())]);
};
$ids['reply_customer'] = $convo('view', $ids['customer'], 'response', '<p>Customer follow-up ' . $marker . '</p>');
$ids['reply_agent'] = $convo('view', $ids['admin_agent'], 'response', '<p>Agent answer ' . $marker . '</p>');
$ids['note'] = $convo('view', $ids['admin_agent'], 'note', '<p>Internal note ' . $marker . '</p>');

$insert('tag_pivot', ['tag_id' => $ids['tag'], 'source_id' => $ids['ticket_view'], 'source_type' => 'ticket_tag']);

update_option('fs_e2e_ids', $ids, 'no');
echo wp_json_encode($ids);
