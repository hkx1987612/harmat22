document.addEventListener('DOMContentLoaded', () => {
    const WP_DATA = window.LakasparkData; 
    if (!WP_DATA) return;

    const CONFIG = { 
        basePath: WP_DATA.baseUrl, 
        filePrefix: `bld-${WP_DATA.scene}-frame-`, 
        fileExt: '.webp', 
        totalFrames: 72, 
        jsonUrl: WP_DATA.jsonUrl,
        jsonVersion: WP_DATA.jsonVersion || '6.2'
    };
    const WP_APARTMENT_DATA = WP_DATA.apartments || {};
    const TOGGLE_ENABLED = WP_DATA.toggle !== 'off';
    const CUSTOM_LINKS = WP_DATA.customLinks || {};
    const STATIC_HITBOXES = WP_DATA.staticHitboxes || {};
    
    let activeFilters = { rooms: 'all', floor: 'all' };

    const mainLayout = document.getElementById('mainLayout');
    const toggleBtn = document.getElementById('listToggleBtn');
    const topToggleBtn = document.getElementById('topListToggleBtn');
    const viewer = document.getElementById('buildingViewer');
    const svgLayer = document.getElementById('hitboxLayer');
    const tooltip = document.getElementById('viewerTooltip');
    const compassSlider = document.getElementById('compassSlider');
    const compassTicks = document.getElementById('compassTicks');
    const compassLabels = document.getElementById('compassLabels');
    const listContainer = document.getElementById('apartmentList');
    const filterRooms = document.getElementById('filterRooms');
    const filterFloorBtns = document.querySelectorAll('.floor-btn');
    const resultCount = document.getElementById('resultCount');
    if (svgLayer) {
        svgLayer.setAttribute('preserveAspectRatio', 'xMidYMid slice');
    }

    // Forgató gombok és Vissza gomb
    const rotateLeftBtn = document.getElementById('rotateLeftBtn');
    const rotateRightBtn = document.getElementById('rotateRightBtn');
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', () => { window.history.back(); });
    }

    let hitboxData = null; 
    let currentFrame = 1;

    // --- PROGRESSZIV, DE ELES KEPBETOLTES ---
    let loadedImages = 0;
    const INITIAL_PRELOAD_RADIUS = 0;
    const INITIAL_LOAD_TARGET = Math.min(CONFIG.totalFrames, (INITIAL_PRELOAD_RADIUS * 2) + 1);
    let loaderDismissed = false;
    let backgroundPreloadStarted = false;
    let pendingFrame = null;
    const loaderOverlay = document.getElementById('lakasparkLoader');
    const loaderBarFill = document.getElementById('loaderBarFill');
    const loaderPercent = document.getElementById('loaderPercent');
    const loaderText = document.getElementById('loaderText');
    
    const loadingTexts = [
        "Az interaktív lakásválasztó betöltése folyamatban...",
        "Épületek előkészítése...", 
        "Lakásválasztó indítása...", 
        "Harmat Lakópark"
    ];
    let textIdx = 0;
    const textInterval = setInterval(() => {
        textIdx = (textIdx + 1) % loadingTexts.length;
        if(loaderText) loaderText.innerText = loadingTexts[textIdx];
    }, 5000);

    function onImageLoadProgress() {
        loadedImages++;
        const p = Math.min(100, Math.round((loadedImages / INITIAL_LOAD_TARGET) * 100));
        if(loaderPercent) loaderPercent.innerText = p + '%';
        if(loaderBarFill) loaderBarFill.style.width = p + '%';

        if (!loaderDismissed && loadedImages >= INITIAL_LOAD_TARGET) {
            loaderDismissed = true;
            clearInterval(textInterval);
            if(loaderOverlay) loaderOverlay.style.opacity = '0';
            setTimeout(() => { if(loaderOverlay) loaderOverlay.style.display = 'none'; }, 500);
            window.setTimeout(() => updateFrame(currentFrame), 0);
            startBackgroundPreload();
        }
    }
    // --- LAZY LOADING VÉGE ---

    function toggleList() {
        if (mainLayout) mainLayout.classList.toggle('list-closed');
    }

    // Segédfüggvény az ID-k összehozására
    function getNormalizedAptData(jsonId) {
        if (!jsonId) return null;
        const normJson = String(jsonId).toLowerCase().replace(/[_ ]/g, '-');
        for (const [wpId, data] of Object.entries(WP_APARTMENT_DATA)) {
            const normWp = String(wpId).toLowerCase().replace(/[_ ]/g, '-');
            if (normJson === normWp) return { originalWpId: wpId, data: data };
        }
        return null;
    }

    if (toggleBtn) toggleBtn.addEventListener('click', toggleList);
    if (topToggleBtn) topToggleBtn.addEventListener('click', toggleList);

    function renderApartmentList() {
        // Fontos: Megvárjuk, amíg a JSON betölt!
        if (!listContainer || !hitboxData) return;
        listContainer.innerHTML = ''; 
        let count = 0;

        // Végigmegyünk az ÖSSZES képkockán, hogy az épület túloldalán lévő (pl. L1) lakásokat is megtaláljuk
        const validJsonIds = new Set();
        Object.values(hitboxData).forEach(frameData => {
            Object.keys(frameData).forEach(id => {
                validJsonIds.add(String(id).toLowerCase().replace(/[_ ]/g, '-'));
            });
        });

        Object.entries(WP_APARTMENT_DATA).forEach(([id, data]) => {
            const normWpId = String(id).toLowerCase().replace(/[_ ]/g, '-');
            
            // Ha a lakás nincs az aktuális épület (scene) JSON fájljában, eldobjuk!
            if (!validJsonIds.has(normWpId)) return;

            const matchRoom = activeFilters.rooms === 'all' || String(data.rooms) === activeFilters.rooms;
            const matchFloor = activeFilters.floor === 'all' || String(data.floor).toUpperCase() === activeFilters.floor.toUpperCase();

            if (matchRoom && matchFloor) {
                count++;
                const card = document.createElement('div');
                card.className = `apt-card status-${data.status}`;
                card.setAttribute('data-id', id);

                let priceText = data.status === 'available' ? 'Érdeklődjön' : (data.status === 'reserved' ? 'Egyeztetés alatt' : 'Eladva');
                let imgHtml = data.image ? `<img src="${data.image}" class="apt-card-img" alt="Alaprajz">` : `<div class="apt-card-img">Nincs kép</div>`;

                card.innerHTML = `
                    ${imgHtml}
                    <div class="apt-card-content">
                        <div class="apt-card-header">
                            <h3>${data.name}</h3>
                            <div class="apt-price">${priceText}</div>
                        </div>
                        <div class="apt-meta-grid">
                            <div class="meta-item">${data.rooms || '-'} szoba</div>
                            <div class="meta-item">${(data.floor === 'F' || data.floor === 'FSZ') ? 'Fsz' : data.floor || '-'} em.</div>
                            <div class="meta-item">${data.b_area ? data.b_area + ' m²' : '-'}</div>
                        </div>
                        <a href="${data.status === 'available' ? data.link : '#'}" class="apt-btn-modern">Adatlap</a>
                    </div>
                `;

                if(data.status !== 'sold') {
                    card.addEventListener('mouseenter', () => highlightPolygon(id, true));
                    card.addEventListener('mouseleave', () => highlightPolygon(id, false));
                    card.addEventListener('click', () => { highlightCard(id, false); });
                }
                
                listContainer.appendChild(card);
            }
        });
        if (resultCount) resultCount.innerText = 'Találat: ' + count + ' lakás';
    }

    function getFrameData(index) {
        if (!hitboxData) return null;
        if (hitboxData[index]) return hitboxData[index];
        return null;
    }

    function updateFrame(frameIndex) {
        const img = ensureFrameLoaded(frameIndex, true);
        if (!img || !img.dataset.loaded) {
            pendingFrame = frameIndex;
            preloadAround(frameIndex, 1);
            return false;
        }

        currentFrame = frameIndex;
        preloadAround(frameIndex, 1);
        imageElements.forEach((img, idx) => { if (img) img.style.opacity = (idx + 1 === frameIndex) ? '1' : '0'; });
        
        if (compassSlider) compassSlider.value = frameIndex;
        updateCompassMask(frameIndex);

        svgLayer.innerHTML = ''; 

        const frameData = getFrameData(frameIndex);
        if (!frameData) return false;

        Object.entries(frameData).forEach(([aptId, pathData]) => {
            const isStatic = STATIC_HITBOXES.hasOwnProperty(aptId);
            const isCustomLink = CUSTOM_LINKS.hasOwnProperty(aptId);
            const match = getNormalizedAptData(aptId);
            const aptInfo = match ? match.data : null;
            const cardId = match ? match.originalWpId : aptId;

            let status = 'available';
            let label = aptId.toUpperCase();

            if (isStatic) {
                status = 'static';
                label = STATIC_HITBOXES[aptId];
            } else if (isCustomLink) {
                if (aptInfo) {
                    const rawStatus = aptInfo.status;
                    status = rawStatus ? String(rawStatus).toLowerCase().trim() : 'available';
                    label = aptInfo.name.toUpperCase();
                } else {
                    status = 'available'; 
                }
            } else {
                if (aptInfo) {
                    const matchRoom = activeFilters.rooms === 'all' || String(aptInfo.rooms) === activeFilters.rooms;
                    const matchFloor = activeFilters.floor === 'all' || String(aptInfo.floor).toUpperCase() === activeFilters.floor.toUpperCase();
                    if (!matchRoom || !matchFloor) return;

                    const rawStatus = aptInfo.status;
                    status = rawStatus ? String(rawStatus).toLowerCase().trim() : 'available';
                    label = aptInfo.name.toUpperCase();
                } else {
                    status = 'available';
                    label = aptId.toUpperCase();
                }
            }

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathData);
            path.setAttribute('data-id', aptId);
            path.classList.add('hitbox-polygon', `status-${status}`);
            
            if (status !== 'sold') {
                path.addEventListener('mouseenter', (e) => {
                    tooltip.style.opacity = '1';
                    tooltip.innerText = label;
                    
                    const pathRect = path.getBoundingClientRect();
                    const viewerRect = viewer.getBoundingClientRect();
                    const leftPos = (pathRect.left - viewerRect.left) + (pathRect.width / 2);
                    const topPos = (pathRect.top - viewerRect.top);
                    
                    tooltip.style.left = leftPos + 'px';
                    tooltip.style.top = (topPos - 10) + 'px';
                    
                    path.classList.add('hover-active');
                    if (aptInfo) {
                        const card = document.querySelector(`.apt-card[data-id="${cardId}"]`);
                        if(card) card.classList.add('hover');
                    }
                });

                path.addEventListener('mouseleave', () => {
                    tooltip.style.opacity = '0';
                    path.classList.remove('hover-active');
                    if (aptInfo) {
                        const card = document.querySelector(`.apt-card[data-id="${cardId}"]`);
                        if(card) card.classList.remove('hover');
                    }
                });
                
                if (!isStatic) {
                    const activatePath = () => {
                        if (isCustomLink) {
                            window.location.href = CUSTOM_LINKS[aptId];
                            return;
                        }
                        if (status === 'available' || status === 'reserved') {
                            document.querySelectorAll('.hitbox-polygon.active').forEach(p => p.classList.remove('active'));
                            path.classList.add('active');

                            if (TOGGLE_ENABLED) {
                                if (mainLayout.classList.contains('list-closed')) {
                                    toggleList();
                                    setTimeout(() => highlightCard(cardId, true), 400);
                                } else {
                                    highlightCard(cardId, true);
                                }
                            }
                        }
                    };

                    path.setAttribute('tabindex', '0');
                    path.setAttribute('role', isCustomLink ? 'link' : 'button');
                    path.setAttribute('aria-label', label);
                    path.addEventListener('click', activatePath);
                    path.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') return;
                        event.preventDefault();
                        activatePath();
                    });
                }
            }

            svgLayer.appendChild(path);
        }); 
        return true;
    }

    function highlightPolygon(id, isHover) {
        const el = document.querySelector(`path[data-id="${id}"]`);
        if (el) isHover ? el.classList.add('active') : el.classList.remove('active');
    }

    function highlightCard(id, scroll = false) {
        const card = document.querySelector(`.apt-card[data-id="${id}"]`);
        if (card) {
            document.querySelectorAll('.apt-card.active').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            
            if (scroll && listContainer) {
                const scrollPos = card.offsetTop - listContainer.offsetTop;
                listContainer.scrollTo({
                    top: scrollPos - 20, 
                    behavior: 'smooth'
                });
            }
        }
    }

    function updateCompassMask(frame) {
        if (!compassTicks || !compassLabels) return;
        const percent = ((frame - 1) / (CONFIG.totalFrames - 1)) * 100;
        const mask = `radial-gradient(circle at ${percent}% 50%, black 0%, rgba(0,0,0,0.8) 10%, transparent 25%)`;
        [compassTicks, compassLabels].forEach(el => { el.style.webkitMaskImage = mask; el.style.maskImage = mask; });
    }

    function animateRotation(steps) {
        let currentStep = 0;
        const dir = steps > 0 ? 1 : -1;
        const absSteps = Math.abs(steps);
        
        const stepInt = setInterval(() => {
            let nextFrame = currentFrame + dir;
            if (nextFrame > CONFIG.totalFrames) nextFrame = 1;
            if (nextFrame < 1) nextFrame = CONFIG.totalFrames;
            updateFrame(nextFrame);
            currentStep++;
            if(currentStep >= absSteps) clearInterval(stepInt);
        }, 45); 
    }

    if(rotateLeftBtn) rotateLeftBtn.addEventListener('click', () => animateRotation(-4));
    if(rotateRightBtn) rotateRightBtn.addEventListener('click', () => animateRotation(4));

    const imageElements = new Array(CONFIG.totalFrames);
    const posterImage = document.getElementById('lakasparkPoster');
    if (posterImage) {
        imageElements[0] = posterImage;
        posterImage.dataset.loaded = posterImage.complete ? '1' : '';
        const markPosterReady = function () {
            if (posterImage.dataset.progressDone) return;
            posterImage.dataset.loaded = '1';
            posterImage.dataset.progressDone = '1';
            onImageLoadProgress();
        };
        if (posterImage.complete) {
            window.setTimeout(markPosterReady, 0);
        } else {
            posterImage.addEventListener('load', markPosterReady, { once: true });
            posterImage.addEventListener('error', markPosterReady, { once: true });
        }
    }

    function normalizeFrameIndex(frame) {
        let normalized = frame;
        while (normalized < 1) normalized += CONFIG.totalFrames;
        while (normalized > CONFIG.totalFrames) normalized -= CONFIG.totalFrames;
        return normalized;
    }

    function buildFrameUrl(frame) {
        return `${CONFIG.basePath}${CONFIG.filePrefix}${String(frame).padStart(2, '0')}${CONFIG.fileExt}`;
    }

    function ensureFrameLoaded(frame, highPriority = false) {
        const normalizedFrame = normalizeFrameIndex(frame);
        const index = normalizedFrame - 1;
        if (imageElements[index]) return imageElements[index];

        const img = new Image();
        img.onload = function () {
            img.dataset.loaded = '1';
            onImageLoadProgress();
            if (pendingFrame === normalizedFrame) {
                pendingFrame = null;
                updateFrame(normalizedFrame);
            }
        };
        img.onerror = function () {
            img.dataset.loaded = '1';
            onImageLoadProgress();
            if (pendingFrame === normalizedFrame) {
                pendingFrame = null;
                updateFrame(normalizedFrame);
            }
        };
        if (highPriority) {
            img.fetchPriority = 'high';
        }
        img.className = 'viewer-image';
        img.alt = '';
        img.setAttribute('aria-hidden', 'true');
        img.decoding = 'async';
        img.style.opacity = (normalizedFrame === currentFrame) ? '1' : '0';
        img.src = buildFrameUrl(normalizedFrame);
        viewer.insertBefore(img, svgLayer);
        imageElements[index] = img;
        return img;
    }

    function preloadAround(frame, radius = INITIAL_PRELOAD_RADIUS) {
        for (let offset = -radius; offset <= radius; offset++) {
            ensureFrameLoaded(frame + offset, Math.abs(offset) <= 1);
        }
    }

    function startBackgroundPreload() {
        if (backgroundPreloadStarted) return;
        backgroundPreloadStarted = true;

        preloadAround(currentFrame, 1);

        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        const conserveData = connection && (
            connection.saveData
            || /(^|-)2g$/.test(connection.effectiveType || '')
        );
        if (conserveData) return;

        const queue = [];
        const queued = new Set([currentFrame]);
        for (let distance = 1; distance < CONFIG.totalFrames; distance++) {
            [currentFrame + distance, currentFrame - distance].forEach((frame) => {
                const normalized = normalizeFrameIndex(frame);
                if (queued.has(normalized)) return;
                queued.add(normalized);
                queue.push(normalized);
            });
        }

        const delay = connection && connection.effectiveType === '3g' ? 850 : 500;
        let index = 0;
        function loadNext() {
            if (index >= queue.length) return;
            if (document.hidden) {
                window.setTimeout(loadNext, 1200);
                return;
            }

            ensureFrameLoaded(queue[index], false);
            index++;
            window.setTimeout(loadNext, delay);
        }
        window.setTimeout(loadNext, 1400);
    }

    preloadAround(currentFrame, 0);

    // A renderelés bekerült a fetch belsejébe, hogy biztosan meglegyen a JSON adat!
    fetch(
        CONFIG.jsonUrl
            + (CONFIG.jsonUrl.includes('?') ? '&' : '?')
            + 'v=' + encodeURIComponent(CONFIG.jsonVersion),
        { cache: 'force-cache', credentials: 'same-origin' }
    )
        .then(res => res.json())
        .then(data => {
            hitboxData = data;
            renderApartmentList(); 
            updateFrame(currentFrame);
        })
        .catch(err => console.error("JSON hiba:", err));

    if (compassSlider) compassSlider.addEventListener('input', (e) => {
        updateFrame(parseInt(e.target.value));
    });

    if (filterRooms) filterRooms.addEventListener('change', (e) => { activeFilters.rooms = e.target.value; renderApartmentList(); updateFrame(currentFrame); });
    filterFloorBtns.forEach(btn => btn.addEventListener('click', (e) => {
        filterFloorBtns.forEach(b => b.classList.remove('active'));
        e.target.classList.add('active');
        activeFilters.floor = e.target.getAttribute('data-val');
        renderApartmentList(); updateFrame(currentFrame);
    }));

    let isDragging = false, startX = 0;
    const startDrag = (e) => { isDragging = true; startX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX; };
    const onDrag = (e) => {
        if (!isDragging) return;
        const currentX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
        if (Math.abs(currentX - startX) > 15) {
            const nextFrame = (currentX - startX < 0) ? (currentFrame < CONFIG.totalFrames ? currentFrame + 1 : 1) : (currentFrame > 1 ? currentFrame - 1 : CONFIG.totalFrames);
            updateFrame(nextFrame); startX = currentX;
        }
    };
    viewer.addEventListener('mousedown', startDrag); viewer.addEventListener('mousemove', onDrag); window.addEventListener('mouseup', () => isDragging = false);
    viewer.addEventListener('touchstart', startDrag, {passive: true}); viewer.addEventListener('touchmove', onDrag, {passive: true}); window.addEventListener('touchend', () => isDragging = false);
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => updateFrame(currentFrame), 120);
    });
});
