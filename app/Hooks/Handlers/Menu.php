<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\TicketTag;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TransStrings;

class Menu
{
    public function add()
    {
        $currentUserPermissions = PermissionManager::currentUserPermissions();

        if (!$currentUserPermissions) {
            return;
        }

        $permission = 'fst_view_dashboard';

        if (current_user_can('manage_options')) {
            $permission = 'manage_options';
        }

        add_menu_page(
            __('Fluent Support', 'fluent-support'),
            __('Fluent Support', 'fluent-support'),
            $permission,
            'fluent-support',
            array($this, 'renderApp'),
            $this->getMenuIcon(),
            4
        );
    }

    public function renderApp()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        $baseUrl = apply_filters('fluent_support/base_url', admin_url('admin.php?page=fluent-support#/'));

        $menuItems = [
            [
                'key'       => 'dashboard',
                'label'     => __('Dashboard', 'fluent-support'),
                'permalink' => $baseUrl
            ],
            [
                'key'       => 'tickets',
                'label'     => __('Tickets', 'fluent-support'),
                'permalink' => $baseUrl . 'tickets',
            ],
            [
                'key'       => 'reports',
                'label'     => __('Reports', 'fluent-support'),
                'permalink' => $baseUrl . 'reports'
            ]
        ];

        $hasSensitiveAccess = PermissionManager::currentUserCan('fst_sensitive_data');
        if ($hasSensitiveAccess) {
            $menuItems[] = [
                'key'       => 'customers',
                'label'     => __('Customers', 'fluent-support'),
                'permalink' => $baseUrl . 'customers'
            ];

            $secondayItems[] = [
                'key'       => 'activity_logger',
                'label'     => __('Activity Logger', 'fluent-support'),
                'permalink' => $baseUrl . 'activity-logger'
            ];
        }

        $secondayItems = [
            [
                'key'       => 'saved_replies',
                'label'     => __('Saved Replies', 'fluent-support'),
                'permalink' => $baseUrl . 'saved-replies'
            ]
        ];

        if (PermissionManager::currentUserCan('fst_manage_settings')) {
            $secondayItems[] = [
                'key'       => 'mailboxes',
                'label'     => __('Business Settings', 'fluent-support'),
                'permalink' => $baseUrl . 'mailboxes'
            ];

            $secondayItems[] = [
                'key'       => 'workflows',
                'label'     => __('Workflows', 'fluent-support'),
                'permalink' => $baseUrl . 'workflows'
            ];

            $secondayItems[] = [
                'key'       => 'settings',
                'label'     => __('Global Settings', 'fluent-support'),
                'permalink' => $baseUrl . 'settings'
            ];
        }

        $menuItems = apply_filters('fluent_support/primary_menu_items', $menuItems);
        $secondayItems = apply_filters('fluent_support/secondary_menu_items', $secondayItems);

        $app = App::getInstance();
        $this->enqueueAssets();
        $app->view->render('admin.menu', [
            'base_url'       => $baseUrl,
            'logo'           => $assets . 'images/logo.svg',
            'menuItems'      => $menuItems,
            'secondaryItems' => $secondayItems
        ]);
    }

    public function enqueueAssets()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        wp_enqueue_style(
            'fluent_support_admin_app', $assets . 'admin/css/alpha-admin.css', [], FLUENT_SUPPORT_VERSION
        );

        $agents = Agent::select(['id', 'first_name', 'last_name'])
            ->where('person_type', 'agent')
            ->get()->toArray();

        foreach ($agents as $index => $agent) {
            $agents[$index]['id'] = strval($agent['id']);
        }

        $me = Helper::getAgentByUserId(get_current_user_id());

        if (!$me && current_user_can('manage_options')) {
            // we should create the agent
            $user = wp_get_current_user();
            $me = Agent::create([
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'user_id'    => $user->ID
            ]);
        }

        $me->permissions = PermissionManager::currentUserPermissions();

        do_action('fluent_support_loading_app', $app);

        // Editor default styles.
        add_filter('user_can_richedit', '__return_true');
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();
        wp_enqueue_media();

        wp_enqueue_script(
            'fluent_support_admin_app_start',
            $assets . 'admin/js/start.js',
            array('jquery'),
            FLUENT_SUPPORT_VERSION,
            true
        );

        wp_enqueue_script(
            'fluent_support_global_admin',
            $assets . 'admin/js/global_admin.js',
            array('jquery'),
            FLUENT_SUPPORT_VERSION,
            true
        );

        $integrationDrivers = [];
        if(!defined('FLUENTSUPPORTPRO')) {
            $integrationDrivers = [
                [
                    'key' => 'telegram_settings',
                    'title' => __('Telegram', 'fluent-support'),
                    'description' => __('Send Telegram notifications to Group, Channel or individual person inbox and reply from Telegram inbox', 'fluent-support'),
                    'promo_heading' => __('Get activity notification to Telegram Messenger and reply directly from Telegram inbox', 'fluent-support'),
                    'require_pro' => true
                ],
                [
                    'key' => 'slack_settings',
                    'title' => __('Slack', 'fluent-support'),
                    'description' => __('Send ticket activity notifications to slack', 'fluent-support'),
                    'promo_heading' => __('Get activity notification to Slack Channel and keep your support team super engaged', 'fluent-support'),
                    'require_pro' => true
                ]
            ];
        }

        $integrationDrivers = apply_filters('fluent_support/integration_drivers', $integrationDrivers);

        $tags = TicketTag::select(['id', 'title'])->get()->toArray();

        $tags = array_map(function ($tag) {
            $tag['id'] = strval($tag['id']);
            return $tag;
        }, $tags);

        $appVars = apply_filters('fluent_support_app_vars', array(
            'slug'              => $slug = $app->config->get('app.slug'),
            'nonce'             => wp_create_nonce($slug),
            'rest'              => $this->getRestInfo($app),
            'brand_logo'        => $this->getMenuIcon(),
            'firstEntry'        => '',
            'lastEntry'         => '',
            'asset_url'         => $assets,
            'support_agents'    => $agents,
            'support_products'  => Product::select(['id', 'title'])->get(),
            'client_priorities' => Helper::customerTicketPriorities(),
            'admin_priorities'  => Helper::adminTicketPriorities(),
            'mailboxes'         => MailBox::select(['id', 'name', 'settings'])->get(),
            'me'                => $me,
            'pref'              => [
                'go_back_after_reply' => 'yes'
            ],
            'notification_integrations' => $integrationDrivers,
            'server_time'       => current_time('mysql'),
            'has_email_parser'  => defined('FLUENTSUPPORTPRO_PLUGIN_VERSION'),
            'ticket_tags'       => $tags,
            'i18n' => TransStrings::getTransStrings(),
            'custom_fields' => apply_filters('fluent_support/ticket_custom_fields', [])
        ));

        $appVars['has_pro'] = defined('FLUENTSUPPORTPRO_PLUGIN_VERSION');

        wp_localize_script('fluent_support_admin_app_start', 'fluentSupportAdmin', $appVars);

        do_action('fluent_support/admin_app_loaded', $app);

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
