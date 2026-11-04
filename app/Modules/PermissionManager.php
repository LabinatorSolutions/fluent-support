<?php

namespace FluentSupport\App\Modules;

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Support\Arr;

/**
 * Fluent Support's own agent permissions, stored in user meta.
 */

class PermissionManager
{
    const META_KEY = '_fluent_support_permissions';

    // Ticket visibility levels returned by resolveTicketVisibility()
    const VISIBILITY_ALL                    = 'all_tickets';
    const VISIBILITY_ASSIGNED_AND_UNASSIGNED = 'assigned_and_unassigned';
    const VISIBILITY_ASSIGNED_ONLY          = 'assigned_only';

    /**
     * @return string[]
     */
    public static function pluginPermissions()
    {
        return [
            'fst_view_dashboard',
            'fst_view_tickets',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_delete_tickets',
            'fst_assign_agents',
            'fst_manage_settings',
            'fst_sensitive_data',
            'fst_manage_workflows',
            'fst_run_workflows',
            'fst_view_all_reports',
            'fst_manage_saved_replies',
            'fst_view_activity_logs',
            'fst_merge_tickets',
            'fst_split_ticket',
            'fst_agent_today_performance',
            'fst_draft_reply',
            'fst_approve_draft_reply'
        ];
    }

    /**
     * Primary permission check. Accepts a single permission string or an array (any match).
     *
     * @param string|array $permissions
     * @return bool
     */
    public static function userCan($permissions)
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $userPermissions = self::currentUserPermissions();

        if (!$userPermissions) {
            return false;
        }

        if (is_string($permissions)) {
            return in_array($permissions, $userPermissions);
        }

        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                if (in_array($permission, $userPermissions)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Alias of userCan().
     *
     * @param $permission
     * @return bool
     */
    public static function currentUserCan($permission)
    {
        return self::userCan($permission);
    }

    /**
     * Save permissions to user meta and drop legacy fst_* capabilities.
     *
     * @param $user
     * @param $permissions
     * @return false|mixed
     */
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

        // Only known permissions. There is no "grant only what you hold" check
        // here because AgentPolicy limits agent edits to administrators.
        $permissions = array_values(array_intersect($allPermissions, $permissions));

        $exclusionRules = self::getExclusionRules();
        $permissions = self::applyExclusionRules($permissions, $exclusionRules);

        // Manage, draft and approve all imply fst_view_tickets
        $manageOrDraftPermissions = [
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_draft_reply',
            'fst_approve_draft_reply',
        ];

        if (!empty(array_intersect($permissions, $manageOrDraftPermissions))
            && !in_array('fst_view_tickets', $permissions)) {
            $permissions[] = 'fst_view_tickets';
        }

        update_user_meta($user->ID, self::META_KEY, array_values($permissions));

        foreach ($allPermissions as $cap) {
            $user->remove_cap($cap);
        }

        return $user;
    }

    /**
     * Remove all Fluent Support permissions from a user.
     *
     * @param int $userId
     * @return void
     */
    public static function detachPermissions($userId)
    {
        delete_user_meta($userId, self::META_KEY);

        // Legacy capabilities
        $user = get_user_by('ID', $userId);
        if ($user && !user_can($user, 'manage_options')) {
            foreach (self::pluginPermissions() as $cap) {
                $user->remove_cap($cap);
            }
        }
    }

    /**
     * Remove conflicting permissions based on exclusion rules.
     *
     * @param array $permissions The array of permissions to filter.
     * @param array $rules       Each key => value pair means: if key is present, remove value.
     * @return array The filtered array of permissions.
     */
    public static function applyExclusionRules($permissions, $rules)
    {
        foreach ($rules as $requiredKey => $removeKey) {
            if (in_array($requiredKey, $permissions) && in_array($removeKey, $permissions)) {
                unset($permissions[array_search($removeKey, $permissions)]);
            }
        }
        return $permissions;
    }

    /**
     * Get the mutual exclusion rules for permission assignment.
     *
     * @return array Each key => value pair means: if key is present, remove value.
     */
    public static function getExclusionRules()
    {
        // Draft-only mode is for agents who can't manage tickets, and a
        // draft-only agent must not approve their own drafts.
        return [
            'fst_manage_unassigned_tickets' => 'fst_draft_reply',
            'fst_manage_other_tickets'      => 'fst_draft_reply',
            'fst_manage_own_tickets'        => 'fst_draft_reply',
            'fst_draft_reply'               => 'fst_approve_draft_reply'
        ];
    }

