<?php

defined('ABSPATH') or die;

/**
 * @var $app FluentSupport\Framework\Foundation\Application
 */

(new \FluentSupport\App\Modules\Auth\AuthForms)->init();

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_menu', 'Menu@add');
$app->addAction('admin_enqueue_scripts', 'Menu@maybeEnqueueAssets');
$app->addAction('admin_bar_menu', 'AdminBarHandler@init');
$app->addAction('wp_dashboard_setup', 'AdminBarHandler@initAdminWidget');

$app->addShortcode('fluent_support_portal', 'ShortcodeHandler@fluentSupportPortal');

add_action('init', function () {
    (new \FluentSupport\App\Services\Integrations\IntegrationInit())->init();
});

(new \FluentSupport\App\Hooks\Handlers\ActivityLogger())->init();

(new \FluentSupport\App\Hooks\Handlers\AIActivityLogger())->init();

(new \FluentSupport\App\Modules\Notifications\NotificationListener())->register();

$app->addAction('fluent_support/ticket_created', 'EmailNotificationHandler@ticketCreated', 10, 2);
$app->addAction('fluent_support/response_added_by_agent', 'EmailNotificationHandler@agentReplied', 10, 3);
$app->addAction('fluent_support/response_added_by_customer', 'EmailNotificationHandler@customerReplied', 10, 3);
$app->addAction('fluent_support/ticket_closed_by_agent', 'EmailNotificationHandler@closedByAgent', 10, 2);
$app->addAction('fluent_support/agent_assigned_to_ticket', 'EmailNotificationHandler@onAgentAssign', 10, 3);
$app->addAction('fluent_support/ticket_created_behalf_of_customer', 'EmailNotificationHandler@ticketCreatedByAgent', 10, 3);
$app->addAction('fluent_support/agent_initiated_ticket_response', 'EmailNotificationHandler@ticketCreatedByAgentOnBehalf', 10, 3);

$app->addAction('fluent_support/async_agent_assigned_to_ticket', 'AsyncActionHandler@resolveAgentAssigned', 10, 4);

$app->addAction('fluent_support_hourly_tasks', 'CleanupHandler@initHourlyTasks');
$app->addAction('fluent_support_daily_tasks', 'CleanupHandler@initDailyTasks');
$app->addAction('fluent_support_weekly_tasks', 'CleanupHandler@maybeMaintanceTask');

$app->addAction('fluent_support/deleting_ticket', 'CleanupHandler@deleteTicketAttachments');

// Background remote storage work (Action Scheduler)
add_action(\FluentSupport\App\Services\Storage\StorageManager::PUSH_HOOK, ['\FluentSupport\App\Services\Storage\StorageManager', 'pushPending']);
add_action(\FluentSupport\App\Services\Storage\StorageManager::DELETE_HOOK, ['\FluentSupport\App\Services\Storage\StorageManager', 'processDeleteQueue']);
$app->addAction('fluent_support/ticket_closed', 'CleanupHandler@maybeDeleteAttachmentsOnClose');

if(isset($_GET['fs_view'])) {
    $app->addAction('init', 'ExternalPages@route');
}

if (isset($_GET['fst_file'])) {
    add_action('init', function () {
        (new \FluentSupport\App\Hooks\Handlers\ExternalPages())->view_attachment();
    });
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'fluent_support', '\FluentSupport\App\Hooks\CLI\FluentCli' );
}


(new \FluentSupport\App\Hooks\Handlers\PermissionFilterManager)->init();

(new \FluentSupport\App\Hooks\Handlers\PrivacyHandler())->init();

$app->addAction('profile_update', '\FluentSupport\App\Services\ProfileInfoService@onWPProfileUpdate', 10, 3);

// Registration is the only point that auto-links a customer row to a WP user.
// An email change alone never claims a row.
$app->addAction('user_register', '\FluentSupport\App\Services\ProfileInfoService@onWPUserRegister', 10, 1);
$app->addAction('wp_ajax_fs_export_agent_report', 'FluentSupport\App\Hooks\Handlers\DataExporter@exportReport');

// Disable LiteSpeed ESI on Fluent Support requests.
if(defined('LSCWP_V')){
    add_action('litespeed_init', function (){
        if(isset($_SERVER['REQUEST_URI']) && strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), 'fluent-support') !== false){
            defined( 'LITESPEED_ESI_OFF' ) || define( 'LITESPEED_ESI_OFF', true );
        }
    });
}

$app->addAction('init', 'BlockEditorHandler@init');

// Boot MCP unconditionally so FluentHub can list us on WP < 6.9. The
// ability/server hooks only fire when the Abilities API or MCP adapter exists.
\FluentSupport\App\Modules\MCP\MCPInit::boot();


// Feature modules (app/Modules/{Name}/routes.php).
/** @var \FluentSupport\Framework\Foundation\Application $app */
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/CustomerPortal/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/Auth/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/TicketImport/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/Notifications/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/CommunityTicket/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/AgentGroups/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/Reports/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/Customers/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/Agents/routes.php';
});
$app->router->group(function ($router) {
    require FLUENT_SUPPORT_PLUGIN_PATH . 'app/Modules/SystemTools/routes.php';
});
