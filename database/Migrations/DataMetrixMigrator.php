<?php

namespace FluentSupport\App\Database\Migrations;

class DataMetrixMigrator
{
    static $tableName = 'fs_data_metrix';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `stat_date` DATE NOT NULL,
                `new_tickets` BIGINT(20) UNSIGNED NOT NULL,  /* All the new tickets that added */
                `unresolved_tickets` BIGINT(20) UNSIGNED NOT NULL,  /* New Tickets that is still on new status */
                `resolved_tickets` BIGINT(20) UNSIGNED NOT NULL, /* Tickets that resolved today */
                `agent_replies` BIGINT(20) UNSIGNED NOT NULL, /* Agent Replies today */
                `customer_replies` BIGINT(20) UNSIGNED NOT NULL, /* Customer Replies today */
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
