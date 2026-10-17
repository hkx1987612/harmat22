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
    const INITIAL_PRELOAD_RADIUS = 0;
    const LOAD_TIMEOUT_MS = 10000;
    const MAX_RESIDENT_FRAMES = 9;
    let loaderDismissed = false;
    let initialRecoveryStarted = false;
    let imageProblem = false;
    let jsonProblem = false;
    let pendingFrame = null;
    let frameSurface = null;
    let frameContext = null;
    const loaderOverlay = document.getElementById('lakasparkLoader');
    const loaderBarFill = document.getElementById('loaderBarFill');
    const loaderPercent = document.getElementById('loaderPercent');
    const loaderText = document.getElementById('loaderText');
    const hasBuildingLinks = !!document.querySelector('.lakaspark-building-links');
    // Loading feedback must never intercept the existing building navigation.
    if (loaderOverlay) loaderOverlay.style.pointerEvents = 'none';
    const loadStatus = document.createElement('div');
    loadStatus.className = 'viewer-load-status';
    loadStatus.setAttribute('role', 'status');
    loadStatus.setAttribute('aria-live', 'polite');
    Object.assign(loadStatus.style, {
        display: 'none', position: 'absolute', top: '58px', left: '12px', right: '12px',
        zIndex: '25', padding: '8px', background: 'rgba(255,255,255,0.94)',
        color: '#333', fontSize: '12px', lineHeight: '1.4', pointerEvents: 'none'
    });
    viewer.appendChild(loadStatus);

    function refreshLoadStatus() {
        loadStatus.textContent = jsonProblem
            ? (hasBuildingLinks
                ? 'Az interaktív kijelölés most nem érhető el. Kérjük, használja az épületgombokat vagy lépjen kapcsolatba értékesítőinkkel.'
                : 'Az interaktív kijelölés most nem érhető el. Kérjük, lépjen kapcsolatba értékesítőinkkel.')
            : imageProblem
                ? (hasBuildingLinks
                    ? 'A nézet lassabban töltődik. Az épületgombokkal közvetlenül továbbléphet.'
                    : (hitboxData && listContainer
                        ? 'A nézet lassabban töltődik. A lakáslistából megnyithatja az adatlapokat.'
                        : 'A nézet lassabban töltődik. Kérjük, várjon, vagy lépjen kapcsolatba értékesítőinkkel.'))
                : '';
        loadStatus.style.display = imageProblem || jsonProblem ? 'block' : 'none';
    }
    
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

    function dismissLoader(success = false) {
        if (success) {
            if (loaderPercent) loaderPercent.innerText = '100%';
            if (loaderBarFill) loaderBarFill.style.width = '100%';
        }
        if (!loaderDismissed) {
            loaderDismissed = true;
            clearTimeout(initialLoadTimer);
            clearInterval(textInterval);
            if(loaderOverlay) loaderOverlay.style.opacity = '0';
            setTimeout(() => { if(loaderOverlay) loaderOverlay.style.display = 'none'; }, 500);
        }
    }
    const initialLoadTimer = window.setTimeout(() => {
        imageProblem = true;
        dismissLoader();
        refreshLoadStatus();
    }, LOAD_TIMEOUT_MS);
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
                let imgHtml = data.image ? `<img src="${data.image}" class="apt-card-img" alt="Alaprajz" loading="lazy" decoding="async">` : `<div class="apt-card-img">Nincs kép</div>`;

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
        frameIndex = normalizeFrameIndex(frameIndex);
        pendingFrame = frameIndex;
        const img = ensureFrameLoaded(frameIndex, true);
        if (!img || img.dataset.loaded !== '1' || !img.naturalWidth) {
            preloadAround(frameIndex, 1);
            return false;
        }

        if (!presentFrame(img)) {
            markImageFailed(img, frameIndex);
            return false;
        }
        pendingFrame = null;
        currentFrame = frameIndex;
        imageProblem = false;
        dismissLoader(true);
        refreshLoadStatus();
        preloadAround(frameIndex, 1);
        
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
            let nextFrame = (pendingFrame === null ? currentFrame : pendingFrame) + dir;
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
    const frameCache = new Map();
    let cacheClock = 0;
    const posterImage = document.getElementById('lakasparkPoster');
    // Keep one painted surface: async image layers can briefly expose the white page.
    try {
        frameSurface = document.createElement('canvas');
        frameContext = frameSurface.getContext('2d');
        if (frameContext) {
            frameSurface.className = 'viewer-image viewer-frame-surface';
            frameSurface.setAttribute('aria-hidden', 'true');
            frameSurface.style.opacity = '0';
            frameSurface.style.zIndex = '1';
            viewer.insertBefore(frameSurface, svgLayer);
        }
    } catch (error) {
        frameContext = null;
    }

    function presentFrame(img) {
        if (!img.complete || !img.naturalWidth || !img.naturalHeight) return false;
        if (frameContext) {
            try {
                if (!frameSurface.dataset.painted) {
                    frameSurface.width = img.naturalWidth;
                    frameSurface.height = img.naturalHeight;
                }
                // Never clear/resize a painted canvas before its replacement is ready.
                frameContext.drawImage(img, 0, 0, frameSurface.width, frameSurface.height);
                frameSurface.dataset.painted = '1';
                frameSurface.dataset.frame = String(normalizeFrameIndex(
                    imageElements.indexOf(img) + 1
                ));
                frameSurface.style.opacity = '1';
            } catch (error) {
                return false;
            }
        }
        if (!frameContext) img.decoding = 'sync';
        imageElements.forEach(frame => {
            if (frame) frame.style.opacity = !frameContext && frame === img ? '1' : '0';
        });
        return true;
    }

    function isResident(img, frame) {
        return imageElements[frame - 1] === img && img.dataset.retired !== '1';
    }

    function watchFrame(img, frame) {
        const entry = { img, used: ++cacheClock, timer: null };
        frameCache.set(frame, entry);
        entry.timer = window.setTimeout(() => {
            if (isResident(img, frame) && img.dataset.loaded !== '1') markImageFailed(img, frame);
        }, LOAD_TIMEOUT_MS);
    }

    function clearFrameTimer(frame) {
        const entry = frameCache.get(frame);
        if (entry) clearTimeout(entry.timer);
    }

    function makeFrameRoom() {
        if (frameCache.size < MAX_RESIDENT_FRAMES) return;
        let oldestFrame = null;
        let oldestUse = Infinity;
        frameCache.forEach((entry, frame) => {
            if (frame === 1 || frame === currentFrame || frame === pendingFrame) return;
            if (entry.used < oldestUse) {
                oldestFrame = frame;
                oldestUse = entry.used;
            }
        });
        if (oldestFrame === null) return;
        const entry = frameCache.get(oldestFrame);
        clearTimeout(entry.timer);
        entry.img.dataset.retired = '1';
        entry.img.onload = null;
        entry.img.onerror = null;
        imageElements[oldestFrame - 1] = null;
        frameCache.delete(oldestFrame);
        if (entry.img.parentNode) entry.img.parentNode.removeChild(entry.img);
        // Drop the decoded bitmap and cancel an outstanding request, not just the DOM node.
        entry.img.removeAttribute('src');
    }

    function markImageReady(img, frame) {
        if (!isResident(img, frame)) return;
        if (img.dataset.progressDone || img.dataset.decoding) return;
        if (!img.complete || !img.naturalWidth || !img.naturalHeight) return;
        img.dataset.decoding = '1';
        let decoded;
        try {
            decoded = typeof img.decode === 'function' ? img.decode() : Promise.resolve();
        } catch (error) {
            delete img.dataset.decoding;
            markImageFailed(img, frame);
            return;
        }
        decoded.then(() => {
            if (!isResident(img, frame)) return;
            delete img.dataset.decoding;
            if (!img.naturalWidth || !img.naturalHeight) return;
            clearFrameTimer(frame);
            img.dataset.loaded = '1';
            delete img.dataset.failed;
            img.dataset.progressDone = '1';
            if (pendingFrame === frame || (pendingFrame === null && currentFrame === frame)) updateFrame(frame);
        }).catch(() => {
            if (!isResident(img, frame)) return;
            delete img.dataset.decoding;
            markImageFailed(img, frame);
        });
    }

    function markImageFailed(img, frame) {
        if (!isResident(img, frame)) return;
        clearFrameTimer(frame);
        img.dataset.loaded = '';
        img.dataset.failed = '1';
        // A failed request is not a usable frame. Keep the last painted view.
        if (pendingFrame === frame || (!loaderDismissed && frame === currentFrame)) {
            imageProblem = true;
            dismissLoader();
            refreshLoadStatus();
        }
        if (frame === 1 && !initialRecoveryStarted && !frameSurface?.dataset.painted
            && (pendingFrame === null || pendingFrame === 1)) {
            initialRecoveryStarted = true;
            updateFrame(2);
        }
    }

    if (posterImage) {
        imageElements[0] = posterImage;
        posterImage.dataset.loaded = '';
        watchFrame(posterImage, 1);
        const markPosterReady = () => markImageReady(posterImage, 1);
        if (posterImage.complete) {
            window.setTimeout(() => {
                if (posterImage.naturalWidth) markPosterReady();
                else markImageFailed(posterImage, 1);
            }, 0);
        } else {
            posterImage.addEventListener('load', markPosterReady, { once: true });
            posterImage.addEventListener('error', () => markImageFailed(posterImage, 1), { once: true });
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
        if (imageElements[index]) {
            frameCache.get(normalizedFrame).used = ++cacheClock;
            if (highPriority) imageElements[index].fetchPriority = 'high';
            return imageElements[index];
        }

        makeFrameRoom();
        const img = new Image();
        img.onload = () => markImageReady(img, normalizedFrame);
        img.onerror = () => markImageFailed(img, normalizedFrame);
        if (highPriority) {
            img.fetchPriority = 'high';
        }
        img.className = 'viewer-image';
        img.alt = '';
        img.setAttribute('aria-hidden', 'true');
        img.decoding = 'async';
        img.style.opacity = '0';
        imageElements[index] = img;
        watchFrame(img, normalizedFrame);
        if (!frameContext) viewer.insertBefore(img, svgLayer);
        img.src = buildFrameUrl(normalizedFrame);
        return img;
    }

    function preloadAround(frame, radius = INITIAL_PRELOAD_RADIUS) {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection && (connection.saveData || /(^|-)2g$/.test(connection.effectiveType || ''))) radius = 0;
        for (let offset = -radius; offset <= radius; offset++) {
            ensureFrameLoaded(frame + offset, offset === 0);
        }
    }

    preloadAround(currentFrame, 0);

    let jsonFinished = false;
    const jsonAbort = typeof AbortController === 'function' ? new AbortController() : null;
    function jsonFailed() {
        if (jsonFinished) return;
        jsonFinished = true;
        clearTimeout(jsonTimer);
        jsonProblem = true;
        dismissLoader();
        refreshLoadStatus();
        if (jsonAbort) jsonAbort.abort();
    }
    const jsonTimer = window.setTimeout(jsonFailed, LOAD_TIMEOUT_MS);
    fetch(
        CONFIG.jsonUrl
            + (CONFIG.jsonUrl.includes('?') ? '&' : '?')
            + 'v=' + encodeURIComponent(CONFIG.jsonVersion),
        { cache: 'force-cache', credentials: 'same-origin', ...(jsonAbort ? { signal: jsonAbort.signal } : {}) }
    )
        .then(res => {
            if (!res.ok) throw new Error('JSON HTTP error');
            return res.json();
        })
        .then(data => {
            if (jsonFinished) return;
            // A4 uses a 1-indexed JSON array; other scenes use keyed objects.
            if (!data || typeof data !== 'object'
                || !Object.values(data).every(frame => frame && typeof frame === 'object'
                    && Object.values(frame).every(path => typeof path === 'string'))) {
                throw new Error('Invalid hitbox data');
            }
            jsonFinished = true;
            clearTimeout(jsonTimer);
            hitboxData = data;
            renderApartmentList(); 
            refreshLoadStatus();
            updateFrame(pendingFrame === null ? currentFrame : pendingFrame);
        })
        .catch(jsonFailed);

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
            const rotationFrame = pendingFrame === null ? currentFrame : pendingFrame;
            const nextFrame = normalizeFrameIndex(rotationFrame + (currentX - startX < 0 ? 1 : -1));
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
