<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\Customers\CustomerController;
use FluentSupport\App\Modules\Customers\CustomerPolicy;

$router->prefix('customers')->withPolicy(CustomerPolicy::class)->group(function ($router) {
    $router->get('/', [CustomerController::class, 'index']);
    $router->post('/', [CustomerController::class, 'create']);
    $router->get('/customerField/{customer_id}', [CustomerController::class, 'fields'])->int('customer_id');
    $router->get('/{customer_id}', [CustomerController::class, 'show'])->int('customer_id');
    $router->put('/{customer_id}', [CustomerController::class, 'update'])->int('customer_id');
    $router->delete('/{customer_id}', [CustomerController::class, 'delete'])->int('customer_id');
    $router->delete('/bulk-delete', [CustomerController::class, 'bulkDelete']);
    $router->put('/bulk-status', [CustomerController::class, 'bulkStatus']);
    $router->post('/profile_image/{customer_id}', [CustomerController::class, 'uploadAvatar'])->int('customer_id');
    $router->post('/reset_avatar/{customer}', [CustomerController::class, 'resetAvatar'])->int('customer');
});

// The contact search for a new ticket (any agent; partial search needs fst_sensitive_data).
$router->prefix('tickets')->withPolicy('AgentTicketPolicy')->group(function ($router) {
    $router->get('search-contact', [CustomerController::class, 'searchContact']);
});
