<?php

namespace FluentSupport\App\Services\Integrations;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;

/**
 * Weekly, opt-in usage counts, sent only while the admin has "Share Essentials" on.
 *
 * One request a week to `/stats`. It carries the counts below and, when it is due, the site
 * environment that used to go to `/plugin-maintenance` on its own (the `maintenance` block).
 * The old endpoint lived on `api.wpmanageninja.com`, which no longer runs a site: the posts had
 * been answered 404 for a long time, so this data never arrived.
 *
 * The counts are numbers only, never ticket content or customer data.
 */
class UsageStats
{
    const ENDPOINT = 'https://fluentapi.wpmanageninja.com/stats';

    const LAST_SENT_OPTION = '_fluent_support_usage_stats_last_sent';

    /**
     * Shared by our plugins, so two of them on one site do not both send the environment block
     * in the same week. Fluent Support stamped it for `/plugin-maintenance` before.
     */
    const MAINTENANCE_OPTION = '_fluent_last_m_run';

    // 6 days, not 7: WP-Cron drifts, and the server keeps one row per site per week anyway.
    const INTERVAL = 518400;

    public function maybeSend($force = false)
    {
        if (!$this->isAllowed()) {
            return false;
        }

        $lastSent = (int)get_option(self::LAST_SENT_OPTION, 0);

        if (!$force && $lastSent && (time() - $lastSent) < self::INTERVAL) {
            return false;
        }

        $metrics = $this->getMetrics();

        if (!$metrics) {
            return false;
        }

        $body = [
            'product'         => 'fluent-support',
            'product_version' => FLUENT_SUPPORT_VERSION,
            // The same value Fluent Support Pro's license calls send, so both land on one site row.
            'site_url'        => home_url('/'),
            'metrics'         => $metrics,
        ];

        $maintenanceDue = $this->maintenanceDue();

        if ($maintenanceDue) {
            $body['maintenance'] = $this->getMaintenanceData();
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'cookies' => []
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int)wp_remote_retrieve_response_code($response);

        if ($code >= 500) {
            // Our side is down. Try again on the next weekly run, which WP-Cron gives us anyway.
            return false;
        }

        // A 4xx is a refused report (malformed or unknown product): sending it again would be
        // refused the same way, so the clock moves on as it does after a success.
        update_option(self::LAST_SENT_OPTION, time(), false);

        if ($maintenanceDue && $code >= 200 && $code < 300) {
            update_option(self::MAINTENANCE_OPTION, time(), false);
        }

        return $code >= 200 && $code < 300;
    }

    /**
     * Counts only. A count that fails is left out instead of failing the whole report.
     */
    public function getMetrics()
    {
        $monthAgo = gmdate('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS);

        $collectors = [
            'tickets'         => function () {
                return Ticket::count();
            },
            'tickets_open'    => function () {
                return Ticket::where('status', '!=', 'closed')->count();
            },
            // fs_tickets.created_at is indexed (idx_created_at).
            'tickets_30d'     => function () use ($monthAgo) {
                return Ticket::where('created_at', '>=', $monthAgo)->count();
            },
            'agents'          => function () {
                return Agent::count();
            },
            'customers'       => function () {
                return Customer::count();
            },
            'mailboxes'       => function () {
                return MailBox::count();
            },
            'mailboxes_email' => function () {
                return MailBox::where('box_type', 'email')->count();
            },
            'products'        => function () {
                return Product::count();
            },
            'has_pro'         => function () {
                return defined('FLUENTSUPPORTPRO');
            },
        ];

        /**
         * Let add-ons (e.g. Fluent Support Pro) add their own counts.
         * Keys: snake_case. Values: int|float|bool|short string|list of short strings.
         * Never anything that identifies a person.
         */
        $collectors = apply_filters('fluent_support/usage_stats_collectors', $collectors);

        $metrics = [];

        foreach ($collectors as $key => $collector) {
            try {
                $metrics[$key] = is_callable($collector) ? $collector() : $collector;
            } catch (\Throwable $e) {
                // Skip this one; never break the weekly cron over a statistic.
            }
        }

        return $metrics;
    }

    /**
     * The environment block. Same fields `/plugin-maintenance` took.
     */
    private function getMaintenanceData()
    {
        global $wp_version;

        return [
            'plugin_version' => FLUENT_SUPPORT_VERSION,
            'php_version'    => (defined('PHP_VERSION')) ? PHP_VERSION : phpversion(),
            'wp_version'     => $wp_version,
            'plugins'        => (array)get_option('active_plugins'),
            'site_lang'      => get_bloginfo('language'),
            'site_url'       => home_url('/'),
            'theme'          => wp_get_theme()->get('Name'),
            // Listed in the "Share Essentials" disclosure ("Your name and email address").
            'admin_email'    => get_bloginfo('admin_email'),
            'site_title'     => get_bloginfo('name')
        ];
    }

    private function maintenanceDue()
    {
        $prev = (int)get_option(self::MAINTENANCE_OPTION, 0);

        return !$prev || (time() - $prev) > self::INTERVAL;
    }

    private function isAllowed()
    {
        // The same switch and filter the old Maintenance class used. One consent, one place.
        return apply_filters('fluentsupport_allow_share_essential', Helper::getOption('_share_essential', 'no') == 'yes');
    }
}
