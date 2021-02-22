<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Services\Helper;

class Menu
{
    public function add()
    {
        $capability = 'manage_options';

        add_menu_page(
            __('Fluent Support', 'fluent-support'),
            __('Fluent Support'),
            $capability,
            'fluent-support',
            array($this, 'renderApp'),
            $this->getMenuIcon(),
            6
        );
    }

    public function renderApp()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        $baseUrl = apply_filters('fluent_support_base_url', admin_url('admin.php?page=fluent-support#/'));

        $menuItems = [
            [
                'key'       => 'dashboard',
                'label'     => __('Dashboard', 'fluent-support'),
                'permalink' => $baseUrl
            ],
            [
                'key' => 'tickets',
                'label' => __('Tickets', 'fluent-support'),
                'permalink' => $baseUrl . 'tickets',
            ]
        ];

        $secondayItems = [
            [
                'key' => 'settings',
                'label' => __('Settings', 'fluent-support'),
                'permalink' => $baseUrl.'settings/products'
            ]
        ];

        $app = App::getInstance();
        $this->enqueueAssets();
        $app->view->render('admin.menu', [
            'base_url' => $baseUrl,
            'logo' => $assets.'images/logo.svg',
            'menuItems' => $menuItems,
            'secondayItems' => $secondayItems
        ]);
    }

    public function enqueueAssets()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        wp_enqueue_script(
            'fluent_support_admin_app_boot',
            $assets . 'admin/js/boot.js',
            array('jquery')
        );

        wp_enqueue_style(
            'fluent_support_admin_app', $assets . 'admin/css/alpha-admin.css'
        );

        $agents = Agent::select(['id', 'first_name', 'last_name'])
            ->where('person_type', 'agent')
            ->get();


        wp_localize_script('fluent_support_admin_app_boot', 'fluentSupportAdmin', array(
            'slug'  => $slug = $app->config->get('app.slug'),
            'nonce' => wp_create_nonce($slug),
            'rest'  => $this->getRestInfo($app),
            'brand_logo' => $this->getMenuIcon(),
            'firstEntry' => '',
            'lastEntry' => '',
            'asset_url' => $assets,
            'support_agents' => $agents,
            'support_products' => Product::select(['id', 'title'])->get(),
            'client_priorities' => Helper::customerTicketPriorities(),
            'admin_priorities' => Helper::adminTicketPriorities(),
            'me' => Helper::getAgentByUserId(get_current_user_id()),
            'pref' => [
                'go_back_after_reply' => 'yes'
            ]
        ));

        do_action('fluent_support_loading_app');

        // Editor default styles.
        add_filter('user_can_richedit', '__return_true');
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();
        wp_enqueue_media();

        wp_enqueue_script(
            'fluent_support_admin_app_start',
            $assets . 'admin/js/start.js',
            array('fluent_support_admin_app_boot'),
            '1.0',
            true
        );
    }

    protected function getRestInfo($app)
    {
        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');

        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];
    }

    protected function getMenuIcon()
    {
        $app = App::getInstance();

        $assets = $app['path.assets'];

        return 'data:image/svg+xml;base64,' . base64_encode(
            file_get_contents($assets . '/images/logo.svg')
        );
    }
}
