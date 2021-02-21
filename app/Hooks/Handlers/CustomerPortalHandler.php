<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Services\Helper;

class CustomerPortalHandler
{
    public function renderPortal()
    {
        $this->enqueueScripts();

        return '<div id="fluent_support_client_app"><h3 class="fs_loading_text">Loading Customer Portal. Please wait...</h3></div>';
    }

    public function enqueueScripts()
    {
        $app = App::getInstance();

        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');
        $slug = $app->config->get('app.slug');

        $restInfo = [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v.'/customer-portal'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];

        $assets = $app['url.assets'];

        wp_enqueue_script('fs_tk_customer_portal', $assets.'portal/js/app.js', ['jquery']);
        wp_enqueue_style('fs_tk_customer_portal', $assets.'portal/css/app.css');

        add_filter('user_can_richedit', '__return_true');
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();

        wp_localize_script('fs_tk_customer_portal', 'fs_customer_portal', [
            'rest' => $restInfo,
            'nonce' => wp_create_nonce($slug),
            'support_products' => Product::select(['id', 'title'])->get(),
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ]);
    }
}
