<?php

namespace FluentSupport\App\Services\Integrations;


use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\App\Services\Integrations\Slack\SlackNotification;

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
    }

}
