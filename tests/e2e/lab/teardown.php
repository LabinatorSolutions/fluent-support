<?php
// Deletes the E2E fixtures and everything the UI made on them: every ticket of a
// fixture customer or with the marker in its title, their threads, tags,
// watchers and meta, then the fixture people, mailboxes, product and tags.
global $wpdb;
$p = $wpdb->prefix . 'fs_';
$ids = get_option('fs_e2e_ids');
if (!$ids) {
    echo 'nothing to clean';
    return;
}

$in = fn(array $values) => implode(',', array_map('intval', $values) ?: [0]);
$people = [$ids['customer'], $ids['customer2'], $ids['agent']];
$ticketIds = $wpdb->get_col($wpdb->prepare(
    "SELECT id FROM {$p}tickets WHERE customer_id IN ({$in($people)}) OR title LIKE %s",
    '%' . $wpdb->esc_like($ids['marker']) . '%'
));

if ($ticketIds) {
    $t = $in($ticketIds);
    $wpdb->query("DELETE FROM {$p}conversations WHERE ticket_id IN ($t)");
    $wpdb->query("DELETE FROM {$p}attachments WHERE ticket_id IN ($t)");
    $wpdb->query("DELETE FROM {$p}tag_pivot WHERE source_id IN ($t) AND source_type IN ('ticket_tag', 'ticket_watcher')");
    $wpdb->query("DELETE FROM {$p}meta WHERE object_type = 'ticket_meta' AND object_id IN ($t)");
    $wpdb->query("DELETE FROM {$p}tickets WHERE id IN ($t)");
}
$wpdb->query("DELETE FROM {$p}persons WHERE id IN ({$in($people)})");
$wpdb->query("DELETE FROM {$p}mail_boxes WHERE id IN ({$in([$ids['mailbox'], $ids['mailbox2']])})");
$wpdb->query("DELETE FROM {$p}products WHERE id = " . (int) $ids['product']);
$wpdb->query("DELETE FROM {$p}taggables WHERE id IN ({$in([$ids['tag'], $ids['tag2']])})");

delete_option('fs_e2e_ids');
echo 'deleted ' . count($ticketIds) . ' tickets';
