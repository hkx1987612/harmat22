<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../../wp-plugins/harmat-sales-manager/attribution-report.php';
function check($value, string $label): void { if (!$value) throw new RuntimeException($label); }

$c='Harmat_CRM_Attribution_Report';
$v4=array('utm_source'=>'youtube','utm_medium'=>'paid_video','utm_campaign'=>'harmat_v4_leads_202611');
$fb=array('utm_source'=>'facebook','utm_medium'=>'paid_social','utm_campaign'=>'harmat_v4_fb_202611');
check($c::classify(array($v4))===array('youtube','tag','harmat_v4_leads_202611'),'V4 tag');
check($c::classify(array($fb))[0]==='facebook','Facebook tag');
check($c::classify(array(array('gclid'=>'Example_GCLID_01')))[1]==='clickOnly','Click ID alone is not Search');
check($c::classify(array(array('gad_campaignid'=>'24449147750')))[0]==='search','Verified Search ID');
check($c::classify(array(), array('24215666538'))===array('search','log',null),'Verified prior Search');
check($c::classify(array($v4), array('24450697579'))[1]==='conflict','Subscriber series not V4 inquiries');
check($c::classify(array($v4, $fb))[1]==='conflict','Disagreeing channel evidence');
check($c::classify(array(array_replace($v4,array('utm_source'=>'facebook'))))[1]==='conflict','Invalid source/tag combination');
check($c::classify(array(array('gad_campaignid'=>'12345555555')))[1]==='unmapped','Unknown Google campaign');
check($c::classify($c::signals(array('_harmat_offer_source_page'=>'https://elsewhere.test/?utm_campaign=harmat_v4_leads_202611')))[1]==='missing','No cross-origin source-page claim');
check($c::classify($c::signals(array('_harmat_offer_tracking'=>serialize(array('utm'=>$fb)))))[0]==='facebook','Saved nested UTM');
check($c::date('2026-11-31')===null && $c::date('2026-11-09')!==null,'Valid date bounds');

$row=static fn($id,$channel,$status,$email='',$phone='',$lead=0,$reason='tag')=>compact('id','channel','status','email','phone','lead','reason')+array('date'=>'2026-11-10 10:00:00','kind'=>'website');
$summary=$c::summarize(array(
    $row(1,'youtube','confirmed','one@example.test'), $row(2,'youtube','pending','one@example.test'),
    $row(3,'search','confirmed','two@example.test','36300000001'), $row(4,'facebook','pending','two@example.test','36300000001'),
    $row(5,'facebook','invalid','three@example.test'), $row(6,'facebook','pending','four@example.test'),
    $row(7,'search','confirmed','five@example.test','36300000002'), $row(8,'search','pending','six@example.test','36300000002'),
    $row(9,'unknown','confirmed'), $row(10,'unknown','confirmed','seven@example.test'),
));
check(count($summary['rows'])===10 && count($summary['qualified'])===3,'Deduplication, unknowns and exclusions');
check($summary['counts']['youtube']['qualified']===1 && $summary['counts']['unknown']['qualified']===2,'One person is never counted in multiple campaigns');
check($summary['rows'][6]['status']==='identity' && $summary['rows'][8]['status']==='identity','Conflicting or missing contact identity is not qualified');
check(!isset($summary['rows'][0]['email']) && !isset($summary['rows'][0]['phone']),'Report contains no contact identifiers');

$dir=sys_get_temp_dir().'/harmat-crm-attribution-'.bin2hex(random_bytes(8));
mkdir($dir);
$archive=$dir.'/harmat22.hu-Nov-2026.gz';
try {
    $fp=gzopen($archive,'wb');
    foreach (array(array(10,'Example_GCLID_01','24449147750',301),array(12,'Example_GCLID_02','24449147750',200),
        array(10,'Example_GCLID_03','24449147750',404),array(10,'Example_GCLID_04','24449147750',200),array(10,'Example_GCLID_04','24415124625',200)) as [$day,$token,$campaign,$status]) {
        gzwrite($fp,"127.0.0.1 - - [$day/Nov/2026:10:00:00 +0100] \"GET /lakaskereso/?gclid=$token&gad_campaignid=$campaign HTTP/1.1\" $status 1\n");
    }
    gzclose($fp);
    $tokens=array();
    for($i=1;$i<=4;$i++) $tokens['Example_GCLID_0'.$i]=array($i=>'2026-11-11 11:00:00');
    $matches=$c::log_campaigns($tokens,$dir,$c::date('2026-11-09'),$c::date('2026-11-22'));
    check($matches[1]===array(24449147750) && !isset($matches[2]) && !isset($matches[3]),'Prior first-party redirect allowed, later/failed requests not used');
    check($c::classify(array(),$matches[4])[1]==='conflict','Conflicting campaign IDs remain unknown');
} finally { if (is_file($archive)) unlink($archive); rmdir($dir); }
echo "PASS: CRM source classification, deduplication, and bounded archive join\n";
