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

        // Button colors still at their block defaults are light-mode values. Print those only
        // outside html.dark so the portal's dark tokens take over; colors the site owner picked
        // apply in both modes. :where() keeps both selectors at the same specificity.
        $customPrimary = self::isCustomized(['primaryButtonBgColor', 'primaryButtonTextColor']);
        $customSecondary = self::isCustomized(['secondaryButtonBgColor', 'secondaryButtonTextColor']);
        ?>
        <style type="text/css">
            #fluent_support_client_app {
                .fs_tickets_container,
                .fs_ticket_form_container {
                    border-radius: <?php echo esc_attr($attributes['containerBorderRadius'] ?? 16); ?>px;
                }

                .fs_client_portal
                .fs_create_ticket_container .el-input__wrapper,
                .fs_create_ticket_container .el-select__wrapper,
                .fs_custom_fields_wrap .el-select__wrapper {
                    border-radius: <?php echo esc_attr($attributes['ticketInputBorderRadius'] ?? 10); ?>px;
                }

                .fs_client_portal .fs_ticket_threads_container .fs_ticket_thread .fs_ticket_thread_content .fs_ticket_avatar {
                    border-radius: <?php echo esc_attr($attributes['avatarBorderRadius'] ?? '50%'); ?>;
                }
            }

            <?php echo $customPrimary ? '' : ':where(html:not(.dark))'; ?> #fluent_support_client_app {
                .fs_create_ticket_btn,
                .fs_create_ticket_button,
                .fs_reply_btn {
                    background-color: <?php echo esc_attr($attributes['primaryButtonBgColor'] ?? 'rgba(14, 18, 27, 1)'); ?>;
                    color: <?php echo esc_attr($attributes['primaryButtonTextColor'] ?? 'rgba(255, 255, 255, 1)'); ?>;
                }
            }

            <?php echo $customSecondary ? '' : ':where(html:not(.dark))'; ?> #fluent_support_client_app {
                .fs_ticket_refresh_btn,
                .fs_upload_button {
                    background-color: <?php echo esc_attr($attributes['secondaryButtonBgColor'] ?? 'rgba(255, 255, 255, 1)'); ?>;
                    color: <?php echo esc_attr($attributes['secondaryButtonTextColor'] ?? '#18181B'); ?>;
                }

                .fs_ticket_refresh_btn span,
                .fs_upload_button span {
                    color: inherit !important;
                }

                .fs_close_ticket {
                    background-color: <?php echo esc_attr($attributes['secondaryButtonBgColor'] ?? '#F5F7FA'); ?>;
                    color: <?php echo esc_attr($attributes['secondaryButtonTextColor'] ?? '#525866'); ?>;
                    opacity: 0.8;
                    filter: brightness(0.8);
                }
            }
        </style>
    <?php }

    /**
     * Whether any of the given block attributes differs from its registered default.
     *
     * WordPress merges the defaults into the attributes before render, so an unset
     * attribute and one left at its default look the same here.
     *
     * @param string[] $keys Attribute names.
     * @return bool
     */
    private static function isCustomized($keys)
    {
        $registered = BlockAttributes::CustomerPortalAttributes();

        foreach ($keys as $key) {
            if (!isset(self::$attributes[$key]) || self::$attributes[$key] === '') {
                continue;
            }

            $default = $registered[$key]['default'] ?? null;
            if (self::normalizeColor(self::$attributes[$key]) !== self::normalizeColor($default)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeColor($color)
    {
        return strtolower(str_replace(' ', '', (string) $color));
    }
}
