<?php

namespace FluentSupport\App\Modules\Auth;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Hooks\Handlers\ReCaptchaHandler;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\Framework\Validator\ValidationException;

/**
 * The portal's own login, signup, password reset and email login code. FluentAuth
 * takes these over when its forms are in use (Helper::getAuthProvider()).
 */
class AuthController extends Controller
{
    /**
     * Two steps: the first submit emails a verification code, the second checks it
     * and creates the user.
     */
    public function signup(Request $request)
    {
        $this->assertOwnForms(__('You are not allowed to signup using this form', 'fluent-support'));

        if (!Helper::isSignupEnabled()) {
            throw new ApiException(__('User registration is not enabled on this site.', 'fluent-support'), 403);
        }

        if (!wp_verify_nonce($request->getSafe('_fsupport_signup_nonce', 'sanitize_text_field'), 'fluent_support_signup_nonce')) {
            throw new ApiException(__('Security verification failed. Please try again', 'fluent-support'));
        }

        /*
         * Filter user signup form data
         *
         * @since v1.0.0
         * @param array $formData
         */
        $formData = apply_filters('fluent_support/signup_form_data', $request->all());

        /*
         * Action before validate user signup
         *
         * @since v1.0.0
         * @param array $formData
         */
        do_action('fluent_support/before_signup_validation', $formData);

        // The captcha was passed on the first step; the second is gated by the code.
        $isCodeStep = !empty($formData['_email_verification_token']);

        if (!$isCodeStep && ReCaptchaHandler::isRecaptchaApplicable('signup_form')
            && !ReCaptchaHandler::validateRecaptcha($formData['g-recaptcha-response'] ?? '', null, null, 'signup')) {
            throw new ApiException(__('Your recaptcha is not verified', 'fluent-support'));
        }

        $rules = $this->signupRules();
        /*
         * Filter user signup validation message
         *
         * @since v1.0.0
         * @param array $arg
         * @param array $rules
         */
        $validator = $this->app->validator->make($formData, $rules, apply_filters('fluent_support/signup_validation_messages', [], $rules));
        if ($validator->validate()->fails()) {
            // Thrown, not Controller::validate(): that one exits outside a real REST request.
            throw new ValidationException('Unprocessable Entity!', 422, null, $validator->errors());
        }

        if (!$isCodeStep) {
            $email = strtolower(trim($formData['email']));

            // The email bucket is the main throttle against mail-bombing an address. The
            // IP bucket is looser because offices and NATs share IPs.
            /*
             * Site-wide hourly cap on signup verification mail, for attackers who rotate
             * both IP and address. Tripping it blocks signup for everyone, so keep it well
             * above organic traffic.
             *
             * @since v2.2.2
             * @param int $limit Signup verification emails allowed site-wide per hour.
             */
            $globalLimit = (int) apply_filters('fluent_support/signup_verification_hourly_limit', 100);

            // The global bucket last, so only requests past the IP and email gates count toward it.
            if (Helper::hitRateLimit('fs_signup_verify_ip_' . wp_hash(Helper::getIp()), 20)
                || Helper::hitRateLimit('fs_signup_verify_email_' . wp_hash($email), 5)
                || Helper::hitRateLimit('fs_signup_verify_global', $globalLimit, HOUR_IN_SECONDS)) {
                throw new ApiException(__('Too many verification code requests. Please try again later.', 'fluent-support'), 429);
            }

            $issued = AuthService::issueCode(AuthService::SIGNUP_CODE, ['email' => $email]);
            if (!$issued) {
                throw new ApiException(__('Unable to send verification code. Please try again later.', 'fluent-support'), 500);
            }

            list($hash, $code) = $issued;
            AuthService::sendSignupCode($formData, $code);

            return ['verification_html' => AuthForms::signupCodeForm($formData['email'], $hash)];
        }

        $record = AuthService::consumeCode(AuthService::SIGNUP_CODE, (string) ($formData['_email_verification_hash'] ?? ''), (string) $formData['_email_verification_token']);

        if ($record === 'used') {
            throw new ApiException(__('Your verification code has already been used. Please try again', 'fluent-support'));
        }
        if ($record === 'expired') {
            throw new ApiException(__('Your verification code has been expired. Please try again', 'fluent-support'));
        }
        if (!is_array($record)) {
            throw new ApiException(__('Please provide a valid verification code that was sent to your email address', 'fluent-support'));
        }
        // Old or malformed records have no bound email.
        if (empty($record['email'])) {
            throw new ApiException(__('Your verification code has expired. Please request a new one', 'fluent-support'));
        }

        // The address the code was sent to, not whatever was submitted with it.
        $formData['email'] = $record['email'];

        /*
         * Action After validate user signup validation success
         *
         * @since v1.0.0
         * @param array $formData
         */
        do_action('fluent_support/after_signup_validation', $formData);

        $userId = AuthService::createUser($formData);

        if (!$userId) {
            return $this->response(apply_filters('fluent_support/signup_create_user_error', [
                'error' => __('Could not create your account. The username or email address may already be registered.', 'fluent-support')
            ]), 423);
        }

        /*
         * Action After creating WP user from ticket sign up form
         *
         * @since v1.0.0
         */
        do_action('fluent_support/after_creating_user');

        AuthService::saveCustomFields($userId, $formData);
        AuthService::assignRole($userId);
        AuthService::logIn(get_user_by('ID', $userId));

        /*
         * Filter for user signup complete message and redirect
         *
         * @since v1.0.0
         * @param array $response
         */
        $response = apply_filters('fluent_support/signup_complete_response', [
            'message'  => __('Successfully registered to the site.', 'fluent-support'),
            'redirect' => Arr::get($formData, '__redirect_to', Helper::getPortalBaseUrl())
        ]);

        // The browser navigates to this by script; keep it on this site.
        if (!empty($response['redirect'])) {
            $portalUrl = Helper::getPortalBaseUrl();
            $response['redirect'] = wp_validate_redirect(esc_url_raw($response['redirect']), $portalUrl) ?: $portalUrl;
        }

        return $response;
    }

