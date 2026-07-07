<?php
/**
 * Plugin Name: Harmat22 Map Redesign
 * Plugin URI: https://harmat22.hu
 * Description: Replaces the neighborhood map block with an interactive Harmat22 presentation module.
 * Version: 2.2
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: harmat22-map-redesign
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Harmat22_Map_Redesign {
    const VERSION = '2.2';

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }
        if (is_front_page() || is_home()) {
            return;
        }

        wp_enqueue_style(
            'harmat22-pannellum',
            'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css',
            array(),
            '2.5.6'
        );

        wp_enqueue_script(
            'harmat22-pannellum',
            'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js',
            array(),
            '2.5.6',
            true
        );

        wp_enqueue_style(
            'harmat22-map-redesign',
            plugin_dir_url(__FILE__) . 'assets/map-redesign.css',
            array('harmat22-pannellum'),
            self::VERSION
        );

        wp_register_script(
            'harmat22-map-redesign',
            plugin_dir_url(__FILE__) . 'assets/map-redesign.js',
            array('harmat22-pannellum'),
            self::VERSION,
            true
        );

        wp_add_inline_script(
            'harmat22-map-redesign',
            'window.Harmat22InteractiveConfig = ' . wp_json_encode(array(
                'assetBase' => plugin_dir_url(__FILE__) . 'assets/harmat-3d/',
                'address' => '1105 Budapest, Harmat utca 22.',
            )) . ';',
            'before'
        );

        wp_enqueue_script('harmat22-map-redesign');
    }
}

new Harmat22_Map_Redesign();
