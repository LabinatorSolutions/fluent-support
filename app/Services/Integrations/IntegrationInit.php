<?php

namespace FluentSupport\App\Services\Integrations;


<<<<<<< HEAD
use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\App\Services\Integrations\Slack\SlackNotification;
use FluentSupport\App\Services\Integrations\Telegram\TelegramNotification;

=======
>>>>>>> 789d83ffe5329d1b5f8f4a837646efc349464191
class IntegrationInit
{
    public function init()
    {
        if(defined('FLUENTCRM')) {
            (new FluentCRM())->boot();
        }

        if(defined('FLUENTFORM')) {
            new \FluentSupport\App\Services\Integrations\FluentForm\FeedIntegration(wpFluentForm());
        }
<<<<<<< HEAD

        $this->addNotificationIntegrations();
    }

    private function addNotificationIntegrations()
    {
        IntegrationSettingsModule::addIntegration(new TelegramNotification());
        IntegrationSettingsModule::addIntegration(new SlackNotification());
=======
>>>>>>> 789d83ffe5329d1b5f8f4a837646efc349464191
    }
}
