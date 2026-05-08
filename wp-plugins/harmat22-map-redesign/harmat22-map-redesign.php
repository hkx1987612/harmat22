<?php
/**
 * Plugin Name: Harmat22 Map Redesign
 * Plugin URI: https://harmat22.hu
 * Description: Refines the homepage neighborhood map module UI with cleaner layout, category chips, and focused interaction.
 * Version: 0.1.0
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: harmat22-map-redesign
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Harmat22_Map_Redesign {
    const VERSION = '0.1.0';

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        wp_register_style(
            'harmat22-map-redesign',
            plugin_dir_url(__FILE__) . 'assets/map-redesign.css',
            array(),
            self::VERSION
        );

        wp_register_script(
            'harmat22-map-redesign',
            plugin_dir_url(__FILE__) . 'assets/map-redesign.js',
            array(),
            self::VERSION,
            true
        );

        wp_enqueue_style('harmat22-map-redesign');
        wp_enqueue_script('harmat22-map-redesign');
    }
}

new Harmat22_Map_Redesign();
