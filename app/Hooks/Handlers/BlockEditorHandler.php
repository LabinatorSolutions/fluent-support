<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;

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
            'attributes' => [
                'buttonAllBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'buttonAllTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'buttonOpenBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'buttonOpenTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'buttonClosedBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'buttonClosedTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'buttonCreateTicketBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'buttonCreateTicketTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
            ]
        ));
    }

    public function fst_render_block($attributes)
    {
        return do_shortcode('[fluent_support_portal]');
    }
}
