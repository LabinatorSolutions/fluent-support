<?php

namespace FluentSupport\App\Services\Integrations;

class RCPro{

    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getRcpMembershipInfo'), 30, 2);
    }

    public function getRcpMembershipInfo($widgets, $customer)
    {
        if (!$customer->user_id) return;

        $member = rcp_get_customer_by_user_id($customer->user_id);

        if(empty($member)) return;

        $memberships = $member->get_memberships();

        if(empty($memberships)) return;

        $membershipInfo = [];

        foreach ($memberships as $membership){
            $membershipInfo[]=[
                'name' => esc_html($membership->get_membership_level_name()),
                'status' => esc_html($membership->get_status()),
                'activated_date' => esc_html(date_i18n(get_option('date_format'), strtotime($membership->get_activated_date())))
            ];
        }

        if(empty($membershipInfo)) return;

        ob_start();
        ?>

        <ul>
            <?php foreach ($membershipInfo as $info):?>
                <li title="<?php $info['name']?>">
                    <?php
                    echo '<code>Level:</code> ' . $info['name'] . '<br>';
                    echo '<code>Status:</code> '. ucfirst($info['status']) . '<br>';
                    if ($info['status']==='active'){
                        echo '<code>Activated:</code> '. $info['activated_date'];
                    }
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['rcpro'] = [
            'header'    => __('Restrict Content Pro', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
