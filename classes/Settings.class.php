<?php

/**
 * Singleton wrapper for the settings file (settings.php).
 */
class Settings
{
  private $settings = array();
  private $database = null;
  private $dbCredentials = array('dns' => null);

  private $nodeCredentials = array();
  private $nodes = array();
  private $nodeDefaultTimeout = 5;

  private $apiKey = null;
  private $authSources = array(array('type' => 'account'));

  private $publicURL = null;

  private $pageName = "Halon log server";
  private $theme = null;
  private $brandLogo = null;
  private $brandLogoHeight = null;
  private $loginText = null;
  private $displayScores = false;
  private $displayTextlog = false;
  private $displayStats = false;
  private $displayListener = array('mailserver:inbound' => "Inbound");
  private $displayTransport = array('mailtransport:outbound' => "Internet");
  private $displayIndexColumns = ['action', 'from', 'to', 'subject', 'status', 'scores', 'date'];

  private $geoIP = false;
  private $geoIPDatabase = null;

  private $sessionName = null;
  private $sessionNavbarHide = false;

  private $elasticsearch = [];
  private $elasticsearchClient = null;

  private $elasticsearchMappings = [
    'id' => '_id',
    'owner' => 'owner',
    'ownerdomain' => 'ownerdomain',
    'msgid' => 'messageid',
    'msglistener' => 'serverid',
    'msgfromserver' => 'senderip',
    'msgsenderhelo' => 'senderhelo',
    'msgtlsstarted' => 'tlsstarted',
    'msghelo' => 'senderhelo',
    'msgsasl' => 'saslusername',
    'msgaction' => 'action',
    'msgfrom' => 'sender',
    'msgfromdomain' => 'senderdomain',
    'msgto' => 'recipient',
    'msgtodomain' => 'recipientdomain',
    'msgtransport' => 'transportid',
    'msgsubject' => 'subject',
    'msgsize' => 'size',
    'msgactionid' => 'actionid',
    'msgdescription' => 'reason',
    'msgts0' => 'receivedtime',
    'serialno' => 'serial',
    'tlsstarted' => 'tlsstarted',
    'score_rpd' => 'score_rpd',
    'score_rpd_refid' => 'score_rpd_refid',
    'score_rpdav' => 'score_rpdav',
    'scores' => [
      'key' => 'scores',
      'value' => [
        'sa' => 'sa',
        'sa_rules' => 'sa_rules',
        'kav' => 'kav',
        'clam' => 'clam'
      ]
    ],
    'queue' => [
      'key' => 'queue',
      'value' => [
        'action' => 'action',
        'errormsg' => 'errormsg',
        'retry' => 'retry',
        'retries' => 'retries'
      ]
    ],
    'metadata' => 'metadata'
  ];
  private $elasticsearchMetadataFilter = null;

  private $statsSettings = [];
  private $statsAggregations = [];
  private $statsColor = [];
  private $statsLabelColor = [];
  private $statsDefaultView = [];
  private $digestToAll = false;
  private $digestSecret = null;
  private $mailSender = null;

  /**
   * Returns a shared Settings instance.
   */
  public static function Get()
  {
    static $inst = null;
    if ($inst === null)
      $inst = new Settings();
    return $inst;
  }

