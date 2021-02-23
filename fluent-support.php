<?php defined('ABSPATH') or die;

/*
Plugin Name: Alpha
Description: An accounting plugin to manage expenses.
Version: 1.0.0
Author: Sheikh Heera
Author URI: https://heera.it
Plugin URI: https://heera.it
License: GPLv2 or later
Text Domain: alpha
Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '0.1');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));


add_action('init', function () {
    if(isset($_GET['test'])) {
        $ticket = \FluentSupport\App\Models\Ticket::with('customer')->find(1);
        do_action('fluent_support/ticket_created', $ticket, $ticket->customer);
    }
});
