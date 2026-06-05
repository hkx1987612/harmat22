<?php
/**
 * Plugin Name: Harmat Google Site Verification
 * Description: Adds Google Search Console verification meta tag to public pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    echo "\n<meta name=\"google-site-verification\" content=\"xXrqAjHvLsP0ICO5fs3mkn_D3Yq7lRLxrojPJGf7drQ\" />\n";
}, 1);
