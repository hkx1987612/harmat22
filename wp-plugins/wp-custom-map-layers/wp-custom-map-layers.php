<?php
/**
 * Plugin Name: WP Custom Map Layers & Hotspots
 * Description: Prémium SVG/Kép alapú térkép, precíziós koordináta kezelővel (Elementor kompatibilis).
 * Version: 2.3.0
 * Author: Senior WP Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_Custom_Map_Layers {

    public function __construct() {
        add_action( 'init', array( $this, 'register_map_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_map_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_map_meta_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // ÚJ: A frontend assetek regisztrálása globálisan
        add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        
        add_shortcode( 'custom_map_layer', array( $this, 'render_frontend_map' ) );
    }

    public function register_map_cpt() {
        register_post_type( 'custom_map_layer', array(
            'labels' => array('name' => 'Térképek', 'singular_name' => 'Térkép', 'menu_name' => 'Térképek (Réteges)'),
            'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-location-alt', 'supports' => array( 'title' )
        ));
    }

    public function add_map_meta_boxes() {
        add_meta_box( 'map_editor_box', 'Térkép Vizuális Szerkesztő & GIS Kalibráció', array( $this, 'render_map_editor' ), 'custom_map_layer', 'normal', 'high' );
    }

    public function render_map_editor( $post ) {
        wp_nonce_field( 'save_map_data_nonce', 'map_meta_nonce' );

        $base_image = get_post_meta( $post->ID, '_map_base_image', true );
        $overlay_image = get_post_meta( $post->ID, '_map_overlay_image', true );
        $overlay_data = get_post_meta( $post->ID, '_map_overlay_data', true );
        $map_data_json = get_post_meta( $post->ID, '_map_data_json', true );
        
        $gps_tl = get_post_meta( $post->ID, '_map_gps_tl', true );
        $gps_br = get_post_meta( $post->ID, '_map_gps_br', true );
        
        if( empty($map_data_json) ) $map_data_json = '[]';
        if( empty($overlay_data) ) $overlay_data = '{"x":0,"y":0,"w":30}';
        ?>
        <style>
            .map-admin-panel { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 15px; }
            .map-admin-row { margin-bottom: 15px; }
            .map-admin-row label { display: block; font-weight: bold; margin-bottom: 5px; }
            .gps-box { background: #e3f2fd; border: 1px solid #90caf9; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
            .overlay-controls { background: #f0f0f1; padding: 10px; border-left: 4px solid #0073aa; margin-top: 10px; display: none; }
        </style>

        <div class="map-admin-panel">
            <div class="map-admin-row">
                <label>1. Alaptérkép (Háttér)</label>
                <input type="hidden" name="map_base_image" id="map_base_image" value="<?php echo esc_attr( $base_image ); ?>">
                <button type="button" class="button upload-img-btn" data-target="map_base_image">Kép kiválasztása</button>
            </div>
            
            <div class="gps-box">
                <h4>🌍 Térkép GPS Kalibráció (Opcionális)</h4>
                <div style="display:flex; gap: 20px;">
                    <div style="flex:1;">
                        <label>Bal Felső sarok (Top-Left):</label>
                        <input type="text" name="map_gps_tl" id="map_gps_tl" value="<?php echo esc_attr( $gps_tl ); ?>" placeholder="Pl: 47.502213, 19.045612" style="width:100%;">
                    </div>
                    <div style="flex:1;">
                        <label>Jobb Alsó sarok (Bottom-Right):</label>
                        <input type="text" name="map_gps_br" id="map_gps_br" value="<?php echo esc_attr( $gps_br ); ?>" placeholder="Pl: 47.481234, 19.086543" style="width:100%;">
                    </div>
                </div>
            </div>

            <div class="map-admin-row">
                <label>2. Lakópark Kiemelés (Overlay)</label>
                <input type="hidden" name="map_overlay_image" id="map_overlay_image" value="<?php echo esc_attr( $overlay_image ); ?>">
                <input type="hidden" name="map_overlay_data" id="map_overlay_data" value="<?php echo esc_attr( $overlay_data ); ?>">
                <button type="button" class="button upload-img-btn" data-target="map_overlay_image">Kép kiválasztása</button>
                
                <div class="overlay-controls" id="overlay-manual-controls">
                    <strong>Kézi Finomhangolás (%)</strong>
                    <div style="display:flex; gap: 10px; margin-top: 5px;">
                        <div style="flex:1;"><label style="font-size:11px;">X pozíció (Balról)</label><input type="number" step="0.1" id="ov_x" value="" style="width:100%;"></div>
                        <div style="flex:1;"><label style="font-size:11px;">Y pozíció (Fentről)</label><input type="number" step="0.1" id="ov_y" value="" style="width:100%;"></div>
                        <div style="flex:1;"><label style="font-size:11px;">Szélesség</label><input type="number" step="0.1" id="ov_w" value="" style="width:100%;"></div>
                    </div>
                </div>
            </div>
            
            <hr>
            <h3>3. Vizuális Szerkesztő (Pontok listája)</h3>
            <div id="map-visual-editor-container" style="border: 2px solid #0073aa; padding: 10px; background: #f0f0f1;">Betöltés...</div>
            <input type="hidden" name="map_data_json" id="map_data_json" value="<?php echo esc_attr( $map_data_json ); ?>">
            
            <hr>
            <h4>Haladó: Adatok Importálása</h4>
            <textarea id="map_import_export_field" style="width: 100%; height: 60px; font-family: monospace;"></textarea>
            <button type="button" class="button button-secondary map-btn" id="import-json-btn">Adatok Importálása</button>
        </div>
        <?php
    }

    public function save_map_meta_data( $post_id ) {
        if ( ! isset( $_POST['map_meta_nonce'] ) || ! wp_verify_nonce( $_POST['map_meta_nonce'], 'save_map_data_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST['map_base_image'] ) ) update_post_meta( $post_id, '_map_base_image', esc_url_raw( $_POST['map_base_image'] ) );
        if ( isset( $_POST['map_overlay_image'] ) ) update_post_meta( $post_id, '_map_overlay_image', esc_url_raw( $_POST['map_overlay_image'] ) );
        if ( isset( $_POST['map_gps_tl'] ) ) update_post_meta( $post_id, '_map_gps_tl', sanitize_text_field( $_POST['map_gps_tl'] ) );
        if ( isset( $_POST['map_gps_br'] ) ) update_post_meta( $post_id, '_map_gps_br', sanitize_text_field( $_POST['map_gps_br'] ) );
        
        // JAVÍTOTT RÉSZ: A nyers (Raw) JSON mentése biztonságosan, ami nem bántja az ékezeteket!
        if ( isset( $_POST['map_overlay_data'] ) ) {
            $raw_overlay = wp_unslash( $_POST['map_overlay_data'] );
            // Csak ellenőrizzük, hogy érvényes JSON-e, de a NYERS szöveget mentjük!
            if ( is_array( json_decode( $raw_overlay, true ) ) ) {
                update_post_meta( $post_id, '_map_overlay_data', wp_slash( $raw_overlay ) );
            }
        }
        
        if ( isset( $_POST['map_data_json'] ) ) {
            $raw_json = wp_unslash( $_POST['map_data_json'] );
            // Csak ellenőrizzük, hogy érvényes JSON-e, de a NYERS szöveget mentjük!
            if ( is_array( json_decode( $raw_json, true ) ) ) {
                update_post_meta( $post_id, '_map_data_json', wp_slash( $raw_json ) );
            }
        }
    }

    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( ( 'post.php' == $hook || 'post-new.php' == $hook ) && 'custom_map_layer' == $post_type ) {
            // Natív WP Media Uploader engedélyezése
            wp_enqueue_media(); 
            
            // Saját admin szkriptünk betöltése verziókövetéssel a cache problémák ellen
            wp_enqueue_script( 
                'custom-map-admin-js', 
                plugin_dir_url( __FILE__ ) . 'admin-map.js', 
                array( 'jquery' ), // A WP Media API a jQuery-re épül
                '1.0.0', 
                true // A footerben töltjük be az oldalgyorsítás (DOM render) érdekében
            );
        }
    }

    // ÚJ: Különválasztottuk a fájlok regisztrálását
    public function register_frontend_assets() {
        // filemtime: Csak akkor üríti a cache-t a usereknél, ha te módosítod a fájlt a szerveren!
        $css_ver = filemtime( plugin_dir_path( __FILE__ ) . 'frontend-map.css' );
        $js_ver  = filemtime( plugin_dir_path( __FILE__ ) . 'frontend-map.js' );

        wp_register_style( 'custom-map-frontend-css', plugin_dir_url( __FILE__ ) . 'frontend-map.css', array(), $css_ver );
        wp_register_script( 'custom-map-frontend-js', plugin_dir_url( __FILE__ ) . 'frontend-map.js', array(), $js_ver, true );
    }

// A shortcode generáló
    public function render_frontend_map( $atts ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        if ( empty( $atts['id'] ) ) return '<p>Hiba: Nincs megadva térkép ID!</p>';

        $post_id = intval( $atts['id'] );
        $map_title = get_the_title( $post_id ); // Lekérjük a térkép címét (A Lakópark neve)
        
        $base_image = get_post_meta( $post_id, '_map_base_image', true );
        if ( empty( $base_image ) ) return '<p>Hiba: Nincs alaptérkép beállítva!</p>';

        $overlay_image = get_post_meta( $post_id, '_map_overlay_image', true );
        $overlay_data = json_decode( get_post_meta( $post_id, '_map_overlay_data', true ), true );
        $map_data = json_decode( get_post_meta( $post_id, '_map_data_json', true ), true );
        
        if ( ! is_array( $map_data ) ) $map_data = array();

        wp_enqueue_style( 'custom-map-frontend-css' );
        wp_enqueue_script( 'custom-map-frontend-js' );

        ob_start();
        ?>
        <div class="cml-wrapper" id="cml-map-<?php echo esc_attr( $post_id ); ?>">
            <div class="cml-canvas" id="cml-canvas-<?php echo esc_attr( $post_id ); ?>">
                <div class="cml-transform-layer">
                    <img src="<?php echo esc_url( $base_image ); ?>" class="cml-base-img" alt="Térkép">
                    
                    <?php /* ÚJ: Útvonalak visszatétele és alapból LÁTHATÓVÁ tétele (is-visible) */ ?>
                    <?php foreach ( $map_data as $index => $cat ) : ?>
                        <?php if ( ! empty( $cat['layerUrl'] ) ) : ?>
                            <img src="<?php echo esc_url( $cat['layerUrl'] ); ?>" class="cml-route-layer is-visible" id="cml-route-<?php echo esc_attr( $post_id . '-' . $index ); ?>" alt="Útvonal">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php /* ÚJ: Overlay (Lakópark) visszatétele és alapból LÁTHATÓVÁ tétele */ ?>
                    <?php if ( ! empty( $overlay_image ) && is_array( $overlay_data ) ) : ?>
                        <div class="cml-overlay-wrapper is-visible" id="cml-overlay-<?php echo esc_attr( $post_id ); ?>" style="left:<?php echo esc_attr($overlay_data['x']); ?>%; top:<?php echo esc_attr($overlay_data['y']); ?>%; width:<?php echo esc_attr($overlay_data['w']); ?>%;">
                            <img src="<?php echo esc_url( $overlay_image ); ?>" class="cml-overlay-img" alt="<?php echo esc_attr( $map_title ); ?>">
                            <div class="cml-tooltip"><?php echo esc_html( $map_title ); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php /* Markerek - alapból LÁTHATÓAK (is-visible) */ ?>
                    <?php foreach ( $map_data as $index => $cat ) : ?>
                        <div class="cml-marker-layer is-visible" id="cml-layer-<?php echo esc_attr( $post_id . '-' . $index ); ?>">
                            <?php if ( ! empty( $cat['points'] ) ) : ?>
                                <?php 
                                $size = 20; /* JAVÍTVA: Fix, kisebb és elegánsabb pötty méret (28-ról 20-ra) */ 
                                foreach ( $cat['points'] as $ptIdx => $point ) : 
                                ?>
                                    <div class="cml-marker" style="left:<?php echo esc_attr($point['x']); ?>%; top:<?php echo esc_attr($point['y']); ?>%; width:<?php echo esc_attr($size); ?>px; height:<?php echo esc_attr($size); ?>px;">
                                        <div class="cml-marker-circle" style="background-color:<?php echo esc_attr($cat['color']); ?>;">
                                            <?php if ( ! empty( $cat['iconUrl'] ) ) : ?>
                                                <img src="<?php echo esc_url( $cat['iconUrl'] ); ?>" alt="icon">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cml-tooltip"><?php echo esc_html( $point['label'] ); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cml-zoom-controls">
                    <button class="cml-z-btn" data-action="in">+</button>
                    <button class="cml-z-btn" data-action="out">−</button>
                    <button class="cml-z-btn" data-action="reset">⟲</button>
                </div>
            </div>

            <div class="cml-ui-panel">
                <h2 class="cml-title">FEDEZZE FEL A<br>KÖRNYÉKET</h2>
                <p class="cml-subtitle">Válassza ki a szolgáltatást és nézze meg hol van Önhöz a legközelebbi.</p>
                
                <div class="cml-cat-grid">
                    <?php if ( ! empty( $overlay_image ) ) : ?>
                        <div class="cml-cat-item is-active cml-cat-overlay" data-target="overlay-<?php echo esc_attr( $post_id ); ?>">
                            <span class="cml-cat-bullet" style="background:#1a2a30;"></span>
                            <span class="cml-cat-name"><?php echo esc_html( $map_title ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php foreach ( $map_data as $index => $cat ) : ?>
                        <?php /* ÚJ: Gombok alapból AKTÍVAK (is-active) */ ?>
                        <div class="cml-cat-item is-active" data-target="<?php echo esc_attr( $post_id . '-' . $index ); ?>">
                            <span class="cml-cat-bullet"></span>
                            <span class="cml-cat-name"><?php echo esc_html( $cat['name'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
new WP_Custom_Map_Layers();