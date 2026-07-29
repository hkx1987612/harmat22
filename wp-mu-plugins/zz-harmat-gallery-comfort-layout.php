<?php
/**
 * Plugin Name: Harmat Gallery Comfort Layout
 * Description: Rebuilds the public gallery page into a stable desktop and mobile image grid.
 * Version: 1.1.0
 */

defined('ABSPATH') || exit;

function harmat_gallery_comfort_is_gallery_page() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
    return is_page('galeria') || $path === 'galeria';
}

function harmat_gallery_comfort_body_class($classes) {
    if (harmat_gallery_comfort_is_gallery_page()) {
        $classes[] = 'harmat-gallery-comfort';
    }

    return $classes;
}
add_filter('body_class', 'harmat_gallery_comfort_body_class', 99);

function harmat_gallery_comfort_head_styles() {
    if (!harmat_gallery_comfort_is_gallery_page()) {
        return;
    }
    ?>
<style id="harmat-gallery-comfort-layout-css">
body.harmat-gallery-comfort,
body.harmat-gallery-comfort #page,
body.harmat-gallery-comfort .site-content,
body.harmat-gallery-comfort .entry-content,
body.harmat-gallery-comfort .elementor,
body.harmat-gallery-comfort .elementor-section,
body.harmat-gallery-comfort .elementor-container,
body.harmat-gallery-comfort .elementor-widget-wrap {
  max-width: 100vw !important;
  overflow-x: hidden !important;
}
body.harmat-gallery-comfort .elementor-widget-opal-image-gallery,
body.harmat-gallery-comfort .elementor-element-4b8fe87 {
  width: min(100%, 1240px) !important;
  max-width: calc(100vw - 48px) !important;
  margin-right: auto !important;
  margin-left: auto !important;
}
body.harmat-gallery-comfort .elementor-widget-opal-image-gallery .elementor-widget-container {
  width: 100% !important;
}
body.harmat-gallery-comfort .elementor-galerry__filters {
  display: flex !important;
  flex-wrap: wrap !important;
  justify-content: center !important;
  align-items: center !important;
  gap: 10px !important;
  width: 100% !important;
  margin: 0 auto 28px !important;
  padding: 0 !important;
}
body.harmat-gallery-comfort .elementor-galerry__filter {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-width: 126px !important;
  min-height: 42px !important;
  margin: 0 !important;
  padding: 0 18px !important;
  border: 1px solid rgba(166, 112, 38, .34) !important;
  background: #fff !important;
  color: #1f2d32 !important;
  box-shadow: none !important;
  font-size: 13px !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
  letter-spacing: .03em !important;
  text-align: center !important;
  white-space: normal !important;
  cursor: pointer !important;
}
body.harmat-gallery-comfort .elementor-galerry__filter.elementor-active {
  background: #a97026 !important;
  border-color: #a97026 !important;
  color: #fff !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery {
  display: block !important;
  width: 100% !important;
  max-width: 100% !important;
  height: auto !important;
  min-height: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  overflow: visible !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery .row.grid,
body.harmat-gallery-comfort .elementor-opal-image-gallery .isotope-grid,
body.harmat-gallery-comfort .elementor-opal-image-gallery .gallery-visibility {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  align-items: stretch !important;
  gap: 24px !important;
  width: 100% !important;
  max-width: 100% !important;
  height: auto !important;
  min-height: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  position: relative !important;
  left: auto !important;
  right: auto !important;
  top: auto !important;
  bottom: auto !important;
  transform: none !important;
  overflow: visible !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery .column-item,
body.harmat-gallery-comfort .elementor-opal-image-gallery .grid__item,
body.harmat-gallery-comfort .elementor-opal-image-gallery .masonry-item {
  position: relative !important;
  left: auto !important;
  right: auto !important;
  top: auto !important;
  bottom: auto !important;
  transform: none !important;
  float: none !important;
  clear: none !important;
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
  height: auto !important;
  min-height: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery .column-item > a,
body.harmat-gallery-comfort .elementor-opal-image-gallery .grid__item > a,
body.harmat-gallery-comfort .elementor-opal-image-gallery .masonry-item > a {
  display: block !important;
  width: 100% !important;
  height: auto !important;
  aspect-ratio: 3 / 2 !important;
  overflow: hidden !important;
  background: #f7efe2 !important;
  border: 1px solid rgba(166, 112, 38, .16) !important;
  box-shadow: 0 16px 34px rgba(35, 46, 50, .08) !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery img {
  display: block !important;
  width: 100% !important;
  max-width: 100% !important;
  height: 100% !important;
  min-height: 0 !important;
  margin: 0 !important;
  object-fit: cover !important;
  object-position: center center !important;
  opacity: 1 !important;
  visibility: visible !important;
  transition: transform .35s ease !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery .column-item > a:hover img {
  transform: scale(1.025) !important;
}
body.harmat-gallery-comfort .elementor-opal-image-gallery .gallery-item-overlay,
body.harmat-gallery-comfort .elementor-opal-image-gallery .opal-icon-zoom {
  pointer-events: none !important;
}
@media (max-width: 860px) {
  body.harmat-gallery-comfort .elementor-widget-opal-image-gallery,
  body.harmat-gallery-comfort .elementor-element-4b8fe87 {
    max-width: calc(100vw - 24px) !important;
  }
  body.harmat-gallery-comfort .elementor-galerry__filters {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 8px !important;
    margin-bottom: 18px !important;
  }
  body.harmat-gallery-comfort .elementor-galerry__filter {
    min-width: 0 !important;
    min-height: 42px !important;
    padding: 0 8px !important;
    font-size: 12px !important;
  }
  body.harmat-gallery-comfort .elementor-opal-image-gallery .row.grid,
  body.harmat-gallery-comfort .elementor-opal-image-gallery .isotope-grid,
  body.harmat-gallery-comfort .elementor-opal-image-gallery .gallery-visibility {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
}
</style>
    <?php
}
add_action('wp_head', 'harmat_gallery_comfort_head_styles', 9999);

function harmat_gallery_comfort_footer_script() {
    if (!harmat_gallery_comfort_is_gallery_page()) {
        return;
    }
    ?>
<script id="harmat-gallery-comfort-layout-js">
(function () {
  var scheduled = false;
  var observerStarted = false;
  var imageObserver = null;

  function isGalleryPage() {
    return document.body && document.body.classList.contains('harmat-gallery-comfort');
  }

  function setImportant(node, property, value) {
    if (!node || !node.style) return;
    if (node.style.getPropertyValue(property) === value && node.style.getPropertyPriority(property) === 'important') return;
    node.style.setProperty(property, value, 'important');
  }

  function getLinkedImageSource(img) {
    var link = img.closest ? img.closest('a[href]') : null;
    var href = link ? (link.getAttribute('href') || '') : '';
    if (/\.(jpe?g|png|webp|gif)(\?|#|$)/i.test(href)) {
      return href;
    }
    return '';
  }

  function getRealImageSource(img) {
    return getLinkedImageSource(img) ||
      img.getAttribute('data-src') ||
      img.getAttribute('data-lazy-src') ||
      img.getAttribute('data-original') ||
      img.getAttribute('data-url') ||
      '';
  }

  function isPlaceholder(src) {
    return !src || src.indexOf('data:image/svg') === 0 || src.indexOf('blank.gif') !== -1;
  }

  function isSmallGeneratedSize(src) {
    return /-\d{2,4}x\d{2,4}\.(jpe?g|png|webp|gif)(\?|#|$)/i.test(src || '');
  }

  function fullSourceFor(img) {
    var existing = img.getAttribute('data-harmat-gallery-full-src') || '';
    if (existing) return existing;

    var source = getLinkedImageSource(img);
    if (source) {
      img.setAttribute('data-harmat-gallery-full-src', source);
    }
    return source;
  }

  function derivativeSource(source, width) {
    if (!source || !/\.jpe?g(\?|#|$)/i.test(source)) return '';
    try {
      var url = new URL(source, window.location.href);
      url.pathname = url.pathname.replace(/\.jpe?g$/i, '-harmat-' + width + '.webp');
      return url.href;
    } catch (error) {
      return source.replace(/\.jpe?g(\?|#|$)/i, '-harmat-' + width + '.webp$1');
    }
  }

  function upgradeImage(img, priority) {
    if (!img || img.getAttribute('data-harmat-gallery-hd') === '1') return;
    var fullSource = fullSourceFor(img);
    if (!fullSource) return;

    var source640 = derivativeSource(fullSource, 640);
    var source1280 = derivativeSource(fullSource, 1280);
    var selectedSource = window.innerWidth <= 860 ? source640 : source1280;
    if (!source640 || !source1280 || !selectedSource) {
      selectedSource = fullSource;
    }

    if (selectedSource !== fullSource) {
      var sourceSet = source640 + ' 640w, ' + source1280 + ' 1280w';
      img.setAttribute('srcset', sourceSet);
      img.setAttribute('data-srcset', sourceSet);
      img.setAttribute('sizes', '(max-width: 860px) calc(100vw - 24px), 606px');
      if (img.getAttribute('data-harmat-gallery-fallback-bound') !== '1') {
        img.setAttribute('data-harmat-gallery-fallback-bound', '1');
        img.addEventListener('error', function () {
          if (img.getAttribute('data-harmat-gallery-fallback-used') === '1') return;
          img.setAttribute('data-harmat-gallery-fallback-used', '1');
          img.removeAttribute('srcset');
          img.removeAttribute('data-srcset');
          img.removeAttribute('sizes');
          img.setAttribute('data-src', fullSource);
          img.setAttribute('data-lazy-src', fullSource);
          img.setAttribute('src', fullSource);
        });
      }
    }

    img.setAttribute('data-src', selectedSource);
    img.setAttribute('data-lazy-src', selectedSource);
    img.setAttribute('src', selectedSource);
    img.setAttribute('data-harmat-gallery-hd', '1');
    img.setAttribute('loading', priority === 'high' ? 'eager' : 'lazy');
    img.setAttribute('fetchpriority', priority === 'high' ? 'high' : 'low');
  }

  function imageIsNearViewport(img, visibleIndex) {
    var eagerCount = window.innerWidth <= 860 ? 2 : 4;
    if (visibleIndex < eagerCount) return true;
    var rect = img.getBoundingClientRect();
    return rect.top < window.innerHeight + 700 && rect.bottom > -250;
  }

  function getImageObserver() {
    if (imageObserver || !('IntersectionObserver' in window)) return imageObserver;
    imageObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        imageObserver.unobserve(entry.target);
        upgradeImage(entry.target, 'low');
      });
    }, { rootMargin: '900px 0px' });
    return imageObserver;
  }

  function observeImage(img) {
    var observer = getImageObserver();
    if (observer) {
      observer.observe(img);
    }
  }

  function activeFilterSelector() {
    var active = document.querySelector('.elementor-galerry__filter.elementor-active');
    return active ? (active.getAttribute('data-filter') || '.__all') : '.__all';
  }

  function itemMatchesFilter(item, selector) {
    if (!selector || selector === '.__all' || selector === '*' || selector === 'all') {
      return true;
    }
    try {
      return item.matches(selector);
    } catch (error) {
      return true;
    }
  }

  function applyGalleryFilter() {
    var selector = activeFilterSelector();
    document.querySelectorAll('.elementor-opal-image-gallery .column-item').forEach(function (item) {
      setImportant(item, 'display', itemMatchesFilter(item, selector) ? 'block' : 'none');
    });
  }

  function repairImages() {
    var visibleIndex = 0;
    document.querySelectorAll('.elementor-opal-image-gallery img').forEach(function (img) {
      var src = img.getAttribute('src') || '';
      var item = img.closest ? img.closest('.column-item') : null;
      var itemHidden = item && window.getComputedStyle(item).display === 'none';
      var isUpgraded = img.getAttribute('data-harmat-gallery-hd') === '1';

      img.setAttribute('decoding', 'async');
      if (!isUpgraded) {
        img.setAttribute('loading', 'lazy');
        img.setAttribute('fetchpriority', 'low');
      }
      if (!img.getAttribute('alt')) {
        img.setAttribute('alt', 'Harmat Lak\u00f3park l\u00e1tv\u00e1nyterv');
      }

      fullSourceFor(img);

      if (!itemHidden) {
        var index = visibleIndex;
        visibleIndex += 1;
        if (imageIsNearViewport(img, index) || (fullSourceFor(img) && !isPlaceholder(src) && !isSmallGeneratedSize(src))) {
          upgradeImage(img, index === 0 ? 'high' : 'low');
        } else {
          observeImage(img);
        }
      }
      setImportant(img, 'opacity', '1');
      setImportant(img, 'visibility', 'visible');
    });
  }

  function clearMasonryStyles() {
    var columns = window.innerWidth <= 860 ? '1fr' : 'repeat(2, minmax(0, 1fr))';
    document.querySelectorAll('.elementor-opal-image-gallery .row.grid, .elementor-opal-image-gallery .isotope-grid, .elementor-opal-image-gallery .gallery-visibility').forEach(function (grid) {
      setImportant(grid, 'display', 'grid');
      setImportant(grid, 'grid-template-columns', columns);
      setImportant(grid, 'height', 'auto');
      setImportant(grid, 'min-height', '0');
      setImportant(grid, 'position', 'relative');
      setImportant(grid, 'left', 'auto');
      setImportant(grid, 'top', 'auto');
      setImportant(grid, 'right', 'auto');
      setImportant(grid, 'bottom', 'auto');
      setImportant(grid, 'transform', 'none');
      setImportant(grid, 'width', '100%');
      setImportant(grid, 'max-width', '100%');
    });

    document.querySelectorAll('.elementor-opal-image-gallery .column-item').forEach(function (item) {
      setImportant(item, 'position', 'relative');
      setImportant(item, 'left', 'auto');
      setImportant(item, 'top', 'auto');
      setImportant(item, 'right', 'auto');
      setImportant(item, 'bottom', 'auto');
      setImportant(item, 'transform', 'none');
      setImportant(item, 'float', 'none');
      setImportant(item, 'width', '100%');
      setImportant(item, 'max-width', '100%');
      setImportant(item, 'height', 'auto');
      setImportant(item, 'margin', '0');
      setImportant(item, 'padding', '0');
    });
  }

  function polishGallery() {
    if (!isGalleryPage()) return;
    document.body.classList.add('harmat-gallery-comfort-ready');
    clearMasonryStyles();
    applyGalleryFilter();
    repairImages();
  }

  function schedulePolish() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      polishGallery();
    });
  }

  function bindFilters() {
    document.querySelectorAll('.elementor-galerry__filter').forEach(function (filter) {
      if (filter.getAttribute('data-harmat-gallery-bound') === '1') return;
      filter.setAttribute('data-harmat-gallery-bound', '1');
      filter.addEventListener('click', function () {
        window.setTimeout(schedulePolish, 0);
        window.setTimeout(schedulePolish, 180);
        window.setTimeout(schedulePolish, 520);
      });
    });
  }

  function startObserver() {
    if (observerStarted || !window.MutationObserver) return;
    var gallery = document.querySelector('.elementor-opal-image-gallery');
    if (!gallery) return;
    observerStarted = true;
    new MutationObserver(schedulePolish).observe(gallery, {
      attributes: true,
      childList: true,
      subtree: true,
      attributeFilter: ['style', 'class', 'src', 'srcset']
    });
  }

  function boot() {
    if (!isGalleryPage()) return;
    bindFilters();
    polishGallery();
    startObserver();
    window.setTimeout(schedulePolish, 300);
    window.setTimeout(schedulePolish, 900);
    window.setTimeout(schedulePolish, 1800);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('load', boot);
  window.addEventListener('resize', schedulePolish);
}());
</script>
    <?php
}
add_action('wp_footer', 'harmat_gallery_comfort_footer_script', 9999);
