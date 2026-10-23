(() => {
  'use strict';

  const ownedRoot = document.getElementById('harmat-home-family');
  if (!ownedRoot || ownedRoot.dataset.homeFamilyReady) return;
  ownedRoot.dataset.homeFamilyReady = 'true';

  const zh = {
    hiddenPrice: '价格面议',
    skip: '跳转到主要内容',
    brandHome: 'Harmat Lakópark 首页',
    language: '语言',
    languageHu: '匈牙利语',
    languageZh: '中文',
    menu: '菜单',
    menuOpen: '打开菜单',
    menuClose: '关闭菜单',
    mainNavigation: '主导航',
    navHome: '首页',
    navSearch: '查找公寓',
    navProject: 'Harmat Lakópark 项目',
    navAbout: '关于我们',
    navNeighborhood: '周边环境',
    navGallery: '图片集',
    navContacts: '联系方式',
    appointment: '预约参观',
    appointmentShort: '预约',
    appointmentOpen: '在新标签页打开官方网站的预约页面',
    navApartments: '公寓',
    navJournal: '施工日志',
    navEveryday: '日常生活',
    navPanorama: '360°全景',
    navContact: '联系我们',
    requestOffer: '咨询报价',
    heroAlt: 'Harmat Lakópark 规划建筑与绿色庭院的鸟瞰效果图',
    chooseApartment: '选择公寓',
    readJournal: '施工日志',
    rendering: '渲染效果图',
    videoPlay: '播放视频',
    videoPause: '暂停视频',
    videoUnavailable: '视频暂时无法播放，当前显示项目效果图。',
    highlights: '项目概况',
    greenLabel: '绿地比例',
    greenNote: '按项目规划',
    phaseLabel: '第一期规划',
    homes: '套公寓',
    phaseNote: '满足不同生活需求',
    comfortLabel: '居家舒适',
    heatPump: '热泵系统',
    comfortNote: '供暖与制冷',
    handoverLabel: '计划交付时间',
    quarter: '第二季度',
    handoverNote: '计划时间',
    apartmentsEyebrow: "当前房源",
    apartmentsTitle: "可售户型",
    allApartments: '全部公寓',
    officialNewTab: '（官方网站，在新标签页打开）',
    available: '可售',
    a1Floor1: 'A1 栋 · 1 层',
    a2Floor4: 'A2 栋 · 4 层',
    a3GroundFloor: 'A3 栋 · 底层',
    a4GroundFloor: 'A4 栋 · 底层',
    a3Floor2: 'A3 栋 · 2 层',
    unit6Link: 'A1-1-L6 官方公寓详情，在新标签页打开',
    unit2Link: 'A2-4-L5 官方公寓详情，在新标签页打开',
    unit5Link: 'A1-1-L5 官方公寓详情，在新标签页打开',
    unit6Alt: 'A1-1-L6 公寓官方户型图',
    unit2Alt: 'A2-4-L5 公寓官方户型图',
    unit5Alt: 'A1-1-L5 公寓官方户型图',
    roomCount: '房间数',
    oneRoom: '1 个房间',
    twoRooms: '2 个房间',
    threeRooms: '3 个房间',
    fourRooms: '4 个房间',
    salesArea: '销售面积',
    price: '售价',
    apartmentDetails: '公寓详情',
    priceNote: '价格与可售状态可能变化。最新条件请以官方公寓详情及销售团队提供的信息为准。',
    sitePhoto: '实景拍摄',
    constructionAlt: 'Harmat Lakópark 九月施工报告中的现场影像',
    constructionCaption: '9 月 30 日报告的 10 月 2 日补充影像。',
    journalDate: '2026 年 9 月 30 日报告',
    journalTitle: '新生活的下一章，正在建造。',
    journalDescription: '九月报告记录了第一期基础及地下室结构工程的进展。真实现场影像，并附有 10 月 2 日的补充拍摄。',
    journalLink: '查看施工日志',
    plannedNote: '计划于 2028 年第二季度交付。此为计划时间，并非保证交付日期。',
    everydayEyebrow: "从项目规划到现场",
    everydayTitle: "家里家外的日常",
    everydayIntro: "看看规划中的庭院、公寓配套，以及周边环境。",
    courtyardAlt: 'Harmat Lakópark 规划绿色内庭效果图',
    courtyardTitle: "楼宇之间的绿色庭院",
    courtyardDescription: "项目规划中的楼间共享绿地。图片展示规划效果，并非目前的建成状态。",
    livingRoomAlt: 'Harmat Lakópark 明亮家庭客厅的室内效果图',
    livingRoomTitle: "居家配套",
    livingRoomDescription: "项目规划包含热泵供暖制冷、地下车库和储藏室。具体内容请查看对应户型图和技术说明。",
    parkAlt: "Óhegy 公园与 Kőbánya 地标，2024年11月9日实拍",
    parkCaption: "Óhegy 公园 · 实拍于2024年11月9日 · 周边公共公园，非小区内部花园",
    neighborhoodEyebrow: "Harmat utca 周边",
    parkTitle: "公园与日常配套",
    parkDescription: "了解附近公共公园、商店、交通与公共服务设施，详细内容可在周边环境页查看。",
    neighborhoodLink: '探索周边环境',
    showroomEyebrow: "面对面交流",
    showroomTitle: "一起看看户型图",
    showroomDescription: "在展示中心，与销售团队交流户型选择和具体配置。",
    contactTeam: '联系销售团队',
    showroomAlt: "Harmat Lakópark 售楼部外观与 HARMAT 标识实景",
    showroomCaption: '实景拍摄 · 展示中心',
    panoramaEyebrow: 'Harmat Lakópark · 3D/360°',
    panoramaTitle: '360°全景',
    panoramaConsole: 'Harmat Lakópark 互动展示',
    panoramaView: '项目全景效果图',
    panoramaTabs: 'Harmat Lakópark 展示菜单',
    panoramaTab: '全景',
    panoramaVideos: '视频',
    panoramaProject: '项目',
    panoramaLocation: '周边环境',
    panoramaNotice: '重要信息',
    panoramaProjectVideo: '项目介绍',
    panoramaProjectVideoCopy: '住宅区的位置、建筑体量及周边联系。',
    panoramaVisualVideo: '效果视频',
    panoramaVisualVideoCopy: '规划中的居住环境与项目氛围。',
    panoramaProjectOverview: '项目概况',
    panoramaProjectTitle: "Harmat Lakópark · 布达佩斯第十区",
    panoramaProjectCopy: "项目地址：1105 Budapest, Harmat utca 22。可在全景中查看楼栋与规划环境。",
    panoramaGreen: '绿色环境',
    panoramaUrban: '宜居的城市节奏',
    panoramaGallery0: '项目概览',
    panoramaGallery1: '建筑立面',
    panoramaGallery4: '居住空间',
    panoramaGallery8: '鸟瞰视角',
    panoramaGallery10: '住宅细节',
    panoramaGallery5: '花园与公共空间',
    panoramaGallery0Zoom: '放大项目概览',
    panoramaGallery1Zoom: '放大建筑立面',
    panoramaGallery4Zoom: '放大居住空间',
    panoramaAerialZoom: '放大鸟瞰视角',
    panoramaGallery10Zoom: '放大住宅细节',
    panoramaGallery5Zoom: '放大花园与公共空间',
    panoramaOverviewZoom: '放大周边概览',
    panoramaOverviewAlt: 'Harmat Lakópark 周边概览',
    panoramaLocationTitle: "Harmat utca 周边",
    panoramaLocationCopy: "周边配套的详细介绍可在“周边环境”页查看。",
    panoramaAddress: '布达佩斯第十区，Harmat utca 22.',
    panoramaLocationServices: '附近的购物、教育和医疗设施',
    panoramaNoticeTitle: '重要信息',
    panoramaNoticeCopy: '效果图、视频与展示资料仅供参考。价格、技术内容、面积、交付时间及配置均以官方文件和合同为准。',
    panoramaNoticeVisual: '效果图为示意图',
    panoramaNoticeData: '数据仅供参考',
    panoramaNoticeContract: '以合同为准',
    panoramaReset: '恢复初始视角',
    panoramaZoom: '放大效果图',
    panoramaClose: '关闭',
    panoramaRotate: '开始旋转',
    panoramaPause: '暂停旋转',
    panoramaFullscreen: '全屏',
    panoramaExitFullscreen: '退出全屏',
    panoramaFullscreenUnavailable: '全屏暂不可用',
    panoramaFullscreenError: '无法进入全屏，当前保持页面内显示。',
    panoramaError: '360°全景暂不可用，当前显示项目效果图。',
    panoramaVideoError: '此视频暂不可用。',
    panoramaLoadButton: '加载全景',
    panoramaLoading: '全景加载中…',
    panoramaByline: '作者：%s',
    panoramaMissingError: '未提供全景图片。',
    panoramaFileError: '无法读取全景图片。',
    panoramaUrlError: '全景图片地址无效。',
    panoramaIosError: '此设备无法显示全景图片。',
    panoramaWebglError: '此浏览器不支持显示全景所需的 WebGL。',
    panoramaSizeError: '全景宽度为 %s 像素，此设备最多支持 %s 像素。',
    panoramaUnknownError: '无法加载全景。',
    panoramaAlt: 'Harmat Lakópark 360°全景中的规划建筑外观效果图',
    panoramaCaption: '此全景为规划中的项目效果图，并非已建成建筑的实景照片。',
    chooserEyebrow: "按楼栋选房",
    chooserTitle: "先选楼栋，再看公寓",
    chooserDescription: "先了解楼栋位置，再查看各套公寓的户型图和详细资料。",
    chooserLink: '虚拟选房',
    modelAlt: '展示中心内的 Harmat Lakópark 建筑沙盘',
    modelCaption: '实景拍摄 · 展示中心内的项目沙盘',
    teamEyebrow: 'Harmat Lakópark 背后的团队',
    teamTitle: '一起规划未来的人。',
    teamDescription: '了解项目背后的团队。面对面交流，让您关心的问题得到充分讨论。',
    teamLink: "关于项目与团队",
    contactEyebrow: "Harmat utca 22.",
    contactTitle: "销售咨询与联系",
    contactDescription: "咨询当前房源信息，或联系销售团队预约参观展示中心。",
    bookingLink: '联系与预约',
    locationAlt: 'Harmat utca 22. 周边实景',
    locationCaption: '实景拍摄 · Harmat utca 及周边',
    footerAddress: '布达佩斯第十区的新家。\n1105 Budapest, Harmat utca 22.',
    backToTop: '返回顶部',
    disclaimer: '效果图仅供说明，并非已建成实景。最终技术内容以合同及附件为准。计划交付时间可能调整。',
    privacy: '隐私声明'
  };
  const huControls = {
    menuOpen: 'Menü megnyitása',
    menuClose: 'Menü bezárása',
    videoPlay: 'Videó lejátszása',
    videoPause: 'Videó szüneteltetése',
    videoUnavailable: 'A videó nem érhető el. A projekt látványterve látható.',
    panoramaRotate: 'Forgatás',
    panoramaPause: 'Forgatás szüneteltetése',
    panoramaFullscreen: 'Teljes képernyő',
    panoramaExitFullscreen: 'Kilépés a teljes képernyőből',
    panoramaFullscreenUnavailable: 'A teljes képernyő nem érhető el',
    panoramaFullscreenError: 'A teljes képernyő nem érhető el. A bemutató az oldalon marad.',
    panoramaError: 'A 360°-os panoráma nem érhető el. A projekt látványterve látható.',
    panoramaVideoError: 'Ez a videó nem érhető el.',
    panoramaLoadButton: 'Panoráma betöltése',
    panoramaLoading: 'Panoráma betöltése…',
    panoramaByline: 'Készítette: %s',
    panoramaMissingError: 'Nincs megadva panorámakép.',
    panoramaFileError: 'A panorámakép nem érhető el.',
    panoramaUrlError: 'A panorámakép címe érvénytelen.',
    panoramaIosError: 'Ez az eszköz nem tudja megjeleníteni a panorámaképet.',
    panoramaWebglError: 'A böngésző nem támogatja a panorámához szükséges WebGL megjelenítést.',
    panoramaSizeError: 'A panoráma szélessége %s képpont, az eszköz legfeljebb %s képpontot támogat.',
    panoramaUnknownError: 'A panoráma nem tölthető be.'
  };
  const translatedNodes = Array.from(ownedRoot.querySelectorAll('[data-i18n]'), node => ({
    node, key: node.dataset.i18n, original: node.textContent
  }));
  const translatedAttributes = ['aria-label', 'alt', 'title'].flatMap(attribute =>
    Array.from(ownedRoot.querySelectorAll(`[data-i18n-${attribute}]`), node => ({
      node, attribute, key: node.getAttribute(`data-i18n-${attribute}`), original: node.getAttribute(attribute)
    }))
  );
  const video = ownedRoot.querySelector('.hhf-hero-video');
  const videoControl = ownedRoot.querySelector('.hhf-video-control');
  const videoButton = ownedRoot.querySelector('.hhf-video-button');
  const videoTooltip = ownedRoot.querySelector('#hhf-video-tooltip');
  const videoStatus = ownedRoot.querySelector('#hhf-video-status');
  const playIcon = videoButton.querySelector('.hhf-video-play-icon');
  const pauseIcon = videoButton.querySelector('.hhf-video-pause-icon');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let language = 'hu';
  let videoFailed = false;
  let updatePanoramaLanguage = () => {};
  let updateApartmentToggle = () => {};

  function controlText(key) {
    return language === 'zh' ? zh[key] : huControls[key];
  }

  function updateVideoControl() {
    const paused = video.paused || videoFailed;
    const label = controlText(videoFailed ? 'videoUnavailable' : 'videoPlay');
    playIcon.toggleAttribute('hidden', !paused);
    pauseIcon.setAttribute('hidden', '');
    // Keep a play-only fallback when the browser declines muted autoplay.
    videoButton.hidden = !paused || videoFailed;
    videoTooltip.hidden = videoButton.hidden;
    videoButton.setAttribute('aria-label', label);
    videoButton.title = label;
    videoTooltip.textContent = label;
    videoButton.disabled = videoFailed;
    if (videoFailed) videoStatus.textContent = label;
  }

  function setLanguage(nextLanguage) {
    language = nextLanguage;
    ownedRoot.lang = language === 'zh' ? 'zh-Hans' : 'hu';
    for (const entry of translatedNodes) {
      if (language === 'hu') entry.node.textContent = entry.original;
      else if (typeof zh[entry.key] === 'string') entry.node.textContent = zh[entry.key];
    }
    for (const entry of translatedAttributes) {
      entry.node.setAttribute(entry.attribute, language === 'zh' && typeof zh[entry.key] === 'string' ? zh[entry.key] : entry.original);
    }
    updateVideoControl();
    updatePanoramaLanguage();
    updateApartmentToggle();
  }

  function initApartmentToggle() {
    const grid = ownedRoot.querySelector('#hhf-apartment-grid');
    const button = ownedRoot.querySelector('.hhf-apartment-toggle');
    if (!grid || !button || typeof window.matchMedia !== 'function') return () => {};
    const extras = Array.from(grid.querySelectorAll('.hhf-apartment')).slice(3);
    if (!extras.length) return () => {};
    const mobile = window.matchMedia('(max-width: 620px)');
    if (typeof mobile.addEventListener !== 'function') return () => {};
    let expanded = false;

    function render() {
      const compact = mobile.matches;
      if (!compact) expanded = false;
      // Move focus before hiding a card or the desktop-only-hidden toggle.
      if (compact && !expanded && extras.some(card => card.contains(document.activeElement))) {
        button.hidden = false;
        button.focus();
      } else if (!compact && document.activeElement === button) {
        const firstLink = grid.querySelector('a');
        if (firstLink) firstLink.focus();
      }
      extras.forEach(card => { card.hidden = compact && !expanded; });
      button.hidden = !compact;
      button.setAttribute('aria-expanded', String(!compact || expanded));
      button.textContent = language === 'zh'
        ? expanded ? '收起更多公寓' : `再显示 ${extras.length} 套公寓`
        : expanded ? 'Kevesebb lakás' : `További ${extras.length} lakás megtekintése`;
    }

    button.addEventListener('click', () => {
      if (!mobile.matches) return;
      expanded = !expanded;
      render();
      if (!expanded && typeof button.scrollIntoView === 'function') {
        const bounds = button.getBoundingClientRect();
        // Keep the collapsed control visible below the native sticky header.
        if (bounds.top < 76 || bounds.bottom > window.innerHeight) {
          button.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
        }
      }
    });
    mobile.addEventListener('change', render);
    render();
    return render;
  }

  let appliedNativeLanguage = null;
  function syncNativeLanguage() {
    const marker = document.documentElement.getAttribute('data-harmat-language');
    const next = marker === 'zh-Hans' ? 'zh' : 'hu';
    // The native runtime may reassert an unchanged marker after observing DOM work.
    if (appliedNativeLanguage === next) return;
    appliedNativeLanguage = next;
    setLanguage(next);
  }
  const languageObserver = new MutationObserver(syncNativeLanguage);
  languageObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-harmat-language'] });

  function canPlayVideo() {
    return !videoFailed && !document.hidden;
  }

  function pauseHeroVideo() {
    if (!video.paused) video.pause();
  }

  function playVideo() {
    if (!canPlayVideo()) { pauseHeroVideo(); return; }
    video.muted = true;
    if (!video.getAttribute('src')) video.src = video.dataset.src;
    try {
      const pending = video.play();
      if (pending && typeof pending.catch === 'function') pending.catch(updateVideoControl);
    } catch {
      updateVideoControl();
    }
  }

  function syncHeroPlayback() {
    if (!canPlayVideo()) pauseHeroVideo();
    else if (video.paused) playVideo();
  }

  videoButton.addEventListener('click', () => {
    if (video.paused) playVideo();
  });
  video.addEventListener('playing', () => {
    // A pending play request can settle after the document becomes hidden.
    if (!canPlayVideo()) { pauseHeroVideo(); updateVideoControl(); return; }
    video.dataset.revealed = 'true';
    updateVideoControl();
  });
  video.addEventListener('pause', updateVideoControl);
  video.addEventListener('error', () => {
    videoFailed = true;
    delete video.dataset.revealed;
    updateVideoControl();
  });
  document.addEventListener('visibilitychange', syncHeroPlayback);

  function initPanorama() {
    const root = ownedRoot.querySelector('[data-panorama-console]');
    if (!root) return () => {};
    const host = root.querySelector('.hhf-panorama-viewer');
    const fallback = root.querySelector('.hhf-panorama-fallback');
    const status = root.querySelector('[data-panorama-status]');
    const actionStatus = root.querySelector('[data-panorama-action-status]');
    const tabs = Array.from(root.querySelectorAll('[data-panorama-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-panorama-panel]'));
    const clips = Array.from(root.querySelectorAll('.hhf-panorama-video-card video'));
    const rotateButton = root.querySelector('[data-panorama-rotate]');
    const fullscreenButton = root.querySelector('[data-panorama-fullscreen]');
    const dialog = root.querySelector('.hhf-panorama-lightbox');
    const lightImage = dialog.querySelector('img');
    const lightCaption = dialog.querySelector('.hhf-panorama-lightbox-caption');
    const startPitch = -27;
    const startYaw = 0;
    const startHfov = 92;
    let viewer = null;
    let activePanel = 'panorama';
    let inViewport = false;
    let rotateWanted = !reducedMotion.matches;
    let rotating = false;
    let loadTimer;
    let actionMessage = '';
    let lightTrigger = null;
    let touchStart = null;

    function isFullscreen() {
      return document.fullscreenElement === root;
    }

    function vendorStrings() {
      return {
        loadButtonLabel: controlText('panoramaLoadButton'),
        loadingLabel: controlText('panoramaLoading'),
        bylineLabel: controlText('panoramaByline'),
        noPanoramaError: controlText('panoramaMissingError'),
        fileAccessError: controlText('panoramaFileError'),
        malformedURLError: controlText('panoramaUrlError'),
        iOS8WebGLError: controlText('panoramaIosError'),
        genericWebGLError: controlText('panoramaWebglError'),
        textureSizeError: controlText('panoramaSizeError'),
        unknownError: controlText('panoramaUnknownError')
      };
    }

    function setToolLabel(button, key) {
      const label = controlText(key);
      button.setAttribute('aria-label', label);
      button.title = label;
      button.querySelector('.hhf-panorama-tooltip').textContent = label;
    }

    function updateLightbox() {
      if (!lightTrigger) return;
      const sourceImage = lightTrigger.querySelector('img');
      lightImage.alt = sourceImage.alt;
      lightCaption.textContent = sourceImage.alt;
    }

    function updateLanguage() {
      const state = host.dataset.panoramaState;
      status.hidden = state !== 'loading' && state !== 'error';
      status.textContent = status.hidden ? '' : controlText(state === 'error' ? 'panoramaError' : 'panoramaLoading');
      actionStatus.hidden = !actionMessage;
      actionStatus.textContent = actionMessage ? controlText(actionMessage) : '';
      setToolLabel(rotateButton, rotating ? 'panoramaPause' : 'panoramaRotate');
      rotateButton.setAttribute('aria-pressed', String(rotating));
      rotateButton.querySelector('[data-rotate-icon]').toggleAttribute('hidden', rotating);
      rotateButton.querySelector('[data-rotate-pause]').toggleAttribute('hidden', !rotating);
      rotateButton.disabled = state === 'error' || reducedMotion.matches;
      const canFullscreen = typeof root.requestFullscreen === 'function';
      setToolLabel(fullscreenButton, !canFullscreen ? 'panoramaFullscreenUnavailable'
        : isFullscreen() ? 'panoramaExitFullscreen' : 'panoramaFullscreen');
      fullscreenButton.disabled = !canFullscreen;
      fullscreenButton.setAttribute('aria-pressed', String(isFullscreen()));
      fullscreenButton.querySelector('[data-fullscreen-enter]').toggleAttribute('hidden', isFullscreen());
      fullscreenButton.querySelector('[data-fullscreen-exit]').toggleAttribute('hidden', !isFullscreen());
      clips.forEach(clip => {
        const error = clip.closest('article').querySelector('.hhf-panorama-video-error');
        if (!error.hidden) error.textContent = controlText('panoramaVideoError');
      });
      // Mutate vendor strings, never replace the viewer DOM or its event handlers.
      if (viewer && typeof viewer.getConfig === 'function') {
        Object.assign(viewer.getConfig().strings, vendorStrings());
      }
      const loadLabel = host.querySelector('.pnlm-load-box > p');
      const loadButton = host.querySelector('.pnlm-load-button p');
      const errorLabel = host.querySelector('.pnlm-error-msg p');
      if (loadLabel) loadLabel.textContent = controlText('panoramaLoading');
      if (loadButton) loadButton.textContent = controlText('panoramaLoadButton');
      if (errorLabel) errorLabel.textContent = controlText('panoramaError');
      updateLightbox();
    }

    function failViewer() {
      window.clearTimeout(loadTimer);
      host.dataset.panoramaState = 'error';
      host.setAttribute('aria-busy', 'false');
      host.hidden = true;
      fallback.hidden = false;
      rotating = false;
      if (viewer) {
        try { viewer.stopAutoRotate(); viewer.stopMovement(); } catch { /* The poster remains available. */ }
      }
      updateLanguage();
    }

    function syncRotation() {
      const next = Boolean(viewer && host.dataset.panoramaState === 'loaded' && rotateWanted
        && inViewport && activePanel === 'panorama' && !document.hidden && !reducedMotion.matches && !dialog.open);
      if (viewer && next !== rotating) {
        try {
          if (next) viewer.startAutoRotate(-1);
          else { viewer.stopAutoRotate(); viewer.stopMovement(); }
        } catch { failViewer(); return; }
      }
      rotating = next;
      updateLanguage();
    }

    function pauseOwnedRotation() {
      rotateWanted = false;
      rotating = false;
      if (viewer) {
        try { viewer.stopAutoRotate(); viewer.stopMovement(); }
        catch { failViewer(); return; }
      }
      updateLanguage();
    }

    function resizeViewer() {
      if (!viewer || host.dataset.panoramaState !== 'loaded' || activePanel !== 'panorama') return;
      try {
        // The fullscreen element is the console, not Pannellum's inner host.
        viewer.getConfig().mouseZoom = isFullscreen() ? true : 'fullscreenonly';
        viewer.resize();
      } catch { failViewer(); }
    }

    function ensureViewer() {
      if (host.dataset.panoramaState !== 'idle') return;
      host.dataset.panoramaState = 'loading';
      host.setAttribute('aria-busy', 'true');
      updateLanguage();
      try {
        if (!window.pannellum || typeof window.pannellum.viewer !== 'function') throw new Error('Pannellum unavailable');
        const cubeMap = ['f', 'r', 'b', 'l', 'u', 'd'].map(face => host.dataset[`cube${face.toUpperCase()}`]);
        if (cubeMap.some(url => !url)) throw new Error('Missing cubemap face');
        viewer = window.pannellum.viewer(host, {
          type: 'cubemap', cubeMap, crossOrigin: 'anonymous', autoLoad: true,
          showControls: false, showFullscreenCtrl: false, showZoomCtrl: false,
          autoRotate: false, autoRotateInactivityDelay: -1, compass: false,
          hfov: startHfov, pitch: startPitch, yaw: startYaw, minHfov: 44, maxHfov: 112,
          mouseZoom: 'fullscreenonly', doubleClickZoom: false, strings: vendorStrings()
        });
        viewer.on('load', () => {
          if (host.dataset.panoramaState === 'error') {
            try { viewer.destroy(); } catch { /* A late load cannot replace the fallback. */ }
            return;
          }
          window.clearTimeout(loadTimer);
          host.dataset.panoramaState = 'loaded';
          host.setAttribute('aria-busy', 'false');
          fallback.hidden = true;
          resizeViewer();
          syncRotation();
        });
        viewer.on('error', failViewer);
        if (host.dataset.panoramaState === 'loading') loadTimer = window.setTimeout(failViewer, 25000);
        updateLanguage();
      } catch { failViewer(); }
    }

    function pauseClips() {
      clips.forEach(clip => { try { clip.pause(); } catch { /* No playback is started here. */ } });
    }

    function showPanel(name) {
      activePanel = name;
      panels.forEach(panel => { panel.hidden = panel.dataset.panoramaPanel !== name; });
      tabs.forEach(tab => {
        const selected = tab.dataset.panoramaTab === name;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
      });
      if (name !== 'video') pauseClips();
      else clips.forEach(clip => { if (!clip.getAttribute('src')) clip.src = clip.dataset.src; });
      if (name === 'panorama') { ensureViewer(); resizeViewer(); }
      syncRotation();
    }

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => showPanel(tab.dataset.panoramaTab));
      tab.addEventListener('keydown', event => {
        let next;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        else return;
        event.preventDefault();
        tabs[next].focus();
        showPanel(tabs[next].dataset.panoramaTab);
      });
    });

    clips.forEach(clip => {
      const rate = Number(clip.dataset.playbackRate);
      function applyRate() {
        try {
          if (Math.abs(clip.defaultPlaybackRate - rate) > 0.01) clip.defaultPlaybackRate = rate;
          if (Math.abs(clip.playbackRate - rate) > 0.01) clip.playbackRate = rate;
        } catch { /* Native controls remain available if a rate is unsupported. */ }
      }
      applyRate();
      clip.addEventListener('loadedmetadata', applyRate);
      clip.addEventListener('ratechange', applyRate);
      clip.addEventListener('play', () => {
        applyRate();
        if (activePanel !== 'video' || document.hidden || !inViewport) {
          try { clip.pause(); } catch { /* Never resume a hidden video. */ }
        } else clips.filter(other => other !== clip).forEach(other => {
          try { other.pause(); } catch { /* Keep one native player active. */ }
        });
      });
      clip.addEventListener('error', () => {
        clip.closest('article').querySelector('.hhf-panorama-video-error').hidden = false;
        updateLanguage();
      });
    });

    rotateButton.addEventListener('click', () => {
      rotateWanted = !rotating;
      showPanel('panorama');
    });
    root.querySelector('[data-panorama-reset]').addEventListener('click', () => {
      showPanel('panorama');
      if (viewer && host.dataset.panoramaState === 'loaded') {
        try { viewer.lookAt(startPitch, startYaw, startHfov, reducedMotion.matches ? 0 : 900); }
        catch { failViewer(); }
      }
    });
    fullscreenButton.addEventListener('click', async () => {
      actionMessage = '';
      try {
        if (isFullscreen()) await document.exitFullscreen();
        else await root.requestFullscreen();
      } catch { actionMessage = 'panoramaFullscreenError'; }
      resizeViewer();
      updateLanguage();
    });
    document.addEventListener('fullscreenchange', () => {
      resizeViewer();
      updateLanguage();
    });

    root.addEventListener('click', event => {
      const trigger = event.target.closest('[data-gallery]');
      if (!trigger || !root.contains(trigger)) return;
      const image = trigger.querySelector('img');
      lightTrigger = trigger;
      lightImage.src = image.currentSrc || image.src;
      updateLightbox();
      try { dialog.showModal(); }
      catch { lightTrigger = null; return; }
      syncRotation();
    });
    root.querySelector('[data-panorama-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const bounds = dialog.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
      const trigger = lightTrigger;
      lightTrigger = null;
      if (trigger && trigger.isConnected) trigger.focus();
      syncRotation();
    });

    host.addEventListener('mousedown', pauseOwnedRotation, { capture: true, passive: true });
    host.addEventListener('pointerdown', event => {
      if (event.pointerType === 'touch') pauseOwnedRotation();
    }, { capture: true, passive: true });
    // The vendor stops rotation on every keydown, including uncaptured keys.
    host.addEventListener('keydown', pauseOwnedRotation, { capture: true });
    for (const type of ['mousewheel', 'DOMMouseScroll']) {
      host.addEventListener(type, () => {
        if (isFullscreen()) pauseOwnedRotation();
      }, { capture: true, passive: true });
    }

    // Let a vertical one-finger gesture scroll the page before the legacy touch handler consumes it.
    host.addEventListener('touchstart', event => {
      pauseOwnedRotation();
      touchStart = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { capture: true, passive: true });
    host.addEventListener('touchmove', event => {
      if (isFullscreen() || !touchStart || event.touches.length !== 1) return;
      const touch = event.touches[0];
      if (Math.abs(touch.clientY - touchStart.y) >= Math.abs(touch.clientX - touchStart.x)) event.stopImmediatePropagation();
    }, { capture: true, passive: true });
    host.addEventListener('touchend', () => { touchStart = null; }, { capture: true, passive: true });
    host.addEventListener('touchcancel', event => {
      touchStart = null;
      pauseOwnedRotation();
      // Pannellum 2.5.6 ends drags on .pnlm-dragfix, not on the host.
      const dragfix = host.querySelector('.pnlm-dragfix');
      const target = dragfix && dragfix.contains(event.target) ? event.target : dragfix || host;
      let ended;
      try {
        ended = new TouchEvent('touchend', { bubbles: true, touches: [], targetTouches: [], changedTouches: [] });
      } catch {
        ended = new Event('touchend', { bubbles: true });
        for (const key of ['touches', 'targetTouches', 'changedTouches']) {
          Object.defineProperty(ended, key, { value: [] });
        }
      }
      target.dispatchEvent(ended);
      if (viewer) {
        try { viewer.stopMovement(); } catch { failViewer(); }
      }
    }, { capture: true, passive: true });

    if ('IntersectionObserver' in window) {
      const nearObserver = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting || entry.intersectionRatio > 0)) {
          nearObserver.disconnect();
          if (activePanel === 'panorama') ensureViewer();
        }
      }, { rootMargin: '420px 0px', threshold: 0.01 });
      nearObserver.observe(root);
      const visibleObserver = new IntersectionObserver(entries => {
        inViewport = entries.some(entry => entry.isIntersecting || entry.intersectionRatio > 0);
        if (!inViewport) pauseClips();
        syncRotation();
      }, { threshold: 0.01 });
      visibleObserver.observe(root);
    } else {
      const updateVisibility = () => {
        const bounds = root.getBoundingClientRect();
        inViewport = bounds.bottom > 0 && bounds.top < window.innerHeight && bounds.width > 0;
        if (inViewport && activePanel === 'panorama') ensureViewer();
        if (!inViewport) pauseClips();
        syncRotation();
      };
      window.addEventListener('scroll', updateVisibility, { passive: true });
      window.addEventListener('resize', updateVisibility);
      updateVisibility();
    }
    if ('ResizeObserver' in window) new ResizeObserver(resizeViewer).observe(root);
    window.addEventListener('resize', resizeViewer);
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) pauseClips();
      syncRotation();
    });
    reducedMotion.addEventListener('change', () => {
      if (reducedMotion.matches) rotateWanted = false;
      syncRotation();
    });
    updateLanguage();
    return updateLanguage;
  }

  updatePanoramaLanguage = initPanorama();
  updateApartmentToggle = initApartmentToggle();
  syncNativeLanguage();
  video.autoplay = true;
  video.loop = true;
  video.muted = true;
  video.playsInline = true;
  videoControl.hidden = false;
  updateVideoControl();
  syncHeroPlayback();
})();
