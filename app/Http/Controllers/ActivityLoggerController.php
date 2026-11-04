<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Activity log endpoints and settings.
 */
class ActivityLoggerController extends Controller
{
    /**
     * @return \WP_REST_Response | array
     */

    public function getActivities (Request $request, Activity $activity)
    {
        try {
            $filters = $request->get('filters', null);
            $filters = is_array($filters) ? map_deep($filters, 'sanitize_text_field') : [];

            return $activity->getActivities( [
                'page' => $request->getSafe('page', 'intval', 1),
                'per_page' => $request->getSafe('per_page', 'intval', 10),
                'from' => $request->getSafe('from', 'sanitize_text_field', ''),
                'to'   => $request->getSafe('to', 'sanitize_text_field', ''),
                'filters' => $filters,
            ] );
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @return \WP_REST_Response | array
     */
    public function updateSettings (Request $request, Activity $activity)
    {
        try {
            // Read raw: sanitize_text_field on the array would turn it into an empty string.
            $raw = $request->get('activity_settings', null);
            $settings = is_array($raw) ? $raw : [];
            $settings = [
                'delete_days'         => isset($settings['delete_days']) ? intval($settings['delete_days']) : 14,
                'disable_logs'        => isset($settings['disable_logs']) ? sanitize_text_field($settings['disable_logs']) : 'no',
                'open_link_in_new_tab' => isset($settings['open_link_in_new_tab']) ? sanitize_text_field($settings['open_link_in_new_tab']) : 'no',
            ];
            return $activity->updateSettings($settings);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @return \WP_REST_Response | array
     */
    public function getSettings(Activity $activity)
    {
        try {
            return $activity->getSettings();
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }
}
