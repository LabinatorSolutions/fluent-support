<?php

namespace FluentSupport\App\Services\Integrations;

class PMPro
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getPmMembershipInfo'), 30, 2);
    }

    public function getPmMembershipInfo($widgets, $customer)
    {
        if (!$customer->user_id) return;

        $levels = pmpro_getMembershipLevelsForUser($customer->user_id);

        if(empty($levels)) return;

        $membershipInfo = [];

        foreach ($levels as $level){
            $membershipInfo[] = [
                'name'       => esc_html($level->name),
                'startdate'  => esc_html(date_i18n(get_option('date_format'), strtotime($level->startdate))),
                'enddate'    => $level->enddate!=null ? esc_html(date_i18n(get_option('date_format'), strtotime($level->enddate))) : ''
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
                    echo '<code>Membership Start:</code> '. $info['startdate'] . '<br>';
                    if($info['enddate']){
                        echo '<code>Membership End:</code> '. $info['enddate'];
                    }
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['pmpro'] = [
            'header'    => __('Paid Membership Pro', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
