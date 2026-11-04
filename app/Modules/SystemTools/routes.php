<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\SystemTools\SystemToolsController;
use FluentSupport\App\Modules\SystemTools\SystemToolsPolicy;

$router->prefix('system-tools')->withPolicy(SystemToolsPolicy::class)->group(function ($router) {
    $router->any('/rest-test', [SystemToolsController::class, 'restTest']);

    $router->get('/cron', [SystemToolsController::class, 'getCronEvents']);
    $router->post('/cron/run', [SystemToolsController::class, 'runCron']);

    $router->get('/database', [SystemToolsController::class, 'getDatabaseHealth']);
    $router->post('/database/repair', [SystemToolsController::class, 'repairDatabase']);

    $router->get('/cleanup', [SystemToolsController::class, 'getCleanupCounts']);
    $router->delete('/cleanup', [SystemToolsController::class, 'deleteOldLogs']);
});
