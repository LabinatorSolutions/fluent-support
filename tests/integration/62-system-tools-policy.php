<?php
/**
 * System Admin Tools routes are readable by settings managers only.
 *
 * Users created here are not factory-tracked and are removed in this file's finally.
 */

use FluentSupport\App\App;
use FluentSupport\App\Modules\SystemTools\SystemToolsPolicy;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $adminId = get_current_user_id();
    $policy = new SystemToolsPolicy();
    $request = App::make('request');

    $makeUser = function (array $permissions) {
        $login = FsTest::uniq('fs-system-tools');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }

        if ($permissions) {
            PermissionManager::attachPermissions($userId, $permissions);
        }

        return $userId;
    };

    $loginAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $subscriberId = $makeUser([]);
    $managerId = $makeUser(['fst_manage_settings']);

    try {
        FsTest::case('system tools policy: anonymous is denied', function () use ($loginAs, $policy, $request) {
            $loginAs(0);
            FsTest::assertSame(false, (bool) $policy->verifyRequest($request), 'anonymous must not read system tools');
        });

        FsTest::case('system tools policy: subscriber without settings permission is denied', function () use ($loginAs, $policy, $request, $subscriberId) {
            $loginAs($subscriberId);
            FsTest::assertSame(false, (bool) $policy->verifyRequest($request), 'a plain subscriber must not read system tools');
        });

        FsTest::case('system tools policy: settings manager is allowed', function () use ($loginAs, $policy, $request, $managerId) {
            $loginAs($managerId);
            FsTest::assertSame(true, (bool) $policy->verifyRequest($request), 'fst_manage_settings grants read access');
        });

        FsTest::case('system tools policy: administrator is allowed', function () use ($loginAs, $policy, $request, $adminId) {
            $loginAs($adminId);
            FsTest::assertSame(true, (bool) $policy->verifyRequest($request), 'the site administrator can read system tools');
        });
    } finally {
        $loginAs($adminId);

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ([$subscriberId, $managerId] as $userId) {
            if (get_user_by('ID', $userId)) {
                wp_delete_user($userId);
            }
        }
    }
};
