/* Isolated review UI. No production forms, customer data, trackers or API calls. */
(() => {
  'use strict';
  const data = JSON.parse(document.getElementById('facility-data').textContent);
  const list = document.getElementById('place-list');
  const search = document.getElementById('place-search');
  const select = document.getElementById('category-select');
  const filters = document.getElementById('category-buttons');
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
    node.dataset.lucide = name;
    node.setAttribute('aria-hidden', 'true');
    return node;
  };
  const icons = () => window.lucide?.createIcons();
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
    document.getElementById('result-count').textContent = text(`${places.length} hely és szolgáltatás`, `${places.length}项配套与服务`);
    document.getElementById('empty-state').hidden = places.length > 0;
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
    document.documentElement.lang = value === 'hu' ? 'hu' : 'zh-Hans';
    document.querySelectorAll('[data-hu]').forEach(node => { node.textContent = node.dataset[value]; });
    document.querySelectorAll('[data-alt-hu]').forEach(node => { node.alt = node.getAttribute('data-alt-' + value); });
    document.querySelectorAll('[data-title-hu]').forEach(node => {
      node.title = node.getAttribute('data-title-' + value);
      node.setAttribute('aria-label', node.title);
    });
    search.placeholder = search.getAttribute('data-placeholder-' + value);
    search.setAttribute('aria-label', search.placeholder);
    document.querySelectorAll('[data-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.language === value)));
    renderFilters();
    renderPlaces();
    renderGallery();
    updateImage();
    updateStatus();
    updateFullscreenControl();
  }

  const menu = document.getElementById('menu-toggle');
  menu.addEventListener('click', () => {
    const open = menu.getAttribute('aria-expanded') !== 'true';
    menu.setAttribute('aria-expanded', String(open));
    document.getElementById('site-nav').classList.toggle('open', open);
  });
  document.getElementById('site-nav').addEventListener('click', () => {
    menu.setAttribute('aria-expanded', 'false');
    document.getElementById('site-nav').classList.remove('open');
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      menu.setAttribute('aria-expanded', 'false');
      document.getElementById('site-nav').classList.remove('open');
    }
  });
  search.addEventListener('input', renderPlaces);
  select.addEventListener('change', () => setCategory(select.value));
  document.getElementById('reset-filters').addEventListener('click', () => { search.value = ''; setCategory('all'); });
  document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => changeLanguage(button.dataset.language)));

  const details = document.getElementById('project-view');
  const host = document.getElementById('panorama');
  const shell = document.getElementById('scene-shell');
  const rotate = document.getElementById('rotate');
  const fullscreen = document.getElementById('fullscreen');
  const status = document.getElementById('viewer-status');
  const tabs = [...document.querySelectorAll('[role=tab][data-panel]')];
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
    document.getElementById('panorama-fallback').hidden = false;
    if (viewer) { viewer.stopAutoRotate(); viewer.stopMovement(); }
    wantedRotation = rotating = false;
    rotate.setAttribute('aria-pressed', 'false');
    updateStatus();
  }
  function syncRotation() {
    const next = Boolean(viewer && host.dataset.state === 'loaded' && wantedRotation && inView
      && details.open && activePanel === 'panorama' && !document.hidden && !document.getElementById('lightbox').open);
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
      viewer = window.pannellum.viewer('panorama', {
        type: 'cubemap', cubeMap: ['f', 'r', 'b', 'l', 'u', 'd'].map(face => `assets/panorama-${face}.jpg`),
        autoLoad: true, autoRotate: false, showControls: false, showFullscreenCtrl: false,
        mouseZoom: false, doubleClickZoom: false, hfov: 100, pitch: -9, yaw: 0, minHfov: 45, maxHfov: 112,
        crossOrigin: 'anonymous', autoRotateInactivityDelay: -1,
        strings: { loadingLabel: text('Betöltés…', '加载中…') }
      });
      viewer.on('load', () => {
        if (host.dataset.state === 'error') { viewer.destroy(); return; }
        clearTimeout(timer);
        host.dataset.state = 'loaded';
        document.getElementById('panorama-fallback').hidden = true;
        viewer.resize();
        updateStatus();
        syncRotation();
      });
      viewer.on('error', failViewer);
      timer = setTimeout(failViewer, 25000);
    } catch { failViewer(); }
  }
  function stopVideos() { document.querySelectorAll('video').forEach(video => video.pause()); }
  function showPanel(name) {
    activePanel = name;
    tabs.forEach(tab => {
      const selected = tab.dataset.panel === name;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      document.getElementById('panel-' + tab.dataset.panel).hidden = !selected;
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
  document.getElementById('zoom-in').addEventListener('click', () => viewer?.setHfov(Math.max(45, viewer.getHfov() - 12), 250));
  document.getElementById('zoom-out').addEventListener('click', () => viewer?.setHfov(Math.min(112, viewer.getHfov() + 12), 250));
  document.getElementById('reset-view').addEventListener('click', () => { wantedRotation = false; syncRotation(); viewer?.lookAt(-9, 0, 100, 400); });
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
  const dialog = document.getElementById('lightbox');
  let imageIndex = 0;
  let returnFocus;
  function updateImage() {
    const name = text(galleryHu[imageIndex], galleryZh[imageIndex]);
    const image = document.getElementById('lightbox-image');
    image.src = `assets/panorama-gallery-${galleryFiles[imageIndex]}.jpg`;
    image.alt = name + text(' · látványterv', ' · 效果图');
    document.getElementById('image-caption').textContent = `${imageIndex + 1} / 6 · ${name}`;
  }
  function renderGallery() {
    const target = document.getElementById('gallery');
    target.replaceChildren();
    galleryFiles.forEach((file, index) => {
      const button = make('button');
      button.type = 'button';
      const name = text(galleryHu[index], galleryZh[index]);
      button.setAttribute('aria-label', name);
      const image = make('img');
      image.src = `assets/panorama-gallery-${file}.jpg`;
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
  document.getElementById('close-lightbox').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { returnFocus?.focus(); syncRotation(); });
  document.getElementById('previous-image').addEventListener('click', () => { imageIndex = (imageIndex + 5) % 6; updateImage(); });
  document.getElementById('next-image').addEventListener('click', () => { imageIndex = (imageIndex + 1) % 6; updateImage(); });
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      imageIndex = (imageIndex + (event.key === 'ArrowRight' ? 1 : 5)) % 6;
      updateImage();
    }
  });
  changeLanguage('hu');
  icons();
})();
