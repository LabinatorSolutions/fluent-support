<?php

namespace FluentSupport\App\Services\Integrations\FluentCart;
use FluentCart\App\Models\Order;

class FluentCart
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getFluentCartPurchaseWidgets'), 120, 2);
    }

    public function getFluentCartPurchaseWidgets($widgets, $customer)
    {
        $wpUserId = $customer->user_id;

        $formattedOrders = Order::whereHas('customer', function($query) use ($wpUserId) {
            $query->where('user_id', $wpUserId);
        })
            ->with([
                'shipping_address',
                'billing_address',
                'order_items'
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        ob_start();
        ?>
        <ul>
            <?php foreach ($formattedOrders as $orderKey => $orderValue): ?>
                <li title="<?php echo __('Purchase Date: ', 'fluent-support') . $orderValue['created_at'] ?>">
                    <?php
                    echo $orderValue['order_items'][0]['post_title'] . ' <code>' . $orderValue['status'] . '</code>';
                    ?>
                    - <?php echo get_woocommerce_currency_symbol().$orderValue['total']; ?>
                    <a target="_blank" rel="nofollow"
                       href="<?php echo admin_url('post.php?post=' . $orderValue['id'] . '&action=edit'); ?>"> <i class="dashicons dashicons-visibility"></i> </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['fct_purchases'] = [
            'header'    => __('Fluent Cart Purchases', 'fluent-support-pro'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
