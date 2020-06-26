<?php

function mail2($recipient, $subject, $message, $in_headers = null)
{
	$settings = Settings::Get();
	$headers = array();
	$headers[] = 'Message-ID: <'.uniqid().'@web-logui>';
	if ($settings->getMailSender())
		$headers[] = "From: ".$settings->getMailSender();
	if ($in_headers !== null)
		$headers = array_merge($headers, $in_headers);
	mail($recipient, $subject, $message, implode("\r\n", $headers));
}
