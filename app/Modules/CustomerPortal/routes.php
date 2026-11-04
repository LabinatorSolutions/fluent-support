<?php

defined('ABSPATH') || exit;

/**
 * Every route behind PortalPolicy. Handlers are named by class: a group
 * namespace would also be put in front of the shared controllers below.
 *
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Http\Controllers\OptionsController;
use FluentSupport\App\Http\Controllers\TicketController;
use FluentSupport\App\Http\Controllers\UploaderController;
use FluentSupport\App\Modules\CustomerPortal\PortalController;
use FluentSupport\App\Modules\CustomerPortal\PortalPolicy;

$router->prefix('customer-portal')->withPolicy(PortalPolicy::class)->group(function ($router) {
    $router->get('public_options', [PortalController::class, 'options']);
    $router->get('custom-fields-rendered', [PortalController::class, 'customFields']);
    $router->post('logout', [PortalController::class, 'logout']);
    $router->get('me', [TicketController::class, 'me']);

    $router->get('tickets', [PortalController::class, 'index']);
    $router->post('tickets', [PortalController::class, 'store']);
    $router->get('tickets/{ticket_id}', [PortalController::class, 'show'])->int('ticket_id');
    $router->post('tickets/{ticket_id}/responses', [PortalController::class, 'reply'])->int('ticket_id');
    $router->post('tickets/{ticket_id}/close', [PortalController::class, 'close'])->int('ticket_id');
    $router->post('tickets/{ticket_id}/re-open', [PortalController::class, 'reopen'])->int('ticket_id');
    $router->post('tickets/{ticket_id}/agent-feedback', [PortalController::class, 'rate'])->int('ticket_id');

    // Customers, remote portals and signed links upload here.
    $router->post('ticket_file_upload', [UploaderController::class, 'uploadTicketFiles']);
});

// The same policy outside the prefix: agents upload from the admin editor here,
// and the country list serves admin and portal forms.
$router->post('ticket_file_upload', [UploaderController::class, 'uploadTicketFiles'])->withPolicy(PortalPolicy::class);
$router->get('me', [TicketController::class, 'me'])->withPolicy(PortalPolicy::class);
$router->get('options/countries', [OptionsController::class, 'getCountries'])->withPolicy(PortalPolicy::class);
