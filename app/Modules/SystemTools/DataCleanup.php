<?php

namespace FluentSupport\App\Modules\SystemTools;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\AIActivityLogs;
use FluentSupport\App\Models\Notification;
use FluentSupport\App\Models\NotificationUser;
use FluentSupport\App\Modules\Notifications\NotificationService;

/**
 * Counts and deletes old logs in chunks, for the Data Cleanup page and the daily cleanup cron.
 */
class DataCleanup
{
    const CHUNK_SIZE = 10000;

    const MODELS = [
        'activity_logs'          => Activity::class,
        'ai_activity_logs'       => AIActivityLogs::class,
        'internal_notifications' => Notification::class,
    ];

    /** @var array|null Filter-added types, resolved once per instance. */
    protected $extraTypes = null;

    /**
     * @return array Log type key => translated title.
     */
    public function getTypes()
    {
        $types = [
            'activity_logs'          => __('Activity Logs', 'fluent-support'),
            'ai_activity_logs'       => __('AI Activity Logs', 'fluent-support'),
            'internal_notifications' => __('Internal Notifications', 'fluent-support'),
        ];

        foreach ($this->getExtraTypes() as $key => $type) {
            if (!isset($types[$key])) {
                $types[$key] = $type['title'];
            }
        }

        return $types;
    }

    /**
     * @return array Valid filter-added types keyed by type key.
     */
    protected function getExtraTypes()
    {
        if ($this->extraTypes !== null) {
            return $this->extraTypes;
        }

        /**
         * Filter the extra log types the Data Cleanup API can count and delete.
         *
         * @since 2.4.5
         *
         * @param array $types Each item: key, title, count (callable(string $cutoff): int)
         *                     and delete (callable(string $cutoff, int $limit): int rows removed, at most $limit).
         */
        $types = (array) apply_filters('fluent_support/system_tools_cleanup_types', []);

        $valid = [];
        foreach ($types as $type) {
            if (empty($type['key']) || empty($type['title'])
                || !is_callable($type['count'] ?? null) || !is_callable($type['delete'] ?? null)) {
                continue;
            }

            $key = sanitize_key($type['key']);
            if (!isset($valid[$key])) {
                $valid[$key] = $type;
            }
        }

        return $this->extraTypes = $valid;
    }

    /**
     * @param array $types
     * @param int   $daysBefore
     * @return array One row per type: key, title, count.
     */
    public function countOlderThan(array $types, $daysBefore)
    {
        $cutoff = $this->getCutoff($daysBefore);
        $titles = $this->getTypes();
        $extraTypes = $this->getExtraTypes();

        $counts = [];
        foreach ($types as $type) {
            if (isset(self::MODELS[$type])) {
                $query = $this->getQuery($type);
                $count = $query ? $query->where('created_at', '<', $cutoff)->count() : 0;
            } elseif (isset($extraTypes[$type])) {
                $count = call_user_func($extraTypes[$type]['count'], $cutoff);
            } else {
                // Unregistered since the request was validated.
                continue;
            }

            $counts[] = [
                'key'   => $type,
                'title' => $titles[$type],
                'count' => max(0, (int) $count),
            ];
        }

        return $counts;
    }

    /**
     * Delete up to one chunk of rows across the given types.
     *
     * @param array $types
     * @param int   $daysBefore
     * @return array deleted (rows removed by this call), has_more (this call filled a whole chunk).
     */
    public function deleteOlderThan(array $types, $daysBefore)
    {
        $cutoff = $this->getCutoff($daysBefore);
        $budget = self::CHUNK_SIZE;
        $deleted = 0;

        foreach ($types as $type) {
            if ($budget <= 0) {
                break;
            }

            $removed = $this->deleteChunk($type, $cutoff, $budget);
            $deleted += $removed;
            $budget -= $removed;
        }

        return [
            'deleted'  => $deleted,
            // A full chunk may leave more; anything less (including 0) means done, so the page's loop always ends.
            'has_more' => $deleted >= self::CHUNK_SIZE,
        ];
    }

    /**
     * @param string $type
     * @param string $cutoff
     * @param int    $limit
     * @return int Rows removed.
     */
    protected function deleteChunk($type, $cutoff, $limit)
    {
        if ($type === 'internal_notifications') {
            return $this->deleteNotificationsChunk($cutoff, $limit);
        }

        if (!isset(self::MODELS[$type])) {
            $extra = $this->getExtraTypes()[$type] ?? null;

            // Capped so a callback can't overrun the chunk budget.
            return $extra ? min($limit, max(0, (int) call_user_func($extra['delete'], $cutoff, $limit))) : 0;
        }

        $query = $this->getQuery($type);

        return $query ? (int) $query->where('created_at', '<', $cutoff)->limit($limit)->delete() : 0;
    }

    /**
     * Recipient deletes are LIMITed to the remaining budget, and a notification is
     * deleted only after its recipients are gone, so no rows are orphaned.
     *
     * @param string $cutoff
     * @param int    $limit
     * @return int Notification + recipient rows removed.
     */
    protected function deleteNotificationsChunk($cutoff, $limit)
    {
        if (!$this->getQuery('internal_notifications')) {
            return 0;
        }

        $removed = 0;

        while ($removed < $limit) {
            $ids = Notification::where('created_at', '<', $cutoff)
                ->orderBy('id', 'ASC')
                ->limit(10)
                ->pluck('id')
                ->toArray();

            if (!$ids) {
                break;
            }

            $budget = $limit - $removed;
            $recipients = (int) NotificationUser::whereIn('notification_id', $ids)->limit($budget)->delete();
            $removed += $recipients;

            // Budget spent: recipients may remain, so leave these notifications for the next call.
            if ($recipients >= $budget) {
                break;
            }

            $removed += (int) Notification::whereIn('id', $ids)->delete();
        }

        return $removed;
    }

    /**
     * @param string $type
     * @return \FluentSupport\Framework\Database\Orm\Builder|null Null when the type's table doesn't exist.
     */
    protected function getQuery($type)
    {
        $model = self::MODELS[$type];
        $model = new $model();

        // AI logs and notifications tables are created when their feature is first enabled.
        // Notifications also delete from the recipients table, so both must exist.
        $exists = $type === 'internal_notifications'
            ? NotificationService::tablesExist()
            : $this->tableExists($model->getTable());

        if (!$exists) {
            return null;
        }

        return $model->newQuery();
    }

    /**
     * @param string $table Table name without the WordPress prefix.
     * @return bool
     */
    protected function tableExists($table)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Site-local cutoff, since created_at is written with current_time(). Shared by the Data Cleanup page and the daily cron.
     *
     * @param int $daysBefore
     * @return string
     */
    protected function getCutoff($daysBefore)
    {
        return gmdate('Y-m-d H:i:s', current_time('timestamp') - ((int) $daysBefore * DAY_IN_SECONDS));
    }
}
