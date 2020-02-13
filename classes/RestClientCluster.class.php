<?php

// Async one-command-only wrapper
class RestClientCluster
{
	private $hosts = [];
	private $errors = [];
	public function __construct($hosts)
	{
		foreach ($hosts as $host)
			$this->hosts[$host->getID()] = $host;
	}
	public function& operation($path, $method = 'GET', $query = null, $body = null)
	{
		$futures = [];
		foreach ($this->hosts as $host => $client)
		{
			try {
				$futures[$host] = $client->operation(is_array($path) ? $path[$host] : $path, $method, $query, $body);
			} catch (RestException $e) {
				/* don't really care */
			}
		}
		$result = [];
		foreach ($futures as $host => $future)
		{
			try {
				$result[$host] = $future->get();
			} catch (RestException $e) {
				$this->errors[$host] = $e;
			}
		}
		return $result;
	}
	public function unregister($id)
	{
		foreach ($this->hosts as $i => $client) {
			if ($id == $i)
				unset($this->hosts[$i]);
		}
	}
	function hasErrors()
	{
		return !empty($this->errors);
	}
	function errors()
	{
		return $this->errors;
	}
}
