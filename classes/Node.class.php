<?php

class Node
{
  private $id;
  private $address;
  private $username;
  private $password;
  private $serial;
  private $tls;
  private $timeout;

  public function __construct($id, $address, $username = null, $password = null, $serial = null, $tls = array(), $timeout = null)
  {
    $this->id = $id;
    $this->address = $address;
    $this->username = $username;
    $this->password = $password;
    $this->serial = $serial;
    $this->tls = $tls;
    $this->timeout = is_numeric($timeout) ? (int)$timeout : 5;
  }

  public function rest($async = false, $username = null, $password = null, $serial = null)
  {
    $session = Session::Get();

    if($username === null) $username = $this->getUsername();
    if($password === null) $password = $this->getPassword();

    $options = array(
      'host' => $this->getAddress(),
      'username' => $username,
      'password' => $password,
      'timeout' => $this->timeout,
      'tls' => $this->tls
      );

    if ($async)
      return new ARestClient($options);
    return new RestClient($options);
  }

  public function getId()
  {
    return $this->id;
  }

  public function getAddress()
  {
    return $this->address;
  }

  public function getUsername()
  {
    return $this->username;
  }

  public function getPassword()
  {
    return $this->password;
  }

  public function getSerial($autoload = false)
  {
    if(!$this->serial && $autoload)
      $this->serial = $this->rest()->operation('/license')->body->serial;
    return $this->serial;
  }
}
