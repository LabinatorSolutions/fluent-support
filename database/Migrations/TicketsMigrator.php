<?php

namespace FluentSupport\App\Database\Migrations;

class TicketsMigrator
{
    static $tableName = 'fs_tickets';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `customer_id` BIGINT(20) UNSIGNED NOT NULL,
                `agent_id` BIGINT(20) UNSIGNED NOT NULL,
                `product_id` BIGINT(20) UNSIGNED NOT NULL,
                `product_source` VARCHAR(192) NULL,
                `privacy` VARCHAR(100) DEFAULT 'private',
                `priority` VARCHAR(100) DEFAULT 'normal',
                `client_priority` VARCHAR(100) DEFAULT 'normal',
                `status` VARCHAR(100) DEFAULT 'new',
                `title` VARCHAR(192) NULL,
                `slug` VARCHAR(192) NULL,
                `content` LONGTEXT NULL,
                `last_agent_response` TIMESTAMP NULL,
                `last_customer_response` TIMESTAMP NULL,
                `response_count` INT(11) DEFAULT 0,
                `first_response_time` INT(11) NULL, /* Seconds took for first contact */
                `total_close_time` INT(11) NULL, /* Seconds took for closing this ticket */
                `resolved_at` TIMESTAMP NULL,
                `closed_by` BIGINT(20) UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
