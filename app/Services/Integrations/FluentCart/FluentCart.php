<?php

namespace FluentSupport\App\Services\Integrations\FluentCart;
use FluentCart\App\Helpers\Helper as CartHelper;
use FluentCart\App\Models\Customer as CartCustomer;
use FluentCart\App\Models\Order;
use FluentCart\App\Services\URL;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;

class FluentCart
{
    const WIDGET_KEY = 'fluent_cart_purchases';

    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getPurchaseWidgets'), 120, 3);

        if (
            !apply_filters('fluent_support/disable_fc_menu', false) &&
            Helper::getBusinessSettings('ticket_link_portal') === 'fluent_cart'
        ) {
            $this->renderCustomerPortalInFluentCartDashboard();
        }
    }

    public function getPurchaseWidgets($widgets, $customer, $context = null)
    {
        if (!ProfileInfoService::wantsWidget($context, self::WIDGET_KEY)) {
            return $widgets;
        }

        $cartCustomer = $this->findCustomer($customer);

        if (!$cartCustomer) {
            return $widgets;
        }

        // The totals come from the customer record; the list needs only the latest orders.
        $orders = Order::query()->where('customer_id', $cartCustomer->id)
            ->orderBy('id', 'DESC')
            ->with(['order_items', 'subscriptions'])
            ->limit(50)
            ->get();

        if ($orders->isEmpty()) {
            return $widgets;
        }

        if (ProfileInfoService::isMcpWidgetRequest($context)) {
            $widgets[self::WIDGET_KEY] = [
                'title' => __('FluentCart Purchases', 'fluent-support'),
                'mcp'   => $this->getMcpData($orders)
            ];

            return $widgets;
        }

        $widgets[self::WIDGET_KEY] = array_merge(
            ['title' => __('FluentCart Purchases', 'fluent-support')],
            $this->getWidgetData($cartCustomer, $orders)
        );

        return $widgets;
    }

    /**
     * Summary figures and purchased items for the sidebar's purchases card; paid renewals sit under their first order.
     *
     * @param CartCustomer $cartCustomer
     * @param \FluentCart\Framework\Support\Collection $orders Newest first.
     * @return array
     */
    private function getWidgetData($cartCustomer, $orders)
    {
        $stats = [
            [
                'label' => __('Lifetime Value', 'fluent-support'),
                'value' => self::formatAmount($cartCustomer->ltv, $orders->first()->currency),
                'url'   => URL::getDashboardUrl('customers/' . $cartCustomer->id . '/view'),
            ],
            [
                'label' => __('Total Purchases:', 'fluent-support'),
                'value' => (string)(int)$cartCustomer->purchase_count,
            ],
        ];

        if ($cartCustomer->first_purchase_date) {
            $stats[] = [
                'label' => __('Since', 'fluent-support'),
                'value' => self::gmtDateIso($cartCustomer->first_purchase_date),
                'type'  => 'date',
            ];
        }

        if ($cartCustomer->last_purchase_date) {
            $stats[] = [
                'label' => __('Last Purchase', 'fluent-support'),
                'value' => self::gmtDateIso($cartCustomer->last_purchase_date),
                'type'  => 'date',
            ];
        }

        $orderIds = $orders->pluck('id')->toArray();

        // Paid renewals repeat their subscription's first order every term: fold them into it.
        // Unpaid ones (pending, scheduled, failed) stay as their own rows with a badge.
        $renewals = [];
        foreach ($orders as $order) {
            $isPaid = in_array($order->payment_status, ['paid', 'partially_refunded'], true);

            if ($order->type === 'renewal' && $isPaid && in_array($order->parent_id, $orderIds)) {
                if (!isset($renewals[$order->parent_id])) {
                    // Orders are newest first, so the first renewal seen is the latest.
                    $renewals[$order->parent_id] = ['count' => 0, 'latest' => $order, 'orders' => []];
                }
                $renewals[$order->parent_id]['count']++;
                $renewals[$order->parent_id]['orders'][] = $order;
            }
        }

        $items = [];
        foreach ($orders as $order) {
            if ($order->type === 'renewal' && isset($renewals[$order->parent_id]) && in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                continue;
            }

            $subscription = $order->subscriptions->first();
            $renewal = isset($renewals[$order->id]) ? $renewals[$order->id] : null;
            $lastPaidAt = $renewal ? $renewal['latest']->created_at : $order->created_at;

            if ($subscription) {
                $badge = [
                    'text' => (string)$subscription->status,
                    'type' => $subscription->status === 'active' ? 'success' : 'warning',
                ];
            } elseif ($order->total_refund) {
                $badge = ['text' => __('Refunded', 'fluent-support'), 'type' => 'danger'];
            } elseif ($order->payment_status !== 'paid') {
                $badge = ['text' => (string)$order->payment_status, 'type' => 'warning'];
            } else {
                $badge = null;
            }

            $details = [];
            if ($renewal) {
                /* translators: %d: number of renewal payments */
                $details[] = sprintf(_n('%d renewal', '%d renewals', $renewal['count'], 'fluent-support'), $renewal['count']);
            }
            if ($subscription && $subscription->status === 'active' && $subscription->next_billing_date) {
                /* translators: %s: date */
                $details[] = sprintf(__('Next renewal %s', 'fluent-support'), wp_date(get_option('date_format'), strtotime($subscription->next_billing_date . ' UTC')));
            } elseif ($subscription && $subscription->expire_at && strtotime($subscription->expire_at . ' UTC') < time()) {
                /* translators: %s: date */
                $details[] = sprintf(__('Expired %s', 'fluent-support'), wp_date(get_option('date_format'), strtotime($subscription->expire_at . ' UTC')));
            }

            foreach ($order->order_items as $orderItem) {
                if ($orderItem->payment_type == 'signup_fee') {
                    continue;
                }

                $item = $this->purchaseRow($order, $orderItem);
                $item['subtitle_meta'] = self::gmtDateIso($lastPaidAt);
                $item['badge'] = $badge;
                if ($details) {
                    $item['info'] = implode(' · ', $details);
                }

                // This item's line in each paid renewal, newest first
                // Lines without a product (custom or fee lines) have no object_id to match on.
                $related = [];
                foreach ($renewal && $orderItem->object_id ? $renewal['orders'] : [] as $renewalOrder) {
                    foreach ($renewalOrder->order_items as $renewalItem) {
                        if ((int) $renewalItem->object_id === (int) $orderItem->object_id) {
                            $related[] = $this->purchaseRow($renewalOrder, $renewalItem);
                        }
                    }
                }
                if ($related) {
                    $item['related'] = $related;
                }

                $items[] = $item;
            }
        }

        // Latest payment first, so a subscription that just renewed sits at the top.
        usort($items, function ($a, $b) {
            return strcmp((string)$b['subtitle_meta'], (string)$a['subtitle_meta']);
        });

        return [
            'stats_style' => 'summary',
            'stats'    => $stats,
            'sections' => [
                [
                    'label' => __('Recent Purchases', 'fluent-support'),
                    'items' => array_slice($items, 0, 10),
                ],
            ],
        ];
    }

    /**
     * One order line as a purchases row: product, price, variation, date and invoice link.
     *
     * @param object $order
     * @param object $orderItem
     * @return array
     */
    private function purchaseRow($order, $orderItem)
    {
        $url = URL::getDashboardUrl('orders/' . $order->id . '/view');

        return [
            'title'              => (string)$orderItem->post_title,
            'meta'               => self::formatAmount($orderItem->line_total, $order->currency),
            'subtitle'           => $orderItem->title !== $orderItem->post_title ? (string)$orderItem->title : '',
            'subtitle_meta'      => self::gmtDateIso($order->created_at),
            'subtitle_meta_type' => 'date',
            'reference'          => ['text' => '#' . $order->invoice_no, 'url' => $url],
            'url'                => $url,
        ];
    }

    /**
     * Cents to a plain-text amount with the currency sign.
     *
     * @param int $cents
     * @param string $currency
     * @return string
     */
    private static function formatAmount($cents, $currency)
    {
        return html_entity_decode(wp_strip_all_tags((string)CartHelper::toDecimal((int)$cents, true, $currency ?: null)), ENT_QUOTES, 'UTF-8');
    }

    private function findCustomer($customer)
    {
        if (!empty($customer->email)) {
            $cartCustomer = CartCustomer::query()->where('email', $customer->email)->first();
            if ($cartCustomer) {
                return $cartCustomer;
            }
        }

        if (!empty($customer->user_id)) {
            return CartCustomer::query()->where('user_id', $customer->user_id)->first();
        }

        return null;
    }

    /**
     * Plain purchase data for MCP agents. Amounts are in the currency's major unit;
     * dates are ISO 8601 UTC (FluentCart stores GMT).
     *
     * @param \FluentCart\Framework\Support\Collection $orders
     * @return array
     */
    private function getMcpData($orders)
    {
        $mcpOrders = [];
        $activeSubscriptions = 0;

        // The server keeps 50 list items; send the latest 50.
        foreach ($orders->take(50) as $order) {
            $items = [];
            foreach ($order->order_items as $orderItem) {
                if ($orderItem->payment_type == 'signup_fee') {
                    continue;
                }

                $items[] = [
                    'product_id' => (int)$orderItem->post_id,
                    'name'       => (string)$orderItem->post_title,
                    'variation'  => (string)$orderItem->title,
                    'qty'        => (int)$orderItem->quantity,
                    'total'      => $this->toAmount($orderItem->line_total),
                ];
            }

            $subscriptions = [];
            foreach ($order->subscriptions as $subscription) {
                if ($subscription->status === 'active') {
                    $activeSubscriptions++;
                }

                $subscriptions[] = [
                    'id'                => (int)$subscription->id,
                    'name'              => (string)$subscription->item_name,
                    'status'            => (string)$subscription->status,
                    'billing_interval'  => (string)$subscription->billing_interval,
                    'next_billing_date' => self::gmtDateIso($subscription->next_billing_date),
                    'expire_at'         => self::gmtDateIso($subscription->expire_at),
                ];
            }

            $mcpOrders[] = [
                'id'             => (int)$order->id,
                'number'         => (string)$order->invoice_no,
                'status'         => (string)$order->status,
                'payment_status' => (string)$order->payment_status,
                'total'          => $this->toAmount($order->total_amount),
                'paid'           => $this->toAmount($order->total_paid),
                'refunded'       => $this->toAmount($order->total_refund),
                'currency'       => (string)$order->currency,
                'date'           => self::gmtDateIso($order->created_at),
                'url'            => URL::getDashboardUrl('orders/' . $order->id . '/view'),
                'items'          => $items,
                'subscriptions'  => $subscriptions,
            ];
        }

        $data = [
            'total_orders'         => Order::query()->where('customer_id', $orders->first()->customer_id)->count(),
            'active_subscriptions' => $activeSubscriptions,
            'orders'               => $mcpOrders,
        ];

        $licenses = $this->getMcpLicenses($orders->first()->customer_id);
        if ($licenses) {
            $data['licenses'] = $licenses;
        }

        return $data;
    }

    /**
     * FluentCart Pro licenses, without the keys.
     *
     * @param int $customerId
     * @return array
     */
    private function getMcpLicenses($customerId)
    {
        if (!class_exists('\FluentCartPro\App\Modules\Licensing\Models\License')) {
            return [];
        }

        $licenses = \FluentCartPro\App\Modules\Licensing\Models\License::query()
            ->where('customer_id', $customerId)
            ->with(['product', 'variation'])
            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get();

        $formatted = [];
        foreach ($licenses as $license) {
            $formatted[] = [
                'id'               => (int)$license->id,
                'product'          => $license->product ? (string)$license->product->post_title : '',
                'variation'        => $license->variation ? (string)$license->variation->variation_title : '',
                'status'           => (string)$license->status,
                'is_expired'       => $license->isExpired(),
                'expiration_date'  => self::gmtDateIso($license->expiration_date),
                'activation_count' => (int)$license->activation_count,
                'limit'            => $license->limit ? (int)$license->limit : 'unlimited',
                'order_id'         => (int)$license->order_id,
            ];
        }

        return $formatted;
    }

    // FluentCart stores amounts in cents, zero-decimal currencies too (see CartHelper::toDecimal()).
    private function toAmount($cents)
    {
        return round((float)$cents / 100, CartHelper::shopConfig('is_zero_decimal') ? 0 : 2);
    }

    /**
     * A GMT MySQL datetime as ISO 8601 UTC, or null.
     *
     * @param string|\DateTimeInterface|null $date
     * @return string|null
     */
    private static function gmtDateIso($date)
    {
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d H:i:s');
        }

        if (!$date || strpos((string)$date, '0000-00-00') === 0) {
            return null;
        }

        $timestamp = strtotime($date . ' UTC');

        return $timestamp ? gmdate('c', $timestamp) : null;
    }

    private function renderCustomerPortalInFluentCartDashboard()
    {
        if (!function_exists('fluent_cart_api') || Helper::getBusinessSettings('ticket_link_portal') !== 'fluent_cart' ) {
            return;
        }

        fluent_cart_api()->addCustomerDashboardEndpoint('fluent-support', [
            'title'           => __('Support', 'fluent-support'),
            'render_callback' => function () {
                echo do_shortcode('[fluent_support_portal]');
            },
            'priority'        => 'high'
        ]);
    }
}
