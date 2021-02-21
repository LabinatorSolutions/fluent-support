<?php

namespace FluentSupport\App\Services\Integrations;


class IntegrationInit
{
    public function init()
    {
        // Easy Digital Downloads
        if (class_exists('\Easy_Digital_Downloads')) {
            (new Edd())->boot();
        }
    }
}
