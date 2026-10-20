<?php
namespace HarmatConstructionFixture;
if (PHP_SAPI !== 'cli') exit(1);
$bootstrap = file_get_contents(__DIR__ . '/2026-10-18-test-construction-updates.php');
if (hash('sha256', str_replace("\r\n", "\n", $bootstrap)) !== '15c538994130e36f6304c65a8a521b24d9d4329f108da8fceace723b7bd6d227') throw new \RuntimeException('Fixture bootstrap drift');
$marker = '$registry = harmat_cu_registry();';
if (substr_count($bootstrap, $marker) !== 1) throw new \RuntimeException('Fixture boundary drift');
eval(substr(substr($bootstrap, 0, strpos($bootstrap, $marker)), 5));
$registry = harmat_cu_registry();
$ready = static function ($file) { return true; };
$records = harmat_cu_records($registry, '2026-10-20', $ready);
check(count($registry) === 7 && count($records) === 7, 'seven dated records');
check(array_column($records, 'id') === array('2026-10-20', '2026-10-19', '2026-10-13', '2026-10-12-site-01', '2026-10-12-site-02', '2026-10-12-site-03', '2026-10-12-site-04'), 'newest first with old order retained');
check(count(harmat_cu_records($registry, '2026-10-18', $ready)) === 5, 'future publication excluded');
check(count(harmat_cu_records($registry, '2026-10-19', $ready)) === 5, 'capture and website publication dates distinct');
check($records[0]['duration'] === 113.033333 && $records[1]['duration'] === 145.783333, 'actual video frame-derived duration');
check(harmat_cu_records($registry, '2026-10-20', static function ($file) { return strpos($file, '2026-10-20') === false; })[0]['date'] === '2026-10-19', 'missing latest media falls back to preceding date');
check(count(harmat_cu_records($registry, '2026-10-20', static function ($file) { return strpos($file, '2026-10-19') === false && strpos($file, '2026-10-20') === false; })) === 5, 'both new media absent retains all old records');
foreach ($records as $record) {
    check(strpos($record['video'], HARMAT_CU_MEDIA_BASE) === 0 && strpos($record['poster'], HARMAT_CU_MEDIA_BASE) === 0, 'same-origin existing media base');
    foreach (array('title', 'summary', 'note') as $field) {
        check(preg_match('//u', $record[$field]['hu']) === 1 && preg_match('//u', $record[$field]['zh']) === 1, 'valid bilingual UTF8');
    }
}
foreach (array(array('approved',false),array('status','draft'),array('file','../secret'),array('date','2027-01-01'),array('duration',INF)) as $change) {
    $bad=$records[0];$bad[$change[0]]=$change[1];
    check(harmat_cu_records(array($bad),'2026-10-20',$ready) === array(), 'unsafe record refused');
}
$state['page']='epitesi-naplo';
$legacy='<section class="harmat-construction-september" data-harmat-construction-september="1" aria-labelledby="harmat-construction-september-title">September retained</section><section class="harmat-build-log-list"><article>Archive retained</article></section>';
$form='<div id="h22-offer-modal"><form id="h22-offer-form"><input name="your-name"><select name="source"><option>Google keresés</option></select><input type="checkbox" required></form></div>';
$input='<!doctype html><html lang="hu"><head><title>Journal</title></head><body><header>Native header</header><main id="main">'.$legacy.'</main>'.$form.'<footer>Native footer</footer></body></html>';
$output=harmat_cu_journal_output($input);
check($output!==$input && substr_count($output,'data-hcu-player data-hcu-video=')===7,'seven lazy players');
check(strpos($output,$legacy)!==false && strpos($output,$form)!==false,'native archive and form byte exact');
check(!preg_match('~<(?:video|source|iframe)\b~i', $output),'no eager MP4 transfer');
check(harmat_cu_journal_output($output)===$output,'idempotent output');
check(strpos(harmat_cu_description('old'),'2026. október 20.')!==false,'latest metadata updates automatically');
$graph=array(array('@id'=>'legacy-video','@type'=>'VideoObject'));
$schema=harmat_cu_schema($graph);
check(count($schema)===8 && $schema[0]===$graph[0],'old schema retained');
check($schema[1]['dateCreated']==='2026-10-20' && $schema[1]['duration']==='PT113.03S','factual latest schema');
check(harmat_cu_schema($schema)===$schema,'no duplicate schema');
foreach (array('POST','PUT','DELETE') as $method) { $_SERVER['REQUEST_METHOD']=$method;check(!harmat_cu_context(),'no write context'); }
$_SERVER['REQUEST_METHOD']='GET';
foreach (array('admin','json','secondary','REST_REQUEST','DOING_AJAX','DOING_CRON') as $flag) { $state['flags'][$flag]=true;check(!harmat_cu_context(),'excluded nonpublic context');$state['flags']=array(); }
$state['missing']=true;check(harmat_cu_journal_output($input)===$input,'missing media safe fallback');$state['missing']=false;
$state['page']='other';check(harmat_cu_journal_output($input)===$input,'unrelated page untouched');$state['page']='epitesi-naplo';
if (($argv[2] ?? '')==='--render') { echo $output;exit; }
echo json_encode(array('status'=>'PASS','checks'=>$checks,'records'=>count($records))) . PHP_EOL;
