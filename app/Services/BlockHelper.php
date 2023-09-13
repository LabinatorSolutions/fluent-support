<?php

namespace FluentSupport\App\Services;

class BlockHelper
{
    public static function CustomerPortalBlockAttributes()
    {
        return [
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
            //Create Ticket form attributes
            'createTicketHeaderBgColor' => [
                'type' => 'string',
                'default' => '#ced3dd',
            ],
            'createTicketHeaderTextColor' => [
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
            'createTicketInputLabelTextColor' => [
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
            'createTicketCreateButtonBgColor' => [
                'type' => 'string',
                'default' => '#67C23A',
            ],
            'createTicketCreateButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            //View ticket Page attributes
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
            'viewTicketPageBodyBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'ribbonSupportStaffBgColor' => [
                'type' => 'string',
                'default' => '#1785EB',
            ],
            'ribbonSupportStaffTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'viewTicketThreadStarterBgColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'viewTicketThreadStarterTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'viewTicketThreadFollowerBgColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'viewTicketThreadFollowerTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
        ];
    }

    public static function processAttributesAndPrepareStyle($attributes)
    {
        ?>
        <style type="text/css">
            #fluent_support_client_app {
                .fs_btn_all {
                    background-color:<?php echo esc_attr($attributes['filterButtonAllBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['filterButtonAllTextColor']); ?>;
                }
                .fs_btn_open {
                    background-color:<?php echo esc_attr($attributes['filterButtonOpenBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['filterButtonOpenTextColor']); ?>;
                }
                .fs_btn_closed {
                    background-color:<?php echo esc_attr($attributes['filterButtonClosedBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['filterButtonClosedTextColor']); ?>;
                }

                .fs_btn_create_ticket {
                    background-color:<?php echo esc_attr($attributes['buttonCreateTicketBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['buttonCreateTicketTextColor']); ?>;
                }

                .fs_table thead{
                    background-color:<?php echo $attributes['allTicketsTableHeaderBgColor']; ?>;
                    color:<?php echo $attributes['allTicketsTableHeaderTextColor']; ?>;
                    text-align:<?php echo $attributes['allTicketsTableHeaderTextAlign']; ?>;
                }
            }
        </style>
    <?php }
}
