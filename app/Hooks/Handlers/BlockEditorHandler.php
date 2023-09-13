<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Services\BlockHelper;

class BlockEditorHandler
{
    public function init()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];
        wp_register_script(
            'fluent-support/customer-portal',
            $assets . 'block-editor/js/fst_block.js',
            array('jquery', 'wp-blocks', 'wp-element')
        );
        register_block_type( 'fluent-support/customer-portal' , array(
            'editor_script' => 'fluent-support/customer-portal',
            'render_callback' => array($this, 'fst_render_block'),
            'attributes' => BlockHelper::CustomerPortalBlockAttributes(),
        ));
    }

    public function fst_render_block($attributes)
    {
        $param = '';
        if(isset($attributes['allTicketsLogoutButtonVisibility']) && $attributes['allTicketsLogoutButtonVisibility']) {
            $param = "show_logout=yes ";
        }
        $param .= "attributes='".json_encode($attributes)."'";
        return do_shortcode("[fluent_support_portal $param]");
    }
}
