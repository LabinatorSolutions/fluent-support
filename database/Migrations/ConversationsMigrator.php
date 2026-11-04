<?php

namespace FluentSupport\Database\Migrations;

class ConversationsMigrator
{
    static $tableName = 'fs_conversations';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `serial` INT(11) UNSIGNED DEFAULT 1,
                `ticket_id` BIGINT(20) UNSIGNED NOT NULL,
                `person_id` BIGINT(20) UNSIGNED NOT NULL,
                `conversation_type` VARCHAR(100) DEFAULT 'response',
                `content` LONGTEXT NULL,
                `source` VARCHAR(100) DEFAULT 'web',
                `content_hash` VARCHAR(192) NULL,
                `message_id` VARCHAR(192) NULL,
                `is_important` ENUM('yes', 'no') DEFAULT 'no',
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_ticket_id` (`ticket_id`),
                INDEX `idx_person_id` (`person_id`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_person_id_created_at` (`person_id`, `created_at`)
            ) $charsetCollate;";
            $created = dbDelta($sql);
            return $created;
        } else {
            static::alterTable($table);
        }

        return false;
    }

    public static function alterTable($table)
    {
        static::addMissingIndexes($table);
    }

    public static function addMissingIndexes($table)
    {
        global $wpdb;

        // Our own table name, not user input. esc_sql() because %i needs WP 6.2+.
        $table = esc_sql($table);

        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            $existing_index_names[] = $index->Key_name;
        }

        // idx_person_id_created_at serves Advanced Reports' agent + date range queries.
        // Without it MySQL scans the agent's whole history via idx_person_id.
        $indexes = [
            'idx_ticket_id'            => '`ticket_id`',
            'idx_person_id'            => '`person_id`',
            'idx_created_at'           => '`created_at`',
            'idx_person_id_created_at' => '`person_id`, `created_at`',
        ];

        foreach ($indexes as $index_name => $columns) {
            if (!in_array($index_name, $existing_index_names)) {
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- all identifiers are either esc_sql()'d or hardcoded literals.
                $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` ({$columns})");
            }
        }
    }
}
