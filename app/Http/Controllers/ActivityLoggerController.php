<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Activity;

class ActivityLoggerController
{
    public function getActivities()
    {
        $activities = Activity::with([
            'person' => function ($query) {
                $query->select(['first_name', 'email', 'person_type', 'last_name', 'id', 'title', 'avatar']);
            }
        ])->orderBy('id', 'DESC')->get();

        return [
            'activities' => $activities,
        ];
    }
}
