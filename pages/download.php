<?php
if (!defined('WEB_LOGUI')) die('File not included');

header('Content-type: text/plain');

if (Session::Get()->checkDisabledFeature('preview-mail-body'))
  die('Permission denied');

$id = $_GET['id'];
$index = $_GET['index'];

$esBackend = new ElasticsearchBackend($settings->getElasticsearch());
$mail = $esBackend->getMail($index, $id);

if (!$mail)
  die('Invalid mail');

$messageid = $mail->msgid;
$actionid = $mail->msgactionid;

if ($_GET['original'] == '1' && Session::Get()->checkDisabledFeature('preview-mail-body-original'))
  die('Permission denied');

$node = $settings->getNode($_GET['node']);
if (!$node)
  die('Unknown node');

$filename = $messageid;
if ($_GET['original'] == '1')
  $filename .= '_org';

try {
  $client = $node->rest();
  $response = $client->operation('/protobuf', 'POST', null, [
    'command' => 'F',
    'program' => 'smtpd',
    'payload' => [
      'conditions' => [
        'ids' => [
          [
            'transaction' => $messageid,
            'queue' => $actionid
          ]
        ]
      ]
    ]
  ]);
  if (!$response->body || !isset($response->body->items) || count($response->body->items) !== 1)
    throw new RestException(404, 'Not found');

  require_once 'inc/eml.php';
  header('Content-Disposition: attachment; filename='.$filename.'.eml');
  eml_download($client, $response->body->items[0]->hqfpath, $actionid, $_GET['original'] == '1', false);
} catch (RestException $e) {
  echo "Error: ".$e->getMessage();
}
