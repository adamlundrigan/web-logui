<?php

/*
 * This is the configuration file, in PHP format. In most cases, it's
 * ok just to edit our settings, and remove the // comments.
 */

 /*
 * Add credentials to your Halon nodes to enable preview and actions on queued or quarantined emails
 */

// $settings['node'][] = array(
// 		'address' => 'https://10.2.0.30/',
// 		'tls' => array('verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false),
// 		);
// $settings['node'][] = array(
// 		'address' => 'https://10.2.0.31/',
// 		'username' => 'admin',
// 		'password' => 'admin',
// 		'tls' => array('verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false),
// 		);

/*
 * The API key is used by external systems to communicate with
 * this application
 */

//$settings['api-key'] = 'secret';

/*
 * Local sqlite database
 * The purpose of this database is to store all necessary pending actions on emails
 * and run them with the provide cron script
 *      * * * * * /usr/bin/php /var/www/html/web-logui/cron.php.txt pending
 *
 * Use an absolute path to the database, with read and write permissions for the
 * user running the web server
 */
//$settings['database']['dsn'] = 'sqlite:/tmp/web-logui.db';

/*
 * Generic settings
 */

//$settings['public-url'] = 'http://10.2.0.166/enduser/';
//$settings['display-scores'] = false;
//$settings['display-textlog'] = false;
//$settings['display-stats'] = false;
//$settings['display-listener']['mailserver:inbound'] = 'Inbound';
//$settings['display-transport']['mailtransport:outbound'] = 'Internet';
//$settings['display-index-columns'] = ['action', 'from', 'to', 'subject', 'status', 'scores', 'date'];
//$settings['mail']['from'] = 'Mail quarantine <postmaster@example.org>';

/*
 * Elasticsearch settings
 */

// $settings['elasticsearch']['host'][] = '127.0.0.1:9200';
// $settings['elasticsearch']['index']['mail']['name'] = 'halon-';
// $settings['elasticsearch']['index']['mail']['rotate'] = '%Y-%m-%d';
// $settings['elasticsearch']['index']['mail']['timefilter'] = 'receivedtime';

// $settings['elasticsearch']['index']['textlog']['name'] = 'logstash-';
// $settings['elasticsearch']['index']['textlog']['rotate'] = '%Y.%m.%d';
// $settings['elasticsearch']['index']['textlog']['type'] = '_doc';
// $settings['elasticsearch']['index']['textlog']['limit'] = 50;
// $settings['elasticsearch']['index']['textlog']['search_rotate_limit'] = 10;
// $settings['elasticsearch']['index']['textlog']['timefilter'] = 'received_at';

/*
 * Default elasticsearch mappings
 * Only change these values if necessary
 */
// $settings['elasticsearch-mappings'] = [
//   'id' => '_id',
//   'owner' => 'owner',
//   'ownerdomain' => 'ownerdomain',
//   'msgid' => 'messageid',
//   'msglistener' => 'serverid',
//   'msgfromserver' => 'senderip',
//   'msgsenderhelo' => 'senderhelo',
//   'msgtlsstarted' => 'tlsstarted',
//   'msghelo' => 'senderhelo',
//   'msgsasl' => 'saslusername',
//   'msgaction' => 'action',
//   'msgfrom' => 'sender',
//   'msgfromdomain' => 'senderdomain',
//   'msgto' => 'recipient',
//   'msgtodomain' => 'recipientdomain',
//   'msgtransport' => 'transportid',
//   'msgsubject' => 'subject',
//   'msgsize' => 'size',
//   'msgactionid' => 'actionid',
//   'msgdescription' => 'reason',
//   'msgts0' => 'receivedtime',
//   'serialno' => 'serial',
//   'tlsstarted' => 'tlsstarted',
//   'score_rpd' => 'score_rpd',
//   'score_rpd_refid' => 'score_rpd_refid',
//   'score_rpdav' => 'score_rpdav',
//   'scores' => [
//     'key' => 'scores',
//     'value' => [
//       'sa' => 'sa',
//       'sa_rules' => 'sa_rules',
//       'kav' => 'kav',
//       'clam' => 'clam'
//     ]
//   ],
//   'queue' => [
//     'key' => 'queue',
//     'value' => [
//       'action' => 'action',
//       'errormsg' => 'errormsg',
//       'retry' => 'retry',
//       'retries' => 'retries'
//     ]
//   ],
//   'metadata' => 'metadata'
// ];

/*
 * Metadata filter, only fetch values when key matches (preg_match)
 */

// $settings['elasticsearch-metadata-filter'] = [
//   '/^example$/'
// ];

/*
 * Authentication
 * You can use the following types:
 *  - Local accounts, statically configured in this file (with access rights).
 *    Use lower case letters when manually adding an access level.
 *  - Session transfer
 *    Rename session-transfer.php.txt to session-transfer.php
 */

//$settings['authentication'][] = array(
// 		'type' => 'account',
// 		'username' => 'foo',
// 		'password' => 'foo',
//		'access' => array( // optional access restrictions
//				'domain' => array('example.com'),
//				'mail' => array('foo@example.com'),
//				),
// 		);

/*
 * If hosting multiple websites on the same server, it's important to use
 * different session names for each site.
 */

//$settings['session-name'] = 'weblogui';

/*
 * Customizable text in the interface.
 */

//$settings['theme'] = 'paper'; // see themes/
//$settings['brand-logo'] = '/logo.png';
//$settings['brand-logo-height'] = 40; // the real height of the image, should be double (hidpi) the size of themes brand container
//$settings['pagename'] = "Halon log server";
//$settings['logintext'] = "Some text you'd like to display on the login form";

/*
 * Maxmind GEOIP2
 * Dependencies:
 * composer require geoip2/geoip2:~2.0
 * composer require components/flag-icon-css
 * Visit https://www.maxmind.com/ for more information
 */

//$settings['geoip'] = false;
//$settings['geoip-database'] = ''; // download the country database at https://dev.maxmind.com/geoip/geoip2/geolite2/ and specify the path to it

/*
  * Session transfer
  */
//$settings['session-navbar-hide'] = false;

/*
 * It's possible to send "digest" messages with a list of what's in
 * the quarantine. It is added as a cron job, to be run every 24 hours:
 * # php cron.php.txt digestday
 * and it will use the authentication sources to find users. To use static
 * users (type account), add a 'email' to them. To send digest messages to
 * EVERY RECIPIENT (user or not) that has quarantine messages, enable the
 * to-all option below. To have a "direct release link" in the messages,
 * enable the digest secret below.
 */

//$settings['digest']['to-all'] = true;
//$settings['digest']['secret'] = 'badsecret';