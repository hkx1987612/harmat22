<?php
/**
 * Plugin Name: Harmat Homepage Energy Grade
 * Description: Polishes the homepage project summary and adds the approved A energy grade.
 * Version: 1.1.2
 */

defined('ABSPATH') || exit;

function harmat_home_energy_grade_project_intro() {
    return 'A zárt, parkosított lakópark kényelmes, alacsony energiaigényű otthonokat kínál hőszivattyús fűtés-hűtéssel, átgondolt alaprajzokkal és mindennapi szolgáltatásokhoz közeli elhelyezkedéssel.';
}

function harmat_home_energy_grade_project_intro_escaped() {
    return 'A z\\u00e1rt, parkos\\u00edtott lak\\u00f3park k\\u00e9nyelmes, alacsony energiaig\\u00e9ny\\u0171 otthonokat k\\u00edn\\u00e1l h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9ssel, \\u00e1tgondolt alaprajzokkal \\u00e9s mindennapi szolg\\u00e1ltat\\u00e1sokhoz k\\u00f6zeli elhelyezked\\u00e9ssel.';
}

function harmat_home_energy_grade_list_html($escaped = false) {
    if ($escaped) {
        return '<li>124 lak\\u00e1s az els\\u0151 \\u00fctemben</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>75% z\\u00f6ldfel\\u00fclet</li><li>m\\u00e9lygar\\u00e1zs \\u00e9s t\\u00e1rol\\u00f3k</li>';
    }

    return '<li>124 lakás az első ütemben</li><li>hőszivattyús fűtés-hűtés</li><li>75% zöldfelület</li><li>mélygarázs és tárolók</li>';
}

function harmat_home_energy_grade_badges_html($escaped = false) {
    if ($escaped) {
        return '<div class="harmat-visual-badges"><span>24 \\u00f3r\\u00e1s z\\u00e1rt lak\\u00f3park</span><span>75% z\\u00f6ldfel\\u00fclet</span><span>H\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</span></div>';
    }

    return '<div class="harmat-visual-badges"><span>24 órás zárt lakópark</span><span>75% zöldfelület</span><span>Hőszivattyús fűtés-hűtés</span></div>';
}

