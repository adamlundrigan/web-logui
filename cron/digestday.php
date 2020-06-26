<?php

/*
 * Don't invoke directly. Run as:
 * php cron.php.txt digestday
 */

if (!isset($_SERVER['argc']))
	die('this file can only be run from command line');

require_once BASE.'/inc/core.php';
require_once BASE.'/inc/utils.php';
require_once BASE.'/inc/twig.php';

// Initial settings
$limit = 10000; // Need limit, because of memory
$timesort = [];
$clients = [];
$conditions = [
	'tss' => [['gte' => ['relative' => (60*60*24)*-1]]]
];

// Build query
$settings = Settings::Get();

/*
if ($settings->getQuarantineFilter()) {
	$qf = array();
	foreach ($settings->getQuarantineFilter() as $q) {
		$conditions['metadatas'][] = ['value' => ['_quarantineid' => ['string' => ['value' => substr($q, 15)]]]];
	}
}
*/

foreach ($settings->getNodes() as $n => $r) {
	$clients[$n] = rest_client($n);
}

function access_level_merge($a, $b)
{
	if (!isset($a)) return $b;
	if (!isset($b)) return $a;
	if (empty($a) || empty($b)) return array();
	return array_merge_recursive($a, $b);
}

function substrdots($text, $len)
{
	if (strlen($text) > $len)
		return substr($text, 0, $len - 3) . '...';
	return $text;
}

// Perform actual requests
echo "Making query ".json_encode($conditions)."\n";
foreach ($settings->getNodes() as $n => $r) {
	$data = $clients[$n]->operation('/protobuf', 'POST', null, [
		'command' => 'F',
		'program' => 'smtpd',
		'payload' => [
			'paging' => ['limit' => $limit],
			'conditions' => $conditions
		]
	]);

	if (is_array($data->body->items)) foreach ($data->body->items as $item)
		$timesort[$item->ts][] = array('id' => $n, 'type' => 'queue', 'data' => $item);
}
krsort($timesort);
if (empty($timesort))
	die("No quarantined messages within one day\n");

$users = array();
foreach ($settings->getAuthSources() as $a) {
	// Send to statically configured users with e-mail address
	if ($a['type'] == 'account' && isset($a['email']))
		$users[$a['email']] = access_level_merge($users[$a['email']], $a['access']);
	// Users in web-apps-controlpanel
	if ($a['type'] == 'control') {
		$context = $stream_context = stream_context_create(['ssl' => $a['tls']]);
		$result = $file = file_get_contents($a['url']."/api/".$a['apikey']."/users", false, $context);
		if ($result) {
			$list = json_decode($result, true);
			foreach ($list as $user) {
				$user_access = array_filter($user['relation'], function($item) {
					return $item['access_type'] === 'user';
				});
				$addresses = array_map(function($item) {
					return $item['access'];
				}, $user_access);
				$users[$user['username']."@".$user['domain']] = ['mail' => array_merge([$user['username']."@".$user['domain']], $addresses)];
			}
		}
	}
}

// Send to everyone in quarantine, if enabled in settings
$allusers = array();
if ($settings->getDigestToAll())
	foreach ($timesort as $t)
		foreach ($t as $m)
			$allusers[strtolower($m['data']->recipient->localpart."@".$m['data']->recipient->domain)] = true;
foreach ($allusers as $email => $tmp)
	$users[$email] = access_level_merge($users[$email], array('mail' => array($email)));

$size = 500;
echo "Found ".count($users)." users\n";

foreach ($users as $email => $access) {
	$maillist = array();
	foreach ($timesort as $t) {
		if (count($maillist) > $size)
			break;
		foreach ($t as $m) {
			if (count($maillist) > $size)
				break;
			// Only show messages they have access to
			$match = false;
			if (count($access) == 0) // no restrictions
				$match = true;
			if (isset($access['mail']))
				foreach ($access['mail'] as $mail)
					if (strtolower($m['data']->recipient->localpart."@".$m['data']->recipient->domain) == $mail)
						$match = true;
			list($tobox, $todomain) = [strtolower($m['data']->recipient->localpart), $m['data']->recipient->domain];
			if (isset($access['domain']))
				foreach ($access['domain'] as $domain)
					if ($todomain == $domain)
						$match = true;
			if (!$match)
				continue;

			$mail = array();
			if ($settings->getDigestSecret()) {
				// make direct release link
				$time = time();
				$message = $time.$m['data']->id->transaction.$m['data']->id->queue.$m['id'];
				$hash = hash_hmac('sha256', $message, $settings->getDigestSecret());
				if ($settings->getDigestReleaseLink()) $mail['release_url'] = $settings->getPublicURL().'/?page=digest&msgid='.$m['data']->id->transaction.'&msgactionid='.$m['data']->id->queue.'&time='.$time.'&node='.$m['id'].'&sign='.$hash;
				if ($settings->getDigestPreviewLink()) $mail['preview_url'] = $settings->getPublicURL().'/?page=digest&msgid='.$m['data']->id->transaction.'&msgactionid='.$m['data']->id->queue.'&time='.$time.'&preview=true&node='.$m['id'].'&sign='.$hash;
			}
			$mail['time'] = date('Y-m-d H:i:s', $m['data']->ts);
			$mail['from'] = isset($m['data']->sender) && $m['data']->sender->localpart && $m['data']->sender->domain ? strtolower($m['data']->sender->localpart."@".$m['data']->sender->domain) : '';
			$mail['to'] = strtolower($m['data']->recipient->localpart."@".$m['data']->recipient->domain);
			$mail['subject'] = $m['data']->subject;
			$maillist[] = $mail;
		}
	}

	if (empty($maillist))
		continue;

	$one_recipient = $maillist[0]['to'];
	for ($i = 1; $i < count($maillist); ++$i) {
		if ($maillist[$i]['to'] != $one_recipient) {
			$one_recipient = null;
			break;
		}
	}

	/*
	 * start printing email below this line.
	 * $one_recipient contains an email if all messages were only to one recipient
	 */

	echo "Digest to $email with ".count($maillist)." messages\n";
	$headers = array();
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'Content-Transfer-Encoding: base64';

	$twigLocals = [
		'mails' => $maillist,
		'quarantine_url' => $settings->getPublicURL(),
		'subject' => "Quarantine digest, ".count($maillist)." new messages"
	];
	if ($one_recipient !== null) $twigLocals['recipient'] = $one_recipient;

	$body = $twig->render('digestday.twig', $twigLocals);

	mail2($email, "Quarantine digest, ".count($maillist)." new messages", chunk_split(base64_encode($body)), $headers);
}
