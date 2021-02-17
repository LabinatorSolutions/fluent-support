<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Database\DBMigrator;
use FluentSupport\App\Database\DBSeeder;

class ActivationHandler
{
    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
        DBSeeder::run();
    }
}