function harmat_home_energy_grade_source($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $replacements = array(
        '<div class="harmat-about-meta-item"><span>M\\u0171szaki tartalom</span><strong>Hamarosan</strong></div>' => '<div class="harmat-about-meta-item"><span>Energetikai besorol\\u00e1s</span><strong>A</strong></div>',
        '<div class="harmat-about-meta-item"><span>Műszaki tartalom</span><strong>Hamarosan</strong></div>' => '<div class="harmat-about-meta-item"><span>Energetikai besorolás</span><strong>A</strong></div>',
        '<p>Modern \\u00faj \\u00e9p\\u00edt\\u00e9s\\u0171 otthonok Budapest X. ker\\u00fclet\\u00e9ben, z\\u00f6ld k\\u00f6rnyezetben, mindennapi szolg\\u00e1ltat\\u00e1sokhoz \\u00e9s k\\u00f6zleked\\u00e9shez k\\u00f6zel.</p>' => '<p>' . harmat_home_energy_grade_project_intro_escaped() . '</p>',
        '<p>Modern \\u00faj \\u00e9p\\u00edt\\u00e9s\\u0171 otthonok Budapest X. ker\\u00fclet\\u00e9ben.</p>' => '<p>' . harmat_home_energy_grade_project_intro_escaped() . '</p>',
        '<p>Modern új építésű otthonok Budapest X. kerületében, zöld környezetben, mindennapi szolgáltatásokhoz és közlekedéshez közel.</p>' => '<p>' . harmat_home_energy_grade_project_intro() . '</p>',
        '<p>Modern új építésű otthonok Budapest X. kerületében.</p>' => '<p>' . harmat_home_energy_grade_project_intro() . '</p>',
        '<div class="harmat-visual-badges"><span>24 \\u00f3r\\u00e1s z\\u00e1rt lak\\u00f3park</span><span>75% z\\u00f6ldfel\\u00fclet</span></div>' => harmat_home_energy_grade_badges_html(true),
        '<div class="harmat-visual-badges"><span>24 \\u00f3r\\u00e1s z\\u00e1rt lak\\u00f3park</span><span>75% z\\u00f6ldfel\\u00fclet</span><span data-harmat-energy-grade="1">A+ energiaoszt\\u00e1ly</span></div>' => harmat_home_energy_grade_badges_html(true),
        '<div class="harmat-visual-badges"><span>24 órás zárt lakópark</span><span>75% zöldfelület</span></div>' => harmat_home_energy_grade_badges_html(false),
        '<div class="harmat-visual-badges"><span>24 órás zárt lakópark</span><span>75% zöldfelület</span><span data-harmat-energy-grade="1">A+ energiaosztály</span></div>' => harmat_home_energy_grade_badges_html(false),
        '<li>398 lak\\u00e1s</li><li>m\\u00e9lygar\\u00e1zs</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>csal\\u00e1dbar\\u00e1t kialak\\u00edt\\u00e1s</li>' => harmat_home_energy_grade_list_html(true),
        '<li>398 lak\\u00e1s</li><li>m\\u00e9lygar\\u00e1zs</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>csal\\u00e1dbar\\u00e1t kialak\\u00edt\\u00e1s</li><li data-harmat-energy-grade="1">A+ energetikai besorol\\u00e1s</li>' => harmat_home_energy_grade_list_html(true),
        '<li>398 lak\\u00e1s</li><li>z\\u00f6ld k\\u00f6rnyezet</li><li>h\\u0151szivatty\\u00fas f\\u0171t\\u00e9s-h\\u0171t\\u00e9s</li><li>m\\u00e9lygar\\u00e1zs</li><li>csal\\u00e1dbar\\u00e1t kialak\\u00edt\\u00e1s</li><li>\\u00d3hegy park k\\u00f6zels\\u00e9ge</li>' => harmat_home_energy_grade_list_html(true),
        '<li>398 lakás</li><li>mélygarázs</li><li>hőszivattyús fűtés-hűtés</li><li>családbarát kialakítás</li>' => harmat_home_energy_grade_list_html(false),
        '<li>398 lakás</li><li>mélygarázs</li><li>hőszivattyús fűtés-hűtés</li><li>családbarát kialakítás</li><li data-harmat-energy-grade="1">A+ energetikai besorolás</li>' => harmat_home_energy_grade_list_html(false),
        '<li>398 lakás</li><li>zöld környezet</li><li>hőszivattyús fűtés-hűtés</li><li>mélygarázs</li><li>családbarát kialakítás</li><li>Óhegy park közelsége</li>' => harmat_home_energy_grade_list_html(false),
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

function harmat_home_energy_grade_style() {
    if (is_admin() || !is_front_page()) {
        return;
    }
    ?>
<style id="harmat-home-energy-grade-css">
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-copy p {
  max-width: 640px;
  margin-bottom: 24px;
  color: #4f5d63;
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta {
  grid-template-columns: minmax(0, 1fr) minmax(150px, .78fr);
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta-item {
  border-radius: 2px;
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta-item:nth-child(2) {
  border-color: rgba(32, 106, 79, .55);
  background: #1f5f4b;
  box-shadow: 0 18px 36px rgba(31, 95, 75, .18);
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta-item:nth-child(2) span {
  color: rgba(255, 255, 255, .82);
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta-item:nth-child(2) strong {
  color: #fff;
  font-family: Montserrat, Arial, sans-serif;
  font-size: clamp(34px, 4vw, 48px);
  font-weight: 900;
  letter-spacing: .02em;
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-list {
  margin-top: 4px;
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-list li {
  min-height: 46px;
  background: rgba(255,255,255,.82);
}
body.home .harmat-about-remake.harmat-about-energy-polished .harmat-visual-badges span {
  background: rgba(255,255,255,.92);
}
@media (max-width: 767px) {
  body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta {
    grid-template-columns: 1fr;
  }
  body.home .harmat-about-remake.harmat-about-energy-polished .harmat-about-meta-item:nth-child(2) strong {
    font-size: 38px;
  }
}
</style>
    <?php
}
add_action('wp_head', 'harmat_home_energy_grade_style', 41);

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

    section.classList.add('harmat-about-energy-polished');
    setText(section.querySelector('.harmat-about-copy p'), 'A zárt, parkosított lakópark kényelmes, alacsony energiaigényű otthonokat kínál hőszivattyús fűtés-hűtéssel, átgondolt alaprajzokkal és mindennapi szolgáltatásokhoz közeli elhelyezkedéssel.');

    var metaItems = section.querySelectorAll('.harmat-about-meta-item');
    if (metaItems.length > 1) {
      setText(metaItems[1].querySelector('span'), 'Energetikai besorolás');
      setText(metaItems[1].querySelector('strong'), 'A');
    }

    var badges = section.querySelector('.harmat-visual-badges');
    if (badges) {
      badges.innerHTML = '<span>24 órás zárt lakópark</span><span>75% zöldfelület</span><span>Hőszivattyús fűtés-hűtés</span>';
    }

    var list = section.querySelector('.harmat-about-list');
    if (list) {
      list.innerHTML = '<li>124 lakás az első ütemben</li><li>hőszivattyús fűtés-hűtés</li><li>75% zöldfelület</li><li>mélygarázs és tárolók</li>';
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
