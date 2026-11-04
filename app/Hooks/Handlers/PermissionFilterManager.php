<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

class PermissionFilterManager
{
    public function init()
    {
        add_action('fluent_support/tickets_query_by_permission_ref', array($this, 'filterAgentTickets'), 10, 2);
    }

    public function filterAgentTickets($ticketsQuery, $userId = false)
    {
        $agent = Helper::getAgentByUserId($userId ?: null);

        (new AgentTicketAccess())->applyVisibilityScope($ticketsQuery, $agent);
    }

    /**
     * @deprecated TicketQuery applies the mailbox restrictions itself.
     */
    public function filterAgentTicketsByMailboxes($ticketsQuery, $args = [])
    {
        (new AgentTicketAccess())->applyMailboxRestrictionScope($ticketsQuery);
    }
}