  /**
   * Private constructor; use Settings::Get().
   */
  private function __construct()
  {
    $settings = array();
    require BASE.'/settings.php';

    $this->settings = $settings;

    $this->extract($this->nodeCredentials, 'node');
    $this->extract($this->nodeDefaultTimeout, 'node-default-timeout');
    $this->extract($this->dbCredentials, 'database');
    $this->extract($this->apiKey, 'api-key');
    $this->extract($this->publicURL, 'public-url');
    $this->extract($this->theme, 'theme');
    $this->extract($this->brandLogo, 'brand-logo');
    $this->extract($this->brandLogoHeight, 'brand-logo-height');
    $this->extract($this->pageName, 'pagename');
    $this->extract($this->loginText, 'logintext');
    $this->extract($this->displayScores, 'display-scores');
    $this->extract($this->displayTextlog, 'display-textlog');
    $this->extract($this->displayStats, 'display-stats');
    $this->extract($this->displayListener, 'display-listener');
    $this->extract($this->displayTransport, 'display-transport');
    $this->extract($this->displayIndexColumns, 'display-index-columns');
    $this->extract($this->authSources, 'authentication');
    $this->extract($this->sessionName, 'session-name');
    $this->extract($this->geoIP, 'geoip');
    $this->extract($this->geoIPDatabase, 'geoip-database');
    $this->extract($this->sessionNavbarHide, 'session-navbar-hide');
    $this->extract($this->elasticsearch, 'elasticsearch');

    if (file_exists(BASE.'/settings-stats.php'))
      require_once BASE.'/settings-stats.php';
    else
      require_once BASE.'/settings-stats-default.php';
    $this->statsSettings = $statsSettings;

    $this->extract($this->statsAggregations, 'aggregations', $this->statsSettings);
    $this->extract($this->statsColor, 'color', $this->statsSettings);
    $this->extract($this->statsLabelColor, 'label-color', $this->statsSettings);
    $this->extract($this->statsDefaultView, 'default-view', $this->statsSettings);

    foreach ($this->nodeCredentials as $id => $cred) {
      $username = isset($cred['username']) ? $cred['username'] : null;
      $password = isset($cred['password']) ? $cred['password'] : null;
      $serial = isset($cred['serialno']) ? $cred['serialno'] : null;
      $tls = isset($cred['tls']) ? $cred['tls'] : array();
      $timeout = isset($cred['timeout']) ? (int)$cred['timeout'] : $this->nodeDefaultTimeout;
      $this->nodes[] = new Node($id, $cred['address'], $username, $password, $serial, $tls, $timeout);
    }

    $mappings = [];
    $this->extract($mappings, 'elasticsearch-mappings');
    if (count($mappings) > 0)
      $this->elasticsearchMappings = array_merge($this->elasticsearchMappings, $mappings);
    $this->extract($this->elasticsearchMetadataFilter, 'elasticsearch-metadata-filter');

    if(!$this->publicURL)
    {
      $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? "https" : "http";
      $url = $protocol."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
      $this->publicURL = preg_replace("#[^/]*$#", "", $url);
    }

    $this->extract($this->digestToAll, 'digest.to-all');
    $this->extract($this->digestSecret, 'digest.secret');
    $this->extract($this->mailSender, 'mail.from');
  }

  /**
   * Extracts a value from the $this->settings array.
   */
  private function extract(&$out, $key, $settings = null)
  {
    $parts = explode('.', $key);
    if (!$settings)
      $tmp = $this->settings;
    else
      $tmp = $settings;
    foreach ($parts as $part) {
      $tmp = isset($tmp[$part]) ? $tmp[$part] : null;
      if($tmp === null) return;
    }

    $out = $tmp;
  }

  public function getElasticsearch()
  {
    if (!$this->elasticsearchClient) {
      if (!$this->elasticsearch)
        return null;

      $hosts = $this->elasticsearch['host'];

      $username = isset($this->elasticsearch['username']) ? $this->elasticsearch['username'] : null;
      $password = isset($this->elasticsearch['password']) ? $this->elasticsearch['password'] : null;
      $this->elasticsearchClient = new Elasticsearch($hosts, $this->elasticsearch['index'], $username, $password);
    }
    return $this->elasticsearchClient;
  }

  /**
   * Returns elasticsearch mappings
   */
  public function getElasticsearchMappings()
  {
    return $this->elasticsearchMappings;
  }

  public function getElasticsearchMetadataFilter() {
    return $this->elasticsearchMetadataFilter;
  }

  /**
   * Returns the credentials for all configured nodes.
   */
  public function getNodeCredentials()
  {
    return $this->nodeCredentials;
  }

  /**
   * Returns a list of all configured nodes.
   */
  public function getNodes()
  {
    return $this->nodes;
  }

  /**
   * Returns a specific node from the list, or null if there's no such node.
   */
  public function getNode($i)
  {
    if($i < count($this->nodes))
      return $this->nodes[$i];
    return null;
  }
  /**
   * Returns a specific node from the list by serial, or null if there's no such node.
   */
  public function getNodeBySerial($serial)
  {
    foreach ($this->nodes as $node)
    {
      try {
        if($node->getSerial(true) == $serial)
          return $node;
      } catch (RestException $e) {}
    }
    return null;
  }

