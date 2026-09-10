<?php
define('ABSPATH', __DIR__);
define('WP_CONTENT_DIR', dirname(__DIR__, 2) . '/outputs/home-native-video/content');
$disabled = in_array('--disabled', $argv, true);
define('HARMAT_HOME_NATIVE_VIDEO_DISABLED', $disabled);
$dir = WP_CONTENT_DIR . '/uploads/harmat-video';
if (!is_dir($dir)) mkdir($dir, 0700, true);
$media = $dir . '/harmat-home-1080p-v2.mp4';
if (!is_file($media)) link(dirname(__DIR__, 2) . '/outputs/home-native-video/harmat-home-1080p-v2.mp4', $media);
$hooks = array();
$front = true;
function add_action($name, $callback, $priority = 10) { global $hooks; $hooks[$name][$priority][] = $callback; }
function add_filter($name, $callback, $priority = 10) { add_action($name, $callback, $priority); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wp_is_json_request() { return false; }
function is_front_page() { global $front; return $front; }
function is_page($slug) { return false; }
function content_url($path) { return 'https://harmat22.hu/wp-content' . $path; }
function home_url($path) { return 'https://harmat22.hu' . $path; }
function set_url_scheme($url, $scheme) { return preg_replace('~^https?~', $scheme, $url); }
function esc_url($text) { return htmlspecialchars($text, ENT_QUOTES); }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
function check($condition, $message) { if (!$condition) throw new Exception($message); }
function capture($name, $priority) {
    global $hooks;
    ob_start();
    foreach ($hooks[$name][$priority] as $callback) $callback();
    return ob_get_clean();
}
require dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-home-youtube-guard.php';
check(harmat_bw_native_video_available() === !$disabled, 'Native availability guard failed');
$html = '<sr7-module id="SR7_1_1"></sr7-module><script>"bg":{"video":{"src":"'
    . content_url(HARMAT_BW_ORIGIN_VIDEO) . '","autoPlay":true,"loop":true,"preload":"auto"}},"tl"</script>';
$filtered = harmat_bw_filter_homepage_html($html);
check(!str_contains($filtered, 'yulu-garden-source-compressed-60m.mp4'), 'Retired video leaked');
check($filtered === harmat_bw_filter_homepage_html($filtered), 'HTML filter not idempotent');
check(str_contains($filtered, 'id="harmat-native-home-video"') === !$disabled, 'Wrong player');
$runtime = capture('wp_footer', 99);
check(str_contains($runtime, 'harmat-native-home-runtime') === !$disabled, 'Wrong runtime');
check(str_contains($runtime, 'youtube.com/iframe_api') === $disabled, 'Unwanted YouTube API');
$schema = capture('wp_head', 41);
preg_match('~<script[^>]*>(.*?)</script>~s', $schema, $matches);
$data = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
check(isset($data['contentUrl']) === !$disabled, 'Wrong Schema content URL');
check(isset($data['embedUrl']) === $disabled, 'Wrong Schema embed URL');
check($data['duration'] === 'PT1M30S', 'Duration changed');
check(str_contains(capture('wp_footer', 100), 'harmat-youtube-3d-runtime'), 'On-demand 3D changed');
$front = false;
check(capture('wp_footer', 99) === '', 'Runtime outside homepage');
check(capture('wp_head', 41) === '', 'Schema outside homepage');
echo 'PASS: 12 homepage scope, idempotence, runtime, schema and fallback checks; disabled=' . (int)$disabled . "\n";
