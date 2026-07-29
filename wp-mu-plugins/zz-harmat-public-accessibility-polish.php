<?php
/**
 * Plugin Name: Harmat Public Accessibility Polish
 * Description: Adds missing Hungarian accessible names to icon-only public header links.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_public_accessibility_polish_is_public(): bool
{
    return !is_admin() && !wp_doing_ajax() && !wp_is_json_request();
}

add_action('wp_footer', function (): void {
    if (!harmat_public_accessibility_polish_is_public()) {
        return;
    }
    ?>
<script id="harmat-public-accessibility-polish-js">
(function () {
  "use strict";

  function hasAccessibleName(link) {
    if ((link.getAttribute("aria-label") || "").trim()) return true;
    if ((link.getAttribute("title") || "").trim()) return true;
    if ((link.textContent || "").trim()) return true;
    if (link.querySelector("img[alt]:not([alt=''])")) return true;
    return Boolean(link.getAttribute("aria-labelledby"));
  }

  function labelFor(link) {
    var href = (link.getAttribute("href") || "").toLowerCase();
    if (href.indexOf("tel:") === 0) return "Telefonos kapcsolat";
    if (href.indexOf("mailto:") === 0) return "E-mail k\u00fcld\u00e9se";
    if (href.indexOf("facebook.com") !== -1) return "Harmat Lak\u00f3park a Facebookon";
    if (href.indexOf("instagram.com") !== -1) return "Harmat Lak\u00f3park az Instagramon";
    return "Navig\u00e1ci\u00f3 megnyit\u00e1sa";
  }

  function repair(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll("header a.elementor-icon").forEach(function (link) {
      if (!hasAccessibleName(link)) {
        link.setAttribute("aria-label", labelFor(link));
      }
    });
  }

  function boot() {
    repair(document);
    var header = document.querySelector("header");
    if (!header || !window.MutationObserver) return;
    new MutationObserver(function () {
      repair(header);
    }).observe(header, {childList:true,subtree:true});
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, {once:true});
  } else {
    boot();
  }
}());
</script>
    <?php
}, 110);
