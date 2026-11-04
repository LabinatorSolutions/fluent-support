<?php

namespace FluentSupport\App\Modules\SystemTools;

use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Settings → System Admin Tools: cron monitor, database health and data cleanup.
 */
class SystemToolsController extends Controller
{
    /**
     * Any method on system-tools/rest-test; the page uses it to check each HTTP method gets through.
     *
     * @return array
     */
    public function restTest()
    {
        return [
            'message' => 'ok'
        ];
    }

    /**
     * @return array
     */
    public function getCronEvents()
    {
        $cronMonitor = new CronMonitor();

        return [
            'can_manage' => current_user_can('manage_options'),
            'server'     => $cronMonitor->getServerInfo(),
            'events'     => array_values($cronMonitor->getEvents()),
        ];
    }

    /**
     * @param Request $request
     * @return array|\WP_REST_Response
     */
    public function runCron(Request $request)
    {
        $hook = $request->getSafe('hook', 'sanitize_text_field', '');

        if (!(new CronMonitor())->run($hook)) {
            return $this->sendError([
                'message' => __('This task can not be run manually.', 'fluent-support')
            ]);
        }

        return [
            'message' => __('The task ran successfully.', 'fluent-support')
        ];
    }

    /**
     * @return array
     */
    public function getDatabaseHealth()
    {
        return array_merge(
            ['can_manage' => current_user_can('manage_options')],
            (new DatabaseHealth())->getReport()
        );
    }

    /**
     * @return array
     */
    public function repairDatabase()
    {
        (new DatabaseHealth())->repair();

        return [
            'message' => __('Repair finished.', 'fluent-support')
        ];
    }

    /**
     * @param Request $request
     * @return array|\WP_REST_Response
     */
    public function getCleanupCounts(Request $request)
    {
        $input = $this->getCleanupInput($request);
        if (!$input) {
            return $this->sendCleanupInputError();
        }

        return [
            'can_manage' => current_user_can('manage_options'),
            'log_counts' => (new DataCleanup())->countOlderThan($input['types'], $input['days_before']),
        ];
    }

    /**
     * Deletes one chunk per call; the page repeats the call while has_more is true.
     *
     * @param Request $request
     * @return array|\WP_REST_Response
     */
    public function deleteOldLogs(Request $request)
    {
        $input = $this->getCleanupInput($request);
        if (!$input) {
            return $this->sendCleanupInputError();
        }

        $result = (new DataCleanup())->deleteOlderThan($input['types'], $input['days_before']);

        return [
            'deleted'  => $result['deleted'],
            'has_more' => $result['has_more'],
            'message'  => __('Old logs deleted.', 'fluent-support')
        ];
    }

    /**
     * @param Request $request
     * @return array|null days_before and types, or null when the input is invalid.
     */
    protected function getCleanupInput(Request $request)
    {
        $daysBefore = $request->getSafe('days_before', 'intval', 0);
        $types = array_values(array_intersect(
            array_map('sanitize_key', (array) $request->get('selected_logs', [])),
            array_keys((new DataCleanup())->getTypes())
        ));

        if ($daysBefore < 7 || !$types) {
            return null;
        }

        return [
            'days_before' => $daysBefore,
            'types'       => $types,
        ];
    }

    /**
     * @return \WP_REST_Response
     */
    protected function sendCleanupInputError()
    {
        return $this->sendError([
            'message' => __('Select at least one log type and 7 days or more.', 'fluent-support')
        ]);
    }
}
