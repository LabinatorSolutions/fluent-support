<?php defined('ABSPATH') or die;

/*
Plugin Name: Fluent Support
Description: The Ultimate Support Plugin For Your Own WordPress.
Version: 1.0.0
Author: WPManageNinja LLC
Author URI: https://wpmanageninja.com
Plugin URI: https://wpmanageninja.com
License: GPLv2 or later
Text Domain: alpha
Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '0.2');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));

