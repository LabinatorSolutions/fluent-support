<?php

defined('ABSPATH') || exit;

/**
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\Notifications\NotificationController;

$router->prefix('notifications')->withPolicy('AgentTicketPolicy')->group(function ($router) {
    $router->get('/', [NotificationController::class, 'index']);
    $router->get('/unread', [NotificationController::class, 'unread']);
    $router->get('/unread-count', [NotificationController::class, 'unreadCount']);
    $router->post('/mark-all-read', [NotificationController::class, 'markAllRead']);
    $router->post('/{notification_id}/mark-read', [NotificationController::class, 'markRead'])->int('notification_id');
});
