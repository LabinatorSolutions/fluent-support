<?php

namespace FluentSupport\Database\Migrations;

class AttachmentsMigrator
{
    static $tableName = 'fs_attachments';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `ticket_id` BIGINT(20) UNSIGNED NULL,
                `person_id` BIGINT(20) UNSIGNED NULL,
                `conversation_id` BIGINT(20) UNSIGNED NULL,
                `file_type` VARCHAR(100) NULL,
                `file_path` TEXT NULL,
                `full_url` TEXT NULL,
                `settings` TEXT NULL,
                `title` VARCHAR(192) NULL,
                `file_hash` VARCHAR(192) NULL,
                `driver` VARCHAR(100) DEFAULT 'local',
                `status` VARCHAR(100) NULL DEFAULT 'active',
                `file_size` VARCHAR(100) NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_file_hash` (`file_hash`),
                INDEX `idx_ticket_id` (`ticket_id`),
                INDEX `idx_person_id` (`person_id`),
                INDEX `idx_conversation_id` (`conversation_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_created_at` (`created_at`)
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
        global $wpdb;

        // Our own table name, not user input. esc_sql() because %i needs WP 6.2+.
        $table = esc_sql($table);

        // @todo: Beta-only migration, remove on final release.
        $existing_columns = $wpdb->get_col("DESC `{$table}`", 0); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!in_array('status', $existing_columns)) {
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- $table is sanitized via esc_sql(); column name is a hardcoded literal.
            $wpdb->query("ALTER TABLE `{$table}` ADD `status` VARCHAR(100) NULL DEFAULT 'active' AFTER `driver`");
        }

        static::addMissingIndexes($table);
    }

    public static function addMissingIndexes($table)
    {
        global $wpdb;

        // Escaped again in case this is called directly.
        $table = esc_sql($table);

        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            $existing_index_names[] = $index->Key_name;
        }

        $indexes = [
            'idx_ticket_id'       => 'ticket_id',
            'idx_person_id'       => 'person_id',
            'idx_conversation_id' => 'conversation_id',
            'idx_status'          => 'status',
            'idx_created_at'      => 'created_at',
            'idx_file_hash'       => 'file_hash',
        ];

        foreach ($indexes as $index_name => $column_name) {
            if (!in_array($index_name, $existing_index_names)) {
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- all identifiers are either esc_sql()'d or hardcoded literals.
                $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$column_name}`)");
            }
        }
    }
}
