<?php
namespace HarmatHomeFamilyFixture;
use DOMDocument;
use DOMElement;
use Throwable;
use RuntimeException;

if (PHP_SAPI!=='cli') exit(1);
$candidate=$argv[1] ?? '';
if (!is_file($candidate)) throw new RuntimeException('Candidate required');
\define('ABSPATH',__DIR__.'/');
$checks=0; $state=array();
function check($ok,$name){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL '.$name);}
function reset_state(){global $state;$state=array('flags'=>array(),'page'=>true,'front'=>true,'main'=>true,'id'=>164,'status'=>'publish','slug'=>'fooldal','raw'=>str_repeat('R',42575),'password'=>'','template'=>'','bad_asset'=>false,'hidden'=>false,'unit_status'=>'current','unit_type'=>'property');$_SERVER['REQUEST_METHOD']='GET';$_GET=array();}
function defined($name){global $state;return isset($state['flags'][$name]) || \defined($name);}
function constant($name){global $state;return $state['flags'][$name] ?? \constant($name);}
function function_exists($name){return \function_exists(__NAMESPACE__.'\\'.$name) || \function_exists($name);}
function hash($algo,$value){if($value===str_repeat('R',42575))return 'dabadc35c52bd029d1097178a52eebd08a5a5ccca1bb5a9d53c12797a473dedf';return \hash($algo,$value);}
function hash_file($algo,$path){global $state;if($state['bad_asset'] && substr($path,-4)==='.css')return str_repeat('0',64);return \hash_file($algo,$path);}
function is_admin(){return false;}function wp_doing_ajax(){return false;}function wp_doing_cron(){return false;}
function is_feed(){return false;}function is_preview(){return false;}function is_404(){return false;}
function is_search(){return false;}function is_embed(){return false;}function is_trackback(){return false;}
function wp_is_json_request(){return false;}
function is_main_query(){global $state;return $state['main'];}
function is_front_page(){global $state;return $state['front'];}
function is_page($id){global $state;return $state['page'] && $id===$state['id'];}
function get_queried_object_id(){global $state;return $state['id'];}
function get_option($key){return $key==='page_on_front'?164:'page';}
function get_page_template_slug($id){global $state;return $state['template'];}
function get_post_status($id){global $state;return $state['status'];}
function get_post_field($field,$id,$raw){global $state;return array('post_content'=>$state['raw'],'post_name'=>$state['slug'],'post_password'=>$state['password'])[$field];}
function get_post($id){global $state;return (object)array('post_type'=>$state['unit_type'],'post_status'=>'publish','post_password'=>'');}
function get_post_meta($id,$key,$single){return '';}
function harmat_sai_property_summary_data($id){global $state;$codes=array(4317=>'A1-1-L6',5212=>'A2-4-L5',4311=>'A1-1-L5');return array('title'=>$codes[$id],'rooms'=>2,'sales_area'=>47.83,'price'=>62236650,'hide_price'=>$state['hidden'],'status'=>$state['unit_status']);}
function get_permalink($id){$d=harmat_sai_property_summary_data($id);return 'https://harmat22.hu/property/'.strtolower($d['title']).'/';}
function hm_migrated_property_floorplan_image_from_uploads($code,$pdf){return 'https://harmat22.hu/wp-content/uploads/2026/05/'.$code.'-cn-floorplan-display.jpg';}
function content_url($path){return 'https://harmat22.hu/wp-content'.$path;}
function esc_url($url){return htmlspecialchars($url,ENT_QUOTES,'UTF-8');}
function esc_html($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function esc_attr($text){return esc_html($text);}
function number_format_i18n($n,$decimals=0){return number_format((float)$n,$decimals,',',' ');}
function add_action(...$args){}
function fixture(){return '<!doctype html><html lang="hu"><head><title>Native title</title><meta name="description" content="Native description"></head><body><header id="masthead">Native header</header><h1 class="harmat-seo-h1">' . "\u{00da}j \u{00e9}p\u{00ed}t\u{00e9}s\u{0171} lak\u{00e1}sok Budapest X. ker\u{00fc}let\u{00e9}ben - Harmat Lak\u{00f3}park</h1>" . '<main id="main" class="site-main"><div class="elementor-164"><video id="harmat-native-home-video"></video><h2 id="harmat-home-featured-title">Native featured</h2></div></main><footer id="colophon">Native footer</footer><div id="h22-offer-modal"><form id="h22-offer-form"><input name="your-name"><select name="source"><option>Google keresés</option></select><input type="checkbox" required></form></div><button id="hm-cookie-settings-button">Cookie</button><div id="harmat-internal-language"><button data-hil-language="hu">HU</button><button data-hil-language="zh-Hans">ZH</button></div><script>window.nativeSales={keep:true};</script></body></html>';}
reset_state();
$source=file_get_contents($candidate);
$tokens=token_get_all($source,TOKEN_PARSE);$code='namespace '.__NAMESPACE__.'; use \\DOMDocument; use \\DOMElement; use \\Throwable;';
foreach($tokens as $token){if(!is_array($token)){$code.=$token;continue;}if($token[0]===T_OPEN_TAG)continue;if($token[0]===T_DIR){$code.=var_export(dirname($candidate),true);}else{$code.=$token[1];}}
eval($code);
check(harmat_home_family_context(),'normal context');
check((bool)harmat_home_family_assets(),'real asset pins');
$input=fixture();$output=harmat_home_family_output($input);
check($output!==$input,'positive replacement');
check(substr_count($output,'id="harmat-home-family"')===1,'one root');
check(substr_count($output,'<h1 ')===1,'one h1');
foreach(array('header','footer','form') as $tag){preg_match('~<'.$tag.'\b[\s\S]*?</'.$tag.'>~',$input,$a);preg_match('~<'.$tag.'\b[\s\S]*?</'.$tag.'>~',$output,$b);check($a[0]===$b[0],'native '.$tag.' byte exact');}
check(strpos($output,'window.nativeSales={keep:true};')!==false,'native script intact');
check(harmat_home_family_output($output)===$output,'idempotent');
foreach(array('POST','PUT','DELETE') as $method){reset_state();$_SERVER['REQUEST_METHOD']=$method;check(harmat_home_family_output($input)===$input,$method.' unchanged');}
foreach(array('WP_CLI','DOING_CRON','DOING_AJAX','REST_REQUEST','XMLRPC_REQUEST') as $flag){reset_state();$state['flags'][$flag]=true;check(harmat_home_family_output($input)===$input,$flag.' unchanged');}
foreach(array('id'=>1777,'front'=>false,'main'=>false,'status'=>'draft','slug'=>'other','raw'=>'drift','password'=>'secret','template'=>'custom.php','bad_asset'=>true,'unit_type'=>'private','unit_status'=>'unexpected') as $key=>$value){reset_state();$state[$key]=$value;check(harmat_home_family_output($input)===$input,$key.' fail-open');}
reset_state();$state['hidden']=true;$hidden=harmat_home_family_units();check($hidden['A1-1-L6']['price_huf']===null,'hidden price null');
foreach(array('reserved','sold') as $status){$state['unit_status']=$status;check(harmat_home_family_units()['A1-1-L6']['status']===$status,'live status '.$status);}
reset_state();
foreach(array(substr($input,0,-7),str_replace('id="h22-offer-form"','id="missing-form"',$input),str_replace('</body>','<form id="h22-offer-form"></form></body>',$input),str_replace('elementor-164','drift',$input),str_replace('<h1 ','<h2 ',$input)) as $broken){check(harmat_home_family_output($broken)===$broken,'malformed/drift native fail-open');}
echo json_encode(array('status'=>'PASS','checks'=>$checks,'scope'=>'SYNTHETIC_PURE_NO_NETWORK_NO_SUBMISSION'));
