<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'Off');
ini_set('session.cookie_httponly', 1);

require_once BASE.'/inc/utils.php';

// Always use UTC timezone
date_default_timezone_set('UTC');

// Autoload our classes from /classes/
function halon_autoloader($class)
{
  @include BASE.'/classes/'.$class.'.class.php';
}
spl_autoload_register('halon_autoloader');

// Autoload Composer packages from /vendor/
require_once BASE.'/vendor/autoload.php';

// Conveniently access the Settings instance as $settings
$settings = Settings::Get();
