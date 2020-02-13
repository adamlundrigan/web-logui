<?php

if (!isset($_SERVER['argc']))
  die('This file can only be run from command line');

define('BASE', dirname(__FILE__));

require_once BASE.'/inc/core.php';

$dbh = $settings->getDatabase();
if (!$dbh)
  die('No such database');

$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$statement = $dbh->prepare('SELECT * FROM pending_actions LIMIT 1;');
if (!$statement || $statement->execute() === false) {
  echo "Adding table `pending_actions`\r\n";
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $dbh->exec('CREATE TABLE pending_actions (msgid VARCHAR(100) NOT NULL, actionid, action VARCHAR(50) NOT NULL, serialno VARCHAR(50) NOT NULL, PRIMARY KEY(msgid, actionid));');
} else {
  echo "Table `pending_actions` already exists. Skipping...\r\n";
}
