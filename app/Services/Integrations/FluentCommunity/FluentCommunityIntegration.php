<?php

namespace FluentSupport\App\Services\Integrations\FluentCommunity;

/**
 * Renders the Fluent Support portal inside the FluentCommunity portal
 * when the portal destination is set to fluent_community.
 */
class FluentCommunityIntegration
{
    public function boot()
    {
        if (!$this->shouldUseCommunityPortal()) {
            return;
        }

        // Tell FC 'support' is a known route so it doesn't redirect to all_feeds.
        add_filter('fluent_community/app_route_paths', [$this, 'registerSupportRoute']);

        add_action('fluent_community/rendering_path_ssr_support', [$this, 'renderSupportPortal']);

        // FC's Vue router has no /support route and would fall through to NotFound.
        // Turn SPA navigation to /support into a full page load so the SSR hook runs.
        // Needed on every portal page, not only the support route.
        add_action('fluent_community/portal_footer', [$this, 'outputRouterGuard'], 999);
    }

    public function registerSupportRoute($paths)
    {
        $paths[] = 'support';
        return $paths;
    }

    public function renderSupportPortal($parts = [])
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->getCommunityAuthUrl());
            exit;
        }

        (new PortalRenderer())->render();
    }

    public function outputRouterGuard()
    {
        $supportUrl = \FluentCommunity\App\Services\Helper::baseUrl('support/');
        ?>
        <script>
        (function () {
            var supportUrl = <?php echo wp_json_encode(esc_url($supportUrl)); ?>;

            function addRouterGuard() {
                var router = window.fluentFrameworkAppRouter;
                if (!router || typeof router.beforeEach !== 'function') {
                    return;
                }
                router.beforeEach(function (to, from, next) {
                    if (to.path === '/support' || to.path.indexOf('/support/') === 0) {
                        window.location.href = supportUrl;
                        return false;
                    }
                    next();
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addRouterGuard);
            } else {
                addRouterGuard();
            }
        })();
        </script>
        <?php
    }

    private function getCommunityAuthUrl()
    {
        $supportUrl = \FluentCommunity\App\Services\Helper::baseUrl('support/');
        $authUrl    = \FluentCommunity\App\Services\Helper::getAuthUrl();

        if (!$authUrl) {
            $authUrl = add_query_arg('fcom_action', 'auth', \FluentCommunity\App\Services\Helper::baseUrl(''));
        }

        return add_query_arg('redirect_to', $supportUrl, $authUrl);
    }

    private function shouldUseCommunityPortal()
    {
        return \FluentSupport\App\Services\Helper::getBusinessSettings('ticket_link_portal') === 'fluent_community';
    }
}
