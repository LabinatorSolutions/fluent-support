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
            'filterButtonActiveBgColor' => [
                'type' => 'string',
                'default' => '#409eff',
            ],
            'filterButtonActiveBorderColor' => [
                'type' => 'string',
                'default' => '#dcdfe6',
            ],
            'filterButtonActiveTextColor' => [
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
                'default' => 4,
            ],
            'filterButtonAllBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonAllBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonAllBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
            'filterButtonOpenBorderColor' => [
                'type' => 'string',
                'default' => '#fff',
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
                'default' => 4,
            ],
            'filterButtonOpenBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonOpenBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonOpenBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
                'default' => 4,
            ],
            'filterButtonClosedBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonClosedBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'filterButtonClosedBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
                'default' => 4,
            ],
            'allTicketsLogoutButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsLogoutButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsLogoutButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
            'allTicketsCreateTicketButtonBorderColor' => [
                'type' => 'string',
                'default' => '#67c23a',
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
            'allTicketsCreateTicketButtonBorderRadius' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsCreateTicketButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsCreateTicketButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsCreateTicketButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'allTicketsCreateTicketButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
            'createTicketHeaderRadius' => [
                'type' => 'number',
                'default' => 10,
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
                'default' => 4,
            ],
            'createTicketHeaderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketViewAllButtonBgColor' => [
                'type' => 'string',
                'default' => '#909399',
            ],
            'createTicketViewAllButtonBorderColor' => [
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
            'createTicketViewAllButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketViewAllButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketViewAllButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketViewAllButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketViewAllButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketViewAllButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketViewAllButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketViewAllButtonBorderRadiusBottomRight' => [
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
            'createTicketCreateButtonBorderColor' => [
                'type' => 'string',
                'default' => '#909399',
            ],
            'createTicketCreateButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketCreateButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketCreateButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketCreateButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketCreateButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketCreateButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketCreateButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketCreateButtonBorderRadiusBottomRight' => [
                'type' => 'number',
                'default' => 4,
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
            'createTicketUploadButtonBorderColor' => [
                'type' => 'string',
                'default' => '#409eff',
            ],
            'createTicketUploadButtonBorderWidthTop' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketUploadButtonBorderWidthRight' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketUploadButtonBorderWidthBottom' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketUploadButtonBorderWidthLeft' => [
                'type' => 'number',
                'default' => 1,
            ],
            'createTicketUploadButtonBorderRadiusTopLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketUploadButtonBorderRadiusTopRight' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketUploadButtonBorderRadiusBottomLeft' => [
                'type' => 'number',
                'default' => 4,
            ],
            'createTicketUploadButtonBorderRadiusBottomRight' => [
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
                    border-color: <?php echo esc_attr($attributes['filterButtonActiveBorderColor'] ?? '#409eff'); ?>;
                    box-shadow: -1px 0 0 0 <?php echo esc_attr($attributes['filterButtonActiveBorderColor'] ?? '#409eff'); ?>;
                    background-color: <?php echo esc_attr($attributes['filterButtonActiveBgColor'] ?? '#409eff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonActiveTextColor'] ?? '#ffffff'); ?>;
                }
                .fst_tickets .fs_all_tickets .fs_tk_header {
                    background-color: <?php echo esc_attr($attributes['allTicketsHeaderBgColor'] ?? '#ebeef4'); ?>;
                    <?php echo self::getBorderRadiusStyle('allTicketsHeader', $attributes)?>
                }
                .fs_btn_all {
                    background-color: <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonAllTextColor'] ?? '#ffffff'); ?>;
                    box-shadow: 1px 0 0 0 <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                    <?php echo self::getBorderStyle('filterButtonAllBorder', $attributes)?>
                    <?php echo self::getBorderRadiusStyle('filterButtonAllBorder', $attributes)?>
                }
                .fs_btn_open {
                    background-color: <?php echo esc_attr($attributes['filterButtonOpenBgColor'] ?? '#ffffff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonOpenTextColor'] ?? '#606266'); ?>;
                    <?php echo self::getBorderStyle('filterButtonOpenBorder', $attributes)?>
                    <?php echo self::getBorderRadiusStyle('filterButtonOpenBorder', $attributes)?>
                }
                .fs_btn_closed {
                    background-color: <?php echo esc_attr($attributes['filterButtonClosedBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonClosedTextColor']); ?>;
                    <?php echo self::getBorderStyle('filterButtonClosedBorder', $attributes)?>
                    <?php echo self::getBorderRadiusStyle('filterButtonClosedBorder', $attributes)?>
                }
                .fs_btn_logout {
                    background-color: <?php echo esc_attr($attributes['allTicketsLogoutButtonBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['allTicketsLogoutButtonTextColor']); ?>;
                    <?php echo self::getBorderStyle('allTicketsLogoutButtonBorder', $attributes)?>
                    <?php echo self::getBorderRadiusStyle('allTicketsLogoutButtonBorder', $attributes)?>
                }
                .fs_btn_create_ticket {
                    background-color:<?php echo esc_attr($attributes['buttonCreateTicketBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['buttonCreateTicketTextColor']); ?>;
                    <?php echo self::getBorderStyle('allTicketsCreateTicketButtonBorder', $attributes)?>
                    <?php echo self::getBorderRadiusStyle('allTicketsCreateTicketButtonBorder', $attributes)?>
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
                <?php echo self::getBorderStyle('viewTicketHeaderBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('viewTicketHeaderBorder', $attributes)?>
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
                <?php echo self::getBorderStyle('refreshButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('refreshButtonBorder', $attributes)?>
            }

            .fs_all_button {
                background-color: <?php echo esc_attr($attributes['allButtonBgColor'] ?? '#ffffff'); ?>;
                color: <?php echo esc_attr($attributes['allButtonTextColor']); ?>;
                <?php echo self::getBorderStyle('allButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('allButtonBorder', $attributes)?>
            }

            .fs_close_button {
                background-color: <?php echo esc_attr($attributes['closeTicketButtonBgColor'] ?? '#f56c6c'); ?>;
                color: <?php echo esc_attr($attributes['closeTicketButtonTextColor']); ?>;
                <?php echo self::getBorderStyle('closeTicketButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('closeTicketButtonBorder', $attributes)?>
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
                <?php echo self::getBorderStyle('createTicketHeaderBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('createTicketHeader', $attributes)?>
            }

            .fs_tk_left h3 {
                color: <?php echo esc_attr($attributes['createTicketHeaderTextColor']); ?>;
            }

            .fs_tk_right .fs_view_all_button {
                background-color:  <?php echo esc_attr($attributes['createTicketViewAllButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketViewAllButtonTextColor']); ?>;
                <?php echo self::getBorderStyle('createTicketViewAllButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('createTicketViewAllButtonBorder', $attributes)?>
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
                <?php echo self::getBorderStyle('createTicketUploadButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('createTicketUploadButtonBorder', $attributes)?>
            }

            .fs_create_button {
                background-color:  <?php echo esc_attr($attributes['createTicketCreateButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketCreateButtonTextColor']); ?>;
                <?php echo self::getBorderStyle('createTicketCreateButtonBorder', $attributes)?>
                <?php echo self::getBorderRadiusStyle('createTicketCreateButtonBorder', $attributes)?>
            } }
        </style>
    <?php }

    public static function getBorderStyle($parentName, $attributes)
    {
        $borderStyle = '';
        $borderStyle .= 'border-top: ' . $attributes[$parentName . 'WidthTop'] . 'px solid ' . $attributes[$parentName . 'Color'] . ';';
        $borderStyle .= 'border-right: ' . $attributes[$parentName . 'WidthRight'] . 'px solid ' . $attributes[$parentName . 'Color'] . ';';
        $borderStyle .= 'border-bottom: ' . $attributes[$parentName . 'WidthBottom'] . 'px solid ' . $attributes[$parentName . 'Color'] . ';';
        $borderStyle .= 'border-left: ' . $attributes[$parentName . 'WidthLeft'] . 'px solid ' . $attributes[$parentName . 'Color'] . ';';
        return $borderStyle;
    }

    public static function getBorderRadiusStyle($parentName, $attributes){
        $borderRadius = '';
        $borderRadius .= 'border-top-left-radius: ' . $attributes[$parentName . 'RadiusTopLeft'] . 'px;';
        $borderRadius .= 'border-top-right-radius: ' . $attributes[$parentName . 'RadiusTopRight'] . 'px;';
        $borderRadius .= 'border-bottom-right-radius: ' . $attributes[$parentName . 'RadiusBottomRight'] . 'px;';
        $borderRadius .= 'border-bottom-left-radius: ' . $attributes[$parentName . 'RadiusBottomLeft'] . 'px;';
        return $borderRadius;
    }
}
