<?php

use ONGR\ElasticsearchDSL;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhraseQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MultiMatchQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateHistogramAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FiltersAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\TopHitsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\SumAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\AvgAggregation;

class ElasticsearchBackend extends Backend
{
  private $es = null;
  private $rotate;

  public function __construct($es)
  {
    $this->es = $es;
    $this->rotate = $this->es->getRotate();
    if (!in_array($this->es->getIndex().strftime($this->rotate, time()), Session::Get()->getElasticsearchIndices())) {
      try {
        $params = [
          'index' => [$this->es->getIndex().'*', $this->es->getTextlogIndex().'*']
        ];
        Session::Get()->setElasticsearchIndices(array_keys($this->es->client()->indices()->get($params)));
      } catch (Exception $e) { die($e->getMessage()); }
    }
  }

  public function isValid() { return $this->client() != null; }

  public function supportsHistory() { return true; }

  public function scrollMailHistory($scroll_id, $param) {
    try {
      $results = [];
      $settings = Settings::Get();

      // query elasticsearch with an scroll id
      $response = $this->es->client()->scroll([
        'scroll_id' => $scroll_id,
        'scroll' => '2m'
      ]);

      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $results[] = es_document_parser($m, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter());

      return ['items' => $results, 'total' => $response['hits']['total']['value'] ?? 0, 'scroll_id' => $response['_scroll_id'] ?? null];
    } catch (Exception $e) {
      $errors[] = "Exception code: ".$e->getMessage();
      return [];
    }
  }

