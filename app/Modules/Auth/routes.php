<?php

defined('ABSPATH') || exit;

/**
 * The portal's own login, signup, password reset and email login code. Public:
 * each endpoint checks its own nonce.
 *
 * @var \FluentSupport\Framework\Http\Router $router
 */

use FluentSupport\App\Modules\Auth\AuthController;

$router->withPolicy('PublicPolicy')->group(function ($router) {
    $router->post('signup', [AuthController::class, 'signup']);
    $router->post('login', [AuthController::class, 'login']);
    $router->post('two_fa', [AuthController::class, 'verifyLoginCode']);
    $router->post('reset_pass', [AuthController::class, 'resetPassword']);
});
