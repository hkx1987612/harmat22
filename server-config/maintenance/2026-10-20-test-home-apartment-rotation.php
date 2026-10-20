<?php
namespace HarmatHomeRotationFixture;
if (PHP_SAPI!=='cli') exit(1);
\define('ABSPATH',__DIR__.'/');
$checks=0; $actions=array(); $context=true; $helpers=true; $exception=false; $query=null;
class WP_Post {
    public $ID; public $post_type='property'; public $post_status='publish'; public $post_password='';
    function __construct($id) { $this->ID=$id; }
}
function check($ok,$name) { global $checks; ++$checks; if (!$ok) throw new \RuntimeException('FAIL '.$name); }
function function_exists($name) { global $helpers; return $helpers && \function_exists(__NAMESPACE__.'\\'.$name); }
function harmat_home_assistant_inline_context() { global $context; return $context; }
function add_action(...$args) { global $actions; $actions[]=$args; }
function get_posts($args) { global $posts,$query; $query=$args; return $posts; }
function harmat_sai_property_summary_data($id) { global $rows,$exception; if ($exception) throw new \RuntimeException('private'); return $rows[$id] ?? null; }
function get_permalink($id) { global $rows; return $rows[$id]['url']; }
function get_post_meta($id,$key,$single) { return 'native-plan'; }
function hm_migrated_property_floorplan_image_from_uploads($code,$source) { global $rows; foreach ($rows as $row) if ($row['title']===$code) return $row['image']; return ''; }
function output() { ob_start(); harmat_har_footer(); return ob_get_clean(); }
function reset_rows() {
    global $posts,$rows;
    $codes=array('A1-1-L6','A2-4-L5','A1-1-L5','A3-F-L3','A4-F-L1','A3-2-L2','A1-F-L2','A2-F-L2','A3-4-L2','A4-1-L2','A2-2-L1','A4-2-L1');
    $posts=$rows=array();
    foreach ($codes as $i=>$code) {
        $id=$i+1; $posts[]=new WP_Post($id);
        $rows[$id]=array('title'=>$code,'rooms'=>2,'sales_area'=>55.25,'status'=>'current','hide_price'=>false,
            'price'=>75000000,'url'=>'https://harmat22.hu/property/'.strtolower($code).'/',
            'image'=>'https://harmat22.hu/wp-content/uploads/'.strtolower($code).'.jpg');
    }
}
$candidate=$argv[1] ?? dirname(__DIR__,2).'/wp-mu-plugins/zz-harmat-home-apartment-rotation.php';
$tokens=token_get_all(file_get_contents($candidate),TOKEN_PARSE); $code='namespace '.__NAMESPACE__.'; use \\Throwable;';
foreach ($tokens as $token) {
    if (is_array($token) && $token[0]===T_OPEN_TAG) continue;
    $code.=is_array($token) ? $token[1] : $token;
}
eval($code); reset_rows();
if (in_array('--render',$argv,true)) { echo output(); exit; }
check($actions===array(array('wp_footer','harmat_har_footer',1250)),'one late home footer hook');
$expected=harmat_har_inventory();
check(count($expected)===12,'twelve public units');
foreach ($expected as $i=>$row) {
    check($row['code']===$rows[$i+1]['title'],'exact public code');
    check(array_keys($row)===array('code','url','image','rooms','area','price'),'public allowlist only');
}
foreach (array('post_type'=>'property','post_status'=>'publish','has_password'=>false,'numberposts'=>501,
    'fields'=>'all','orderby'=>'title','order'=>'ASC','cache_results'=>true,'update_post_meta_cache'=>true,
    'update_post_term_cache'=>false,'suppress_filters'=>true) as $key=>$value) check($query[$key]===$value,'bounded query '.$key);
