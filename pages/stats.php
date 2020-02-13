<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!$settings->getDisplayStats()) die("The setting display-stats isn't enabled");

require_once BASE.'/inc/twig.php';

if (isset($_POST['set-target']) || ($_GET['set-target']))
  $target = $_POST['set-target'] ?? $_GET['set-target'];

[$index_start, $index_stop] = valid_date_range($_GET['start'] ?? null, $_GET['stop'] ?? null);

$stats = $settings->getStatsAggregations();

uasort($stats['line'], function ($a, $b) {
  if (!isset($a['groupby']))
    return -1;
  if ($a['groupby'] == $b['groupby'])
    return 0;
  return -1;
});

uasort($stats['bar'], function ($a, $b) {
  if (!isset($a['groupby']))
    return -1;
  if ($a['groupby'] == $b['groupby'])
    return 0;
  return 1;
});

uasort($stats['pie'], function ($a, $b) {
  if (!isset($a['groupby']))
    return -1;
  if ($a['groupby'] == $b['groupby'])
    return 0;
  return 1;
});

$twigLocals = [
  'access'      => Session::Get()->getAccess(),
  'target' => $target,
  'index_start' => $index_start,
  'index_stop'  => $index_stop,
  'stats' => [
    'line' => $stats['line'] ?? [],
    'bar' => $stats['bar'] ?? [],
    'pie' => $stats['pie'] ?? []
  ],
  'default_view' => $settings->getStatsDefaultView()
];

echo $twig->render('stats.twig', $twigGlobals + $twigLocals);
