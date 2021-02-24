<?php

namespace FluentSupport\App\Modules;

use FluentSupport\Framework\Support\Arr;

class PermissionManager
{
    public static function pluginPermissions()
    {
        return [
            'fst_view_dashboard',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_manage_settings'
        ];
    }

    public static function getRoles()
    {
        return [
            'support_agent' => [
                'title' => 'Support Agent',
                'permissions' => [
                    'fst_view_dashboard',
                    'fst_manage_own_tickets',
                    'fst_manage_unassigned_tickets'
                ]
            ],
            'support_manager' => [
                'title' => 'Support Manager',
                'permissions' => [
                    'fst_view_dashboard',
                    'fst_manage_own_tickets',
                    'fst_manage_unassigned_tickets',
                    'fst_manage_other_tickets',
                    'fst_manage_settings'
                ]
            ]
        ];
    }

    public static function getRole($roleName)
    {
        $roles = self::getRoles();
        if(isset($roles[$roleName])) {
            return $roles[$roleName];
        }
        return false;
    }

    public static function attachPermissions($user, $permissions)
    {
        if(is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }


        if(!$user) {
            return false;
        }

        $allPermissions = self::pluginPermissions();
        foreach ($allPermissions as $permission ) {
            $user->remove_cap($permission);
        }

        $permissions = array_intersect($allPermissions, $permissions);

        foreach ($permissions as $permission) {
            $user->add_cap($permission);
        }

        return $user;
    }

    public static function getUserPermissions($user = false)
    {
        if(is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        if(!$user) {
            return [];
        }

        $pluginPermission = self::pluginPermissions();

        if ($user->has_cap('manage_options')) {
            $pluginPermission[] = 'administrator';
            return $pluginPermission;
        }

        return array_values(array_intersect(array_keys($user->allcaps), $pluginPermission));
    }
}
