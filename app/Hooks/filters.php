<?php

/**
 * All registered filter's handlers should be in app\Hooks\Handlers,
 * addFilter is similar to add_filter and addCustomFlter is just a
 * wrapper over add_filter which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomFilter('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_filter('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app \FluentSupport\Framework\Foundation\Application;
 */


add_filter('fluent_support/parse_smartcode_data', function ($string, $data) {
    return (new \FluentSupport\App\Services\Parser\Parser())->parse($string, $data);
}, 10, 2);


/*
 * In the WP core wp-includes/functions.php file, where the filter is defined for the list of mime types and file extensions
 * In the list the JSON file type/extension is missing. So we had to add this application/JSON type to the list by the hooks
 */

add_filter('mime_types', function($mimes) {
    $mimes['json'] = 'application/json';

    return $mimes;
});


add_filter('fluent_support/dashboard_notice', function ($messages) {
    if(defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && version_compare(FLUENT_SUPPORT_PRO_MIN_VERSION, FLUENTSUPPORTPRO_PLUGIN_VERSION, '>')) {
        $updateUrl = admin_url('plugins.php?s=fluent-support-pro&plugin_status=all&fluentsupport_pro_check_update=' . time());
        $html = '<div class="fs_box fs_dashboard_box"><div class="fs_box_header">Heads UP! Fluent Support Pro update available</div><div class="fs_box_body" style="padding: 20px;">Fluent Support Pro Plugin needs to be updated. <a href="'.esc_url($updateUrl).'>">Click here to update the plugin</a></div></div>';
        $messages .= $html;
    }
    return $messages;
}, 100);
