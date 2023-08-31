<?php defined('ABSPATH') or die;
/**
 * Plugin Name: Fluent Support
 * Description: The Ultimate Support Plugin For Your WordPress.
 * Version: 1.7.2
 * Author: WPManageNinja LLC
 * Author URI: https://wpmanageninja.com
 * Plugin URI: https://fluentsupport.com
 * License: GPLv2 or later
 * Text Domain: fluent-support
 * Domain Path: /language
*/

define('FLUENT_SUPPORT_VERSION', '1.7.2');
define('FLUENT_SUPPORT_PRO_MIN_VERSION', '1.7.2');
define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');
define('FLUENT_SUPPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_SUPPORT_PLUGIN_PATH', plugin_dir_path(__FILE__));

require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));


add_action('wp_insert_site', function ($new_site) {
    if (is_plugin_active_for_network('fluent-support/fluent-support.php')) {
        switch_to_blog($new_site->blog_id);
        (new \FluentSupport\App\Hooks\Handlers\ActivationHandler)->handle(false);
        restore_current_blog();
    }
});

add_filter('block_categories_all', function ($categories, $post) {
    return array_merge(
        array(
            array(
                'slug'  => 'fluent-support',
                'title' => __('Fluent Support', 'fluent-support')
            ),
        ),
        $categories
    );

}, 1, 2);
function create_block_gutenberg_block_init() {
    wp_register_script(
        'fluent-support/customer-portal',
        plugin_dir_url(__FILE__) . '/assets/block-editor/js/fst_block.js',
        array( 'wp-blocks', 'wp-element')
    );
    wp_localize_script(
        'fluent-support/customer-portal',
        'rest_url',
        array(
            'url' => 'https://www.fs.test/index.php?rest_route=/fluent-support/v2',
        )
    );
    register_block_type( 'fluent-support/customer-portal' , array(
        'editor_script' => 'fluent-support/customer-portal',
    ));
}
add_action( 'init', 'create_block_gutenberg_block_init' );
