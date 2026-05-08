(function () {
  "use strict";

  var SECTION_HINTS = [
    "fedezze fel a környéket",
    "a harmat lakópark környezete",
    "környéket",
  ];

  function normalize(value) {
    return (value || "").toLowerCase().replace(/\s+/g, " ").trim();
  }

  function findMapSection() {
    var sections = Array.from(document.querySelectorAll("section, .elementor-section, .vc_section, div"));
    for (var i = 0; i < sections.length; i++) {
      var text = normalize(sections[i].textContent);
      var hit = SECTION_HINTS.some(function (hint) {
        return text.indexOf(hint) !== -1;
      });
      if (hit && sections[i].querySelector("iframe, .leaflet-container, #map")) {
        return sections[i];
      }
    }
    return null;
  }

  function collectPoiLines(section) {
    var allLines = normalize(section.textContent).split(/\n+/);
    var cleaned = [];

    for (var i = 0; i < allLines.length; i++) {
      var line = allLines[i].trim();
      if (!line || line.length < 3 || line.length > 80) {
        continue;
      }

      if (
        line.indexOf("harmat lakópark") !== -1 ||
        line === "+ -" ||
        line.indexOf("válassza ki") !== -1
      ) {
        continue;
      }

      if (/^\d+(\.\d+)?$/.test(line)) {
        continue;
      }

      cleaned.push(line);
    }

    // Remove duplicates while preserving order.
    return cleaned.filter(function (item, idx) {
      return cleaned.indexOf(item) === idx;
    }).slice(0, 30);
  }

  function classifyPoi(name) {
    var n = normalize(name);
    if (/mall|ikea|lidl|market|központ|bevásárl/i.test(n)) return "shopping";
    if (/kórház|orvos|egészség|rendelő/i.test(n)) return "health";
    if (/óvoda|iskola|bölcsőde|nursery/i.test(n)) return "education";
    if (/vasút|busz|keleti|kispest|közlekedés/i.test(n)) return "transport";
    if (/park|kert|liget|természet/i.test(n)) return "nature";
    if (/vendéglő|grill|café|etterem|étterem/i.test(n)) return "food";
    return "other";
  }

  function labelForCategory(key) {
    var labels = {
      all: "Összes",
      transport: "Közlekedés",
      shopping: "Bevásárlás",
      health: "Egészségügy",
      education: "Oktatás",
      nature: "Természet",
      food: "Éttermek",
      other: "Egyéb"
    };
    return labels[key] || "Egyéb";
  }

  function mockTravelMeta(index) {
    var km = (0.8 + (index % 9) * 0.4).toFixed(1);
    var mins = 4 + (index % 9) * 2;
    return km + " km · kb. " + mins + " perc";
  }

  function buildUI(section, pois) {
    if (!pois.length || section.classList.contains("h22-map-enhanced")) {
      return;
    }

    section.classList.add("h22-map-enhanced");

    var mapNode = section.querySelector("iframe, .leaflet-container, #map");
    if (!mapNode) {
      return;
    }

    var mapWrapper = document.createElement("div");
    mapWrapper.className = "h22-map-canvas";
    mapNode.parentNode.insertBefore(mapWrapper, mapNode);
    mapWrapper.appendChild(mapNode);

    var shell = document.createElement("div");
    shell.className = "h22-map-shell";

    var controls = document.createElement("aside");
    controls.className = "h22-map-controls";
    controls.innerHTML =
      '<h3 class="h22-map-controls-title">Közeli helyek, egyszerűbben</h3>' +
      '<div class="h22-map-chip-row"></div>' +
      '<ul class="h22-map-list"></ul>';

    var list = controls.querySelector(".h22-map-list");
    var chips = controls.querySelector(".h22-map-chip-row");

    var model = pois.map(function (name, idx) {
      return {
        name: name,
        category: classifyPoi(name),
        meta: mockTravelMeta(idx),
      };
    });

    function renderList(category) {
      list.innerHTML = "";
      model
        .filter(function (item) {
          return category === "all" || item.category === category;
        })
        .forEach(function (item) {
          var li = document.createElement("li");
          li.className = "h22-map-item";
          li.innerHTML =
            '<p class="h22-map-item-name">' + item.name + "</p>" +
            '<p class="h22-map-item-meta">' + item.meta + "</p>";
          list.appendChild(li);
        });
    }

    function setActiveChip(target) {
      Array.from(chips.querySelectorAll(".h22-map-chip")).forEach(function (chip) {
        chip.classList.remove("is-active");
      });
      target.classList.add("is-active");
    }

    var categories = ["all", "transport", "shopping", "health", "education", "nature", "food", "other"];
    categories.forEach(function (category, idx) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "h22-map-chip" + (idx === 0 ? " is-active" : "");
      button.textContent = labelForCategory(category);
      button.addEventListener("click", function () {
        setActiveChip(button);
        renderList(category);
      });
      chips.appendChild(button);
    });

    renderList("all");

    shell.appendChild(controls);
    shell.appendChild(mapWrapper);

    mapWrapper.parentNode.insertBefore(shell, mapWrapper);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var section = findMapSection();
    if (!section) {
      return;
    }
    var pois = collectPoiLines(section);
    buildUI(section, pois);
  });
})();
