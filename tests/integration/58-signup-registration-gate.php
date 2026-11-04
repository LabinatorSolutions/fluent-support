<?php
/**
 * Portal signup follows WordPress's "Anyone can register" setting.
 *
 * Hiding the signup link used to be the only control, while POST /signup stayed open
 * to anyone. With registration off there must be no signup form, no link, and the
 * endpoint must refuse.
 */

use FluentSupport\App\Services\Helper;

return function () {

    $adminId = get_current_user_id();
    $originalCanRegister = get_option('users_can_register');

    // These cases cover our own forms; FluentAuth's are covered in 57-fluent-auth-bridge.
    add_filter('fluent_support/use_fluent_auth_forms', '__return_false');
    wp_set_current_user(0);

    $setRegistration = function ($open) {
        update_option('users_can_register', $open ? 1 : 0);
    };

    try {
        // With FluentAuth's own forms setting on, the opt-out above can't bring our forms back.
        if (Helper::getAuthProvider() !== 'fluent_support') {
            FsTest::case('signup-gate: built-in forms', function () {
                FsTest::skip('FluentAuth forms setting is on in the lab');
            });
            return;
        }

        FsTest::case('signup-gate: REST signup refuses while registration is off', function () use ($setRegistration) {
            $setRegistration(false);

            $result = FsTest::rest('POST', '/signup', [
                'first_name' => 'Gate',
                'username'   => 'gate-' . FsFactory::marker(),
                'email'      => FsFactory::marker() . '-gate@example.invalid',
                'password'   => 'a-long-password',
            ]);

            FsTest::assertSame(403, (int) $result['status'], 'status');
            FsTest::assert(strpos($result['message'], 'registration is not enabled') !== false, 'message: ' . $result['message']);
        });

        FsTest::case('signup-gate: REST signup passes the gate while registration is on', function () use ($setRegistration) {
            $setRegistration(true);

            $result = FsTest::rest('POST', '/signup', ['username' => 'nobody']);

            // Stops at the nonce check, the next step after the gate.
            FsTest::assert(strpos($result['message'], 'Security verification failed') !== false, 'reached nonce check: ' . $result['message']);
        });

        FsTest::case('signup-gate: no signup form or link while registration is off', function () use ($setRegistration) {
            $setRegistration(false);

            $signup = do_shortcode('[fluent_support_signup]');
            FsTest::assert(strpos($signup, 'fstRegistrationForm') === false, 'signup shortcode renders no form');
            FsTest::assert(strpos($signup, 'registration is not enabled') !== false, 'signup shortcode explains why');

            $auth = do_shortcode('[fluent_support_auth]');
            FsTest::assert(strpos($auth, 'id="fst_login_form"') !== false, 'login form still rendered');
            FsTest::assert(strpos($auth, 'fs_show_reset_password') !== false, 'reset link still rendered');
            FsTest::assert(strpos($auth, 'fs_show_signup') === false, 'no signup link');
            FsTest::assert(strpos($auth, 'fstRegistrationForm') === false, 'no embedded signup form');
            FsTest::assert(strpos($auth, 'registration is not enabled') === false, 'no stray message in the combined form');

            $login = do_shortcode('[fluent_support_login show-signup="true"]');
            FsTest::assert(strpos($login, 'fs_show_signup') === false, 'attribute cannot turn the link back on');
        });

        FsTest::case('signup-gate: a completed signup only redirects on this site', function () use ($setRegistration) {
            $setRegistration(true);

            // Runs the two-step signup: the first post mails a code, the second creates the user.
            $signup = function ($slug, $redirectTo) {
                $email = FsFactory::marker() . '-' . $slug . '@example.invalid';
                $code = null;
                $captureCode = function ($body, $verificationCode) use (&$code) {
                    $code = $verificationCode;
                    return $body;
                };

                $params = [
                    '_fsupport_signup_nonce' => wp_create_nonce('fluent_support_signup_nonce'),
                    'first_name'             => 'Redirect',
                    'username'               => substr(FsFactory::marker() . '-' . $slug, 0, 60),
                    'email'                  => $email,
                    'password'               => 'a-long-password-123',
                    '__redirect_to'          => $redirectTo,
                ];

                add_filter('fluent_support/signup_verification_email_body', $captureCode, 10, 2);
                FsTest::rest('POST', '/signup', $params);
                remove_filter('fluent_support/signup_verification_email_body', $captureCode, 10);

                $record = \FluentSupport\App\Models\Meta::where('object_type', 'fs_login_hashes')
                    ->where('value', 'like', '%' . $email . '%')
                    ->orderBy('id', 'DESC')
                    ->first();

                $result = FsTest::rest('POST', '/signup', array_merge($params, [
                    '_email_verification_token' => (string) $code,
                    '_email_verification_hash'  => $record ? $record->key : '',
                ]));

                // Signup logs the new user in; go back to a visitor and remove what it made.
                wp_set_current_user(0);
                if ($user = get_user_by('email', $email)) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    wp_delete_user($user->ID);
                }
                \FluentSupport\App\Models\Meta::where('object_type', 'fs_login_hashes')
                    ->where('value', 'like', '%' . $email . '%')
                    ->delete();

                return $result;
            };

            $onSite = home_url('/support-portal/');
            $kept = $signup('redirect-onsite', $onSite);
            FsTest::assertHealthy($kept, 'signup with on-site redirect');
            FsTest::assertSame($onSite, $kept['data']['redirect'] ?? null, 'on-site redirect kept');

            $portal = Helper::getPortalBaseUrl();
            foreach (['offsite' => 'https://evil.example/phish', 'script' => 'javascript:alert(1)'] as $slug => $target) {
                $result = $signup('redirect-' . $slug, $target);
                FsTest::assertHealthy($result, 'signup with ' . $slug . ' redirect');
                FsTest::assertSame($portal, $result['data']['redirect'] ?? null, $slug . ' redirect replaced by the portal');
            }
        });

        FsTest::case('signup-gate: signup form and link while registration is on', function () use ($setRegistration) {
            $setRegistration(true);

            $auth = do_shortcode('[fluent_support_auth]');
            FsTest::assert(strpos($auth, 'fs_show_signup') !== false, 'signup link');
            FsTest::assert(strpos($auth, 'fstRegistrationForm') !== false, 'embedded signup form');

            $hidden = do_shortcode('[fluent_support_auth show-signup="false"]');
            FsTest::assert(strpos($hidden, 'fstRegistrationForm') === false, 'show-signup="false" still hides it');
        });
    } finally {
        remove_filter('fluent_support/use_fluent_auth_forms', '__return_false');
        update_option('users_can_register', $originalCanRegister);
        wp_set_current_user($adminId);
    }
};
