<?php

panic('Unsupported API call');

function panic($message)
{
  http_response_code(503);
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => $message)));
}

function success_json($data)
{
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode($data));
}

function success_text($data)
{
  header('Content-Type: text/plain; charset=UTF-8');
  die($data);
}
