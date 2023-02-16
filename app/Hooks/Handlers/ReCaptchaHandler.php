<?php

namespace FluentSupport\App\Hooks\Handlers;


class ReCaptchaHandler
{

    public static function validateRecaptcha($token,$secret = null,$recaptchaVersion = null)
    {

        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

            if(!$secret){
                $reCaptchaData = get_option('_fs_recaptcha_data');
                $recaptchaVersion = $reCaptchaData["reCaptcha_version"];
                $secret = $reCaptchaData['secretKey'];
            }
            
            $response = wp_remote_post($verifyUrl, [
                'method' => 'POST',
                'body'   => [
                    'secret'   => $secret,
                    'response' => $token
                ],
            ]);

            if (is_wp_error($response)) {
                return false;
            }
        
            $result = json_decode(wp_remote_retrieve_body($response));
        
            if ($recaptchaVersion === 'recaptcha_v3') {
                $score = $result->score;
                $checkScore = apply_filters('fluent_support/recaptcha_v3_ref_score', 0.5);

                return $score >= $checkScore;
            }

            return $result->success;
    }
}