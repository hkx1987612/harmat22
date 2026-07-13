<?php
/**
 * Plugin Name: Harmat Lakáskereső Redesign
 * Description: Clean standalone apartment search page for /lakaskereso/ using Harmat Sales Manager data.
 * Version: 1.1.9
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_lakas_redesign_is_page() {
    return !is_admin() && is_page('lakaskereso');
}

function harmat_lakas_redesign_should_load_assets() {
    return harmat_lakas_redesign_is_page() || (!is_admin() && is_singular('property'));
}

add_filter('body_class', function ($classes) {
    if (harmat_lakas_redesign_is_page()) {
        $classes[] = 'harmat-lakas-redesign-page';
    }
    return $classes;
});

add_filter('the_content', function ($content) {
    if (!harmat_lakas_redesign_is_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return harmat_lakas_redesign_render();
}, 20);

add_action('wp_enqueue_scripts', function () {
    if (!harmat_lakas_redesign_should_load_assets()) {
        return;
    }

    wp_register_style('harmat-lakas-redesign', false, array(), '1.1.9');
    wp_enqueue_style('harmat-lakas-redesign');
    wp_add_inline_style('harmat-lakas-redesign', harmat_lakas_redesign_css());

    if (harmat_lakas_redesign_is_page()) {
        wp_register_script('harmat-lakas-redesign', false, array(), '1.1.9', true);
        wp_enqueue_script('harmat-lakas-redesign');
        wp_add_inline_script('harmat-lakas-redesign', harmat_lakas_redesign_js());
    }
}, 90);

add_action('wp_footer', function () {
    if (is_admin() || !is_singular('property')) {
        return;
    }

    echo harmat_lakas_redesign_render_related();
}, 20);


function harmat_lakas_redesign_cache_key() {
    return 'harmat_lakas_redesign_markup_v11';
}

function harmat_lakas_redesign_clear_cache() {
    delete_transient(harmat_lakas_redesign_cache_key());
}

function harmat_lakas_redesign_items() {
    global $harmat_sales_manager;
    if (!$harmat_sales_manager || !method_exists($harmat_sales_manager, 'frontend_sales_data')) {
        return array();
    }

    $items = $harmat_sales_manager->frontend_sales_data();
    return is_array($items) ? $items : array();
}

function harmat_lakas_redesign_area($value) {
    $number = (float) $value;
    if (!$number) {
        return '-';
    }
    return number_format_i18n($number, 2) . ' m²';
}

function harmat_lakas_redesign_money($value, $hide = false) {
    $number = (int) $value;
    if ($hide || !$number) {
        return 'Ár egyeztetés alapján';
    }
    return number_format_i18n($number, 0) . ' Ft';
}

function harmat_lakas_redesign_public_sqm_price($item) {
    if (!empty($item['hidePrice'])) {
        return 0;
    }

    $sqm_price = isset($item['sqmPrice']) ? (int) $item['sqmPrice'] : 0;
    if ($sqm_price > 0) {
        return $sqm_price;
    }

    $price = isset($item['price']) ? (int) $item['price'] : 0;
    $area = isset($item['salesArea']) ? (float) $item['salesArea'] : 0.0;
    return ($price > 0 && $area > 0) ? (int) round($price / $area) : 0;
}

function harmat_lakas_redesign_sqm_money($item, $hide = false) {
    $sqm_price = $hide ? 0 : harmat_lakas_redesign_public_sqm_price($item);
    if (!$sqm_price) {
        return 'Érdeklődjön árainkról';
    }
    return number_format_i18n($sqm_price, 0) . ' Ft / m²';
}

function harmat_lakas_redesign_public_sqm_range($items) {
    $values = array();
    foreach ($items as $item) {
        $sqm_price = harmat_lakas_redesign_public_sqm_price($item);
        if ($sqm_price > 0) {
            $values[] = $sqm_price;
        }
    }

    if (!$values) {
        return array('min' => 0, 'max' => 0, 'step' => 50000);
    }

    $step = 50000;
    $min = (int) floor(min($values) / $step) * $step;
    $max = (int) ceil(max($values) / $step) * $step;
    if ($max <= $min) {
        $max = $min + $step;
    }

    return array('min' => $min, 'max' => $max, 'step' => $step);
}

function harmat_lakas_redesign_image($item, $index) {
    $post_id = isset($item['id']) ? (int) $item['id'] : 0;
    if ($post_id) {
        $thumb = get_the_post_thumbnail_url($post_id, 'large');
        if ($thumb) {
            return $thumb;
        }
    }

    $fallbacks = array(
        '/wp-content/uploads/2026/02/Harmat22_latvany-3-400x225.jpg',
        '/wp-content/uploads/2026/02/Harmat22_latvany-4-400x225.jpg',
        '/wp-content/uploads/2026/02/Harmat22_latvany-8-400x225.jpg',
        '/wp-content/uploads/2026/02/Harmat22_latvany-10-400x225.jpg',
        '/wp-content/uploads/2026/02/Harmat22_latvany-19-400x225.jpg',
    );

    return home_url($fallbacks[$index % count($fallbacks)]);
}

function harmat_lakas_redesign_unique($items, $key) {
    $values = array();
    foreach ($items as $item) {
        $value = isset($item[$key]) ? (string) $item[$key] : '';
        if ($value !== '') {
            $values[$value] = true;
        }
    }
    $values = array_keys($values);
    if ($key === 'floor') {
        usort($values, function ($a, $b) {
            $rank = function ($value) {
                $value = trim((string) $value);
                if (strcasecmp($value, 'Fsz') === 0) {
                    return -1;
                }
                if (is_numeric($value)) {
                    return (int) $value;
                }
                return 1000;
            };

            $rank_a = $rank($a);
            $rank_b = $rank($b);
            if ($rank_a === $rank_b) {
                return strcasecmp((string) $a, (string) $b);
            }

            return $rank_a <=> $rank_b;
        });
        return array_values($values);
    }

    natcasesort($values);
    return array_values($values);
}

function harmat_lakas_redesign_card_markup($item, $index = 0) {
    $status = isset($item['status']) ? (string) $item['status'] : 'current';
    $title = isset($item['title']) ? (string) $item['title'] : '';
    $building = isset($item['building']) ? (string) $item['building'] : '';
    $floor = isset($item['floor']) ? (string) $item['floor'] : '';
    $rooms_value = isset($item['rooms']) ? (string) $item['rooms'] : '';
    $area = $item['salesArea'] ?? 0;
    $net = $item['b_area'] ?? 0;
    $terrace = $item['terrace'] ?? 0;
    $hide_price = !empty($item['hidePrice']);
    $price = harmat_lakas_redesign_money($item['price'] ?? 0, $hide_price);
    $sqm_price = harmat_lakas_redesign_public_sqm_price($item);
    $sqm_price_label = harmat_lakas_redesign_sqm_money($item, $hide_price);
    $url = !empty($item['url']) ? $item['url'] : '#';

    ob_start();
    ?>
    <article class="hm-lakas-card hm-status-<?php echo esc_attr($status); ?>"
        data-card
        data-status="<?php echo esc_attr($status); ?>"
        data-query="<?php echo esc_attr(strtolower($title)); ?>"
        data-building="<?php echo esc_attr($building); ?>"
        data-floor="<?php echo esc_attr($floor); ?>"
        data-rooms="<?php echo esc_attr($rooms_value); ?>"
        data-price-hidden="<?php echo esc_attr($hide_price ? '1' : '0'); ?>"
        data-price="<?php echo esc_attr($hide_price ? '' : (int) ($item['price'] ?? 0)); ?>"
        data-sqm-price="<?php echo esc_attr($sqm_price ?: ''); ?>">
        <a class="hm-lakas-media" href="<?php echo esc_url($url); ?>">
            <img src="<?php echo esc_url(harmat_lakas_redesign_image($item, $index)); ?>" alt="<?php echo esc_attr($title ?: 'Harmat Lakópark lakás'); ?>" loading="lazy" decoding="async">
            <span class="hm-lakas-badge"><?php echo esc_html($title); ?></span>
            <span class="hm-lakas-status"><?php echo esc_html($item['statusLabel'] ?? 'Elérhető'); ?></span>
        </a>
        <div class="hm-lakas-body">
            <div class="hm-lakas-price">
                <small>Árinformáció</small>
                <strong><?php echo esc_html($price); ?></strong>
                <span><?php echo esc_html($sqm_price_label); ?></span>
            </div>
            <div class="hm-lakas-facts">
                <div><small>Épület</small><strong><?php echo esc_html($building ?: '-'); ?></strong></div>
                <div><small>Emelet</small><strong><?php echo esc_html($floor ?: '-'); ?></strong></div>
                <div><small>Szoba</small><strong><?php echo esc_html($rooms_value ?: '-'); ?></strong></div>
                <div><small>Eladási terület</small><strong><?php echo esc_html(harmat_lakas_redesign_area($area)); ?></strong></div>
                <div><small>Alapterület</small><strong><?php echo esc_html(harmat_lakas_redesign_area($net)); ?></strong></div>
                <div><small>Terasz / kert</small><strong><?php echo esc_html(harmat_lakas_redesign_area($terrace)); ?></strong></div>
            </div>
            <div class="hm-lakas-actions">
                <a href="<?php echo esc_url($url); ?>">Megnézem</a>
                <a href="<?php echo esc_url($url); ?>#opal-contactform-popup" class="hm-lakas-outline">Ajánlatot kérek</a>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

function harmat_lakas_redesign_render_related() {
    $items = harmat_lakas_redesign_items();
    if (!$items) {
        return '';
    }

    $current_id = get_queried_object_id();
    $current = null;
    foreach ($items as $item) {
        if (!empty($item['id']) && (int) $item['id'] === (int) $current_id) {
            $current = $item;
            break;
        }
    }

    $current_rooms = $current['rooms'] ?? '';
    $current_building = $current['building'] ?? '';
    $ranked = array();
    foreach ($items as $item) {
        if (!empty($item['id']) && (int) $item['id'] === (int) $current_id) {
            continue;
        }
        $rank = 30;
        if ($current_rooms !== '' && (string) ($item['rooms'] ?? '') === (string) $current_rooms) {
            $rank -= 18;
        }
        if ($current_building !== '' && (string) ($item['building'] ?? '') === (string) $current_building) {
            $rank -= 8;
        }
        if (($item['status'] ?? 'current') === 'current') {
            $rank -= 3;
        }
        $ranked[] = array('rank' => $rank, 'item' => $item);
    }
    usort($ranked, function ($a, $b) {
        return $a['rank'] <=> $b['rank'];
    });

    $related = array_slice(array_column($ranked, 'item'), 0, 6);
    if (!$related) {
        return '';
    }

    ob_start();
    ?>
    <section id="hm-lakas-related-source" class="hm-lakas-page hm-lakas-related-section">
        <div class="hm-lakas-related-head">
            <i aria-hidden="true" class="icon_before opal-icon-decor"></i>
            <h2>Hasonló lakások</h2>
        </div>
        <div class="hm-lakas-grid">
            <?php foreach ($related as $index => $item) : ?>
                <?php echo harmat_lakas_redesign_card_markup($item, $index); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <script>
    (function(){
      var source=document.getElementById("hm-lakas-related-source");
      if(!source) return;
      var headings=Array.prototype.slice.call(document.querySelectorAll("h1,h2,h3,.elementor-heading-title"));
      var heading=headings.find(function(node){ return /Hasonl/i.test((node.textContent||"").trim()); });
      if(!heading) return;
      var headingSection=heading.closest(".e-con.e-parent, .elementor-section, section, .elementor-element");
      var gridSection=headingSection ? headingSection.nextElementSibling : null;
      if(headingSection && headingSection.parentNode){
        headingSection.parentNode.insertBefore(source, headingSection);
      }
      var sourceTop=source.getBoundingClientRect().top + window.scrollY;
      var oldLoop=Array.prototype.slice.call(document.querySelectorAll(".elementor-widget-loop-grid")).find(function(node){
        return node.getBoundingClientRect().top + window.scrollY > sourceTop;
      });
      if(headingSection) headingSection.remove();
      if(gridSection && gridSection.querySelector(".elementor-widget-loop-grid")) gridSection.remove();
      if(oldLoop) {
        var oldLoopSection=oldLoop.closest(".e-con.e-parent, .elementor-section, section, .elementor-element");
        if(oldLoopSection) oldLoopSection.remove();
      }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function harmat_lakas_redesign_render() {
    if (!headers_sent()) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $cached_markup = get_transient(harmat_lakas_redesign_cache_key());
    if (is_string($cached_markup) && $cached_markup !== '') {
        return $cached_markup;
    }

    $items = harmat_lakas_redesign_items();
    if (!$items) {
        return '<section class="hm-lakas-page"><div class="hm-lakas-empty">Jelenleg nincs megjeleníthető lakás.</div></section>';
    }

    $current_count = 0;
    foreach ($items as $item) {
        if (($item['status'] ?? 'current') === 'current') {
            $current_count++;
        }
    }

    $buildings = harmat_lakas_redesign_unique($items, 'building');
    $floors = harmat_lakas_redesign_unique($items, 'floor');
    $rooms = harmat_lakas_redesign_unique($items, 'rooms');
    $sqm_range = harmat_lakas_redesign_public_sqm_range($items);

    ob_start();
    ?>
    <section class="hm-lakas-page" data-hm-lakas-page>
        <div class="hm-lakas-hero">
            <p>Harmat Lakópark</p>
            <h1>Lakáskereső</h1>
            <div class="hm-lakas-stats" aria-label="Lakás statisztika">
                <span><strong><?php echo esc_html(count($items)); ?></strong> lakás</span>
                <span><strong><?php echo esc_html($current_count); ?></strong> elérhető</span>
                <span><strong>Árak</strong> lakásonként</span>
            </div>
        </div>

        <div class="hm-lakas-toolbar" data-hm-filter data-sqm-active="0">
            <div class="hm-lakas-tabs" role="group" aria-label="Státusz">
                <button type="button" class="is-active" data-status="all">Mind</button>
                <button type="button" data-status="current">Elérhető</button>
                <button type="button" data-status="reserved">Foglalva</button>
                <button type="button" data-status="sold">Eladva</button>
            </div>
            <label>
                <span>Lakás száma</span>
                <input type="search" data-filter="query" placeholder="pl. A1-F-L1">
            </label>
            <label>
                <span>Épület</span>
                <select data-filter="building">
                    <option value="">Mind</option>
                    <?php foreach ($buildings as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Emelet</span>
                <select data-filter="floor">
                    <option value="">Mind</option>
                    <?php foreach ($floors as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Szoba</span>
                <select data-filter="rooms">
                    <option value="">Mind</option>
                    <?php foreach ($rooms as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?> szoba</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="hm-lakas-sort-field">
                <span>Vételár szerint</span>
                <select data-filter="sort">
                    <option value="">Alapértelmezett</option>
                    <option value="price-asc">Legalacsonyabb ár</option>
                    <option value="price-desc">Legmagasabb ár</option>
                </select>
            </label>
            <div class="hm-lakas-range-field" data-sqm-range>
                <span>m² ár tartomány</span>
                <div class="hm-lakas-range-box">
                    <strong data-sqm-summary>Mind</strong>
                    <div class="hm-lakas-range-slider" data-range-slider>
                        <span class="hm-lakas-range-track"></span>
                        <span class="hm-lakas-range-fill" data-range-fill></span>
                        <input type="range" data-filter="sqmMin" min="<?php echo esc_attr($sqm_range['min']); ?>" max="<?php echo esc_attr($sqm_range['max']); ?>" step="<?php echo esc_attr($sqm_range['step']); ?>" value="<?php echo esc_attr($sqm_range['min']); ?>" data-default="<?php echo esc_attr($sqm_range['min']); ?>" aria-label="m² ár alsó határ">
                        <input type="range" data-filter="sqmMax" min="<?php echo esc_attr($sqm_range['min']); ?>" max="<?php echo esc_attr($sqm_range['max']); ?>" step="<?php echo esc_attr($sqm_range['step']); ?>" value="<?php echo esc_attr($sqm_range['max']); ?>" data-default="<?php echo esc_attr($sqm_range['max']); ?>" aria-label="m² ár felső határ">
                    </div>
                </div>
            </div>
            <button type="button" class="hm-lakas-reset" data-reset>Alaphelyzet</button>
        </div>

        <div class="hm-lakas-resultbar">
            <strong data-count><?php echo esc_html(count($items)); ?></strong>
            <span>találat</span>
        </div>

        <div class="hm-lakas-grid">
            <?php foreach (array_values($items) as $index => $item) :
                $status = isset($item['status']) ? (string) $item['status'] : 'current';
                $title = isset($item['title']) ? (string) $item['title'] : '';
                $building = isset($item['building']) ? (string) $item['building'] : '';
                $floor = isset($item['floor']) ? (string) $item['floor'] : '';
                $rooms_value = isset($item['rooms']) ? (string) $item['rooms'] : '';
                $area = $item['salesArea'] ?? 0;
                $net = $item['b_area'] ?? 0;
                $terrace = $item['terrace'] ?? 0;
                $hide_price = !empty($item['hidePrice']);
                $price = harmat_lakas_redesign_money($item['price'] ?? 0, $hide_price);
                $sqm_price = harmat_lakas_redesign_public_sqm_price($item);
                $sqm_price_label = harmat_lakas_redesign_sqm_money($item, $hide_price);
                $url = !empty($item['url']) ? $item['url'] : '#';
                ?>
                <article class="hm-lakas-card hm-status-<?php echo esc_attr($status); ?>"
                    data-card
                    data-status="<?php echo esc_attr($status); ?>"
                    data-query="<?php echo esc_attr(strtolower($title)); ?>"
                    data-building="<?php echo esc_attr($building); ?>"
                    data-floor="<?php echo esc_attr($floor); ?>"
                    data-rooms="<?php echo esc_attr($rooms_value); ?>"
                    data-price-hidden="<?php echo esc_attr($hide_price ? '1' : '0'); ?>"
                    data-price="<?php echo esc_attr($hide_price ? '' : (int) ($item['price'] ?? 0)); ?>"
                    data-sqm-price="<?php echo esc_attr($sqm_price ?: ''); ?>">
                    <a class="hm-lakas-media" href="<?php echo esc_url($url); ?>">
                        <img src="<?php echo esc_url(harmat_lakas_redesign_image($item, $index)); ?>" alt="<?php echo esc_attr($title ?: 'Harmat Lakópark lakás'); ?>" loading="lazy" decoding="async">
                        <span class="hm-lakas-badge"><?php echo esc_html($title); ?></span>
                        <span class="hm-lakas-status"><?php echo esc_html($item['statusLabel'] ?? 'Elérhető'); ?></span>
                    </a>
                    <div class="hm-lakas-body">
                        <div class="hm-lakas-price">
                            <small>Árinformáció</small>
                            <strong><?php echo esc_html($price); ?></strong>
                            <span><?php echo esc_html($sqm_price_label); ?></span>
                        </div>
                        <div class="hm-lakas-facts">
                            <div><small>Épület</small><strong><?php echo esc_html($building ?: '-'); ?></strong></div>
                            <div><small>Emelet</small><strong><?php echo esc_html($floor ?: '-'); ?></strong></div>
                            <div><small>Szoba</small><strong><?php echo esc_html($rooms_value ?: '-'); ?></strong></div>
                            <div><small>Eladási terület</small><strong><?php echo esc_html(harmat_lakas_redesign_area($area)); ?></strong></div>
                            <div><small>Alapterület</small><strong><?php echo esc_html(harmat_lakas_redesign_area($net)); ?></strong></div>
                            <div><small>Terasz / kert</small><strong><?php echo esc_html(harmat_lakas_redesign_area($terrace)); ?></strong></div>
                        </div>
                        <div class="hm-lakas-actions">
                            <a href="<?php echo esc_url($url); ?>">Megnézem</a>
                            <a href="<?php echo esc_url($url); ?>#opal-contactform-popup" class="hm-lakas-outline">Ajánlatot kérek</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="hm-lakas-empty" data-empty>Nincs a szűrésnek megfelelő lakás.</div>
    </section>
    <?php
    $markup = ob_get_clean();
    set_transient(harmat_lakas_redesign_cache_key(), $markup, 30 * MINUTE_IN_SECONDS);
    return $markup;
}

function harmat_lakas_redesign_css() {
    return '
    body.harmat-lakas-redesign-page .site-content{background:#fbf4e8}
    .hm-lakas-page{width:min(1320px,calc(100% - 44px));margin:0 auto;padding:56px 0 76px;color:#253137;font-family:Montserrat,Arial,sans-serif}
    .hm-lakas-related-section{padding-top:62px}
    .hm-lakas-related-head{margin-bottom:28px}
    .hm-lakas-related-head h2{margin:0;color:#253137;font-family:"Marcellus SC",Georgia,serif;font-size:36px;font-weight:400;line-height:1.15;text-transform:uppercase}
    .hm-lakas-related-head .icon_before{display:block;margin-bottom:10px;color:#a8762d;font-size:16px}
    .hm-lakas-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 32px;align-items:center;margin-bottom:22px;padding:24px 30px;border:1px solid rgba(168,118,45,.2);background:linear-gradient(135deg,#fffaf1,#f6ead8)}
    .hm-lakas-hero p{grid-column:1;grid-row:1;margin:0;color:#a8762d;font-size:12px;font-weight:900;letter-spacing:.22em;text-transform:uppercase}
    .hm-lakas-hero h1{grid-column:1;grid-row:2;margin:0;padding:0!important;color:#253137;font-family:"Marcellus SC",Georgia,serif;font-size:50px;font-weight:400;line-height:.98;letter-spacing:.04em;text-transform:uppercase}
    .hm-lakas-stats{grid-column:2;grid-row:1/3;display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;align-self:center}
    .hm-lakas-stats span{min-height:38px;display:inline-flex;align-items:center;gap:7px;padding:0 14px;border:1px solid rgba(168,118,45,.22);border-radius:999px;background:#fff;color:#687078;font-size:12px;font-weight:700}
    .hm-lakas-stats strong{color:#253137;font-size:16px}
    .hm-lakas-toolbar{display:grid;grid-template-columns:minmax(180px,1.05fr) repeat(3,minmax(108px,.68fr)) minmax(168px,.9fr) minmax(360px,2fr) auto;gap:14px;align-items:end;margin-bottom:18px;padding:22px;border:1px solid rgba(168,118,45,.2);background:#fffdf8}
    .hm-lakas-tabs{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:8px}
    .hm-lakas-tabs button,.hm-lakas-reset{min-height:38px;padding:0 18px;border:1px solid rgba(168,118,45,.34);background:#fff;color:#8c621f;font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;cursor:pointer}
    .hm-lakas-tabs button{border-radius:999px}
    .hm-lakas-tabs button.is-active{background:#253137;border-color:#253137;color:#fff}
    .hm-lakas-tabs button[data-status="current"].is-active{background:#1f7a4d;border-color:#1f7a4d}
    .hm-lakas-tabs button[data-status="reserved"].is-active{background:#b77a24;border-color:#b77a24}
    .hm-lakas-tabs button[data-status="sold"].is-active{background:#69727d;border-color:#69727d}
    .hm-lakas-toolbar label{display:grid;gap:7px;margin:0;color:#a8762d;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
    .hm-lakas-toolbar input,.hm-lakas-toolbar select{width:100%;height:46px;border:1px solid rgba(168,118,45,.35);background:#fff;color:#253137;padding:0 13px;font-size:14px;outline:none}
    .hm-lakas-toolbar input:focus,.hm-lakas-toolbar select:focus{border-color:#a8762d;box-shadow:0 0 0 3px rgba(168,118,45,.12)}
    .hm-lakas-range-field{display:grid;gap:7px;margin:0;color:#a8762d;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
    .hm-lakas-range-box{height:46px;display:grid;grid-template-rows:18px 1fr;gap:3px;border:1px solid rgba(168,118,45,.35);background:#fff;padding:5px 13px 7px}
    .hm-lakas-range-box strong{display:block;min-width:0;color:#253137;font-size:12px;font-weight:900;letter-spacing:0;line-height:18px;text-align:center;text-transform:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .hm-lakas-range-slider{position:relative;height:18px}
    .hm-lakas-range-track,.hm-lakas-range-fill{position:absolute;left:0;right:0;top:50%;height:4px;border-radius:999px;transform:translateY(-50%)}
    .hm-lakas-range-track{background:#ead8b8}
    .hm-lakas-range-fill{background:#a8762d}
    .hm-lakas-range-slider input[type=range]{position:absolute;left:0;top:0;width:100%;height:18px;margin:0;padding:0;border:0;background:transparent;box-shadow:none;pointer-events:none;-webkit-appearance:none;appearance:none}
    .hm-lakas-range-slider input[type=range]:focus{box-shadow:none}
    .hm-lakas-range-slider input[type=range][data-filter="sqmMin"]{z-index:3}
    .hm-lakas-range-slider input[type=range][data-filter="sqmMax"]{z-index:4}
    .hm-lakas-range-slider input[type=range]::-webkit-slider-runnable-track{height:4px;background:transparent}
    .hm-lakas-range-slider input[type=range]::-moz-range-track{height:4px;background:transparent}
    .hm-lakas-range-slider input[type=range]::-webkit-slider-thumb{width:18px;height:18px;margin-top:-7px;border:3px solid #fff;border-radius:999px;background:#a8762d;box-shadow:0 4px 12px rgba(37,49,55,.24);cursor:pointer;pointer-events:auto;-webkit-appearance:none;appearance:none}
    .hm-lakas-range-slider input[type=range]::-moz-range-thumb{width:18px;height:18px;border:3px solid #fff;border-radius:999px;background:#a8762d;box-shadow:0 4px 12px rgba(37,49,55,.24);cursor:pointer;pointer-events:auto}
    .hm-lakas-reset{height:46px;background:#4f4c49;color:#d5aa6f;border-color:#4f4c49}
    .hm-lakas-resultbar{display:flex;align-items:center;gap:8px;margin:0 0 20px;color:#687078;font-size:14px}
    .hm-lakas-resultbar strong{min-width:44px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#a8762d;color:#fff}
    .hm-lakas-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
    .hm-lakas-card{border:1px solid rgba(168,118,45,.18);background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 18px 42px rgba(31,35,37,.08)}
    .hm-lakas-card.is-hidden{display:none}
    .hm-lakas-media{position:relative;display:block;height:220px;background:#efe7da;overflow:hidden;text-decoration:none}
    .hm-lakas-media img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s ease}
    .hm-lakas-card:hover .hm-lakas-media img{transform:scale(1.035)}
    .hm-lakas-badge,.hm-lakas-status{position:absolute;top:14px;z-index:2;display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;color:#fff;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 12px 26px rgba(18,24,26,.18)}
    .hm-lakas-badge{left:14px;background:#253137}
    .hm-lakas-status{right:14px;background:#1f7a4d}
    .hm-status-reserved .hm-lakas-status{background:#b77a24}
    .hm-status-sold .hm-lakas-status{background:#69727d}
    .hm-status-sold{filter:grayscale(.15);opacity:.82}
    .hm-lakas-body{padding:18px}
    .hm-lakas-price{margin-bottom:14px;padding:13px 14px;border-left:3px solid #a8762d;background:#fff8ed}
    .hm-lakas-price small,.hm-lakas-facts small{display:block;margin-bottom:5px;color:#a8762d;font-family:"Marcellus SC",Georgia,serif;font-size:11px;line-height:1.1;text-transform:uppercase}
    .hm-lakas-price strong{display:block;color:#253137;font-size:18px;line-height:1.25}
    .hm-lakas-price span{display:block;margin-top:5px;color:#687078;font-size:12px;font-weight:800;line-height:1.25}
    .hm-lakas-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid rgba(168,118,45,.16);border-radius:6px;overflow:hidden}
    .hm-lakas-facts div{min-width:0;padding:12px;border-right:1px solid rgba(168,118,45,.13);border-bottom:1px solid rgba(168,118,45,.13);background:#fffdf8}
    .hm-lakas-facts div:nth-child(3n){border-right:0}
    .hm-lakas-facts div:nth-last-child(-n+3){border-bottom:0}
    .hm-lakas-facts strong{display:block;color:#253137;font-size:15px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .hm-lakas-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}
    .hm-lakas-actions a{min-height:42px;display:inline-flex;align-items:center;justify-content:center;background:#a8762d;color:#fff;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
    .hm-lakas-actions .hm-lakas-outline{background:#fff;border:1px solid #a8762d;color:#a8762d}
    .hm-lakas-empty{display:none;margin:28px 0 0;padding:24px;border:1px solid rgba(168,118,45,.2);background:#fff;text-align:center;color:#687078}
    .hm-lakas-empty.is-visible{display:block}
    @media(min-width:1121px) and (max-width:1360px){.hm-lakas-toolbar{grid-template-columns:minmax(180px,1.05fr) repeat(3,minmax(108px,.68fr)) minmax(168px,.9fr) minmax(120px,.7fr)}.hm-lakas-range-field{grid-column:1/6}.hm-lakas-reset{grid-column:6}}
    @media(max-width:1120px){.hm-lakas-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.hm-lakas-range-field{grid-column:1/-1}.hm-lakas-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.hm-lakas-reset{grid-column:auto}.hm-lakas-hero{grid-template-columns:1fr}.hm-lakas-hero p,.hm-lakas-hero h1,.hm-lakas-stats{grid-column:1;grid-row:auto}.hm-lakas-stats{justify-content:flex-start}}
    @media(max-width:680px){.hm-lakas-page{width:calc(100% - 24px);padding:34px 0 56px}.hm-lakas-hero{padding:24px 18px}.hm-lakas-hero h1{font-size:38px}.hm-lakas-toolbar{grid-template-columns:1fr;padding:16px}.hm-lakas-range-field{grid-column:1;grid-template-columns:1fr}.hm-lakas-grid{grid-template-columns:1fr}.hm-lakas-media{height:238px}.hm-lakas-facts{grid-template-columns:repeat(2,minmax(0,1fr))}.hm-lakas-facts div:nth-child(n){border-right:1px solid rgba(168,118,45,.13);border-bottom:1px solid rgba(168,118,45,.13)}.hm-lakas-facts div:nth-child(2n){border-right:0}.hm-lakas-actions{grid-template-columns:1fr}}

    body.single-property .elementor-widget-loop-grid .e-loop-item.property .property_loop,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-section-wrap,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property > .elementor {
        border-radius:8px!important;
        overflow:hidden!important;
        background:#fff!important;
        border:1px solid rgba(168,118,45,.18)!important;
        box-shadow:0 18px 42px rgba(31,35,37,.08)!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property {
        background:transparent!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property img {
        width:100%!important;
        height:220px!important;
        object-fit:cover!important;
        display:block!important;
        transition:transform .35s ease!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property:hover img {
        transform:scale(1.035)!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-image,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-image .elementor-widget-container {
        margin:0!important;
        line-height:0!important;
        overflow:hidden!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-heading.elementor-absolute,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-heading[class*="elementor-absolute"] {
        top:14px!important;
        left:14px!important;
        right:auto!important;
        z-index:8!important;
        width:auto!important;
        max-width:calc(100% - 28px)!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-heading.elementor-absolute .elementor-heading-title,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-heading[class*="elementor-absolute"] .elementor-heading-title {
        min-height:32px!important;
        display:inline-flex!important;
        align-items:center!important;
        padding:0 12px!important;
        border-radius:999px!important;
        background:#253137!important;
        color:#fff!important;
        font-family:Montserrat,Arial,sans-serif!important;
        font-size:11px!important;
        font-weight:900!important;
        letter-spacing:.08em!important;
        line-height:1!important;
        text-transform:uppercase!important;
        box-shadow:0 12px 26px rgba(18,24,26,.18)!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid {
        width:calc(100% - 36px)!important;
        margin:16px 18px 0!important;
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:0!important;
        border:1px solid rgba(168,118,45,.16)!important;
        border-radius:6px!important;
        overflow:hidden!important;
        background:#fffdf8!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full > *,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid > * {
        min-width:0!important;
        padding:12px!important;
        border-right:1px solid rgba(168,118,45,.13)!important;
        border-bottom:1px solid rgba(168,118,45,.13)!important;
        background:transparent!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full > *:nth-child(3n),
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid > *:nth-child(3n) {
        border-right:0!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property h6 {
        margin:0 0 5px!important;
        color:#a8762d!important;
        font-family:"Marcellus SC",Georgia,serif!important;
        font-size:11px!important;
        line-height:1.1!important;
        text-transform:uppercase!important;
        white-space:nowrap!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property p {
        margin:0!important;
        color:#253137!important;
        font-family:Montserrat,Arial,sans-serif!important;
        font-size:14px!important;
        font-weight:600!important;
        line-height:1.25!important;
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-button-wrapper,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .more-link-wrap,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .property-box-button {
        display:flex!important;
        justify-content:center!important;
        margin:18px 18px 22px!important;
    }
    body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-button,
    body.single-property .elementor-widget-loop-grid .e-loop-item.property a.elementor-button {
        min-height:42px!important;
        padding:0 24px!important;
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        border:0!important;
        border-radius:0!important;
        background:#a8762d!important;
        color:#fff!important;
        font-family:Montserrat,Arial,sans-serif!important;
        font-size:12px!important;
        font-weight:900!important;
        letter-spacing:.12em!important;
        text-transform:uppercase!important;
        box-shadow:none!important;
    }
    @media(max-width:680px){
      body.single-property .elementor-widget-loop-grid .e-loop-item.property img{height:238px!important}
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full,
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full > *:nth-child(n),
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid > *:nth-child(n){border-right:1px solid rgba(168,118,45,.13)!important;border-bottom:1px solid rgba(168,118,45,.13)!important}
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .e-grid.e-con-full > *:nth-child(2n),
      body.single-property .elementor-widget-loop-grid .e-loop-item.property .elementor-widget-container .e-grid > *:nth-child(2n){border-right:0!important}
    }
    ';
}

function harmat_lakas_redesign_js() {
    return '
    (function(){
      var page=document.querySelector("[data-hm-lakas-page]");
      if(!page) return;
      var cards=Array.prototype.slice.call(page.querySelectorAll("[data-card]"));
      var filters=page.querySelector("[data-hm-filter]");
      var count=page.querySelector("[data-count]");
      var empty=page.querySelector("[data-empty]");
      var grid=page.querySelector(".hm-lakas-grid");
      cards.forEach(function(card,index){card.dataset.originalOrder=String(index);});
      var state={status:"all",query:"",building:"",floor:"",rooms:"",sqmMin:"",sqmMax:"",sqmActive:false,sort:""};
      function numberValue(value){
        return parseInt(String(value||"").replace(/[^0-9]/g,""),10)||0;
      }
      function money(value){
        return new Intl.NumberFormat("hu-HU").format(numberValue(value));
      }
      function rangeField(name){
        return filters.querySelector("[data-filter="+name+"]");
      }
      function syncRangeFill(){
        var minField=rangeField("sqmMin");
        var maxField=rangeField("sqmMax");
        var fill=filters.querySelector("[data-range-fill]");
        if(!minField||!maxField||!fill) return;
        var low=numberValue(minField.min);
        var high=numberValue(minField.max);
        var span=Math.max(high-low,1);
        var minValue=numberValue(minField.value);
        var maxValue=numberValue(maxField.value);
        fill.style.left=Math.max(0,Math.min(100,((minValue-low)/span)*100))+"%";
        fill.style.right=Math.max(0,Math.min(100,(100-((maxValue-low)/span)*100)))+"%";
      }
      function syncRangeLabels(){
        var summary=filters.querySelector("[data-sqm-summary]");
        if(summary) summary.textContent=state.sqmActive?(money(state.sqmMin)+" - "+money(state.sqmMax)+" Ft/m²"):"Mind";
        filters.dataset.sqmActive=state.sqmActive?"1":"0";
        syncRangeFill();
      }
      function resetRange(){
        var minField=rangeField("sqmMin");
        var maxField=rangeField("sqmMax");
        if(minField) minField.value=minField.dataset.default||minField.min||"";
        if(maxField) maxField.value=maxField.dataset.default||maxField.max||"";
        state.sqmMin=minField?minField.value:"";
        state.sqmMax=maxField?maxField.value:"";
        state.sqmActive=false;
        syncRangeLabels();
      }
      function applyUrlState(){
        var params=new URLSearchParams(window.location.search||"");
        var rooms=params.get("rooms")||"";
        var roomField=filters.querySelector("[data-filter=rooms]");
        if(!/^[1-5]$/.test(rooms)||!roomField||!roomField.querySelector("option[value=\""+rooms+"\"]")) return;
        state.rooms=rooms;
        roomField.value=rooms;
      }
      function syncRangeState(changedName){
        var minField=rangeField("sqmMin");
        var maxField=rangeField("sqmMax");
        if(!minField||!maxField) return;
        var minValue=numberValue(minField.value);
        var maxValue=numberValue(maxField.value);
        if(minValue>maxValue){
          if(changedName==="sqmMin"){
            maxValue=minValue;
            maxField.value=String(maxValue);
          }else{
            minValue=maxValue;
            minField.value=String(minValue);
          }
        }
        state.sqmMin=String(minValue);
        state.sqmMax=String(maxValue);
        state.sqmActive=true;
        syncRangeLabels();
      }
      function cardSqm(card){
        return numberValue(card.dataset.sqmPrice);
      }
      function cardPrice(card){
        return numberValue(card.dataset.price);
      }
      function isPriceKnown(card){
        return card.dataset.priceHidden!=="1"&&cardSqm(card)>0;
      }
      function isTotalPriceKnown(card){
        return card.dataset.priceHidden!=="1"&&cardPrice(card)>0;
      }
      function sortCards(){
        if(!grid) return;
        var sorted=cards.slice();
        sorted.sort(function(a,b){
          if(state.sort==="price-asc"||state.sort==="price-desc"){
            var ap=isTotalPriceKnown(a);
            var bp=isTotalPriceKnown(b);
            if(ap!==bp) return ap?-1:1;
            if(ap&&bp){
              var priceDelta=cardPrice(a)-cardPrice(b);
              if(priceDelta!==0) return state.sort==="price-asc"?priceDelta:-priceDelta;
            }
          }
          if(state.sort==="sqm-asc"||state.sort==="sqm-desc"){
            var ak=isPriceKnown(a);
            var bk=isPriceKnown(b);
            if(ak!==bk) return ak?-1:1;
            if(ak&&bk){
              var delta=cardSqm(a)-cardSqm(b);
              if(delta!==0) return state.sort==="sqm-asc"?delta:-delta;
            }
          }
          return numberValue(a.dataset.originalOrder)-numberValue(b.dataset.originalOrder);
        });
        sorted.forEach(function(card){grid.appendChild(card);});
      }
      function apply(){
        var visible=0;
        var minSqm=numberValue(state.sqmMin);
        var maxSqm=numberValue(state.sqmMax);
        cards.forEach(function(card){
          var sqm=cardSqm(card);
          var priceOk=true;
          if(state.sqmActive&&(minSqm||maxSqm)){
            priceOk=isPriceKnown(card)&&(!minSqm||sqm>=minSqm)&&(!maxSqm||sqm<=maxSqm);
          }
          var ok=(state.status==="all"||card.dataset.status===state.status)&&
            (!state.query||(card.dataset.query||"").indexOf(state.query)!==-1)&&
            (!state.building||card.dataset.building===state.building)&&
            (!state.floor||card.dataset.floor===state.floor)&&
            (!state.rooms||card.dataset.rooms===state.rooms)&&
            priceOk;
          card.classList.toggle("is-hidden",!ok);
          if(ok) visible++;
        });
        sortCards();
        if(count) count.textContent=visible;
        if(empty) empty.classList.toggle("is-visible",visible===0);
        filters.querySelectorAll("[data-status]").forEach(function(btn){btn.classList.toggle("is-active",btn.dataset.status===state.status);});
      }
      filters.addEventListener("click",function(e){
        var status=e.target.closest("[data-status]");
        if(status){state.status=status.dataset.status||"all";apply();return;}
        if(e.target.closest("[data-reset]")){
          state={status:"all",query:"",building:"",floor:"",rooms:"",sqmMin:"",sqmMax:"",sqmActive:false,sort:""};
          filters.querySelectorAll("[data-filter]").forEach(function(field){
            if(field.type==="range") return;
            field.value="";
          });
          resetRange();
          apply();
        }
      });
      filters.addEventListener("input",function(e){
        var field=e.target.closest("[data-filter]");
        if(!field) return;
        var value=(field.value||"").trim();
        if(field.dataset.filter==="sqmMin"||field.dataset.filter==="sqmMax"){
          syncRangeState(field.dataset.filter);
          apply();
          return;
        }
        state[field.dataset.filter]=field.dataset.filter==="query"?value.toLowerCase():value;
        apply();
      });
      filters.addEventListener("change",function(e){
        var field=e.target.closest("[data-filter]");
        if(!field) return;
        if(field.dataset.filter==="sqmMin"||field.dataset.filter==="sqmMax"){
          syncRangeState(field.dataset.filter);
          apply();
          return;
        }
        state[field.dataset.filter]=field.value;
        apply();
      });
      resetRange();
      applyUrlState();
      apply();
    })();
    ';
}
