<?php
if (!defined('WEB_LOGUI')) die('File not included');

if (!isset($_GET['index']) || !isset($_GET['id']))
  die('Missing arguments');

use Elasticsearch\ClientBuilder;

$errors = [];
$showoriginal = isset($_GET['original']);
$found_in_node = false;
$pending_action_type = null;

$esBackend = new ElasticsearchBackend($settings->getElasticsearch());
$mail = $esBackend->getMail($_GET['index'], $_GET['id']);

$msgaction = $mail->queue['action'] ?? $mail->msgaction;

if (!$mail)
  die('Invalid mail');

// If in queue or quarantine, try to fetch mail over REST
if (isset($mail->serialno) && in_array($msgaction, ['QUEUE', 'QUARANTINE'])) {
  $dbh = $settings->getDatabase();
  if ($dbh) {
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      if (isset($_POST['action'])) {
        $statement = $dbh->prepare('INSERT INTO pending_actions (msgid, actionid, serialno, action) VALUES (:msgid, :actionid, :serialno, :action);');

        if ($_POST['action'] == 'bounce') {
          $statement->execute([':msgid' => $mail->msgid, ':actionid' => $mail->msgactionid, ':serialno' => $mail->serialno, ':action' => 'bounce']);
          $pending_action_type = 'bounce';
        } else if ($_POST['action'] == 'delete') {
          $statement->execute([':msgid' => $mail->msgid, ':actionid' => $mail->msgactionid, ':serialno' => $mail->serialno, ':action' => 'delete']);
          $pending_action_type = 'delete';
        } else if ($_POST['action'] == 'retry') {
          $statement->execute([':msgid' => $mail->msgid, ':actionid' => $mail->msgactionid, ':serialno' => $mail->serialno, ':action' => 'retry']);
          $pending_action_type = 'retry';
        }

        header('Location: ?page=preview&index='.$_GET['index'].'&id='.$_GET['id']);
        die();
      } else {
        // check for pending actions
        $q = $dbh->prepare('SELECT * FROM pending_actions WHERE msgid = :msgid AND actionid = :actionid;');
        $q->execute([':msgid' => $mail->msgid, 'actionid' => $mail->msgactionid]);
        if ($row = $q->fetch()) {
          $pending_action_type = $row['action'];
        }
      }
    } catch (PDOException $e) {
      $errors['database'] = $e;
    }
  }

  $node = $settings->getNodeBySerial($mail->serialno);
  if ($node) {
    $found_in_node = true;
    $results = [];
    try {
      $node_id = $node->getId();
      $client = $node->rest();

      $response = $client->operation('/protobuf', 'POST', null, [
        'command' => 'F',
        'program' => 'smtpd',
        'payload' => [
          'conditions' => [
            'ids' => [
              [
                'transaction' => $mail->msgid,
                'queue' => $mail->msgactionid
              ]
            ]
          ]
        ]
      ]);

      if (!$response->body || !isset($response->body->items) || count($response->body->items) !== 1)
        throw new RestException(404, 'Not found');

      $purifier_cfg = HTMLPurifier_Config::createDefault();
      $purifier_cfg->set('Cache.DefinitionImpl', null);
      $purifier_cfg->set('URI.AllowedSchemes', ['data' => true]);
      $purifier_cfg->set('URI.DisableExternal', true);
      $purifier_cfg->set('URI.DisableExternalResources', true);
      $purifier = new HTMLPurifier($purifier_cfg);

      require_once 'inc/eml.php';
      $maildata = eml_download($client, $response->body->items[0]->hqfpath, $mail->msgactionid, $showoriginal, true);

      $message = \ZBateson\MailMimeParser\Message::from($maildata);
      $def = $purifier_cfg->getHTMLDefinition();
      $def->info_tag_transform['img'] = new HTMLPurifier_TagTransform_IMG($message);

      $html = $message->getHtmlContent();

      $preview = [];
      if ($html !== null) {
        $html = iconv("utf-8", "utf-8//ignore", trim(@$purifier->purify($html)));
      }

      $text = $message->getTextContent();
      if ($text !== null) {
        $text = htmlspecialchars($text);
      }

      $headers = $message->getAllHeaders();
      if (is_array($headers) && count($headers)) {
        $header = implode(array_map(function($header) {
          return $header->getName() . ": " . $header->getRawValue();
        }, $headers), "\n");
      }

      if ($_GET['preview'] == 'text') {
        if ($text !== null) {
          $body = $text;
          $encode = 'TEXT';
        } else {
          $encode = 'ERROR';
          $body = '<p class="text-center text-muted">Text preview unavailable</p>';
        }
      } else if ($_GET['preview'] == 'html') {
        if ($html !== null) {
          $body = $html;
          $encode = 'HTML';
        } else {
          $encode = 'ERROR';
          $body = '<p class="text-center text-muted">HTML preview unavailable</p>';
        }
      } else {
        if ($text !== null || $html !== null) {
          $body = $html !== null ? $html : $text;
          $encode = $html !== null ? 'HTML' : 'TEXT';
        } else {
          $encode = 'ERROR';
          $body = '<p class="text-center text-muted">Preview unavailable</p>';
        }
      }
    } catch (RestException $e) {
      $encode = 'ERROR';
      $body = '<p class="text-center text-muted">Preview unavailable</p>';
    }
  }
}

