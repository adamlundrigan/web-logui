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

Halon remote logging to Elasticsearch
---
In order to start logging to Elasticsearch, please follow the [Remote logging to Elasticsearch](https://support.halon.io/hc/en-us/articles/115005513365) guide on our knowledge base.

The implementation code is available in our [code repository](https://github.com/halon/hsl-examples/tree/master/logging/elasticsearch).

Halon remote logging to Logstash
---

To enable the textlog feature, please refer to our [Remote syslog to Logstash](https://support.halon.io/hc/en-us/articles/360000700065) article.
