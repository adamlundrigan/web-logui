<?php

require_once dirname(__FILE__).'/RestException.class.php';

class RestResponse
{
	public $code = null;
	public $body = null;
	public $headers = null;

	public function __construct($code, $body = null, $headers = [])
	{
		$this->code = $code;
		$this->body = $body;
		$this->headers = $headers;
	}

	public function get()
	{
		$response = new StdClass;
		$response->code = $this->code;
		if (isset($this->body)) $response->body = $this->body;
		$response->headers = $this->headers;
		return $response;
	}
}

class RestResponseException {
	private $exception = null;

	public function __construct(RestException $exception)
	{
		$this->exception = $exception;
	}

	public function get()
	{
		throw $this->exception;
	}
}