$action_colors = array(
  'DELIVER' => '#5cb85c',
  'QUEUE' => '#00aeef',
  'QUARANTINE' => '#f0ad4e',
  'ARCHIVE' => '#b8b8b8',
  'REJECT' => '#d9534f',
  'DELETE' => '#000',
  'BOUNCE' => '#000',
  'ERROR' => '#000',
  'DEFER' => '#e83e8c',
);

// geoip
if ($settings->getGeoIP()) {
  try {
    $reader = new GeoIp2\Database\Reader($settings->getGeoIPDatabase());
    $ipinfo = $reader->country($mail->msgfromserver);
    $geoip['name'] = $ipinfo->country->name;
    $geoip['isocode'] = strtolower($ipinfo->country->isoCode);
  } catch(Exception $e) {}
}

require_once BASE.'/inc/twig.php';

$reportfp = $reportfpbody = $reportfn = false;
if (Session::Get()->checkAccessAll()) {
  $scores = history_parse_scores($mail);
  if (isset($scores['rpd']['score'])) {
    if ($scores['rpd']['score'] === 'Bulk' || $scores['rpd']['score'] === 'Spam') {
      $reportfp = true;
      if ($msgaction === 'QUEUE' || $msgaction === 'QUARANTINE')
        $reportfpfile = true;
    }
    if ($scores['rpd']['score'] !== 'Spam' && $msgaction === 'QUEUE')
      $reportfn = true;
  }
  if (isset($_GET['report'])) {
    $reportdata = [];
    $reporttype = ($_GET['reporttype'] == 'fn') ? 'fn' : 'fp';
    $reportvalid = false;
    if ($reporttype == 'fp' && ($reportfp || $reportfpfile))
      $reportvalid = true;
    if ($reporttype == 'fn' && $reportfn)
      $reportvalid = true;
    if ($reportvalid) {
      if ($reporttype == 'fp' && $reportfp)
        $reportdata['refid'] = isset($scores['rpd']['text']) ? $scores['rpd']['text'] : '';
    }
  }
}

$f = $_GET;
$f['original'] = true;
$show_original_link = '?'.http_build_query($f);

$f = $_GET;
unset($f['original']);
$show_modified_link = '?'.http_build_query($f);
$show_original = $showoriginal;

$f = $_GET;
$f['preview'] = 'text';
$show_text_link = '?'.http_build_query($f);

$f = $_GET;
unset($f['preview']);
$show_html_link = '?'.http_build_query($f);

$twigLocals = [
  'index'               => $_GET['index'],
  'node_id'							=> isset($node_id) ? $node_id : null,
  'found_in_node'				=> $found_in_node,
  'attachments' 				=> $attachments ?: null,
  'body'								=> $body ?: null,
  'encode'							=> $encode ?: null,
  'show_text'						=> $_GET['preview'] == 'text',
  'show_html'						=> $_GET['preview'] == 'html' || !$_GET['preview'],
  'mail'								=> $mail,
  'scores'							=> history_parse_scores($mail) ?: null,
  'pending_action_type' => $pending_action_type,
  'show_original_link'	=> $show_original_link,
  'show_modified_link'	=> $show_modified_link,
  'show_original'				=> $show_original,
  'show_text_link'			=> $show_text_link,
  'show_html_link'			=> $show_html_link,
  'msg_mailflow'				=> $msgactionlog ?: [],
  'msg_action'          => $msgaction,
  'geoip'								=> $geoip ?: null,
  'referer'							=> $_POST['referer'] ?: $_SERVER['HTTP_REFERER'],
  'action_color'				=> $action_colors[$msgaction],
  'action_icon'					=> $action_icons[$msgaction],
  'action_text'         => substr($mail->queue['action'] ?? $mail->msgaction, 0, 1),
  'action_colors'				=> $action_colors,
  'action_icons'				=> $action_icons,
  'disabled_features'		=> Session::Get()->getDisabledFeatures(),
  'feature_scores'      => $settings->getDisplayScores(),
  'feature_textlog'     => $settings->getDisplayTextlog(),
  'is_superadmin'       => Session::Get()->checkAccessAll(),
  'textlog_access'      => Session::Get()->checkTextlogAccess(),
  'reportfp'            => $reportfp,
  'reportfn'            => $reportfn,
  'errors'              => $errors,
  'msgts'               => $mail->msgts0 - $_SESSION['timezone'] * 60
];

if ($settings->getDisplayTextlog() && $mail->msgid) {
  $twigLocals['support_log'] = true;
  $twigLocals['support_log_query'] = urlencode($_SERVER['QUERY_STRING']);
}

if ($header)
  $twigLocals['header'] = $header;

$transports = $settings->getDisplayTransport();
if (isset($transports[$mail->msgtransport]))
  $twigLocals['transport'] = $transports[$mail->msgtransport];

  $listeners = $settings->getDisplayListener();
if (isset($listeners[$mail->msglistener]))
  $twigLocals['listener'] = $listeners[$mail->msglistener];

echo $twig->render('preview.twig', $twigGlobals + $twigLocals);
