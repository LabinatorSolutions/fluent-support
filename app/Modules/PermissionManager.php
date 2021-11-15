<?php

namespace FluentSupport\App\Modules;

use FluentSupport\App\Services\Helper;

class PermissionManager
{
    public static function pluginPermissions()
    {
        return [
            'fst_view_dashboard',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_delete_tickets',
            'fst_manage_settings',
            'fst_sensitive_data',
            'fst_manage_workflows',
            'fst_run_workflows',
            'fst_view_all_reports',
            'fst_manage_saved_replies',
            'fst_view_activity_logs'
        ];
    }

    public static function attachPermissions($user, $permissions)
    {
        if (is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        if (!$user) {
            return false;
        }

        if (user_can($user, 'manage_options')) {
            return $user;
        }

        $allPermissions = self::pluginPermissions();
        foreach ($allPermissions as $permission) {
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
        if (is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        if (!$user) {
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

        if ($permissions && $cached) {
            return $permissions;
        }

        $permissions = self::getUserPermissions(get_current_user_id());

        return $permissions;
    }

    public static function currentUserCan($permission)
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return current_user_can($permission);
    }

    public static function currentUserTicketsPermissionLevel()
    {
        $permissions = self::currentUserPermissions();

        if (in_array('fst_manage_other_tickets', $permissions)) {
            return 'all';
        }

        if (in_array('fst_manage_unassigned_tickets', $permissions)) {
            return 'own_plus';
        }

        return 'own';
    }

    public static function agentTicketPermissionLevel($userId = false)
    {
        if(!$userId) {
            $userId = get_current_user_id();
        }

        $permissions = self::getUserPermissions($userId);

        if (in_array('fst_manage_other_tickets', $permissions)) {
            return 'all';
        }

        if (in_array('fst_manage_unassigned_tickets', $permissions)) {
            return 'own_plus';
        }

        return 'own';
    }

    public static function hasTicketPermission($ticket)
    {
        $permissionLevel = self::currentUserTicketsPermissionLevel();
        if ($permissionLevel == 'all') {
            return true;
        }
        $agent = Helper::getAgentByUserId();
        if ($ticket->agent_id == $agent->id) {
            return true;
        }

        return !$ticket->agent_id && $permissionLevel == 'own_plus';
    }

    public static function getReadablePermissionGroups()
    {
        return [
            [
                'title'       => 'Tickets Permissions',
                'permissions' => [
                    'fst_view_dashboard'            => 'View Dashboard',
                    'fst_manage_own_tickets'        => 'Manage Own Tickets',
                    'fst_manage_unassigned_tickets' => 'Manage Unassigned Tickets',
                    'fst_manage_other_tickets'      => 'Manage Others Tickets',
                    'fst_delete_tickets'            => 'Delete Tickets',
                ]
            ],
            [
                'title'       => 'Workflow Permissions',
                'permissions' => [
                    'fst_manage_workflows'     => 'Manage Workflows',
                    'fst_run_workflows'        => 'Run workflows',
                    'fst_manage_saved_replies' => 'Manage Saved Replies'
                ]
            ],
            [
                'title'       => 'Settings',
                'permissions' => [
                    'fst_manage_settings' => 'Manage Overall Settings',
                    'fst_sensitive_data'  => 'Access Private Data (Customers, Agents)'
                ]
            ],
            [
                'title'       => 'Reporting',
                'permissions' => [
                    'fst_view_all_reports'   => 'View All Reports',
                    'fst_view_activity_logs' => 'View Activity Logs'
                ]
            ]
        ];
    }
}
