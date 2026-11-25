<x-layouts.admin>
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <a href="{{ route('admin.buildings.index') }}" class="hover:text-emerald-600 transition-colors">Gedung</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-700">Edit Gedung</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Gedung: {{ $building->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">Perbarui data gedung dan sesuaikan titik lokasi pada peta interaktif 3D &amp; Satelit (Standar Peta User).</p>
    </div>

    <form method="POST" action="{{ route('admin.buildings.update', $building) }}">
        @csrf
        @method('PUT')
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            {{-- LEFT PANEL: Form Input Vertikal (±35%) --}}
            <div class="w-full lg:w-[35%]">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Edit Informasi Gedung
                        </h2>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Gedung <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $building->name) }}" required placeholder="Contoh: Gedung FASILKOM-TI" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kode Gedung <span class="text-red-500">*</span></label>
                        <input type="text" name="code" value="{{ old('code', $building->code) }}" required placeholder="Contoh: FST-A" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Fakultas / Unit</label>
                        <input type="text" name="faculty" value="{{ old('faculty', $building->faculty) }}" placeholder="Contoh: Fakultas Ilmu Komputer dan TI" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('faculty') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Latitude (Read-Only) 🔒</label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $building->latitude) }}" readonly required class="w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm px-3.5 py-2.5 border text-sm font-mono text-gray-700 cursor-not-allowed select-all focus:outline-hidden">
                            @error('latitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Longitude (Read-Only) 🔒</label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $building->longitude) }}" readonly required class="w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm px-3.5 py-2.5 border text-sm font-mono text-gray-700 cursor-not-allowed select-all focus:outline-hidden">
                            @error('longitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-lg flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-xs text-emerald-800 leading-relaxed">
                            <span class="font-semibold">The Magic UX:</span> Geser pin merah pada peta di sebelah kanan atau klik lokasi baru untuk memperbarui koordinat secara otomatis. Nyalakan <b>Mode Satelit</b> untuk melihat atap gedung.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jumlah Lantai <span class="text-red-500">*</span></label>
                        <input type="number" name="floor_count" value="{{ old('floor_count', $building->floor_count) }}" min="1" max="20" required class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900">
                        @error('floor_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="description" rows="3" placeholder="Deskripsi fasilitas atau catatan gedung..." class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">{{ old('description', $building->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                        <button type="submit" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm shadow-sm transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.buildings.index') }}" class="text-gray-500 hover:text-gray-800 text-sm font-medium transition-colors">Batal</a>
                    </div>
                </div>
            </div>

            {{-- RIGHT PANEL: Peta Interaktif Mapbox 3D + User HUD (±65%) --}}
            <div class="w-full lg:w-[65%]">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                            <h3 class="text-sm font-bold text-gray-800">Peta Interaktif: {{ $building->name }}</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="bmap-engine-badge" class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                                Mapbox 3D Engine
                            </span>
                            <span class="text-xs text-gray-500 bg-white px-2.5 py-1 rounded-full border border-gray-200">
                                Draggable Marker Active
                            </span>
                        </div>
                    </div>

                    <div class="relative">
                        <div id="buildingMap" class="w-full z-0 min-h-[580px]" style="height: 580px;"></div>

                        {{-- Map overlay instruction (Top-Left) --}}
                        <div class="absolute top-3 left-3 z-[999] bg-white/95 backdrop-blur-md px-3.5 py-2 rounded-xl shadow-md border border-gray-200/80 text-xs text-gray-700 flex items-center gap-2 max-w-xs sm:max-w-sm pointer-events-none">
                            <span class="inline-block w-2.5 h-2.5 bg-red-500 rounded-full shrink-0"></span>
                            <span>Tarik pin merah atau <b>klik peta</b> untuk memposisikan ulang gedung</span>
                        </div>

                        {{-- Floating Spatial HUD Controls (Top-Right — Identik dengan Peta User) --}}
                        <div class="absolute top-3 right-14 z-[999] flex flex-wrap items-center justify-end gap-1.5">
                            <!-- 2D / 3D Perspective Toggle Button -->
                            <button 
                                type="button" 
                                id="bmap-perspective-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-[#10A760] shadow-md border border-[#10A760] hover:bg-green-50 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Beralih antara Mode 3D dan Mode 2D Datar"
                            >
                                <i class="fa-solid fa-cube text-[#10A760]"></i>
                                <span id="bmap-perspective-label">Mode 3D</span>
                            </button>

                            <!-- Satellite Mode Toggle Button -->
                            <button 
                                type="button" 
                                id="bmap-satellite-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-cyan-700 shadow-md border border-gray-200 hover:border-cyan-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Beralih antara Peta Vektor Standar dan Citra Satelit Google Hybrid"
                            >
                                <i id="bmap-satellite-icon" class="fa-solid fa-satellite text-cyan-600"></i>
                                <span id="bmap-satellite-label">Satelit</span>
                            </button>

                            <!-- Reset Camera to USU Center Button -->
                            <button 
                                type="button" 
                                id="bmap-reset-camera-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-blue-600 shadow-md border border-gray-200 hover:border-blue-400 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Pusatkan Kamera ke Kampus USU"
                            >
                                <i class="fa-solid fa-crosshairs text-blue-600"></i>
                                <span>Reset Kamera</span>
                            </button>

                            <!-- Toggle Reference Layer: Existing Campus Buildings -->
                            <button 
                                type="button" 
                                id="bmap-ref-buildings-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-amber-700 shadow-md border border-gray-200 hover:border-amber-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Tampilkan / Sembunyikan Pin Referensi Gedung Kampus Lainnya"
                            >
                                <i class="fa-solid fa-building-columns text-amber-600"></i>
                                <span>Gedung (<span id="bmap-ref-buildings-count">76</span>)</span>
                            </button>

                            <!-- Toggle Reference Layer: Photocopy Partners -->
                            <button 
                                type="button" 
                                id="bmap-ref-photocopy-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-emerald-700 shadow-md border border-gray-200 hover:border-emerald-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Tampilkan / Sembunyikan Lokasi Mitra Fotokopi & Percetakan USU"
                            >
                                <i class="fa-solid fa-print text-emerald-600"></i>
                                <span>Fotokopi (<span id="bmap-ref-photocopy-count">{{ \App\Models\PhotocopyPlace::where('is_active', true)->count() }}</span>)</span>
                            </button>
                        </div>

                        {{-- Floating Coordinates Pill (Bottom) --}}
                        <div class="absolute bottom-4 left-4 right-4 z-[999] bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-xl shadow-md border border-gray-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-500">Lat: <strong id="displayLat" class="text-gray-900 font-mono">{{ old('latitude', $building->latitude) }}</strong></span>
                                <span class="text-gray-500">Lng: <strong id="displayLng" class="text-gray-900 font-mono">{{ old('longitude', $building->longitude) }}</strong></span>
                            </div>
                            <button type="button" id="resetPinBtn" class="text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-1 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Kembalikan ke Posisi Awal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('styles')
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Mapbox GL JS 3D CSS -->
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css" />
    <!-- Leaflet 1.9.4 CSS (Auto-Fallback Engine) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .leaflet-container { font-family: inherit; }
        .custom-red-pin, .custom-mapbox-marker { background: none; border: none; }
        @keyframes pinBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .red-pin-pulse { animation: pinBounce 1.8s infinite ease-in-out; }
    </style>
    @endpush

    @push('scripts')
    <script>
        window.MAPBOX_ACCESS_TOKEN = '{{ env('VITE_MAPBOX_ACCESS_TOKEN', '') }}';
    </script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        // Bersihkan konteks WebGL saat halaman di-refresh
        window.addEventListener('beforeunload', () => {
            if (window.__usuBuildingFormMapInstance && typeof window.__usuBuildingFormMapInstance.remove === 'function') {
                try { window.__usuBuildingFormMapInstance.remove(); } catch (e) {}
                window.__usuBuildingFormMapInstance = null;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const currentBuildingId = {{ (int) $building->id }};
            const currentBuildingName = @json($building->name);

            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const displayLat = document.getElementById('displayLat');
            const displayLng = document.getElementById('displayLng');
            const resetPinBtn = document.getElementById('resetPinBtn');
            const engineBadge = document.getElementById('bmap-engine-badge');

            const perspectiveBtn = document.getElementById('bmap-perspective-btn');
            const perspectiveLabel = document.getElementById('bmap-perspective-label');
            const satelliteBtn = document.getElementById('bmap-satellite-btn');
            const satelliteIcon = document.getElementById('bmap-satellite-icon');
            const satelliteLabel = document.getElementById('bmap-satellite-label');
            const resetCameraBtn = document.getElementById('bmap-reset-camera-btn');
            const refBuildingsBtn = document.getElementById('bmap-ref-buildings-btn');
            const refBuildingsCount = document.getElementById('bmap-ref-buildings-count');
            const refPhotocopyBtn = document.getElementById('bmap-ref-photocopy-btn');
            const refPhotocopyCount = document.getElementById('bmap-ref-photocopy-count');

            const USU_DEFAULT_LAT = 3.5652450;
            const USU_DEFAULT_LNG = 98.6565200;
            const originalLat = parseFloat("{{ $building->latitude }}") || USU_DEFAULT_LAT;
            const originalLng = parseFloat("{{ $building->longitude }}") || USU_DEFAULT_LNG;

            const initialLat = parseFloat(latInput.value) || originalLat;
            const initialLng = parseFloat(lngInput.value) || originalLng;

            const DEFAULT_ZOOM = 17;
            const DEFAULT_PITCH = 50;
            const DEFAULT_BEARING = -15;
            const SATELLITE_STORAGE_KEY = 'usuclassmap_satellite_mode';
            const SATELLITE_SOURCE_ID = 'google-satellite-source';
            const SATELLITE_LAYER_ID = 'google-satellite-layer';

            let is3DMode = true;
            let isSatelliteMode = false;
            try {
                isSatelliteMode = localStorage.getItem(SATELLITE_STORAGE_KEY) === 'true';
            } catch (e) {}

            let isRefBuildingsActive = false;
            let isRefPhotocopyActive = false;
            let cachedBuildings = [];
            let cachedPhotocopy = [];

            // Fungsi Sinkronisasi Real-Time (The Magic UX)
            function updateCoordinates(lat, lng) {
                const formattedLat = Number(lat).toFixed(7);
                const formattedLng = Number(lng).toFixed(7);
                latInput.value = formattedLat;
                lngInput.value = formattedLng;
                if (displayLat) displayLat.textContent = formattedLat;
                if (displayLng) displayLng.textContent = formattedLng;
            }

            function updateSatelliteButtonUI() {
                if (!satelliteBtn) return;
                if (isSatelliteMode) {
                    satelliteBtn.classList.add('bg-slate-900', 'text-white', 'border-slate-700');
                    satelliteBtn.classList.remove('bg-white/95', 'text-gray-700', 'border-gray-200');
                    if (satelliteIcon) {
                        satelliteIcon.classList.remove('text-cyan-600');
                        satelliteIcon.classList.add('text-cyan-400');
                    }
                    if (satelliteLabel) satelliteLabel.textContent = 'Mode Satelit';
                } else {
                    satelliteBtn.classList.remove('bg-slate-900', 'text-white', 'border-slate-700');
                    satelliteBtn.classList.add('bg-white/95', 'text-gray-700', 'border-gray-200');
                    if (satelliteIcon) {
                        satelliteIcon.classList.remove('text-cyan-400');
                        satelliteIcon.classList.add('text-cyan-600');
                    }
                    if (satelliteLabel) satelliteLabel.textContent = 'Satelit';
                }
            }

            function updatePerspectiveButtonUI() {
                if (!perspectiveBtn) return;
                if (perspectiveLabel) perspectiveLabel.textContent = is3DMode ? 'Mode 3D' : 'Mode 2D';
                if (is3DMode) {
                    perspectiveBtn.classList.add('text-[#10A760]', 'border-[#10A760]');
                    perspectiveBtn.classList.remove('text-gray-700', 'border-gray-200');
                } else {
                    perspectiveBtn.classList.remove('text-[#10A760]', 'border-[#10A760]');
                    perspectiveBtn.classList.add('text-gray-700', 'border-gray-200');
                }
            }

            async function fetchReferenceBuildings() {
                if (cachedBuildings.length > 0) return cachedBuildings;
                try {
                    const res = await fetch('/api/buildings');
                    const json = await res.json();
                    cachedBuildings = (json.data || []).filter(b => Number(b.id) !== currentBuildingId);
                    if (refBuildingsCount) refBuildingsCount.textContent = cachedBuildings.length;
                } catch (e) {
                    console.warn('Gagal memuat referensi gedung:', e);
                }
                return cachedBuildings;
            }

            async function fetchReferencePhotocopy() {
                if (cachedPhotocopy.length > 0) return cachedPhotocopy;
                try {
                    const res = await fetch('/api/copy-shops?per_page=200');
                    const json = await res.json();
                    cachedPhotocopy = json.data || [];
                    if (refPhotocopyCount) refPhotocopyCount.textContent = cachedPhotocopy.length;
                } catch (e) {
                    console.warn('Gagal memuat referensi fotokopi:', e);
                }
                return cachedPhotocopy;
            }

            fetchReferenceBuildings();
            fetchReferencePhotocopy();

            function createRedPinElement() {
                const el = document.createElement('div');
                el.className = 'custom-red-pin cursor-move';
                const shortTitle = currentBuildingName.length > 26 ? currentBuildingName.substring(0, 24) + '...' : currentBuildingName;
                el.innerHTML = `
                    <div class="flex flex-col items-center red-pin-pulse" style="filter: drop-shadow(0 6px 10px rgba(220,38,38,0.45));">
                        <div class="px-2.5 py-1 bg-red-600 text-white text-[10px] font-extrabold rounded-lg border-2 border-white shadow-md whitespace-nowrap mb-1">
                            ✏️ ${shortTitle} (Geser / Klik Peta)
                        </div>
                        <svg width="36" height="44" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 0C7.58 0 4 3.58 4 8C4 13.54 12 24 12 24C12 24 20 13.54 20 8C20 3.58 16.42 0 12 0ZM12 11C10.34 11 9 9.66 9 8C9 6.34 10.34 5 12 5C13.66 5 15 6.34 15 8C15 9.66 13.66 11 12 11Z" fill="#DC2626"/>
                            <circle cx="12" cy="8" r="3" fill="white"/>
                        </svg>
                    </div>
                `;
                return el;
            }

            // =====================================================================
            // ENGINE 1: MAPBOX GL JS 3D (PRIMARY — STANDAR PETA USER)
            // =====================================================================
            const mb = window.mapboxgl;
            const token = window.MAPBOX_ACCESS_TOKEN || '';
            const canUseMapbox = mb && token && (typeof mb.supported !== 'function' || mb.supported({ failIfMajorPerformanceCaveat: false }));

            if (canUseMapbox) {
                try {
                    mb.accessToken = token;

                    if (window.__usuBuildingFormMapInstance) {
                        try { window.__usuBuildingFormMapInstance.remove(); } catch (e) {}
                        window.__usuBuildingFormMapInstance = null;
                    }

                    const map = new mb.Map({
                        container: 'buildingMap',
                        style: 'mapbox://styles/mapbox/streets-v12',
                        center: [initialLng, initialLat],
                        zoom: DEFAULT_ZOOM,
                        pitch: is3DMode ? DEFAULT_PITCH : 0,
                        bearing: is3DMode ? DEFAULT_BEARING : 0,
                        antialias: true,
                        failIfMajorPerformanceCaveat: false
                    });

                    window.__usuBuildingFormMapInstance = map;
                    map.addControl(new mb.NavigationControl({ visualizePitch: true }), 'top-right');

                    function injectSatelliteLayer() {
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
                            const beforeId = map.getLayer('3d-buildings') ? '3d-buildings' : undefined;
                            map.addLayer({
                                id: SATELLITE_LAYER_ID,
                                type: 'raster',
                                source: SATELLITE_SOURCE_ID,
                                layout: { visibility: isSatelliteMode ? 'visible' : 'none' },
                                paint: { 'raster-opacity': 1.0, 'raster-fade-duration': 200 }
                            }, beforeId);
                        } else {
                            map.setLayoutProperty(SATELLITE_LAYER_ID, 'visibility', isSatelliteMode ? 'visible' : 'none');
                        }
                    }

                    function inject3DBuildingsLayer() {
                        const layers = map.getStyle()?.layers || [];
                        const labelLayerId = layers.find(l => l.type === 'symbol' && l.layout && l.layout['text-field'])?.id;
                        if (!map.getLayer('3d-buildings')) {
                            map.addLayer({
                                id: '3d-buildings',
                                source: 'composite',
                                'source-layer': 'building',
                                filter: ['==', 'extrude', 'true'],
                                type: 'fill-extrusion',
                                minzoom: 14,
                                paint: {
                                    'fill-extrusion-color': '#d1d5db',
                                    'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 14, 0, 15.05, ['get', 'height']],
                                    'fill-extrusion-base': ['interpolate', ['linear'], ['zoom'], 14, 0, 15.05, ['get', 'min_height']],
                                    'fill-extrusion-opacity': 0.65
                                },
                                layout: {
                                    visibility: (is3DMode && !isSatelliteMode) ? 'visible' : 'none'
                                }
                            }, labelLayerId);
                        } else {
                            map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
                        }
                    }

                    map.on('style.load', () => {
                        injectSatelliteLayer();
                        inject3DBuildingsLayer();
                    });

                    updateSatelliteButtonUI();
                    updatePerspectiveButtonUI();

                    // Draggable Red Pin Marker
                    const marker = new mb.Marker({
                        element: createRedPinElement(),
                        draggable: true,
                        anchor: 'bottom'
                    })
                    .setLngLat([initialLng, initialLat])
                    .addTo(map);

                    marker.on('drag', () => {
                        const pos = marker.getLngLat();
                        updateCoordinates(pos.lat, pos.lng);
                    });

                    marker.on('dragend', () => {
                        const pos = marker.getLngLat();
                        updateCoordinates(pos.lat, pos.lng);
                    });

                    // Klik peta untuk memindahkan marker
                    map.on('click', (e) => {
                        marker.setLngLat([e.lngLat.lng, e.lngLat.lat]);
                        updateCoordinates(e.lngLat.lat, e.lngLat.lng);
                    });

                    // Tombol Kembalikan ke Posisi Awal Gedung
                    if (resetPinBtn) {
                        resetPinBtn.addEventListener('click', () => {
                            marker.setLngLat([originalLng, originalLat]);
                            map.flyTo({
                                center: [originalLng, originalLat],
                                zoom: DEFAULT_ZOOM,
                                pitch: is3DMode ? DEFAULT_PITCH : 0,
                                bearing: is3DMode ? DEFAULT_BEARING : 0,
                                duration: 900
                            });
                            updateCoordinates(originalLat, originalLng);
                        });
                    }

                    // HUD 1: Mode 3D / 2D
                    if (perspectiveBtn) {
                        perspectiveBtn.addEventListener('click', () => {
                            is3DMode = !is3DMode;
                            map.easeTo({
                                pitch: is3DMode ? DEFAULT_PITCH : 0,
                                bearing: is3DMode ? DEFAULT_BEARING : 0,
                                duration: 900
                            });
                            if (map.getLayer('3d-buildings')) {
                                map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
                            } else if (is3DMode && !isSatelliteMode) {
                                inject3DBuildingsLayer();
                            }
                            updatePerspectiveButtonUI();
                        });
                    }

                    // HUD 2: Mode Satelit Google Hybrid
                    if (satelliteBtn) {
                        satelliteBtn.addEventListener('click', () => {
                            isSatelliteMode = !isSatelliteMode;
                            try { localStorage.setItem(SATELLITE_STORAGE_KEY, isSatelliteMode ? 'true' : 'false'); } catch (e) {}
                            updateSatelliteButtonUI();
                            if (map.getLayer(SATELLITE_LAYER_ID)) {
                                map.setLayoutProperty(SATELLITE_LAYER_ID, 'visibility', isSatelliteMode ? 'visible' : 'none');
                            } else {
                                injectSatelliteLayer();
                            }
                            if (map.getLayer('3d-buildings')) {
                                map.setLayoutProperty('3d-buildings', 'visibility', (is3DMode && !isSatelliteMode) ? 'visible' : 'none');
                            }
                        });
                    }

                    // HUD 3: Reset Kamera ke Pusat Kampus USU
                    if (resetCameraBtn) {
                        resetCameraBtn.addEventListener('click', () => {
                            is3DMode = true;
                            map.flyTo({
                                center: [USU_DEFAULT_LNG, USU_DEFAULT_LAT],
                                zoom: 16,
                                pitch: DEFAULT_PITCH,
                                bearing: DEFAULT_BEARING,
                                duration: 1200
                            });
                            updatePerspectiveButtonUI();
                            if (map.getLayer('3d-buildings')) {
                                map.setLayoutProperty('3d-buildings', 'visibility', !isSatelliteMode ? 'visible' : 'none');
                            }
                        });
                    }

                    // HUD 4: Layer Referensi Gedung Kampus Lainnya
                    let refBuildingMarkers = [];
                    if (refBuildingsBtn) {
                        refBuildingsBtn.addEventListener('click', async () => {
                            isRefBuildingsActive = !isRefBuildingsActive;
                            if (isRefBuildingsActive) {
                                const buildings = await fetchReferenceBuildings();
                                refBuildingsBtn.classList.add('bg-amber-600', 'text-white', 'border-amber-600');
                                refBuildingsBtn.classList.remove('bg-white/95', 'text-gray-700');
                                buildings.forEach(b => {
                                    const bLat = parseFloat(b.latitude);
                                    const bLng = parseFloat(b.longitude);
                                    if (!Number.isFinite(bLat) || !Number.isFinite(bLng)) return;
                                    const el = document.createElement('div');
                                    el.className = 'custom-mapbox-marker';
                                    el.innerHTML = `
                                        <div class="px-2 py-1 bg-slate-800/90 text-white text-[10px] font-bold rounded-md border border-white/80 shadow-sm flex items-center gap-1 whitespace-nowrap">
                                            <i class="fa-solid fa-building text-amber-400 text-[9px]"></i>
                                            <span>${b.code || b.name.substring(0, 14)}</span>
                                        </div>
                                    `;
                                    const popup = new mb.Popup({ offset: [0, -15] }).setHTML(`
                                        <div class="p-2 text-xs">
                                            <div class="font-bold text-gray-900">${b.name}</div>
                                            <div class="text-gray-500">Kode: ${b.code || '-'} • ${b.faculty || 'USU'}</div>
                                        </div>
                                    `);
                                    const m = new mb.Marker({ element: el, anchor: 'bottom' })
                                        .setLngLat([bLng, bLat])
                                        .setPopup(popup)
                                        .addTo(map);
                                    refBuildingMarkers.push(m);
                                });
                            } else {
                                refBuildingMarkers.forEach(m => m.remove());
                                refBuildingMarkers = [];
                                refBuildingsBtn.classList.remove('bg-amber-600', 'text-white', 'border-amber-600');
                                refBuildingsBtn.classList.add('bg-white/95', 'text-gray-700');
                            }
                        });
                    }

                    // HUD 5: Layer Referensi 31 Mitra Fotokopi
                    let refPhotocopyMarkers = [];
                    if (refPhotocopyBtn) {
                        refPhotocopyBtn.addEventListener('click', async () => {
                            isRefPhotocopyActive = !isRefPhotocopyActive;
                            if (isRefPhotocopyActive) {
                                const places = await fetchReferencePhotocopy();
                                refPhotocopyBtn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600');
                                refPhotocopyBtn.classList.remove('bg-white/95', 'text-gray-700');
                                places.forEach(p => {
                                    const pLat = parseFloat(p.location?.latitude ?? p.latitude);
                                    const pLng = parseFloat(p.location?.longitude ?? p.longitude);
                                    if (!Number.isFinite(pLat) || !Number.isFinite(pLng)) return;
                                    const shortName = (p.name || 'Fotokopi').length > 18 ? p.name.substring(0, 16) + '...' : p.name;
                                    const el = document.createElement('div');
                                    el.className = 'custom-mapbox-marker';
                                    el.innerHTML = `
                                        <div class="px-2 py-1 bg-emerald-600 text-white text-[10px] font-bold rounded-md border border-white shadow-sm flex items-center gap-1 whitespace-nowrap">
                                            <i class="fa-solid fa-print text-emerald-200 text-[9px]"></i>
                                            <span>${shortName}</span>
                                        </div>
                                    `;
                                    const popup = new mb.Popup({ offset: [0, -15] }).setHTML(`
                                        <div class="p-2 text-xs">
                                            <div class="font-bold text-gray-900">${p.name}</div>
                                            <div class="text-emerald-700 font-semibold">${p.location?.name || p.location_name || 'Kampus USU'}</div>
                                        </div>
                                    `);
                                    const m = new mb.Marker({ element: el, anchor: 'bottom' })
                                        .setLngLat([pLng, pLat])
                                        .setPopup(popup)
                                        .addTo(map);
                                    refPhotocopyMarkers.push(m);
                                });
                            } else {
                                refPhotocopyMarkers.forEach(m => m.remove());
                                refPhotocopyMarkers = [];
                                refPhotocopyBtn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
                                refPhotocopyBtn.classList.add('bg-white/95', 'text-gray-700');
                            }
                        });
                    }

                    return; // Mapbox 3D sukses berjalan
                } catch (err) {
                    console.warn('[USUClassMap Admin] Mapbox WebGL gagal, otomatis beralih ke Leaflet 2D Fallback:', err);
                }
            }

            // =====================================================================
            // ENGINE 2: AUTO-FALLBACK LEAFLET 2D (ANTI-BLANK GUARANTEE)
            // =====================================================================
            if (engineBadge) {
                engineBadge.textContent = 'Leaflet 2D Fallback';
                engineBadge.className = 'text-[11px] font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200';
            }
            if (perspectiveBtn) perspectiveBtn.style.display = 'none';

            const mapContainer = document.getElementById('buildingMap');
            mapContainer.innerHTML = '';

            const lMap = L.map('buildingMap', {
                center: [initialLat, initialLng],
                zoom: 17,
                zoomControl: true
            });

            const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            });
            const googleSatLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                attribution: '&copy; Google Maps',
                maxZoom: 20
            });

            if (isSatelliteMode) {
                googleSatLayer.addTo(lMap);
            } else {
                osmLayer.addTo(lMap);
            }
            updateSatelliteButtonUI();

            const redIcon = L.divIcon({
                className: 'custom-red-pin',
                html: `<div style="transform: translate(-50%, -100%);">${createRedPinElement().innerHTML}</div>`,
                iconSize: [0, 0],
                iconAnchor: [0, 0]
            });

            const lMarker = L.marker([initialLat, initialLng], { draggable: true, icon: redIcon }).addTo(lMap);

            lMarker.on('drag', (e) => {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
            lMarker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
            lMap.on('click', (e) => {
                lMarker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng);
            });

            if (resetPinBtn) {
                resetPinBtn.addEventListener('click', () => {
                    lMarker.setLatLng([originalLat, originalLng]);
                    lMap.panTo([originalLat, originalLng]);
                    updateCoordinates(originalLat, originalLng);
                });
            }
            if (satelliteBtn) {
                satelliteBtn.addEventListener('click', () => {
                    isSatelliteMode = !isSatelliteMode;
                    try { localStorage.setItem(SATELLITE_STORAGE_KEY, isSatelliteMode ? 'true' : 'false'); } catch (e) {}
                    updateSatelliteButtonUI();
                    if (isSatelliteMode) {
                        lMap.removeLayer(osmLayer);
                        googleSatLayer.addTo(lMap);
                    } else {
                        lMap.removeLayer(googleSatLayer);
                        osmLayer.addTo(lMap);
                    }
                });
            }
            if (resetCameraBtn) {
                resetCameraBtn.addEventListener('click', () => {
                    lMap.setView([USU_DEFAULT_LAT, USU_DEFAULT_LNG], 16);
                });
            }

            setTimeout(() => lMap.invalidateSize(), 300);
        });
    </script>
    @endpush
</x-layouts.admin>
