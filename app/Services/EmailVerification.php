<?php
namespace FluentSupport\App\Services;
class EmailVerification
{
    public function triggerEmailVerification($email=null)
    {
        if(!$email) {
            $email = Helper::getCurrentCustomer()->email;
        }
        $emailBody = $this->emailBody($email);
        $emailSubject = __('Email Verification', 'fluent-support');
        $emailSubject = apply_filters('fluent_support/email_verification_subject', $emailSubject, $email);
        $emailHeaders = apply_filters('fluent_support/email_verification_headers', [], $email);
        wp_mail($email, $emailSubject, $emailBody, $emailHeaders);
    }

    public function verifyEmail($code)
    {
        $email = Helper::getCurrentCustomer()->email;
        $verificationCode = Helper::getOption($email.'_verification_code');

        if($code === $verificationCode) {
            $customer = \FluentSupport\App\Models\Customer::where('email', $email)->first();
            if($customer) {
                $customer->status = 'active';
                $customer->save();
            }
            Helper::deleteOption($email.'_verification_code');
            return true;
        } else{
            throw new \Exception(__('Invalid verification code', 'fluent-support'));
        }
    }

    private function emailBody($email)
    {
        $verificationCode = wp_generate_password(6, false);

        $verificationLink = add_query_arg([
            'email' => $email,
            'code'  => $verificationCode,
            'fs_action' => 'verify_email'
        ], Helper::getPortalBaseUrl());

        Helper::updateOption($email.'_verification_code', $verificationCode);

        $firstName = Helper::getCurrentCustomer()->first_name;
        $emailText = sprintf('<p>Hello %s,</p><br> <p>Please click on the link below to verify your email address.</p><br>', $firstName, $verificationLink);
        $emailText .= sprintf('<a href="%s" style="text-decoration: none;"><button style="background: #0fb779; color: #fff; border: none; padding: 12px; border-radius: 2px; cursor: pointer;">Click Here</button></a><br>', $verificationLink, $verificationLink);
        $emailText .= sprintf('<p>Alternatively, you can use the below code to verify:</p><br>');
        $emailText .= sprintf('<div style="background:#e9e9e9;padding: 10px;text-align: center;letter-spacing: 14px;font-size: 20px;border: 1px dashed;"><strong>%s</strong></div><br>', $verificationCode);
        $emailText .= sprintf('<p>If you didn\'t request this verification, please ignore this email. This verification code will be expired in one hour.</p><br>');
        $emailText .= sprintf('<p>Thanks</p>');

        return $emailText;
    }

}
