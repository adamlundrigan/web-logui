<?php

function rest_client($n, $async = false, $username = null, $password = null)
{
  $settings = Settings::Get();
  $r = $settings->getNode($n);
  if (!$r)
    throw new Exception("Node not configured");

  return $r->rest($async, $username, $password);
}