$posts=array_reverse($posts); check(array_slice(harmat_har_inventory(),0,6)===array_slice($expected,0,6),'featured order retained'); reset_rows();
foreach (array('sold','reserved','unknown') as $status) {
    $rows[7]['status']=$status; check(count(harmat_har_inventory())===11,'only current '.$status);
    $rows[1]['status']=$status; check(harmat_har_inventory()===array(),'missing featured fails safely'); reset_rows();
}
foreach (array('post_type'=>'post','post_status'=>'draft','post_password'=>'secret') as $key=>$value) {
    $posts[6]->$key=$value; check(count(harmat_har_inventory())===11,'nonpublic excluded '.$key); reset_rows();
}
foreach (array(null,'2',true,0,-1,INF,NAN,21) as $value) {
    $rows[7]['rooms']=$value; check(count(harmat_har_inventory())===11,'invalid room'); reset_rows();
}
foreach (array(null,'55',true,0,-1,INF,NAN,2001) as $value) {
    $rows[7]['sales_area']=$value; check(count(harmat_har_inventory())===11,'invalid area'); reset_rows();
}
foreach (array(null,'yes',0,1) as $value) {
    $rows[7]['hide_price']=$value; check(count(harmat_har_inventory())===11,'hide-price must be boolean'); reset_rows();
}
foreach (array(null,'75000000',true,1.5,1000000000001) as $value) {
    $rows[7]['price']=$value; check(count(harmat_har_inventory())===11,'invalid price'); reset_rows();
}
$rows[7]['hide_price']=true; check(harmat_har_inventory()[6]['price']===null,'hidden raw price never serialized'); reset_rows();
foreach (array(0,-1) as $value) { $rows[7]['price']=$value; check(harmat_har_inventory()[6]['price']===null,'nonpositive price hidden'); reset_rows(); }
foreach (array('A5-1-L2','<script>','A1-X-L2','A1-1-L2<script>','a1-1-l2','') as $value) {
    $rows[7]['title']=$value; check(count(harmat_har_inventory())===11,'unrecognized code'); reset_rows();
}
foreach (array('http://harmat22.hu/property/a1-f-l2/','https://other.test/property/a1-f-l2/',
    'https://user:pass@harmat22.hu/property/a1-f-l2/','https://harmat22.hu:443/property/a1-f-l2/',
    'https://harmat22.hu/property/a1-f-l2/?x=1','https://harmat22.hu/property/a1-f-l2/#x',
    'javascript:alert(1)','https://harmat22.hu/wp-admin/','https://harmat22.hu/property/ bad/') as $value) {
    $rows[7]['url']=$value; check(count(harmat_har_inventory())===11,'unsafe property URL'); reset_rows();
}
foreach (array('http://harmat22.hu/wp-content/a.jpg','https://other.test/wp-content/a.jpg','https://harmat22.hu/wp-admin/a.jpg') as $value) {
    $rows[7]['image']=$value; check(count(harmat_har_inventory())===11,'unsafe image URL'); reset_rows();
}
$posts[]=clone $posts[6]; check(harmat_har_inventory()===array(),'duplicate code fails'); reset_rows();
$posts[]=new \stdClass(); check(harmat_har_inventory()===array(),'foreign query row fails'); reset_rows();
$posts=array_fill(0,501,$posts[0]); check(harmat_har_inventory()===array(),'unbounded result fails'); reset_rows();
$posts=null; check(harmat_har_inventory()===array(),'query failure'); reset_rows();
$posts=array_slice($posts,0,6); check(harmat_har_inventory()===array(),'no unnecessary controls for only six'); reset_rows();
$exception=true; check(harmat_har_inventory()===array(),'exception private/fallback'); $exception=false;
$helpers=false; check(harmat_har_inventory()===array() && output()==='','missing helpers safe'); $helpers=true;
$context=false; check(output()==='','non-home context no emission'); $context=true;
$html=output();
foreach (array('data','style','script') as $type) check(substr_count($html,'id="harmat-home-rotation-'.$type.'"')===1,'single resource '.$type);
check(output()==='','idempotent footer');
check(!preg_match('/<(?:form|input|iframe|link)\b|wp-json|wp_mail|nonce/i',$html),'no form/service/credentials');
preg_match('~<script type="application/json" id="harmat-home-rotation-data">(.*?)</script>~s',$html,$match);
check(json_decode($match[1],true)===$expected,'exact safe public data');
echo json_encode(array('status'=>'PASS','checks'=>$checks,'scope'=>'PURE_PUBLIC_DATA_NO_NETWORK_NO_SUBMISSION'));
