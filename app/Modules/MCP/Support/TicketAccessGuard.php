<?php

namespace FluentSupport\App\Modules\MCP\Support;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\PermissionManager;

class TicketAccessGuard
{
    private static $restricted = null;

    private static function restrictedMailboxIds()
    {
        if (self::$restricted === null) {
            self::$restricted = PermissionManager::getRestrictedMailboxIds() ?: [];
        }
        return self::$restricted;
    }

    /**
     * Check the current user can access $ticket, including mailbox restrictions.
     * Returns WP_Error on failure, null on success:
     *
     *   if ($err = TicketAccessGuard::assert($ticket)) {
     *       return $err;
     *   }
     */
    public static function assert(Ticket $ticket)
    {
        if (!PermissionManager::canAccessTicket($ticket)) {
            return MCPHelper::error('forbidden', 'You do not have access to this ticket');
        }

        $restricted = self::restrictedMailboxIds();
        if ($restricted && in_array((int) $ticket->mailbox_id, array_map('intval', $restricted), true)) {
            return MCPHelper::error('forbidden', 'You do not have access to this ticket');
        }

        return null;
    }

    /**
     * Check the current user can create or move tickets in $mailboxId.
     * Returns WP_Error on failure, null on success.
     */
    public static function assertMailboxWritable($mailboxId)
    {
        $restricted = self::restrictedMailboxIds();
        if ($restricted && in_array($mailboxId, array_map('intval', $restricted), true)) {
            return MCPHelper::error('forbidden', 'You do not have access to the specified mailbox');
        }
        return null;
    }

    /**
     * Check $targetAgent isn't restricted from the ticket's mailbox.
     * Returns WP_Error on failure, null on success.
     *
     * @param int|null $mailboxId Destination mailbox when the ticket is being
     *                            moved; null checks the one it sits in now.
     */
    public static function assertAssignableAgent(Ticket $ticket, Agent $targetAgent, $mailboxId = null)
    {
        $meta            = $targetAgent->getMeta('agent_restrictions');
        $agentRestricted = (!empty($meta['businessBoxRestrictions']) && !empty($meta['restrictedBusinessBoxes']))
            ? $meta['restrictedBusinessBoxes']
            : [];

        $checkMailboxId = $mailboxId === null ? (int) $ticket->mailbox_id : (int) $mailboxId;

        if ($agentRestricted && in_array($checkMailboxId, array_map('intval', $agentRestricted), true)) {
            return MCPHelper::error('forbidden', "That agent is restricted from this ticket's mailbox");
        }

        return null;
    }
}
