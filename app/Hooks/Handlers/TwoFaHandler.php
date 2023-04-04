<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;


class TwoFaHandler
{
    public function maybe2FaRedirect($user = null)
    {
        $return = $this->sendAndGet2FaConfirmFormUrl($user, 'both');

        if (!$return) {
            return false;
        }

        $getForm = $this->get2faForm($return);

        wp_send_json([
            'load_2fa' => 'yes',
            'two_fa_form' => $getForm
        ]);
    }

    public function sendAndGet2FaConfirmFormUrl($user, $return = 'url')
    {
        try {
            $twoFaCode = str_pad(random_int(100123, 900987), 6, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            $twoFaCode = str_pad(mt_rand(100123, 900987), 6, 0, STR_PAD_LEFT);
        }

        $string = $user->ID . '-' . wp_generate_uuid4() . mt_rand(1, 99999999);
        $hash = wp_hash_password($string);
        $hash = sanitize_title($hash, '', 'display');
        $hash .= $user->ID . '-' . time();

        $data = array(
            'login_hash' => $hash,
            'user_id' => $user->ID,
            'status' => 'issued',
            'ip_address' => $_SERVER['HTTP_USER_AGENT'],
            'use_type' => 'email_2_fa',
            'used_count' => 0,
            'user_email' => $user->user_email,
            'two_fa_code_hash' => wp_hash_password($twoFaCode),
            'valid_till' => date('Y-m-d H:i:s', current_time('timestamp') + 10 * 30),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $existingRecord = Meta::where('key', $hash)->first();

        if ($existingRecord) {
            $saveSettingsData = Meta::where('key', $hash)->update([
                'value' => maybe_serialize($data)
            ]);
        } else {
            $saveSettingsData = Meta::updateOrInsert([
                'object_type' => 'fs_2fa',
                'key' => $hash,
            ], [
                'value' => maybe_serialize($data)
            ]);
        }

        if (!$saveSettingsData) {
            return false;
        }
        $data['twoFaCode'] = $twoFaCode;
        $this->send2FaEmail($data, $user, '');

        return [
            'redirect_to' => add_query_arg([
                'fs_2fa' => 'email',
                'login_hash' => $hash,
                'action' => 'fs_2fa_email'
            ], wp_login_url()),
            'login_hash' => $hash,
        ];
    }

    public function verify2FaEmailCode($data)
    {
        $redirectUrl = Helper::getPortalBaseUrl();

        $code = $data['login_passcode'];
        $hash = $data['login_hash'];

        if (!$code || !$hash) {
            wp_send_json([
                'message' => __('Please provide a valid login code', 'fluent-support')
            ], 423);
        }

        $logHash = Meta::where('key', $hash)->first();
        $logHash = maybe_unserialize($logHash->value, []);

        if (!$logHash) {
            wp_send_json([
                'message' => __('Your provided code or url is not valid', 'fluent-support')
            ], 423);
        }
        if (!wp_check_password($code, $logHash['two_fa_code_hash'])) {

            $logHash['used_count'] += 1;

            Meta::where('key', $hash)->update([
                'value' => maybe_serialize($logHash)
            ]);

            return false;
        }

        if (strtotime($logHash['created_at']) < current_time('timestamp') - 300 || $logHash['used_count'] > 5 || $logHash['status'] != 'issued') {
            wp_send_json([
                'message' => __('Sorry, your login code has been expired. Please try to login again', 'fluent-support')
            ], 423);
        }
        $user = get_user_by('email', $logHash['user_email']);

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        if (is_user_logged_in()) {
            $logHash['status'] = 'used';

            Meta::where('key', $hash)->update([
                'value' => maybe_serialize($logHash)
            ]);
        }

        wp_send_json([
            'redirect' => $redirectUrl
        ], 200);
    }

    private function send2FaEmail($data, $user, $autoLoginUrl = false)
    {
        $emailTo = $user->user_email;
        $emailSubject = sprintf(__('Your Login code for %1s - %d', 'fluent-support'), get_bloginfo('name'), $data['twoFaCode']);

        $emailLines = [
            '<div style="font-family: Arial, sans-serif; font-size: 16px; line-height: 24px; color: #000; padding: 20px;">',
            sprintf(__('Hello %s,', 'fluent-support'), $user->display_name),
            sprintf(__('Someone requested to login to %s and here is the Login code that you can use in the login form', 'fluent-support'), get_bloginfo('name')),
            '<br/><br/>',
            '<div style="background-color: #f1f1f1; padding: 15px; border-radius: 5px;">',
            '<h2 style="font-size: 18px; margin-bottom: 10px;">' . __('Your Login Code:', 'fluent-support') . '</h2>',
            '<p style="font-size: 22px; padding: 10px; text-align: center; background-color: #fff; border: 2px solid #333; border-radius: 5px; display: inline-block; margin-bottom: 10px;">' . $data['twoFaCode'] . '</p>',
            sprintf(__('This code will expire in %d minutes and can only be used once.', 'fluent-support'), 5),
            '</div>',
            '<br/><br/>',
            '<hr style="border: none; height: 1px; background-color: #dcdcdc; margin: 20px 0;"/>',
            '<p style="font-size: 14px;">' . __('If you did not request this login code, please ignore this email.', 'fluent-support') . '</p>',
            '</div>'
        ];

        $emailBody = implode("\r\n", $emailLines);

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($emailTo, $emailSubject, $emailBody, $headers);
    }

    public function get2faForm($data = [])
    {
        ob_start();
        ?>

        <form
            style="margin-top: 20px; padding: 20px; font-weight: 400; overflow: hidden; background: #f6f6f6; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,.15);"
            class="fs_2fa" id="fs_2fa_form">
            <input type="hidden" name="login_hash" value="<?php echo esc_attr($data['login_hash']); ?>"/>
            <div style="margin-bottom: 10px;">
                <?php _e('Please check your email inbox and enter the 2-factor authentication code below:', 'fluent-support'); ?>
            </div>
            <div style="margin-bottom: 20px;">
                <label for="login_passcode"><?php _e('Two-Factor Authentication Code', 'fluent-support'); ?></label>
                <div>
                    <input
                        style="font-size: 14px; box-sizing: border-box; padding: 8px; border: 1px solid #ccc; border-radius: 3px; width: 100%;"
                        placeholder="<?php _e('Login Code', 'fluent-support'); ?>" type="text" name="login_passcode"
                        id="login_passcode" class="input" size="20"/>
                </div>
            </div>
            <div>
                <button
                    style="display: inline-block; cursor: pointer; border: 0; background: #2271b1; color: #fff; text-decoration: none; text-shadow: none; min-height: 32px; padding: 8px 24px; width: 100%; font-size: 14px; border-radius: 3px;"
                    id="fs_2fa_confirm" type="submit">
                    <?php _e('Login', 'fluent-support'); ?>
                </button>
            </div>
        </form>

        <?php

        return ob_get_clean();
    }

}
