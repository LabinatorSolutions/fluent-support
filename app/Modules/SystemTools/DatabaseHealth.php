<?php

namespace FluentSupport\App\Modules\SystemTools;

use FluentSupport\Database\DBMigrator;
use FluentSupport\Database\Migrations\ActivityMigrator;
use FluentSupport\Database\Migrations\AIActivityLogsMigrator;
use FluentSupport\Database\Migrations\AttachmentsMigrator;
use FluentSupport\Database\Migrations\ConversationsMigrator;
use FluentSupport\Database\Migrations\DataMetrixMigrator;
use FluentSupport\Database\Migrations\MailBoxMigrator;
use FluentSupport\Database\Migrations\MetaMigrator;
use FluentSupport\Database\Migrations\NotificationsMigrator;
use FluentSupport\Database\Migrations\NotificationUsersMigrator;
use FluentSupport\Database\Migrations\PersonsMigrator;
use FluentSupport\Database\Migrations\ProductsMigrator;
use FluentSupport\Database\Migrations\TaggablesMigrator;
use FluentSupport\Database\Migrations\TagRelationsMigrator;
use FluentSupport\Database\Migrations\TicketsMigrator;

/**
 * Whether every Fluent Support table exists.
 */
class DatabaseHealth
{
    /**
     * @return array
     */
    public function getReport()
    {
        return [
            'tables' => $this->getTables(),
        ];
    }

    /**
     * Re-run the migrations; each one creates its table only when missing and adds missing columns.
     *
     * @return void
     */
    public function repair()
    {
        DBMigrator::migrate();

        /**
         * Fires after the core migrations re-run, so modules that add tables through
         * `fluent_support/system_tools_tables` can create theirs.
         *
         * @since 2.4.5
         */
        do_action('fluent_support/system_tools_database_repair');
    }

    /**
     * Missing tables first, then in the order defined in getDefinitions().
     *
     * @return array
     */
    protected function getTables()
    {
        global $wpdb;

        $existing = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'fs_') . '%'));

        $tables = [];
        foreach ($this->getDefinitions() as $definition) {
            $exists = in_array($wpdb->prefix . $definition['name'], $existing, true);

            // Created when their feature is first enabled, so their absence isn't a fault.
            if (!$exists && !empty($definition['lazy'])) {
                continue;
            }

            $tables[] = [
                'name'   => $definition['name'],
                'title'  => $definition['title'],
                'status' => $exists ? 'ok' : 'missing',
            ];
        }

        $missing = array_filter($tables, function ($table) {
            return $table['status'] === 'missing';
        });

        return array_values(array_merge($missing, array_diff_key($tables, $missing)));
    }

    /**
     * @return array
     */
    protected function getDefinitions()
    {
        $definitions = [
            ['name' => TicketsMigrator::$tableName, 'title' => __('Tickets', 'fluent-support')],
            ['name' => ConversationsMigrator::$tableName, 'title' => __('Ticket Replies and Notes', 'fluent-support')],
            ['name' => PersonsMigrator::$tableName, 'title' => __('Customers and Agents', 'fluent-support')],
            ['name' => AttachmentsMigrator::$tableName, 'title' => __('Attachments', 'fluent-support')],
            ['name' => MailBoxMigrator::$tableName, 'title' => __('Business Inboxes', 'fluent-support')],
            ['name' => ProductsMigrator::$tableName, 'title' => __('Products', 'fluent-support')],
            ['name' => TaggablesMigrator::$tableName, 'title' => __('Tags', 'fluent-support')],
            ['name' => TagRelationsMigrator::$tableName, 'title' => __('Ticket Tag Assignments', 'fluent-support')],
            ['name' => MetaMigrator::$tableName, 'title' => __('Settings and Metadata', 'fluent-support')],
            ['name' => ActivityMigrator::$tableName, 'title' => __('Activity Logs', 'fluent-support')],
            ['name' => DataMetrixMigrator::$tableName, 'title' => __('Daily Report Metrics', 'fluent-support')],
            ['name' => AIActivityLogsMigrator::$tableName, 'title' => __('AI Activity Logs', 'fluent-support'), 'lazy' => true],
            ['name' => NotificationsMigrator::$tableName, 'title' => __('Internal Notifications', 'fluent-support'), 'lazy' => true],
            ['name' => NotificationUsersMigrator::$tableName, 'title' => __('Notification Recipients', 'fluent-support'), 'lazy' => true],
        ];

        /**
         * Filter the tables checked on the Database Health page.
         *
         * @since 2.4.5
         *
         * @param array $definitions Each item: name (without the WordPress prefix), title, optional lazy (bool).
         */
        $definitions = (array) apply_filters('fluent_support/system_tools_tables', $definitions);

        return array_filter($definitions, function ($definition) {
            return !empty($definition['name']) && !empty($definition['title']);
        });
    }
}
