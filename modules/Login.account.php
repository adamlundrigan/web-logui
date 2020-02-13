<?php

/*
 * authenticate users against static users in the configuration (settings.php)
 */

function halon_login_account($username, $password, $method, $settings)
{
  if ($username === $method['username'] && $password === $method['password'])
  {
    $result = array();
    $result['username'] = $method['username'];
    $result['source'] = 'local';
    $result['access'] = is_array($method['access']) ? $method['access'] : [];
    $result['disabled_features'] = $method['disabled_features'];
    return $result;
  }
  return false;
}
