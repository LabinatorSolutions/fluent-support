<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\TicketImport\ImportController;

$router->prefix('ticket_importer')->withPolicy('AdminSettingsPolicy')->group(function ($router) {
    $router->get('/', [ImportController::class, 'stats']);
    $router->post('/import', [ImportController::class, 'import']);
    $router->post('/helpscout/token', [ImportController::class, 'helpScoutToken']);
    $router->delete('/delete', [ImportController::class, 'deleteSourceTickets']);
});
