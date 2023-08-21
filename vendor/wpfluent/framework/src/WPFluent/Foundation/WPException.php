<?php

namespace FluentSupport\Framework\Foundation;

use Exception;
use WP_Error;

/**
 * Exception Class.
 */
class WPException extends Exception {

	protected $code = 400;
	protected $message = '';
	protected $errorData = [];

	public function __construct(WP_Error $wpError)
	{
		$this->wpError = $wpError;
		$this->errorData = $wpError->get_error_data();
		$this->message = $wpError->get_error_message();

		if (is_array($this->errorData) && isset($this->errorData['status'])) {
			$this->code = $this->errorData['status'];
		}

		parent::__construct($message, $code);
	}

	public function getCode()
	{
		return $this->code;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public function errors()
	{
		return $this->toArray();
	}

	protected function toArray()
	{
		$errors = [];

		foreach ((array) $this->wpError->errors as $code => $messages) {
			foreach ((array) $messages as $message) {
				$errors[] = [
					'code'    => $code,
					'message' => $message,
					'data'    => $this->wpError->get_error_data($code),
				];
			}
		}

		return $errors;
	}
}
