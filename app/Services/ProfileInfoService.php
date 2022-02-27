<?php

namespace FluentSupport\App\Services;


class ProfileInfoService
{
    public static function getProfileExtraWidgets($customer)
    {
        $widgets = [];
        /*
         * Filter customer profile widgets
         *
         * @since v1.0.0
         * @param array $widgets
         * @param object|array  $customer
         *
         * @return void
         */
        $widgets = apply_filters('fluent_support/customer_extra_widgets', $widgets, $customer);
        return $widgets;
    }
}
