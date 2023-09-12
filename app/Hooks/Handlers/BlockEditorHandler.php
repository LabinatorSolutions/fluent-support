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
                'allTicketsHeaderBgColor' => [
                    'type' => 'string',
                    'default' => '#f4f6f4',
                ],
                'filterButtonAllBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'filterButtonAllTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'filterButtonOpenBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'filterButtonOpenTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'filterButtonClosedBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'filterButtonClosedTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'allTicketsLogoutButtonVisibility' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'allTicketsLogoutButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'allTicketsLogoutButtonTextColor' => [
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
                'allTicketsTableHeaderBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'allTicketsTableHeaderTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'allTicketsTableHeaderTextAlign' => [
                    'type' => 'string',
                    'default' => 'center',
                ],
                'allTicketsFooterBgColor' => [
                    'type' => 'string',
                    'default' => '#c8ccd3',
                ],
                'ticketHeaderBgColor' => [
                    'type' => 'string',
                    'default' => '#426fca',
                ],
                'ticketFooterBgColor' => [
                    'type' => 'string',
                    'default' => '#426fca',
                ],
                'createTicketFormHeaderBgColor' => [
                    'type' => 'string',
                    'default' => '#16c410',
                ],
                'refreshButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'refreshButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#000000',
                ],
                'allButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'allButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#606266',
                ],
                'closeTicketButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#f56c6c',
                ],
                'closeTicketButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'viewTicketHeaderStyleBgColor' => [
                    'type' => 'string',
                    'default' => '#ced3dd',
                ],
                'viewTicketHeaderStyleTextColor' => [
                    'type' => 'string',
                    'default' => '#000000',
                ],
                'viewTicketHeaderIdTextColor' => [
                    'type' => 'string',
                    'default' => '#93a1b0',
                ],
                'viewTicketHeaderBadgeBgColor' => [
                    'type' => 'string',
                    'default' => '#f56c6c',
                ],
                'viewTicketHeaderBadgeTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'replyBoxBgColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'threadContentBgColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'viewTicketThreadTitleTextColor' => [
                    'type' => 'string',
                    'default' => '#8494a5',
                ],
                'viewTicketThreadDateTimeTextColor' => [
                    'type' => 'string',
                    'default' => '#a5b2bd',
                ],
                'viewTicketThreadBodyTextColor' => [
                    'type' => 'string',
                    'default' => '#253642',
                ],
                'createTicketHeaderBgColor' => [
                    'type' => 'string',
                    'default' => '#ced3dd',
                ],
                'createTicketHeaderTextBgColor' => [
                    'type' => 'string',
                    'default' => '#314351',
                ],
                'createTicketViewAllButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#909399',
                ],
                'createTicketViewAllButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'createTicketBodyBgColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'createTicketInputHeaderTextColor' => [
                    'type' => 'string',
                    'default' => '#43454b',
                ],
                'createTicketTipMessageTextColor' => [
                    'type' => 'string',
                    'default' => '#606266',
                ],
                'createTicketUploadButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#409eff',
                ],
                'createTicketUploadButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'createTicketCreateButtonBgColor' => [
                    'type' => 'string',
                    'default' => '#67C23A',
                ],
                'createTicketCreateButtonTextColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
            ]
        ));
    }

    public function fst_render_block($attributes)
    {
        return do_shortcode("[fluent_support_portal attributes='".json_encode($attributes)."']");
    }
}
