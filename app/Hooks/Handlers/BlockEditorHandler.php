<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\App\Services\Blocks\BlockAttributes;
use FluentSupport\App\Vite;

class BlockEditorHandler
{
    public function init()
    {
        // The editor preview draws the portal's markup, so the block's editor style
        // depends on the portal CSS (added below; block.json takes one style before WP 6.1)
        if (is_rtl()) {
            wp_register_style('fluent-support-portal-preview', App::getInstance()['url.assets'] . 'portal/css/app-rtl.css', [], FLUENT_SUPPORT_VERSION);
        } else {
            wp_register_style('fluent-support-portal-preview', Vite::getEnqueuePath('portal/css/app.css'), [], FLUENT_SUPPORT_VERSION);
        }

        // block.json (built by npm run build:block) holds the metadata and
        // registers index.js / index.css with the dependencies in index.asset.php
        $blockType = register_block_type(BlockAttributes::blockDir(), array(
            'render_callback' => array($this, 'renderBlock'),
        ));

        if (!$blockType) {
            return;
        }

        $editorStyle = wp_styles()->query(generate_block_asset_handle($blockType->name, 'editorStyle'), 'registered');
        if ($editorStyle) {
            $editorStyle->deps[] = 'fluent-support-portal-preview';
        }

        wp_localize_script(generate_block_asset_handle($blockType->name, 'editorScript'), 'fluent_support_vars', [
            'rest' => $this->getRestInfo(),
        ]);
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

    public function renderBlock($attributes)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '';
        }

        $param = '';

        if (Arr::get($attributes, 'showLogoutButton')) {
            $param = "show_logout=yes";
        }

        if ($selectedMailbox = Arr::get($attributes, 'selectedMailbox')) {
            $param .= " business_box_id='" . esc_attr($selectedMailbox) . "'";
        }

        $param .= " attributes='" . esc_attr( base64_encode( wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) . "'";

        // Wrapper attributes carry the block class and any "Additional CSS class(es)" set in the editor
        return '<div ' . get_block_wrapper_attributes() . '>' . do_shortcode("[fluent_support_portal $param]") . '</div>';
    }
}
