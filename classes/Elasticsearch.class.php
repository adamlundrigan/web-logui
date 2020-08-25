<?php

use Elasticsearch\ClientBuilder;

class Elasticsearch
{
  private $_client = null;

  private $hosts;
  private $index;
  private $indexPattern;
  private $indexRegExp;
  private $type;
  private $rotate;
  private $timefilter;

  private $textlog_index;
  private $textlog_rotate;
  private $textlog_type;
  private $textlog_timefilter;
  private $textlog_limit;
  private $textlog_rotatelimit;

  private $username;
  private $password;
  private $tls;
  private $timeout;

  public function client() { return $this->_client; }
  public function getIndex() { return $this->index; }
  public function getIndexPattern() { return $this->indexPattern; }
  public function getIndexRegExp() { return $this->indexRegExp; }
  public function getType() { return $this->type; }
  public function getRotate() { return $this->rotate; }
  public function getTimefilter() { return $this->timefilter; }

  public function getTextlogIndex() { return $this->textlog_index; }
  public function getTextlogRotate() { return $this->textlog_rotate; }
  public function getTextlogType() { return $this->textlog_type; }
  public function getTextlogTimefilter() { return $this->textlog_timefilter; }
  public function getTextlogLimit() { return $this->textlog_limit; }
  public function getTextlogRotateLimit() { return $this->textlog_rotatelimit; }

  public function __construct($hosts, $index, $username = null, $password = null, $tls = [], $timeout = null)
  {
    $this->hosts = $hosts;
    $this->index = $index['mail']['name'];
    $this->indexPattern = $this->index . (($index['mail']['wildcard'] ?? false) ? '*-' : '');
    $this->indexRegExp = ($index['mail']['wildcard'] ?? false)
      ? '#^'.preg_quote($this->index, '#').'(?<subindex>.+)\-(?<rotate>[^-]+)$#'
      : '#^'.preg_quote($this->index, '#').'(?<rotate>[^-]+)$#'
    ;
    $this->type = $index['mail']['type'] ?? null;
    $this->rotate = $index['mail']['rotate'];
    $this->timefilter = $index['mail']['timefilter'];

    $this->textlog_index = $index['textlog']['name'];
    $this->textlog_rotate = $index['textlog']['rotate'];
    $this->textlog_type = $index['textlog']['type'];
    $this->textlog_timefilter = $index['textlog']['timefilter'];
    $this->textlog_limit = $index['textlog']['limit'] ?? 25;
    $this->textlog_rotatelimit = $index['textlog']['search_rotate_limit'] ?? 10;

    $this->username = $username;
    $this->password = $password;
    $this->tls = $tls;
    $this->timeout = is_numeric($timeout) ? $timeout : 5;

    try {
      $this->_client = ClientBuilder::create()->setHosts($this->hosts)->build();
    } catch(Exception $e) {
      die($e);
    }
  }
}
