<?php

namespace FluentSupport\App\Services;


class TransStrings
{
    public static function getTransStrings()
    {
        return [
            'dashboard_sub_heading' => __('Here are a few tickets you may want to take a look at', 'fluent-support'),
            'dashboard_all_catch_up' => __('Looks like you have caught up everything for now.', 'fluent-support'),
            'Your Overview for Today' => __('Your Overview for Today', 'fluent-support')
        ];
    }
}