    /**
     * Get raw permissions from user meta.
     *
     * @param int|null $userId
     * @return array
     */
    public static function getMetaPermissions($userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        $permissions = get_user_meta($userId, self::META_KEY, true);

        return is_array($permissions) ? $permissions : [];
    }

    /**
     * Reads user meta, migrating legacy fst_* capabilities on first read.
     *
     * @param int|\WP_User|false $user
     * @return array|string[]
     */
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
            $pluginPermission = array_values(array_diff($pluginPermission, ['fst_draft_reply']));
            return $pluginPermission;
        }

        $permissions = self::getMetaPermissions($user->ID);

        if (!empty($permissions)) {
            return array_values(array_intersect($permissions, $pluginPermission));
        }

        // Legacy: permissions stored as WP capabilities. Move them to meta.
        $legacyPermissions = array_values(array_intersect(array_keys($user->allcaps), $pluginPermission));

        if (!empty($legacyPermissions)) {
            update_user_meta($user->ID, self::META_KEY, $legacyPermissions);

            foreach ($legacyPermissions as $cap) {
                $user->remove_cap($cap);
            }
        }

        return $legacyPermissions;
    }

    /**
     * @param bool $cached
     * @return array|mixed|string[]
     */
    public static function currentUserPermissions($cached = true)
    {
        static $permissions;

        if ($permissions && $cached) {
            return $permissions;
        }

        $permissions = self::getUserPermissions(get_current_user_id());

        return $permissions;
    }

    /**
     * Capability for the admin menu: 'manage_options' for admins, the user's
     * role for agents, or '' to hide the menu.
     *
     * @return string
     */
    public static function getMenuPermission()
    {
        if (current_user_can('manage_options')) {
            return 'manage_options';
        }

        $userId = get_current_user_id();

        if (!$userId) {
            return '';
        }

        $metaPermissions = self::getMetaPermissions($userId);

        // Legacy agents may still have fst_* capabilities instead of meta
        if (empty($metaPermissions)) {
            $user = get_user_by('ID', $userId);
            if ($user) {
                $legacyPermissions = array_intersect(array_keys($user->allcaps), self::pluginPermissions());
                if (empty($legacyPermissions)) {
                    return '';
                }
            } else {
                return '';
            }
        }

        $user = wp_get_current_user();
        $roles = array_values((array) $user->roles);

        return Arr::get($roles, 0, '');
    }

    /**
     * Get the mailbox IDs that the current agent is restricted from accessing.
     *
     * @return array Mailbox IDs the agent cannot access, or empty array if unrestricted.
     */
    public static function getRestrictedMailboxIds()
    {
        return (new AgentTicketAccess())->getRestrictedMailboxIds();

    }

    /**
     * Whether the current user can reply, close, assign, etc. False for
     * draft-only agents.
     *
     * @return bool
     */
    public static function canManageTickets()
    {
        return self::userCan([
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets'
        ]);
    }

    /**
     * Whether the current user can reach ticket routes at all, read or write.
     *
     * @return bool
     */
    public static function canAccessTicketRoutes()
    {
        return self::userCan([
            'fst_view_tickets',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
            'fst_merge_tickets',
            'fst_draft_reply'
        ]);
    }

    /**
     * Manage permissions win. Otherwise view-only and draft agents see all
     * tickets, since they can't modify them.
     *
     * @param array $permissions
     * @return string One of the VISIBILITY_* constants.
     */
    private static function resolveTicketVisibility(array $permissions)
    {
        if (in_array('fst_manage_other_tickets', $permissions)) {
            return self::VISIBILITY_ALL;
        }

        if (in_array('fst_manage_unassigned_tickets', $permissions)) {
            return self::VISIBILITY_ASSIGNED_AND_UNASSIGNED;
        }

        if (in_array('fst_manage_own_tickets', $permissions)) {
            return self::VISIBILITY_ASSIGNED_ONLY;
        }

        if (in_array('fst_draft_reply', $permissions)
            || in_array('fst_view_tickets', $permissions)) {
            return self::VISIBILITY_ALL;
        }

        return self::VISIBILITY_ASSIGNED_ONLY;
    }

    /**
     * @return string
     */
    public static function currentTicketVisibility()
    {
        $permissions = self::currentUserPermissions();
        return self::resolveTicketVisibility($permissions);
    }

    /**
     * @param int|false $userId
     * @return string
     */
    public static function getAgentTicketVisibility($userId = false)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        $permissions = self::getUserPermissions($userId);

        return self::resolveTicketVisibility($permissions);
    }

    /**
     * Whether the current agent can access this ticket.
     *
     * @param $ticket
     * @return bool
     */
    public static function canAccessTicket($ticket)
    {
        return (new AgentTicketAccess())->currentAgentCanAccess($ticket);
    }

    /**
     * @return array[]
     */
    public static function getReadablePermissionGroups()
    {
        return [
            [
                'title'       => __('Tickets Permissions', 'fluent-support'),
                'permissions' => [
                    'fst_view_dashboard'            => __('View Dashboard', 'fluent-support'),
                    'fst_manage_own_tickets'        => __('Manage Own Tickets', 'fluent-support'),
                    'fst_manage_unassigned_tickets' => __('Manage Unassigned Tickets', 'fluent-support'),
                    'fst_manage_other_tickets'      => __('Manage Others Tickets', 'fluent-support'),
                    'fst_assign_agents'             => __('Assign Agents', 'fluent-support'),
                    'fst_delete_tickets'            => __('Delete Tickets & Individual Responses', 'fluent-support'),
                    'fst_merge_tickets'             => __('Merge Tickets', 'fluent-support'),
                    'fst_split_ticket'              => __('Split Ticket', 'fluent-support'),
                    'fst_draft_reply'               => __('Draft Reply', 'fluent-support'),
                    'fst_approve_draft_reply'       => __('Approve Draft Reply', 'fluent-support'),
                    'fst_view_tickets'              => __('View Tickets (Read Only)', 'fluent-support'),
                ]
            ],
            [
                'title'       => __('Workflow Permissions', 'fluent-support'),
                'permissions' => [
                    'fst_manage_workflows'     => __('Manage Workflows', 'fluent-support'),
                    'fst_run_workflows'        => __('Run workflows', 'fluent-support'),
                    'fst_manage_saved_replies' => __('Manage Saved Replies', 'fluent-support')
                ]
            ],
            [
                'title'       => __('Settings', 'fluent-support'),
                'permissions' => [
                    'fst_manage_settings' => __('Manage Overall Settings', 'fluent-support'),
                    'fst_sensitive_data'  => __('Access Private Data (Customers, Agents)', 'fluent-support')
                ]
            ],
            [
                'title'       => __('Reporting', 'fluent-support'),
                'permissions' => [
                    'fst_view_all_reports'   => __('View All Reports', 'fluent-support'),
                    'fst_view_activity_logs' => __('View Activity Logs', 'fluent-support'),
                    'fst_agent_today_performance' => __('View Agent Today Performance', 'fluent-support'),
                ]
            ]
        ];
    }

    public static function getMailboxesForRestriction()
    {
        return MailBox::select(['id', 'name'])->get();
    }

    // Deprecated aliases, kept for third-party add-ons.

    /**
     * @deprecated Use currentTicketVisibility() instead.
     */
    public static function currentUserTicketsPermissionLevel()
    {
        _deprecated_function(__METHOD__, '2.0.5', 'PermissionManager::currentTicketVisibility()');

        return self::mapVisibilityToLegacy(self::currentTicketVisibility());
    }

    /**
     * @deprecated Use getAgentTicketVisibility() instead.
     */
    public static function agentTicketPermissionLevel($userId = false)
    {
        _deprecated_function(__METHOD__, '2.0.5', 'PermissionManager::getAgentTicketVisibility()');

        return self::mapVisibilityToLegacy(self::getAgentTicketVisibility($userId));
    }

    /**
     * @deprecated Use canAccessTicket() instead.
     */
    public static function hasTicketPermission($ticket)
    {
        _deprecated_function(__METHOD__, '2.0.5', 'PermissionManager::canAccessTicket()');

        return self::canAccessTicket($ticket);
    }

    /**
     * Map new VISIBILITY_* constants back to legacy string values.
     *
     * @param string $visibility
     * @return string 'all', 'own_plus', or 'own'
     */
    private static function mapVisibilityToLegacy($visibility)
    {
        $map = [
            self::VISIBILITY_ALL                    => 'all',
            self::VISIBILITY_ASSIGNED_AND_UNASSIGNED => 'own_plus',
            self::VISIBILITY_ASSIGNED_ONLY          => 'own',
        ];

        return $map[$visibility] ?? 'own';
    }
}
