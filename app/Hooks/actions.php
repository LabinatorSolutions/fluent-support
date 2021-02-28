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

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_menu', 'Menu@add');

add_shortcode('fluent_support_portal', function () {
    return (new \FluentSupport\App\Hooks\Handlers\CustomerPortalHandler())->renderPortal();
});


add_shortcode('fluent_support_login', function () {
    $app = App::getInstance();
    $assets = $app['url.assets'];
    wp_enqueue_style('fluent_support_login_style', $assets.'admin/css/all_public.css');
    $return = '<div class="fst_login_wrapper">';
    $return .= wp_login_form([
        'echo'           => false,
        'redirect'       => get_permalink(get_the_ID()),
        'remember'       => true,
        'value_remember' => true
    ]);
    $return .= '</div>';
    return $return;
});

add_shortcode('fluent_support_admin_portal', function () {
    if (!get_current_user_id()) {
        $return = '<div class="fst_login"><h3>Please Login</h3>';
        $return .= do_shortcode('[fluent_support_login]');
        $return .= '</div>';
        return $return;
    }

    $currentUserPermissions = \FluentSupport\App\Modules\PermissionManager::currentUserPermissions();
    if (!$currentUserPermissions) {
        return 'Sorry, You do not have permission to this page';
    }

    add_filter('fluent_support_base_url', function ($url) {
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
