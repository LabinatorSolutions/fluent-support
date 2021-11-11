<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\Framework\Support\Arr;

class AuthHandler
{

    public function init()
    {
        add_shortcode('fluent_support_login', array($this, 'loginForm'));
        add_shortcode('fluent_support_signup', array($this, 'registrationForm'));
    }

    public function loginForm()
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];
        wp_enqueue_style('fluent_support_login_style', $assets . 'admin/css/all_public.css');
        wp_enqueue_script('fluent_support_login_helper', $assets . 'portal/js/login_helper.js');
        $return = '<div class="fst_login_wrapper">';

        $loginArgs = apply_filters('fluent_support/login_form_args', [
            'echo'           => false,
            'redirect'       => get_permalink(get_the_ID()),
            'remember'       => true,
            'value_remember' => true
        ]);

        $return .= wp_login_form($loginArgs);
        $return .= '</div>';
        return $return;
    }

    public function registrationForm()
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];
        wp_enqueue_style('fluent_support_login_style', $assets . 'admin/css/all_public.css');

        $registrationFields = $this->getSignupFields();

        $registrationForm = '<div class="fst_registration_wrapper"><form class="fs_registration_form" method="post" name="fs_registration_form">';

        foreach ($registrationFields as $fieldName => $registrationField) {
            $registrationForm .= $this->renderField($fieldName, $registrationField);
        }

        $registrationForm .= '</form></div>';

        return $registrationForm;
    }


    private function renderField($fieldName, $field)
    {
        $fieldType = Arr::get($field, 'type');

        $textTypes = ['text', 'email', 'password'];

        $html = '<div class="fst_field_group fst_field_'.$fieldName.'">';
        if($label = Arr::get($field, 'label')) {
            $html .= '<div class="fst_field_label"><label for="'.Arr::get($field, 'id').'">'.$label.'</label></div>';
        }

        if(in_array($fieldType, $textTypes)) {

            $inputAtts = array_filter([
                'type' => esc_attr($fieldType),
                'id' => esc_attr(Arr::get($field, 'id')),
                'placeholder' => esc_attr(Arr::get($field, 'placeholder')),
                'name' => esc_attr($fieldName)
            ]);

            $atts = '';

            foreach ($inputAtts as $attKey => $att) {
                $atts .= $attKey.'="'.$att.'" ';
            }

            $html .= '<div class="fs_input_wrap"><input '.$atts.'/></div>';
        } else {
            return '';
        }

        return $html.'</div>';
    }

    public function getSignupFields()
    {
        return apply_filters('fluent_support/registration_form_fields', [
            'first_name' => [
                'required' => true,
                'type' => 'text',
                'label' => __('First mame', 'fluent-support'),
                'id' => 'fst_first_name',
                'placeholder' => __('First name', 'fluent-support')
            ],
            'last_name' => [
                'type' => 'text',
                'label' => __('Last Name', 'fluent-support'),
                'id' => 'fst_last_name',
                'placeholder' => __('Last name', 'fluent-support')
            ],
            'username' => [
                'required' => true,
                'type' => 'text',
                'label' => __('Username', 'fluent-support'),
                'id' => 'fst_username',
                'placeholder' => __('Username', 'fluent-support')
            ],
            'email' => [
                'required' => true,
                'type' => 'email',
                'label' => __('Email Address', 'fluent-support'),
                'id' => 'fst_email',
                'placeholder' => __('Your Email Address', 'fluent-support')
            ],
            'password' => [
                'required' => true,
                'type' => 'password',
                'label' => __('Password', 'fluent-support'),
                'id' => 'fst_password',
                'placeholder' => __('Password', 'fluent-support')
            ]
        ]);
    }
}
