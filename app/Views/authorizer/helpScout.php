<?php defined('ABSPATH') or die; ?>
<div class='fs_help_scout_authorization'>
    <p>
        <?php
        /* translators: %s is the Help Scout authorization code */
        printf(esc_html__('Your code is %s', 'fluent-support'), esc_html($code));
        ?>
    </p>
</div>