  public function loadMailHistory($search, $size, $param, &$errors = array())
  {
    try {
      $results = [];
      $settings = Settings::Get();

      // set up interval for indices
      $indices = $this->initIndices(
        $this->es->getIndex(),
        $this->es->getRotate(),
        $param['index_range']['start'],
        $param['index_range']['stop']
      );

      if (count($indices) < 1)
        return [];

      $schema = $settings->getElasticsearchMappings();

      // sort
      $fieldsort = $this->es->getTimefilter();
      if (isset($param['sort'])) {
        switch ($param['sort']) {
          case 'to':
            $fieldsort = $schema['msgto'].'.keyword';
          break;
          case 'from':
            $fieldsort = $schema['msgfrom'].'.keyword';
          break;
          case 'subject':
            $fieldsort = $schema['msgsubject'].'.keyword';
            break;
        }
      }
      $fieldorder = isset($param['sortorder']) ? $param['sortorder'] : 'DESC';
      $sort = new FieldSort($fieldsort, $fieldorder === 'DESC' ? FieldSort::DESC : FieldSort::ASC);

      // query
      $query = new BoolQuery();

      // time zone
      $query_timezone = [];
      if (isset($_SESSION['timezone_utc'])) {
        $query_timezone = ['time_zone' => trim($_SESSION['timezone_utc'])];
      }

      // offset
      $start = new DateTime($param['index_range']['start']);
      $stop = new DateTime($param['index_range']['stop']);
      $stop->modify('+1 day');

      $query->add(new RangeQuery($this->es->getTimefilter(), [
        'lt' => $stop->getTimestamp() * 1000,
        'gte' => $start->getTimestamp() * 1000
      ] + $query_timezone));

      if (is_string($search) && strlen($search) > 0) {
        $searchFields = [
          $schema['msgsubject'],
          $schema['msgfromdomain'],
          $schema['msgfrom'],
          $schema['msgtodomain'],
          $schema['msgto'],
          $schema['msgid']
        ];
        if (inet_pton($search))
          array_push($searchFields, $schema['msgfromserver']);
        $query->add(new MultiMatchQuery($searchFields, $search, ['operator' => 'AND']));
      }

      if (isset($param['filters'])) {
        $boolField = new BoolQuery();
        foreach ($param['filters'] as $field => $i) {
          $boolFilter = new BoolQuery();
          foreach ($i as $filter) {
            $boolOperator = BoolQuery::SHOULD;
            if ($filter['operator'] == 'contains')
              $operator = 'OR';
            else if ($filter['operator'] == 'not') {
              $operator = 'AND';
              $boolOperator = BoolQuery::MUST_NOT;
            } else
              $operator = 'AND';
            switch ($field) {
              case 'messageid':
                $boolFilter->add(new MatchQuery($schema['msgid'], $filter['value'], ['operator' => $operator]), $boolOperator);
                break;
              case 'subject':
                $boolFilter->add(new MatchQuery($schema['msgsubject'], $filter['value'], ['operator' => $operator]), $boolOperator);
                break;
              case 'from':
                $boolFilter->add(new MultiMatchQuery([$schema['msgfrom'], $schema['msgfromdomain']], $filter['value'], ['operator' => $operator]), $boolOperator);
                break;
              case 'to':
                $boolFilter->add(new MultiMatchQuery([$schema['msgto'], $schema['msgtodomain']], $filter['value'], ['operator' => $operator]), $boolOperator);
                break;
              case 'remoteip':
                if (inet_pton($filter['value']))
                  $boolFilter->add(new MatchQuery($schema['msgfromserver'], $filter['value'], ['operator' => 'AND']), $boolOperator);
                break;
              case 'status':
                $status = new BoolQuery();
                $status->add(new MatchQuery($schema['msgdescription'], $filter['value'], ['operator' => $operator]), BoolQuery::SHOULD);
                $status->add(new MatchQuery($schema['queue']['key'].'.'.$schema['queue']['value']['errormsg'], $filter['value'], ['operator' => $operator]), BoolQuery::SHOULD);
                $boolFilter->add($status, $boolOperator);
                break;
              case 'action':
                if (strtoupper($filter['value']) == 'QUEUE') {
                  $queue = new BoolQuery();
                  $queue->add(new MatchQuery($schema['msgaction'], $filter['value']), BoolQuery::MUST);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'DELIVER'), BoolQuery::MUST_NOT);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'BOUNCE'), BoolQuery::MUST_NOT);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'DELETE'), BoolQuery::MUST_NOT);

                  $boolFilter->add($queue, $boolOperator);
                } else {
                  $boolFilter->add(new MultiMatchQuery([$schema['msgaction'], $schema['queue']['key'].'.'.$schema['queue']['value']['action']], $filter['value'], ['operator' => 'AND']), $boolOperator);
                }
                break;
              case 'metadata':
                $boolFilter->add(new MultiMatchQuery([$schema['metadata'].'.*'], $filter['value'], ['operator' => $operator]), $boolOperator);
                break;
              default:
                continue;
            }
          }
          $boolField->add($boolFilter);
        }
        $query->add($boolField);
      }

      // restrict
      $restrict = new BoolQuery();
      foreach ($this->restrict_query() as $v)
        $restrict->add(new TermQuery($v['type'], $v['value']), BoolQuery::SHOULD);

      // filter
      $body = new Search();
      $body->addQuery($query);
      $body->addQuery($restrict);
      $body->addSort($sort);

      // params
      $params = [
        'index' => implode(',', $indices),
        'from' => $param['offset'],
        'size' => $size + 1,
        'body' => $body->toArray()
      ];
      if ($this->es->getType())
        $params['type'] = $this->es->getType();

      // query elasticsearch with given params
      $response = $this->es->client()->search($params);
      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $results[] = es_document_parser($m, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter());

      return ['items' => $results, 'total' => $response['hits']['total']['value'] ?? 0, 'scroll_id' => $response['_scroll_id'] ?? null];
    } catch (Exception $e) {
      $errors[] = "Exception code: ".$e->getMessage();
      return [];
    }
  }

  public function getMail($index, $id)
  {
    $result = null;
    $access = Session::Get()->getAccess();
    $settings = Settings::Get();

    $params = [
      'index' => $index,
      'id' => $id,
      'type' => $this->es->getType()
    ];
    try {
      $response = $this->es->client()->get($params);
      if ($response) {
        $mail = es_document_parser($response, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter())['doc'];
        if (is_array($access['mail']) || is_array($access['domain']) || is_array($access['sasl'])) {
          $access_mail = $access_domain = $access_sasl = false;
          if (is_array($access['mail']) && in_array($mail->owner, $access['mail']))
            $access_mail = true;
          if (is_array($access['domain']) && in_array($mail->ownerdomain, $access['domain']))
            $access_domain = true;
          if (is_array($access['sasl']) && in_array($mail->saslusername, $access['sasl']))
            $access_sasl = true;
          if ($access_mail || $access_domain || $access_sasl)
            $result = $mail;
        } else {
          $result = $mail;
        }
      }
    } catch (Exception $e) {}

    return $result;
  }

  public function getTextlog($msgid, $msgts0, $page = 1)
  {
    $result = [];
    $more = false;
    $page = $page > 0 && $page < 100 ? $page : 1;

    try {
      // set up indices
      $indices = $this->initIndices(
        $this->es->getTextlogIndex(),
        $this->es->getTextlogRotate(),
        $msgts0,
        time(),
        true
      );

      if (count($indices) < 1)
        return [];

      if (count($indices) > $this->es->getTextlogRotateLimit())
        $indices = array_slice($indices, 0, $this->es->getTextlogRotateLimit());

      // sort
      $sort = new FieldSort($this->es->getTextlogTimefilter(), FieldSort::ASC);

      // filter
      $query = new BoolQuery();
      $query->add(new MatchPhraseQuery('message', '"'.$msgid.'"'));

      $body = new Search();
      $body->addQuery($query);
      $body->addSort($sort);

      $params = [
        'index' => implode(',', $indices),
        'type' => $this->es->getTextlogType(),
        'size' => ($this->es->getTextlogLimit() * $page) + 1,
        'body' => $body->toArray()
      ];

      $response = $this->es->client()->search($params);
      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $result[] = logstash_document_parser($m);

      if (count($result) > ($this->es->getTextlogLimit() * $page)) {
        array_pop($result);
        $more = true;
      }
    } catch (Exception $e) {}

    return ['result' => $result, 'more' => $more];
  }

  public function getAggregation($buckets = [], $param = [], $metrics = null) {
    try {
      $settings = Settings::Get();
      $result = [];
      $f = 0;

      $indices = $this->initIndices(
        $this->es->getIndex(),
        $this->es->getRotate(),
        $param['start'],
        $param['stop']
      );

      if (count($indices) < 1)
        return [];

      $body = new Search();

      // aggregations
      $aggregation = $this->addBucket($buckets, 0, $metrics, $param['interval'] ?? null);

      if (!$aggregation)
        return [];

        // time zone
        $query_timezone = [];

      // range
      if ($param['start'] && $param['stop']) {
        if ($param['interval'] == 'fixed_interval') {
          $start = new DateTime('now');
          $stop = new DateTime('now');
          $start->modify('-1 hour');
        } else {
          $start = new DateTime($param['start']);
          $stop = new DateTime($param['stop']);
          $stop->modify('+1 day');

          if (isset($_SESSION['timezone_utc'])) {
            $query_timezone = ['time_zone' => trim($_SESSION['timezone_utc'])];
          }
        }

        // query
        $query = new BoolQuery();
        $query->add(new RangeQuery($this->es->getTimefilter(), [
          'lt' => $param['offset'] ?? $stop->getTimestamp() * 1000,
          'gte' => $start->getTimestamp() * 1000
        ] + $query_timezone), BoolQuery::MUST);

        $body->addQuery($query);
      }

      if ($param['target']) {
        $query = new MatchQuery($settings->getElasticsearchMappings()['msgtodomain'], $param['target']);
        $body->addQuery($query);
      }

      // restrict
      $restrict = new BoolQuery();
      foreach ($this->restrict_query() as $v)
        $restrict->add(new TermQuery($v['type'], $v['value']), BoolQuery::SHOULD);

      $body->addQuery($restrict);
      $body->addAggregation($aggregation);

      $params = [
        'index' => implode(',', $indices),
        'type' => $this->es->getType(),
        'body' => $body->toArray(),
        'size' => 0
      ];

      $response = $this->es->client()->search($params);
      return $response ?? [];
    } catch (Exception $e) {
      echo $e;
      return [];
    }
  }

  private function addBucket($bucket, $i = 0, $metrics, $interval = null) {
    $agg = $this->addAggregation(
      $bucket['type'],
      $bucket['key'] ?? ++$i,
      $bucket['field'] ?? null,
      $bucket['filters'] ?? null,
      ['size' => $bucket['size'] ?? null, 'sort' => $bucket['sort'] ?? null, 'interval' => $interval]
    );

    if (isset($bucket['aggregation']))
      $agg->addAggregation($this->addBucket($bucket['aggregation'], $i, $metrics));
    else if (isset($metrics))
      $agg->addAggregation($this->addBucket($metrics, $i, null));

    return $agg;
  }

  private function addAggregation($type, $name, $field, $filters, $opts = []) {
    switch ($type) {
      case 'terms':
        $termsAggregation = new TermsAggregation($name ?? ++$f, $field);
        if (isset($opts['sort']))
          $termsAggregation->addParameter('order', ['_count' => $opts['sort']]);
        if (isset($opts['size']))
          $termsAggregation->addParameter('size', $opts['size']);
        return $termsAggregation;
      case 'filters':
        $filterAgg = [];
        foreach ($filters ?? [] as $filter_name => $filter) {
          if ($filter['type'] == 'phrase')
            $filterAgg[$filter_name] = new MatchPhraseQuery($filter['field'], $filter['value']);
        }
        return new FiltersAggregation($name ?? ++$f, $filterAgg);
      case 'sum':
        return new SumAggregation($name ?? ++$f, $field);
      case 'min':
        return new MinAggregation($name ?? ++$f, $field);
      case 'max':
        return new MaxAggregation($name ?? ++$f, $field);
      case 'avg':
        return new AvgAggregation($name ?? ++$f, $field);
      case 'histogram':
        return new DateHistogramAggregation($name ?? ++$f, $field, $opts['interval'] ?? 'day');
      case 'fixed_interval':
        return new DateHistogramAggregation($name ?? ++$f, $field, '1m');
      default:
        return null;
    }
  }

  public function restrict_query()
  {
    $access = Session::Get()->getAccess();
    $restrict = [];
    if (is_array($access['domain']))
      foreach ($access['domain'] as $domain)
        $restrict[] = ['type' => 'ownerdomain', 'value' => $domain];

    if (is_array($access['mail']))
      foreach ($access['mail'] as $mail)
        $restrict[] = ['type' => 'owner', 'value' => $mail];

    if (is_array($access['sasl']))
      foreach ($access['sasl'] as $sasl)
        $restrict[] = ['type' => 'saslusername', 'value' => $sasl];

    return $restrict;
  }

  private function initIndices($index, $rotate, $start, $stop, $timestamp = false)
  {
    if ($timestamp) {
      $intervalStart = new DateTime();
      $intervalStart->setTimestamp($start);
      $intervalStart->modify('-1 day');

      $intervalStop = new DateTime();
      $intervalStop->setTimestamp($stop);
      $intervalStop->modify('+1 day');
    } else {
      // set up interval for indices
      $intervalStart = new DateTime($start);
      $intervalStart->modify('-1 day');
      $intervalStop = new DateTime($stop);
      $intervalStop->modify('+2 day');
    }
    $intervalPeriod = new DatePeriod($intervalStart, new DateInterval('P1D'), $intervalStop);

    // verify indices
    $indices = [];
    foreach ($intervalPeriod as $date) {
      $i = $index.strftime($rotate, $date->getTimestamp());
      if ($this->validIndex($i))
        $indices[] = $i;
    }
    return $indices;
  }

  public function validIndex($index)
  {
    if (in_array($index, Session::Get()->getElasticsearchIndices()))
      return true;
    return false;
  }
}
