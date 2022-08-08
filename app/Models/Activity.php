<?php

namespace FluentSupport\App\Models;

use Exception;
use FluentSupport\App\Services\Helper;

class Activity extends Model
{
    protected $table = 'fs_activities';

    protected $fillable = ['person_id', 'person_type', 'event_type', 'object_id', 'object_type', 'description'];

    public function person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'person_id', 'id'
        );
    }

    // Get All Activities
    public function getActivities ()
    {
        $activities = static::with([
            'person' => function ($query) {
                $query->select(['first_name', 'person_type', 'last_name', 'id', 'avatar']);
            }
        ])->latest('id')->paginate();

        if (!$activities) {
            throw new Exception('No activities found');
        }

        $settings = $this->getSettings();

        return [
            'activities' => $activities,
            'settings'   => $settings['activity_settings']
        ];
    }

    // Update Activity Settings
    public function updateSettings ($settings)
    {
        $defaults = [
            'delete_days'  => 14,
            'disable_logs' => 'no'
        ];
        $settings = wp_parse_args($settings, $defaults);
        $settings['delete_days'] = (int)$settings['delete_days'];

        Helper::updateOption('_activity_settings', $settings);

        return [
            'message' => __('Activity settings has been updated', 'fluent-support')
        ];
    }

    // Get Activity Settings
    public function getSettings()
    {
        $settings = Helper::getOption('_activity_settings', []);

        $defaults = [
            'delete_days'  => 14,
            'disable_logs' => 'no'
        ];

        $settings = wp_parse_args($settings, $defaults);

        if (! $settings ) throw new Exception('No activity settings found');

        return [
            'activity_settings' => $settings
        ];

    }
}
