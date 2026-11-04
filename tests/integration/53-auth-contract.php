<?php
/**
 * The portal's own auth endpoints (signup, login, two_fa, reset_pass): status,
 * response keys and message of every branch. The login script
 * (resources/customer_portal/login_helper.js) reads these.
 *
 * Recorded over HTTP against the old AuthController before the Auth module
 * replaced it; every branch matched except the two wrong-password messages,
 * which now equal the unknown-account message so the form can't tell which
 * accounts exist.
 *
 * Runs logged out with our own forms (not FluentAuth's). FluentAuth's own
 * login limiter is turned off for the run, since it counts failures per IP
 * across runs. Mail is not sent; the emailed codes are read from the mail filter.
 * The users this creates carry the fixture marker and are removed in the finally.
 */

use FluentSupport\App\Services\Helper;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();
    $email = strtolower($marker) . '-auth@example.invalid';
    $password = 'Passw0rd!Passw0rd';

    $lastCode = null;
    $catchCode = function ($body, $code) use (&$lastCode) {
        $lastCode = (string) $code;
        return $body;
    };
    $noMail = '__return_true';

    $originalRegistration = get_option('users_can_register');
    $originalBusiness = Helper::getOption('global_business_settings', []);

    // FluentAuth caches its settings; turn its per-IP login limit off for the run.
    $fluentAuthLimit = null;
    $fluentAuthHelper = '\FluentAuth\App\Helpers\Helper';
    if (class_exists($fluentAuthHelper) && property_exists($fluentAuthHelper, 'authSettings')) {
        $fluentAuthLimit = new ReflectionProperty($fluentAuthHelper, 'authSettings');
        $fluentAuthLimit->setAccessible(true);
        $fluentAuthHelper::getAuthSettings();
        $settings = (array) $fluentAuthLimit->getValue();
        $originalFluentAuth = $settings;
        $settings['login_try_limit'] = 0;
        $fluentAuthLimit->setValue(null, $settings);
    }

    $setTwoFa = function ($on) use ($originalBusiness) {
        Helper::updateOption('global_business_settings', array_merge((array) $originalBusiness, ['enable_two_fa' => $on ? 'yes' : 'no']));
    };

    $clearLimits = function () {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_fs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_fs\\_%'");
        wp_cache_flush();
    };

    // [status, sorted keys, message] of one call, made logged out.
    $call = function ($route, array $params) {
        wp_set_current_user(0);
        $result = FsTest::rest('POST', $route, $params);
        $data = is_array($result['data']) ? $result['data'] : [];
        $keys = array_keys($data);
        sort($keys);
        return [(int) $result['status'], $keys, (string) ($data['message'] ?? ''), $data];
    };

    $expect = function ($label, $actual, $status, array $keys, $message = null) {
        FsTest::assertSame($status, $actual[0], $label . ': status');
        FsTest::assertSame($keys, $actual[1], $label . ': keys');
        if ($message !== null) {
            FsTest::assertSame($message, $actual[2], $label . ': message');
        }
    };

    $hiddenValue = function ($html, $name) {
        return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', (string) $html, $m) ? html_entity_decode($m[1]) : '';
    };

    add_filter('fluent_support/use_fluent_auth_forms', '__return_false');
    add_filter('fluent_support/signup_verification_email_body', $catchCode, 99, 2);
    add_filter('pre_wp_mail', $noMail);

    try {
        if (Helper::getAuthProvider() !== 'fluent_support') {
            FsTest::case('auth contract', function () {
                FsTest::skip('FluentAuth forms setting is on in the lab');
            });
            return;
        }

        update_option('users_can_register', 1);
        $setTwoFa(false);
        $clearLimits();

        // The calls run logged out, so the nonces must be made for the logged-out user.
        wp_set_current_user(0);
        $signupNonce = wp_create_nonce('fluent_support_signup_nonce');
        $loginNonce = wp_create_nonce('fsupport_login_nonce');
        $resetNonce = wp_create_nonce('fluent_support_reset_pass_nonce');
        $signup = ['_fsupport_signup_nonce' => $signupNonce, 'first_name' => 'Cap', 'username' => $marker . 'auth', 'email' => $email, 'password' => $password];

        FsTest::case('auth_signup_contract', function () use ($call, $expect, $hiddenValue, $signup, $email, &$lastCode, $marker) {
            $expect('bad nonce', $call('/signup', ['_fsupport_signup_nonce' => 'nope', 'email' => $email]), 422, ['message'], 'Security verification failed. Please try again');
            $expect('invalid fields', $call('/signup', ['_fsupport_signup_nonce' => $signup['_fsupport_signup_nonce'], 'email' => 'not-an-email']), 422, ['email', 'first_name', 'password', 'username']);

            $step1 = $call('/signup', $signup);
            $expect('step 1', $step1, 200, ['verification_html']);
            $hash = $hiddenValue($step1[3]['verification_html'] ?? '', '_email_verification_hash');
            FsTest::assert($hash !== '' && $lastCode, 'a code was issued and emailed');

            $expect('wrong code', $call('/signup', $signup + ['_email_verification_hash' => $hash, '_email_verification_token' => '000000']), 422, ['message'], 'Please provide a valid verification code that was sent to your email address');
            $expect('unknown hash', $call('/signup', $signup + ['_email_verification_hash' => 'nohash', '_email_verification_token' => '123456']), 422, ['message'], 'Please provide a valid verification code that was sent to your email address');
            $expect('right code', $call('/signup', $signup + ['_email_verification_hash' => $hash, '_email_verification_token' => $lastCode]), 200, ['message', 'redirect'], 'Successfully registered to the site.');
            $expect('code reused', $call('/signup', $signup + ['_email_verification_hash' => $hash, '_email_verification_token' => $lastCode]), 422, ['message'], 'Your verification code has already been used. Please try again');

            FsTest::assert((bool) get_user_by('email', $email), 'the account exists');
        });

        FsTest::case('auth_signup_gate_and_rate_limit_contract', function () use ($call, $expect, $signup, $clearLimits, $marker) {
            update_option('users_can_register', 0);
            $expect('registration off', $call('/signup', ['username' => $marker . 'b', 'email' => 'b' . $signup['email']] + $signup), 403, ['message'], 'User registration is not enabled on this site.');
            update_option('users_can_register', 1);

            $clearLimits();
            $again = ['username' => $marker . 'r', 'email' => 'r' . $signup['email']] + $signup;
            for ($i = 0; $i < 5; $i++) {
                $call('/signup', $again);
            }
            $expect('rate limited', $call('/signup', $again), 429, ['message'], 'Too many verification code requests. Please try again later.');
            $clearLimits();
        });

        FsTest::case('auth_login_contract', function () use ($call, $expect, $loginNonce, $email, $password, $clearLimits) {
            $expect('bad nonce', $call('/login', ['_support_login_nonce' => 'nope', 'log' => $email, 'pwd' => 'x']), 403, ['message'], 'Security verification failed');
            $expect('missing password', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email]), 403, ['message'], 'Email and Password is required');
            $expect('unknown account', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => 'nobody-' . $email, 'pwd' => 'x']), 403, ['message'], 'Email or Password is not valid. Please try again');
            $expect('wrong password', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => 'wrong']), 403, ['message'], 'Email or Password is not valid. Please try again');
            $expect('right password', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => $password]), 200, ['redirect']);

            $clearLimits();
            for ($i = 0; $i < 5; $i++) {
                $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => 'wrong']);
            }
            $expect('rate limited', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => $password]), 429, ['message'], 'Too many login attempts. Please try again after 15 minutes.');
            $clearLimits();
        });

        FsTest::case('auth_login_code_contract', function () use ($call, $expect, $hiddenValue, $setTwoFa, $loginNonce, $email, $password, &$lastCode) {
            $setTwoFa(true);
            try {
                $expect('2FA wrong password', $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => 'wrong']), 403, ['message'], 'Email or Password is not valid. Please try again');

                $prompt = $call('/login', ['_support_login_nonce' => $loginNonce, 'log' => $email, 'pwd' => $password]);
                $expect('2FA prompt', $prompt, 200, ['load_2fa', 'two_fa_form']);
                $hash = $hiddenValue($prompt[3]['two_fa_form'] ?? '', 'login_hash');
                FsTest::assert($hash !== '' && $lastCode, 'a login code was issued and emailed');

                $expect('missing code', $call('/two_fa', ['login_hash' => $hash]), 423, ['message'], 'Please provide a valid login code');
                $expect('wrong code', $call('/two_fa', ['login_hash' => $hash, 'login_passcode' => '000000']), 423, ['message'], 'Invalid verification code');
                $expect('right code', $call('/two_fa', ['login_hash' => $hash, 'login_passcode' => $lastCode]), 200, ['redirect']);
                $expect('code reused', $call('/two_fa', ['login_hash' => $hash, 'login_passcode' => $lastCode]), 423, ['message'], 'Sorry, your login code has been expired. Please try to login again');
                $expect('unknown hash', $call('/two_fa', ['login_hash' => 'nohash', 'login_passcode' => '123456']), 423, ['message'], 'Your provided code or url is not valid');
            } finally {
                $setTwoFa(false);
            }
        });

        FsTest::case('auth_reset_password_contract', function () use ($call, $expect, $resetNonce, $email, $clearLimits) {
            $sent = 'If an account matches that username or email, a password reset link has been sent. Please check your inbox, including the spam folder.';

            $expect('bad nonce', $call('/reset_pass', ['_fsupport_reset_pass_nonce' => 'nope', 'user_login' => $email]), 422, ['message'], 'Security verification failed. Please try again');
            $expect('empty', $call('/reset_pass', ['_fsupport_reset_pass_nonce' => $resetNonce, 'user_login' => '']), 422, ['message'], 'Username or email is required.');
            $expect('unknown account', $call('/reset_pass', ['_fsupport_reset_pass_nonce' => $resetNonce, 'user_login' => 'nobody-' . $email]), 200, ['message'], $sent);
            $expect('known account', $call('/reset_pass', ['_fsupport_reset_pass_nonce' => $resetNonce, 'user_login' => $email]), 200, ['message'], $sent);
            $expect('within cooldown', $call('/reset_pass', ['_fsupport_reset_pass_nonce' => $resetNonce, 'user_login' => $email]), 200, ['message'], $sent);
            $clearLimits();
        });
    } finally {
        remove_filter('fluent_support/use_fluent_auth_forms', '__return_false');
        remove_filter('fluent_support/signup_verification_email_body', $catchCode, 99);
        remove_filter('pre_wp_mail', $noMail);
        update_option('users_can_register', $originalRegistration);
        Helper::updateOption('global_business_settings', $originalBusiness);
        if ($fluentAuthLimit) {
            $fluentAuthLimit->setValue(null, $originalFluentAuth);
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach (get_users(['search' => '*' . strtolower($marker) . '*', 'search_columns' => ['user_login', 'user_email'], 'fields' => 'ID']) as $userId) {
            wp_delete_user($userId);
        }
        \FluentSupport\App\Models\Customer::where('email', 'LIKE', '%' . strtolower($marker) . '%auth@example.invalid')->delete();
        wp_set_current_user($adminId);
    }
};
