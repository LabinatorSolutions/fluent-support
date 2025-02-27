<?php

namespace FluentSupport\App\Services\Blocks;

class BlockHelper
{
    // Store the attributes provided for styling
    private static $attributes = [];

    /**
     * Generate and output inline CSS styles based on the provided attributes.
     *
     * This method processes the provided attributes and dynamically generates CSS styles for various components
     * of customer portal pages, such as the Tickets List, View Ticket, and Create Ticket sections.
     *
     * @param array $attributes An associative array of attributes used for styling.
     */
    public static function processAttributesAndPrepareStyle($attributes)
    {
        self::$attributes = $attributes;
        ?>
        <style type="text/css">
            #fluent_support_client_app {
                .fs_tickets_container {
                    border-radius: <?php echo isset($attributes['allTicketsBorderRadius']) ? $attributes['allTicketsBorderRadius'] : 16; ?>px;
                }

                .fs_ticket_wrapper .fs_tickets_container .fs_tickets_header .fs_create_ticket_btn {
                    border-radius: <?php echo isset($attributes['createTicketButtonBorderRadius']) ? $attributes['createTicketButtonBorderRadius'] : 8; ?>px;
                    background-color: <?php echo isset($attributes['createTicketButtonBackgroundColor']) ? $attributes['createTicketButtonBackgroundColor'] : '#409eff'; ?>;
                    color: <?php echo isset($attributes['createTicketButtonTextColor']) ? $attributes['createTicketButtonTextColor'] : '#ffffff'; ?>;
                }

                .fs_button_groups,
                .fs_product_filter .el-select__wrapper,
                .fs_sorting .el-button,
                .fs_search_filter .el-input__wrapper {
                    border-radius: <?php echo isset($attributes['allTicketsFilterBorderRadius']) ? $attributes['allTicketsFilterBorderRadius'] : 8; ?>px;
                }

                .fs_tickets_table .el-table__header thead th:first-child,
                .fs_tickets_table .el-table__header thead th:last-child {
                    border-radius: <?php echo isset($attributes['allTicketsTableHeaderBorderRadius']) ? $attributes['allTicketsTableHeaderBorderRadius'] : 8; ?>px;
                }

                .fs_pagination_container .el-select__wrapper,
                .fs_pagination_container .el-pagination .el-pager li {
                    border-radius: <?php echo isset($attributes['allTicketsFooterButtonBorderRadius']) ? $attributes['allTicketsFooterButtonBorderRadius'] : 8; ?>px;
                }

            }

        </style>
    <?php }
}
