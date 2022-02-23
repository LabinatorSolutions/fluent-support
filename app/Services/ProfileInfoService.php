<?php

namespace FluentSupport\App\Services;


class ProfileInfoService
{
    public static function getProfileExtraWidgets($customer)
    {
        $widgets = [];
        /*
         * @since v1.0.0
         * Filter customer profile widgets
         * @param array $widgets
         * @param object|array  $customer
         */
        $widgets = apply_filters('fluent_support/customer_extra_widgets', $widgets, $customer);
        return $widgets;
    }
}
