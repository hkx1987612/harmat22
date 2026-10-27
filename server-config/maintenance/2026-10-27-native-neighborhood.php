<?php
// Private full-theme preflight, no HTTP endpoint or customer submission.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) exit(1);
$candidate = $argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/neighborhood-practical-[a-f0-9]{32}/zz-harmat-neighborhood-practical\.php\z~D',$candidate)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
    || (fileperms(dirname($candidate)) & 0077) || (fileperms($candidate) & 0077)) exit(2);
umask(0077);
define('DISABLE_WP_CRON',true);
define('DONOTCACHEPAGE',true);
define('WP_USE_THEMES',true);
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']=$_SERVER['SERVER_NAME']='harmat22.hu';
$_SERVER['REQUEST_URI']='/harmat-lakopark-kornyeke/';
$_SERVER['HTTPS']='on';
$_SERVER['SERVER_PORT']='443';
$_SERVER['HTTP_ACCEPT']='text/html';
$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/index.php';
require '/home/harmath2/public_html/wp-load.php';
wp();
require $candidate;
if (!harmat_np_context() || !harmat_np_assets()) throw new RuntimeException('Native candidate identity/assets fail');
harmat_np_activate();
$base=ob_get_level(); ob_start();
do_action('template_redirect');
$template=apply_filters('template_include',get_page_template());
if (!is_string($template) || !is_file($template)) throw new RuntimeException('Native template missing');
include $template;
while (ob_get_level()>$base+1) ob_end_flush();
$html=ob_get_clean();
if (substr_count($html,'id="harmat-neighborhood-practical"')!==1
    || substr_count($html,'class="place-row"')!==17
    || strpos($html,'id="h22-offer-form"')===false
    || strpos($html,'id="h22-offer-modal"')===false
    || strpos($html,'id="harmat-internal-language"')===false
    || strpos($html,'harmat22-map-redesign-js')!==false
    || strpos($html,'harmat-neighborhood-page-20260531')!==false)
    throw new RuntimeException('Native content/shell/isolation failed');
$target=dirname($candidate).'/native-render.html';
$file=fopen($target,'x+b');
if (!$file) throw new RuntimeException('Exclusive native proof required');
if (fwrite($file,$html)!==strlen($html) || !fflush($file)) throw new RuntimeException('Proof write failed');
fclose($file);
echo json_encode(array('status'=>'PASS','native_theme'=>true,'places'=>17,'forms_retained'=>true,'submission'=>false));
