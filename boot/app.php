<?php

use FluentSupport\Framework\Foundation\Application;
use FluentSupport\App\Hooks\Handlers\ActivationHandler;
use FluentSupport\App\Hooks\Handlers\DeactivationHandler;

return function($file) {

    register_activation_hook($file, function() {
        (new ActivationHandler)->handle();
    });

    register_deactivation_hook($file, function() {
        (new DeactivationHandler)->handle();
    });

    add_action('plugins_loaded', function() use ($file) {
        $application = new Application($file);

        load_plugin_textdomain('fluent-support', false, 'fluent-support/language/');

        do_action('fluent_support_loaded', $application);
        do_action('fluent_support_addons_loaded', $application);
    });
};
