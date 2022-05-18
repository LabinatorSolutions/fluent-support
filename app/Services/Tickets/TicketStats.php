<?php

namespace FluentSupport\App\Services\Tickets;

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
                'number'=> 50,
                'icon'  => 'el-icon-user'
            ],
            [
                'title' => __('Un-Assigned', 'fluent-support'),
                'url'   => $urlBase . 'tickets?agent_id=unassigned',
                'number'=> 20,
                'icon'  => 'el-icon-folder'
            ],
            [
                'title' => __('Closed', 'fluent-support'),
                'url'   => $urlBase . 'tickets?agent_id=unassigned',
                'number'=> 15,
                'icon'  => 'el-icon-message'
            ]
        ]);
    }
}
