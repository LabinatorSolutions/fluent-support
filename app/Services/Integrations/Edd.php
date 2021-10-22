<?php

namespace FluentSupport\App\Services\Integrations;

class Edd
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getPurchaseWidgets'), 30, 2);
    }

    public function getPurchaseWidgets($widgets, $customer)
    {
        $by = 'email';
        $value = $customer->email;
        if ($customer->user_id) {
            $by = 'ID';
            $value = $customer->user_id;
        }

        $user = get_user_by($by, $value);

        if (!$user) {
            return $widgets;
        }

        $payments = edd_get_payments([
            'user'   => $user->ID,
            'output' => 'payments'
        ]);

        if (!$payments) {
            return $widgets;
        }


        ob_start();
        ?>
        <ul>
            <?php foreach ($payments as $payment): ?>
                <li title="Purchase Date: <?php echo $payment->completed_date; ?>">
                    <?php foreach ($payment->cart_details as $cart_detail): ?>
                        <?php
                        echo $cart_detail['name'] . ' <code>' . $payment->status_nicename . '</code>';
                        ?>
                    <?php endforeach; ?>
                    - $<?php echo $payment->total; ?>
                    <a target="_blank" rel="nofollow" href="<?php echo admin_url('edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id='.$payment->ID); ?>"><i class="el-icon-view"></i></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();
        $widgets['edd_purchases'] = [
            'header'    => __('EDD Purchases', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
