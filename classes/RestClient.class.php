<?php

require_once dirname(__FILE__).'/ARestClient.class.php';
require_once dirname(__FILE__).'/RestResponse.class.php';

class RestClient extends ARestClient
{
	public function operation($path, $method = 'GET', $query = null, $body = null)
	{
		$response = parent::operation($path, $method, $query, $body)->get();
		return new RestResponse($response->code, $response->body, $response->headers);
	}
}
