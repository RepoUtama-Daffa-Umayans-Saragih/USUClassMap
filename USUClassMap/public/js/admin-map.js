/**
 * USUClassMap — Admin Spatial Dashboard (Phase 7.2 Refactored)
 * Pure Leaflet Map Engine — Emits Events to Alpine.js & Manages Map Canvas
 */
document.addEventListener('DOMContentLoaded', function() {
    const config = window.ADMIN_MAP_CONFIG;
    if (!config) {
        console.error('ADMIN_MAP_CONFIG not found.');
        return;
    }

    // =========================================================================
    // 1. INJECT MAP CSS STYLES
    // =========================================================================
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }
        @keyframes editPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
            50% { box-shadow: 0 0 0 14px rgba(245, 158, 11, 0); }
        }
        .admin-marker-tooltip {
            font-weight: 700;
            color: #1e40af;
            background: white;
            padding: 6px 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid #D4A017;
            font-size: 12px;
        }
        .admin-marker-tooltip::before {
            border-top-color: #D4A017 !important;
        }
        .edit-marker-tooltip {
            font-weight: 700;
            color: #92400e;
            background: #fffbeb;
            padding: 6px 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid #f59e0b;
            font-size: 12px;
        }
        .edit-marker-tooltip::before {
            border-top-color: #f59e0b !important;
        }
    `;
    document.head.appendChild(style);

    // =========================================================================
    // 2. MAP INITIALIZATION
    // =========================================================================
    const mapContainer = document.getElementById('admin-map');
    if (!mapContainer) return;

    const map = L.map('admin-map', {
        zoomControl: false
    }).setView([3.565245, 98.656520], 16);

    L.control.zoom({ position: 'topright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | <b>USUClassMap</b>'
    }).addTo(map);

    // =========================================================================
    // 3. ICONS DEFINITION
    // =========================================================================
    const greenIcon = L.divIcon({
        html: '<div style="background:#10A760;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>',
        className: '',
        iconSize: [28, 28],
        iconAnchor: [14, 14]
    });

    const editIcon = L.divIcon({
        html: '<div style="background:#f59e0b;width:38px;height:38px;border-radius:50%;border:4px solid white;box-shadow:0 4px 15px rgba(245,158,11,0.6);display:flex;align-items:center;justify-content:center;animation:editPulse 1.5s infinite;"><svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>',
        className: '',
        iconSize: [38, 38],
        iconAnchor: [19, 19]
    });

    const adminIcon = L.divIcon({
        html: '<div style="background:#D4A017;width:40px;height:40px;border-radius:50%;border:4px solid white;box-shadow:0 4px 15px rgba(212,160,23,0.5);display:flex;align-items:center;justify-content:center;animation:pulse 2s infinite;"><svg width="20" height="20" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="1"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>',
        className: '',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });

    // =========================================================================
    // 4. MARKERS & STATE
    // =========================================================================
    const buildingMarkers = {}; // id -> { marker, data }
    let editState = {
        active: false,
        buildingId: null,
        marker: null,
        originalLatLng: null,
        dragListeners: {}
    };

    function addBuildingMarker(building) {
        if (!building.latitude || !building.longitude) return;

        const marker = L.marker([building.latitude, building.longitude], {
            icon: greenIcon,
            draggable: false
        }).addTo(map);

        marker.bindPopup(
            `<div style="min-width:180px;padding:2px">` +
            `<b style="font-size:13px;color:#111827">${building.name}</b><br>` +
            `<span style="color:#6b7280;font-size:11px">Kode: ${building.code}</span><br>` +
            `<span style="color:#10A760;font-size:11px;font-weight:600">🏛️ ${building.faculty || 'Universitas'}</span>` +
            `<div style="margin-top:8px;display:flex;gap:4px">` +
            `<button onclick="window.__triggerBuildingContext(${building.id})" ` +
            `style="flex:1;padding:6px 8px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px">` +
            `🎯 Pilih Konteks</button>` +
            `</div></div>`
        );

        marker.on('click', function() {
            window.__triggerBuildingContext(building.id);
        });

        buildingMarkers[building.id] = { marker, data: { ...building } };
    }

    // Triggered when marker or popup button is clicked
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
    const adminMarker = L.marker([3.565245, 98.656520], {
        icon: adminIcon,
        draggable: true,
        autoPan: true,
        zIndexOffset: 1000
    }).addTo(map);

    adminMarker.bindTooltip('📍 Geser untuk atur posisi gedung baru', {
        permanent: true,
        direction: 'top',
        offset: [0, -25],
        className: 'admin-marker-tooltip'
    }).openTooltip();

    adminMarker.on('dragstart', function() {
        adminMarker.setTooltipContent('⏳ Menyesuaikan koordinat...');
        const el = adminMarker.getElement();
        if (el) {
            const div = el.querySelector('div');
            if (div) div.style.animation = 'none';
        }
    });

    adminMarker.on('drag', function(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        adminMarker.setTooltipContent(`📌 Koordinat:<br>Lat: <b>${lat}</b><br>Lng: <b>${lng}</b>`);
    });

    adminMarker.on('dragend', function(e) {
        const lat = e.target.getLatLng().lat;
        const lng = e.target.getLatLng().lng;

        // Emit coordinate update to Alpine
        window.dispatchEvent(new CustomEvent('coordinate-updated', {
            detail: { lat: lat.toFixed(8), lng: lng.toFixed(8) }
        }));

        adminMarker.setTooltipContent(`✅ Posisi diatur!<br>Lat: <b>${lat.toFixed(6)}</b><br>Lng: <b>${lng.toFixed(6)}</b>`);
        const el = adminMarker.getElement();
        if (el) {
            const div = el.querySelector('div');
            if (div) div.style.animation = 'pulse 2s infinite';
        }
        setTimeout(() => {
            adminMarker.setTooltipContent('📍 Geser untuk atur posisi gedung baru');
        }, 3000);
    });

    // =========================================================================
    // 6. EDIT MODE & DRAG LOGIC (LEAFLET SIDE)
    // =========================================================================
    function enterEditMode(buildingData, marker, buildingId) {
        if (editState.active) {
            exitEditMode(false);
        }

        if (map.hasLayer(adminMarker)) {
            map.removeLayer(adminMarker);
        }

        map.closePopup();

        editState = {
            active: true,
            buildingId: buildingId,
            marker: marker,
            originalLatLng: L.latLng(buildingData.latitude, buildingData.longitude),
            dragListeners: {}
        };

        // 1. Dispatch CustomEvent to Alpine (Decoupled, Pure Event-Driven)
        window.dispatchEvent(new CustomEvent('edit-building', {
            detail: { id: buildingId, ...buildingData }
        }));

        // 2. Change marker icon to edit pulse icon & enable drag
        marker.setIcon(editIcon);
        marker.dragging.enable();
        marker.setZIndexOffset(1000);

        // 3. Set tooltip
        if (marker.getTooltip()) marker.unbindTooltip();
        marker.bindTooltip(`✏️ Mengedit: <b>${buildingData.name}</b><br>Geser untuk ubah posisi GPS`, {
            permanent: true,
            direction: 'top',
            offset: [0, -22],
            className: 'edit-marker-tooltip'
        }).openTooltip();

        map.panTo(marker.getLatLng(), { animate: true });

        // 4. Attach drag events
        const onDragStart = function() {
            marker.setTooltipContent('⏳ Menyesuaikan koordinat...');
        };

        const onDrag = function(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            marker.setTooltipContent(`📌 Koordinat Baru:<br>Lat: <b>${lat}</b><br>Lng: <b>${lng}</b>`);
        };

        const onDragEnd = function(e) {
            const lat = e.target.getLatLng().lat;
            const lng = e.target.getLatLng().lng;

            // Dispatch to Alpine
            window.dispatchEvent(new CustomEvent('coordinate-updated', {
                detail: { lat: lat.toFixed(8), lng: lng.toFixed(8) }
            }));

            marker.setTooltipContent(`✅ Posisi diperbarui!<br>Lat: <b>${lat.toFixed(6)}</b><br>Lng: <b>${lng.toFixed(6)}</b>`);
            setTimeout(() => {
                if (editState.active) {
                    marker.setTooltipContent(`✏️ Mengedit: <b>${buildingData.name}</b><br>Geser untuk ubah posisi GPS`);
                }
            }, 3000);
        };

        marker.on('dragstart', onDragStart);
        marker.on('drag', onDrag);
        marker.on('dragend', onDragEnd);

        editState.dragListeners = { onDragStart, onDrag, onDragEnd };
    }

    function exitEditMode(keepPosition) {
        if (!editState.active || !editState.marker) return;

        const marker = editState.marker;

        if (editState.dragListeners.onDragStart) {
            marker.off('dragstart', editState.dragListeners.onDragStart);
            marker.off('drag', editState.dragListeners.onDrag);
            marker.off('dragend', editState.dragListeners.onDragEnd);
        }

        marker.dragging.disable();
        marker.setIcon(greenIcon);
        marker.setZIndexOffset(0);

        if (marker.getTooltip()) marker.unbindTooltip();

        if (!keepPosition && editState.originalLatLng) {
            marker.setLatLng(editState.originalLatLng);
        }

        if (keepPosition && editState.buildingId && buildingMarkers[editState.buildingId]) {
            const newLatLng = marker.getLatLng();
            buildingMarkers[editState.buildingId].data.latitude = newLatLng.lat;
            buildingMarkers[editState.buildingId].data.longitude = newLatLng.lng;
        }

        if (editState.buildingId && buildingMarkers[editState.buildingId]) {
            const bd = buildingMarkers[editState.buildingId].data;
            marker.bindPopup(
                `<div style="min-width:180px;padding:2px">` +
                `<b style="font-size:13px;color:#111827">${bd.name}</b><br>` +
                `<span style="color:#6b7280;font-size:11px">Kode: ${bd.code}</span><br>` +
                `<span style="color:#10A760;font-size:11px;font-weight:600">🏛️ ${bd.faculty || 'Universitas'}</span>` +
                `<div style="margin-top:8px;display:flex;gap:4px">` +
                `<button onclick="window.__triggerBuildingContext(${bd.id})" ` +
                `style="flex:1;padding:6px 8px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px">` +
                `🎯 Pilih Konteks</button>` +
                `</div></div>`
            );
        }

        editState = {
            active: false,
            buildingId: null,
            marker: null,
            originalLatLng: null,
            dragListeners: {}
        };

        if (!map.hasLayer(adminMarker)) {
            adminMarker.addTo(map);
            adminMarker.setLatLng(map.getCenter());
        }
    }

    // =========================================================================
    // 7. LISTEN TO ALPINE EVENTS
    // =========================================================================

    // Cancel edit requested by Alpine button
    window.addEventListener('cancel-edit-building', function() {
        exitEditMode(false);
    });

    // Building updated on map
    window.addEventListener('building-updated-on-map', function(e) {
        const updated = e.detail;
        if (updated && buildingMarkers[updated.id]) {
            buildingMarkers[updated.id].data = { ...updated };
            exitEditMode(true);
        }
    });

    // New building created on map
    window.addEventListener('building-created-on-map', function(e) {
        const created = e.detail;
        if (created) {
            addBuildingMarker(created);
            adminMarker.setLatLng(map.getCenter());
        }
    });

    // Tab switch visibility for draggable new marker
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            if (tab === 'building') {
                if (!editState.active && !map.hasLayer(adminMarker)) {
                    adminMarker.addTo(map);
                    adminMarker.setLatLng(map.getCenter());
                }
            } else {
                if (map.hasLayer(adminMarker)) {
                    map.removeLayer(adminMarker);
                }
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
