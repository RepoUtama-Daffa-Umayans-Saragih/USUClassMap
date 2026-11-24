<x-layouts.admin-fotokopi>
    <div class="mb-6">
        <a href="{{ route('admin-fotokopi.stores.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-600 transition-colors mb-2">
            &larr; Kembali ke Daftar Mitra
        </a>
        <h1 class="text-2xl font-black text-gray-900">Tambah Mitra Toko Fotocopy</h1>
        <p class="text-xs text-gray-500 mt-1">Daftarkan gerai mitra percetakan &amp; fotocopy baru di kawasan kampus USU beserta titik lokasi pada Peta 3D &amp; Satelit.</p>
    </div>

    <form method="POST" action="{{ route('admin-fotokopi.stores.store') }}">
        @csrf
        <div class="flex flex-col xl:flex-row gap-6 items-start">
            {{-- LEFT PANEL: Form Input Data Mitra Toko (±42%) --}}
            <div class="w-full xl:w-[42%] bg-white rounded-2xl p-6 border border-gray-100 shadow-xs space-y-5">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10B981]"></span>
                        Informasi &amp; Operasional Mitra Toko
                    </h2>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">Auto-Sync Peta Publik</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Toko *</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Berkah Abadi Copy & ATK Perpustakaan" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                        @error('name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Lokasi Kampus *</label>
                        <input type="text" name="location_name" value="{{ old('location_name') }}" placeholder="Contoh: Pelataran Parkir Perpustakaan Pusat USU" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                        @error('location_name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Jam Operasional *</label>
                        <input type="text" name="opening_hours" value="{{ old('opening_hours', '07:30 - 20:30') }}" placeholder="07:30 - 20:30" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Hari Operasional *</label>
                        <input type="text" name="operating_days" value="{{ old('operating_days', 'Senin - Sabtu') }}" placeholder="Senin - Sabtu" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+6282277809900" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Akun Instagram</label>
                        <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="@berkahabadi_usu" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Facebook Page</label>
                        <input type="text" name="facebook" value="{{ old('facebook') }}" placeholder="berkahabadi.copy" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Harga Mulai (Rp)</label>
                        <input type="number" name="starting_price" value="{{ old('starting_price', 200) }}" min="0" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Rating Awal</label>
                        <input type="number" step="0.1" name="rating" value="{{ old('rating', 4.8) }}" min="1" max="5" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Toko</label>
                        <select name="is_open" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                            <option value="1" {{ old('is_open', '1') == '1' ? 'selected' : '' }}>Buka (Aktif)</option>
                            <option value="0" {{ old('is_open') == '0' ? 'selected' : '' }}>Tutup Sementara</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kasir Penanggung Jawab</label>
                        <select name="user_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">
                            <option value="">-- Pilih Akun Kasir --</option>
                            @foreach($cashiers as $c)
                                <option value="{{ $c->id }}" {{ old('user_id') == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Latitude Peta (2-Way Sync)</label>
                        <input type="number" step="0.0000001" id="store-latitude" name="latitude" value="{{ old('latitude', '3.5652500') }}" class="w-full text-xs font-mono rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border bg-emerald-50/30">
                        @error('latitude')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Longitude Peta (2-Way Sync)</label>
                        <input type="number" step="0.0000001" id="store-longitude" name="longitude" value="{{ old('longitude', '98.6548500') }}" class="w-full text-xs font-mono rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border bg-emerald-50/30">
                        @error('longitude')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-xl flex items-start gap-2.5">
                    <i class="fa-solid fa-map-location-dot text-emerald-600 mt-0.5"></i>
                    <p class="text-[11px] text-emerald-800 leading-relaxed">
                        <b>Sinkronisasi 2 Arah:</b> Geser pin toko atau klik peta di sebelah kanan untuk mengisi Latitude/Longitude otomatis, atau ketik angka koordinat di atas untuk memindahkan pin di peta.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Deskripsi / Tentang Toko</label>
                    <textarea name="description" rows="3" placeholder="Paling strategis di dekat Perpustakaan Pusat USU. Melayani fotocopy referensi buku perpus, cetak online via WA..." class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5 border">{{ old('description') }}</textarea>
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <a href="{{ route('admin-fotokopi.stores.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                    <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">Simpan Mitra Toko</button>
                </div>
            </div>

            {{-- RIGHT PANEL: Peta Interaktif Mapbox 3D + User HUD (±58%) --}}
            <div class="w-full xl:w-[58%]">
                <div class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden sticky top-20">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                            <h3 class="text-sm font-bold text-gray-800">Peta Interaktif Lokasi Mitra Fotokopi (Standar Peta User)</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="smap-engine-badge" class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                                Mapbox 3D Engine
                            </span>
                            <span class="text-xs text-gray-500 bg-white px-2.5 py-1 rounded-full border border-gray-200">
                                2-Way Binding Active
                            </span>
                        </div>
                    </div>

                    <div class="relative">
                        <div id="storeMap" class="w-full z-0 min-h-[560px]" style="height: 560px;"></div>

                        {{-- Map overlay instruction (Top-Left) --}}
                        <div class="absolute top-3 left-3 z-[999] bg-white/95 backdrop-blur-md px-3.5 py-2 rounded-xl shadow-md border border-gray-200/80 text-xs text-gray-700 flex items-center gap-2 max-w-xs sm:max-w-sm pointer-events-none">
                            <span class="inline-block w-2.5 h-2.5 bg-emerald-600 rounded-full shrink-0"></span>
                            <span>Tarik pin toko atau <b>klik peta</b> untuk menentukan lokasi gerai</span>
                        </div>

                        {{-- Floating Spatial HUD Controls (Top-Right — Identik dengan Peta User) --}}
                        <div class="absolute top-3 right-14 z-[999] flex flex-wrap items-center justify-end gap-1.5">
                            <!-- 2D / 3D Perspective Toggle Button -->
                            <button 
                                type="button" 
                                id="smap-perspective-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-[#10A760] shadow-md border border-[#10A760] hover:bg-green-50 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Beralih antara Mode 3D dan Mode 2D Datar"
                            >
                                <i class="fa-solid fa-cube text-[#10A760]"></i>
                                <span id="smap-perspective-label">Mode 3D</span>
                            </button>

                            <!-- Satellite Mode Toggle Button -->
                            <button 
                                type="button" 
                                id="smap-satellite-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-cyan-700 shadow-md border border-gray-200 hover:border-cyan-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Beralih antara Peta Vektor Standar dan Citra Satelit Google Hybrid"
                            >
                                <i id="smap-satellite-icon" class="fa-solid fa-satellite text-cyan-600"></i>
                                <span id="smap-satellite-label">Satelit</span>
                            </button>

                            <!-- Reset Camera to USU Center Button -->
                            <button 
                                type="button" 
                                id="smap-reset-camera-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-blue-600 shadow-md border border-gray-200 hover:border-blue-400 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Pusatkan Kamera ke Kampus USU"
                            >
                                <i class="fa-solid fa-crosshairs text-blue-600"></i>
                                <span>Reset Kamera</span>
                            </button>

                            <!-- Toggle Reference Layer: 48 Campus Buildings -->
                            <button 
                                type="button" 
                                id="smap-ref-buildings-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-amber-700 shadow-md border border-gray-200 hover:border-amber-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Tampilkan / Sembunyikan Pin Referensi Gedung Kampus USU"
                            >
                                <i class="fa-solid fa-building-columns text-amber-600"></i>
                                <span>Gedung (<span id="smap-ref-buildings-count">77</span>)</span>
                            </button>

                            <!-- Toggle Reference Layer: Existing Photocopy Partners -->
                            <button 
                                type="button" 
                                id="smap-ref-photocopy-btn"
                                class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-emerald-700 shadow-md border border-gray-200 hover:border-emerald-500 flex items-center gap-1.5 transition-all cursor-pointer"
                                title="Tampilkan / Sembunyikan Pin Mitra Fotokopi Lainnya"
                            >
                                <i class="fa-solid fa-print text-emerald-600"></i>
                                <span>Fotokopi (<span id="smap-ref-photocopy-count">32</span>)</span>
                            </button>
                        </div>

                        {{-- Floating Coordinates Pill (Bottom) --}}
                        <div class="absolute bottom-4 left-4 right-4 z-[999] bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-xl shadow-md border border-gray-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-500">Lat: <strong id="displayLat" class="text-gray-900 font-mono">{{ old('latitude', '3.5652500') }}</strong></span>
                                <span class="text-gray-500">Lng: <strong id="displayLng" class="text-gray-900 font-mono">{{ old('longitude', '98.6548500') }}</strong></span>
                            </div>
                            <button type="button" id="resetPinBtn" class="text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-1 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Pusatkan ke Kampus USU
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
        .custom-store-pin, .custom-mapbox-marker { background: none; border: none; }
        @keyframes storePinBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .store-pin-pulse { animation: storePinBounce 1.8s infinite ease-in-out; }
    </style>
    @endpush

    @push('scripts')
    <script>
        window.MAPBOX_ACCESS_TOKEN = '{{ env('VITE_MAPBOX_ACCESS_TOKEN', '') }}';
    </script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        window.addEventListener('beforeunload', () => {
            if (window.__usuStoreFormMapInstance && typeof window.__usuStoreFormMapInstance.remove === 'function') {
                try { window.__usuStoreFormMapInstance.remove(); } catch (e) {}
                window.__usuStoreFormMapInstance = null;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const latInput = document.getElementById('store-latitude');
            const lngInput = document.getElementById('store-longitude');
            const displayLat = document.getElementById('displayLat');
            const displayLng = document.getElementById('displayLng');
            const resetPinBtn = document.getElementById('resetPinBtn');
            const engineBadge = document.getElementById('smap-engine-badge');

            const perspectiveBtn = document.getElementById('smap-perspective-btn');
            const perspectiveLabel = document.getElementById('smap-perspective-label');
            const satelliteBtn = document.getElementById('smap-satellite-btn');
            const satelliteIcon = document.getElementById('smap-satellite-icon');
            const satelliteLabel = document.getElementById('smap-satellite-label');
            const resetCameraBtn = document.getElementById('smap-reset-camera-btn');
            const refBuildingsBtn = document.getElementById('smap-ref-buildings-btn');
            const refBuildingsCount = document.getElementById('smap-ref-buildings-count');
            const refPhotocopyBtn = document.getElementById('smap-ref-photocopy-btn');
            const refPhotocopyCount = document.getElementById('smap-ref-photocopy-count');

            const USU_DEFAULT_LAT = 3.5652500;
            const USU_DEFAULT_LNG = 98.6548500;
            const USU_CAMPUS_CENTER_LAT = 3.5652450;
            const USU_CAMPUS_CENTER_LNG = 98.6565200;
            const DEFAULT_ZOOM = 16.5;
            const DEFAULT_PITCH = 50;
            const DEFAULT_BEARING = -15;
            const SATELLITE_STORAGE_KEY = 'usuclassmap_satellite_mode';
            const SATELLITE_SOURCE_ID = 'google-satellite-source';
            const SATELLITE_LAYER_ID = 'google-satellite-layer';

            const initialLat = parseFloat(latInput.value) || USU_DEFAULT_LAT;
            const initialLng = parseFloat(lngInput.value) || USU_DEFAULT_LNG;

            let is3DMode = true;
            let isSatelliteMode = false;
            try {
                isSatelliteMode = localStorage.getItem(SATELLITE_STORAGE_KEY) === 'true';
            } catch (e) {}

            let isRefBuildingsActive = false;
            let isRefPhotocopyActive = false;
            let cachedBuildings = [];
            let cachedPhotocopy = [];

            function updateCoordinates(lat, lng, updateInputs = true) {
                const formattedLat = Number(lat).toFixed(7);
                const formattedLng = Number(lng).toFixed(7);
                if (updateInputs) {
                    latInput.value = formattedLat;
                    lngInput.value = formattedLng;
                }
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
                    cachedBuildings = json.data || [];
                    if (refBuildingsCount) refBuildingsCount.textContent = cachedBuildings.length;
                } catch (e) {}
                return cachedBuildings;
            }

            async function fetchReferencePhotocopy() {
                if (cachedPhotocopy.length > 0) return cachedPhotocopy;
                try {
                    const res = await fetch('/api/copy-shops?per_page=50');
                    const json = await res.json();
                    cachedPhotocopy = json.data || [];
                    if (refPhotocopyCount) refPhotocopyCount.textContent = cachedPhotocopy.length;
                } catch (e) {}
                return cachedPhotocopy;
            }

            fetchReferenceBuildings();
            fetchReferencePhotocopy();

            function createStorePinElement() {
                const el = document.createElement('div');
                el.className = 'custom-store-pin cursor-move';
                el.innerHTML = `
                    <div class="flex flex-col items-center store-pin-pulse" style="filter: drop-shadow(0 6px 10px rgba(16,185,129,0.45));">
                        <div class="px-2.5 py-1 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[10px] font-extrabold rounded-lg border-2 border-white shadow-md whitespace-nowrap mb-1 flex items-center gap-1.5">
                            <i class="fa-solid fa-print text-emerald-200"></i>
                            <span>Lokasi Mitra Toko Baru (Geser / Klik Peta)</span>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-[#10B981] border-4 border-white shadow-lg flex items-center justify-center text-white">
                            <i class="fa-solid fa-store text-xs"></i>
                        </div>
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

                    if (window.__usuStoreFormMapInstance) {
                        try { window.__usuStoreFormMapInstance.remove(); } catch (e) {}
                        window.__usuStoreFormMapInstance = null;
                    }

                    const map = new mb.Map({
                        container: 'storeMap',
                        style: 'mapbox://styles/mapbox/streets-v12',
                        center: [initialLng, initialLat],
                        zoom: DEFAULT_ZOOM,
                        pitch: is3DMode ? DEFAULT_PITCH : 0,
                        bearing: is3DMode ? DEFAULT_BEARING : 0,
                        antialias: true,
                        failIfMajorPerformanceCaveat: false
                    });

                    window.__usuStoreFormMapInstance = map;
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

                    const marker = new mb.Marker({
                        element: createStorePinElement(),
                        draggable: true,
                        anchor: 'bottom'
                    })
                    .setLngLat([initialLng, initialLat])
                    .addTo(map);

                    marker.on('drag', () => {
                        const pos = marker.getLngLat();
                        updateCoordinates(pos.lat, pos.lng, true);
                    });

                    marker.on('dragend', () => {
                        const pos = marker.getLngLat();
                        updateCoordinates(pos.lat, pos.lng, true);
                    });

                    map.on('click', (e) => {
                        marker.setLngLat([e.lngLat.lng, e.lngLat.lat]);
                        updateCoordinates(e.lngLat.lat, e.lngLat.lng, true);
                    });

                    // Two-Way Binding: Input Manual -> Geser Pin di Peta
                    function syncFromManualInputs() {
                        const manualLat = parseFloat(latInput.value);
                        const manualLng = parseFloat(lngInput.value);
                        if (Number.isFinite(manualLat) && Number.isFinite(manualLng) && manualLat >= -90 && manualLat <= 90 && manualLng >= -180 && manualLng <= 180) {
                            marker.setLngLat([manualLng, manualLat]);
                            updateCoordinates(manualLat, manualLng, false);
                            map.easeTo({ center: [manualLng, manualLat], duration: 500 });
                        }
                    }
                    latInput.addEventListener('input', syncFromManualInputs);
                    lngInput.addEventListener('input', syncFromManualInputs);

                    if (resetPinBtn) {
                        resetPinBtn.addEventListener('click', () => {
                            marker.setLngLat([USU_DEFAULT_LNG, USU_DEFAULT_LAT]);
                            map.flyTo({
                                center: [USU_DEFAULT_LNG, USU_DEFAULT_LAT],
                                zoom: DEFAULT_ZOOM,
                                pitch: is3DMode ? DEFAULT_PITCH : 0,
                                bearing: is3DMode ? DEFAULT_BEARING : 0,
                                duration: 900
                            });
                            updateCoordinates(USU_DEFAULT_LAT, USU_DEFAULT_LNG, true);
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

                    // HUD 3: Reset Kamera
                    if (resetCameraBtn) {
                        resetCameraBtn.addEventListener('click', () => {
                            is3DMode = true;
                            map.flyTo({
                                center: [USU_CAMPUS_CENTER_LNG, USU_CAMPUS_CENTER_LAT],
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

                    // HUD 4: Layer Referensi 48 Gedung Kampus
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
                                        <div class="px-2 py-1 bg-teal-700/90 text-white text-[10px] font-bold rounded-md border border-white shadow-sm flex items-center gap-1 whitespace-nowrap">
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

                    return;
                } catch (err) {
                    console.warn('[USUClassMap Admin Fotokopi] Mapbox WebGL gagal, otomatis beralih ke Leaflet 2D Fallback:', err);
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

            const mapContainer = document.getElementById('storeMap');
            mapContainer.innerHTML = '';

            const lMap = L.map('storeMap', {
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

            const storeIcon = L.divIcon({
                className: 'custom-store-pin',
                html: `<div style="transform: translate(-50%, -100%);">${createStorePinElement().innerHTML}</div>`,
                iconSize: [0, 0],
                iconAnchor: [0, 0]
            });

            const lMarker = L.marker([initialLat, initialLng], { draggable: true, icon: storeIcon }).addTo(lMap);

            lMarker.on('drag', (e) => {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng, true);
            });
            lMarker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng, true);
            });
            lMap.on('click', (e) => {
                lMarker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng, true);
            });

            function syncLeafletFromInputs() {
                const manualLat = parseFloat(latInput.value);
                const manualLng = parseFloat(lngInput.value);
                if (Number.isFinite(manualLat) && Number.isFinite(manualLng)) {
                    lMarker.setLatLng([manualLat, manualLng]);
                    updateCoordinates(manualLat, manualLng, false);
                    lMap.panTo([manualLat, manualLng]);
                }
            }
            latInput.addEventListener('input', syncLeafletFromInputs);
            lngInput.addEventListener('input', syncLeafletFromInputs);

            if (resetPinBtn) {
                resetPinBtn.addEventListener('click', () => {
                    lMarker.setLatLng([USU_DEFAULT_LAT, USU_DEFAULT_LNG]);
                    lMap.panTo([USU_DEFAULT_LAT, USU_DEFAULT_LNG]);
                    updateCoordinates(USU_DEFAULT_LAT, USU_DEFAULT_LNG, true);
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
                    lMap.setView([USU_CAMPUS_CENTER_LAT, USU_CAMPUS_CENTER_LNG], 16);
                });
            }

            setTimeout(() => lMap.invalidateSize(), 300);
        });
    </script>
    @endpush
</x-layouts.admin-fotokopi>
