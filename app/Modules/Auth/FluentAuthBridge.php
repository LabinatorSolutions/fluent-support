<?php

namespace FluentSupport\App\Modules\Auth;

use FluentSupport\App\Services\Helper;

/**
 * Hands the portal's login, signup and reset forms to FluentAuth when it is installed.
 *
 * From FluentAuth 3.0 a plugin can adopt its forms through LoginBridge even when the
 * site's own FluentAuth forms setting is off. Without that, our wp_login_form() picks up
 * FluentAuth's magic login, social and passkey buttons, and our REST login cannot show
 * FluentAuth's second factor. Older FluentAuth versions are used only when that setting
 * is on, as before.
 */
class FluentAuthBridge
{
    const HOST = 'fluent-support';

    /**
     * Set while one of our shortcodes renders a FluentAuth form.
     *
     * @var bool
     */
    private static $rendering = false;

    /**
     * The submitted signup data, kept from validation until the user is created.
     *
     * @var array|null
     */
    private static $signupData = null;

    public static function register()
    {
        if (!defined('FLUENT_AUTH_VERSION')) {
            return;
        }

        // On init, so an opt-out filter added by the theme is seen.
        add_action('init', [__CLASS__, 'registerHost'], 1);

        add_filter('fluent_auth/registration_form_fields', [__CLASS__, 'addSignupFields']);
        add_action('fluent_auth/after_signup_validation', [__CLASS__, 'captureSignupData']);
        add_action('fluent_auth/after_creating_user', [__CLASS__, 'saveSignupMeta']);
        add_filter('fluent_auth/signup_default_role', [__CLASS__, 'signupRole']);
        add_filter('fluent_auth/login_redirect_url', [__CLASS__, 'loginRedirectUrl']);
    }

    /**
     * Tells FluentAuth which posts come from our forms, so its endpoints answer them.
     * Only the signed marker FluentAuth prints in forms it renders for us counts.
     */
    public static function registerHost()
    {
        if (self::shouldAdopt()) {
            \FluentAuth\App\Services\LoginBridge::register(self::HOST, [__CLASS__, 'hasSignedMarker']);
        }
    }

    /**
     * Whether FluentAuth should render our forms even with its forms setting off.
     *
     * @return bool
     */
    public static function shouldAdopt()
    {
        if (!self::hasBridge()) {
            return false;
        }

        // A site that turned on our portal 2FA keeps it: adopting FluentAuth's forms would
        // switch it off without a word, and FluentAuth's 2FA may not cover customer roles.
        // Raw option: this runs on init priority 1, before translated defaults should be cached.
        $adopt = \FluentSupport\Framework\Support\Arr::get(Helper::getOption('global_business_settings', []), 'enable_two_fa') !== 'yes';

        /*
         * Filter whether the portal forms use FluentAuth when it is installed
         *
         * @since v2.4.1
         *
         * @param bool $adopt false by default while Fluent Support's own 2FA is on
         */
        return (bool) apply_filters('fluent_support/use_fluent_auth_forms', $adopt);
    }

    /**
     * Renders a FluentAuth form as ours: adopts the screen when the bridge exists and
     * adds our signup fields while the form is built.
     *
     * @param callable $render returns the form html
     * @param string $redirectTo
     * @return string
     */
    public static function render(callable $render, $redirectTo = '')
    {
        if (self::hasBridge()) {
            \FluentAuth\App\Services\LoginBridge::adopt([
                'host'        => self::HOST,
                'redirect_to' => $redirectTo
            ]);
        }

        self::$rendering = true;

        try {
            return $render();
        } finally {
            self::$rendering = false;
        }
    }

