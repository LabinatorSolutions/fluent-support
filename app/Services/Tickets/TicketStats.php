<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Services\TicketHelper;

class TicketStats
{
    public function getQuickLinks()
    {
        $urlBase = apply_filters(
            'fst_menu_url_base',
            admin_url('admin.php?page=fluent-support#/')
        );

        return apply_filters('fst_quick_links', [
            [
                'title' => __('Total Tickets', 'fluent-support'),
                'url'   => $urlBase . 'tickets',
                'number'=> TicketHelper::countAllTickets(),
                'icon'  => 'el-icon-user'
            ],
            [
                'title' => __('Un-Assigned', 'fluent-support'),
                'url'   => $urlBase . 'tickets?agent_id=unassigned',
                'number'=> TicketHelper::countUnassignedTickets(),
                'icon'  => 'el-icon-folder'
            ],
            [
                'title' => __('Closed', 'fluent-support'),
                'url'   => $urlBase . 'tickets?filter_type=simple&filters%5Bstatus_type%5D=closed',
                'number'=> TicketHelper::countClosedTickets(),
                'icon'  => 'el-icon-message'
            ]
        ]);
    }
}
