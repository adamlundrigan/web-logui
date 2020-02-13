<?php

/**
 * Singleton wrapper for the user's session.
 */
class Session
{
  private $authenticated = null;
  private $username = null;
  private $access = null;
  private $disabled_features = null;
  private $navbar_hide = false;
  private $textlog_access = false;

  // elasticsearch
  private $available_indices = [];

  /**
   * Returns a shared Session instance.
   */
  public static function Get()
  {
    static $inst = null;
    if ($inst === null)
      $inst = new Session();
    return $inst;
  }

  /**
   * Private constructor; use Session::Get().
   */
  private function __construct()
  {
    $session_name = Settings::Get()->getSessionName();
    if ($session_name)
      session_name($session_name);

    session_start();

    $this->authenticated = $_SESSION['authenticated'];
    $this->username = $_SESSION['username'];
    $this->access = $_SESSION['access'];

    if(isset($_SESSION['disabled_features']))
      $this->disabled_features = $_SESSION['disabled_features'];

    if (isset($_SESSION['available_indices']))
      $this->available_indices = $_SESSION['available_indices'];

    if (isset($_SESSION['textlog_access']))
      $this->textlog_access = $_SESSION['textlog_access'];

    $this->navbar_hide = isset($_SESSION['navbar_hide']) ? $_SESSION['navbar_hide'] : false;
  }

  /**
   * Returns true if the user is authenticated.
   */
  public function isAuthenticated()
  {
    return $this->authenticated === true;
  }

  /**
   * Returns the user's username.
   *
   * What exactly this means is a bit dependent on the configured
   * authentication methods - it may be basically anything, but should be
   * written out as-is.
   */
  public function getUsername()
  {
    return $this->username;
  }

  /**
   * Returns the user's access parameters (permissions).
   *
   * This is an array with several keys that, if given, restrict the user to
   * only the given realms. An empty array means no access.
   *
   * Possible keys:
   *
   *   - mail: Can only see records involving the given email address(es)
   *   - domain: Can only see records involving the given domain(s)
   *
   * @param $key The key to retrieve, or NULL for the whole array
   */
  public function getAccess($key=NULL)
  {
    return $key !== NULL ? $this->access[$key] : ($this->access ?: array());
  }

  public function checkAccessMail($mail)
  {
    // super admin
    if (count($this->access) == 0)
      return true;
    // mail access
    $access_mail = (is_array($this->access['mail']) ? $this->access['mail'] : array());
    if (in_array($mail, $access_mail, true))
      return true;
    // domain access
    $access_domain = (is_array($this->access['domain']) ? $this->access['domain'] : array());
    $mail = explode('@', $mail);
    if (count($mail) != 2)
      return false;
    if (in_array($mail[1], $access_domain, true))
      return true;
    return false;
  }

  public function checkAccessDomain($domain)
  {
    // super admin
    if (count($this->access) == 0)
      return true;
    // domain access
    $access_domain = (is_array($this->access['domain']) ? $this->access['domain'] : array());
    if (in_array($domain, $access_domain, true))
      return true;
    return false;
  }

  public function checkAccessAll()
  {
    // super admin
    if (is_array($this->access) && count($this->access) == 0)
      return true;
    return false;
  }

  /**
   * Returns the features that are disabled for the user.
   */
  public function getDisabledFeatures()
  {
    return (is_array($this->disabled_features) ? $this->disabled_features : array());
  }

  /**
   * Check if a specific feature is disabled for the user.
   */
  public function checkDisabledFeature($feature)
  {
    if (is_array($this->disabled_features) and in_array($feature, $this->disabled_features))
      return true;
    return false;
  }

  public function checkTextlogAccess()
  {
    return $this->textlog_access;
  }

  /**
   * Cache available indices for Elasticsearch, only update if needed
   */
  public function setElasticsearchIndices($indices)
  {
    $this->available_indices = $indices;
    $_SESSION['available_indices'] = $indices;
  }

  /**
   * Returns current indices cache for Elasticsearch
   */
  public function getElasticsearchIndices()
  {
    return $this->available_indices;
  }

  public function getNavbarHide()
  {
    return $this->navbar_hide;
  }
}
