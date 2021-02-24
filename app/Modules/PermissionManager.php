<?php

namespace FluentSupport\App\Modules;


class PermissionManager
{
    public static function pluginPermissions()
    {
        return [
            'fst_view_dashboard',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_manage_settings',
            'fst_sensitive_data'
        ];
    }

    public static function attachPermissions($user, $permissions)
    {
        if(is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        if(!$user) {
            return false;
        }

        if(user_can($user, 'manage_options')) {
            return $user;
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

    public static function currentUserPermissions($cached = true)
    {
        static $permissions;

        if($permissions && $cached) {
            return $permissions;
        }

        $permissions = self::getUserPermissions(get_current_user_id());

        return $permissions;
    }

    public static function currentUserCan($permission)
    {
        if(current_user_can('manage_options')) {
            return true;
        }
        
        return current_user_can($permission);
    }
}
