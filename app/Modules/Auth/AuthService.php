<?php

namespace FluentSupport\App\Modules\Auth;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

/**
 * What the auth endpoints share and other code calls: emailed codes (signup
 * verification and the login 2FA code), creating a WordPress user, logging in.
 */
class AuthService
{
    const CODE_TTL_SECONDS = 10 * MINUTE_IN_SECONDS;

    /** Meta object types the codes live under: signup verification, login 2FA. */
    const SIGNUP_CODE = 'fs_login_hashes';
    const LOGIN_CODE = 'fs_2fa';

    /**
     * Store a new code and return [hash, code]. The hash goes back to the browser;
     * the code goes to the email address only.
     *
     * @param string $type   SIGNUP_CODE or LOGIN_CODE.
     * @param array  $record email for a signup code; user_id and user_email for a login code.
     * @return array|false [hash, code], or false when it could not be stored.
     */
    public static function issueCode($type, array $record)
    {
        try {
            $code = str_pad((string) random_int(100123, 900987), 6, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            $code = str_pad((string) wp_rand(100123, 900987), 6, '0', STR_PAD_LEFT);
        }

        $owner = $type === self::LOGIN_CODE ? $record['user_id'] : $record['email'];
        $hash = sanitize_title(wp_hash_password($owner . '-' . wp_generate_uuid4() . wp_rand(1, 99999999)), '', 'display') . $owner . '-' . time();

        $record += [
            'login_hash'       => $hash,
            'status'           => 'issued',
            'ip_address'       => Helper::getIp(),
            'use_type'         => $type === self::LOGIN_CODE ? 'email_2_fa' : 'signup_verification',
            'used_count'       => 0,
            'two_fa_code_hash' => wp_hash_password($code),
            // Site time: current_time('timestamp') is already shifted by the site's
            // offset, and gmdate() prints it without shifting again. status() reads it
            // back the same way (strtotime($validTill . ' UTC') against
            // current_time('timestamp')), so the two agree in every timezone.
            'valid_till'       => gmdate('Y-m-d H:i:s', current_time('timestamp') + self::CODE_TTL_SECONDS),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ];

        if ($type === self::LOGIN_CODE) {
            // One outstanding login code per account.
            Meta::where('object_type', self::LOGIN_CODE)->where('object_id', $record['user_id'])->delete();
        }

        $saved = Meta::create([
            'object_type' => $type,
            'object_id'   => $type === self::LOGIN_CODE ? $record['user_id'] : null,
            'key'         => $hash,
            'value'       => maybe_serialize($record),
        ]);

        return ($saved && $saved->exists) ? [$hash, $code] : false;
    }

    /**
     * The stored record behind $hash, or null.
     *
     * @param string $type
     * @param string $hash
     * @return array|null
     */
    public static function codeRecord($type, $hash)
    {
        $row = $hash ? Meta::where('object_type', $type)->where('key', $hash)->first() : null;
        $record = $row ? Helper::safeUnserialize($row->value) : null;

        return is_array($record) ? $record : null;
    }

    /**
     * Check $code against the record behind $hash and use it up. Single use: the
     * record is consumed with a compare-and-set, so two parallel requests with the
     * right code can't both pass. Each wrong guess counts; after 5 the code is dead.
     *
     * @param string $type
     * @param string $hash
     * @param string $code
     * @return array|string The record, or why it failed: unknown, used, expired, wrong.
     */
    public static function consumeCode($type, $hash, $code)
    {
        $row = $hash ? Meta::where('object_type', $type)->where('key', $hash)->first() : null;
        $record = $row ? Helper::safeUnserialize($row->value) : null;

        if (!is_array($record)) {
            return 'unknown';
        }

        if (Arr::get($record, 'status') !== 'issued') {
            return 'used';
        }

        // valid_till is written with gmdate(). Parse it as UTC explicitly, since a
        // plugin may have changed the PHP default timezone.
        $validTill = Arr::get($record, 'valid_till', '');
        if (Arr::get($record, 'used_count', 0) > 5 || ($validTill && strtotime($validTill . ' UTC') < current_time('timestamp'))) {
            return 'expired';
        }

        $compareAndSet = function (array $next) use ($type, $hash, $row) {
            return Meta::where('object_type', $type)->where('key', $hash)->where('value', $row->value)
                ->update(['value' => maybe_serialize($next)]);
        };

        if (!wp_check_password((string) $code, (string) Arr::get($record, 'two_fa_code_hash'))) {
            $compareAndSet(array_merge($record, ['used_count' => Arr::get($record, 'used_count', 0) + 1]));
            return 'wrong';
        }

        if (!$compareAndSet(array_merge($record, ['used_count' => Arr::get($record, 'used_count', 0) + 1, 'status' => 'used']))) {
            return 'used';
        }

        return $record;
    }

    /**
     * Email a signup verification code.
     *
     * @param array  $formData email, first_name.
     * @param string $code
     * @return void
     */
    public static function sendSignupCode(array $formData, $code)
    {
        // translators: %s is the site name
        $subject = apply_filters('fluent_support/signup_verification_mail_subject', sprintf(__('Your registration verification code for %s', 'fluent-support'), get_bloginfo('name')));

        $p = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        // translators: %s is the user's first name
        $body = $p . sprintf(__('Hello %s,', 'fluent-support'), Arr::get($formData, 'first_name')) . '</p>' .
            $p . __('Thank you for registering with us! To complete the setup of your account, please enter the verification code below on the registration page.', 'fluent-support') . '</p>' .
            // translators: %s is the verification code
            $p . '<b>' . sprintf(__('Verification Code: %s', 'fluent-support'), $code) . '</b></p>' .
            '<br />' .
            $p . __('This code is valid for 10 minutes and is meant to ensure the security of your account. If you did not initiate this request, please ignore this email.', 'fluent-support') . '</p>';

        $body = apply_filters('fluent_support/signup_verification_email_body', $body, $code, $formData);

        self::sendCodeMail($formData['email'], $subject, $body);
    }

    /**
     * Email a login (2FA) code.
     *
     * @param \WP_User $user
     * @param string   $code
     * @return void
     */
    public static function sendLoginCode($user, $code)
    {
        // translators: %1s is the site name
        $subject = sprintf(__('Your Login code for %1s', 'fluent-support'), get_bloginfo('name'));

        $p = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        // translators: %s is the user's display name
        $body = $p . sprintf(__('Hello %s,', 'fluent-support'), $user->display_name) . '</p>' .
            // translators: %s is the site name
            $p . sprintf(__('Someone requested to login to %s and here is the Login code that you can use in the login form', 'fluent-support'), get_bloginfo('name')) . '</p>' .
            // translators: %s is the two-factor authentication code
            $p . '<b>' . sprintf(__('Verification Code: %s', 'fluent-support'), $code) . '</b></p>' .
            '<br />' .
            $p . __('This code is valid for 10 minutes and is meant to ensure the security of your account. If you did not initiate this request, please ignore this email.', 'fluent-support') . '</p>';

        $body = apply_filters('fluent_support/signup_verification_email_body', $body, $code, ['user_id' => $user->ID, 'user_email' => $user->user_email]);

        self::sendCodeMail($user->user_email, $subject, $body);
    }

    /**
     * Create a WordPress user from signup-style data (email, username, password,
     * first_name, last_name), with the signup hooks. Also used by agents creating a
     * customer and by the PHP API.
     *
     * @param array $formData
     * @return int|false The user ID.
     */
    public static function createUser(array $formData)
    {
        /*
         * Filter user signup email
         *
         * @since v1.0.0
         * @param string $email
         */
        $email = apply_filters('fluent_support/signup_email', Arr::get($formData, 'email'));

        /*
         * Filter user signup username
         *
         * @since v1.0.0
         * @param string $username
         */
        $userName = apply_filters('fluent_support/signup_username', Arr::get($formData, 'username'));

        /*
         * Filter user signup password
         *
         * @since v1.0.0
         * @param string $password
         */
        $password = apply_filters('fluent_support/signup_password', Arr::get($formData, 'password') ?: wp_generate_password(16, true, true));

        /*
         * Action before creating WP user using Fluent Support signup form
         *
         * @since v1.0.0
         * @param string $userName
         * @param string $password
         * @param string $email
         */
        do_action('fluent_support/before_creating_user', $userName, $password, $email);

        $userId = wp_create_user($userName, $password, $email);

        if (is_wp_error($userId)) {
            return false;
        }

        self::setName($userId, $formData);

        return $userId;
    }

    /**
     * Save the custom registration fields on the user.
     *
     * @param int   $userId
     * @param array $formData
     * @return void
     */
    public static function saveCustomFields($userId, array $formData)
    {
        foreach (Helper::getCustomRegistrationFieldKeys() as $key) {
            if (isset($formData[$key]) && is_scalar($formData[$key])) {
                update_user_meta($userId, $key, sanitize_text_field($formData[$key]));
            }
        }
    }

    /**
     * Give a newly signed-up user the portal role.
     *
     * @param int $userId
     * @return void
     */
    public static function assignRole($userId)
    {
        $user = new \WP_User($userId);

        /*
         * Action before assigning role to registered user
         *
         * @since v1.0.0
         * @param \WP_User $user
         */
        do_action('fluent_support/before_assigning_role', $user);

        /*
         * Filter user assignable role after signup
         *
         * @since v1.0.0
         * @param string $setRole WordPress user role key
         */
        $user->set_role(apply_filters('fluent_support/user_role', 'subscriber'));

        /*
         * Action after assigning role to registered user
         *
         * @since v1.0.0
         * @param \WP_User $user
         */
        do_action('fluent_support/after_assigning_role', $user);
    }

    /**
     * Log the user in: the one login step for signup, login and the 2FA code.
     *
     * @param \WP_User $user
     * @param bool     $remember
     * @return void
     */
    public static function logIn($user, $remember = false)
    {
        /*
         * Action before login
         *
         * @since v1.0.0
         * @param integer $userId
         */
        do_action('fluent_support/before_logging_in_user', $user->ID);

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());

        // What wp_signon() fires, for plugins that track logins.
        do_action('wp_login', $user->user_login, $user); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        /*
         * Action after login
         *
         * @since v1.0.0
         * @param integer $userId
         */
        do_action('fluent_support/after_logging_in_user', $user->ID);
    }

    /**
     * Sets the name fields from first/last name, if given.
     *
     * @param int   $userId
     * @param array $formData
     * @return void
     */
    private static function setName($userId, array $formData)
    {
        $firstName = Arr::get($formData, 'first_name', '');
        $lastName = Arr::get($formData, 'last_name', '');
        $name = trim($firstName . ' ' . $lastName);

        if (!$name) {
            return;
        }

        $data = array_filter([
            'ID'            => $userId,
            'user_nicename' => $name,
            'display_name'  => $name,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
        ]);

        /*
         * Action before updating a customer/user
         *
         * @since v1.0.0
         * @param array $data
         */
        do_action('fluent_support/before_updating_user', $data);

        /*
         * Filter user updatable data
         *
         * @since v1.0.0
         * @param $data
         */
        wp_update_user(apply_filters('fluent_support/update_user_data', $data));

        /*
         * Action after updating a customer/user
         *
         * @since v1.0.0
         * @param array $data
         */
        do_action('fluent_support/after_updating_user', $data);
    }

    private static function sendCodeMail($to, $subject, $body)
    {
        $message = Helper::loadView('notification', [
            'body'        => $body,
            'pre_header'  => __('Activate your account', 'fluent-support'),
            'show_footer' => false
        ]);

        wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}
