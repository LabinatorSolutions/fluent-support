<?php

namespace FluentSupport\Framework\Response;

class Response
{
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function json($data = null, $code = 200)
    {
        return wp_send_json($data, $code);
    }

    public function send($data = null, $code = 200)
    {
        return new \WP_REST_Response($data, $code);
    }

    public function sendSuccess($data = null, $code = 200)
    {
         return new \WP_REST_Response($data, $code);
    }

    public function sendError($data = null, $code = 423)
    {
        if (!$code || $code < 400 ) {
            $code = 423;
        }

        return new \WP_REST_Response($data, $code);
    }

    public function wpErrorToResponse(\WP_Error $wpError, $code = 500)
    {
        $errorData = $wpError->get_error_data();

        if (is_array($errorData) && isset($errorData['status'])) {
            $code = $errorData['status'];
        }

        $errors = [];

        foreach ((array) $wpError->errors as $code => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = [
                    'code'    => $code,
                    'message' => $message,
                    'data'    => $wpError->get_error_data($code),
                ];
            }
        }

        $data = array_shift($errors);

        if (count($errors)) {
            $data['additional_errors'] = $errors;
        }

        return new \WP_REST_Response($data, $code);
    }
}
