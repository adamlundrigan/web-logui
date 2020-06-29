<?php
if (!defined('WEB_LOGUI')) die('File not included');

if (isset($_POST['username']) && isset($_POST['password'])) {
  $session_name = $settings->getSessionName();
  if ($session_name)
    session_name($session_name);
  session_start();
  session_regenerate_id(true);

  $_SESSION['timezone'] = $_POST['timezone'];
  $_SESSION['timezone_utc'] = $_POST['timezone_utc'];
  $username = $_POST['username'];
  $password = $_POST['password'];

  foreach ($settings->getAuthSources() as $method)
  {
    $authmethod = 'halon_login_' . $method['type'];
    if (!function_exists($authmethod) && file_exists('modules/Login.'.$method['type'].'.php'))
      require_once 'modules/Login.'.$method['type'].'.php';
    $result = $authmethod($username, $password, $method, $settings);
    if ($result && is_array($result))
    {
      $_SESSION = array_merge($_SESSION, $result);
      $_SESSION['authenticated'] = true;
      break;
    }
  }

  if (isset($_SESSION['authenticated']))
  {
    if ($_SESSION['authenticated'] === true)
    {
      if ($_POST['query'])
        header("Location: ?".$_POST['query']);
      else
        header("Location: .");
      die();
    }
  }
  else
  {
    $error = 'Login failed';
    session_unset();
    session_destroy();
  }
}

require_once BASE.'/inc/twig.php';

$twigLocals = [
  'login_text' 	=> $settings->getLoginText() ?: null,
  'error' 			=> $error ?: null,
  'query' 			=> $_GET['query'] ?: null
];

echo $twig->render('login.twig', $twigGlobals + $twigLocals);
