<?php

define('WEB_LOGUI', true);
define('BASE', dirname(__FILE__));

require_once BASE.'/inc/core.php';

if (Session::Get()->isAuthenticated() && $_SERVER['QUERY_STRING'] == 'xhr') {
  require_once BASE.'/xhr.php';
  die();
}

if (!Session::Get()->isAuthenticated() && (!isset($_GET['page']) || $_GET['page'] != 'login' && $_GET['page'] != 'digest')) {
  session_destroy();
  header("Location: ?page=login&query=".urlencode($_SERVER['QUERY_STRING']));
  die();
}

switch (@$_GET['page'])
{
  case 'login':
    require_once BASE.'/pages/login.php';
  break;
  case 'logout':
    require_once BASE.'/pages/logout.php';
  break;
  case 'stats':
    require_once BASE.'/pages/stats.php';
  break;
  case 'log':
    require_once BASE.'/pages/log.php';
  break;
  case 'preview':
    require_once BASE.'/pages/preview.php';
  break;
  case 'download':
    require_once BASE.'/pages/download.php';
  break;
  case 'digest':
    require_once BASE.'/pages/digest.php';
  break;
  default:
  case 'index':
    require_once BASE.'/pages/index.php';
  break;
}
