<?php
/**
 * Plugin Name: Harmat Homepage Energy Grade
 * Description: Adds the approved A+ energy grade to the homepage project summary.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_home_energy_grade_source($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $replacements = array(
        '<div class="harmat-about-meta-item"><span>M\\u0171szaki tartalom</span><strong>Hamarosan</strong></div>' => '<div class="harmat-about-meta-item"><span>Energetikai besorol\\u00e1s</span><strong>A+</strong></div>',
        '<div class="harmat-about-meta-item"><span>Műszaki tartalom</span><strong>Hamarosan</strong></div>' => '<div class="harmat-about-meta-item"><span>Energetikai besorolás</span><strong>A+</strong></div>',
        '<div class="harmat-visual-badges"><span>24 \\u00f3r\\u00e1s z\\u00e1rt lak\\u00f3park</span><span>75% z\\u00f6ldfel\\u00fclet</span></div>' => '<div class="harmat-visual-badges"><span>24 \\u00f3r\\u00e1s z\\u00e1rt lak\\u00f3park</span><span>75% z\\u00f6ldfel\\u00fclet</span><span data-harmat-energy-grade="1">A+ energiaoszt\\u00e1ly</span></div>',
        '<li>398 lak\\u00e1s</li><li>m\\u00e9lygar\\u00e1zs</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>csal\\u00e1dbar\\u00e1t kialak\\u00edt\\u00e1s</li>' => '<li>398 lak\\u00e1s</li><li>m\\u00e9lygar\\u00e1zs</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>csal\\u00e1dbar\\u00e1t kialak\\u00edt\\u00e1s</li><li data-harmat-energy-grade="1">A+ energetikai besorol\\u00e1s</li>',
    );

    return strtr($html, $replacements);
}

function harmat_home_energy_grade_start_buffer() {
    if (is_admin() || wp_doing_ajax() || !is_front_page()) {
        return;
    }

    ob_start('harmat_home_energy_grade_source');
}
add_action('template_redirect', 'harmat_home_energy_grade_start_buffer', 2);

function harmat_home_energy_grade_script() {
    if (is_admin() || !is_front_page()) {
        return;
    }
    ?>
<script id="harmat-home-energy-grade-js">
(function () {
  function setText(node, text) {
    if (node && node.textContent !== text) {
      node.textContent = text;
    }
  }

  function applyEnergyGrade() {
    var section = document.querySelector('body.home .harmat-about-remake');
    if (!section) return;

    var metaItems = section.querySelectorAll('.harmat-about-meta-item');
    if (metaItems.length > 1) {
      setText(metaItems[1].querySelector('span'), 'Energetikai besorolás');
      setText(metaItems[1].querySelector('strong'), 'A+');
    }

    var badges = section.querySelector('.harmat-visual-badges');
    if (badges && !badges.querySelector('[data-harmat-energy-grade]')) {
      var badge = document.createElement('span');
      badge.setAttribute('data-harmat-energy-grade', '1');
      badge.textContent = 'A+ energiaosztály';
      badges.appendChild(badge);
    }

    var list = section.querySelector('.harmat-about-list');
    if (list && !list.querySelector('[data-harmat-energy-grade]')) {
      var item = document.createElement('li');
      item.setAttribute('data-harmat-energy-grade', '1');
      item.textContent = 'A+ energetikai besorolás';
      list.appendChild(item);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyEnergyGrade);
  } else {
    applyEnergyGrade();
  }
  window.addEventListener('load', applyEnergyGrade);
  setTimeout(applyEnergyGrade, 500);
  setTimeout(applyEnergyGrade, 1600);
  setTimeout(applyEnergyGrade, 3200);
}());
</script>
    <?php
}
add_action('wp_footer', 'harmat_home_energy_grade_script', 96);
