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
