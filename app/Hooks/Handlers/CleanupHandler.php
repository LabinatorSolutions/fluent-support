<?php

namespace FluentSupport\App\Hooks\Handlers;


use FluentSupport\App\Models\Meta;

class CleanupHandler
{
    public function initHourlyTasks()
    {
        $this->cleanLiveActivities();
    }

    protected function cleanLiveActivities()
    {
        // Delete All Live Activity older than 24 hours
        $oldDateTime = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 86400);

        Meta::where('key', '_live_activity')
            ->where('updated_at', '<', $oldDateTime)
            ->delete();
    }

}
