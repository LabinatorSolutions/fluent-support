<?php

namespace FluentSupport\App\Exceptions;

use FluentSupport\Framework\Validator\ValidationException;

/**
 * A user-facing API error. Route::callback renders a ValidationException as its
 * errors map, so the response is {message} (plus any extra keys) with the status
 * as the HTTP status: the body Helper::getSafeErrorMessage() already sends.
 */
class ApiException extends ValidationException
{
    /**
     * @param string $message
     * @param int    $status 400 or higher; lower values are sent as 422.
     * @param array  $extra  More keys for the response body.
     */
    public function __construct($message, $status = 422, array $extra = [])
    {
        parent::__construct($message, $status, null, array_merge(['message' => $message], $extra));
    }

    /**
     * @param string $message
     * @return static
     */
    public static function forbidden($message = '')
    {
        return new static($message ?: __('You do not have permission to do that.', 'fluent-support'), 403);
    }

    /**
     * @param string $message
     * @return static
     */
    public static function notFound($message = '')
    {
        return new static($message ?: __('The requested item was not found.', 'fluent-support'), 404);
    }

    /**
     * @param string $message
     * @return static
     */
    public static function invalid($message)
    {
        return new static($message, 422);
    }
}
