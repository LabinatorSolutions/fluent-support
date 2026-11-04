<?php
/**
 * The weekly Action Scheduler cleanup touches only Fluent Support's own actions,
 * logs and claims. Rows are written directly and removed in the finally.
 * Chunked log cleanup is covered by 65-system-tools-daily-cleanup-chunks.php.
 */

use FluentSupport\App\Hooks\Handlers\CleanupHandler;

return function () {
    global $wpdb;

    $marker = FsFactory::marker();

    FsTest::case('scheduled action cleanup removes only Fluent Support\'s old actions and claims', function () use ($wpdb, $marker) {
        $p = $wpdb->prefix;
        $groupId = (int) $wpdb->get_var("SELECT group_id FROM {$p}actionscheduler_groups WHERE slug = 'fluent-support'");
        if (!$groupId) {
            $wpdb->insert($p . 'actionscheduler_groups', ['slug' => 'fluent-support']);
            $groupId = (int) $wpdb->insert_id;
        }
        $otherGroupId = (int) $wpdb->get_var("SELECT group_id FROM {$p}actionscheduler_groups WHERE slug = 'fstest-other'");
        if (!$otherGroupId) {
            $wpdb->insert($p . 'actionscheduler_groups', ['slug' => 'fstest-other']);
            $otherGroupId = (int) $wpdb->insert_id;
        }

        $old = '2001-01-01 00:00:00';
        $wpdb->insert($p . 'actionscheduler_claims', ['date_created_gmt' => $old]);
        $ourClaim = (int) $wpdb->insert_id;
        $wpdb->insert($p . 'actionscheduler_claims', ['date_created_gmt' => $old]);
        $otherOrphanClaim = (int) $wpdb->insert_id;

        $action = function ($group, $claim) use ($wpdb, $p, $old, $marker) {
            $wpdb->insert($p . 'actionscheduler_actions', [
                'hook' => 'fstest_' . $marker, 'status' => 'complete', 'scheduled_date_gmt' => $old, 'scheduled_date_local' => $old,
                'args' => '[]', 'group_id' => $group, 'claim_id' => $claim, 'last_attempt_gmt' => $old, 'last_attempt_local' => $old,
            ]);
            $id = (int) $wpdb->insert_id;
            $wpdb->insert($p . 'actionscheduler_logs', ['action_id' => $id, 'message' => 'done', 'log_date_gmt' => $old, 'log_date_local' => $old]);
            return $id;
        };
        $ours = $action($groupId, $ourClaim);
        $theirs = $action($otherGroupId, 0);

        try {
            (new CleanupHandler())->maybeRemoveOldScheuledActionLogs();

            FsTest::assertSame(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}actionscheduler_actions WHERE action_id = %d", $ours)), 'our old action deleted');
            FsTest::assertSame(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}actionscheduler_logs WHERE action_id = %d", $ours)), 'its logs deleted');
            FsTest::assertSame(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}actionscheduler_claims WHERE claim_id = %d", $ourClaim)), 'its claim deleted');
            FsTest::assertSame(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}actionscheduler_actions WHERE action_id = %d", $theirs)), 'another group\'s action kept');
            FsTest::assertSame(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}actionscheduler_claims WHERE claim_id = %d", $otherOrphanClaim)), 'another plugin\'s claim kept');
        } finally {
            $wpdb->query($wpdb->prepare("DELETE FROM {$p}actionscheduler_logs WHERE action_id IN (%d, %d)", $ours, $theirs));
            $wpdb->query($wpdb->prepare("DELETE FROM {$p}actionscheduler_actions WHERE action_id IN (%d, %d)", $ours, $theirs));
            $wpdb->query($wpdb->prepare("DELETE FROM {$p}actionscheduler_claims WHERE claim_id IN (%d, %d)", $ourClaim, $otherOrphanClaim));
            $wpdb->delete($p . 'actionscheduler_groups', ['group_id' => $otherGroupId]);
        }
    });
};
