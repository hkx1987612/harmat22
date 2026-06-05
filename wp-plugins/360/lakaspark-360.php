<?php
/*
Plugin Name: Lakópark 360 Viewer
Description: Interaktív 360-as lakásválasztó modul JSON hitboxokkal.
Version: 1.7
Author: 21stCenturyWebsites
*/

if (!defined('ABSPATH')) exit;

add_shortcode('lakaspark_360', 'render_lakaspark_360_viewer');

function render_lakaspark_360_viewer($atts) {
    // 1. LÉPÉS: Shortcode attribútumok definiálása (üres alapértelmezésekkel, ahogy kérted)
    $atts = shortcode_atts(array(
        'scene'           => 'A1',
        'image_folder'    => '',
        'json_url'        => '',
        'toggle'          => 'on', // on vagy off
        'custom_links'    => '',   // Formátum: ID1|url1,ID2|url2
        'static_hitboxes' => ''    // Formátum: ID1|Címke1,ID2|Címke2
    ), $atts);

    $scene = sanitize_text_field($atts['scene']);
    $image_folder = sanitize_text_field($atts['image_folder']);
    $json_url = sanitize_text_field($atts['json_url']);
    $toggle_mode = sanitize_text_field($atts['toggle']);

    // Custom linkek feldolgozása
    $custom_links_arr = array();
    if (!empty($atts['custom_links'])) {
        $pairs = explode(',', $atts['custom_links']);
        foreach ($pairs as $pair) {
            $parts = explode('|', $pair);
            if (count($parts) == 2) {
                $custom_links_arr[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    // Statikus hitboxok feldolgozása
    $static_hitboxes_arr = array();
    if (!empty($atts['static_hitboxes'])) {
        $pairs = explode(',', $atts['static_hitboxes']);
        foreach ($pairs as $pair) {
            $parts = explode('|', $pair);
            if (count($parts) == 2) {
                $static_hitboxes_arr[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    wp_enqueue_style('lakaspark-360-css', plugin_dir_url(__FILE__) . 'viewer.css', array(), '6.0');
    wp_enqueue_script('lakaspark-360-js', plugin_dir_url(__FILE__) . 'viewer.js', array(), '6.0', true);

    $apartment_data = array();
    $filter_rooms = array();
    $filter_floorsRaw = array();
    $needs_apartments = ($toggle_mode !== 'off');
    
    // 2. LÉPÉS: Lakás adatok kigyűjtése
    if ($needs_apartments) {
        $args = array('post_type' => 'property', 'posts_per_page' => -1, 'post_status' => 'publish');
        $query = new WP_Query($args);
    }

    if ($needs_apartments && $query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $hitbox_id = get_the_title(); 
            
            if($hitbox_id) {
                $rooms = get_post_meta($post_id, 'property_rooms', true);
                
                // FÖLDSZINT LOGIKA JAVÍTÁSA
                $floor = get_post_meta($post_id, 'property_address_street_number', true);
                if (empty($floor)) {
                    $floor = get_post_meta($post_id, 'propeerty_address_street_number', true);
                }
                if (empty($floor) && preg_match('/-([Ff0-9]+)[-_]/', $hitbox_id, $matches)) {
                    $floor = $matches[1];
                }
                if (strtoupper($floor) === 'F' || strtoupper($floor) === 'FSZ') {
                    $floor = 'Fsz';
                }

                if($rooms) $filter_rooms[$rooms] = $rooms;
                if($floor) $filter_floorsRaw[$floor] = $floor;

                // KÉP LEKÉRÉSE
                $img_url = '';
                $epl_floorplan = get_post_meta($post_id, 'property_floorplan', true);
                if (!empty($epl_floorplan)) {
                    $img_url = $epl_floorplan; 
                }
                if (empty($img_url)) {
                    $attachments = get_attached_media('image', $post_id);
                    foreach ($attachments as $att) {
                        if (stripos(strtolower($att->post_title), 'alaprajz') !== false || stripos(strtolower($att->post_name), 'alaprajz') !== false) {
                            $img_url = wp_get_attachment_url($att->ID);
                            break;
                        }
                    }
                }
                if (empty($img_url)) {
                    $img_url = get_the_post_thumbnail_url($post_id, 'medium');
                }

                // EPL STÁTUSZ FORDÍTÓ
                $raw_status = get_post_meta($post_id, 'property_status', true);
                $raw_status = strtolower(trim($raw_status));
                $status_class = 'available'; 
                
                if ($raw_status === 'sold') {
                    $status_class = 'sold';
                } elseif (in_array($raw_status, ['under-offer', 'reserved', 'egyeztetes-alatt'])) {
                    $status_class = 'reserved';
                } elseif (in_array($raw_status, ['current', 'available', 'active', ''])) {
                    $status_class = 'available';
                }

                $apartment_data[$hitbox_id] = array(
                    'id'     => $post_id,
                    'name'   => $hitbox_id,
                    'status' => $status_class,
                    'price'  => get_post_meta($post_id, 'property_price', true),
                    'rooms'  => $rooms,
                    'floor'  => $floor,
                    'b_area' => get_post_meta($post_id, 'property_building_area', true),
                    'l_area' => get_post_meta($post_id, 'property_land_area', true),
                    'link'   => get_permalink(),
                    'image'  => $img_url 
                );
            }
        }
        wp_reset_postdata();
    }

    // 3. LÉPÉS: Rendezések (Földszint prioritással)
    uksort($apartment_data, function($a, $b) {
        $get_floor = function($id) {
            if (preg_match('/-([Ff0-9]+)[-_]/', $id, $m)) {
                $f = strtoupper($m[1]);
                return ($f === 'F' || $f === 'FSZ') ? 0 : (int)$f;
            }
            return 99;
        };
        $fa = $get_floor($a);
        $fb = $get_floor($b);
        if ($fa !== $fb) return $fa - $fb;
        return strnatcmp($a, $b);
    });

    $ordered_floors = array();
    foreach($filter_floorsRaw as $f) {
        $key = ($f === 'Fsz') ? 0 : (int)$f;
        $ordered_floors[$key] = $f;
    }
    ksort($ordered_floors);
    sort($filter_rooms);
    
    // 4. LÉPÉS: Adatok átadása a JS-nek (MINDEN új változóval)
    wp_localize_script('lakaspark-360-js', 'LakasparkData', array(
        'scene'          => $scene,
        'baseUrl'        => $image_folder,
        'jsonUrl'        => $json_url,
        'apartments'     => $apartment_data,
        'toggle'         => $toggle_mode,
        'customLinks'    => $custom_links_arr,
        'staticHitboxes' => $static_hitboxes_arr
    ));

    // 5. LÉPÉS: HTML Generálás
    ob_start();
    ?>
    <div class="lakaspark-app-container" data-toggle="<?php echo esc_attr($toggle_mode); ?>">
        
        <?php if ($toggle_mode !== 'off'): ?>
        <div class="lakaspark-filter-bar">
            <div class="filter-group">
                <span class="filter-label">Szobák:</span>
                <div class="modern-select">
                    <select id="filterRooms">
                        <option value="all">Összes</option>
                        <?php foreach($filter_rooms as $r): ?>
                            <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?> szoba</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-divider"></div>

            <div class="filter-group">
                <span class="filter-label">Emelet:</span>
                <div class="floor-buttons" id="filterFloors">
                    <button class="floor-btn active" data-val="all">Összes</button>
                    <?php foreach($ordered_floors as $f): ?>
                        <button class="floor-btn" data-val="<?php echo esc_attr($f); ?>">
                            <?php echo esc_html($f); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="filter-results-info">
                <span id="resultCount">Lakások betöltése...</span>
                <?php if ($toggle_mode !== 'off'): ?>
                <button class="list-btn-modern" id="topListToggleBtn" title="Lakáslista megnyitása/bezárása">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="lakaspark-main-layout list-closed" id="mainLayout">
            
            <div class="lakaspark-viewer-section">
                <div class="viewer-container" id="buildingViewer">
                    
                                        <?php
                    $poster_url = trailingslashit($image_folder) . 'bld-' . $scene . '-frame-01.webp';
                    ?>
                    <img id="lakasparkPoster" class="viewer-image viewer-poster" src="<?php echo esc_url($poster_url); ?>" alt="Harmat Lakópark épületnézet" fetchpriority="high" decoding="async"><button class="back-btn-modern" id="backBtn" title="Vissza az előző oldalra">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Vissza
                    </button>

                    <div id="lakasparkLoader" class="lakaspark-loader">
                        <div class="loader-content">
                            <div class="loader-text" id="loaderText">Betöltés...</div>
                            <div class="loader-bar-container">
                                <div class="loader-bar-fill" id="loaderBarFill"></div>
                            </div>
                            <div class="loader-percent" id="loaderPercent" aria-live="polite"></div>
                        </div>
                    </div>

                    <svg id="hitboxLayer" class="viewer-svg" viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMid slice"></svg>
                    <div id="viewerTooltip" class="viewer-tooltip"></div>
                    
                    <div class="rotation-controls">
                        <button id="rotateLeftBtn" class="rotate-btn" title="Forgatás balra">
                            <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        </button>
                        <button id="rotateRightBtn" class="rotate-btn" title="Forgatás jobbra">
                            <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                        </button>
                    </div>

                    <div class="compass-overlay">
                        <div class="compass-ticks" id="compassTicks"></div>
                        <div class="compass-labels" id="compassLabels">
                            <span style="left: 0%;">ÉNy</span>
                            <span style="left: 12.5%;">É</span>
                            <span style="left: 25%;">ÉK</span>
                            <span style="left: 37.5%;">K</span>
                            <span style="left: 50%;">DK</span>
                            <span style="left: 62.5%;">D</span>
                            <span style="left: 75%;">DNy</span>
                            <span style="left: 87.5%;">Ny</span>
                            <span style="left: 100%;">ÉNy</span>
                        </div>
                        <input type="range" id="compassSlider" min="1" max="72" value="1" class="compass-modern-slider">
                    </div>
                </div>
            </div>

            <?php if ($toggle_mode !== 'off'): ?>
            <button class="list-toggle-btn" id="listToggleBtn" title="Lista kinyitása/bezárása">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <?php endif; ?>

            <?php if ($toggle_mode !== 'off'): ?>
            <div class="lakaspark-list-section" id="apartmentList">
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
