Installation instructions for the web-logui application for the Halon MTA. Please read more on https://halon.io.

Requirements
---
* Halon MTA 5.3 or later
* PHP compatible web server (Apache, NGINX, IIS)
* PHP (>=7.1)
* [Composer](https://getcomposer.org)
* [Elasticsearch (>=6.x)](https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html)

Installation
---
1. Create a database file, for example, **web-logui.db**, outside of the document root, and give read and write access for the file to the web server's user.
2. Copy all project files to a web server directory, for example: /var/www/web-logui, and make sure that the site is configured on the chosen web server.
3. Make a copy of the `settings-default.php` file to `settings.php`, and edit the latter to configure all required settings.
- $ `cp ./settings-default.php ./settings.php`
- $ `vim ./settings.php`
4. Run the following commands to install the database and any dependencies from the project directory:
- $ `composer install`
- $ `php -f install.db.php`

Elasticsearch 7.x index template
---
```
PUT /_template/halon
{
  "index_patterns": ["halon-*"],
  "settings": {
    "number_of_shards": 2,
    "number_of_replicas": 1,
    "analysis": {
      "analyzer": {
        "email_analyzer": {
          "type": "custom",
          "tokenizer": "uax_url_email",
          "filter": ["lowercase", "stop"]
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "receivedtime": {
        "type":   "date",
        "format": "epoch_millis"
      },
      "finishedtime": {
        "type":   "date",
        "format": "epoch_millis"
      },
      "senderip": {
        "type":   "ip"
      },
      "serverip": {
        "type":   "ip"
      },
      "owner": {
        "type": "text",
        "analyzer": "email_analyzer"
      },
      "ownerdomain": {
        "type": "text",
        "analyzer": "email_analyzer"
      },
      "messageid": {
          "type": "keyword"
      }
    }
  }
}
```

Halon remote logging to Elasticsearch
---
In Halon, under the **Code Editor** page, create the following files and edit them by inserting the following scripts, accordingly.

File: `elastic/settings.hsl`
```
$elasticurl = "https://elastic:elastic@yourhostname.com:9200";
$indexname = "halon";
$indexrotate = "%Y-%m-%d";
$indextype = "_doc";
```

File: `elastic/eodrcpt.hsl`
```
include "elastic/settings.hsl";

$httpoptions = [
	"timeout" => 10,
	"background" => true,
	"background_hash" => hash($messageid),
	"background_retry_count" => 1,
	"tls_default_ca" => true,
	"headers" => ["Content-Type: application/json"]
];

function sendlog($logdata = []) {
	global $elasticurl, $httpoptions, $indexname, $indexrotate, $indextype; // settings
	global $transaction, $senderip, $senderport, $serverip, $serverport, $serverid; // connect
	global $senderhelo, $tlsstarted, $saslusername; // auth
	global $saslauthed; // mail from
	global $recipient, $recipientlocalpart, $recipientdomain, $transportid, $actionid; // eodrcpt

	$time = time();
	$logdata += [
		"serial" => serial(),
		"owner" => $recipient,
		"ownerdomain" => $recipientdomain,
		"hostname" => gethostname(),
		"messageid" => $transaction["id"],
		"senderip" => $senderip,
		"senderport" => $senderport,
		"serverip" => $serverip,
		"serverport" => $serverport,
		"serverid" => $serverid,
		"senderhelo" => $senderhelo,
		"tlsstarted" => $tlsstarted,
		"saslusername" => $saslusername,
		"saslauthed" => $saslauthed,
		"sender" => $transaction["sender"],
		"senderlocalpart" => $transaction["senderlocalpart"],
		"senderdomain" => $transaction["senderdomain"],
		"senderparams" => $transaction["senderparams"],
		"recipient" => $recipient,
		"recipientlocalpart" => $recipientlocalpart,
		"recipientdomain" => $recipientdomain,
		"transportid" => $transportid,
		"actionid" => $actionid,
		"subject" => MIME("0")->getHeader("subject"),
		"size" => MIME("0")->getSize(),
		"receivedtime" => round($time * 1000),
		"metadata" => GetMetaData()
	];

	SetMetaData(GetMetaData() + ["receivedtime" => "$time"]);

	$path = "/".$indexname."-".strftime($indexrotate, $time)."/".$indextype."/".$transaction["id"].":".$actionid;
	http($elasticurl.$path, $httpoptions, [], json_encode($logdata));
}

function Reject(...$args) {
	global $logdata;
	$logdata["action"] = "REJECT";
	$logdata["reason"] = isset($args[0]) ? $args[0] : "";
	sendlog($logdata);
	builtin Reject(...$args);
}
function Deliver(...$args) {
	global $logdata;
	$logdata["action"] = "QUEUE";
	sendlog($logdata);
	builtin Deliver(...$args);
}
function Defer(...$args) {
	global $logdata;
	$logdata["action"] = "DEFER";
	$logdata["reason"] = isset($args[0]) ? $args[0] : "";
	sendlog($logdata);
	builtin Defer(...$args);
}
function Delete(...$args) {
	global $logdata;
	$logdata["action"] = "DELETE";
	sendlog($logdata);
	builtin Delete(...$args);
}
function Quarantine(...$args) {
	global $logdata;
	$logdata["action"] = "QUARANTINE";
	$logdata["reason"] = isset($args[1]["reason"]) ? $args[1]["reason"] : "";
	sendlog($logdata);
	builtin Quarantine(...$args);
}
function ScanRPD(...$args) {
	global $logdata;
	$outbound = $args[0]["outbound"] ?? false;
	$logdata["score_rpd"] = builtin ScanRPD([ "outbound" => $outbound ]);
	$logdata["score_rpd_refid"] = builtin ScanRPD([ "outbound" => $outbound, "refid" => true ]);
	$logdata["score_rpdav"] = builtin ScanRPD([ "outbound" => $outbound, "extended_result" => true ])["virus_score"];
	return builtin ScanRPD(...$args);
}
function ScanSA(...$args) {
	global $logdata;
	$logdata["scores"]["sa"] = builtin ScanSA();
	$logdata["scores"]["sa_rules"] = builtin ScanSA(["rules" => true]);
	return builtin ScanSA(...$args);
}
function ScanKAV(...$args) {
	global $logdata;
	$logdata["scores"]["kav"] = builtin ScanKAV() ? : "";
	return builtin ScanKAV(...$args);
}
function ScanCLAM(...$args) {
	global $logdata;
	$logdata["scores"]["clam"] = builtin ScanCLAM() ? : "";
	return builtin ScanCLAM(...$args);
}
```

File: `elastic/post.hsl`
```
include "elastic/settings.hsl";

$httpoptions = [
	"timeout" => 10,
	"background" => true,
	"background_hash" => hash($message["id"]["transaction"]),
	"background_retry_count" => 1,
	"tls_default_ca" => true,
	"headers" => ["Content-Type: application/json"]
];

function sendlog() {
	global $elasticurl, $httpoptions, $indexname, $indexrotate, $indextype; // settings
	global $message, $arguments;

	$receivedtime = GetMetaData()["receivedtime"];
	$time = time();
	$logdata["doc"] = [
		"queue" => [
			"action" => $arguments["action"] ?? "DELIVER",
			"retry" => $arguments["retry"],
			"errormsg" => $arguments["attempt"]["result"]["reason"][0] ?? "",
			"errorcode" => $arguments["attempt"]["result"]["code"],
			"transfertime" => $arguments["attempt"]["duration"]
		],
		"sender" => $message["sender"],
		"senderdomain" => $message["senderaddress"]["domain"],
		"recipient" => $message["recipient"],
		"recipientdomain" => $message["recipientaddress"]["domain"],
		"transportid" => $message["transportid"],
		"finishedtime" => round($time * 1000)
	];

	$path = "/".$indexname."-".strftime($indexrotate, $receivedtime)."/".$indextype."/".$message["id"]["transaction"].":".$message["id"]["queue"]."/_update";
	http($elasticurl.$path, $httpoptions, [], json_encode($logdata));
}

sendlog();
```

Finally, in Halon, under the **Code Editor** page, include the `elastic/eodrcpt.hsl` file in the **EOD rcpt** context, and include the `elastic/post.hsl` file in the QUEUE **Post-delivery** context; refer to the [include](https://docs.halon.io/hsl/structures.html#include) statement.
