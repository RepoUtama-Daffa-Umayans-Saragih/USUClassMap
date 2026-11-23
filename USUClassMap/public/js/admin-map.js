/**
 * USUClassMap — Admin Spatial Dashboard (Mapbox GL JS 3D Engine - Standalone CDN Script)
 * Pure Mapbox GL JS 3D Engine — Emits Events to Alpine.js & Manages Admin Map Canvas
 */

document.addEventListener('DOMContentLoaded', function() {
    const config = window.ADMIN_MAP_CONFIG;
    if (!config) {
        console.error('ADMIN_MAP_CONFIG not found.');
        return;
    }

    const mapContainer = document.getElementById('admin-map');
    if (!mapContainer) return;

    // =========================================================================
    // 1. INJECT MAP CSS STYLES FOR ADMIN TOOLTIPS & PULSE ANIMATIONS
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
    `;
    document.head.appendChild(style);

    // =========================================================================
    // 2. MAP INITIALIZATION (MAPBOX GL JS 3D)
    // =========================================================================
    const DEFAULT_CENTER = [98.656520, 3.565245];
    const DEFAULT_ZOOM = 16;
    const DEFAULT_PITCH = 50;
    const DEFAULT_BEARING = -15;

    const token = config.mapboxToken 
        || window.MAPBOX_ACCESS_TOKEN 
        || '';

    const mb = window.mapboxgl;
    if (!mb) {
        console.error('[AdminMap] window.mapboxgl CDN script belum selesai dimuat.');
        return;
    }
    mb.accessToken = token;

    const map = new mb.Map({
        container: 'admin-map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: DEFAULT_CENTER,
        zoom: DEFAULT_ZOOM,
        pitch: DEFAULT_PITCH,
        bearing: DEFAULT_BEARING,
        antialias: true
    });

    map.addControl(new mb.NavigationControl({ visualizePitch: true }), 'top-right');

    map.on('style.load', () => {
        // Injeksi layer 3D gedung extrusions
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
                }
            }, labelLayerId);
        }
    });

    // =========================================================================
    // 3. ICONS FACTORIES (DOM ELEMENTS FOR MAPBOX MARKERS)
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

    // =========================================================================
    // 4. MARKERS & STATE
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
    // 5. DRAGGABLE ADMIN MARKER (FOR NEW BUILDINGS)
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

    // =========================================================================
    // 6. EDIT MODE & DRAG LOGIC
    // =========================================================================
    function enterEditMode(buildingData, marker, buildingId) {
        if (editState.active) {
            exitEditMode(false);
        }

        // Sembunyikan admin marker baru sementara
        adminMarker.remove();

        const currentLngLat = marker.getLngLat();

        editState = {
            active: true,
            buildingId: buildingId,
            marker: marker,
            originalLngLat: currentLngLat
        };

        // 1. Dispatch CustomEvent to Alpine (Decoupled, Pure Event-Driven)
        window.dispatchEvent(new CustomEvent('edit-building', {
            detail: { id: buildingId, ...buildingData }
        }));

        // 2. Ganti elemen marker dengan edit icon dan jadikan draggable
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

        // Tampilkan kembali admin marker baru di center peta saat ini
        adminMarker.setLngLat(map.getCenter()).addTo(map);
    }

    // =========================================================================
    // 7. LISTEN TO ALPINE EVENTS
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
    // 8. GLOBAL TOAST NOTIFICATION HELPER
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
});
