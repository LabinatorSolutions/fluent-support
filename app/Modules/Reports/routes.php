<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\Reports\ReportController;

$router->prefix('reports')->withPolicy('ReportPolicy')->group(function ($router) {
    $router->get('/stats', [ReportController::class, 'stats']);
});

$router->prefix('my-reports')->withPolicy('AgentTicketPolicy')->group(function ($router) {
    $router->get('/tickets-resolve-growth', [ReportController::class, 'myClosedChart']);
    $router->get('/response-growth', [ReportController::class, 'myRepliesChart']);
    $router->get('/my-summary', [ReportController::class, 'mySummary']);
});

// The dashboard.
$router->prefix('tickets')->withPolicy('AgentTicketPolicy')->group(function ($router) {
    $router->get('my_stats', [ReportController::class, 'dashboard']);
    $router->get('agent_performance', [ReportController::class, 'agentsToday']);
});
