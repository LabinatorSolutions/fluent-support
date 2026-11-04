<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TicketHelper;

class TicketStats
{
    public function getQuickLinks()
    {
        $urlBase = apply_filters(
            'fst_menu_url_base',
            admin_url('admin.php?page=fluent-support#/')
        );

        $counts = TicketHelper::getStatusCounts();

        return apply_filters('fst_quick_links', [
            [
                'title' => __('Total Tickets', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets' ),
                'number'=> $counts['total'],
                'icon'  => 'el-icon-user'
            ],
            [
                'title' => __('Active Tickets', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets?filter_type=simple&status_type=active' ),
                'number'=> $counts['active'],
                'icon'  => 'el-icon-message'
            ],
            [
                'title' => __('New Tickets', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets?filter_type=simple&status_type=new' ),
                'number'=> $counts['new'],
                'icon'  => 'el-icon-folder'
            ],
            [
                'title' => __('Un-Assigned Tickets', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets?agent_id=unassigned' ),
                'number'=> $counts['unassigned'],
                'icon'  => 'el-icon-folder'
            ],
            [
                'title' => __('Closed Tickets', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets?filter_type=simple&status_type=closed' ),
                'number'=> $counts['closed'],
                'icon'  => 'el-icon-message'
            ],
            [
                'title' => __('Awaiting Replies', 'fluent-support'),
                'url'   => esc_url_raw( $urlBase . 'tickets?status_type=open&waiting_for_reply=yes' ),
                'number'=> $this->countAwaitingReply(),
                'icon'  => 'el-icon-message'
            ]
        ]);
    }

    /**
     * Open tickets waiting on an agent, among the ones the current user can see in the list.
     *
     * @return int
     */
    private function countAwaitingReply()
    {
        return (new AgentTicketAccess())->applyListScope(
            Ticket::waitingOnly()->where('status', '!=', 'closed')
        )->count();
    }

    /**
     * Counts for the ticket list sidebar views, limited to the tickets the
     * agent may see (visibility and mailbox restrictions).
     *
     * @param \FluentSupport\App\Models\Agent $agent The current user's agent; the visibility scope reads the current user.
     * @return array{statuses: array, open: array, priorities: array}
     */
    public function getSidebarCounts($agent)
    {
        $byStatus = $this->scopeToAgent(
            Ticket::query()->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status'),
            $agent
        )->get()->pluck('total', 'status')->toArray();

        $statuses = [];
        foreach (Helper::ticketStatusGroups() as $groupName => $groupStatuses) {
            // An empty group ("all") means every status.
            $statuses[$groupName] = (int) array_sum($groupStatuses ? array_intersect_key($byStatus, array_flip($groupStatuses)) : $byStatus);
        }

        $openStatuses = Helper::getTkStatusesByGroupName('open') ?: ['new', 'active'];

        // Same conditions as Ticket::scopeWaitingOnly().
        $open = $this->scopeToAgent(
            Ticket::query()
                ->selectRaw('SUM(CASE WHEN agent_id = ? THEN 1 ELSE 0 END) AS mine', [(int) $agent->id])
                ->selectRaw('SUM(CASE WHEN agent_id IS NULL THEN 1 ELSE 0 END) AS unassigned')
                ->selectRaw("SUM(CASE WHEN last_agent_response < last_customer_response OR last_agent_response IS NULL OR status = 'new' THEN 1 ELSE 0 END) AS waiting")
                ->whereIn('status', $openStatuses),
            $agent
        )->first();

        $byPriority = $this->scopeToAgent(
            Ticket::query()->select('priority')->selectRaw('COUNT(*) AS total')->whereIn('status', $openStatuses)->groupBy('priority'),
            $agent
        )->get()->pluck('total', 'priority')->toArray();

        $priorities = [];
        foreach (array_keys(Helper::adminTicketPriorities()) as $priority) {
            $priorities[$priority] = (int) ($byPriority[$priority] ?? 0);
        }

        return [
            'statuses'   => $statuses,
            'open'       => [
                'mine'       => (int) ($open ? $open->mine : 0),
                'unassigned' => (int) ($open ? $open->unassigned : 0),
                'waiting'    => (int) ($open ? $open->waiting : 0),
            ],
            'priorities' => $priorities,
        ];
    }

    private function scopeToAgent($query, $agent)
    {
        return (new AgentTicketAccess())->applyListScope($query, $agent);
    }
}
