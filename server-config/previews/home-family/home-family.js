(() => {
  'use strict';

  if (document.documentElement.dataset.homeFamilyReady) return;
  document.documentElement.dataset.homeFamilyReady = 'true';

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons({ attrs: { 'aria-hidden': 'true', focusable: 'false' } });
  }

  const zh = {
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
    heroLocation: '布达佩斯第十区 · Kőbánya · Harmat utca 22.',
    heroAlt: 'Harmat Lakópark 规划建筑与绿色庭院的鸟瞰效果图',
    heroDescription: '为共同的日常生活，留一处温暖的新家。',
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
    apartmentsEyebrow: '寻找属于您的空间',
    apartmentsTitle: '从一个新家，开启共同的生活计划。',
    allApartments: '全部公寓',
    officialNewTab: '（官方网站，在新标签页打开）',
    available: '可售',
    a1Floor1: 'A1 栋 · 1 层',
    a2Floor4: 'A2 栋 · 4 层',
    unit6Link: 'A1-1-L6 官方公寓详情，在新标签页打开',
    unit2Link: 'A2-4-L5 官方公寓详情，在新标签页打开',
    unit5Link: 'A1-1-L5 官方公寓详情，在新标签页打开',
    unit6Alt: 'A1-1-L6 公寓官方户型图',
    unit2Alt: 'A2-4-L5 公寓官方户型图',
    unit5Alt: 'A1-1-L5 公寓官方户型图',
    roomCount: '房间数',
    oneRoom: '1 个房间',
    twoRooms: '2 个房间',
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
    everydayEyebrow: '让家更有温度',
    everydayTitle: '给家人空间，给日常生活从容。',
    everydayIntro: '一起吃早餐，出门透透气，回到熟悉的地方。让家成为家的，是这些平凡的共同瞬间。',
    courtyardAlt: 'Harmat Lakópark 规划绿色内庭效果图',
    courtyardTitle: '楼宇之间，留住绿意',
    courtyardDescription: '规划中 75% 的绿地比例，是项目的重要特色。为呼吸、休息以及共处时光，留出更多空间。',
    livingRoomAlt: 'Harmat Lakópark 明亮家庭客厅的室内效果图',
    livingRoomTitle: '让家跟随您的生活节奏',
    livingRoomDescription: '项目规划包含热泵供暖与制冷、地下车库及储藏室。各套公寓的具体内容，请参阅户型图和技术说明。',
    parkAlt: 'Harmat Lakópark 周边公共公园实景',
    parkCaption: '实景拍摄 · 附近公共公园，非小区内部花园',
    neighborhoodEyebrow: '走近 Kőbánya',
    parkTitle: '也给散步留一点时间。',
    parkDescription: '家的生活，不止于入户门内。也来了解 Harmat utca 周边与附近的公共公园。',
    neighborhoodLink: '探索周边环境',
    showroomEyebrow: '期待与您见面',
    showroomTitle: '从心中的问题，到共同的计划。',
    showroomDescription: '一起看看户型、不同选择，以及您对家的想法。欢迎在展示中心与销售团队当面交流。',
    contactTeam: '联系销售团队',
    showroomAlt: 'Harmat Lakópark 展示中心会客区',
    showroomCaption: '实景拍摄 · 展示中心',
    panoramaEyebrow: 'Harmat Lakópark · 3D/360°',
    panoramaTitle: '项目全景（3D/360°）',
    panoramaDescription: '环顾规划中的住宅区。3D/360°全景将在官方网站打开。',
    panoramaLink: '打开360°漫游',
    panoramaOpen: '在新标签页打开官方网站的360°全景',
    panoramaAlt: 'Harmat Lakópark 360°全景中的规划建筑外观效果图',
    panoramaCaption: '此全景为规划中的项目效果图，并非已建成建筑的实景照片。',
    chooserEyebrow: '换个角度看新家',
    chooserTitle: '您希望住在哪一栋？',
    chooserDescription: '通过官方虚拟选房工具，了解各栋建筑的位置并浏览公寓。',
    chooserLink: '虚拟选房',
    modelAlt: '展示中心内的 Harmat Lakópark 建筑沙盘',
    modelCaption: '实景拍摄 · 展示中心内的项目沙盘',
    teamEyebrow: 'Harmat Lakópark 背后的团队',
    teamTitle: '一起规划未来的人。',
    teamDescription: '了解项目背后的团队。面对面交流，让您关心的问题得到充分讨论。',
    teamLink: '认识我们',
    contactEyebrow: '从一次交谈开始',
    contactTitle: '说说您心中的新家。',
    contactDescription: '欢迎咨询公寓详情，或与销售团队预约当面交流。',
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
    videoUnavailable: 'A videó nem érhető el. A projekt látványterve látható.'
  };
  const translatedNodes = Array.from(document.querySelectorAll('[data-i18n]'), node => ({
    node, key: node.dataset.i18n, original: node.innerHTML
  }));
  const translatedAttributes = ['aria-label', 'alt', 'title'].flatMap(attribute =>
    Array.from(document.querySelectorAll(`[data-i18n-${attribute}]`), node => ({
      node, attribute, key: node.getAttribute(`data-i18n-${attribute}`), original: node.getAttribute(attribute)
    }))
  );
  const originalTitle = document.title;
  const originalDescription = document.querySelector('meta[name="description"]').content;
  const languageSwitch = document.querySelector('.language-switch');
  const menu = document.querySelector('.site-menu');
  const menuSummary = menu.querySelector('summary');
  const video = document.querySelector('.hero-video');
  const videoControl = document.querySelector('.video-control');
  const videoButton = document.querySelector('.video-button');
  const videoTooltip = document.getElementById('video-tooltip');
  const videoStatus = document.getElementById('video-status');
  const playIcon = videoButton.querySelector('.video-play-icon');
  const pauseIcon = videoButton.querySelector('.video-pause-icon');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let language = 'hu';
  let videoFailed = false;
  let userPaused = false;

  function controlText(key) {
    return language === 'zh' ? zh[key] : huControls[key];
  }

  function updateMenuLabel() {
    const label = controlText(menu.open ? 'menuClose' : 'menuOpen');
    menuSummary.setAttribute('aria-label', label);
    menuSummary.title = label;
    menuSummary.setAttribute('aria-expanded', String(menu.open));
  }

  function updateVideoControl() {
    const paused = video.paused || videoFailed;
    const label = controlText(videoFailed ? 'videoUnavailable' : paused ? 'videoPlay' : 'videoPause');
    playIcon.toggleAttribute('hidden', !paused);
    pauseIcon.toggleAttribute('hidden', paused);
    videoButton.setAttribute('aria-label', label);
    videoButton.title = label;
    videoTooltip.textContent = label;
    videoButton.disabled = videoFailed;
    if (videoFailed) videoStatus.textContent = label;
  }

  function setLanguage(nextLanguage) {
    language = nextLanguage;
    document.documentElement.lang = language === 'zh' ? 'zh-Hans' : 'hu';
    for (const entry of translatedNodes) {
      if (language === 'hu') entry.node.innerHTML = entry.original;
      else entry.node.textContent = zh[entry.key];
    }
    for (const entry of translatedAttributes) {
      entry.node.setAttribute(entry.attribute, language === 'zh' ? zh[entry.key] : entry.original);
    }
    languageSwitch.querySelectorAll('button').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.language === language));
    });
    document.title = language === 'zh' ? 'Harmat Lakópark | 为共同生活寻找新家' : originalTitle;
    document.querySelector('meta[name="description"]').content = language === 'zh'
      ? 'Harmat Lakópark：位于布达佩斯第十区 Harmat utca 22. 的新住宅项目。公寓、施工日志与联系信息。'
      : originalDescription;
    updateMenuLabel();
    updateVideoControl();
  }

  // Do not expose a language choice if any marked copy is missing.
  const dictionaryComplete = [...translatedNodes, ...translatedAttributes].every(entry => typeof zh[entry.key] === 'string');
  if (dictionaryComplete) {
    languageSwitch.hidden = false;
    languageSwitch.querySelectorAll('button').forEach(button => {
      button.addEventListener('click', () => setLanguage(button.dataset.language));
    });
  }

  menu.addEventListener('toggle', updateMenuLabel);
  menu.querySelector('nav').addEventListener('click', event => {
    if (event.target.closest('a')) menu.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu.open) {
      menu.open = false;
      menuSummary.focus();
    }
  });
  document.addEventListener('click', event => {
    if (menu.open && !menu.contains(event.target) && !languageSwitch.contains(event.target)) menu.open = false;
  });

  function playVideo() {
    if (videoFailed) return;
    video.muted = true;
    if (!video.getAttribute('src')) video.src = video.dataset.src;
    try {
      const pending = video.play();
      if (pending && typeof pending.catch === 'function') pending.catch(updateVideoControl);
    } catch {
      updateVideoControl();
    }
  }

  videoButton.addEventListener('click', () => {
    if (video.paused) {
      userPaused = false;
      playVideo();
    } else {
      userPaused = true;
      video.pause();
    }
  });
  video.addEventListener('playing', () => {
    video.dataset.revealed = 'true';
    updateVideoControl();
  });
  video.addEventListener('pause', updateVideoControl);
  video.addEventListener('error', () => {
    videoFailed = true;
    delete video.dataset.revealed;
    updateVideoControl();
  });
  reducedMotion.addEventListener('change', event => {
    if (event.matches) video.pause();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) video.pause();
    else if (!userPaused && !reducedMotion.matches) playVideo();
  });

  videoControl.hidden = false;
  updateMenuLabel();
  updateVideoControl();
  if (!reducedMotion.matches && !document.hidden) playVideo();
})();
