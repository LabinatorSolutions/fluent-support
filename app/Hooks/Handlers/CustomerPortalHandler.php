<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\TicketType;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TransStrings;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalHandler
{
    public function renderPortal()
    {
        if (PermissionManager::currentUserPermissions()) {
            $adminPortalUrl = Helper::getPortalAdminBaseUrl();
            return '<div style="text-align: center;"><h3>Customer Portal is only accessible by Customers. Looks like you are a support staff</h3><a href="' . $adminPortalUrl . '">Go to Support Admin Page</a></div>';
        } else if ($this->hasCustomerPortalAccess()) {
            $this->enqueueScripts();
            return '<div id="fluent_support_client_app"><h3 class="fs_loading_text">Loading Customer Portal. Please wait...</h3></div>';
        } else {
            $businessSettings = Helper::getBusinessSettings();
            return do_shortcode(Arr::get($businessSettings, 'login_message', ''));
        }
    }

    public function enqueueScripts()
    {
        $app = App::getInstance();

        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');
        $slug = $app->config->get('app.slug');

        $restInfo = [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v . '/customer-portal'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];

        $assets = $app['url.assets'];

        wp_enqueue_script('fs_tk_customer_portal', $assets . 'portal/js/app.js', ['jquery']);
        wp_enqueue_style('fs_tk_customer_portal', $assets . 'portal/css/app.css');

        $data = [
            'rest'                       => $restInfo,
            'nonce'                      => wp_create_nonce($slug),
            'support_products'           => Product::select(['id', 'title'])->get(),
            'customer_ticket_priorities' => Helper::customerTicketPriorities(),
            'ticket_types'               => TicketType::select(['id', 'title'])->get(),
            'view_tickets_url' => Helper::getPortalBaseUrl().'/#',
            'i18n' => TransStrings::getTransStrings()
        ];

        if($this->isSignedTicketView()) {
            $data['intended_ticket_hash'] = sanitize_text_field($_REQUEST['support_hash']);
        } else {
            add_filter('user_can_richedit', '__return_true');
        }

        wp_tinymce_inline_scripts();
        wp_enqueue_editor();

        wp_localize_script('fs_tk_customer_portal', 'fs_customer_portal', $data);
    }

    public function hasCustomerPortalAccess()
    {
        $userId = get_current_user_id();

        if ($userId) {
            return true;
        }

        return $this->isSignedTicketView();
    }

    protected function isSignedTicketView()
    {
        if(!Helper::isPublicSignedTicketEnabled()) {
            return false;
        }

        return isset($_REQUEST['fs_view']) && $_REQUEST['fs_view'] == 'ticket' && isset($_REQUEST['support_hash']) && isset($_REQUEST['ticket_id']);
    }
}
