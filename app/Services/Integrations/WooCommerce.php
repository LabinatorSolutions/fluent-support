<?php

namespace FluentSupport\App\Services\Integrations;

use FluentSupport\App\Services\Helper;

class WooCommerce
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getWooPurchaseWidgets'), 30, 2);
    }

    public function getWooPurchaseWidgets($widgets, $customer)
    {
        $app = Helper::FluentSupport();
        $page = intval($app->request->get('page', 1));
        $per_page = intval($app->request->get('per_page', 10));

        $customer_orders = wc_get_orders([
            'billing_email' => $customer->email,
            'limit'         => $per_page,
            'offset'        => $per_page * ($page - 1),
            'order'         => 'DESC',
            'paginate'      => true
        ]);

        $formattedOrders = [];
        $lastOrder = false;

        $customerQuery = wpFluent()->table('wc_customer_lookup')
            ->where('email', $customer->email);

        if($customer->user_id) {
            $customerQuery = $customerQuery->orWhere('user_id', $customer->user_id);
        }

        $customer = $customerQuery->first();


        $orderStats = wpFluent()->table('wc_order_stats')
            ->where('customer_id', $customer->customer_id)
            ->get();

        foreach ($orderStats as $order) {
            $orderIds[] = $order->order_id;
        }

        if(is_array($orderIds)){
            $orderIds = array_unique($orderIds);
        }
        $orderIds = $orderIds;

        $orderedProducts = wpFluent()->table('wc_order_product_lookup')
            ->select([
                'posts.ID', 'posts.post_title', 'posts.guid'
            ])
            ->join('posts', 'wc_order_product_lookup.product_id', '=', 'posts.ID')
            ->whereIn('order_id', $orderIds)
            ->groupBy('posts.ID')
            ->get();

        if(!$customer || !$orderStats || !$orderedProducts){
            return $widgets;
        }

        foreach ($customer_orders->orders as $order) {
            $item_count = $order->get_item_count() - $order->get_item_count_refunded();

            foreach ($orderedProducts as $product){
                $formattedOrders[] = [
                    'order'         => '#' . $order->get_order_number(),
                    'date'          => esc_html(wc_format_datetime($order->get_date_created())),
                    'status'        => esc_html($order->get_status()),
                    'total'         => esc_html($order->total),
                    'item_count'    => esc_html($item_count),
                    'product_name'  => esc_html($product->post_title),
                    'product_url'   => esc_html($product->guid)
                ];
            }

            if (!$lastOrder) {
                $lastOrder = $order;
            }
        }

        ob_start();
        ?>
        <ul>
            <?php foreach ($formattedOrders as $orderKey=>$orderValue):?>
                <li title="Purchase Date: <?php echo $orderValue['date'] ?>">
                        <?php
                        echo $orderValue['product_name'] . ' <code>' . $orderValue['status'] . '</code>';
                        ?>
                    - $<?php echo $orderValue['total']; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['woo_purchases'] = [
            'header'    => __('WooCommerce Purchases', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
