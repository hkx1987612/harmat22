<?php
/**
 * Plugin Name: Harmat Reserved Virtual Apartment View
 * Description: Keeps reserved apartments viewable from the virtual selector without changing sales availability.
 * Version: 1.3.0
 */

defined('ABSPATH') || exit;

function harmat_reserved_virtual_view_is_selector(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return false;
    }

    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    return strpos($path, 'virtualis-lakasvalaszto') === 0;
}

add_action('wp_head', function (): void {
    if (!harmat_reserved_virtual_view_is_selector()) {
        return;
    }
    ?>
<style id="harmat-reserved-virtual-view-style">
.apt-card.status-reserved .apt-btn-modern[data-harmat-reserved-view="1"] {
  background: #8a5a18;
  color: #fff;
  cursor: pointer;
  pointer-events: auto;
}
.apt-card.status-reserved .apt-btn-modern[data-harmat-reserved-view="1"]:hover,
.apt-card.status-reserved .apt-btn-modern[data-harmat-reserved-view="1"]:focus-visible {
  background: #6f4611;
  color: #fff;
}
</style>
    <?php
}, 110);

add_action('wp_footer', function (): void {
    if (!harmat_reserved_virtual_view_is_selector()) {
        return;
    }
    ?>
<script id="harmat-reserved-virtual-view-js">
(function () {
  "use strict";

  if (window.__harmatReservedVirtualViewReady) return;
  window.__harmatReservedVirtualViewReady = true;

  function normalized(value) {
    return String(value || "").toLowerCase().replace(/[_ ]/g, "-");
  }

  function apartmentData() {
    return window.LakasparkData && window.LakasparkData.apartments
      ? window.LakasparkData.apartments
      : {};
  }

  function apartmentById(id) {
    var apartments = apartmentData();
    var match = null;

    if (!id) return null;
    if (apartments[id]) return apartments[id];

    Object.keys(apartments).some(function (candidateId) {
      if (normalized(candidateId) !== normalized(id)) return false;
      match = apartments[candidateId];
      return true;
    });

    return match;
  }

  function apartmentFor(card) {
    return apartmentById(card.getAttribute("data-id") || "");
  }

  function currentBuilding() {
    var match = window.location.pathname.match(/virtualis-lakasvalaszto-(a[1-4])-epulet/i);
    return match ? match[1].toUpperCase() : "";
  }

  function floorLabel(value) {
    value = String(value || "").trim();
    return !value || /^(?:f|fsz|0)$/i.test(value) ? "Fsz" : value;
  }

  function matchesFilters(apartment) {
    var rooms = document.getElementById("filterRooms");
    var floor = document.querySelector(".floor-btn.active");
    var selectedRooms = rooms ? String(rooms.value || "all") : "all";
    var selectedFloor = floor ? String(floor.getAttribute("data-val") || "all") : "all";

    return (selectedRooms === "all" || String(apartment.rooms || "") === selectedRooms)
      && (selectedFloor === "all" || floorLabel(apartment.floor).toUpperCase() === selectedFloor.toUpperCase());
  }

  function reservedApartments() {
    var apartments = apartmentData();
    var building = currentBuilding();

    if (!building) return [];

    return Object.keys(apartments).map(function (id) {
      return {id: id, data: apartments[id]};
    }).filter(function (item) {
      return item.data
        && item.data.status === "reserved"
        && item.id.toUpperCase().indexOf(building + "-") === 0
        && matchesFilters(item.data);
    });
  }

  function createReservedCard(id, apartment) {
    var card = document.createElement("div");
    var image = apartment.image
      ? '<img src="' + apartment.image + '" class="apt-card-img" alt="' + id + ' alaprajza" loading="lazy" decoding="async">'
      : '<div class="apt-card-img">Nincs k\u00e9p</div>';
    var floor = floorLabel(apartment.floor);
    var floorText = floor === "Fsz" ? floor : floor + " em.";

    card.className = "apt-card status-reserved";
    card.setAttribute("data-id", id);
    card.setAttribute("data-harmat-reserved-generated", "1");
    card.innerHTML = image
      + '<div class="apt-card-content">'
      + '<div class="apt-card-header"><h3>' + id + '</h3><div class="apt-price">Foglalva</div></div>'
      + '<div class="apt-meta-grid">'
      + '<div class="meta-item">' + (apartment.rooms || "-") + ' szoba</div>'
      + '<div class="meta-item">' + floorText + '</div>'
      + '<div class="meta-item">' + (apartment.b_area || "-") + ' m\u00b2</div>'
      + '</div>'
      + '<a href="' + apartment.link + '" class="apt-btn-modern">Adatlap</a>'
      + '</div>';

    return card;
  }

  function repairCard(card) {
    if (!card || !card.classList.contains("status-reserved")) return;

    var apartment = apartmentFor(card);
    var link = card.querySelector("a.apt-btn-modern");
    if (!apartment || apartment.status !== "reserved" || !apartment.link || !link) return;

    link.href = apartment.link;
    link.setAttribute("data-harmat-reserved-view", "1");
    link.setAttribute("aria-label", apartment.name + " adatlap megnyit\u00e1sa");
    link.removeAttribute("aria-disabled");
    link.removeAttribute("tabindex");
  }

  function repairHitbox(path) {
    if (!path || !path.classList.contains("status-reserved")) return;
    if (path.getAttribute("data-harmat-reserved-view") === "1") return;

    var apartment = apartmentById(path.getAttribute("data-id") || "");
    if (!apartment || apartment.status !== "reserved" || !apartment.link) return;

    path.setAttribute("data-harmat-reserved-view", "1");
    path.setAttribute("role", "link");
    path.setAttribute("aria-label", apartment.name + " adatlap megnyit\u00e1sa");
  }

  function repairHitboxes(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope.matches && scope.matches(".hitbox-polygon.status-reserved")) {
      repairHitbox(scope);
    }
    scope.querySelectorAll(".hitbox-polygon.status-reserved").forEach(repairHitbox);
  }

  var repairBusy = false;
  var repairTimer = 0;

  function synchronize(list) {
    if (repairBusy || !list) return;
    repairBusy = true;

    list.querySelectorAll(".apt-card.status-reserved").forEach(repairCard);

    if (!document.body.classList.contains("harmat-virtual-has-selection")) {
      var existing = {};
      list.querySelectorAll(".apt-card[data-id]").forEach(function (card) {
        existing[normalized(card.getAttribute("data-id"))] = true;
      });

      reservedApartments().forEach(function (item) {
        if (existing[normalized(item.id)]) return;
        var card = createReservedCard(item.id, item.data);
        list.appendChild(card);
        repairCard(card);
      });

      var count = document.getElementById("resultCount");
      var total = list.querySelectorAll(".apt-card").length;
      if (count) count.textContent = String(total);
      list.classList.toggle("is-empty", total === 0);
    }

    repairBusy = false;
  }

  function schedule(list) {
    if (repairTimer) window.clearTimeout(repairTimer);
    repairTimer = window.setTimeout(function () {
      repairTimer = 0;
      synchronize(list);
    }, 40);
  }

  function boot() {
    var list = document.getElementById("apartmentList");
    var hitboxLayer = document.getElementById("hitboxLayer");
    if (!list) return;

    function openReservedHitbox(event) {
      if (event.type === "keydown" && event.key !== "Enter" && event.key !== " ") return;

      var target = event.target && event.target.closest
        ? event.target.closest(".hitbox-polygon.status-reserved")
        : null;
      var apartment = target ? apartmentById(target.getAttribute("data-id") || "") : null;
      if (!apartment || apartment.status !== "reserved" || !apartment.link) return;

      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) event.stopImmediatePropagation();
      window.location.href = apartment.link;
    }

    document.addEventListener("click", openReservedHitbox, true);
    document.addEventListener("keydown", openReservedHitbox, true);

    schedule(list);
    repairHitboxes(hitboxLayer || document);
    if (!window.MutationObserver) return;

    new MutationObserver(function () {
      schedule(list);
    }).observe(list, {childList: true, subtree: true});

    if (hitboxLayer) {
      new MutationObserver(function (records) {
        records.forEach(function (record) {
          record.addedNodes.forEach(function (node) {
            if (node.nodeType === 1) repairHitboxes(node);
          });
        });
      }).observe(hitboxLayer, {childList: true, subtree: true});
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, {once: true});
  } else {
    boot();
  }
}());
</script>
    <?php
}, 120);
