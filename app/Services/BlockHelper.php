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
            'filterButtonOpenBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonOpenTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'filterButtonClosedBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'filterButtonClosedTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'filterButtonClosedBorderRadius' => [
                'type' => 'string',
                'default' => '0',
            ],
            'filterButtonClosedBorderColor' => [
                'type' => 'string',
                'default' => '#ffffff',
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
            'buttonCreateTicketBgColor' => [
                'type' => 'string',
                'default' => '#67c23a',
            ],
            'buttonCreateTicketTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
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
            'refreshButtonBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'refreshButtonTextColor' => [
                'type' => 'string',
                'default' => '#000000',
            ],
            'refreshButtonBorderWidth' => [
                'type' => 'string',
                'default' => 1,
            ],
            'refreshButtonBorderRadius' => [
                'type' => 'string',
                'default' => 5,
            ],
            'allButtonBgColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'allButtonTextColor' => [
                'type' => 'string',
                'default' => '#606266',
            ],
            'allButtonBorderWidth' => [
                'type' => 'string',
                'default' => 1,
            ],
            'allButtonBorderRadius' => [
                'type' => 'string',
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
            'closeTicketButtonBorderWidth' => [
                'type' => 'string',
                'default' => 1,
            ],
            'closeTicketButtonBorderRadius' => [
                'type' => 'string',
                'default' => 5,
            ],
            'createTicketCreateButtonBgColor' => [
                'type' => 'string',
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
            'viewTicketThreadStarterBgColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'viewTicketThreadStarterTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'viewTicketThreadStarterBorderWidth' => [
                'type' => 'string',
                'default' => 1,
            ],
            'viewTicketThreadStarterPaddingBottom' => [
                'type' => 'string',
                'default' => 5,
            ],
            'viewTicketThreadStarterPaddingLeft' => [
                'type' => 'string',
                'default' => 10,
            ],
            'viewTicketThreadFollowerBgColor' => [
                'type' => 'string',
                'default' => '#15BE7C',
            ],
            'viewTicketThreadFollowerTextColor' => [
                'type' => 'string',
                'default' => '#ffffff',
            ],
            'businessBoxId' => [
                'type' => 'string',
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
                .fs_tk_header {
                    background-color: <?php echo esc_attr($attributes['allTicketsHeaderBgColor'] ?? '#ebeef4'); ?>;
                }
                .fs_btn_all {
                    background-color: <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonAllTextColor'] ?? '#ffffff'); ?>;
                    border: 1px solid <?php echo esc_attr($attributes['filterButtonAllBorderColor'] ?? '#409eff'); ?>;
                    box-shadow: 1px 0 0 0 <?php echo esc_attr($attributes['filterButtonAllBgColor'] ?? '#409eff'); ?>;
                }
                .fs_btn_open {
                    background-color: <?php echo esc_attr($attributes['filterButtonOpenBgColor'] ?? '#ffffff'); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonOpenTextColor'] ?? '#606266'); ?>;
                    border: 1px solid <?php echo esc_attr($attributes['filterButtonOpenBorderColor'] ?? '#ffffff'); ?>;
                }
                .fs_btn_closed {
                    background-color: <?php echo esc_attr($attributes['filterButtonClosedBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['filterButtonClosedTextColor']); ?>;
                    border: 1px solid <?php echo esc_attr($attributes['filterButtonClosedBgColor']); ?>;
                    border-radius: <?php echo esc_attr($attributes['filterButtonClosedBorderRadius'] ?? 4) . 'px'; ?>;
                    border-color: <?php echo esc_attr($attributes['filterButtonClosedBorderColor'] ?? '#ffffff'); ?>;
                }

                .fs_btn_logout {
                    background-color: <?php echo esc_attr($attributes['allTicketsLogoutButtonBgColor']); ?>;
                    color: <?php echo esc_attr($attributes['allTicketsLogoutButtonTextColor']); ?>;
                    border: 1px solid <?php echo esc_attr($attributes['allTicketsLogoutButtonBorderColor']); ?>;
                }

                .fs_btn_create_ticket {
                    background-color:<?php echo esc_attr($attributes['buttonCreateTicketBgColor']); ?>;
                    color:<?php echo esc_attr($attributes['buttonCreateTicketTextColor']); ?>;
                    border: 1px solid <?php echo esc_attr($attributes['buttonCreateTicketBorderColor']); ?>;
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
                }

            /*View Ticket style*/

            .fst_client_portal .fs_ticket .fs_tk_header {
                background-color: <?php echo esc_attr($attributes['viewTicketHeaderStyleBgColor']); ?>;

            }

            .fs_tk_header h2 {
                color: <?php echo esc_attr($attributes['viewTicketHeaderStyleTextColor']); ?>
            }

            .fs_ticket_id {
                color: <?php echo esc_attr($attributes['viewTicketHeaderIdTextColor']); ?>;
            }

            .fs_refresh_button {
                background-color: <?php echo esc_attr($attributes['refreshButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['refreshButtonTextColor']); ?>;
                border: 1px solid <?php echo esc_attr($attributes['refreshButtonBgColor'] ?? '#ffffff'); ?>;
            }

            .fs_all_button {
                background-color: <?php echo esc_attr($attributes['allButtonBgColor'] ?? '#ffffff'); ?>;
                color: <?php echo esc_attr($attributes['allButtonTextColor']); ?>;
                border: 1px solid <?php echo esc_attr($attributes['allButtonBgColor'] ?? '#ffffff'); ?>;
            }

            .fs_close_button {
                background-color: <?php echo esc_attr($attributes['closeTicketButtonBgColor'] ?? '#f56c6c'); ?>;
                color: <?php echo esc_attr($attributes['closeTicketButtonTextColor']); ?>;
                border: 1px solid <?php echo esc_attr($attributes['closeTicketButtonBgColor'] ?? '#f56c6c'); ?>;
            }

            .fst_reply_box, .fs_threads_container {
                background-color: <?php echo esc_attr($attributes['viewTicketPageBodyBgColor']); ?>;
            }

            .fs_agent {
                border-left: 4px solid <?php echo esc_attr($attributes['ribbonSupportStaffBgColor']); ?>;
            }

            .fs_conv_type_response .fs_thread_ribbon_agent{
                background-color:  <?php echo esc_attr($attributes['ribbonSupportStaffBgColor']); ?>;
                color: <?php echo esc_attr($attributes['ribbonSupportStaffTextColor']); ?>;
            }

            .fs_customer {
                border-left: 4px solid <?php echo esc_attr($attributes['viewTicketThreadStarterBgColor']); ?>;
            }

            .fs_conv_type_response .fs_thread_ribbon_customer{
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadStarterBgColor']); ?>;
                color: <?php echo esc_attr($attributes['viewTicketThreadStarterTextColor']); ?>;
            }

            .fs_cc_customer {
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadFollowerBgColor']); ?>;
                color: <?php echo esc_attr($attributes['viewTicketThreadFollowerTextColor']); ?>;
            }

            .fs_thread_ribbon_customer_cc {
                background-color:  <?php echo esc_attr($attributes['viewTicketThreadFollowerBgColor']); ?>;
            }

            /*Create Ticket style*/
            .fs_tk_create_head {
                background-color:  <?php echo esc_attr($attributes['createTicketHeaderBgColor']); ?>;
            }

            .fs_tk_left h3 {
                color: <?php echo esc_attr($attributes['createTicketHeaderTextColor']); ?>;
            }

            .fs_tk_right .fs_view_all_button {
                background-color:  <?php echo esc_attr($attributes['createTicketViewAllButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketViewAllButtonTextColor']); ?>;
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
            }

            .fs_create_button {
                background-color:  <?php echo esc_attr($attributes['createTicketCreateButtonBgColor']); ?>;
                color: <?php echo esc_attr($attributes['createTicketCreateButtonTextColor']); ?>;
            }

            }
        </style>
    <?php }
}
