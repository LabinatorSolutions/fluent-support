<?php

namespace FluentSupport\App\Modules\MCP\Support;

use FluentSupport\App\Modules\PermissionManager;

class AbilityGuard
{
    const MANAGE_TICKET_CAPS = [
        'fst_manage_own_tickets',
        'fst_manage_unassigned_tickets',
        'fst_manage_other_tickets',
    ];

    /**
     * Per-ability caps. any_of needs one of the caps, all_of needs every cap.
     * When both are set, both must pass.
     */
    private static function capabilities()
    {
        return [
            'fluent-support/list-tickets'         => ['any_of' => ['fst_view_tickets']],
            'fluent-support/get-ticket'           => ['any_of' => ['fst_view_tickets']],
            'fluent-support/create-ticket'        => ['any_of' => self::MANAGE_TICKET_CAPS],
            // Draft-only agents may call it; ResponseTools forces draft mode for them.
            'fluent-support/reply-to-ticket'      => ['any_of' => array_merge(self::MANAGE_TICKET_CAPS, ['fst_draft_reply'])],
            'fluent-support/close-ticket'         => ['any_of' => self::MANAGE_TICKET_CAPS],
            'fluent-support/update-ticket'        => ['any_of' => self::MANAGE_TICKET_CAPS],
            'fluent-support/delete-ticket'        => ['any_of' => self::MANAGE_TICKET_CAPS, 'all_of' => ['fst_delete_tickets']],
            'fluent-support/get-ticket-activity'  => ['any_of' => ['fst_view_tickets']],
            'fluent-support/merge-tickets'        => ['any_of' => ['fst_merge_tickets']],
            'fluent-support/add-internal-note'    => ['any_of' => self::MANAGE_TICKET_CAPS],
            // Own drafts and notes only (row gate in ResponseTools).
            'fluent-support/update-response'      => ['any_of' => array_merge(self::MANAGE_TICKET_CAPS, ['fst_draft_reply'])],
            'fluent-support/delete-response'      => ['any_of' => array_merge(self::MANAGE_TICKET_CAPS, ['fst_draft_reply'])],
            // Approvers publish any draft; managers their own (checked in the tool).
            'fluent-support/publish-draft-reply'  => ['any_of' => array_merge(self::MANAGE_TICKET_CAPS, ['fst_approve_draft_reply'])],
            'fluent-support/tag-ticket'           => ['any_of' => self::MANAGE_TICKET_CAPS],
            'fluent-support/get-customer-tickets' => ['any_of' => ['fst_sensitive_data']],
            'fluent-support/get-support-context'  => ['any_of' => ['fst_view_tickets']],
            'fluent-support/get-ticket-views'     => ['any_of' => ['fst_view_tickets']],
            'fluent-support/list-saved-replies'   => ['any_of' => ['fst_manage_saved_replies']],
            'fluent-support/get-support-insights' => ['any_of' => ['fst_view_all_reports']],
            'fluent-support/search-customers'     => ['any_of' => ['fst_sensitive_data']],
            'fluent-support/get-mentions'         => ['any_of' => ['fst_view_tickets']],
            'fluent-support/update-customer'      => ['any_of' => ['fst_sensitive_data']],
            // Same as the admin route: a ticket manager who also holds Split Ticket.
            'fluent-support/split-ticket'         => ['any_of' => self::MANAGE_TICKET_CAPS, 'all_of' => ['fst_split_ticket']],
            // Prompts: they only return instructions; the tools they name check their own caps.
            'fluent-support/triage-queue'         => ['any_of' => ['fst_view_tickets']],
            'fluent-support/draft-reply'          => ['any_of' => ['fst_view_tickets']],
            'fluent-support/summarize-ticket'     => ['any_of' => ['fst_view_tickets']],
            'fluent-support/support-report'       => ['any_of' => ['fst_view_all_reports']],
            'fluent-support/bulk-action'          => ['any_of' => self::MANAGE_TICKET_CAPS],
        ];
    }

    /**
     * permission_callback for an ability. Only checks login and fails closed for
     * unknown abilities. Caps are checked in AbilitiesRegistrar::wrapExecuteCallback()
     * so denials come back as structured errors, not the adapter's "Permission denied".
     */
    public static function callbackFor($abilityName)
    {
        return function () use ($abilityName) {
            if (!array_key_exists($abilityName, self::capabilities())) {
                return false;
            }
            return is_user_logged_in();
        };
    }

    public static function check($abilityName)
    {
        $caps = self::capabilities()[$abilityName] ?? null;

        if ($caps === null) {
            return false;
        }

        return self::checkCaps($caps);
    }

    /**
     * Whether the current user has these caps: any_of needs one, all_of needs every
     * one. Pro's tools pass their own caps here so both plugins check the same way.
     *
     * @param array{any_of?: string[], all_of?: string[]} $caps
     * @return bool
     */
    public static function checkCaps(array $caps)
    {
        if (empty($caps['any_of']) && empty($caps['all_of'])) {
            return false;
        }

        if (!empty($caps['any_of']) && !PermissionManager::userCan($caps['any_of'])) {
            return false;
        }

        if (!empty($caps['all_of'])) {
            foreach ($caps['all_of'] as $cap) {
                if (!PermissionManager::currentUserCan($cap)) {
                    return false;
                }
            }
        }

        return true;
    }
}
