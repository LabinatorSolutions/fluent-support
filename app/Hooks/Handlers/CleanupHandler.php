<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class CleanupHandler
{
    public function initHourlyTasks()
    {
        $this->cleanLiveActivities();
    }

    public function initDailyTasks()
    {
        $this->cleanActivityLogs();
    }

    protected function cleanLiveActivities()
    {
        // Delete All Live Activity older than 24 hours
        $oldDateTime = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 86400);

        Meta::where('key', '_live_activity')
            ->where('object_type', 'ticket_meta')
            ->where('updated_at', '<', $oldDateTime)
            ->delete();
    }

    protected function cleanActivityLogs()
    {
        $settings = Helper::getOption('_activity_settings', []);

        if (!$settings && empty($settings['delete_days'])) {
            $settings['delete_days'] = 14;
        }

        $oldDateTime = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - ($settings['delete_days'] * 86400));

        Activity::where('created_at', '<', $oldDateTime)->delete();
    }

}
