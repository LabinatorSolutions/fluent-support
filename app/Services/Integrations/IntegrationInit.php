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

        if(defined('FLUENTCRM')) {
            (new FluentCRM())->boot();
        }

        $this->addNotificationIntegrations();
    }

    private function addNotificationIntegrations()
    {
        IntegrationSettingsModule::addIntegration(new TelegramNotification());
    }
}
