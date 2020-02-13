<?php

if (!defined('WEB_LOGUI')) die('File not included');
header('Content-Type: application/json; charset=UTF-8');

function checkAccess($perm)
{
  if (Session::Get()->checkAccessAll())
    return true;
  $access = Session::Get()->getAccess();
  foreach ($access as $type)
    foreach ($type as $item)
      if ($item == $perm)
        return true;
  if (strpos($perm, '@') !== false)
    if (Session::Get()->checkAccessMail($perm))
      return true;
  return false;
}

function getDatasets($agg, $data, $metrics) {
  $datasets = [];

  if (isset($data[$agg['key']]['buckets'])) {
    foreach ($data[$agg['key']]['buckets'] as $k => $bucket) {
      $dataset['label'] = $bucket['key'] ?? $k;
      $dataset['data'] = [];

      if (isset($agg['aggregation']) && isset($bucket[$agg['aggregation']['key']])) {
        $data = getMetrics($agg['aggregation'], $bucket[$agg['aggregation']['key']], $metrics);
      } else {
        $data = getMetrics($agg, $bucket, $metrics);
      }
      $dataset['data'] = $data;

      $datasets[] = $dataset;
    }
  } else {
    $datasets[]['data'] = getMetricValue($data, $metrics);
  }

  return $datasets;
}

function getMetrics($agg, $bucket, $metrics) {
  if (isset($agg['aggregation'])) {
    foreach ($bucket['buckets'] as $b) {
      return getMetrics($agg['aggregation'], $b[$agg['aggregation']['key']], $metrics);
    }
  } else {
    $data = [];
    if (isset($bucket['buckets'])) {
      foreach ($bucket['buckets'] as $k => $b) {
        $data[$b['key'] ?? $k] = getMetricValue($b, $metrics);
      }
    } else {
      $data[$bucket['key'] ?? 0] = getMetricValue($bucket, $metrics);
    }
    return $data;
  }
}

function getMetricValue($value, $metrics = null) {
  if (!isset($metrics))
    $v = $value['doc_count'];
  else if ($metrics['type'] == 'sum')
    $v = $value[$metrics['key']]['value'];

  if (isset($metrics) && is_callable($metrics['format']))
    $v = $metrics['format']($v);

  return $v;
}

if ($_POST['page'] == 'stats')
{
  if (!$settings->getDisplayStats())
    die(json_encode(array('error' => "The setting display-stats isn't enabled")));

  if (!$_POST['start'] || !$_POST['stop'])
    die(json_encode(['error' => 'Missing date range']));

  $name = $_POST['type'];
  [$start, $stop] = valid_date_range($_POST['start'], $_POST['stop']);

  $dt_start = new DateTime($start);
  $dt_stop = new DateTime($stop);
  $days = $dt_stop->diff($dt_start)->format('%a');
  if ($days <= 2)
    $interval = 'hour';
  else if ($days <= 62)
    $interval = 'day';
  else if ($days > 62 && $days < 365)
    $interval = 'month';
  else
    $interval = 'year';

  $fixedInterval = null;
  if (isset($_POST['interval'])) {
    if ($_POST['interval'] == 'fixed_interval')
      $fixedInterval = 'fixed_interval';
  }

  $colorset = $settings->getStatsColor();

  $map = $settings->getStatsAggregation($_POST['chart'], $name);
  if (!$map)
    die(json_encode(['error' => 'Unknown type']));

  if (in_array($_POST['chart'], ['pie', 'bar']) && $name) {
    try {
      $aggs = $map['buckets']['aggregation'];

      $esBackend = new ElasticsearchBackend($settings->getElasticsearch());
      $data = $esBackend->getAggregation(
        $aggs,
        [
          'start' => $start,
          'stop' => $stop,
          'target' => $_POST['target'] ?? null,
          'interval' => $fixedInterval
        ],
        $map['metrics']
      );

      $datasets = getDatasets($aggs, $data['aggregations'], $map['metrics'] ?? null);

      $chartdata = [];
      $chartdata['label'] = $name;

      $labels = [];
      foreach ($datasets as $k => $dataset) {
        $data = [];
        if (is_array($dataset['data']))
          foreach ($dataset['data'] as $label => $v)
            $data[isset($label) ? $label : $dataset['label']] = $v ?? 0;
        else
          $data[$dataset['label']] = $dataset['data'];

        foreach ($data as $label => $v) {
          $name = $label;
          $chartdata['backgroundColor'][] = $settings->getStatsLabelColor()[$name]['bg'] ?? $colorset[$color++ % count($colorset)];
          if (isset($settings->getStatsLabelColor()[$name]) && isset($settings->getStatsLabelColor()[$name]['border']))
            $chartdata['borderColor'][] = $settings->getStatsLabelColor()[$name]['border'];
          $labels[] = $name;
          $chartdata['data'][] = $v;
        }
      }

      if (count($chartdata) > 0)
        die(json_encode(['label' => $map['label'] ?? '', 'group' => $map['groupby'] ?? '', 'labels' => $labels, 'datasets' => [$chartdata]]));
      else
        die(json_encode(['label' => $map['label'] ?? '', 'group' => $map['groupby'] ?? '', 'labels' => [], 'datasets' => []]));
    } catch (Exception $e) {}
  }

  if ($_POST['chart'] == 'line' && isset($_POST['type'])) {
    try {
      $aggs = $map['buckets']['aggregation'];

      if ($fixedInterval) {
        $aggs['type'] = 'fixed_interval';
        $interval = $fixedInterval;
      }

      $esBackend = new ElasticsearchBackend($settings->getElasticsearch());
      $data = $esBackend->getAggregation(
        $aggs,
        [
          'start' => $start,
          'stop' => $stop,
          'target' => $_POST['target'] ?? null,
          'interval' => $interval
        ],
        $map['metrics']
      );

      $datasets = getDatasets($aggs, $data['aggregations'], $map['metrics'] ?? null);

      $chartdata = [];
      $items = [];
      foreach ($datasets as $dataset) {
        if (is_array($dataset['data'])) {
          foreach ($dataset['data'] as $k => $v) {
            $items[$k]['data'][] = ['t' => $dataset['label'], 'y' => $v];
          }
        } else {
          if ($map['splitseries'] === false)
            $items[$map['legend'] ?? 'data']['data'][] = ['t' => $dataset['label'], 'y' => $dataset['data']];
          else
            $items[$dataset['label']]['data'][] = ['t' => $dataset['label'], 'y' => $dataset['data']];
        }
      }
      foreach ($items as $k => $item) {
        $i['label'] = $k;
        $i['data'] = $item['data'];
        $i['backgroundColor'] = $settings->getStatsLabelColor()[$k]['bg'] ?? $colorset[$color++ % count($colorset)];
        if ($settings->getStatsLabelColor()[$k]['border'])
          $i['borderColor'] = $settings->getStatsLabelColor()[$k]['border'];
        $i['lineTension'] = 0.2;
        $chartdata[] = $i;
      }

      die(json_encode(['label' => $map['label'] ?? '', 'group' => $map['groupby'] ?? '', 'datasets' => $chartdata, 'interval' => $interval]));
    } catch (Exception $e) {}
  }

  die(json_encode(array('error' => 'Not implemented yet')));
}

if ($_POST['page'] == 'messages') {
  if ($_POST['type'] == 'datepicker') {
    $indices = str_replace($settings->getElasticsearch()->getIndex(), '', Session::Get()->getElasticsearchIndices());
    die(json_encode(['indices' => $indices]));
  }
}

die(json_encode(array('error' => 'unsupported request')));
