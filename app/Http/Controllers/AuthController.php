<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Hooks\Handlers\AuthHandler;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $fields = AuthHandler::getSignupFields();

        $rules = $this->getRules($fields);

        $messages = $this->getMessages($rules);

        $formData = apply_filters('fluent-support/signup_form_data', $request->all());

        do_action('fluent-support/before_signup_validation', []);

        $this->validate($formData, $rules, $messages);

        do_action('fluent_support/after_signup_validation', $formData);

        $userId = $this->createUser($formData);

        if (is_wp_error($userId)) {
            return $this->response(
                apply_filters(
                    'fluent-support/signup_create_user_error',
                    ['error' => $userId->get_error_message()]
                ), 423);
        }

        do_action('fluent_support/after_creating_user');

        $this->maybeUpdateUser($userId, $formData);
        $this->assignRole($userId);
        $this->login($userId);

        return $this->response(
            apply_filters('fluent-support/signup_complete_response', [
                'message'  => __('Successfully registered to the site.', 'fluent-support'),
                'redirect' => Arr::get($formData, '__redirect_to', Helper::getPortalBaseUrl())
            ])
        );
    }

    protected function getRules($fields = [])
    {
        $rules = [];

        foreach ($fields as $fieldName => $field) {
            if (array_key_exists('required', $field)) {
                $rules[$fieldName] = 'required';
            }

            $pipe = array_key_exists($fieldName, $rules) ? '|' : '';

            if ($field['type'] === 'email') {
                $rules[$fieldName] = $rules[$fieldName] . $pipe . 'email';
            } elseif ($field['type'] === 'password') {
                $rules[$fieldName] = $rules[$fieldName] . $pipe . 'min:8';
            }
        }

        return apply_filters('fluent-support/signup_validation_rules', $rules);
    }

    protected function getMessages($rules = [])
    {
        return apply_filters('fluent-support/signup_validation_messages', [], $rules);
    }

    protected function createUser($formData = [])
    {
        $email = apply_filters('fluent-support/signup_email', Arr::get($formData, 'email'));
        $userName = apply_filters('fluent-support/signup_username', Arr::get($formData, 'username'));

        if (empty($formData['password'])) {
            $password = wp_generate_password(8);
        } else {
            $password = $formData['password'];
        }

        $password = apply_filters('fluent-support/signup_password', $password);

        do_action('fluent_support/before_creating_user', $userName, $password, $email);

        return wp_create_user($userName, $password, $email);
    }

    protected function maybeUpdateUser($userId, $formData)
    {
        $name = trim(Arr::get($formData, 'first_name') . ' ' . Arr::get($formData, 'last_name'));

        $data = array_filter([
            'ID'            => $userId,
            'user_nicename' => $name,
            'display_name'  => $name
        ]);

        if ($name) {
            do_action('fluent-support/before_updating_user', $data);

            wp_update_user(apply_filters('fluent-support/update_user_data', $data));

            do_action('fluent-support/after_updating_user', $data);
        }
    }

    protected function assignRole($userId)
    {
        $user = new \WP_User($userId);

        do_action('fluent-support/before_assigning_role', $user);

        $user->set_role(apply_filters('fluent-support/user_role', 'subscriber'));

        do_action('fluent-support/after_assigning_role', $user);
    }

    protected function login($userId)
    {
        do_action('fluent-support/before_logging_in_user', $userId);

        wp_clear_auth_cookie();
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId);

        do_action('fluent-support/after_logging_in_user', $userId);
    }
}
