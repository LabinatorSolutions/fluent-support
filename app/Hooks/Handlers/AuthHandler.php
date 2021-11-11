<?php

use FluentSupport\App\App;

add_shortcode('fluent_support_login', function () {
    $app = App::getInstance();
    $assets = $app['url.assets'];
    wp_enqueue_style('fluent_support_login_style', $assets.'admin/css/all_public.css');
    wp_enqueue_script('fluent_support_login_helper', $assets.'portal/js/login_helper.js');
    $return = '<div class="fst_login_wrapper">';
    $return .= wp_login_form([
        'echo'           => false,
        'redirect'       => get_permalink(get_the_ID()),
        'remember'       => true,
        'value_remember' => true
    ]);
    $return .= '</div>';
    return $return;
});

add_shortcode('fluent_support_signup', 'fsRegistrationForm');

function fsRegistrationForm()
{
    $app = App::getInstance();
    $assets = $app['url.assets'];
    wp_enqueue_style('fluent_support_login_style', $assets.'admin/css/all_public.css');

    $registrationForm = '<div class="fst_registration_wrapper">
        <form class="fs_registration_form" method="post" name="fs_registration_form">
            <label for="fsr_first_name">First Name</label>
            <input type="text" id="fsr_first_name" name="fsr_first_name">
            <label for="fsr_last_name">Last Name</label>
            <input type="text" id="fsr_last_name" name="fsr_last_name">
            <label for="fsr_username">Username</label>
            <input type="text" required id="fsr_username" name="fsr_username" />
            <label for="fsr_email">Email</label>
            <input id="fsr_email" required type="email" name="fsr_email" />
            <label for="fsr_pass">Password</label>
            <input type="password" required id="fsr_pass" name="fsr_pass" />
            <input type="submit" class="fsr_submit_button" value="Sign Up" />
        </form>
    </div>';

    return $registrationForm;
}

add_action('init','fsCreateUserAccount');
function fsCreateUserAccount(){
    $user = [
        'first_name' => ( isset($_POST['fsr_first_name']) ? $_POST['fsr_first_name'] : '' ),
        'last_name' => ( isset($_POST['fsr_last_name']) ? $_POST['fsr_last_name'] : '' ),
        'user_login' => (isset($_POST['fsr_username']) ? $_POST['fsr_username'] : ''),
        'user_email' => ( isset($_POST['fsr_email']) ? $_POST['fsr_email'] : '' ),
        'user_pass' => ( isset($_POST['fsr_pass']) ? $_POST['fsr_pass'] : '' )
    ];

    if(!empty($user['user_login'] || $user['user_email'] || $user['user_pass'])){
        if ( !username_exists( $user['user_login'] )  && !email_exists( $user['user_email'] ) ) {
            $user_id = wp_insert_user( $user );
            if( !is_wp_error($user_id) ) {
                wp_redirect( '/');
                exit;
            } else {

            }
        }
    }
}
