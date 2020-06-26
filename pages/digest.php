<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!$settings->getDigestSecret()) die('No digest secret');

$msgid = $_GET['msgid'];
$node = intval($_GET['node']);
$actionid = intval($_GET['msgactionid']);
$time = intval($_GET['time']);
$sign = $_GET['sign'];

// Validate signature
$message = $time.$msgid.$actionid.$node;
$hash = hash_hmac('sha256', $message, $settings->getDigestSecret());
if ($hash !== $sign) die('Failed to release message');

// Fetch mail
$node = $settings->getNode($node);
try {
	$client = $node->rest();
	$items = $client->operation('/protobuf', 'POST', null, [
		'command' => 'F',
		'program' => 'smtpd',
		'payload' => [
			'conditions' => [
				'ids' => [
					[
						'transaction' => $msgid,
						'queue' => $actionid
					]
				]
			]
		]
	])->body->items;
} catch (Exception $e) {
	die('Operation failed');
}
if (!isset($items[0])) die('Invalid mail');
$mail = $items[0];

// Check time, allow 1 week of links
if ($time + (3600*24*7) < time())
	die('Link has expired (valid 1 week)');

$msgfrom = isset($mail->sender) && $mail->sender->localpart && $mail->sender->domain ? strtolower($mail->sender->localpart.'@'.$mail->sender->domain) : '';
$msgto = strtolower($mail->recipient->localpart.'@'.$mail->recipient->domain);

// preview email
if ($_GET['preview'] == 'true') {
	if (!Session::Get()->isAuthenticated()) {
		session_destroy();
		header('Location: ?page=login&query='.urlencode($_SERVER['QUERY_STRING']));
	} else {
		$url = '?'.http_build_query([
			'page' => 'index',
			'search' => $msgid
		]);

		header('Location: '.$url);
	}
	die();
}

// Perform action and close window
try {
	$client->operation('/protobuf', 'POST', null, [
		'command' => 'G',
		'program' => 'smtpd',
		'payload' => [
			'conditions' => [
				'ids' => [
					$mail->id
				]
			],
			'move' => [
				'queue' => 'ACTIVE'
			]
		]
	]);
} catch (Exception $e) {
	die($e->getMessage());
}
?>
<html>
<head>
	<title>Message successfully released</title>
	<script>
		window.close();
	</script>
</head>
<body>
	The message was successfully released.
</body>
</html>
