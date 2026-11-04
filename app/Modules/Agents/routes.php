<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\Agents\AgentController;
use FluentSupport\App\Modules\Agents\AgentPolicy;

$router->prefix('agents')->withPolicy(AgentPolicy::class)->group(function ($router) {
    $router->get('/', [AgentController::class, 'index']);
    $router->post('/', [AgentController::class, 'create']);
    $router->put('/{agent_id}', [AgentController::class, 'update'])->int('agent_id');
    $router->delete('/{agent_id}', [AgentController::class, 'delete'])->int('agent_id');
    $router->post('/avatar/{agent_id}', [AgentController::class, 'uploadAvatar'])->int('agent_id');
    $router->post('/reset_avatar/{agent}', [AgentController::class, 'resetAvatar'])->int('agent');
});

$router->prefix('tickets')->withPolicy('AgentTicketPolicy')->group(function ($router) {
    $router->get('ping', [AgentController::class, 'ping']);
});
