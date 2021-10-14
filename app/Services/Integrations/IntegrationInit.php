<?php

namespace FluentSupport\App\Services\Integrations;


use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\App\Services\Integrations\Telegram\TelegramNotification;

class IntegrationInit
{
    public function init()
    {
        // Easy Digital Downloads
        if (class_exists('\Easy_Digital_Downloads')) {
            (new Edd())->boot();
        }

        // WooCommerce
        if(defined('WC_PLUGIN_FILE')) {
            (new WooCommerce())->boot();
        }

        if(defined('FLUENTCRM')) {
            (new FluentCRM())->boot();
        }

        if(defined('FLUENTFORM')) {
            new \FluentSupport\App\Services\Integrations\FluentForm\FeedIntegration(wpFluentForm());
        }

        $this->addNotificationIntegrations();
    }

    private function addNotificationIntegrations()
    {
        IntegrationSettingsModule::addIntegration(new TelegramNotification());
    }
}
