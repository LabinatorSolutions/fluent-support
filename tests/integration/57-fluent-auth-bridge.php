<?php
/**
 * FluentAuth adoption: with FluentAuth 3.0+ installed, the portal's login, signup and
 * reset forms are FluentAuth's, even when FluentAuth's own forms setting is off.
 *
 * Needs FluentAuth active in the lab. Skips without it.
 */

use FluentSupport\App\Modules\Auth\FluentAuthBridge;
use FluentSupport\App\Services\Helper;

return function () {

    $hasBridge = defined('FLUENT_AUTH_VERSION') && version_compare(FLUENT_AUTH_VERSION, '3.0.0', '>=');
    $skipReason = defined('FLUENT_AUTH_VERSION')
        ? 'FluentAuth ' . FLUENT_AUTH_VERSION . ' has no LoginBridge'
        : 'FluentAuth is not active in the lab';

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();
    $originalAuthSettings = get_option('__fls_auth_forms_settings', null);
    $originalCanRegister = get_option('users_can_register');
    $originalRequest = $_REQUEST;

    $customKeys = function () {
        return ['country', 'city'];
    };

    $signedMarker = function () {
        $field = \FluentAuth\App\Services\LoginBridge::MARKER_FIELD;
        return [
            $field          => FluentAuthBridge::HOST,
            $field . '_sig' => wp_create_nonce(\FluentAuth\App\Services\LoginBridge::MARKER_ACTION . FluentAuthBridge::HOST),
        ];
    };

    $createdUserIds = [];

    // Every case starts from the request this file began with and leaves it that way, so a
    // marker set in one case can't vouch for the next.
    $case = function ($name, callable $fn) use ($originalRequest) {
        FsTest::case($name, function () use ($fn, $originalRequest) {
            $_REQUEST = $originalRequest;
            try {
                $fn();
            } finally {
                $_REQUEST = $originalRequest;
            }
        });
    };

    // Rendering one of our forms marks the whole request as ours inside FluentAuth. These
    // cases need a request nothing has rendered in yet, registered the way init does it.
    $freshBridgeState = function () {
        \FluentAuth\App\Services\LoginBridge::reset();
        FluentAuthBridge::registerHost();
    };

    // Forms are rendered for a visitor, and the marker nonce is an anonymous one.
    wp_set_current_user(0);
    update_option('__fls_auth_forms_settings', ['enabled' => 'no']);
    update_option('users_can_register', 0);
    add_filter('fluent_support/custom_registration_form_fields_key', $customKeys);

    try {
        $case('fluent-auth-bridge: adopting makes FluentAuth the provider with its setting off', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            FsTest::assertSame('fluent_auth', Helper::getAuthProvider(), 'auth provider');
        });

        $case('fluent-auth-bridge: the opt-out filter keeps our own forms', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            add_filter('fluent_support/use_fluent_auth_forms', '__return_false');
            try {
                FsTest::assertSame('fluent_support', Helper::getAuthProvider(), 'auth provider with opt-out');
            } finally {
                remove_filter('fluent_support/use_fluent_auth_forms', '__return_false');
            }
        });

        $case('fluent-auth-bridge: the login shortcode renders FluentAuth with our signed marker', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $html = do_shortcode('[fluent_support_login]');

            FsTest::assert(strpos($html, 'id="fls_login_form"') !== false, 'FluentAuth login form rendered');
            FsTest::assert(strpos($html, 'id="fst_login_form"') === false, 'our own login form not rendered');
            FsTest::assert(strpos($html, 'name="_fls_host" value="fluent-support"') !== false, 'signed marker printed');
        });

        $case('fluent-auth-bridge: signup renders our custom fields', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            update_option('users_can_register', 1);
            try {
                $html = do_shortcode('[fluent_support_signup]');
            } finally {
                update_option('users_can_register', 0);
            }

            FsTest::assert(strpos($html, 'id="flsRegistrationForm"') !== false, 'FluentAuth signup form rendered: ' . substr(wp_strip_all_tags($html), 0, 120));
            FsTest::assert(strpos($html, 'name="city"') !== false, 'city field added');

            $supportsSelect = defined('\\FluentAuth\\App\\Hooks\\Handlers\\CustomAuthHandler::SIGNUP_FIELD_TYPES');
            if ($supportsSelect) {
                FsTest::assert(strpos($html, '<select id="fst_country" name="country"') !== false, 'country rendered as a select');
            } else {
                FsTest::assert(strpos($html, 'type="text" id="fst_country"') !== false, 'country downgraded to text on FluentAuth without select');
            }
        });

        $case('fluent-auth-bridge: registration closed means no FluentAuth signup form', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $signup = do_shortcode('[fluent_support_signup]');
            FsTest::assert(strpos($signup, 'flsRegistrationForm') === false, 'signup shortcode renders no form');

            $auth = do_shortcode('[fluent_support_auth]');
            FsTest::assert(strpos($auth, 'id="fls_login_form"') !== false, 'login form still rendered');
            FsTest::assert(strpos($auth, 'flsResetPasswordForm') !== false, 'reset form still rendered');
            FsTest::assert(strpos($auth, 'flsRegistrationForm') === false, 'no signup form in the combined form');
            FsTest::assert(strpos($auth, 'fls_show_signup') === false, 'no signup link in the combined form');
        });

        $case('fluent-auth-bridge: show-signup="false" hides FluentAuth signup with registration open', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            update_option('users_can_register', 1);
            try {
                $auth = do_shortcode('[fluent_support_auth show-signup="false"]');
                $open = do_shortcode('[fluent_support_auth]');
            } finally {
                update_option('users_can_register', 0);
            }

            FsTest::assert(strpos($auth, 'flsRegistrationForm') === false, 'no signup form when hidden');
            FsTest::assert(strpos($auth, 'flsResetPasswordForm') !== false, 'reset form kept when signup hidden');
            FsTest::assert(strpos($open, 'flsRegistrationForm') !== false, 'signup form shown by default with registration open');
        });

        $case('fluent-auth-bridge: login shortcode links open forms that are on the page', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            update_option('users_can_register', 1);
            try {
                $both = do_shortcode('[fluent_support_login show-signup="true" show-reset-password="true"]');
                $plain = do_shortcode('[fluent_support_login]');
            } finally {
                update_option('users_can_register', 0);
            }

            FsTest::assert(strpos($both, 'id="fls_show_signup"') !== false, 'signup link shown');
            FsTest::assert(strpos($both, 'id="flsRegistrationForm"') !== false, 'signup form on the page for the link');
            FsTest::assert(strpos($both, 'id="fls_show_reset_password"') !== false, 'reset link shown');
            FsTest::assert(strpos($both, 'id="flsResetPasswordForm"') !== false, 'reset form on the page for the link');

            FsTest::assert(strpos($plain, 'id="fls_login_form"') !== false, 'plain login form rendered');
            FsTest::assert(strpos($plain, 'flsRegistrationForm') === false && strpos($plain, 'flsResetPasswordForm') === false, 'plain login has no extra forms');
            FsTest::assert(strpos($plain, 'fls_show_signup') === false && strpos($plain, 'fls_show_reset_password') === false, 'plain login has no links');
        });

        $case('fluent-auth-bridge: show-reset-password="false" hides FluentAuth reset', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            update_option('users_can_register', 1);
            try {
                $auth = do_shortcode('[fluent_support_auth show-reset-password="false"]');
            } finally {
                update_option('users_can_register', 0);
            }

            FsTest::assert(strpos($auth, 'flsRegistrationForm') !== false, 'signup form still shown');
            FsTest::assert(strpos($auth, 'flsResetPasswordForm') === false, 'no reset form');
            FsTest::assert(strpos($auth, 'fls_show_reset_password') === false, 'no reset link');
        });

        $case('fluent-auth-bridge: other FluentAuth signup forms do not get our fields', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $fields = (new \FluentAuth\App\Hooks\Handlers\CustomAuthHandler())->getSignupFields();

            FsTest::assert(!isset($fields['country']) && !isset($fields['city']), 'no custom fields outside our forms');
        });

        $case('fluent-auth-bridge: we register with FluentAuth when adopting', function () use ($hasBridge, $skipReason, $freshBridgeState) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            \FluentAuth\App\Services\LoginBridge::reset();
            FsTest::assert(!\FluentAuth\App\Services\LoginBridge::isRegistered(FluentAuthBridge::HOST), 'not registered after reset');

            FluentAuthBridge::registerHost();
            FsTest::assert(\FluentAuth\App\Services\LoginBridge::isRegistered(FluentAuthBridge::HOST), 'registered by registerHost()');
        });

        $case('fluent-auth-bridge: we do not register with FluentAuth when opted out', function () use ($hasBridge, $skipReason, $freshBridgeState) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            add_filter('fluent_support/use_fluent_auth_forms', '__return_false');
            try {
                \FluentAuth\App\Services\LoginBridge::reset();
                FluentAuthBridge::registerHost();
                FsTest::assert(!\FluentAuth\App\Services\LoginBridge::isRegistered(FluentAuthBridge::HOST), 'not registered when opted out');
            } finally {
                remove_filter('fluent_support/use_fluent_auth_forms', '__return_false');
                $freshBridgeState();
            }
        });

        $case('fluent-auth-bridge: a plain is_fs_auth field without the signed marker gets nothing', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $_REQUEST = array_merge($_REQUEST, ['is_fs_auth' => 'yes', '__redirect_to' => home_url('/forged/')]);

            $forged = [\FluentAuth\App\Services\LoginBridge::MARKER_FIELD => FluentAuthBridge::HOST, \FluentAuth\App\Services\LoginBridge::MARKER_FIELD . '_sig' => 'forged'];
            foreach ([[], $forged] as $extra) {
                $request = $_REQUEST;
                $_REQUEST = array_merge($_REQUEST, $extra);

                FsTest::assert(!FluentAuthBridge::hasSignedMarker(), 'no claim on our behalf');
                FsTest::assertSame('editor', apply_filters('fluent_auth/signup_default_role', 'editor', []), 'site role kept');

                $fields = (new \FluentAuth\App\Hooks\Handlers\CustomAuthHandler())->getSignupFields();
                FsTest::assert(!isset($fields['country']) && !isset($fields['city']), 'no custom fields added');

                FsTest::assertSame(admin_url(), apply_filters('fluent_auth/login_redirect_url', admin_url(), null), 'redirect untouched');

                $_REQUEST = $request;
            }
        });

        $case('fluent-auth-bridge: FluentAuth signup endpoint answers our signed marker only, and only with registration on', function () use ($hasBridge, $skipReason, $signedMarker, $marker, $freshBridgeState) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            global $wpdb;
            $hashTable = $wpdb->prefix . 'fls_login_hashes';
            $lastHashId = (int) $wpdb->get_var("SELECT MAX(id) FROM {$hashTable}");

            // Runs FluentAuth's real admin-ajax signup handler on a request nothing has
            // rendered in. It ends in wp_send_json(), so the request is made to look like
            // ajax and wp_die() throws instead of exiting.
            $callSignup = function (array $request) use ($freshBridgeState) {
                $freshBridgeState();

                $stop = function () {
                    throw new RuntimeException('fs-ajax-stop');
                };
                $handler = function () use ($stop) {
                    return $stop;
                };
                $savedRequest = $_REQUEST;
                $_REQUEST = $request;
                add_filter('wp_doing_ajax', '__return_true');
                add_filter('wp_die_ajax_handler', $handler);
                ob_start();
                try {
                    (new \FluentAuth\App\Hooks\Handlers\CustomAuthHandler())->handleSignupAjax();
                } catch (RuntimeException $e) {
                    if ($e->getMessage() !== 'fs-ajax-stop') {
                        throw $e;
                    }
                } finally {
                    $output = ob_get_clean();
                    remove_filter('wp_doing_ajax', '__return_true');
                    remove_filter('wp_die_ajax_handler', $handler);
                    $_REQUEST = $savedRequest;
                }

                return (array) json_decode((string) $output, true);
            };

            $email = $marker . '-fa-endpoint@example.invalid';
            $form = [
                'action'            => 'fluent_auth_signup',
                '_fls_signup_nonce' => wp_create_nonce('fluent_auth_signup_nonce'),
                'first_name'        => 'Endpoint',
                'username'          => substr($marker . '-fa-endpoint', 0, 60),
                'email'             => $email,
                'password'          => 'a-long-password-123',
                'country'           => 'BD',
                'city'              => 'Dhaka',
            ];
            $withMarker = array_merge($signedMarker(), $form);
            $isRefused = function ($response) {
                return strpos((string) ($response['message'] ?? ''), 'registration is not enabled') !== false;
            };
            $isVerificationStep = function ($response) {
                return strpos((string) ($response['verifcation_html'] ?? ''), 'name="_email_verification_token"') !== false;
            };

            try {
                update_option('users_can_register', 0);
                $closed = $callSignup($withMarker);
                FsTest::assert($isRefused($closed), 'registration off: refused: ' . wp_json_encode($closed));

                update_option('users_can_register', 1);
                $noMarker = $callSignup($form);
                FsTest::assert($isRefused($noMarker), 'no marker: refused, FluentAuth forms are off for this request: ' . wp_json_encode($noMarker));

                $open = $callSignup($withMarker);
                FsTest::assert($isVerificationStep($open), 'signed marker and registration on: email verification form: ' . substr(wp_json_encode($open), 0, 200));

                FsTest::assert(!get_user_by('email', $email), 'no user created before the email code is entered');
            } finally {
                update_option('users_can_register', 0);
                $freshBridgeState();
                $wpdb->query($wpdb->prepare("DELETE FROM {$hashTable} WHERE id > %d AND use_type = %s", $lastHashId, 'signup_verification'));

                // Only reached if the gate failed; don't leave the account behind.
                if ($stray = get_user_by('email', $email)) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    wp_delete_user($stray->ID);
                }
            }
        });

        $case('fluent-auth-bridge: a marked signup gets our role and saves custom fields', function () use ($hasBridge, $skipReason, $signedMarker, $marker, &$createdUserIds) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $_REQUEST = array_merge($_REQUEST, $signedMarker());

            FsTest::assertSame('subscriber', apply_filters('fluent_auth/signup_default_role', 'editor', []), 'signup role');

            do_action('fluent_auth/after_signup_validation', [
                'email'   => $marker . '-fa-signup@example.invalid',
                'country' => 'BD',
                'city'    => 'Dhaka',
                'zip'     => 'not-enabled',
            ]);

            $userId = wp_insert_user([
                'user_login' => substr($marker . '-fa-signup', 0, 60),
                'user_email' => $marker . '-fa-signup@example.invalid',
                'user_pass'  => wp_generate_password(20),
                'role'       => 'subscriber',
            ]);
            FsTest::assert(!is_wp_error($userId), 'user created');
            $createdUserIds[] = (int) $userId;

            do_action('fluent_auth/after_creating_user', $userId, []);

            FsTest::assertSame('BD', get_user_meta($userId, 'country', true), 'country meta');
            FsTest::assertSame('Dhaka', get_user_meta($userId, 'city', true), 'city meta');
            FsTest::assertSame('', get_user_meta($userId, 'zip', true), 'field not enabled in settings is ignored');
        });

        $case('fluent-auth-bridge: signup redirect survives FluentAuth logging the new user in', function () use ($hasBridge, $skipReason, $signedMarker, $marker, &$createdUserIds) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $portal = home_url('/support-portal/');
            $savedRequest = $_REQUEST;
            $_REQUEST = array_merge($_REQUEST, $signedMarker(), ['__redirect_to' => $portal]);

            try {
                // FluentAuth's order: validation hooks run for the visitor, then login(), then the redirect filter.
                do_action('fluent_auth/after_signup_validation', ['email' => $marker . '-fa-redirect@example.invalid']);

                $userId = wp_insert_user([
                    'user_login' => substr($marker . '-fa-redirect', 0, 60),
                    'user_email' => $marker . '-fa-redirect@example.invalid',
                    'user_pass'  => wp_generate_password(20),
                    'role'       => 'subscriber',
                ]);
                FsTest::assert(!is_wp_error($userId), 'user created');
                $createdUserIds[] = (int) $userId;

                wp_set_current_user($userId);
                $redirect = apply_filters('fluent_auth/login_redirect_url', admin_url(), get_user_by('ID', $userId));
            } finally {
                wp_set_current_user(0);
                $_REQUEST = $savedRequest;
            }

            FsTest::assertSame($portal, $redirect, 'portal redirect kept after login');
        });

        $case('fluent-auth-bridge: signup redirect stays on this site', function () use ($hasBridge, $skipReason, $signedMarker) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $portal = home_url('/support-portal/');

            $_REQUEST = array_merge($_REQUEST, $signedMarker(), ['__redirect_to' => $portal]);
            FsTest::assertSame($portal, apply_filters('fluent_auth/login_redirect_url', admin_url(), null), 'on-site redirect kept');

            $_REQUEST['__redirect_to'] = 'https://evil.example/phish';
            FsTest::assertSame(Helper::getPortalBaseUrl(), apply_filters('fluent_auth/login_redirect_url', admin_url(), null), 'off-site redirect replaced');
        });

        $case('fluent-auth-bridge: FluentAuth without the bridge leaves our forms alone while its setting is off', function () use ($hasBridge) {
            if (!defined('FLUENT_AUTH_VERSION') || $hasBridge) {
                FsTest::skip('needs FluentAuth older than 3.0.0');
                return;
            }

            FsTest::assertSame('fluent_support', Helper::getAuthProvider(), 'auth provider');
            FsTest::assert(strpos(do_shortcode('[fluent_support_login]'), 'id="fst_login_form"') !== false, 'our own login form rendered');
        });

        $case('fluent-auth-bridge: FluentAuth without the bridge gets our fields, select as text', function () use ($hasBridge) {
            if (!defined('FLUENT_AUTH_VERSION') || $hasBridge) {
                FsTest::skip('needs FluentAuth older than 3.0.0');
                return;
            }

            update_option('__fls_auth_forms_settings', ['enabled' => 'yes']);
            update_option('users_can_register', 1);
            try {
                FsTest::assertSame('fluent_auth', Helper::getAuthProvider(), 'auth provider with FluentAuth forms on');

                $html = do_shortcode('[fluent_support_signup]');
                FsTest::assert(strpos($html, 'name="city"') !== false, 'city field added');
                FsTest::assert(strpos($html, 'type="text" id="fst_country"') !== false, 'country downgraded to text');
            } finally {
                update_option('__fls_auth_forms_settings', ['enabled' => 'no']);
                update_option('users_can_register', 0);
            }
        });

        $case('fluent-auth-bridge: our REST login is closed while FluentAuth is the provider', function () use ($hasBridge, $skipReason) {
            if (!$hasBridge) {
                FsTest::skip($skipReason);
                return;
            }

            $result = FsTest::rest('POST', '/login', ['log' => 'nobody', 'pwd' => 'nothing']);
            FsTest::assert((int) $result['status'] >= 400, 'login refused, got ' . $result['status']);
            FsTest::assert(strpos($result['message'], 'not allowed to login') !== false, 'refused by the provider check: ' . $result['message']);
        });
    } finally {
        $_REQUEST = $originalRequest;
        remove_filter('fluent_support/custom_registration_form_fields_key', $customKeys);

        // Later files start from FluentAuth state as init leaves it, not adopted by our renders.
        if ($hasBridge) {
            $freshBridgeState();
        }

        if ($originalAuthSettings === null) {
            delete_option('__fls_auth_forms_settings');
        } else {
            update_option('__fls_auth_forms_settings', $originalAuthSettings);
        }
        update_option('users_can_register', $originalCanRegister);

        foreach ($createdUserIds as $userId) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }

        wp_set_current_user($adminId);
    }
};