    public function login(Request $request)
    {
        $this->assertOwnForms(__('You are not allowed to login using this form', 'fluent-support'));

        if (!wp_verify_nonce($request->getSafe('_support_login_nonce', 'sanitize_text_field'), 'fsupport_login_nonce')) {
            throw new ApiException(__('Security verification failed', 'fluent-support'), 403);
        }

        if (ReCaptchaHandler::isRecaptchaApplicable('login_form')
            && !ReCaptchaHandler::validateRecaptcha($request->get('g-recaptcha-response', ''), null, null, 'login')) {
            throw new ApiException(__('Your recaptcha is not verified', 'fluent-support'));
        }

        $login = sanitize_user((string) $request->get('log', ''));
        $password = trim((string) $request->get('pwd', ''));

        if (!$login || !$password) {
            throw new ApiException(__('Email and Password is required', 'fluent-support'), 403);
        }

        $redirectUrl = Helper::getPortalBaseUrl();
        if ($redirect = $request->getSafe('redirect_to', 'sanitize_text_field')) {
            $redirectUrl = wp_validate_redirect($redirect, $redirectUrl);
        }

        if (get_current_user_id()) {
            return ['redirect' => $redirectUrl];
        }

        $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);

        // 5 attempts per account and IP, 20 per account, for 15 minutes.
        $ip = Helper::getIp();
        $accountId = $user ? $user->ID : strtolower($login);
        $ipKey = 'fs_login_ip_' . wp_hash($accountId . '|' . $ip);
        $accountKey = 'fs_login_act_' . wp_hash($accountId);

        if ((int) get_transient($ipKey) >= 5 || (int) get_transient($accountKey) >= 20) {
            throw new ApiException(__('Too many login attempts. Please try again after 15 minutes.', 'fluent-support'), 429);
        }

        $authenticated = $user ? wp_authenticate($user->user_login, $password) : null;

        if (!$authenticated || is_wp_error($authenticated)) {
            if (!$user) {
                do_action('wp_login_failed', $login, new \WP_Error('authentication_failed', __('Invalid username, email address or incorrect password.', 'fluent-support'))); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            }

            set_transient($ipKey, (int) get_transient($ipKey) + 1, 15 * MINUTE_IN_SECONDS);
            set_transient($accountKey, (int) get_transient($accountKey) + 1, 15 * MINUTE_IN_SECONDS);

            // The same answer for an unknown account and a wrong password, so the form
            // can't tell which accounts exist. Other refusals (a security plugin) pass through.
            $enumerating = !$authenticated || in_array($authenticated->get_error_code(), ['invalid_username', 'invalid_email', 'incorrect_password'], true);
            throw new ApiException($enumerating ? __('Email or Password is not valid. Please try again', 'fluent-support') : $authenticated->get_error_message(), 403);
        }

