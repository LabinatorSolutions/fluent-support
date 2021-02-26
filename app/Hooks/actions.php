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

add_shortcode('fluent_support_admin_portal', function () {
    $currentUserPermissions = \FluentSupport\App\Modules\PermissionManager::currentUserPermissions();
    if (!$currentUserPermissions) {
        $return = '<div class="fst_login"><h3>Sorry, You don not have permission to view this</h3>';
        $return .= wp_login_form([
            'echo'           => false,
            'redirect'       => get_permalink(get_the_ID()),
            'remember'       => true,
            'value_remember' => true
        ]);
        $return .= '</div>';
        return $return;
    }
    add_filter('fluent_support_base_url', function ($url) {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request)) . '/#/';
    });

    ob_start();
    echo '<div class="fst_front">';
    (new \FluentSupport\App\Hooks\Handlers\Menu())->renderApp();
    echo '</div>';
    return ob_get_clean();
});

// init integrations
(new \FluentSupport\App\Services\Integrations\IntegrationInit())->init();

/*
 * Email Notification Hooks
 */

$app->addAction('fluent_support/ticket_created', 'EmailNotificationHandler@ticketCreated', 10, 2);
$app->addAction('fluent_support/response_added_by_agent', 'EmailNotificationHandler@agentReplied', 10, 3);


if (isset($_GET['fst_file'])) {
    add_action('init', function () {
        (new \FluentSupport\App\Hooks\Handlers\ExternalPages())->view_attachment();
    });
}
