/* Isolated review UI. No production forms, customer data, trackers or API calls. */
(() => {
  'use strict';
  const root = document.getElementById('harmat-neighborhood-practical');
  if (!root || root.dataset.booted) return;
  root.dataset.booted = 'true';
  const byId = id => root.querySelector('#hn-' + id);
  const assets = {"panorama-f.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg","panorama-r.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_r.jpg","panorama-b.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_b.jpg","panorama-l.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_l.jpg","panorama-u.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_u.jpg","panorama-d.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_d.jpg","panorama-gallery-0.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_0.jpg","panorama-gallery-1.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_1.jpg","panorama-gallery-4.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_4.jpg","panorama-gallery-8.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_8.jpg","panorama-gallery-10.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_10.jpg","panorama-gallery-5.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_5.jpg","panorama-overview.jpg":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/video_swsp_xmsp.jpg","panorama-project-video.mp4":"https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/swsp_xmsp.mp4","ohegy-park.jpg":"https://harmat22.hu/wp-content/mu-plugins/harmat-home-family/ohegy-park-2024-original.jpg","hero-video.mp4":"https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4"};
  const data = JSON.parse(byId('facility-data').textContent);
  const list = byId('place-list');
  const search = byId('place-search');
  const select = byId('category-select');
  const filters = byId('category-buttons');
  const categories = [{ id: 'all', hu: 'Minden hely', zh: '全部配套', icon: 'list' }, ...data.categories];
  let language = 'hu';
  let category = 'all';
  const text = (hu, zh) => language === 'hu' ? hu : zh;
  const make = (tag, className, content) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (content !== undefined) node.textContent = content;
    return node;
  };
  const icon = name => {
    const node = make('i');
    node.setAttribute('data-hn-lucide', name);
    node.setAttribute('aria-hidden', 'true');
    return node;
  };
  const icons = () => {
    window.HarmatNeighborhoodIcons?.createIcons({ nameAttr: 'data-hn-lucide' });
    root.querySelectorAll('[data-lucide]').forEach(node => node.removeAttribute('data-lucide'));
  };
  const normalized = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

  function setCategory(value) {
    category = value;
    select.value = value;
    filters.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.category === value)));
    renderPlaces();
  }

  function renderFilters() {
    filters.replaceChildren();
    select.replaceChildren();
    categories.forEach(group => {
      const label = text(group.hu, group.zh);
      const count = group.id === 'all' ? data.places.length : data.places.filter(p => p.category === group.id).length;
      const button = make('button', 'category-button');
      button.type = 'button';
      button.dataset.category = group.id;
      button.setAttribute('aria-pressed', String(category === group.id));
      button.append(icon(group.icon), make('span', '', label), make('span', 'category-count', String(count)));
      button.addEventListener('click', () => setCategory(group.id));
      filters.append(button);
      const option = make('option', '', `${label} (${count})`);
      option.value = group.id;
      select.append(option);
    });
    select.value = category;
  }

  function renderPlaces() {
    const query = normalized(search.value.trim());
    const places = data.places.filter(place => (category === 'all' || place.category === category)
      && normalized([place.name, place.zh, place.descriptionHu, place.descriptionZh].join(' ')).includes(query));
    list.replaceChildren();
    byId('result-count').textContent = text(`${places.length} hely és szolgáltatás`, `${places.length}项配套与服务`);
    byId('empty-state').hidden = places.length > 0;
    data.categories.forEach(group => {
      const subset = places.filter(place => place.category === group.id);
      if (!subset.length) return;
      const section = make('section', 'place-group');
      section.dataset.group = group.id;
      const heading = make('h3', '', text(group.hu, group.zh));
      heading.id = `group-${group.id}`;
      section.setAttribute('aria-labelledby', heading.id);
      section.append(heading);
      subset.forEach(place => {
        const row = make('article', 'place-row');
        row.dataset.place = place.id;
        const copy = make('div');
        copy.append(make('h4', 'place-name', text(place.name, place.zh)),
          make('p', 'place-description', text(place.descriptionHu, place.descriptionZh)));
        const distance = language === 'hu' ? place.distance : place.distance.replace(/^kb\. /, '约').replace(',', '.').replace('közelben', '附近');
        const link = make('a', 'map-link');
        const url = new URL('https://www.google.com/maps/search/');
        url.searchParams.set('api', '1');
        const queryPlace = place.id === '1' || place.id === '17' ? place.name + ', Harmat utca 22, Budapest'
          : place.name.includes('Budapest') ? place.name : place.name + ', Budapest';
        url.searchParams.set('query', queryPlace);
        link.href = url.href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.title = text(`Térkép: ${place.name}`, `在地图中查看：${place.zh}`);
        link.setAttribute('aria-label', link.title);
        link.append(icon('arrow-up-right'));
        row.append(copy, make('span', 'place-distance', distance), link);
        section.append(row);
      });
      list.append(section);
    });
    icons();
  }

  function changeLanguage(value) {
    language = value;
    root.lang = value === 'hu' ? 'hu' : 'zh-Hans';
    root.querySelectorAll('[data-hu]').forEach(node => { node.textContent = node.dataset[value]; });
    root.querySelectorAll('[data-alt-hu]').forEach(node => { node.alt = node.getAttribute('data-alt-' + value); });
    root.querySelectorAll('[data-title-hu]').forEach(node => {
      node.title = node.getAttribute('data-title-' + value);
      node.setAttribute('aria-label', node.title);
    });
    search.placeholder = search.getAttribute('data-placeholder-' + value);
    search.setAttribute('aria-label', search.placeholder);
    root.querySelectorAll('[data-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.language === value)));
    renderFilters();
    renderPlaces();
    renderGallery();
    updateImage();
    updateStatus();
    updateFullscreenControl();
  }

  search.addEventListener('input', renderPlaces);
  select.addEventListener('change', () => setCategory(select.value));
  byId('reset-filters').addEventListener('click', () => { search.value = ''; setCategory('all'); });
  root.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => changeLanguage(button.dataset.language)));

  const details = byId('project-view');
  const host = byId('panorama');
  const shell = byId('scene-shell');
  const rotate = byId('rotate');
  const fullscreen = byId('fullscreen');
  const status = byId('viewer-status');
  const tabs = [...root.querySelectorAll('[role=tab][data-panel]')];
  let viewer;
  let activePanel = 'panorama';
  let wantedRotation = false;
  let inView = true;
  let timer;
  let rotating = false;

  function updateStatus() {
    status.textContent = host.dataset.state === 'error'
      ? text('A 360° nézet nem tölthető be. A projekt látványterve látható.', '360°全景暂不可用，当前显示项目效果图。')
      : host.dataset.state === 'loading' ? text('360° betöltése…', '正在加载360°全景…') : '';
  }
  function failViewer() {
    clearTimeout(timer);
    host.dataset.state = 'error';
    host.hidden = true;
    byId('panorama-fallback').hidden = false;
    if (viewer) { viewer.stopAutoRotate(); viewer.stopMovement(); }
    wantedRotation = rotating = false;
    rotate.setAttribute('aria-pressed', 'false');
    updateStatus();
  }
  function syncRotation() {
    const next = Boolean(viewer && host.dataset.state === 'loaded' && wantedRotation && inView
      && details.open && activePanel === 'panorama' && !document.hidden && !byId('lightbox').open);
    if (viewer && next !== rotating) {
      if (next) viewer.startAutoRotate(-1);
      else { viewer.stopAutoRotate(); viewer.stopMovement(); }
    }
    rotating = next;
    rotate.setAttribute('aria-pressed', String(wantedRotation));
  }
  function ensureViewer() {
    if (host.dataset.state !== 'idle') { viewer?.resize(); return; }
    host.dataset.state = 'loading';
    updateStatus();
    try {
      viewer = window.pannellum.viewer('hn-panorama', {
        type: 'cubemap', cubeMap: ['f', 'r', 'b', 'l', 'u', 'd'].map(face => assets[`panorama-${face}.jpg`]),
        autoLoad: true, autoRotate: false, showControls: false, showFullscreenCtrl: false,
        mouseZoom: false, doubleClickZoom: false, hfov: 100, pitch: -9, yaw: 0, minHfov: 45, maxHfov: 112,
        crossOrigin: 'anonymous', autoRotateInactivityDelay: -1,
        strings: { loadingLabel: text('Betöltés…', '加载中…') }
      });
      viewer.on('load', () => {
        if (host.dataset.state === 'error') { viewer.destroy(); return; }
        clearTimeout(timer);
        host.dataset.state = 'loaded';
        byId('panorama-fallback').hidden = true;
        viewer.resize();
        updateStatus();
        syncRotation();
      });
      viewer.on('error', failViewer);
      timer = setTimeout(failViewer, 25000);
    } catch { failViewer(); }
  }
  function stopVideos() { root.querySelectorAll('video').forEach(video => video.pause()); }
  function showPanel(name) {
    activePanel = name;
    tabs.forEach(tab => {
      const selected = tab.dataset.panel === name;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      byId('panel-' + tab.dataset.panel).hidden = !selected;
    });
    stopVideos();
    if (name === 'panorama' && details.open) ensureViewer();
    syncRotation();
  }
  details.addEventListener('toggle', () => {
    if (details.open && activePanel === 'panorama') ensureViewer();
    if (!details.open) stopVideos();
    syncRotation();
  });
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => showPanel(tab.dataset.panel));
    tab.addEventListener('keydown', event => {
      const offsets = { ArrowRight: 1, ArrowLeft: -1 };
      let next;
      if (event.key in offsets) next = (index + offsets[event.key] + tabs.length) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      if (next === undefined) return;
      event.preventDefault();
      showPanel(tabs[next].dataset.panel);
      tabs[next].focus();
    });
  });
  rotate.addEventListener('click', () => { wantedRotation = !wantedRotation; syncRotation(); });
  byId('zoom-in').addEventListener('click', () => viewer?.setHfov(Math.max(45, viewer.getHfov() - 12), 250));
  byId('zoom-out').addEventListener('click', () => viewer?.setHfov(Math.min(112, viewer.getHfov() + 12), 250));
  byId('reset-view').addEventListener('click', () => { wantedRotation = false; syncRotation(); viewer?.lookAt(-9, 0, 100, 400); });
  host.addEventListener('pointerdown', () => { wantedRotation = false; syncRotation(); });
  function updateFullscreenControl() {
    const full = document.fullscreenElement === shell;
    fullscreen.title = full ? text('Kilépés a teljes képernyőből', '退出全屏') : text('Teljes képernyő', '全屏');
    fullscreen.setAttribute('aria-label', fullscreen.title);
    fullscreen.replaceChildren(icon(full ? 'minimize' : 'maximize'));
    icons();
  }
  fullscreen.addEventListener('click', async () => {
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else await shell.requestFullscreen();
    } catch { status.textContent = text('A teljes képernyő nem érhető el.', '当前无法进入全屏。'); }
  });
  document.addEventListener('fullscreenchange', () => { updateFullscreenControl(); viewer?.resize(); });
  document.addEventListener('visibilitychange', syncRotation);
  if ('ResizeObserver' in window) new ResizeObserver(() => viewer?.resize()).observe(shell);
  if ('IntersectionObserver' in window) new IntersectionObserver(entries => {
    inView = entries[0].isIntersecting;
    syncRotation();
  }).observe(shell);

  const galleryFiles = [0, 1, 4, 5, 8, 10];
  const galleryHu = ['Lakópark áttekintése', 'Épülethomlokzat', 'Épületek közötti terek', 'Kert és közösségi tér', 'Madártávlati nézet', 'Épületrészlet'];
  const galleryZh = ['项目整体', '建筑外观', '楼宇之间的空间', '花园与公共空间', '鸟瞰视角', '建筑细节'];
  const dialog = byId('lightbox');
  let imageIndex = 0;
  let returnFocus;
  function updateImage() {
    const name = text(galleryHu[imageIndex], galleryZh[imageIndex]);
    const image = byId('lightbox-image');
    image.src = assets[`panorama-gallery-${galleryFiles[imageIndex]}.jpg`];
    image.alt = name + text(' · látványterv', ' · 效果图');
    byId('image-caption').textContent = `${imageIndex + 1} / 6 · ${name}`;
  }
  function renderGallery() {
    const target = byId('gallery');
    target.replaceChildren();
    galleryFiles.forEach((file, index) => {
      const button = make('button');
      button.type = 'button';
      const name = text(galleryHu[index], galleryZh[index]);
      button.setAttribute('aria-label', name);
      const image = make('img');
      image.src = assets[`panorama-gallery-${file}.jpg`];
      image.loading = 'lazy';
      image.alt = name + text(' · látványterv', ' · 效果图');
      button.append(image, make('span', '', name));
      button.addEventListener('click', () => {
        imageIndex = index;
        returnFocus = button;
        updateImage();
        dialog.showModal();
        syncRotation();
      });
      target.append(button);
    });
  }
  byId('close-lightbox').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { returnFocus?.focus(); syncRotation(); });
  byId('previous-image').addEventListener('click', () => { imageIndex = (imageIndex + 5) % 6; updateImage(); });
  byId('next-image').addEventListener('click', () => { imageIndex = (imageIndex + 1) % 6; updateImage(); });
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      imageIndex = (imageIndex + (event.key === 'ArrowRight' ? 1 : 5)) % 6;
      updateImage();
    }
  });
  let appliedLanguage;
  function syncLanguage() {
    const next = document.documentElement.getAttribute('data-harmat-language') === 'zh-Hans' ? 'zh' : 'hu';
    if (appliedLanguage === next) return;
    appliedLanguage = next;
    changeLanguage(next);
  }
  new MutationObserver(syncLanguage).observe(document.documentElement, {
    attributes: true, attributeFilter: ['data-harmat-language']
  });
  syncLanguage();
  icons();
})();
