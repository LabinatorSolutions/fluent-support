<?php

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app FluentSupport\Framework\Foundation\Application
 */

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_menu', 'Menu@add');

add_shortcode('fluent_support_portal', function () {
    return (new \FluentSupport\App\Hooks\Handlers\CustomerPortalHandler())->renderPortal();
});

// init integrations
(new \FluentSupport\App\Services\Integrations\IntegrationInit())->init();

/*
 * Email Notification Hooks
 */

$app->addAction('fluent_support/ticket_created', 'EmailNotificationHandler@ticketCreated', 10, 2);
$app->addAction('fluent_support/response_added_by_agent', 'EmailNotificationHandler@agentReplied', 10, 3);
