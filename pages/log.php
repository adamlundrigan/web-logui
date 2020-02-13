<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!$settings->getDisplayTextlog() || Session::Get()->checkDisabledFeature('preview-textlog'))
  die("The setting display-textlog isn't enabled");

if (!Session::Get()->checkAccessAll() && !Session::Get()->checkTextlogAccess())
  die("Insufficient permissions");

require_once BASE.'/inc/twig.php';

$page = !is_nan(intval($_GET['p'])) ? intval($_GET['p']) : 1;

$esBackend = new ElasticsearchBackend($settings->getElasticsearch());
$mail = $esBackend->getMail($_GET['index'], $_GET['id']);

if (!$mail)
  die('Invalid mail');

$log = $esBackend->getTextlog($mail->msgid, $mail->msgts0, $page);

$twigLocals = [
  'textlog'       => $log['result'] ?? [],
  'more'          => $log['more'],
  'more_link'     => http_build_query([
    'id' => $_GET['id'],
    'index' => $_GET['index'],
    'p' => is_numeric($_GET['p']) ? $_GET['p'] + 1 : 2
  ]),
  'preview_link'  => http_build_query([
    'id' => $_GET['id'],
    'index' => $_GET['index']
  ])
];

echo $twig->render('log.twig', $twigGlobals + $twigLocals);