  /**
   * Returns a database wrapper object.
   */
  public function getDatabase()
  {
    if(!$this->database)
    {
      $credentials = $this->dbCredentials;

      if(!$credentials['dsn'])
        return null;

      $dsn = $credentials['dsn'];
      $username = isset($credentials['user']) ? $credentials['user'] : null;
      $password = isset($credentials['password']) ? $credentials['password'] : null;
      $this->database = new PDO($dsn, $username, $password);
    }

    return $this->database;
  }

  /**
   * Returns the API key.
   */
  public function getAPIKey()
  {
    return $this->apiKey;
  }
  /**
   * Returns all configured authentication sources.
   */
  public function getAuthSources()
  {
    return $this->authSources;
  }

  /**
   * Returns the site's public URL (autodetected by default).
   */
  public function getPublicURL()
  {
    return $this->publicURL;
  }

  /**
   * Returns the theme.
   */
  public function getTheme()
  {
    if (file_exists('themes/'.$this->theme.'/bootstrap.min.css'))
      return 'themes/'.$this->theme.'/bootstrap.min.css';
    if ($this->theme)
      return $this->theme;
    return 'vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
  }

  /**
   * Returns the brand logo
   */
  public function getBrandLogo()
  {
    return $this->brandLogo;
  }

  /**
   * Returns the brand logo height
   */
  public function getBrandLogoHeight()
  {
    return $this->brandLogoHeight / 2;
  }

  /**
   * Returns the page name.
   */
  public function getPageName()
  {
    return $this->pageName;
  }

  /**
   * Returns some text to display at the top of the login form, or null.
   */
  public function getLoginText()
  {
    return $this->loginText;
  }

  /**
   * Returns whether scores should be displayed.
   */
  public function getDisplayScores()
  {
    return $this->displayScores;
  }

  /**
   * Returns whether the text log should be displayed.
   */
  public function getDisplayTextlog()
  {
    return $this->displayTextlog;
  }

  /**
   * Returns whether the stats tab should be displayed.
   */
  public function getDisplayStats()
  {
    return $this->displayStats;
  }

  /**
   * ???
   */
  public function getDisplayListener()
  {
    return $this->displayListener;
  }

  /**
   * ???
   */
  public function getDisplayTransport()
  {
    return $this->displayTransport;
  }

  /**
   * Get index columns order
   */
  public function getDisplayIndexColumns()
  {
    return $this->displayIndexColumns;
  }

  /**
   * Returns the custom session name, if any.
   */
  public function getSessionName()
  {
    return $this->sessionName;
  }

  /**
    * Returns whether navbar should be hidden when a session-transfer occurs
    */
  public function getSessionNavbarHide()
  {
    return $this->sessionNavbarHide;
  }

  /**
   * Returns whether or not geoip is enabled
   */
  public function getGeoIP()
  {
    if (!class_exists("GeoIp2\Database\Reader"))
      return false;
    return $this->geoIP;
  }

  /**
   * Returns path to geoip database
   */
  public function getGeoIPDatabase()
  {
    return $this->geoIPDatabase;
  }

  public function getStatsAggregations() {
    return $this->statsAggregations ?? [];
  }

  public function getStatsAggregation($chart_type, $stat_name) {
    return $this->statsAggregations[$chart_type][$stat_name] ?? null;
  }

  public function getStatsColor() {
    return $this->statsColor;
  }

  public function getStatsLabelColor() {
    return $this->statsLabelColor;
  }

  public function getStatsDefaultView() {
    return $this->statsDefaultView;
  }

  /**
   * Returns whether digest emails should be sent to everyone.
   */
  public function getDigestToAll()
  {
    return $this->digestToAll;
  }

  /**
   * Returns the secret key used to generate a "direct release" link in
   * digest emails.
   */
  public function getDigestSecret()
  {
    return $this->digestSecret;
  }

  /**
   * Returns the value for the "From:" field in outgoing emails, if any.
   */
  public function getMailSender()
  {
    return $this->mailSender;
  }
}
