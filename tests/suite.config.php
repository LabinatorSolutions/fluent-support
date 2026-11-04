<?php
/**
 * Per-plugin test suite configuration for the Fluent Support family.
 *
 * Plugin-specific names live only here. Porting the suite to another WPFluent
 * plugin means editing this file and one regex in the lint.
 */

return [
    // Plugin identity ------------------------------------------------------
    'plugin_slug'      => 'fluent-support',

    // Substring that marks a PHP notice/warning as ours. Also matches
    // fluent-support-pro on purpose.
    'plugin_dir_hint'  => 'plugins/fluent-support',

    // REST -----------------------------------------------------------------
    'rest_namespace'   => 'fluent-support/v2',
    'routes_file'      => 'app/Http/Routes/api.php',

    // Database -------------------------------------------------------------
    // Plugin table prefix, without the WordPress prefix. Also the regex in
    // lint/raw-sql-prefix.php.
    'table_prefix'     => 'fs_',

    // Counted before and after every run; a change is a hard failure.
    'protected_tables' => ['fs_tickets', 'fs_conversations', 'fs_persons'],

    // Framework wiring -----------------------------------------------------
    // There is no global app function like FluentCrm(), so use the facade.
    'app_bootstrap'    => ['\\FluentSupport\\App\\App', 'getInstance'],
    'request_class'    => 'FluentSupport\\Framework\\Http\\Request\\Request',

    // A class that only exists when the plugin is loaded. Proves the harness
    // booted against the right install.
    'sentinel_class'   => 'FluentSupport\\App\\Models\\Ticket',

    // Caches ---------------------------------------------------------------
    // Transient LIKE patterns flushed before every suite, since a warm
    // transient can hide broken SQL. fluent-support has no object-cache group.
    'cache_groups'     => [],

    // Optional seams -------------------------------------------------------
    // fluent-support has no loopback-intercept filter.
    'loopback_filter'  => '',

    // Product family -------------------------------------------------------
    // fluent-connect is a FluentCRM/ThriveCart product, not part of this family.
    'siblings'         => [
        'fluent-support-pro' => [
            'kind'        => 'pro',
            'constant'    => 'FLUENTSUPPORTPRO',
            'namespace'   => 'FluentSupportPro',
            'routes_file' => 'app/Http/routes.php', // non-standard path
            'path_hint'   => '../fluent-support-pro',
        ],
    ],
];
