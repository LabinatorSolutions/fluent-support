<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\CommunityTicket\CommunityTicketController;
use FluentSupport\App\Modules\CommunityTicket\CommunityTicketPolicy;

$router->prefix('community')->withPolicy(CommunityTicketPolicy::class)->group(function ($router) {
    $router->post('/ticket', [CommunityTicketController::class, 'create']);
});
