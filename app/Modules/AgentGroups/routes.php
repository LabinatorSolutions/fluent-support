<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\AgentGroups\AgentGroupController;

$router->prefix('agent-groups')->withPolicy('AdminSensitivePolicy')->group(function ($router) {
    $router->get('/', [AgentGroupController::class, 'index']);
    $router->post('/', [AgentGroupController::class, 'create']);
    $router->get('/{group_id}', [AgentGroupController::class, 'get'])->int('group_id');
    $router->put('/{group_id}', [AgentGroupController::class, 'update'])->int('group_id');
    $router->delete('/{group_id}', [AgentGroupController::class, 'delete'])->int('group_id');
});
