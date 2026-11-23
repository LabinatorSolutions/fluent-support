<?php
/**
 * Product titles are saved as plain text. A settings-only agent (not a site
 * administrator) must not be able to store markup that the admin app shows.
 *
 * Users and products created here are removed in this file's finally.
 */

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Modules\PermissionManager;

return function () {
    global $wpdb;

    $adminId = get_current_user_id();
    $markup = '<img src=x onerror="window.fsxss=1">';
    $userIds = [];
    $agentIds = [];
    $productIds = [];

    $makeAgent = function (array $permissions) use (&$userIds, &$agentIds) {
        $login = FsTest::uniq('fs-product-title');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }
        $userIds[] = $userId;
        $agentIds[] = Agent::create(['email' => $login . '@example.test', 'first_name' => 'Title', 'last_name' => 'Test', 'user_id' => $userId])->id;
        PermissionManager::attachPermissions($userId, $permissions);

        return $userId;
    };

    $loginAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $storedTitle = function ($id) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}fs_products WHERE id = %d", $id));
    };

    $settingsAgent = $makeAgent(['fst_view_tickets', 'fst_manage_settings']);
    $viewAgent = $makeAgent(['fst_view_tickets']);

    try {
        FsTest::case('product title: settings agent is not a site administrator', function () use ($settingsAgent) {
            FsTest::assertSame(false, user_can($settingsAgent, 'manage_options'), 'the attacker in this scenario has no manage_options');
        });

        FsTest::case('product title: markup is stripped on create', function () use ($loginAs, $settingsAgent, $markup, $storedTitle, &$productIds) {
            $loginAs($settingsAgent);
            $title = FsTest::uniq('product');
            $result = FsTest::rest('POST', '/products', ['title' => $title . $markup]);
            FsTest::assertHealthy($result, 'POST /products');
            $productIds[] = $id = (int) $result['data']['product']->id;

            FsTest::assertSame($title, $storedTitle($id), 'stored title');
        });

        FsTest::case('product title: markup is stripped on update', function () use ($loginAs, $settingsAgent, $markup, $storedTitle, &$productIds) {
            $loginAs($settingsAgent);
            $id = $productIds[] = Product::create(['title' => FsTest::uniq('product'), 'created_by' => $settingsAgent])->id;
            $title = FsTest::uniq('renamed');

            $result = FsTest::rest('PUT', '/products/' . $id, ['title' => $title . $markup]);
            FsTest::assertHealthy($result, 'PUT /products/{id}');

            FsTest::assertSame($title, $storedTitle($id), 'stored title');
        });

        FsTest::case('product title: plain punctuation survives', function () use ($loginAs, $settingsAgent, $storedTitle, &$productIds) {
            $loginAs($settingsAgent);
            $title = 'Tom & Jerry\'s "Pro" plan ' . FsTest::uniq('p');
            $result = FsTest::rest('POST', '/products', ['title' => $title]);
            FsTest::assertHealthy($result, 'POST /products');
            $productIds[] = $id = (int) $result['data']['product']->id;

            FsTest::assertSame($title, $storedTitle($id), 'stored title');
        });

        FsTest::case('product title: an agent without fst_manage_settings cannot create products', function () use ($loginAs, $viewAgent) {
            $loginAs($viewAgent);
            $result = FsTest::rest('POST', '/products', ['title' => FsTest::uniq('product')]);
            FsTest::assertSame(403, $result['status'], 'POST /products as a view-only agent');
        });
    } finally {
        $loginAs($adminId);
        if ($productIds) {
            Product::whereIn('id', $productIds)->delete();
        }
        if ($agentIds) {
            Agent::whereIn('id', $agentIds)->delete();
        }
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
