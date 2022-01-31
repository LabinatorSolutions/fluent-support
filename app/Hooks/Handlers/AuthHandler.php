<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class AuthHandler
{

    protected $loaded = false;

    public function init()
    {
        add_shortcode('fluent_support_login', array($this, 'loginForm'));
        add_shortcode('fluent_support_signup', array($this, 'registrationForm'));
        add_shortcode('fluent_support_auth', array($this, 'authForm'));
    }

    /**
     * loginForm will generate html for login form
     * @param $attributes
     * @return string
     */
    public function loginForm($attributes)
    {
        if (get_current_user_id()) {
            return '';
        }

        $this->loadAssets();
        $attributes = $this->getShortcodes($attributes);
        $this->handleAlreadyLoggedIn($attributes);

        $return = '<div id="fst_login_form" class="fst_login_wrapper">';

        $loginArgs = apply_filters('fluent_support/login_form_args', [
            'echo'           => false,
            'redirect'       => Helper::getPortalBaseUrl(),
            'remember'       => true,
            'value_remember' => true,
        ]);

        $return .= wp_login_form($loginArgs);

        if ($attributes['show-signup'] == 'true') {
            $return .= '<p style="text-align: center">'
                . __('Not registered?', 'fluent-support')
                . ' <a href="#" id="fs_show_signup">'
                . __('Create an Account', 'fluent-support')
                . '</a></p>';
        }

        $return .= '</div>';
        return $return;
    }

    /**
     * registrationForm method will generate html for sign up form
     * @param $attributes
     * @return string
     */
    public function registrationForm($attributes)
    {
        if (get_current_user_id()) {
            return '';
        }

        $attributes = $this->getShortcodes($attributes);
        $this->handleAlreadyLoggedIn($attributes);

        $registrationFields = static::getSignupFields();
        $hide = $attributes['hide'] == 'true' ? 'hide' : '';

        $this->loadAssets($hide);

        $registrationForm = '<div class="fst_registration_wrapper ' . $hide . '"><form id="fstRegistrationForm" class="fs_registration_form" method="post" name="fs_registration_form">';

        foreach ($registrationFields as $fieldName => $registrationField) {
            $registrationForm .= $this->renderField($fieldName, $registrationField);
        }

        $registrationForm .= '<input type="hidden" name="__redirect_to" value="' . $attributes['redirect-to'] . '">';
        $registrationForm .= '<input type="hidden" name="_fsupport_signup_nonce" value="' . wp_create_nonce('fluent_support_signup_nonce') . '">';
        $registrationForm .= '<button type="submit" id="fst_submit">' . $this->submitBtnLoadingSvg() . '<span>' . __('Signup', 'fluent-support') . '</span></button>';

        $registrationForm .= '</form>';

        if ($hide) {
            $registrationForm .= '<p style="text-align: center">'
                . __('Already have an account?', 'fluent-support')
                . ' <a href="#" id="fs_show_login">'
                . __('Login', 'fluent-support')
                . '</a></p>';
        }

        $registrationForm .= '</div>';

        return $registrationForm;
    }

    /**
     * authForm will render the login form html
     * @param $attributes
     * @return string
     */
    public function authForm($attributes)
    {
        $authForm = '<div class="fst_auth_wrapper">';

        $authForm .= do_shortcode('[fluent_support_login show-signup=true]');
        $authForm .= do_shortcode('[fluent_support_signup hide=true]');

        $authForm .= '</div>';

        return $authForm;
    }

    /**
     * renderField method will generate html for a field
     * @param $fieldName
     * @param $field
     * @return string
     */
    private function renderField($fieldName, $field)
    {
        $fieldType = Arr::get($field, 'type');
        $isRequired = Arr::get($field, 'required');
        $isRequired = $isRequired ? 'is-required' : '';

        $textTypes = ['text', 'email', 'password'];

        $html = '<div class="fst_field_group fst_field_' . $fieldName . '">';
        if ($label = Arr::get($field, 'label')) {
            $html .= '<div class="fst_field_label ' . $isRequired . '"><label for="' . Arr::get($field, 'id') . '">' . $label . '</label></div>';
        }

        if (in_array($fieldType, $textTypes)) {

            $inputAtts = array_filter([
                'type'        => esc_attr($fieldType),
                'id'          => esc_attr(Arr::get($field, 'id')),
                'placeholder' => esc_attr(Arr::get($field, 'placeholder')),
                'name'        => esc_attr($fieldName)
            ]);

            $atts = '';

            foreach ($inputAtts as $attKey => $att) {
                $atts .= $attKey . '="' . $att . '" ';
            }

            if (Arr::get($field, 'required')) {
                $atts .= 'required';
            }

            $html .= '<div class="fs_input_wrap"><input ' . $atts . '/></div>';
        } else {
            return '';
        }

        return $html . '</div>';
    }

    /**
     * getSignupFields method will return the list of fields that will be used for sign up form
     * @return mixed
     */
    public static function getSignupFields()
    {
        return apply_filters('fluent_support/registration_form_fields', [
            'first_name' => [
                'required'    => true,
                'type'        => 'text',
                'label'       => __('First name', 'fluent-support'),
                'id'          => 'fst_first_name',
                'placeholder' => __('First name', 'fluent-support')
            ],
            'last_name'  => [
                'type'        => 'text',
                'label'       => __('Last Name', 'fluent-support'),
                'id'          => 'fst_last_name',
                'placeholder' => __('Last name', 'fluent-support')
            ],
            'username'   => [
                'required'    => true,
                'type'        => 'text',
                'label'       => __('Username', 'fluent-support'),
                'id'          => 'fst_username',
                'placeholder' => __('Username', 'fluent-support')
            ],
            'email'      => [
                'required'    => true,
                'type'        => 'email',
                'label'       => __('Email Address', 'fluent-support'),
                'id'          => 'fst_email',
                'placeholder' => __('Your Email Address', 'fluent-support')
            ],
            'password'   => [
                'required'    => true,
                'type'        => 'password',
                'label'       => __('Password', 'fluent-support'),
                'id'          => 'fst_password',
                'placeholder' => __('Password', 'fluent-support')
            ]
        ]);
    }

    protected function submitBtnLoadingSvg()
    {
        $loadingIcon = '<svg version="1.1" id="loader-1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
           width="40px" height="20px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
        <path fill="#000" d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
          <animateTransform attributeType="xml"
            attributeName="transform"
            type="rotate"
            from="0 25 25"
            to="360 25 25"
            dur="0.6s"
            repeatCount="indefinite"/>
          </path>
        </svg>';

        return apply_filters('fluent_support/signup_loading_icon', $loadingIcon);
    }

    protected function getShortcodes($attributes)
    {
        $shortCodeDefaults = apply_filters('fluent_support/auth_shortcode_defaults', [
            'auto-redirect' => false,
            'redirect-to'   => Helper::getPortalBaseUrl(),
            'hide'          => false,
            'show-signup'   => false
        ]);

        return shortcode_atts($shortCodeDefaults, $attributes);
    }

    protected function handleAlreadyLoggedIn($attributes)
    {
        if (get_current_user_id() && !wp_is_json_request() && is_singular()) {
            if ($attributes['auto-redirect'] === 'true') {
                $redirect = $attributes['redirect-to'];
                ?>
                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function () {
                        var redirect = "<?php echo $redirect; ?>";
                        window.location.replace(redirect);
                    });
                </script>
                <?php
            }

            die();
        }
    }

    public function loadAssets($hide = '')
    {
        if ($this->loaded) {
            return false;
        }

        $app = App::getInstance();
        $assets = $app['url.assets'];
        wp_enqueue_style('fluent_support_login_style', $assets . 'admin/css/all_public.css', [], FLUENT_SUPPORT_VERSION);
        wp_enqueue_script('fluent_support_login_helper', $assets . 'portal/js/login_helper.js', [], FLUENT_SUPPORT_VERSION);

        wp_localize_script('fluent_support_login_helper', 'fluentSupportPublic', [
            'signup' => rest_url($app->config->get('app.rest_namespace') . '/' . $app->config->get('app.rest_version')) . '/signup',
            'login'  => rest_url($app->config->get('app.rest_namespace') . '/' . $app->config->get('app.rest_version')) . '/login',
            'nonce'  => wp_create_nonce('wp_rest'),
            'hide'   => $hide,
            'fsupport_login_nonce' => wp_create_nonce('fsupport_login_nonce')
        ]);


        $this->loaded = true;
    }
}