        // Read fresh, not from getBusinessSettings()'s per-request cache.
        if (Arr::get((new Settings())->globalBusinessSettings(false), 'enable_two_fa') === 'yes') {
            // The account bucket is the main throttle; the IP one is looser for shared IPs.
            if (Helper::hitRateLimit('fs_2fa_send_ip_' . wp_hash($ip), 20) || Helper::hitRateLimit('fs_2fa_send_act_' . wp_hash($user->ID), 10)) {
                throw new ApiException(__('Too many verification code requests. Please try again after 15 minutes.', 'fluent-support'), 429);
            }

            $issued = AuthService::issueCode(AuthService::LOGIN_CODE, ['user_id' => $user->ID, 'user_email' => $user->user_email]);
            if (!$issued) {
                // Fail closed: never fall through to a password-only login.
                throw new ApiException(__('Unable to send verification code. Please try again later.', 'fluent-support'), 500);
            }

            list($hash, $code) = $issued;
            AuthService::sendLoginCode($user, $code);

            return ['load_2fa' => 'yes', 'two_fa_form' => AuthForms::loginCodeForm($hash)];
        }

        delete_transient($ipKey);
        delete_transient($accountKey);
        AuthService::logIn($authenticated, (bool) $request->get('rememberme'));

        return ['redirect' => $redirectUrl];
    }

    /**
     * The second login step when email 2FA is on.
     */
    public function verifyLoginCode(Request $request)
    {
        $code = $request->getSafe('login_passcode', 'sanitize_text_field', '');
        $hash = $request->getSafe('login_hash', 'sanitize_text_field', '');

        if (!$code || !$hash) {
            throw new ApiException(__('Please provide a valid login code', 'fluent-support'), 423);
        }

        $tooMany = __('Too many verification attempts. Please try again after 15 minutes.', 'fluent-support');

        if (Helper::hitRateLimit('fs_2fa_verify_ip_' . wp_hash(Helper::getIp()), 20)) {
            throw new ApiException($tooMany, 429);
        }

        $pending = AuthService::codeRecord(AuthService::LOGIN_CODE, $hash);
        if (!$pending) {
            throw new ApiException(__('Your provided code or url is not valid', 'fluent-support'), 423);
        }

        if (Helper::hitRateLimit('fs_2fa_verify_act_' . wp_hash($pending['user_id'] ?? ''), 10)) {
            throw new ApiException($tooMany, 429);
        }

        $record = AuthService::consumeCode(AuthService::LOGIN_CODE, $hash, $code);

        if ($record === 'wrong') {
            throw new ApiException(__('Invalid verification code', 'fluent-support'), 423);
        }
        if (!is_array($record)) {
            throw new ApiException(__('Sorry, your login code has been expired. Please try to login again', 'fluent-support'), 423);
        }

        // Log in the account the code was issued to, and only while it still owns the
        // email the code went to.
        $user = get_user_by('ID', (int) ($record['user_id'] ?? 0));

        if (!$user || strcasecmp((string) $user->user_email, (string) ($record['user_email'] ?? '')) !== 0) {
            throw new ApiException(__('Your provided code or url is not valid', 'fluent-support'), 423);
        }

        AuthService::logIn($user);

        return ['redirect' => Helper::getPortalBaseUrl()];
    }

    public function resetPassword(Request $request)
    {
        $this->assertOwnForms(__('You are not allowed to reset password using this form', 'fluent-support'));

        if (!wp_verify_nonce($request->getSafe('_fsupport_reset_pass_nonce', 'sanitize_text_field'), 'fluent_support_reset_pass_nonce')) {
            throw new ApiException(__('Security verification failed. Please try again', 'fluent-support'));
        }

        $usernameOrEmail = trim(wp_unslash($request->getSafe('user_login', 'sanitize_text_field')));

        if (!$usernameOrEmail) {
            throw new ApiException(__('Username or email is required.', 'fluent-support'));
        }

        // Generous because of shared NAT IPs. Runs before the account lookup so probes for
        // missing accounts are throttled too, and a 429 reveals nothing about existence.
        if (Helper::hitRateLimit('fs_reset_pass_ip_' . wp_hash(Helper::getIp()), 20)) {
            throw new ApiException(__('Too many password reset requests. Please try again after 15 minutes.', 'fluent-support'), 429);
        }

        // Every outcome gets the same answer, so the form can't confirm which accounts exist.
        $answer = ['message' => __('If an account matches that username or email, a password reset link has been sent. Please check your inbox, including the spam folder.', 'fluent-support')];

        $user = get_user_by('email', $usernameOrEmail) ?: get_user_by('login', $usernameOrEmail);
        if (!$user) {
            return $answer;
        }

        $errors = new \WP_Error();
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own lost-password hooks.
        $user = apply_filters('lostpassword_user_data', $user, $errors);
        do_action('lostpassword_post', $errors, $user);
        $errors = apply_filters('lostpassword_errors', $errors, $user);

        if (!$user || $errors->has_errors() || (is_multisite() && !is_user_member_of_blog($user->ID, get_current_blog_id()))) {
            return $answer;
        }

        do_action('retrieve_password', $user->user_login);

        $allow = apply_filters('allow_password_reset', true, $user->ID);
        // phpcs:enable
        if (!$allow || is_wp_error($allow)) {
            return $answer;
        }

        // get_password_reset_key() invalidates the previous link, so repeated requests could
        // block recovery. The owner already has a working link from the first send.
        $cooldownKey = 'fs_reset_pass_sent_' . wp_hash($user->ID);
        if (get_transient($cooldownKey)) {
            return $answer;
        }
        set_transient($cooldownKey, 1, 5 * MINUTE_IN_SECONDS);

        $resetUrl = add_query_arg([
            'action' => 'rp',
            'key'    => get_password_reset_key($user),
            'login'  => rawurlencode($user->user_login)
        ], wp_login_url());

        /*
         * Filter reset password link text
         *
         * @since v1.5.7
         * @param string $linkText
         */
        // translators: %s is the site name
        $linkText = apply_filters('fluent_support/reset_password_link', sprintf(__('Reset your password for %s', 'fluent-support'), get_bloginfo('name')));
        $resetLink = '<a href="' . $resetUrl . '">' . $linkText . '</a>';

        /*
         * Filter reset password email subject
         *
         * @since v1.5.7
         * @param string $mailSubject
         */
        // translators: %s is the site name
        $subject = apply_filters('fluent_support/reset_password_mail_subject', sprintf(__('Reset your password for %s support portal', 'fluent-support'), get_bloginfo('name')));

        // translators: %s is the user's first name
        $message = '<p>' . sprintf(__('Hi %s,', 'fluent-support'), $user->first_name) . '</p>' .
            '<p>' . __('Someone has requested a new password for the following account on WordPress:', 'fluent-support') . '</p>' .
            // translators: %s is the username
            '<p>' . sprintf(__('Username: %s', 'fluent-support'), $user->user_login) . '</p>' .
            '<p>' . $resetLink . '</p>' .
            '<p>' . __('If you did not request to reset your password, please ignore this email.', 'fluent-support') . '</p>';

        /*
         * Filter reset password email body text
         *
         * @since v1.5.7
         * @param string $message
         * @param object $user
         * @param string $resetLink
         */
        $message = apply_filters('fluent_support/reset_password_message', $message, $user, $resetLink);

        wp_mail($user->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

        return $answer;
    }

    /**
     * These endpoints serve the portal's own forms only, not FluentAuth's.
     *
     * @param string $message
     * @return void
     * @throws ApiException
     */
    private function assertOwnForms($message)
    {
        if (Helper::getAuthProvider() !== 'fluent_support') {
            throw new ApiException($message);
        }
    }

    /**
     * Validation rules from the signup form's fields.
     *
     * @return array
     */
    private function signupRules()
    {
        $rules = [];

        foreach (AuthForms::getSignupFields() as $fieldName => $field) {
            $fieldRules = array_key_exists('required', $field) ? ['required'] : [];

            if ($field['type'] === 'email') {
                $fieldRules[] = 'email';
            } elseif ($field['type'] === 'password') {
                $fieldRules[] = 'min:8';
            }

            if ($fieldRules) {
                $rules[$fieldName] = implode('|', $fieldRules);
            }
        }

        /*
         * Filter user signup validation rules
         *
         * @since v1.0.0
         * @param array $rules
         */
        return apply_filters('fluent_support/signup_validation_rules', $rules);
    }
}
