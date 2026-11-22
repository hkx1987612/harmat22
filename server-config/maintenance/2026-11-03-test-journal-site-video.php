<?php
namespace HarmatConstructionFixture;
if (PHP_SAPI !== 'cli') exit(1);
$bootstrap = file_get_contents(__DIR__ . '/2026-10-18-test-construction-updates.php');
$marker = '$registry = harmat_cu_registry();';
if (substr_count($bootstrap, $marker) !== 1) throw new \RuntimeException('Fixture boundary drift');
eval(substr($bootstrap, 5, strpos($bootstrap, $marker) - 5));
$registry = harmat_cu_registry();
$ready = static function ($file) { return true; };
$records = harmat_cu_records($registry, '2026-11-03', $ready);
check(count($records) === 8, 'eight valid records');
check(array_column(array_slice($records, 0, 2), 'id') === array('2026-10-30', '2026-10-20'), 'site video first');
check(count(harmat_cu_records($registry, '2026-11-02', $ready)) === 7, 'no pre-publication entries');
$markup = harmat_cu_journal_markup($records);
check(substr_count($markup, 'data-hcu-player data-hcu-video=') === 8, 'eight click-only players');
check($records[0]['file'] === '2026-10-30-harmat-site' && empty($records[0]['journal_only']), 'same site footage eligible for homepage');
check(!preg_match('~<(?:video|source|iframe)\b~i', $markup), 'no eager video');
$graph = harmat_cu_schema(array());
check($graph[0]['duration'] === 'PT47.6S', 'media duration');
check($graph[0]['uploadDate'] === '2026-11-03' && $graph[0]['dateCreated'] === '2026-10-30', 'capture and publish dates distinct');
check(harmat_cu_schema($graph) === $graph, 'schema idempotent');
$harmat_home_family_units = array();
$body = include dirname($candidate) . '/harmat-home-family/body.php';
$home = harmat_cu_home_teaser($body);
check(is_string($home) && strpos($home, '#construction-2026-10-30') !== false
    && strpos($home, '2026-10-30-harmat-site.jpg') !== false && strpos($home, '2026-10-30-harmat-site.mp4') !== false,
    'homepage uses matching October 30 record, poster and video');
echo json_encode(array('status' => 'PASS', 'checks' => $checks)) . PHP_EOL;
