<?php defined('ABSPATH') or die;

/*
Plugin Name: Fluent Support
Description: The Ultimate Support Plugin For Your Own WordPress.
Version: 1.0
Author: WPManageNinja LLC
Author URI: https://wpmanageninja.com
Plugin URI: https://fluentsupport.com
License: GPLv2 or later
Text Domain: fluent-support
Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '1.0');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));

//add_action('init', function () {
//    global $wpdb;
//    $query = \FluentSupport\App\Models\Ticket::orderBy('last_agent_response', 'DESC')->limit(10);
//
//    $query->where(function ($q) use ($wpdb) {
//        $q->whereRaw($wpdb->prefix.'fs_tickets.last_agent_response < '.$wpdb->prefix.'fs_tickets.last_customer_response')
//            ->orWhereNull('last_agent_response')
//            ->orWhere('status', 'new');
//    })->whereIn('status', ['new', 'active']);
//
//    $tickets = $query->get();
//
//    print_r($wpdb->last_query); die();
//
//    print_r($tickets->toArray()); die();
//
//});
