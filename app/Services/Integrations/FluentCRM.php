<?php

namespace FluentSupport\App\Services\Integrations;

class FluentCRM
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getCRMProfile'), 10, 2);
    }

    public function getCRMProfile($widgets, $customer)
    {
        $profile = fluentcrm_get_crm_profile_html($customer->email, false);
        if($profile) {
            $widgets['fluentcrm'] = [
                'header' => __('CRM Profile', 'fluent-support'),
                'body_html' => $profile
            ];
        }

        return $widgets;
    }
}
