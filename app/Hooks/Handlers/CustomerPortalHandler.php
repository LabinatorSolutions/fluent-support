<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TransStrings;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalHandler
{
    public function renderPortal()
    {
        if (static::customerStatus() && static::customerStatus()->status === 'inactive') {
            return '<div id="fluent_support_client_app" style="text-align: center;"><h3 class="fs_customer_restriction">' . __('You don’t have permission to view the tickets', 'fluent-support') . '</h3></div>';
        } else if (PermissionManager::currentUserPermissions()) {
            $adminPortalUrl = Helper::getPortalAdminBaseUrl();
            return '<div style="text-align: center;"><h3>' . __('Customer Portal is only accessible by Customers. Looks like you are a support staff', 'fluent-support') . '</h3><a href="' . $adminPortalUrl . '">' . __('Go to Support Admin Page', 'fluent-support') . '</a></div>';
        } else if ($this->hasCustomerPortalAccess()) {
            $this->enqueueScripts();
            return '<div id="fluent_support_client_app"><h3 class="fs_loading_text">' . __('Loading Customer Portal. Please wait...', 'fluent-support') . '</h3></div>';
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
            'view_tickets_url'           => '#/',
            'i18n'                       => TransStrings::getTransStrings(),
            'fallback_image'             => $assets . 'images/file.png',
        ];

        if ($this->isSignedTicketView()) {
            $data['intended_ticket_hash'] = sanitize_text_field($_REQUEST['support_hash']);
            $data['view_tickets_url'] = Helper::getPortalBaseUrl() . '/#';
        } else {
            add_filter('user_can_richedit', '__return_true');
        }

        wp_tinymce_inline_scripts();
        wp_enqueue_editor();

        $data = apply_filters('fluent_support/customer_portal_vars', $data);

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

    protected static function customerStatus()
    {
        $user = get_current_user_id();
        return Customer::where('user_id', $user)->select(['status'])->first();
    }

    protected function isSignedTicketView()
    {
        if (!Helper::isPublicSignedTicketEnabled()) {
            return false;
        }

        return isset($_REQUEST['fs_view']) && $_REQUEST['fs_view'] == 'ticket' && isset($_REQUEST['support_hash']) && isset($_REQUEST['ticket_id']);
    }
}
