<?php

namespace FluentSupport\App\Database;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

use FluentSupport\App\Database\Migrations\MetaMigrator;
use FluentSupport\App\Database\Migrations\PersonsMigrator;
use FluentSupport\App\Database\Migrations\TicketsMigrator;
use FluentSupport\App\Database\Migrations\ConversationsMigrator;
use FluentSupport\App\Database\Migrations\AttachmentsMigrator;
use FluentSupport\App\Database\Migrations\TaggablesMigrator;
use FluentSupport\App\Database\Migrations\TagRelationsMigrator;
use FluentSupport\App\Database\Migrations\DataMetrixMigrator;
use FluentSupport\App\Database\Migrations\ProductsMigrator;

class DBMigrator
{
    protected static $migrators = [
        ProductsMigrator::class,
        PersonsMigrator::class,
        TicketsMigrator::class,
        ConversationsMigrator::class,
        AttachmentsMigrator::class,
        TaggablesMigrator::class,
        TagRelationsMigrator::class,
        DataMetrixMigrator::class,
        MetaMigrator::class
    ];

    public static function run($network_wide = false)
    {
        global $wpdb;

        if ($network_wide) {
            // Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
            if (function_exists('get_sites') && function_exists('get_current_network_id')) {
                $site_ids = get_sites(array(
                    'fields' => 'ids',
                    'network_id' => get_current_network_id()
                ));
            } else {
                $site_ids = $wpdb->get_col(
                    "SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;"
                );
            }
            // Install the plugin for all these sites.
            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                self::migrate();
                restore_current_blog();
            }
        } else {
            self::migrate();
        }
    }

    public static function migrate()
    {
        foreach (static::$migrators as $class) {
            $class::migrate();
        }
    }
}
