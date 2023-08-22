<?php

namespace FluentSupport\App\Hooks\Handlers;

class ShortcodeHandler
{
    public function fluentSupportPortal($args)
    {
        $args = shortcode_atts( array(
            'show_logout' => 'no',
            'business_box_id' => null,
        ), $args );
    
        if($args['show_logout'] == 'yes') {
            add_filter('fluent_support/customer_portal_vars', function ($vars) {
    
                $vars['show_logout'] = true;
                return $vars;
            });
        }
    
        if($args['business_box_id']) {
            add_filter('fluent_support/customer_portal_vars', function ($vars) use($args) {
                $vars['mailbox_id'] = wp_strip_all_tags($args['business_box_id']);
                return $vars;
            });
        }
    
        return (new CustomerPortalHandler)->renderPortal();
    }
}