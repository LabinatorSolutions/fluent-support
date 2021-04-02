<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\Database\DBMigrator;

class ActivationHandler
{
    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
    }
}
