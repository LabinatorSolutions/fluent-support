<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Services\Helper;

class ActivityLoggerController extends Controller
{
    public function getActivities()
    {
        $activities = Activity::with([
            'person' => function ($query) {
                $query->select(['first_name', 'person_type', 'last_name', 'id', 'avatar']);
            }
        ])->orderBy('id', 'DESC')->get();

        return [
            'activities' => $activities,
        ];
    }

    public function getSettings()
    {
        $settings = Helper::getOption('_activity_settings', []);

        $defaults = [
            'delete_days' => 14
        ];

        $settings = wp_parse_args($settings, $defaults);

        return [
            'activity_settings' => $settings
        ];

    }

    public function updateSettings()
    {
        $settings = $this->request->get('activity_settings', []);
        $defaults = [
            'delete_days' => 14
        ];
        $settings = wp_parse_args($settings, $defaults);
        $settings['delete_days'] = intval($settings['delete_days']);

        Helper::updateOption('_activity_settings', $settings);

        return [
            'message' => __('Activity settings has been updated', 'fluent-support')
        ];

    }
}
