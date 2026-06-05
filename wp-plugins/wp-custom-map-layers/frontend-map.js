document.addEventListener('DOMContentLoaded', function() {
    
    const maps = document.querySelectorAll('.cml-wrapper');
    let globalZIndex = 10; // Globális z-index számláló a Smart Layeringhez

    maps.forEach(function(mapWrapper) {
        
        const catItems = mapWrapper.querySelectorAll('.cml-cat-item');
        
        // 1. INICIALIZÁLÁS: Minden réteg automatikus bekapcsolása induláskor!
        catItems.forEach(function(item) {
            const targetId = item.getAttribute('data-target');
            const markerLayer = mapWrapper.querySelector('#cml-layer-' + targetId);
            const routeLayer = mapWrapper.querySelector('#cml-route-' + targetId);
            const overlayLayer = mapWrapper.querySelector('#cml-' + targetId); 
            
            // Gomb aktívvá tétele
            item.classList.add('is-active');

            // Rétegek megjelenítése és kezdő z-index kiosztása
            if(markerLayer) {
                markerLayer.classList.add('is-visible');
                markerLayer.style.zIndex = ++globalZIndex;
            }
            if(routeLayer) {
                routeLayer.classList.add('is-visible');
                routeLayer.style.zIndex = ++globalZIndex;
            }
            if(overlayLayer) {
                overlayLayer.classList.add('is-visible');
                overlayLayer.style.zIndex = ++globalZIndex;
            }
        });

        // 2. Gombok KATTINTÁSI logikája (Legutóbb bekapcsolt kerül felülre)
        catItems.forEach(function(item) {
            item.addEventListener('click', function() {
                
                this.classList.toggle('is-active');
                const targetId = this.getAttribute('data-target');
                
                const markerLayer = mapWrapper.querySelector('#cml-layer-' + targetId);
                const routeLayer = mapWrapper.querySelector('#cml-route-' + targetId);
                const overlayLayer = mapWrapper.querySelector('#cml-' + targetId); 
                
                if(markerLayer) {
                    if(this.classList.contains('is-active')) {
                        markerLayer.classList.add('is-visible');
                        markerLayer.style.zIndex = ++globalZIndex; 
                    } else {
                        markerLayer.classList.remove('is-visible');
                    }
                }
                
                // JAVÍTVA: Az útvonal (Route) is megkapja a legmagasabb Z-indexet bekapcsoláskor!
                if(routeLayer) {
                    if(this.classList.contains('is-active')) {
                        routeLayer.classList.add('is-visible');
                        routeLayer.style.zIndex = ++globalZIndex; 
                    } else {
                        routeLayer.classList.remove('is-visible');
                    }
                }

                if(overlayLayer) {
                    if(this.classList.contains('is-active')) {
                        overlayLayer.classList.add('is-visible');
                        overlayLayer.style.zIndex = ++globalZIndex;
                    } else {
                        overlayLayer.classList.remove('is-visible');
                    }
                }
            });
        });

// 3. Hover logika a markerekhez (Fölé húzáskor abszolút legfelülre kerül)
        const allMarkers = mapWrapper.querySelectorAll('.cml-marker');
        allMarkers.forEach(function(marker) {
            marker.addEventListener('mouseenter', function() {
                const parentLayer = this.closest('.cml-marker-layer');
                if (parentLayer) {
                    parentLayer.style.zIndex = ++globalZIndex;
                }
                this.style.zIndex = ++globalZIndex;
            });
        });

        
        // --- PAN & ZOOM ENGINE ---
        const canvas = mapWrapper.querySelector('.cml-canvas');
        const transformLayer = mapWrapper.querySelector('.cml-transform-layer');
        const zoomBtns = mapWrapper.querySelectorAll('.cml-z-btn');
        
        if (canvas && transformLayer) {
            let scale = 1;
            let pointX = 0;
            let pointY = 0;
            let start = { x: 0, y: 0 };
            let isDragging = false;
            
            const minScale = 1;
            const maxScale = 4; // Max 4x-es nagyítás engedélyezve

            function setTransform() {
                // Biztonsági korlátok: ne lehessen kivinni a képernyőről a térképet
                const maxPanX = 0;
                const minPanX = canvas.clientWidth - (canvas.clientWidth * scale);
                const maxPanY = 0;
                const minPanY = canvas.clientHeight - (canvas.clientHeight * scale);

                if (pointX > maxPanX) pointX = maxPanX;
                if (pointX < minPanX) pointX = minPanX;
                if (pointY > maxPanY) pointY = maxPanY;
                if (pointY < minPanY) pointY = minPanY;

                transformLayer.style.transform = `translate(${pointX}px, ${pointY}px) scale(${scale})`;
                
                // ÚJ: CSS Változó átadása a "Counter-Scale" (Visszanagyítás) funkcióhoz
                // Ezzel érjük el, hogy a pöttyök és szövegek ne nőjenek a térképpel együtt!
                mapWrapper.style.setProperty('--map-inv-scale', 1 / scale);
            }

            // 1. Egérgörgő Zoom
            canvas.addEventListener('wheel', function(e) {
                e.preventDefault();
                
                const xs = (e.clientX - canvas.getBoundingClientRect().left - pointX) / scale;
                const ys = (e.clientY - canvas.getBoundingClientRect().top - pointY) / scale;
                
                const delta = (e.deltaY || -e.wheelDelta || e.detail) >> 10 || 1;
                const direction = delta > 0 ? -1 : 1;
                const step = 0.2;
                
                scale += direction * step;
                if (scale < minScale) scale = minScale;
                if (scale > maxScale) scale = maxScale;

                pointX = e.clientX - canvas.getBoundingClientRect().left - xs * scale;
                pointY = e.clientY - canvas.getBoundingClientRect().top - ys * scale;

                setTransform();
            }, { passive: false });

            // 2. Húzás (Drag & Pan) és KÉTÚJJAS ZOOM (Pinch) Asztalon és Mobilon
            let initialPinchDistance = null;
            let initialScale = 1;

            // Segédfüggvény: Két ujj közötti távolság kiszámítása (Pitagorasz-tétel)
            function getDistance(touches) {
                return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
            }

            function onPointerDown(e) {
                // UI elemek érintésekor kilépünk (Gombok működésének biztosítása)
                if (e.target.closest('.cml-zoom-controls') || e.target.closest('.cml-marker') || e.target.closest('.cml-ui-panel')) {
                    return; 
                }
                e.preventDefault(); 
                
                if (e.touches && e.touches.length === 2) {
                    // KÉT UJJ: Pinch-to-zoom indítása
                    isDragging = false;
                    initialPinchDistance = getDistance(e.touches);
                    initialScale = scale;
                } else {
                    // EGY UJJ / EGÉR: Húzás (Pan) indítása
                    isDragging = true;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    start = { x: clientX - pointX, y: clientY - pointY };
                }
                transformLayer.style.transition = 'none'; // Animáció kikapcsolása a sima követésért
            }

            function onPointerMove(e) {
                if (e.target.closest('.cml-zoom-controls') || e.target.closest('.cml-marker') || e.target.closest('.cml-ui-panel')) {
                    return; 
                }
                e.preventDefault();

                // --- KÉTÚJJAS ZOOM (Pinch) Logika ---
                if (e.touches && e.touches.length === 2) {
                    if (initialPinchDistance) {
                        const currentDistance = getDistance(e.touches);
                        const pinchRatio = currentDistance / initialPinchDistance;
                        
                        let newScale = initialScale * pinchRatio;
                        if (newScale < minScale) newScale = minScale;
                        if (newScale > maxScale) newScale = maxScale;
                        
                        // Fókuszpont számítás: hogy a zoom a képernyő közepe felé történjen
                        const centerX = canvas.clientWidth / 2;
                        const centerY = canvas.clientHeight / 2;
                        const xs = (centerX - pointX) / scale;
                        const ys = (centerY - pointY) / scale;

                        scale = newScale;
                        
                        // Pozíció finomhangolása (ne ugorjon el a térkép nagyítás közben)
                        pointX = centerX - xs * scale;
                        pointY = centerY - ys * scale;

                        setTransform();
                    }
                    return;
                }

                // --- EGYÚJJAS HÚZÁS Logika ---
                if (!isDragging || scale === 1) return; // Csak belenagyított állapotban lehet húzni
                
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                pointX = clientX - start.x;
                pointY = clientY - start.y;
                setTransform();
            }

            function onPointerUp(e) {
                isDragging = false;
                initialPinchDistance = null; // Kétujjas értékek nullázása
                transformLayer.style.transition = 'transform 0.1s ease-out'; // Visszakapcsoljuk az animációt
            }

            // Eseményfigyelők rögzítése
            canvas.addEventListener('mousedown', onPointerDown);
            canvas.addEventListener('mousemove', onPointerMove);
            window.addEventListener('mouseup', onPointerUp);
            
            canvas.addEventListener('touchstart', onPointerDown, { passive: false });
            canvas.addEventListener('touchmove', onPointerMove, { passive: false });
            window.addEventListener('touchend', onPointerUp);
            window.addEventListener('touchcancel', onPointerUp); // Ha hívás jön be, vagy megszakad a tapizás
            
            // 3. Zoom Gombok (+ / - / Reset)
            zoomBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    
                    // Gombnyomásnál a térkép közepe felé nagyítunk
                    const centerX = canvas.clientWidth / 2;
                    const centerY = canvas.clientHeight / 2;
                    
                    const xs = (centerX - pointX) / scale;
                    const ys = (centerY - pointY) / scale;

                    if (action === 'in' && scale < maxScale) scale += 0.5;
                    if (action === 'out' && scale > minScale) scale -= 0.5;
                    
                    if (action === 'reset') {
                        scale = 1;
                        pointX = 0;
                        pointY = 0;
                    } else {
                        if (scale < minScale) scale = minScale;
                        if (scale > maxScale) scale = maxScale;
                        pointX = centerX - xs * scale;
                        pointY = centerY - ys * scale;
                    }
                    
                    setTransform();
                });
            });
        }

    });
});