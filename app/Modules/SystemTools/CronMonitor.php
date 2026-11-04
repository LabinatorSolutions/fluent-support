<?php

namespace FluentSupport\App\Modules\SystemTools;

/**
 * Scheduled Fluent Support tasks and the server limits they run under.
 */
class CronMonitor
{
    /**
     * Status of every registered task, for the Cron Job Monitor page.
     *
     * @return array
     */
    public function getEvents()
    {
        $events = [];

        foreach ($this->getJobs() as $job) {
            $nextRun = $this->getNextRun($job);
            $now = time();

            if (!$nextRun) {
                $status = 'not_scheduled';
                $nextRunHuman = '';
            } elseif ($nextRun > $now) {
                $status = 'ok';
                /* translators: %s: time until the next run, e.g. "5 mins" */
                $nextRunHuman = sprintf(__('in %s', 'fluent-support'), human_time_diff($now, $nextRun));
            } else {
                $status = ($now - $nextRun) > $job['interval'] ? 'overdue' : 'ok';
                /* translators: %s: time since the missed run, e.g. "2 hours" */
                $nextRunHuman = sprintf(__('%s ago', 'fluent-support'), human_time_diff($nextRun, $now));
            }

            $events[] = [
                'hook'           => $job['hook'],
                'title'          => $job['title'],
                'interval'       => $job['interval'],
                'next_run_human' => $nextRunHuman,
                'status'         => $status,
            ];
        }

        return $events;
    }

    /**
     * Fire a registered task now. Only hooks listed by getJobs() can run.
     *
     * @param string $hook
     * @return bool False when the hook is not a registered task.
     */
    public function run($hook)
    {
        if (!in_array($hook, array_column($this->getJobs(), 'hook'), true)) {
            return false;
        }

        do_action($hook);

        return true;
    }

    /**
     * @return array
     */
    public function getServerInfo()
    {
        $memoryLimit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $maxExecutionTime = (int) ini_get('max_execution_time');

        return [
            'memory_limit'       => $memoryLimit > 0 ? size_format($memoryLimit) : __('Unlimited', 'fluent-support'),
            'usage_percent'      => $memoryLimit > 0 ? round(memory_get_usage(true) / $memoryLimit * 100, 2) : 0,
            'max_execution_time' => $maxExecutionTime > 0
                /* translators: %d: number of seconds */
                ? sprintf(_n('%d second', '%d seconds', $maxExecutionTime, 'fluent-support'), $maxExecutionTime)
                : __('Unlimited', 'fluent-support'),
            'has_server_cron'    => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ];
    }

    /**
     * @return array
     */
    protected function getJobs()
    {
        $jobs = [
            [
                'hook'      => 'fluent_support_hourly_tasks',
                'title'     => __('Hourly Cleanup and Auto-Close', 'fluent-support'),
                'interval'  => HOUR_IN_SECONDS,
                'scheduler' => 'wp_cron',
            ],
            [
                'hook'      => 'fluent_support_daily_tasks',
                'title'     => __('Daily Log Cleanup', 'fluent-support'),
                'interval'  => DAY_IN_SECONDS,
                'scheduler' => 'wp_cron',
            ],
            [
                'hook'      => 'fluent_support_weekly_tasks',
                'title'     => __('Weekly Maintenance', 'fluent-support'),
                'interval'  => WEEK_IN_SECONDS,
                'scheduler' => 'wp_cron',
            ],
        ];

        // Free schedules this hook, but only Pro listens on it.
        if (has_action('fluent_support_half_hourly')) {
            $jobs[] = [
                'hook'      => 'fluent_support_half_hourly',
                'title'     => __('Email Piping Retry and Storage Tokens', 'fluent-support'),
                'interval'  => 30 * MINUTE_IN_SECONDS,
                'scheduler' => 'action_scheduler',
            ];
        }

        /**
         * Filter the tasks shown on the Cron Job Monitor page.
         *
         * @since 2.4.5
         *
         * @param array $jobs Each item: hook, title, interval (seconds), scheduler ('wp_cron' default, or 'action_scheduler').
         */
        $jobs = (array) apply_filters('fluent_support/system_tools_cron_events', $jobs);

        return array_filter($jobs, function ($job) {
            return !empty($job['hook']) && !empty($job['title']) && !empty($job['interval']);
        });
    }

    /**
     * @param array $job
     * @return int|false Unix timestamp, or false when not scheduled.
     */
    protected function getNextRun($job)
    {
        if (($job['scheduler'] ?? '') === 'action_scheduler') {
            if (!function_exists('as_next_scheduled_action')) {
                return false;
            }

            $next = as_next_scheduled_action($job['hook'], [], 'fluent-support');

            // true means the action is running right now.
            return $next === true ? time() : $next;
        }

        return wp_next_scheduled($job['hook']);
    }
}