    public static function addSignupFields($fields)
    {
        if (!self::isOurRequest()) {
            return $fields;
        }

        $customFields = AuthForms::allCustomFields();
        $supportsSelect = self::supportsSelectFields();

        foreach (Helper::getCustomRegistrationFieldKeys() as $key) {
            if (!isset($customFields[$key])) {
                continue;
            }

            $field = $customFields[$key];

            // FluentAuth versions without select support leave the field out of the form.
            if (($field['type'] ?? '') === 'select' && !$supportsSelect) {
                $field['type'] = 'text';
                unset($field['options']);
            }

            $fields[$key] = $field;
        }

        return $fields;
    }

    public static function captureSignupData($formData)
    {
        // Older FluentAuth signup forms carry no marker, so their posts cannot be told apart.
        if (self::isOurRequest() || !self::hasBridge()) {
            self::$signupData = (array)$formData;
        }
    }

    public static function saveSignupMeta($userId)
    {
        if (self::$signupData === null || !$userId) {
            return;
        }

        AuthService::saveCustomFields($userId, (array) self::$signupData);
        self::$signupData = null;
    }

    public static function signupRole($role)
    {
        if (!self::isOurRequest()) {
            return $role;
        }

        /*
         * Filter user assignable role after signup
         *
         * @since v1.0.0
         * @param string $setRole WordPress user role key
         */
        return apply_filters('fluent_support/user_role', 'subscriber');
    }

    /**
     * FluentAuth's signup posts its redirect as `__redirect_to` but reads `redirect_to`,
     * so without this a portal signup would land in wp-admin.
     *
     * @param string $redirectUrl
     * @return string
     */
    public static function loginRedirectUrl($redirectUrl)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- FluentAuth verified the request before redirecting.
        if (!self::isOurRequest() || empty($_REQUEST['__redirect_to'])) {
            return $redirectUrl;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $target = sanitize_url(wp_unslash($_REQUEST['__redirect_to']));

        return wp_validate_redirect($target, Helper::getPortalBaseUrl());
    }

    /**
     * Whether this request is one of our forms: rendering it, or posting it back.
     *
     * @return bool
     */
    private static function isOurRequest()
    {
        return self::$rendering || self::hasSignedMarker();
    }

    /**
     * Whether the request carries the signed marker FluentAuth prints in every form it
     * renders for us. A visitor cannot forge it, unlike a plain request field.
     *
     * The marker is a nonce for the visitor who loaded the form. A signup logs the new
     * user in before FluentAuth applies its redirect filter, and the nonce no longer
     * verifies for that user, so the first answer for a signature is kept.
     *
     * @return bool
     */
    public static function hasSignedMarker()
    {
        static $verified = [];

        if (!self::hasBridge()) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the marker is itself a nonce, verified below.
        $markerField = \FluentAuth\App\Services\LoginBridge::MARKER_FIELD;

        if (empty($_REQUEST[$markerField]) || empty($_REQUEST[$markerField . '_sig'])) {
            return false;
        }

        if (sanitize_key(wp_unslash($_REQUEST[$markerField])) !== self::HOST) {
            return false;
        }

        $signature = sanitize_text_field(wp_unslash($_REQUEST[$markerField . '_sig']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (!isset($verified[$signature])) {
            $verified[$signature] = (bool)wp_verify_nonce($signature, \FluentAuth\App\Services\LoginBridge::MARKER_ACTION . self::HOST);
        }

        return $verified[$signature];
    }

    /**
     * LoginBridge ships from FluentAuth 3.0.0. The version is checked before the class,
     * because FluentAuth 2.x's autoloader requires a missing file instead of returning
     * false, so class_exists() alone would be a fatal error there.
     *
     * @return bool
     */
    private static function hasBridge()
    {
        return defined('FLUENT_AUTH_VERSION')
            && version_compare(FLUENT_AUTH_VERSION, '3.0.0', '>=')
            && class_exists('\FluentAuth\App\Services\LoginBridge');
    }

    /**
     * @return bool
     */
    private static function supportsSelectFields()
    {
        $constant = '\FluentAuth\App\Hooks\Handlers\CustomAuthHandler::SIGNUP_FIELD_TYPES';

        return defined($constant) && in_array('select', (array)constant($constant), true);
    }
}
