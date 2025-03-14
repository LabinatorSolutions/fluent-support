<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Services\Blocks\BlockAttributes;

class BlockEditorHandler
{
    public function init()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];
        wp_register_script(
            'fluent-support/customer-portal',
            $assets . 'block-editor/js/fst_block.js',
            array('jquery', 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n', 'wp-api-fetch'),
        );

        wp_localize_script('fluent-support/customer-portal', 'fluent_support_vars', [
            'rest'            => $this->getRestInfo(),
        ]);

        register_block_type( 'fluent-support/customer-portal' , array(
            'editor_script' => 'fluent-support/customer-portal',
            'render_callback' => array($this, 'fst_render_block'),
            'attributes' => BlockAttributes::CustomerPortalAttributes(),
        ));



        wp_enqueue_script(
            'fluent-support/all-tickets',
            $assets . 'block-editor/js/AllTicketsIndex.js',
            array('wp-blocks', 'wp-components', 'wp-block-editor', 'wp-element'),
            FLUENT_SUPPORT_VERSION,
            true
        );

        wp_localize_script('fluent-support/all-tickets', 'fluent_support_vars', [
            'rest'            => $this->getRestInfo(),
        ]);

        register_block_type( 'fluent-support/all-tickets' , array(
            'editor_script' => 'fluent-support/all-tickets',
            'render_callback' => array($this, 'fst_render_block'),
            'attributes' => BlockAttributes::CustomerPortalAttributes(),
        ));
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

    public function fst_render_block($attributes)
    {
        $param = '';
        if(isset($attributes['showLogoutButton']) && $attributes['showLogoutButton']) {
            $param = "show_logout=yes";
        }

        if(isset($attributes['businessBoxId']) && $attributes['businessBoxId']) {
            $param .= "business_box_id='{$attributes['businessBoxId']}' ";
        }

        $param .= "attributes='".json_encode($attributes)."'";

        return do_shortcode("[fluent_support_portal $param]");
    }
}
