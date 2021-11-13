<?php defined('ABSPATH') or die;

/*
Plugin Name: Fluent Support
Description: The Ultimate Support Plugin For Your WordPress.
Version: 1.4.0
Author: WPManageNinja LLC
Author URI: https://wpmanageninja.com
Plugin URI: https://fluentsupport.com
License: GPLv2 or later
Text Domain: fluent-support
Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '1.4.0');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');
define('FLUENT_SUPPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));


// Handle Network new Site Activation
add_action('wpmu_new_blog', function ($blogId) {
    switch_to_blog($blogId);
    (new \FluentSupport\App\Hooks\Handlers\ActivationHandler)->handle(false);
    restore_current_blog();
});
