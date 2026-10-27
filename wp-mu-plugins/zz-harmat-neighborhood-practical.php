<?php
/**
 * Plugin Name: Harmat Neighborhood Practical
 * Description: Scoped, reversible neighborhood presentation; native sales shell retained.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function harmat_np_context() {
    foreach (array('WP_CLI','DOING_CRON','DOING_AJAX','REST_REQUEST','XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET','HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview()
        || is_404() || is_search() || is_embed() || is_trackback() || !is_main_query()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || isset($_GET['elementor-preview']) || !is_page(3959) || get_queried_object_id() !== 3959
        || get_post_status(3959) !== 'publish' || get_page_template_slug(3959) !== ''
        || get_post_field('post_name',3959,'raw') !== 'harmat-lakopark-kornyeke'
        || get_post_field('post_password',3959,'raw') !== ''
        || !function_exists('harmat_perf_neighborhood_page_markup')) return false;
    $raw = get_post_field('post_content',3959,'raw');
    return is_string($raw) && strlen($raw) === 5041
        && hash('sha256',$raw) === 'd6428ecfad17c8988ead9a88c132d9870af2c94ad4e9030d181074e5d85abd5d';
}

function harmat_np_assets() {
    static $checked;
    if (isset($checked)) return $checked;
    $checked = false;
    $dir = __DIR__.'/harmat-neighborhood-practical';
    $manifest = $dir.'/manifest.json';
    if (is_link($dir) || !is_file($manifest) || is_link($manifest)
        || hash_file('sha256',$manifest) !== '0b6ad48e27156ebc2f2621d7d47ea8455e601a6736f9391f3c32e4d1c90827d5') return false;
    $pins = json_decode(file_get_contents($manifest),true);
    if (!is_array($pins) || array_keys($pins) !== array('body.html','neighborhood.css','neighborhood.js','lucide.js','LICENSE-lucide.txt')) return false;
    foreach ($pins as $name=>$pin) {
        if (!is_string($pin) || !preg_match('/\A[a-f0-9]{64}\z/D',$pin)
            || !is_file($dir.'/'.$name) || is_link($dir.'/'.$name)
            || hash_file('sha256',$dir.'/'.$name) !== $pin) return false;
    }
    $checked = $pins;
    return $checked;
}

function harmat_np_content($content) {
    if (!harmat_np_context() || !in_the_loop() || !harmat_np_assets()) return $content;
    $body = file_get_contents(__DIR__.'/harmat-neighborhood-practical/body.html');
    if (!is_string($body) || substr_count($body,'id="harmat-neighborhood-practical"') !== 1
        || substr_count($body,'class="place-row"') !== 17) return $content;
    return $body;
}

function harmat_np_enqueue() {
    if (!harmat_np_context() || !($pins = harmat_np_assets())) return;
    // Suppress only this route's old viewer; its source and all other pages stay intact.
    foreach (array('harmat22-pannellum','harmat22-map-redesign') as $handle) {
        wp_dequeue_style($handle);
        wp_dequeue_script($handle);
    }
    $base = content_url('/mu-plugins/harmat-neighborhood-practical/');
    wp_enqueue_style('harmat-neighborhood-practical', $base.'neighborhood.css',array(),substr($pins['neighborhood.css'],0,16));
    wp_enqueue_script('harmat-neighborhood-icons',$base.'lucide.js',array(),substr($pins['lucide.js'],0,16),true);
    wp_enqueue_script('harmat-neighborhood-pannellum',content_url('/mu-plugins/harmat-home-family/pannellum.js'),array(),'a28b2f7b339fd0a6',true);
    wp_enqueue_script('harmat-neighborhood-practical',$base.'neighborhood.js',array('harmat-neighborhood-icons','harmat-neighborhood-pannellum'),substr($pins['neighborhood.js'],0,16),true);
}

function harmat_np_activate() {
    if (!harmat_np_context() || !harmat_np_assets()) return;
    remove_action('wp_head','harmat_perf_neighborhood_page_styles',86);
    // Old content filter runs first and remains the fallback if a guard fails.
    add_filter('the_content','harmat_np_content',1005);
    add_action('wp_enqueue_scripts','harmat_np_enqueue',100);
}
add_action('wp','harmat_np_activate',99);
