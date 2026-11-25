/**
 * USUClassMap — Admin Spatial Dashboard (Mapbox GL JS 3D Engine)
 * Synchronized with User Map (Google Satellite Hybrid, 3D Mode, Reset Camera, 31 Photocopy Partners, WebGL Guard)
 * ZERO FEATURE LOSS: Draggable Admin Marker, Edit Marker GPS, Context Selector, Alpine Event Dispatching
 */

import mapboxgl from 'mapbox-gl';

// Bersihkan konteks WebGL saat berpindah atau me-refresh halaman agar tidak exhaust WebGL
window.__usuAdminMapDestroyed = false;
window.addEventListener('beforeunload', () => {
    window.__usuAdminMapDestroyed = true;
    if (window.__usuAdminMapInstance) {
        try {
            window.__usuAdminMapInstance.remove();
        } catch (e) {}
        window.__usuAdminMapInstance = null;
    }
});

function initAdminMapEngine() {
    const config = window.ADMIN_MAP_CONFIG;
    if (!config) {
        console.error('[USUClassMap Admin] ADMIN_MAP_CONFIG not found.');
        return;
    }

    const mapContainer = document.getElementById('admin-map');
    if (!mapContainer) return;

    // Pastikan container memiliki dimensi valid (> 0px)
    if (mapContainer.clientWidth === 0 || mapContainer.clientHeight === 0) {
        mapContainer.style.minHeight = '450px';
        mapContainer.style.height = '100%';
        mapContainer.style.width = '100%';
    }

    // =========================================================================
    // 1. INJECT MAP CSS STYLES FOR ADMIN TOOLTIPS, MARKERS & PULSE ANIMATIONS
    // =========================================================================
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes adminPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }
        @keyframes editPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            50% { box-shadow: 0 0 0 16px rgba(245, 158, 11, 0); }
        }
        .admin-marker-badge {
            font-weight: 700;
            color: #1e40af;
            background: white;
            padding: 5px 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.18);
            border: 2px solid #D4A017;
            font-size: 11px;
            white-space: nowrap;
            margin-bottom: 6px;
            pointer-events: none;
        }
        .edit-marker-badge {
            font-weight: 700;
            color: #92400e;
            background: #fffbeb;
            padding: 5px 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.18);
            border: 2px solid #f59e0b;
            font-size: 11px;
            white-space: nowrap;
            margin-bottom: 6px;
            pointer-events: none;
        }
        .custom-mapbox-marker {
            background: transparent;
            border: none;
        }
        .usu-custom-popup .mapboxgl-popup-content {
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .usu-custom-popup .mapboxgl-popup-close-button {
            padding: 4px 8px;
            color: #64748b;
            font-size: 16px;
            font-weight: bold;
            z-index: 10;
        }
    `;
    document.head.appendChild(style);

    // =========================================================================
    // 2. STATE & CONFIGURATION (SYNCHRONIZED WITH USER MAP)
    // =========================================================================
    const DEFAULT_CENTER = [98.656520, 3.565245];
    const DEFAULT_ZOOM = 16;
    const DEFAULT_PITCH = 50;
    const DEFAULT_BEARING = -15;
    const DEFAULT_MAP_STYLE = 'mapbox://styles/mapbox/streets-v12';
    const SATELLITE_STORAGE_KEY = 'usuclassmap_satellite_mode';
    const SATELLITE_SOURCE_ID = 'google-satellite-source';
    const SATELLITE_LAYER_ID = 'google-satellite-layer';

    let is3DMode = true;
    let isSatelliteMode = false;
    try {
        const savedSatellite = localStorage.getItem(SATELLITE_STORAGE_KEY);
        if (savedSatellite === 'true') {
            isSatelliteMode = true;
        }
    } catch (e) {}

    let isPhotocopyLayerActive = false;
    let allPhotocopyPlaces = [];
    let photocopyMarkers = [];

    const token = config.mapboxToken 
        || window.MAPBOX_ACCESS_TOKEN 
        || (typeof import.meta !== 'undefined' && import.meta.env?.VITE_MAPBOX_ACCESS_TOKEN) 
        || '';

    const mb = window.mapboxgl || mapboxgl;
    if (!mb) {
        console.error('[USUClassMap Admin] Mapbox GL JS tidak terdeteksi.');
        renderFallbackMapError(new Error('Pustaka Mapbox GL JS tidak terdeteksi. Periksa koneksi internet Anda.'));
        return;
    }
    mb.accessToken = token;
    if (typeof mapboxgl !== 'undefined' && mapboxgl) {
        mapboxgl.accessToken = token;
    }

    // Cek dukungan WebGL sebelum instansiasi
    if (typeof mb.supported === 'function' && !mb.supported({ failIfMajorPerformanceCaveat: false })) {
        const err = new Error('Browser atau kartu grafis (GPU) Anda saat ini tidak mengizinkan inisialisasi WebGL.');
        console.error('[USUClassMap Admin]', err);
        renderFallbackMapError(err);
        return;
    }

    // Singleton cleanup
    if (window.__usuAdminMapInstance) {
        try {
            window.__usuAdminMapInstance.remove();
        } catch (e) {
            console.warn('[USUClassMap Admin] Cleanup instance lama:', e);
        }
        window.__usuAdminMapInstance = null;
    }

    // Spatial Routing Editor State (Tahap 4)
    const ADMIN_ROUTE_GRAPH_SOURCE = 'admin-routing-graph-source';
    const ADMIN_ROUTE_WALKWAYS_SOURCE = 'admin-routing-walkways-source';
    const ADMIN_ROUTE_BRIDGES_SOURCE = 'admin-routing-bridges-source';
    const ADMIN_ROUTE_BARRIERS_SOURCE = 'admin-routing-barriers-source';
    const ADMIN_ROUTE_DRAFT_SOURCE = 'admin-routing-draft-source';
    const ADMIN_ROUTE_ENTRANCES_SOURCE = 'admin-routing-entrances-source';

    let adminRoutingMode = null;
    let adminRoutingOverlayVisible = true;
    let adminRoutingDraftCoords = [];
    let adminRoutingPayload = {
        graphGeojson: { type: 'FeatureCollection', features: [] },
        overrides: [],
        buildings: [],
        photocopyPlaces: []
    };
    let adminEntrancePreviewMarker = null;

    // =========================================================================
    // 3. MAP INITIALIZATION (MAPBOX GL JS 3D)
    // =========================================================================
    let map = null;
    try {
        map = new mb.Map({
            container: 'admin-map',
            style: DEFAULT_MAP_STYLE,
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            pitch: is3DMode ? DEFAULT_PITCH : 0,
            bearing: is3DMode ? DEFAULT_BEARING : 0,
            antialias: true,
            failIfMajorPerformanceCaveat: false
        });

        window.__usuAdminMapInstance = map;

        // Tambahkan kontrol navigasi (Zoom & Compass/Pitch)
        map.addControl(new mb.NavigationControl({ visualizePitch: true }), 'top-right');

        // Saat style dimuat, pasang layer Satelit, 3D Extrusion & Overlay Graf Routing
        map.on('style.load', () => {
            injectSatelliteLayer();
            inject3DBuildingsLayer();
            ensureAdminRoutingLayers();
            refreshAdminRoutingSources();
        });

        updateSatelliteButtonState();
        updatePerspectiveButtonState();

        // Load photocopy places count for HUD badge
        loadPhotocopyPlaces();

    } catch (err) {
        console.error('[USUClassMap Admin] Inisialisasi peta 3D/WebGL gagal:', err);
        renderFallbackMapError(err);
        return;
    }

    // =========================================================================
    // 4. SPATIAL LAYERS: GOOGLE SATELLITE HYBRID & 3D BUILDINGS
    // =========================================================================
    function injectSatelliteLayer() {
        if (!map) return;

        if (!map.getSource(SATELLITE_SOURCE_ID)) {
            map.addSource(SATELLITE_SOURCE_ID, {
                type: 'raster',
                tiles: [
                    'https://mt0.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                    'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                    'https://mt2.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                    'https://mt3.google.com/vt/lyrs=y&x={x}&y={y}&z={z}'
                ],
                tileSize: 256,
                attribution: '&copy; Google Maps'
            });
        }

        if (!map.getLayer(SATELLITE_LAYER_ID)) {
            const beforeLayerId = map.getLayer('3d-buildings') ? '3d-buildings' : undefined;
            map.addLayer({
                id: SATELLITE_LAYER_ID,
                type: 'raster',
                source: SATELLITE_SOURCE_ID,
                layout: {
                    visibility: isSatelliteMode ? 'visible' : 'none'
                },
                paint: {
                    'raster-opacity': 1.0,
                    'raster-fade-duration': 200
                }
            }, beforeLayerId);
        } else {
            map.setLayoutProperty(SATELLITE_LAYER_ID, 'visibility', isSatelliteMode ? 'visible' : 'none');
        }
    }

    function inject3DBuildingsLayer() {
        if (!map) return;
        const layers = map.getStyle()?.layers || [];
        const labelLayerId = layers.find(
            (layer) => layer.type === 'symbol' && layer.layout && layer.layout['text-field']
        )?.id;

        if (!map.getLayer('3d-buildings')) {
            map.addLayer({
                'id': '3d-buildings',
                'source': 'composite',
                'source-layer': 'building',
                'filter': ['==', 'extrude', 'true'],
                'type': 'fill-extrusion',
                'minzoom': 14,
                'paint': {
                    'fill-extrusion-color': '#d1d5db',
                    'fill-extrusion-height': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        14,
                        0,
                        15.05,
                        ['get', 'height']
                    ],
                    'fill-extrusion-base': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        14,
                        0,
                        15.05,
                        ['get', 'min_height']
                    ],
                    'fill-extrusion-opacity': 0.65
                },
                'layout': {
                    'visibility': (is3DMode && !isSatelliteMode) ? 'visible' : 'none'
                }
            }, labelLayerId);
        } else {
            map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
        }
    }

    function renderFallbackMapError(error) {
        const el = document.getElementById('admin-map') || mapContainer;
        if (!el) return;

        // Auto-Fallback ke Leaflet 2D OSM jika pustaka Leaflet tersedia
        if (window.L && typeof window.L.map === 'function') {
            try {
                console.info('[USUClassMap Admin] Menjalankan Auto-Fallback Leaflet 2D OSM...');
                el.innerHTML = '';
                const lMap = window.L.map('admin-map', {
                    center: [DEFAULT_CENTER[1], DEFAULT_CENTER[0]],
                    zoom: DEFAULT_ZOOM,
                    zoomControl: true
                });
                const osmLayer = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19
                });
                const googleSatLayer = window.L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                    attribution: '&copy; Google Maps',
                    maxZoom: 20
                });
                if (isSatelliteMode) {
                    googleSatLayer.addTo(lMap);
                } else {
                    osmLayer.addTo(lMap);
                }

                const lAdminPin = window.L.marker([DEFAULT_CENTER[1], DEFAULT_CENTER[0]], { draggable: true }).addTo(lMap);
                lAdminPin.on('dragend', (ev) => {
                    const pos = ev.target.getLatLng();
                    window.dispatchEvent(new CustomEvent('coordinate-updated', {
                        detail: { lat: pos.lat.toFixed(8), lng: pos.lng.toFixed(8) }
                    }));
                });
                lMap.on('click', (ev) => {
                    lAdminPin.setLatLng(ev.latlng);
                    window.dispatchEvent(new CustomEvent('coordinate-updated', {
                        detail: { lat: ev.latlng.lat.toFixed(8), lng: ev.latlng.lng.toFixed(8) }
                    }));
                });

                if (Array.isArray(config.buildings)) {
                    config.buildings.forEach(b => {
                        const bLat = parseFloat(b.latitude);
                        const bLng = parseFloat(b.longitude);
                        if (!isNaN(bLat) && !isNaN(bLng)) {
                            window.L.circleMarker([bLat, bLng], {
                                radius: 8,
                                fillColor: '#10A760',
                                color: '#ffffff',
                                weight: 2,
                                fillOpacity: 0.9
                            }).addTo(lMap).bindPopup(`<b>${b.name}</b><br>Kode: ${b.code}`);
                        }
                    });
                }

                window.USUAdminMap = {
                    togglePerspective: () => {},
                    toggleSatelliteMode: () => {
                        isSatelliteMode = !isSatelliteMode;
                        updateSatelliteButtonState();
                        if (isSatelliteMode) {
                            lMap.removeLayer(osmLayer);
                            googleSatLayer.addTo(lMap);
                        } else {
                            lMap.removeLayer(googleSatLayer);
                            osmLayer.addTo(lMap);
                        }
                    },
                    resetCampusView: () => lMap.setView([DEFAULT_CENTER[1], DEFAULT_CENTER[0]], DEFAULT_ZOOM),
                    togglePhotocopyLayer: () => {},
                    retryInitMap: () => initAdminMapEngine(),
                    getMap: () => lMap
                };
                window.USUClassMap = window.USUAdminMap;
                return;
            } catch (leafletErr) {
                console.error('[USUClassMap Admin] Leaflet fallback gagal:', leafletErr);
            }
        }

        const errMsg = (error && error.message) ? error.message : String(error || '');
        const isWebGLError = errMsg.toLowerCase().includes('webgl') || errMsg.toLowerCase().includes('grafis');

        const errorTitle = isWebGLError 
            ? 'Akselerasi Grafis (WebGL) Terkendala' 
            : 'Gagal Memuat Engine Peta Admin 3D';
            
        const errorDesc = isWebGLError
            ? 'Browser Anda kehabisan konteks grafis WebGL atau akselerasi hardware sedang nonaktif. Klik tombol "Muat Ulang Peta" di bawah untuk mencoba kembali.'
            : (errMsg || 'Periksa token Mapbox atau koneksi internet perangkat Anda.');

        el.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full w-full min-h-[450px] p-6 text-center bg-slate-50 border border-slate-200 rounded-2xl shadow-inner z-10">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mb-4 shadow-sm ring-8 ring-amber-50">
                    <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                </div>
                <h3 class="text-base font-bold text-gray-800">${errorTitle}</h3>
                <p class="text-xs text-gray-600 max-w-md mt-2 leading-relaxed">${errorDesc}</p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <button 
                        type="button" 
                        onclick="if(window.USUAdminMap && window.USUAdminMap.retryInitMap) { window.USUAdminMap.retryInitMap(); } else { location.reload(); }" 
                        class="px-5 py-2.5 bg-[#10A760] text-white text-xs font-bold rounded-xl shadow-md hover:bg-[#0D8A4E] transition-all flex items-center gap-2 cursor-pointer active:scale-95"
                    >
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>Muat Ulang Peta</span>
                    </button>
                    <button 
                        type="button" 
                        onclick="location.reload()" 
                        class="px-4 py-2.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-all cursor-pointer"
                    >
                        Refresh Halaman
                    </button>
                </div>
                <div class="mt-4 text-[11px] text-gray-400 font-mono">
                    Detail: ${errMsg || 'WebGL_Init_Failure'}
                </div>
            </div>
        `;
    }

    // =========================================================================
    // 5. ICONS FACTORIES (DOM ELEMENTS FOR MAPBOX MARKERS)
    // =========================================================================
    function createGreenMarkerEl() {
        const el = document.createElement('div');
        el.className = 'cursor-pointer';
        el.innerHTML = `
            <div style="background:#10A760;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;transition:transform 0.15s ease;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="white">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        `;
        return el;
    }

    function createEditMarkerEl() {
        const el = document.createElement('div');
        el.className = 'flex flex-col items-center cursor-move';
        el.innerHTML = `
            <div class="edit-marker-badge">
                ✏️ Geser untuk ubah GPS
            </div>
            <div style="background:#f59e0b;width:38px;height:38px;border-radius:50%;border:4px solid white;box-shadow:0 4px 15px rgba(245,158,11,0.6);display:flex;align-items:center;justify-content:center;animation:editPulse 1.5s infinite;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </div>
        `;
        return el;
    }

    function createAdminMarkerEl() {
        const el = document.createElement('div');
        el.className = 'flex flex-col items-center cursor-move';
        el.innerHTML = `
            <div class="admin-marker-badge">
                📍 Geser untuk atur posisi gedung baru
            </div>
            <div style="background:#D4A017;width:40px;height:40px;border-radius:50%;border:4px solid white;box-shadow:0 4px 15px rgba(212,160,23,0.5);display:flex;align-items:center;justify-content:center;animation:adminPulse 2s infinite;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="1">
                    <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        `;
        return el;
    }

    function createPhotocopyMarkerElement(name) {
        const el = document.createElement('div');
        el.className = 'custom-mapbox-marker photocopy-marker z-30';
        el.style.display = 'block';
        el.style.width = 'max-content';
        el.style.height = 'auto';
        el.style.zIndex = '30';
        el.style.cursor = 'pointer';

        const shortName = name.length > 20 ? name.substring(0, 18) + '...' : name;

        el.innerHTML = `
            <div class="relative flex flex-col items-center group cursor-pointer" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.28));">
                <div class="px-2.5 py-1.5 bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white text-[11px] font-black rounded-lg border-2 border-white whitespace-nowrap flex items-center gap-1.5 transition-all transform group-hover:scale-105 shadow-md">
                    <i class="fa-solid fa-print text-xs text-emerald-200"></i>
                    <span class="tracking-tight text-white">${shortName}</span>
                </div>
                <div class="w-2.5 h-2.5 bg-cyan-600 transform rotate-45 -mt-1 border-r border-b border-white"></div>
            </div>
        `;
        return el;
    }

    // =========================================================================
    // 6. BUILDING MARKERS & STATE (CORE ADMIN CRUD)
    // =========================================================================
    const buildingMarkers = {}; // id -> { marker, data }
    let editState = {
        active: false,
        buildingId: null,
        marker: null,
        originalLngLat: null
    };

    function addBuildingMarker(building) {
        if (!building.latitude || !building.longitude) return;

        const lng = parseFloat(building.longitude);
        const lat = parseFloat(building.latitude);
        if (isNaN(lng) || isNaN(lat)) return;

        const el = createGreenMarkerEl();

        const popupContent = `
            <div style="min-width:180px;padding:4px">
                <b style="font-size:13px;color:#111827">${building.name}</b><br>
                <span style="color:#6b7280;font-size:11px">Kode: ${building.code}</span><br>
                <span style="color:#10A760;font-size:11px;font-weight:600">🏛️ ${building.faculty || 'Universitas'}</span>
                <div style="margin-top:8px;display:flex;gap:4px">
                    <button onclick="window.__triggerBuildingContext(${building.id})" style="flex:1;padding:6px 8px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px">
                        🎯 Pilih Konteks
                    </button>
                </div>
            </div>
        `;

        const popup = new mb.Popup({ offset: [0, -20], closeButton: true }).setHTML(popupContent);

        const marker = new mb.Marker({ element: el, anchor: 'center', draggable: false })
            .setLngLat([lng, lat])
            .setPopup(popup)
            .addTo(map);

        el.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                window.__triggerBuildingContext(building.id);
            }
        });

        buildingMarkers[building.id] = { marker, data: { ...building } };
    }

    window.__triggerBuildingContext = function(buildingId) {
        const entry = buildingMarkers[buildingId];
        if (!entry) return;
        enterEditMode(entry.data, entry.marker, buildingId);
    };

    // Load initial markers
    if (Array.isArray(config.buildings)) {
        config.buildings.forEach(b => addBuildingMarker(b));
    }

    // =========================================================================
    // 7. DRAGGABLE ADMIN MARKER (FOR NEW BUILDINGS)
    // =========================================================================
    const adminMarkerEl = createAdminMarkerEl();
    const adminMarker = new mb.Marker({
        element: adminMarkerEl,
        draggable: true,
        anchor: 'bottom'
    })
    .setLngLat(DEFAULT_CENTER)
    .addTo(map);

    adminMarker.on('dragend', function() {
        const lngLat = adminMarker.getLngLat();
        window.dispatchEvent(new CustomEvent('coordinate-updated', {
            detail: { lat: lngLat.lat.toFixed(8), lng: lngLat.lng.toFixed(8) }
        }));
    });

    // Klik peta untuk memindahkan pin aktif ATAU menggambar overlay routing admin
    map.on('click', function(e) {
        const origTarget = e.originalEvent?.target;
        if (origTarget && (origTarget.closest('.mapboxgl-marker') || origTarget.closest('.mapboxgl-popup'))) {
            return;
        }
        const clickedLng = e.lngLat.lng;
        const clickedLat = e.lngLat.lat;

        if (['walkway', 'bridge', 'barrier'].includes(adminRoutingMode)) {
            adminRoutingDraftCoords.push([
                Number(clickedLng.toFixed(7)),
                Number(clickedLat.toFixed(7))
            ]);
            refreshAdminRoutingDraftSource();
            window.dispatchEvent(new CustomEvent('admin-routing-draft-updated', {
                detail: { points: adminRoutingDraftCoords }
            }));
            return;
        }

        if (adminRoutingMode === 'entrance') {
            previewEntrancePinInternal(clickedLat, clickedLng, false);
            window.dispatchEvent(new CustomEvent('admin-routing-entrance-picked', {
                detail: { lat: clickedLat.toFixed(7), lng: clickedLng.toFixed(7) }
            }));
            return;
        }

        if (editState.active && editState.marker) {
            editState.marker.setLngLat([clickedLng, clickedLat]);
            window.dispatchEvent(new CustomEvent('coordinate-updated', {
                detail: { lat: clickedLat.toFixed(8), lng: clickedLng.toFixed(8) }
            }));
        } else if (adminMarker && adminMarker.getElement()?.isConnected) {
            adminMarker.setLngLat([clickedLng, clickedLat]);
            window.dispatchEvent(new CustomEvent('coordinate-updated', {
                detail: { lat: clickedLat.toFixed(8), lng: clickedLng.toFixed(8) }
            }));
        }
    });

    // =========================================================================
    // 8. EDIT MODE & DRAG LOGIC
    // =========================================================================
    function enterEditMode(buildingData, marker, buildingId) {
        if (editState.active) {
            exitEditMode(false);
        }

        adminMarker.remove();

        const currentLngLat = marker.getLngLat();

        editState = {
            active: true,
            buildingId: buildingId,
            marker: marker,
            originalLngLat: currentLngLat
        };

        window.dispatchEvent(new CustomEvent('edit-building', {
            detail: { id: buildingId, ...buildingData }
        }));

        marker.remove();

        const editEl = createEditMarkerEl();
        const editMarkerInstance = new mb.Marker({
            element: editEl,
            draggable: true,
            anchor: 'bottom'
        })
        .setLngLat(currentLngLat)
        .addTo(map);

        editMarkerInstance.on('dragend', function() {
            const lngLat = editMarkerInstance.getLngLat();
            window.dispatchEvent(new CustomEvent('coordinate-updated', {
                detail: { lat: lngLat.lat.toFixed(8), lng: lngLat.lng.toFixed(8) }
            }));
        });

        buildingMarkers[buildingId].marker = editMarkerInstance;
        editState.marker = editMarkerInstance;

        map.flyTo({
            center: [currentLngLat.lng, currentLngLat.lat],
            zoom: 17,
            pitch: DEFAULT_PITCH,
            bearing: DEFAULT_BEARING,
            duration: 800
        });
    }

    function exitEditMode(keepPosition) {
        if (!editState.active || !editState.marker) return;

        const currentMarker = editState.marker;
        const targetLngLat = keepPosition 
            ? currentMarker.getLngLat() 
            : editState.originalLngLat;

        currentMarker.remove();

        if (editState.buildingId && buildingMarkers[editState.buildingId]) {
            const bd = buildingMarkers[editState.buildingId].data;
            if (keepPosition) {
                bd.latitude = targetLngLat.lat;
                bd.longitude = targetLngLat.lng;
            }

            const greenEl = createGreenMarkerEl();
            const popupContent = `
                <div style="min-width:180px;padding:4px">
                    <b style="font-size:13px;color:#111827">${bd.name}</b><br>
                    <span style="color:#6b7280;font-size:11px">Kode: ${bd.code}</span><br>
                    <span style="color:#10A760;font-size:11px;font-weight:600">🏛️ ${bd.faculty || 'Universitas'}</span>
                    <div style="margin-top:8px;display:flex;gap:4px">
                        <button onclick="window.__triggerBuildingContext(${bd.id})" style="flex:1;padding:6px 8px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px">
                            🎯 Pilih Konteks
                        </button>
                    </div>
                </div>
            `;
            const popup = new mb.Popup({ offset: [0, -20], closeButton: true }).setHTML(popupContent);

            const greenMarker = new mb.Marker({ element: greenEl, anchor: 'center', draggable: false })
                .setLngLat(targetLngLat)
                .setPopup(popup)
                .addTo(map);

            greenEl.addEventListener('click', (e) => {
                if (e.target.tagName !== 'BUTTON') {
                    window.__triggerBuildingContext(bd.id);
                }
            });

            buildingMarkers[editState.buildingId].marker = greenMarker;
        }

        editState = {
            active: false,
            buildingId: null,
            marker: null,
            originalLngLat: null
        };

        adminMarker.setLngLat(map.getCenter()).addTo(map);
    }

    // =========================================================================
    // 9. LISTEN TO ALPINE EVENTS
    // =========================================================================
    window.addEventListener('cancel-edit-building', function() {
        exitEditMode(false);
    });

    window.addEventListener('building-updated-on-map', function(e) {
        const updated = e.detail;
        if (updated && buildingMarkers[updated.id]) {
            buildingMarkers[updated.id].data = { ...updated };
            exitEditMode(true);
        }
    });

    window.addEventListener('building-created-on-map', function(e) {
        const created = e.detail;
        if (created) {
            addBuildingMarker(created);
            adminMarker.setLngLat(map.getCenter());
        }
    });

    // Tab switch visibility for draggable new marker
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            if (tab === 'building') {
                if (!editState.active) {
                    adminMarker.setLngLat(map.getCenter()).addTo(map);
                }
            } else {
                adminMarker.remove();
            }
        });
    });

    // =========================================================================
    // 10. PHOTOCOPY LAYER (MITRA FOTOKOPI USU INTEGRATION)
    // =========================================================================
    async function loadPhotocopyPlaces() {
        if (allPhotocopyPlaces.length > 0) return allPhotocopyPlaces;
        try {
            const endpoint = config.endpoints?.copyShops || '/api/copy-shops?per_page=200';
            const resp = await fetch(endpoint);
            const json = await resp.json();
            allPhotocopyPlaces = json.data || [];
            const floatingBadge = document.getElementById('admin-floating-photocopy-count');
            if (floatingBadge) floatingBadge.textContent = allPhotocopyPlaces.length;
            return allPhotocopyPlaces;
        } catch (e) {
            console.error('[USUClassMap Admin] Gagal memuat data mitra fotokopi:', e);
            return [];
        }
    }

    function renderPhotocopyMarkers(places) {
        if (!map) return;

        if (Array.isArray(photocopyMarkers)) {
            photocopyMarkers.forEach(m => m.remove());
        }
        photocopyMarkers = [];

        if (!Array.isArray(places) || places.length === 0) return;

        places.forEach(place => {
            const lat = parseFloat(place.location?.latitude ?? place.latitude);
            const lng = parseFloat(place.location?.longitude ?? place.longitude);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            const iconEl = createPhotocopyMarkerElement(place.name || 'Mitra Fotokopi');

            const isOpen = (typeof place.status === 'object' && place.status !== null)
                ? (place.status.is_open_now ?? true)
                : (place.is_open_now ?? true);

            const statusBadge = isOpen
                ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-circle text-[8px] text-emerald-500"></i> Buka</span>`
                : `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200"><i class="fa-solid fa-circle text-[8px] text-red-500"></i> Tutup</span>`;

            const ratingScore = (typeof place.rating === 'object' && place.rating !== null)
                ? (place.rating.score || '4.8')
                : (place.rating || '4.8');

            const userRatingCount = (typeof place.rating === 'object' && place.rating !== null)
                ? (place.rating.user_rating_count || 50)
                : (place.user_rating_count || 50);

            const waUrl = (typeof place.contacts === 'object' && place.contacts !== null && place.contacts.whatsapp_url)
                ? place.contacts.whatsapp_url
                : (place.whatsapp_url || (place.whatsapp ? `https://wa.me/${String(place.whatsapp).replace(/[^0-9]/g, '')}` : 'https://wa.me/6285769737410'));

            const locationName = (typeof place.location === 'object' && place.location !== null && place.location.name)
                ? place.location.name
                : (place.location_name || place.address || '');

            const popupContent = `
                <div class="p-0 overflow-hidden font-sans">
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="font-bold text-sm text-gray-900 leading-tight">${place.name}</h4>
                            ${statusBadge}
                        </div>
                        <div class="flex items-center gap-1 text-xs text-amber-600 font-bold">
                            <span><i class="fa-solid fa-star"></i> ${ratingScore}</span>
                            <span class="text-gray-400 font-normal">(${userRatingCount} ulasan)</span>
                        </div>
                        <p class="text-xs text-gray-600 line-clamp-2">${locationName}</p>
                        <div class="pt-2 border-t border-gray-100 flex items-center gap-2">
                            <a href="/fotocopy/${place.id}" target="_blank" class="flex-1 py-1.5 px-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs rounded-lg text-center shadow-xs transition-colors">
                                Lihat & Pesan
                            </a>
                            <a href="${waUrl}" target="_blank" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold transition-colors" title="Chat WhatsApp">
                                <i class="fa-solid fa-comment-dots"></i> WA
                            </a>
                        </div>
                    </div>
                </div>
            `;

            const popup = new mb.Popup({
                className: 'custom-popup usu-custom-popup',
                maxWidth: '300px',
                offset: [0, -32],
                closeButton: true,
                closeOnClick: false,
                focusAfterOpen: false
            }).setHTML(popupContent);

            const marker = new mb.Marker({ element: iconEl, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .setPopup(popup)
                .addTo(map);

            photocopyMarkers.push(marker);
        });
    }

    async function togglePhotocopyLayer() {
        if (!map) return;
        isPhotocopyLayerActive = !isPhotocopyLayerActive;
        const btn = document.getElementById('admin-floating-photocopy-btn');

        if (isPhotocopyLayerActive) {
            const places = await loadPhotocopyPlaces();
            renderPhotocopyMarkers(places);
            if (btn) {
                btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600');
                btn.classList.remove('bg-white/95', 'text-gray-700');
            }
        } else {
            photocopyMarkers.forEach(m => m.remove());
            photocopyMarkers = [];
            if (btn) {
                btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
                btn.classList.add('bg-white/95', 'text-gray-700');
            }
        }
    }

    // =========================================================================
    // 11. HUD CONTROL METHODS & BUTTON STATE MANAGERS
    // =========================================================================
    function togglePerspective() {
        if (!map) return;
        is3DMode = !is3DMode;
        const targetPitch = is3DMode ? DEFAULT_PITCH : 0;
        const targetBearing = is3DMode ? DEFAULT_BEARING : 0;

        map.easeTo({
            pitch: targetPitch,
            bearing: targetBearing,
            duration: 1000
        });

        if (map.getLayer('3d-buildings')) {
            map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
        } else if (is3DMode && !isSatelliteMode) {
            inject3DBuildingsLayer();
        }

        updatePerspectiveButtonState();
    }

    function toggleSatelliteMode() {
        if (!map) return;
        isSatelliteMode = !isSatelliteMode;

        try {
            localStorage.setItem(SATELLITE_STORAGE_KEY, isSatelliteMode ? 'true' : 'false');
        } catch (e) {}

        updateSatelliteButtonState();

        if (map.getLayer(SATELLITE_LAYER_ID)) {
            map.setLayoutProperty(SATELLITE_LAYER_ID, 'visibility', isSatelliteMode ? 'visible' : 'none');
        } else {
            injectSatelliteLayer();
        }

        if (map.getLayer('3d-buildings')) {
            map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
        }
    }

    function resetCampusView() {
        if (!map) return;
        is3DMode = true;
        map.flyTo({
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            pitch: DEFAULT_PITCH,
            bearing: DEFAULT_BEARING,
            essential: true,
            duration: 1500
        });

        updatePerspectiveButtonState();

        if (map.getLayer('3d-buildings')) {
            map.setLayoutProperty('3d-buildings', 'visibility', (!isSatelliteMode) ? 'visible' : 'none');
        } else if (!isSatelliteMode) {
            inject3DBuildingsLayer();
        }
    }

    function updatePerspectiveButtonState() {
        const labelEl = document.getElementById('admin-perspective-label');
        if (labelEl) {
            labelEl.textContent = is3DMode ? 'Mode 3D' : 'Mode 2D';
        }
        const btn = document.getElementById('admin-perspective-toggle-btn');
        if (btn) {
            if (is3DMode) {
                btn.classList.add('text-[#10A760]', 'border-[#10A760]');
            } else {
                btn.classList.remove('text-[#10A760]', 'border-[#10A760]');
            }
        }
    }

    function updateSatelliteButtonState() {
        const btn = document.getElementById('admin-satellite-toggle-btn');
        const label = document.getElementById('admin-satellite-label');
        const icon = document.getElementById('admin-satellite-icon');

        if (!btn) return;

        if (isSatelliteMode) {
            btn.classList.add('bg-slate-900', 'text-white', 'border-slate-700', 'shadow-slate-900/30');
            btn.classList.remove('bg-white/95', 'text-gray-700', 'border-gray-200');
            if (icon) {
                icon.classList.remove('text-cyan-600');
                icon.classList.add('text-cyan-400');
            }
            if (label) {
                label.textContent = 'Mode Satelit';
            }
        } else {
            btn.classList.remove('bg-slate-900', 'text-white', 'border-slate-700', 'shadow-slate-900/30');
            btn.classList.add('bg-white/95', 'text-gray-700', 'border-gray-200');
            if (icon) {
                icon.classList.remove('text-cyan-400');
                icon.classList.add('text-cyan-600');
            }
            if (label) {
                label.textContent = 'Satelit';
            }
        }
    }

    // =========================================================================
    // 11B. SPATIAL ROUTING OVERLAY & EDITOR HELPERS (TAHAP 4)
    // =========================================================================
    function ensureAdminRoutingLayers() {
        if (window.__usuAdminMapDestroyed || !map || !map.getCanvasContainer() || !map.isStyleLoaded()) {
            return false;
        }
        const emptyFC = { type: 'FeatureCollection', features: [] };

        [
            ADMIN_ROUTE_GRAPH_SOURCE,
            ADMIN_ROUTE_WALKWAYS_SOURCE,
            ADMIN_ROUTE_BRIDGES_SOURCE,
            ADMIN_ROUTE_BARRIERS_SOURCE,
            ADMIN_ROUTE_DRAFT_SOURCE,
            ADMIN_ROUTE_ENTRANCES_SOURCE
        ].forEach((srcId) => {
            if (!map.getSource(srcId)) {
                map.addSource(srcId, { type: 'geojson', data: emptyFC });
            }
        });

        const vis = adminRoutingOverlayVisible ? 'visible' : 'none';

        if (!map.getLayer('admin-routing-graph-edges')) {
            map.addLayer({
                id: 'admin-routing-graph-edges',
                type: 'line',
                source: ADMIN_ROUTE_GRAPH_SOURCE,
                layout: { 'line-join': 'round', 'line-cap': 'round', visibility: vis },
                paint: { 'line-color': '#06b6d4', 'line-width': 2, 'line-opacity': 0.55 }
            });
        }
        if (!map.getLayer('admin-routing-walkways-line')) {
            map.addLayer({
                id: 'admin-routing-walkways-line',
                type: 'line',
                source: ADMIN_ROUTE_WALKWAYS_SOURCE,
                layout: { 'line-join': 'round', 'line-cap': 'round', visibility: vis },
                paint: { 'line-color': '#10b981', 'line-width': 4, 'line-opacity': 0.95 }
            });
        }
        if (!map.getLayer('admin-routing-bridges-line')) {
            map.addLayer({
                id: 'admin-routing-bridges-line',
                type: 'line',
                source: ADMIN_ROUTE_BRIDGES_SOURCE,
                layout: { 'line-join': 'round', 'line-cap': 'round', visibility: vis },
                paint: { 'line-color': '#0284c7', 'line-width': 4.5, 'line-opacity': 0.95 }
            });
        }
        if (!map.getLayer('admin-routing-barriers-line')) {
            map.addLayer({
                id: 'admin-routing-barriers-line',
                type: 'line',
                source: ADMIN_ROUTE_BARRIERS_SOURCE,
                layout: { 'line-join': 'round', 'line-cap': 'round', visibility: vis },
                paint: { 'line-color': '#ef4444', 'line-width': 3.5, 'line-dasharray': [3, 2], 'line-opacity': 0.95 }
            });
        }
        if (!map.getLayer('admin-routing-entrances-points')) {
            map.addLayer({
                id: 'admin-routing-entrances-points',
                type: 'circle',
                source: ADMIN_ROUTE_ENTRANCES_SOURCE,
                layout: { visibility: vis },
                paint: {
                    'circle-radius': 6,
                    'circle-color': '#f59e0b',
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff'
                }
            });
        }
        if (!map.getLayer('admin-routing-draft-line')) {
            map.addLayer({
                id: 'admin-routing-draft-line',
                type: 'line',
                source: ADMIN_ROUTE_DRAFT_SOURCE,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#f59e0b', 'line-width': 4, 'line-dasharray': [2, 1] }
            });
        }
        if (!map.getLayer('admin-routing-draft-points')) {
            map.addLayer({
                id: 'admin-routing-draft-points',
                type: 'circle',
                source: ADMIN_ROUTE_DRAFT_SOURCE,
                filter: ['==', '$type', 'Point'],
                paint: {
                    'circle-radius': 5,
                    'circle-color': '#f59e0b',
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff'
                }
            });
        }

        return true;
    }

    function refreshAdminRoutingDraftSource() {
        if (window.__usuAdminMapDestroyed || !map || !map.getCanvasContainer()) return;
        ensureAdminRoutingLayers();
        const features = [];
        if (adminRoutingDraftCoords.length >= 2) {
            features.push({
                type: 'Feature',
                properties: {},
                geometry: { type: 'LineString', coordinates: adminRoutingDraftCoords }
            });
        }
        adminRoutingDraftCoords.forEach((pt) => {
            features.push({
                type: 'Feature',
                properties: {},
                geometry: { type: 'Point', coordinates: pt }
            });
        });
        const src = map.getSource(ADMIN_ROUTE_DRAFT_SOURCE);
        if (src) src.setData({ type: 'FeatureCollection', features });
    }

    function refreshAdminRoutingSources() {
        if (window.__usuAdminMapDestroyed || !map || !map.getCanvasContainer()) return;
        if (!ensureAdminRoutingLayers()) {
            setTimeout(refreshAdminRoutingSources, 250);
            return;
        }

        const graphSrc = map.getSource(ADMIN_ROUTE_GRAPH_SOURCE);
        if (graphSrc) {
            graphSrc.setData(adminRoutingPayload.graphGeojson || { type: 'FeatureCollection', features: [] });
        }

        const splitByType = (type) => ({
            type: 'FeatureCollection',
            features: (adminRoutingPayload.overrides || [])
                .filter((ov) => ov.type === type && Array.isArray(ov.coordinates) && ov.coordinates.length >= 2)
                .map((ov) => ({
                    type: 'Feature',
                    properties: { id: ov.id, name: ov.name || type },
                    geometry: { type: 'LineString', coordinates: ov.coordinates }
                }))
        });

        map.getSource(ADMIN_ROUTE_WALKWAYS_SOURCE)?.setData(splitByType('walkway'));
        map.getSource(ADMIN_ROUTE_BRIDGES_SOURCE)?.setData(splitByType('bridge'));
        map.getSource(ADMIN_ROUTE_BARRIERS_SOURCE)?.setData(splitByType('barrier'));

        const entranceFeatures = [];
        [...(adminRoutingPayload.buildings || []), ...(adminRoutingPayload.photocopyPlaces || [])].forEach((entity) => {
            const eLat = parseFloat(entity.entrance_lat);
            const eLng = parseFloat(entity.entrance_lng);
            if (Number.isFinite(eLat) && Number.isFinite(eLng)) {
                entranceFeatures.push({
                    type: 'Feature',
                    properties: { name: entity.name },
                    geometry: { type: 'Point', coordinates: [eLng, eLat] }
                });
            }
        });
        map.getSource(ADMIN_ROUTE_ENTRANCES_SOURCE)?.setData({ type: 'FeatureCollection', features: entranceFeatures });

        setRoutingOverlayVisibleInternal(adminRoutingOverlayVisible);
        refreshAdminRoutingDraftSource();
    }

    function setRoutingOverlayVisibleInternal(flag) {
        adminRoutingOverlayVisible = !!flag;
        if (window.__usuAdminMapDestroyed || !map || !map.getCanvasContainer()) return;
        const vis = adminRoutingOverlayVisible ? 'visible' : 'none';
        [
            'admin-routing-graph-edges',
            'admin-routing-walkways-line',
            'admin-routing-bridges-line',
            'admin-routing-barriers-line',
            'admin-routing-entrances-points'
        ].forEach((layerId) => {
            try {
                if (map.getLayer(layerId)) {
                    map.setLayoutProperty(layerId, 'visibility', vis);
                }
            } catch (e) {}
        });
    }

    function previewEntrancePinInternal(lat, lng, flyTo = true) {
        if (window.__usuAdminMapDestroyed || !map || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
        if (!adminEntrancePreviewMarker) {
            const el = document.createElement('div');
            el.className = 'px-2 py-1 rounded-full bg-amber-500 text-white text-[11px] font-black border-2 border-white shadow-lg';
            el.textContent = '🚪 Pintu Masuk';
            adminEntrancePreviewMarker = new mb.Marker({ element: el, anchor: 'bottom' });
        }
        adminEntrancePreviewMarker.setLngLat([lng, lat]).addTo(map);
        if (flyTo) {
            map.flyTo({ center: [lng, lat], zoom: 18, duration: 700 });
        }
    }

    // =========================================================================
    // 12. PUBLIC API ADAPTER: window.USUAdminMap & window.USUClassMap
    // =========================================================================
    window.USUAdminMap = {
        togglePerspective: () => togglePerspective(),
        toggleSatelliteMode: () => toggleSatelliteMode(),
        resetCampusView: () => resetCampusView(),
        togglePhotocopyLayer: () => togglePhotocopyLayer(),
        setRoutingMode: (mode) => {
            adminRoutingMode = mode || null;
            adminRoutingDraftCoords = [];
            refreshAdminRoutingDraftSource();
            if (adminRoutingMode !== 'entrance' && adminEntrancePreviewMarker) {
                try { adminEntrancePreviewMarker.remove(); } catch (e) {}
            }
            try {
                if (map && map.getCanvas()) {
                    map.getCanvas().style.cursor = adminRoutingMode ? 'crosshair' : '';
                }
            } catch (e) {}
        },
        setRoutingOverlayVisible: (flag) => setRoutingOverlayVisibleInternal(flag),
        renderRoutingOverlays: (payload = {}) => {
            adminRoutingPayload = {
                graphGeojson: payload.graphGeojson || { type: 'FeatureCollection', features: [] },
                overrides: Array.isArray(payload.overrides) ? payload.overrides : [],
                buildings: Array.isArray(payload.buildings) ? payload.buildings : [],
                photocopyPlaces: Array.isArray(payload.photocopyPlaces) ? payload.photocopyPlaces : []
            };
            if (typeof payload.visible === 'boolean') {
                adminRoutingOverlayVisible = payload.visible;
            }
            refreshAdminRoutingSources();
        },
        previewEntrancePin: (lat, lng, flyTo = true) => previewEntrancePinInternal(parseFloat(lat), parseFloat(lng), flyTo),
        undoRoutingDraftPoint: () => {
            adminRoutingDraftCoords.pop();
            refreshAdminRoutingDraftSource();
            window.dispatchEvent(new CustomEvent('admin-routing-draft-updated', {
                detail: { points: adminRoutingDraftCoords }
            }));
        },
        clearRoutingDraft: () => {
            adminRoutingDraftCoords = [];
            refreshAdminRoutingDraftSource();
            window.dispatchEvent(new CustomEvent('admin-routing-draft-updated', {
                detail: { points: adminRoutingDraftCoords }
            }));
        },
        focusCoord: (lng, lat, zoom = 18) => {
            if (!map) return;
            const nLng = parseFloat(lng);
            const nLat = parseFloat(lat);
            if (!Number.isFinite(nLng) || !Number.isFinite(nLat)) return;
            map.flyTo({ center: [nLng, nLat], zoom, duration: 750 });
        },
        retryInitMap: () => {
            const el = document.getElementById('admin-map');
            if (el) el.innerHTML = '';
            initAdminMapEngine();
        },
        getMap: () => map
    };

    // Alias compatibility
    window.USUClassMap = window.USUAdminMap;

    // =========================================================================
    // 13. GLOBAL TOAST NOTIFICATION HELPER
    // =========================================================================
    window.showAdminToast = function(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-amber-600';
        const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';

        toast.className = `${bgColor} text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-2.5 text-sm font-bold transform translate-x-full transition-transform duration-300 pointer-events-auto border border-white/20`;
        toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        });

        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    };
}

// Inisialisasi engine secara tangguh (anti-double initialization)
let isAdminMapEngineStarted = false;
function startAdminMapEngine() {
    if (isAdminMapEngineStarted) return;
    isAdminMapEngineStarted = true;
    initAdminMapEngine();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAdminMapEngine, { once: true });
} else {
    startAdminMapEngine();
}
