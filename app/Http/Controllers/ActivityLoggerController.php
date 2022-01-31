<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Services\Helper;

/**
 *  ActivityLoggerController class for REST API
 * This class is responsible for getting data for all request related to activity and activity settings
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */

class ActivityLoggerController extends Controller
{
    /**
     * getActivities method will get information regarding all activity with users(agent/customer) and activity settings
     * @return array
     */
    public function getActivities()
    {
        $activities = Activity::with([
            'person' => function ($query) {
                $query->select(['first_name', 'person_type', 'last_name', 'id', 'avatar']);
            }
        ])->orderBy('id', 'DESC')->paginate();

        $settings = $this->getSettings();

        return [
            'activities' => $activities,
            'settings'   => $settings['activity_settings']
        ];
    }

    /**
     * getSettings method will get the list of activity settings and return
     * @return array
     */
    public function getSettings()
    {
        $settings = Helper::getOption('_activity_settings', []);

        $defaults = [
            'delete_days'  => 14,
            'disable_logs' => 'no'
        ];

        $settings = wp_parse_args($settings, $defaults);

        return [
            'activity_settings' => $settings
        ];

    }

    /**
     * updateSettings method will update existing activity settings
     * @return array
     */
    public function updateSettings()
    {
        $settings = $this->request->get('activity_settings', []);
        $defaults = [
            'delete_days'  => 14,
            'disable_logs' => 'no'
        ];
        $settings = wp_parse_args($settings, $defaults);
        $settings['delete_days'] = intval($settings['delete_days']);

        Helper::updateOption('_activity_settings', $settings);

        return [
            'message' => __('Activity settings has been updated', 'fluent-support')
        ];

    }
}
