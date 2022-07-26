<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\TicketStats;
use FluentSupport\App\Services\Helper;

class AdminBarHandler
{
    public function init()
    {
        $currentUserPermissions = PermissionManager::currentUserPermissions();

        if (!$currentUserPermissions) {
            return;
        }

        add_action('admin_bar_menu', [$this, 'showTicketSummary'], 999);

    }

    public function showTicketSummary($adminBar)
    {
        $isHidden = Helper::showTicketSummaryAdminBar();
        if(!$isHidden)
            return;

        $app = App::getInstance();
        $assets = $app['url.assets'];
        wp_enqueue_script('fst_global_summary', $assets . 'admin/js/global_summary.js', ['jquery'], FLUENT_SUPPORT_VERSION);

        wp_localize_script('fst_global_summary', 'fst_bar_vars', [
            'rest'            => $this->getRestInfo(),
            'links'           => (new TicketStats())->getQuickLinks(),
            'trans' => [
                'Quick Summary' => __('Quick Summary', 'fluent-support')
            ]
        ]);

        $args = [
            'parent' => 'top-secondary',
            'id'     => 'fst_global_summary',
            'title'  => __('Ticket Summary', 'fluent-support'),
            'href'   => '#',
            'meta'   => false
        ];

        $adminBar->add_node($args);
    }

    protected function getRestInfo()
    {
        $app = App::getInstance();

        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');

        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v
        ];
    }
}
