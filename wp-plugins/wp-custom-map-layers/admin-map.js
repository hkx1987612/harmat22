jQuery(document).ready(function($) {
    
    var customUploader;
    $(document).on('click', '.upload-img-btn', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        customUploader = wp.media({ title: 'Válassz képet', multiple: false }).on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.url);
            if(targetId === 'map_base_image') initVisualEditor();
            if(targetId === 'map_overlay_image') renderOverlay();
        }).open();
    });

    var mapData = []; 
    var overlayData = {x: 0, y: 0, w: 30}; 
    var activeCategoryIndex = null;

    try { mapData = JSON.parse($('#map_data_json').val() || '[]'); } catch(e) {}
    try { overlayData = JSON.parse($('#map_overlay_data').val() || '{"x":0,"y":0,"w":30}'); } catch(e) {}

    // HTML struktúra (ÚJ: map-routes-layer az útvonalképeknek)
    var editorHtml = `
        <div style="display:flex; gap:20px;">
            <div style="width: 360px; background:#fff; padding:15px; border:1px solid #ccc; max-height: 800px; overflow-y: auto;">
                <h4>Kategóriák és Pontok</h4>
                <div id="category-list"></div>
                <button type="button" class="button button-primary" id="add-category-btn" style="width:100%; margin-top:10px;">+ Új Kategória</button>
            </div>
            <div style="flex:1; position:relative; overflow:hidden; border:1px solid #ccc; background:#eee;" id="canvas-wrapper">
                <div id="map-canvas" style="position:relative; display:inline-block;">
                    <img id="map-canvas-img" src="" style="max-width:100%; height:auto; display:block;">
                    <div id="map-routes-layer" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:1;"></div>
                    <img id="map-overlay-layer" src="" style="position:absolute; display:none; pointer-events:none; z-index:2;">
                    <div id="map-markers-layer" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:3;"></div>
                </div>
            </div>
        </div>
    `;
    $('#map-visual-editor-container').html(editorHtml);

    // Kategóriák Kirajzolása (Új "Útvonal Kép" opcióval)
    function renderCategories() {
        var listHtml = '';
        mapData.forEach(function(cat, index) {
            var isActive = (index === activeCategoryIndex) ? 'border-left: 4px solid #0073aa; background:#f0f0f1;' : '';
            var iconPreview = cat.iconUrl ? `<div class="icon-preview-circle" style="width:28px; height:28px; background:${cat.color}; border:2px solid #fff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-left:10px;"><img src="${cat.iconUrl}" style="width:80%; height:80%; object-fit:contain; pointer-events:none;"></div>` : `<div class="icon-preview-circle" style="width:28px; height:28px; background:${cat.color}; border:2px solid #fff; border-radius:50%; display:inline-block; vertical-align:middle; margin-left:10px;"></div>`;
            
            listHtml += `<div class="category-item" style="padding:10px; border-bottom:1px solid #eee; cursor:pointer; ${isActive}" data-index="${index}">`;
            listHtml += `<input type="text" class="cat-name" value="${cat.name}" placeholder="Kategória neve" style="width:100%; margin-bottom:5px;">`;
            
            listHtml += `<div style="display:flex; align-items:center; gap:5px; margin-bottom:5px;">
                            <input type="color" class="cat-color" value="${cat.color}">
                            <button type="button" class="button upload-icon-btn" data-index="${index}">Ikon</button>
                            ${iconPreview}
                        </div>`;
            
            // ÚJ: Útvonalkép feltöltő a buszokhoz/villamosokhoz
            var layerStatus = cat.layerUrl ? `<span style="font-size:10px; color:green; line-height:24px;">✓ Kép aktív</span> <button type="button" class="button-link remove-layer-btn" data-index="${index}" style="color:red; font-size:10px;">(Törlés)</button>` : `<span style="font-size:10px; color:#999; line-height:24px;">Nincs útvonal kép</span>`;
            listHtml += `<div style="background:#f9f9f9; padding:5px; border:1px solid #ddd; margin-bottom:10px;">
                            <label style="font-size:10px; display:block; margin-bottom:3px; font-weight:bold;">Útvonal réteg (Teljes térképes PNG):</label>
                            <div style="display:flex; gap:5px;">
                                <button type="button" class="button upload-layer-btn" data-index="${index}" style="font-size:11px;">Feltöltés</button>
                                ${layerStatus}
                            </div>
                        </div>`;
                        
            listHtml += `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                            <button type="button" class="button add-gps-point-btn" data-index="${index}" style="font-size:11px;">+ GPS Pont</button>
                            <button type="button" class="button-link delete-cat" style="color:red; font-size:11px;">Kategória Törlése</button>
                        </div>`;
            
            if(cat.points && cat.points.length > 0) {
                listHtml += `<div style="background:#fff; border:1px solid #ddd; padding:5px; margin-top:10px;">
                                <strong style="font-size:11px; display:block; margin-bottom:5px;">Mentett pontok:</strong>`;
                cat.points.forEach(function(pt, ptIdx) {
                    listHtml += `
                        <div style="background:#f9f9f9; padding:5px; border:1px solid #ccc; margin-bottom:5px;">
                            <input type="text" class="pt-label" data-cat="${index}" data-pt="${ptIdx}" value="${pt.label}" style="width:100%; font-size:11px; margin-bottom:3px;" placeholder="Pont neve">
                            <div style="display:flex; gap:5px; align-items:center;">
                                <span style="font-size:10px;">X:</span> <input type="number" step="0.1" class="pt-x" data-cat="${index}" data-pt="${ptIdx}" value="${pt.x}" style="width:55px; font-size:11px; padding:0 2px;">
                                <span style="font-size:10px;">Y:</span> <input type="number" step="0.1" class="pt-y" data-cat="${index}" data-pt="${ptIdx}" value="${pt.y}" style="width:55px; font-size:11px; padding:0 2px;">
                                <button type="button" class="button-link delete-pt" data-cat="${index}" data-pt="${ptIdx}" style="color:red; font-size:11px;">Töröl</button>
                            </div>
                        </div>`;
                });
                listHtml += `</div>`;
            }
            listHtml += `</div>`;
        });
        $('#category-list').html(listHtml);
        updateJson();
        renderMarkers();
        renderRouteLayers(); // Újrarajzoljuk az útvonalakat is
    }

    // ÚJ: Útvonalképek kirajzolása a vászonra
    function renderRouteLayers() {
        var layersHtml = '';
        mapData.forEach(function(cat) {
            if(cat.layerUrl) {
                layersHtml += `<img src="${cat.layerUrl}" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; opacity:0.7;">`;
            }
        });
        $('#map-routes-layer').html(layersHtml);
    }

    // ÚJ: Útvonal kép feltöltés és törlés események
    $(document).on('click', '.upload-layer-btn', function(e) {
        e.preventDefault(); e.stopPropagation();
        var index = $(this).data('index');
        var layerUploader = wp.media({ title: 'Válassz teljes méretű útvonal képet (PNG)', multiple: false }).on('select', function() {
            mapData[index].layerUrl = layerUploader.state().get('selection').first().toJSON().url;
            renderCategories();
        }).open();
    });

    $(document).on('click', '.remove-layer-btn', function(e) {
        e.preventDefault(); e.stopPropagation();
        var index = $(this).data('index');
        mapData[index].layerUrl = '';
        renderCategories();
    });

    // --- CÉLZOTT FRISSÍTÉS (Színválasztó Bug Javítása) ---
    $(document).on('input', '.cat-name, .cat-color', function() {
        var item = $(this).closest('.category-item');
        var index = item.data('index');
        mapData[index].name = item.find('.cat-name').val();
        mapData[index].color = item.find('.cat-color').val();
        
        // Csak a gomb színét és a markereket frissítjük, nem rajzoljuk újra az egész HTML-t!
        item.find('.icon-preview-circle').css('background-color', mapData[index].color);
        updateJson();
        renderMarkers(); 
    });

    $(document).on('input', '.pt-label, .pt-x, .pt-y', function() {
        var catIdx = $(this).data('cat'); var ptIdx = $(this).data('pt');
        mapData[catIdx].points[ptIdx].label = $(`.pt-label[data-cat="${catIdx}"][data-pt="${ptIdx}"]`).val();
        mapData[catIdx].points[ptIdx].x = parseFloat($(`.pt-x[data-cat="${catIdx}"][data-pt="${ptIdx}"]`).val()).toFixed(2);
        mapData[catIdx].points[ptIdx].y = parseFloat($(`.pt-y[data-cat="${catIdx}"][data-pt="${ptIdx}"]`).val()).toFixed(2);
        updateJson(); renderMarkers();
    });

    $(document).on('click', '.delete-pt', function() {
        if(confirm('Törlöd ezt a pontot?')) {
            mapData[$(this).data('cat')].points.splice($(this).data('pt'), 1);
            renderCategories();
        }
    });

    // --- TÖBBI ALAP ESEMÉNY ---
    $('#add-category-btn').click(function() {
        mapData.push({ name: 'Új Kategória', color: '#0073aa', iconUrl: '', layerUrl: '', iconSize: 28, desc: '', points: [] });
        activeCategoryIndex = mapData.length - 1;
        renderCategories();
    });

    $(document).on('click', '.category-item', function(e) {
        if(!$(e.target).is('input, button, img')) { activeCategoryIndex = $(this).data('index'); renderCategories(); }
    });

    $(document).on('click', '.upload-icon-btn', function(e) {
        e.preventDefault(); e.stopPropagation(); var index = $(this).data('index');
        var iconUploader = wp.media({ title: 'Válassz Ikont', multiple: false }).on('select', function() {
            mapData[index].iconUrl = iconUploader.state().get('selection').first().toJSON().url;
            renderCategories();
        }).open();
    });

    $(document).on('click', '.delete-cat', function(e) {
        e.stopPropagation();
        if(confirm('Törlöd a kategóriát?')) { mapData.splice($(this).closest('.category-item').data('index'), 1); activeCategoryIndex = null; renderCategories(); }
    });

    function renderMarkers() {
        var markersHtml = '';
        mapData.forEach(function(cat, catIdx) {
            if(!cat.points) return;
            var size = cat.iconSize || 28;
            cat.points.forEach(function(point, ptIdx) {
                // 80%-os ikonméret beállítva!
                var content = `<div style="width:100%; height:100%; background:${cat.color}; border:2px solid #fff; border-radius:50%; box-shadow:0 2px 4px rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                        ${cat.iconUrl ? `<img src="${cat.iconUrl}" style="width:80%; height:80%; object-fit:contain; pointer-events:none;">` : ''}
                    </div>`;
                markersHtml += `<div class="admin-draggable-marker" data-cat="${catIdx}" data-pt="${ptIdx}" title="${point.label}" style="position:absolute; top:${point.y}%; left:${point.x}%; width:${size}px; height:${size}px; transform:translate(-50%, -50%); cursor:move; pointer-events:auto;">${content}</div>`;
            });
        });
        $('#map-markers-layer').html(markersHtml);

        $('.admin-draggable-marker').draggable({
            containment: "#map-canvas-img",
            stop: function(event, ui) {
                var catIdx = $(this).data('cat'); var ptIdx = $(this).data('pt'); var parent = $('#map-canvas');
                mapData[catIdx].points[ptIdx].x = ( (ui.position.left + ($(this).width()/2)) / parent.width() * 100 ).toFixed(2);
                mapData[catIdx].points[ptIdx].y = ( (ui.position.top + ($(this).height()/2)) / parent.height() * 100 ).toFixed(2);
                renderCategories();
            }
        });
    }

    $('#map-canvas-img').click(function(e) {
        if (activeCategoryIndex === null) { alert('Válassz kategóriát!'); return; }
        var offset = $(this).offset();
        var pointLabel = prompt('Helyszín neve:');
        if(pointLabel) {
            if(!mapData[activeCategoryIndex].points) mapData[activeCategoryIndex].points = [];
            mapData[activeCategoryIndex].points.push({ 
                x: (((e.pageX - offset.left) / $(this).width()) * 100).toFixed(2), 
                y: (((e.pageY - offset.top) / $(this).height()) * 100).toFixed(2), 
                label: pointLabel 
            });
            renderCategories();
        }
    });

    // --- Overlay / GPS konverter kód ugyanaz (rövidítve) ---
    function renderOverlay() {
        var overlayUrl = $('#map_overlay_image').val(); var overlayImg = $('#map-overlay-layer');
        if(overlayUrl) {
            overlayImg.attr('src', overlayUrl).css({ display: 'block', left: overlayData.x + '%', top: overlayData.y + '%', width: overlayData.w + '%' });
            $('#ov_x').val(overlayData.x); $('#ov_y').val(overlayData.y); $('#ov_w').val(overlayData.w); $('#overlay-manual-controls').show();
        } else { overlayImg.hide(); $('#overlay-manual-controls').hide(); }
    }
    $('#ov_x, #ov_y, #ov_w').on('input', function() {
        overlayData.x = parseFloat($('#ov_x').val()) || 0; overlayData.y = parseFloat($('#ov_y').val()) || 0; overlayData.w = parseFloat($('#ov_w').val()) || 30;
        $('#map_overlay_data').val(JSON.stringify(overlayData));
        $('#map-overlay-layer').css({ left: overlayData.x + '%', top: overlayData.y + '%', width: overlayData.w + '%' });
    });

    function parseCoordinate(c) { var p = c.replace(/[^\d.,-]/g, '').split(','); return p.length >= 2 ? { lat: parseFloat(p[0]), lng: parseFloat(p[1]) } : null; }
    function calculatePercentFromGPS(lat, lng) {
        var tlStr = $('#map_gps_tl').val(); var brStr = $('#map_gps_br').val();
        if(!tlStr || !brStr) { alert('Hiba: Töltsd ki a kalibrációs adatokat!'); return null; }
        var tl = parseCoordinate(tlStr); var br = parseCoordinate(brStr);
        if(!tl || !br || isNaN(tl.lat) || isNaN(br.lat)) { alert('Hiba: Kalibrációs formátum hibás.'); return null; }
        var xP = ((lng - tl.lng) / (br.lng - tl.lng)) * 100; var yP = ((tl.lat - lat) / (tl.lat - br.lat)) * 100;
        return { x: xP.toFixed(2), y: yP.toFixed(2) };
    }
    $(document).on('click', '.add-gps-point-btn', function(e) {
        e.stopPropagation(); var index = $(this).data('index');
        var coordInput = prompt('Google Maps koordináta (Lat, Lng):');
        if(coordInput) {
            var targetCoords = parseCoordinate(coordInput);
            if(targetCoords) {
                var percents = calculatePercentFromGPS(targetCoords.lat, targetCoords.lng);
                if(percents) {
                    var pointLabel = prompt('Helyszín neve:');
                    if(pointLabel !== null) {
                        if(!mapData[index].points) mapData[index].points = [];
                        mapData[index].points.push({ x: percents.x, y: percents.y, label: pointLabel });
                        renderCategories();
                    }
                }
            } else { alert('Érvénytelen formátum!'); }
        }
    });

    function updateJson() { var jsonStr = JSON.stringify(mapData); $('#map_data_json').val(jsonStr); $('#map_import_export_field').val(jsonStr); }

    $('#import-json-btn').click(function() {
        try { var parsedData = JSON.parse($('#map_import_export_field').val());
            if (Array.isArray(parsedData)) { mapData = parsedData; updateJson(); renderCategories(); alert('Sikeres importálás!'); } else { alert('Hibás formátum.'); }
        } catch (e) { alert('Érvénytelen JSON.'); }
    });

    function initVisualEditor() {
        var baseImgSrc = $('#map_base_image').val();
        if(baseImgSrc) { $('#map-canvas-img').attr('src', baseImgSrc).on('load', function() { renderOverlay(); renderMarkers(); renderRouteLayers(); }); }
    }

    initVisualEditor(); renderCategories();
});