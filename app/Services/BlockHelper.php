<?php

namespace FluentSupport\App\Services;

//use function GuzzleHttp\default_ca_bundle;

class BlockHelper
{
    public static function CustomerPortalBlockAttributes()
    {
        return [
            'allTicketsHeaderBgColor' => [
                'type' => 'string',
                'default' => '#ebeef4',
            ],
            'filterButtonAllBgColor' => [
                'type' => 'string',
                'default' => '#409eff',
            ],
            'filterButtonAllTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonAllBorderColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonAllBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonAllBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonAllBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonAllBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonAllBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonAllBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonAllBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonAllBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonAllBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonAllBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonOpenTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'filterButtonOpenBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonOpenBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonOpenBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonOpenBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonOpenBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonOpenBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonClosedBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonClosedTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'filterButtonClosedBorderColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonClosedBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonClosedBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonClosedBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonClosedBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonClosedBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'filterButtonClosedBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonClosedBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonClosedBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonClosedBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'filterButtonClosedBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsLogoutButtonVisibility' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'allTicketsLogoutButtonBgColor' => [
                'type' => 'string',
                'default' => '#f56c6c',
            ],
            'allTicketsLogoutButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'allTicketsLogoutButtonBorderColor' => [
                'type' => 'string',
                'default' => '#f56c6c',
            ],
            'allTicketsLogoutButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsLogoutButtonBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsLogoutButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsLogoutButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'buttonCreateTicketBgColor' => [
                'type' => 'string',
                'default' => '#67c23a',
            ],
            'buttonCreateTicketTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'buttonCreateTicketBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsCreateTicketButtonBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsCreateTicketButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allTicketsCreateTicketButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'buttonCreateTicketBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsTableHeaderBgColor' => [
                'type' => 'string',
                'default' => '#f8f8f8',
            ],
            'allTicketsTableHeaderTextColor' => [
                'type' => 'string',
                'default' => '#646568',
            ],
            'allTicketsTableHeaderTextAlign' => [
                'type' => 'string',
                'default' => 'center',
            ],
            'allTicketsFooterBgColor' => [
                'type' => 'string',
                'default' => '#ebeef4',
            ],
            //Create Ticket form attributes
            'createTicketHeaderBgColor' => [
                'type' => 'string',
                'default' => '#ebeef4',
            ],
            'createTicketHeaderTextColor' => [
                'type' => 'string',
                'default' => '#314351',
            ],
            'createTicketHeaderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'createTicketHeaderRadiusTopRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'createTicketHeaderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'createTicketHeaderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'createTicketViewAllButtonBgColor' => [
                'type' => 'string',
                'default' => '#909399',
            ],
            'createTicketViewAllButtonBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'createTicketViewAllButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketViewAllButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'createTicketCreateButtonBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'createTicketCreateButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketBodyBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'createTicketInputLabelTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'createTicketHintMessageTextColor' => [
                'type' => 'string',
                'default' => '#76777b',
            ],
            'createTicketUploadButtonBgColor' => [
                'type' => 'string',
                'default' => '#409eff',
            ],
            'createTicketUploadButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'createTicketUploadButtonBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'createTicketUploadButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'refreshButtonBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'refreshButtonTextColor' => [
                'type' => 'string',
                'default' => '#000000',
            ],
            'refreshButtonBorderColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'refreshButtonBorderWidth' => [
                'type' => 'number',
                'default' => 1,
            ],
            'refreshButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'refreshButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'refreshButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'refreshButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'refreshButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'refreshButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'refreshButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'refreshButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 6,
            ],
            'refreshButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allButtonBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'allButtonTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'allButtonBorderColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'allButtonBorderWidth' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'allButtonBorderRadius' => [
                'type' => 'number',
                'default' => 5,
            ],
            'closeTicketButtonBgColor' => [
                'type' => 'string',
                'default' => '#f56c6c',
            ],
            'closeTicketButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'closeTicketButtonBorderColor' => [
                'type' => 'string',
                'default' => '#f56c6c',
            ],
            'closeTicketButtonBorderWidth' => [
                'type' => 'number',
                'default' => 1,
            ],
            'closeTicketButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'closeTicketButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'closeTicketButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'closeTicketButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'closeTicketButtonBorderRadius' => [
                'type' => 'number',
                'default' => 5,
            ],
            'closeTicketButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 3,
            ],
            'closeTicketButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'closeTicketButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 6,
            ],
            'closeTicketButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 7,
            ],
            'createTicketCreateButtonBgColor' => [
                'type' => 'number',
                'default' => '#67c23a',
            ],
            'createTicketCreateButtonTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            //View ticket Page attributes
            'viewTicketHeaderStyleBgColor' => [
                'type' => 'string',
                'default' => '#ebeef4',
            ],
            'viewTicketHeaderStyleTextColor' => [
                'type' => 'string',
                'default' => '#314351',
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
            'viewTicketHeaderBorderColor' => [
                'type' => 'string',
                'default' => '#ebeef4',
            ],
            'viewTicketHeaderBorderWidth' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderBorderWidthTop' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderBorderWidthRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderBorderWidthBottom' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderBorderWidthLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderRadius' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderRadiusTopRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'viewTicketHeaderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsHeaderRadius' => [
                'type' => 'number',
                'default' => 10,
            ],
            'allTicketsHeaderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'allTicketsHeaderRadiusTopRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'allTicketsHeaderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsHeaderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsFooterRadius' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsFooterRadiusTopLeft' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsFooterRadiusTopRight' => [
                'type' => 'number',
                'default' => 0,
            ],
            'allTicketsFooterRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'allTicketsFooterRadiusBottomRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'viewTicketPageBodyBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'CustomerThreadRibbonColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'AgentThreadRibbonColor' => [
                'type' => 'string',
                'default' => '#1785EB',
            ],
            'ribbonSupportStaffBgColor' => [
                'type' => 'string',
                'default' => '#1785EB',
            ],
            'ribbonSupportStaffTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'ribbonSupportStaffTailWidth' => [
                'type' => 'number',
                'default' => 5,
            ],
            'ribbonSupportStaffPadding' => [
                'type' => 'number',
                'default' => 5,
            ],
            'ribbonSupportStaffPaddingTop' => [
                'type' => 'number',
                'default' => 5,
            ],
            'ribbonSupportStaffPaddingBottom' => [
                'type' => 'number',
                'default' => 5,
            ],
            'ribbonSupportStaffPaddingLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'ribbonSupportStaffPaddingRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'viewTicketThreadStarterBgColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'viewTicketThreadStarterTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'viewTicketThreadStarterTailWidth' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadStarterPadding' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadStarterPaddingTop' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadStarterPaddingBottom' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadStarterPaddingLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'viewTicketThreadStarterPaddingRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'viewTicketThreadFollowerBgColor' => [
                'type' => 'string',
                'default' => '#ff00ff',
            ],
            'viewTicketThreadFollowerTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'viewTicketThreadFollowerTailWidth' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadFollowerPadding' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadFollowerPaddingTop' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadFollowerPaddingBottom' => [
                'type' => 'number',
                'default' => 5,
            ],
            'viewTicketThreadFollowerPaddingLeft' => [
                'type' => 'number',
                'default' => 10,
            ],
            'viewTicketThreadFollowerPaddingRight' => [
                'type' => 'number',
                'default' => 10,
            ],
            'businessBoxId' => [
                'type' => 'number',
                'default' => '',
            ],
        ];
    }

    public static function processAttributesAndPrepareStyle($attributes)
    {
        ?>
        <style type="text/css">
            #fluent_support_client_app {
            /*Tickets List style*/
                .fs_button_groups > .fs_btn_active {
                    background-color: #409eff;
                    border-color: #409eff;
                    box-shadow: -1px 0 0 0 #409eff;
                    color: #fff;
                }
            .fst_tickets .fs_all_tickets .fs_tk_header {
                    background-color: <?php echo esc_attr($attributes['allTicketsHeaderBgColor'] ?? '#ebeef4'); ?>;
                    border-radius: <?php echo esc_attr($attributes['allTicketsHeaderRadiusTopLeft']); ?>px <?php echo esc_attr($attributes['allTicketsHeaderRadiusTopRight']); ?>px <?php echo esc_attr($attributes['allTicketsHeaderRadiusBottomRight']); ?>px <?php echo esc_attr($attributes['allTicketsHeaderRadiusBottomLeft']); ?>px;
                }
                .fs_btn_all {
                    background-color: <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonAllTextColor'] ?? '#ffffff'); ?>;
                    box-shadow: 1px 0 0 0 <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                    border: <?php echo esc_attr($attributes['filterButtonAllBorderWidth']); ?>px solid <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#ffffff'); ?>;
                    border-radius: <?php echo esc_attr($attributes['filterButtonAllBorderRadius']); ?>px;
                }
                .fs_btn_open {
                    background-color: <?php echo esc_attr($attributes['filterButtonOpenBgColor'] ?? '#ffffff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonOpenTextColor'] ?? '#606266'); ?>;
                    border: <?php echo esc_attr($attributes['filterButtonOpenBorderWidth']); ?>px solid <?php echo esc_attr($attributes['filterButtonOpenBgColor'] ?? '#ffffff'); ?>;
                    border-radius: <?php echo esc_attr($attributes['filterButtonOpenBorderRadius']); ?>px;
                }
                .fs_btn_closed {
                    background-color: <?php echo esc_attr($attributes['filterButtonClosedBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonClosedTextColor']); ?>;
                    border: <?php echo esc_attr($attributes['filterButtonClosedBorderWidth']); ?>px solid <?php echo esc_attr($attributes['filterButtonClosedBgColor'] ?? '#ffffff'); ?>;
                    border-radius: <?php echo esc_attr($attributes['filterButtonClosedBorderRadius']); ?>px;
                }

                .fs_btn_logout {
                    background-color: <?php echo esc_attr($attributes['allTicketsLogoutButtonBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['allTicketsLogoutButtonTextColor']); ?>;
                    border: <?php echo esc_attr($attributes['allTicketsLogoutButtonBorderWidth']); ?>px solid <?php echo esc_attr($attributes['allTicketsLogoutButtonBgColor'] ?? '#ffffff'); ?>;
                    border-radius: <?php echo esc_attr($attributes['allTicketsLogoutButtonBorderRadius']); ?>px;
                }
                .fs_btn_create_ticket {
                    background-color:<?php echo esc_attr($attributes['buttonCreateTicketBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['buttonCreateTicketTextColor']); ?>;
                    border: <?php echo esc_attr($attributes['buttonCreateTicketBorderWidth']); ?>px solid <?php echo esc_attr($attributes['buttonCreateTicketBgColor'] ?? '#ffffff'); ?>;
                    border-radius: <?php echo esc_attr($attributes['buttonCreateTicketBorderRadius']); ?>px;
                }

                .fs_table thead{
                    background-color:<?php echo esc_attr($attributes['allTicketsTableHeaderBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['allTicketsTableHeaderTextColor']); ?>;
                    text-align:<?php echo esc_attr($attributes['allTicketsTableHeaderTextAlign']); ?>;
                }

                .fs_table thead th{
                    background-color:<?php echo esc_attr($attributes['allTicketsTableHeaderBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['allTicketsTableHeaderTextColor']); ?>;
                    text-align:<?php echo esc_attr($attributes['allTicketsTableHeaderTextAlign']); ?>;
                }
                .fst_pagi_wrapper{
                    background-color:<?php echo esc_attr($attributes['allTicketsFooterBgColor']); ?>;
                    margin: 0 auto;
                    border-radius: <?php echo esc_attr($attributes['allTicketsFooterRadiusTopLeft']); ?>px <?php echo esc_attr($attributes['allTicketsFooterRadiusTopRight']); ?>px <?php echo esc_attr($attributes['allTicketsFooterRadiusBottomRight']); ?>px <?php echo esc_attr($attributes['allTicketsFooterRadiusBottomLeft']); ?>px;
                }

            /*View Ticket style*/

            .fst_client_portal .fs_ticket .fs_tk_header {
                background-color: <?php echo esc_attr($attributes['viewTicketHeaderStyleBgColor']); ?>;
                border-top: <?php echo esc_attr($attributes['viewTicketHeaderBorderWidthTop']); ?>px solid <?php echo esc_attr($attributes['viewTicketHeaderBorderColor'] ?? '#ebeef4'); ?>;
                border-right: <?php echo esc_attr($attributes['viewTicketHeaderBorderWidthRight']); ?>px solid <?php echo esc_attr($attributes['viewTicketHeaderBorderColor'] ?? '#ebeef4'); ?>;
                border-bottom: <?php echo esc_attr($attributes['viewTicketHeaderBorderWidthBottom']); ?>px solid <?php echo esc_attr($attributes['viewTicketHeaderBorderColor'] ?? '#ebeef4'); ?>;
                border-left: <?php echo esc_attr($attributes['viewTicketHeaderBorderWidthLeft']); ?>px solid <?php echo esc_attr($attributes['viewTicketHeaderBorderColor'] ?? '#ebeef4'); ?>;
                border-radius: <?php echo esc_attr($attributes['viewTicketHeaderRadiusTopLeft']); ?>px <?php echo esc_attr($attributes['viewTicketHeaderRadiusTopRight']); ?>px <?php echo esc_attr($attributes['viewTicketHeaderRadiusBottomRight']); ?>px <?php echo esc_attr($attributes['viewTicketHeaderRadiusBottomLeft']); ?>px;
            }

            .fst_client_portal .fs_th_header .fs_tk_subject h2 {
                color: <?php echo esc_attr($attributes['viewTicketHeaderStyleTextColor']); ?>;
            }

            .fs_ticket_id {
                color: <?php echo esc_attr($attributes['viewTicketHeaderIdTextColor']); ?>;
            }

            .fs_refresh_button {
                background-color: <?php echo esc_attr($attributes['refreshButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['refreshButtonTextColor']); ?>;
                border-top: <?php echo esc_attr($attributes['refreshButtonBorderWidthTop']); ?>px solid <?php echo esc_attr($attributes['refreshButtonBorderColor'] ?? '#ffffff'); ?>;
                border-right: <?php echo esc_attr($attributes['refreshButtonBorderWidthRight']); ?>px solid <?php echo esc_attr($attributes['refreshButtonBorderColor'] ?? '#ffffff'); ?>;
                border-bottom: <?php echo esc_attr($attributes['refreshButtonBorderWidthBottom']); ?>px solid <?php echo esc_attr($attributes['refreshButtonBorderColor'] ?? '#ffffff'); ?>;
                border-left: <?php echo esc_attr($attributes['refreshButtonBorderWidthLeft']); ?>px solid <?php echo esc_attr($attributes['refreshButtonBorderColor'] ?? '#ffffff'); ?>;
                border-top-left-radius: <?php echo esc_attr($attributes['refreshButtonBorderRadiusTopLeft']); ?>px;
                border-top-right-radius: <?php echo esc_attr($attributes['refreshButtonBorderRadiusTopRight']); ?>px;
                border-bottom-right-radius: <?php echo esc_attr($attributes['refreshButtonBorderRadiusBottomRight']); ?>px;
                border-bottom-left-radius: <?php echo esc_attr($attributes['refreshButtonBorderRadiusBottomLeft']); ?>px;
            }

            .fs_all_button {
                background-color: <?php echo esc_attr($attributes['allButtonBgColor'] ?? '#ffffff'); ?>;
                color: <?php echo esc_attr($attributes['allButtonTextColor']); ?>;
                border-top: <?php echo esc_attr($attributes['allButtonBorderWidthTop']); ?>px solid <?php echo esc_attr($attributes['allButtonBorderColor'] ?? '#ffffff'); ?>;
                border-right: <?php echo esc_attr($attributes['allButtonBorderWidthRight']); ?>px solid <?php echo esc_attr($attributes['allButtonBorderColor'] ?? '#ffffff'); ?>;
                border-bottom: <?php echo esc_attr($attributes['allButtonBorderWidthBottom']); ?>px solid <?php echo esc_attr($attributes['allButtonBorderColor'] ?? '#ffffff'); ?>;
                border-left: <?php echo esc_attr($attributes['allButtonBorderWidthLeft']); ?>px solid <?php echo esc_attr($attributes['allButtonBorderColor'] ?? '#ffffff'); ?>;
                border-top-left-radius: <?php echo esc_attr($attributes['allButtonBorderRadiusTopLeft']); ?>px;
                border-top-right-radius: <?php echo esc_attr($attributes['allButtonBorderRadiusTopRight']); ?>px;
                border-bottom-right-radius: <?php echo esc_attr($attributes['allButtonBorderRadiusBottomRight']); ?>px;
                border-bottom-left-radius: <?php echo esc_attr($attributes['allButtonBorderRadiusBottomLeft']); ?>px;
            }

            .fs_close_button {
                background-color: <?php echo esc_attr($attributes['closeTicketButtonBgColor'] ?? '#f56c6c'); ?>;
                color: <?php echo esc_attr($attributes['closeTicketButtonTextColor']); ?>;
                border-top: <?php echo esc_attr($attributes['closeTicketButtonBorderWidthTop']); ?>px solid <?php echo esc_attr($attributes['closeTicketButtonBorderColor'] ?? '#f56c6c'); ?>;
                border-right: <?php echo esc_attr($attributes['closeTicketButtonBorderWidthRight']); ?>px solid <?php echo esc_attr($attributes['closeTicketButtonBorderColor'] ?? '#f56c6c'); ?>;
                border-bottom: <?php echo esc_attr($attributes['closeTicketButtonBorderWidthBottom']); ?>px solid <?php echo esc_attr($attributes['closeTicketButtonBorderColor'] ?? '#f56c6c'); ?>;
                border-left: <?php echo esc_attr($attributes['closeTicketButtonBorderWidthLeft']); ?>px solid <?php echo esc_attr($attributes['closeTicketButtonBorderColor'] ?? '#f56c6c'); ?>;
                border-top-left-radius: <?php echo esc_attr($attributes['closeTicketButtonBorderRadiusTopLeft']); ?>px;
                border-top-right-radius: <?php echo esc_attr($attributes['closeTicketButtonBorderRadiusTopRight']); ?>px;
                border-bottom-right-radius: <?php echo esc_attr($attributes['closeTicketButtonBorderRadiusBottomRight']); ?>px;
                border-bottom-left-radius: <?php echo esc_attr($attributes['closeTicketButtonBorderRadiusBottomLeft']); ?>px;
            }

            .fst_reply_box, .fs_threads_container {
                background-color: <?php echo esc_attr($attributes['viewTicketPageBodyBgColor']); ?>;
            }

            .fs_agent {
                border-left: <?php echo esc_attr($attributes['ribbonSupportStaffTailWidth']); ?>px solid <?php echo esc_attr($attributes['ribbonSupportStaffBgColor']); ?>;
            }

            .fs_conv_type_response .fs_thread_ribbon_agent{
                background-color:  <?php echo esc_attr($attributes['ribbonSupportStaffBgColor']); ?>;
                color: <?php echo esc_attr($attributes['ribbonSupportStaffTextColor']); ?>;
                padding: <?php echo esc_attr($attributes['ribbonSupportStaffPaddingTop']); ?>px <?php echo esc_attr($attributes['ribbonSupportStaffPaddingRight']); ?>px <?php echo esc_attr($attributes['ribbonSupportStaffPaddingBottom']); ?>px <?php echo esc_attr($attributes['ribbonSupportStaffPaddingLeft']); ?>px;
            }

            .fs_customer {
                border-left: <?php echo esc_attr($attributes['viewTicketThreadStarterTailWidth']); ?>px solid <?php echo esc_attr($attributes['viewTicketThreadStarterBgColor']); ?>;
            }

            .fs_conv_type_response .fs_thread_ribbon_customer{
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadStarterBgColor']); ?>;
                color: <?php echo esc_attr($attributes['viewTicketThreadStarterTextColor']); ?>;
                padding: <?php echo esc_attr($attributes['viewTicketThreadStarterPaddingTop']); ?>px <?php echo esc_attr($attributes['viewTicketThreadStarterPaddingRight']); ?>px <?php echo esc_attr($attributes['viewTicketThreadStarterPaddingBottom']); ?>px <?php echo esc_attr($attributes['viewTicketThreadStarterPaddingLeft']); ?>px;
            }

            .fs_cc_customer {
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadFollowerBgColor']); ?>;
                color: <?php echo esc_attr($attributes['viewTicketThreadFollowerTextColor']); ?>;
                border-left: <?php echo esc_attr($attributes['viewTicketThreadFollowerTailWidth']); ?>px solid <?php echo esc_attr($attributes['viewTicketThreadFollowerBgColor']); ?>;;
            }

            .fs_thread_ribbon_customer_cc {
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadFollowerBgColor']); ?>;
                padding: <?php echo esc_attr($attributes['viewTicketThreadFollowerPaddingTop']); ?>px <?php echo esc_attr($attributes['viewTicketThreadFollowerPaddingRight']); ?>px <?php echo esc_attr($attributes['viewTicketThreadFollowerPaddingBottom']); ?>px <?php echo esc_attr($attributes['viewTicketThreadFollowerPaddingLeft']); ?>px;
            }

            /*Create Ticket style*/
            .fs_tk_create_head {
                background-color:  <?php echo esc_attr($attributes['createTicketHeaderBgColor']); ?>;
                border-radius: <?php echo esc_attr($attributes['createTicketHeaderRadiusTopLeft']); ?>px <?php echo esc_attr($attributes['createTicketHeaderRadiusTopRight']); ?>px <?php echo esc_attr($attributes['createTicketHeaderRadiusBottomRight']); ?>px <?php echo esc_attr($attributes['createTicketHeaderRadiusBottomLeft']); ?>px;
            }

            .fs_tk_left h3 {
                color: <?php echo esc_attr($attributes['createTicketHeaderTextColor']); ?>;
            }

            .fs_tk_right .fs_view_all_button {
                background-color:  <?php echo esc_attr($attributes['createTicketViewAllButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketViewAllButtonTextColor']); ?>;
                border: <?php echo esc_attr($attributes['createTicketViewAllButtonBorderWidth']); ?>px solid <?php echo esc_attr($attributes['createTicketViewAllButtonBgColor'] ?? '#f56c6c'); ?>;
                border-radius: <?php echo esc_attr($attributes['createTicketViewAllButtonBorderRadius']); ?>px;
            }

            .fs_tk_body {
                background-color:  <?php echo esc_attr($attributes['createTicketBodyBgColor']); ?>;
            }

            .fs_input_label, .fs_input_label label {
                color: <?php echo esc_attr($attributes['createTicketInputLabelTextColor']); ?>;
            }

            .fs_tk_help,.fs_tk_upload_help {
                color: <?php echo esc_attr($attributes['createTicketHintMessageTextColor']); ?>;
            }

            .fs_attachment_button {
                background-color:  <?php echo esc_attr($attributes['createTicketUploadButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketUploadButtonTextColor']); ?>;
                border: <?php echo esc_attr($attributes['createTicketUploadButtonBorderWidth']); ?>px solid <?php echo esc_attr($attributes['createTicketUploadButtonBgColor'] ?? '#f56c6c'); ?>;
                border-radius: <?php echo esc_attr($attributes['createTicketUploadButtonBorderRadius']); ?>px;
            }

            .fs_create_button {
                background-color:  <?php echo esc_attr($attributes['createTicketCreateButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketCreateButtonTextColor']); ?>;
                border: <?php echo esc_attr($attributes['createTicketCreateButtonBorderWidth']); ?>px solid <?php echo esc_attr($attributes['createTicketCreateButtonBgColor'] ?? '#f56c6c'); ?>;
                border-radius: <?php echo esc_attr($attributes['createTicketCreateButtonBorderRadius']); ?>px;
            }

            }
        </style>
    <?php }
}
