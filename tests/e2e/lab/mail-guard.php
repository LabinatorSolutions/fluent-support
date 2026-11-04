<?php
/**
 * Plugin Name: Fluent Support E2E mail guard
 * Description: Installed by tests/e2e for a run and removed after it. Every
 * wp_mail() is stored in option fs_e2e_mail instead of being sent.
 */
add_filter('pre_wp_mail', function ($short, $atts) {
    $sent = get_option('fs_e2e_mail', []);
    $sent[] = ['to' => $atts['to'], 'subject' => $atts['subject']];
    update_option('fs_e2e_mail', $sent, 'no');
    return true;
}, 1, 2);
