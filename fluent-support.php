<?php defined('ABSPATH') or die;

/*
Plugin Name: Fluent Support
Description: The Ultimate Support Plugin For Your Own WordPress.
Version: 1.1.0
Author: WPManageNinja LLC
Author URI: https://wpmanageninja.com
Plugin URI: https://fluentsupport.com
License: GPLv2 or later
Text Domain: fluent-support
Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '1.1.1');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));


