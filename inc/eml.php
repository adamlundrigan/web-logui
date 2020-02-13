<?php

class HTMLPurifier_TagTransform_IMG extends HTMLPurifier_TagTransform
{
	private $mail = null;
	public function __construct($mail) {
		$this->mail = $mail;
	}
	public function transform($tag, $config, $context) {
		if (strtolower(substr($tag->attr['src'], 0, 4)) != 'cid:')
			return $tag;

		$x = $this->mail->getPartByContentId(substr($tag->attr['src'], 4));
		if (!$x)
			return null;

		$contentType = $x->getContentType();
		if (substr($contentType, 0, 6) != 'image/')
			return null;

		$tag->attr['src'] = 'data:' . $contentType . ';base64,' . base64_encode($x->getContent());
		return $tag;
	}
}

function eml_download($client, $hqfpath, $queueid = 1, $original = false, $string = false) {
	$emlpath = substr($hqfpath, 0, -3).'eml';
	$maxread = 10 * 1024 * 1024;

	$readUntil = function($client, $emlpath, $offset, $maxread, $cb, $until = null) {
		while (true)
		{
			$read= $maxread;
			if ($until)
			{
				$length = $until - $offset;
				if ($length > $maxread)
					$read = $maxread;
				else
					$read = $length;
				if ($read == 0)
					break;
			}
			$result = $client->operation('/system/files', 'GET', ['path' => $emlpath, 'offset' => $offset, 'limit' => $read])->body;
			$cb(base64_decode($result->data));
			if ($result->size < $read)
				break;
			$offset = $result->offset;
		}
	};

	if ($original) {
		if ($string) {
			$mail = '';
			$readUntil($client, $emlpath, 0, $maxread, function ($data) use (&$mail) {
				$mail .= $data;
			});
			return $mail;
		} else {
			$size = $client->operation('/system/files:size', 'POST', ['path' => $emlpath])->body->size;
			header("Content-Length: $size");
			$readUntil($client, $emlpath, 0, $maxread, function ($data) {
				echo $data;
				flush();
			});
			return;
		}
	}

	$offset = 0;
	$data = base64_decode($client->operation('/system/files', 'GET', ['path' => $hqfpath, 'offset' => $offset, 'limit' => 73])->body->data);
	$hqfHeader = unpack("Z3magic/Cversion_major/Cversion_minor/Z36transactionid/Qrecipient_offset/Qts_sec/Qts_usec/Qtlv_offset", $data);

	if ($hqfHeader['magic'] != 'HQF')
		throw new Exception('not a hqf file');

	if ($hqfHeader['version_major'] != 1)
		throw new Exception('file version not supported');

	$recipient_offset = $hqfHeader['recipient_offset'];
	while ($recipient_offset > 0)
	{
		$offset = $recipient_offset;
		$data = base64_decode($client->operation('/system/files', 'GET', ['path' => $hqfpath, 'offset' => $offset, 'limit' => 49])->body->data);

		$hqfRecipientHeader = unpack("Qqueueid/Qrecipient_offset/Cstatus/Qretryts_sec/Qretryts_usec/Qretry/Qtlv_offset", $data);
		$recipient_offset = $hqfRecipientHeader['recipient_offset'];

		if ($hqfRecipientHeader['queueid'] != $queueid)
			continue;

		$delta = '';
		if ($hqfRecipientHeader['tlv_offset'] > 0)
		{
			$offset = $hqfRecipientHeader['tlv_offset'];
			while (true)
			{
				$data = base64_decode($client->operation('/system/files', 'GET', ['path' => $hqfpath, 'offset' => $offset, 'limit' => 9])->body->data);
				$offset += 9;
				$hqfTLV = unpack("Ctype/Qlen", $data);
				if ($hqfTLV['type'] == 0xff)
				{
					if ($hqfTLV['len'] == 0)
						break;
					$offset = $hqfTLV['len'];
					continue;
				}
				if ($hqfTLV['len'])
				{
					$data  = '';
					$readUntil($client, $hqfpath, $offset, $maxread, function ($d) use (&$data) {
						$data .= $d;
					}, $offset + $hqfTLV['len']);
					$offset += $hqfTLV['len'];
				}
				if ($hqfTLV['type'] == 7)
					$delta = $data;
			}
		}

		$replaces = [];
		$offset = 0;
		$deltasizediff = 0;
		while ($offset < strlen($delta))
		{
			$c = unpack("Ctype", $delta, $offset);
			if ($c['type'] == 1)
			{
				$c = unpack("Ctype/Qoffset/Qreplace/Qlen", $delta, $offset);
				$offset += 25;
				$d = unpack("Z".$c['len']."data", $delta, $offset);
				$offset += $c['len'];
				$replaces[$c['offset']] = [ 'replace' => $c['replace'], 'insert' => $d['data'] ];
				$deltasizediff -= $c['replace'];
				$deltasizediff += strlen($d['data']);
			}
			else
				throw new Exception('found a unknown delta type');
		}

		if (!$string)
		{
			$size = $client->operation('/system/files:size', 'POST', ['path' => $emlpath])->body->size;
			$size += $deltasizediff;
			header("Content-Length: $size");
		}

		$mail = '';
		$pos = 0;
		foreach ($replaces as $k => $v)
		{
			if ($pos < $k) {
				$readUntil($client, $emlpath, $pos, $maxread, function ($data) use (&$mail, $string) {
					if ($string) {
						$mail .= $data;
					} else {
						echo $data;
						flush();
					}
				}, $k);
				$pos = $k;
			}
			if ($string) {
				$mail .= $v['insert'];
			} else {
				echo $v['insert'];
				flush();
			}
			$pos += $v['replace'];
		}

		$offset = $pos;
		if ($string) {
			$readUntil($client, $emlpath, $offset, $maxread, function ($data) use (&$mail) {
				$mail .= $data;
			});
			return $mail;
		} else {
			$readUntil($client, $emlpath, $offset, $maxread, function ($data) {
				echo $data;
				flush();
			});
		}
	}
}
