<?php

namespace FluentSupport\App\Services;


class ProfileInfoService
{
    public static function getProfileExtraWidgets($customer)
    {
        $widgets = [];
        $widgets = apply_filters('fluent_support/customer_extra_widgets', $widgets, $customer);
        return $widgets;
    }
}
