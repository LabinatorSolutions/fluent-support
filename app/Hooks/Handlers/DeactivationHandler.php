<?php

namespace FluentSupport\App\Hooks\Handlers;

class DeactivationHandler
{
    public function handle()
    {
        wp_clear_scheduled_hook( 'fluent_support/hourly_tasks' );
    }
}
