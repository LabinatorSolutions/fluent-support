<?php

namespace FluentSupport\App\Services;


class ProfileInfoService
{
    public static function getProfileExtraWidgets($customer)
    {
        $widgets = [];
        if(defined('FLUENTCRM')) {
            $profile = fluentcrm_get_crm_profile_html($customer->email, false);
            if($profile) {
                $widgets['fluentcrm'] = [
                    'header' => __('CRM Profile', 'fluent-support'),
                    'body_html' => $profile
                ];
            }
        }

        $widgets = apply_filters('fluent_support/customer_extra_widgets', $widgets, $customer);

        return $widgets;
    }
}
