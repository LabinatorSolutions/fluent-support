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

use FluentSupport\App\App;

require_once 'Handlers/AuthHandler.php';

(new \FluentSupport\App\Hooks\Handlers\AuthHandler)->init();

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_menu', 'Menu@add');
$app->addAction('admin_enqueue_scripts', 'Menu@maybeEnqueueAssets');

add_shortcode('fluent_support_portal', function () {
    return (new \FluentSupport\App\Hooks\Handlers\CustomerPortalHandler())->renderPortal();
});

add_shortcode('fluent_support_admin_portal', function () {
    $app = App::getInstance();
    $assets = $app['url.assets'];
    wp_enqueue_style('fluent_support_login_style', $assets.'admin/css/all_public.css');
    if (!get_current_user_id()) {
        $return = '<div class="fst_login"><h3>'.__('Please Login', 'fluent-support').'</h3>';
        $return .= do_shortcode('[fluent_support_login]');
        $return .= '</div>';
        return $return;
    }

    $currentUserPermissions = \FluentSupport\App\Modules\PermissionManager::currentUserPermissions();
    if (!$currentUserPermissions) {
        return __('Sorry, You do not have permission to this page', 'fluent-support');
    }

    add_filter('fluent_support/base_url', function ($url) {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request)) . '/#/';
    });

    add_filter('fluent_support/secondary_menu_items', function ($items) {
        global $wp;
        $items[] = [
            'key'       => 'logout',
            'label'     => __('Logout', 'fluent-support'),
            'permalink' => wp_logout_url(home_url(add_query_arg(array(), $wp->request)))
        ];
        return $items;
    });

    ob_start();
    echo '<div class="fst_front">';
    (new \FluentSupport\App\Hooks\Handlers\Menu())->renderApp();
    echo '</div>';
    return ob_get_clean();
});

// init integrations
(new \FluentSupport\App\Services\Integrations\IntegrationInit())->init();

// Activities
(new \FluentSupport\App\Hooks\Handlers\ActivityLogger())->init();

/*
 * Email Notification Hooks
 */

$app->addAction('fluent_support/ticket_created', 'EmailNotificationHandler@ticketCreated', 10, 2);
$app->addAction('fluent_support/response_added_by_agent', 'EmailNotificationHandler@agentReplied', 10, 3);
$app->addAction('fluent_support/response_added_by_customer', 'EmailNotificationHandler@customerReplied', 10, 3);
$app->addAction('fluent_support/ticket_closed_by_agent', 'EmailNotificationHandler@closedByAgent', 10, 2);

// Cleanup
$app->addAction('fluent_support_hourly_tasks', 'CleanupHandler@initHourlyTasks');
$app->addAction('fluent_support_daily_tasks', 'CleanupHandler@initDailyTasks');

if(isset($_GET['fs_view'])) {
    $app->addAction('init', 'ExternalPages@route');
}

if (isset($_GET['fst_file'])) {
    add_action('init', function () {
        (new \FluentSupport\App\Hooks\Handlers\ExternalPages())->view_attachment();
    });
}

// require the CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'fluent_support', '\FluentSupport\App\Hooks\CLI\FluentCli' );
}

