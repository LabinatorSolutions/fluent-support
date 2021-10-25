<?php

namespace FluentSupport\App\Services\Integrations;

class BuddyBoss
{
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getBbInfo'), 30, 2);
    }

    public function getBbInfo($widgets, $customer)
    {

        $groupIds = groups_get_user_groups($customer->user_id);

        if(empty($groupIds)) return;

        $groupInfos = [];

        foreach( $groupIds["groups"] as $id ) {
            $group = groups_get_group( array( 'group_id' => $id) );
            $groupInfos[] = [
                'name'          => esc_html($group->name),
                'member_from'   => esc_html(date_i18n(get_option('date_format'), strtotime($group->date_created)))
            ];
        }
        if (empty($groupInfos)) return;

        ob_start();
        ?>

        <ul>
            <?php foreach ($groupInfos as $group):?>
                <li title="<?php $group['name']?>">
                    <?php
                    echo '<code>Name: </code>' . $group['name'] . '<br>';
                    echo '<code>Member From:  </code>'. $group['member_from'];
                    echo bp_get_group_name($group);
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['bb_groups'] = [
            'header'    => __('BuddyBoss Groups', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
