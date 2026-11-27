<?php
namespace HarmatConstructionFixture;
if (PHP_SAPI !== 'cli') exit(1);
$bootstrap = file_get_contents(__DIR__ . '/2026-10-18-test-construction-updates.php');
$marker = '$registry = harmat_cu_registry();';
if (substr_count($bootstrap, $marker) !== 1) throw new \RuntimeException('Fixture boundary drift');
eval(substr($bootstrap, 5, strpos($bootstrap, $marker) - 5));

$ready = static function ($file) { return true; };
$records = harmat_cu_records(null, '2026-11-27', $ready);
check(count($records) === 9 && $records[0]['id'] === '2026-11-27', 'one new first journal entry');
check(harmat_cu_records(null, '2026-11-26', $ready) === array_slice($records, 1), 'eight original entries are unchanged');
check($records[0]['journal_only'] && $records[0]['sound'] && $records[0]['date_is_publication'], 'journal and click sound opt-in only');
$journal = harmat_cu_journal_markup($records);
check(substr_count($journal, 'data-hcu-sound="1"') === 1 && strpos($journal, 'Közzétéve: 2026. november 27.') !== false && !str_contains($journal, '<video'), 'only new player can start with audio; date is labelled; no eager video');
$graph = harmat_cu_schema(array());
check($graph[0]['duration'] === 'PT168.9S' && $graph[0]['uploadDate'] === '2026-11-27' && !isset($graph[0]['dateCreated']), 'no invented footage date');
check(harmat_cu_schema($graph) === $graph, 'video metadata remains idempotent');
check(harmat_cu_share_image('fallback') === HARMAT_CU_MEDIA_BASE . '2026-10-30-harmat-site.jpg', 'existing share cover preserved');
$harmat_home_family_units = array();
$body = include dirname($candidate) . '/harmat-home-family/body.php';
$home = harmat_cu_home_teaser($body);
check(is_string($home) && strpos($home, '#construction-2026-10-30') !== false && strpos($home, '2026-11-27-harmat') === false, 'existing homepage teaser preserved');
echo 'PASS ' . $checks . " narrated journal assertions\n";
