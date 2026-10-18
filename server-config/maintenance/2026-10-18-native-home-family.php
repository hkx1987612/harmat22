<?php
// Private CLI full-theme preflight; no form, REST, mail or cron dispatch.
if (PHP_SAPI!=='cli' || defined('ABSPATH') || defined('WP_CLI')) exit(1);
$candidate=$argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/home-family-[a-f0-9]{32}/zz-harmat-home-family\.php\z~D',$candidate)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate)!==$candidate
    || (fileperms(dirname($candidate)) & 0077)!==0 || (fileperms($candidate) & 0077)!==0) exit(2);
umask(0077);
register_shutdown_function(static function() use ($candidate) {
    $error=error_get_last();
    if ($error && in_array($error['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR),true)) {
        $handle=fopen(dirname($candidate).'/native-error.json','x+b');
        if ($handle) {fwrite($handle,json_encode($error));fclose($handle);}
    }
});
define('DISABLE_WP_CRON',true);
define('DONOTCACHEPAGE',true);
define('WP_USE_THEMES',true);
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='harmat22.hu';
$_SERVER['SERVER_NAME']='harmat22.hu';
$_SERVER['REQUEST_URI']='/';
$_SERVER['HTTPS']='on';
$_SERVER['SERVER_PORT']='443';
$_SERVER['HTTP_ACCEPT']='text/html';
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['PHP_SELF']='/index.php';
require '/home/harmath2/public_html/wp-load.php';
wp();
if (function_exists('harmat_home_family_output')) throw new RuntimeException('Candidate already active');
require $candidate;
remove_action('template_redirect','harmat_home_family_buffer',-4000);
if (!harmat_home_family_context() || !harmat_home_family_assets()) throw new RuntimeException('Native home context/assets failed');
$base=ob_get_level();
ob_start();
do_action('template_redirect');
$template=get_front_page_template();
if (!$template) $template=get_page_template();
$template=apply_filters('template_include',$template);
if (!is_string($template) || !is_file($template)) throw new RuntimeException('Native template missing');
include $template;
while (ob_get_level()>$base+1) ob_end_flush();
$original=ob_get_clean();
$changed=harmat_home_family_output($original);
if ($changed===$original || substr_count($changed,'id="harmat-home-family"')!==1
    || !harmat_home_family_document($changed)) {
    $harmat_home_family_units=harmat_home_family_units();
    $body=include dirname($candidate).'/harmat-home-family/body.php';
    $info=array('context'=>harmat_home_family_context(),'assets'=>(bool)harmat_home_family_assets(),
        'document'=>(bool)harmat_home_family_document($original),'units'=>(bool)$harmat_home_family_units,
        'changed'=>$changed!==$original,'main_open'=>substr_count($original,'<main id="main" class="site-main">'),
        'main_close'=>substr_count($original,'</main>'),'head_close'=>substr_count($original,'</head>'),
        'status'=>http_response_code(),'h1'=>preg_match_all('~<h1\b[\s\S]*?</h1>~i',$original,$h1)?$h1[0]:array(),
        'body_type'=>gettype($body),'body_root'=>is_string($body)?substr_count($body,'id="harmat-home-family"'):0);
    file_put_contents(dirname($candidate).'/home-failure.json',json_encode($info));
    file_put_contents(dirname($candidate).'/home-failed-baseline.html',$original);
    throw new RuntimeException('Native home replacement failed');
}
$oldmain=substr($original,strpos($original,'<main '),strpos($original,'</main>')+7-strpos($original,'<main '));
$newmain=substr($changed,strpos($changed,'<main '),strpos($changed,'</main>')+7-strpos($changed,'<main '));
$reversed=str_replace($newmain,$oldmain,$changed);
$reversed=preg_replace('~<link rel="stylesheet" href="https://harmat22\.hu/wp-content/mu-plugins/harmat-home-family/[a-z.-]+\?ver=[a-f0-9]{16}">|<script defer src="https://harmat22\.hu/wp-content/mu-plugins/harmat-home-family/[a-z.-]+\?ver=[a-f0-9]{16}"></script>~','',$reversed);
$reversed=preg_replace('~<p class="harmat-seo-h1">([\s\S]*?)</p>~','<h1 class="harmat-seo-h1">$1</h1>',$reversed,1);
if ($reversed!==$original) throw new RuntimeException('Native shell was changed outside declared home edits');
foreach (array('home-baseline.html'=>$original,'native-render.html'=>$changed) as $name=>$bytes) {
    $file=fopen(dirname($candidate).'/'.$name,'x+b');
    if (!$file) throw new RuntimeException('Private proof exists');
    fwrite($file,$bytes); fclose($file);
}
while (ob_get_level()>0) ob_end_clean();
echo json_encode(array('native'=>true,'original_bytes'=>strlen($original),'new_bytes'=>strlen($changed),
    'outside_main_preserved'=>true,'template'=>basename($template),'sha256'=>hash('sha256',$changed)));
