<?php

namespace FluentSupport\App\Services\Integrations;

class WishListMember{

    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getWLMembershipInfo'), 30, 2);
    }

    public function getWLMembershipInfo($widgets, $customer)
    {
        if(!$customer->user_id) return;

        $levels = wlmapi_get_member_levels($customer->user_id);

        if(empty($levels)) return;
        $membershipInfo = [];
        foreach($levels as $level) {
            $membershipInfo[] = [
                'level' => esc_html($level->Name),
                'status'=> esc_html($level->Status[0])
            ];
        }

        if(empty($membershipInfo)) return;

        ob_start();
        ?>

        <ul>
            <?php foreach ($membershipInfo as $info):?>
                <li title="<?php $info['level']?>">
                    <?php
                    echo '<code>Level:</code> ' . $info['level'] . '<br>';
                    echo '<code>Status:</code> '. $info['status'] . '<br>';
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['wlm'] = [
            'header'    => __('WishList Member', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
