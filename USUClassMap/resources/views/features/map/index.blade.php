<x-layouts.app>
    @push('styles')
        <!-- Mapbox GL JS 3D CSS -->
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css" />
        <!-- Pannellum 360 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css" />
        <style>
            .custom-leaflet-marker, .custom-mapbox-marker {
                background: transparent;
                border: none;
            }
            /* Custom Thin Scrollbar */
            .custom-scrollbar::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 4px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            @keyframes usuGpsPulse {
                0% { transform: scale(1); opacity: 0.75; }
                70% { transform: scale(2.35); opacity: 0; }
                100% { transform: scale(2.35); opacity: 0; }
            }
            .usu-gps-pulse-ring {
                animation: usuGpsPulse 2s cubic-bezier(0.24, 0, 0.38, 1) infinite;
            }
        </style>
    @endpush

    <div class="flex h-[calc(100vh-4rem)] flex-col md:flex-row relative overflow-hidden" x-data="{ sidebarOpen: true }">
        
        <!-- Mobile Toggle Button -->
        <div class="md:hidden absolute top-3 left-3 z-[1000]">
            <button @click="sidebarOpen = !sidebarOpen" class="bg-white/95 backdrop-blur shadow-md px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 flex items-center gap-2 border border-gray-200">
                <svg class="w-4 h-4 text-[#10A760]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <span>Cari & Filter</span>
            </button>
        </div>

        <!-- Sidebar -->
        <aside 
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="w-full md:w-96 bg-white shadow-xl z-20 flex flex-col flex-shrink-0 h-full transition-transform duration-300 ease-in-out absolute md:relative border-r border-gray-200"
        >
            <!-- Search Bar Header -->
            <div class="p-4 border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-base text-gray-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10A760]"></span>
                        Pencarian Jadwal
                    </h3>
                    <button @click="sidebarOpen = false" class="md:hidden text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        id="map-search-input"
                        type="text" 
                        class="block w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#10A760] focus:border-transparent transition-all" 
                        placeholder="Cari mata kuliah atau dosen, clue gedung (depan FIB...), atau ATK..."
                        autocomplete="off"
                    >
                    <button id="clear-search-btn" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600" style="display: none;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Recent Searches Container (Populated via localStorage) -->
                <div id="recent-searches-container" class="mt-2.5 pt-2 border-t border-gray-100" style="display: none;"></div>
            </div>
            
            <!-- Day Filters Section -->
            <div class="p-3.5 border-b border-gray-100 bg-gray-50/50">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Filter Hari</span>
                    <span class="text-[11px] text-gray-400">Pilih satu hari</span>
                </div>
                <div id="day-filters" class="flex flex-wrap gap-1.5">
                    @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] as $hari)
                        <button 
                            type="button"
                            data-day="{{ $hari }}"
                            class="px-2.5 py-1 text-xs font-semibold rounded-md border border-[#10A760] text-[#10A760] bg-white hover:bg-[#E8F5EE] transition-all shadow-2xs"
                        >
                            {{ $hari }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-2.5 pt-2 border-t border-gray-200/70 flex items-center justify-between">
                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Layanan Kampus</span>
                    <button 
                        type="button" 
                        id="toggle-fotocopy-btn" 
                        onclick="window.USUClassMap && window.USUClassMap.togglePhotocopyLayer()"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg border border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 transition-all shadow-2xs cursor-pointer"
                    >
                        <i class="fa-solid fa-print text-emerald-600 text-xs"></i>
                        <span>Mitra Fotokopi (<span id="fotocopy-count-badge">{{ $photocopyCount ?? \App\Models\PhotocopyPlace::where('is_active', true)->count() }}</span>)</span>
                    </button>
                </div>
            </div>

            <!-- Results Summary Bar -->
            <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 flex items-center justify-between gap-2 text-xs">
                <span id="results-count" class="font-bold text-gray-700 truncate">Memuat data gedung...</span>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span id="map-wib-clock" class="px-2 py-0.5 text-[10px] font-bold rounded bg-white border border-gray-200 text-gray-700" title="Waktu Indonesia Barat (Asia/Jakarta)">
                        WIB {{ now('Asia/Jakarta')->format('H:i') }}
                    </span>
                    <span id="source-badge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800">
                        Semua Gedung
                    </span>
                </div>
            </div>

            <!-- Scrollable Search Results List -->
            <div class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-3" id="search-results">
                <!-- Populated dynamically via resources/js/features/map.js -->
            </div>

            <!-- Collapsible Map Legend -->
            <div class="border-t border-gray-200 bg-white p-3" x-data="{ expanded: false }">
                <button @click="expanded = !expanded" class="flex justify-between items-center w-full text-xs font-bold text-gray-600 hover:text-gray-900">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-[#10A760]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Legenda Peta
                    </span>
                    <svg class="w-3.5 h-3.5 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="expanded" class="mt-2.5 pt-2 border-t border-gray-100 space-y-1.5 text-xs text-gray-600" style="display: none;">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#10B981] inline-block shadow-xs border border-white ring-1 ring-emerald-200"></span>
                        <span>Pin Hijau: Gedung Kelas Buka (07:00 – 16:00 WIB)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#0D9488] inline-block shadow-xs border border-white ring-1 ring-teal-200"></span>
                        <span>Pin Teal: Mitra Fotokopi Buka</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#DC2626] inline-block shadow-xs border border-white ring-1 ring-red-300"></span>
                        <span>Pin Merah: Tempat / Kelas Tutup (Belum Buka)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#EA580C] inline-block shadow-xs"></span>
                        <span>Pin Oranye: Jadwal Kuliah Saya (Login)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#D4A017] inline-block shadow-xs"></span>
                        <span>Pin Kuning: Gedung Hasil Pencarian</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-gray-400 inline-block"></span>
                        <span>Pin Transparan: Gedung Lain</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Map Canvas Area -->
        <main class="flex-1 relative h-full w-full bg-gray-200">
            <!-- Floating Spatial HUD Controls (Huawei C.O.R.E. Style) -->
            <div class="absolute top-3 right-14 z-[999] flex flex-wrap items-center gap-2">
                <!-- 2D / 3D Perspective Toggle Button -->
                <button 
                    type="button" 
                    id="map-perspective-toggle-btn"
                    onclick="window.USUClassMap && window.USUClassMap.togglePerspective()"
                    class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-[#10A760] shadow-md border border-gray-200 hover:border-[#10A760] flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Beralih antara Mode 3D dan Mode 2D Datar"
                >
                    <i class="fa-solid fa-cube text-[#10A760]"></i>
                    <span id="perspective-label">Mode 3D</span>
                </button>

                <!-- Satellite Mode Toggle Button -->
                <button 
                    type="button" 
                    id="map-satellite-toggle-btn"
                    onclick="window.USUClassMap && window.USUClassMap.toggleSatelliteMode()"
                    class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-cyan-700 shadow-md border border-gray-200 hover:border-cyan-500 flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Beralih antara Peta Vektor Standar dan Citra Satelit"
                >
                    <i id="satellite-icon" class="fa-solid fa-satellite text-cyan-600"></i>
                    <span id="satellite-label">Satelit</span>
                </button>

                <!-- Reset Camera to USU Center Button -->
                <button 
                    type="button" 
                    id="map-reset-center-btn"
                    onclick="window.USUClassMap && window.USUClassMap.resetCampusView()"
                    class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-blue-600 shadow-md border border-gray-200 hover:border-blue-400 flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Pusatkan Kembali Kamera ke Kampus USU"
                >
                    <i class="fa-solid fa-crosshairs text-blue-600"></i>
                    <span>Reset Kamera</span>
                </button>

                <!-- Floating Quick Toggle for Photocopy Layer -->
                <button 
                    type="button" 
                    id="map-floating-photocopy-btn"
                    onclick="window.USUClassMap && window.USUClassMap.togglePhotocopyLayer()"
                    class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-emerald-700 shadow-md border border-gray-200 hover:border-emerald-500 flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Tampilkan / Sembunyikan Lokasi Mitra Fotokopi & Percetakan USU"
                >
                    <i class="fa-solid fa-print text-emerald-600"></i>
                    <span>Fotokopi (<span id="floating-photocopy-count">{{ $photocopyCount ?? \App\Models\PhotocopyPlace::where('is_active', true)->count() }}</span>)</span>
                </button>

                <!-- Floating Quick Toggle for In-App Campus Route Panel -->
                <button
                    type="button"
                    id="map-route-toggle-btn"
                    onclick="window.USUClassMap && window.USUClassMap.toggleRoutePanel()"
                    class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl text-xs font-bold text-gray-700 hover:text-[#10A760] shadow-md border border-gray-200 hover:border-[#10A760] flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Buka Navigasi Rute Kampus USU In-App"
                >
                    <i class="fa-solid fa-route text-[#10A760]"></i>
                    <span>Rute Kampus</span>
                </button>
            </div>

            <!-- In-App Campus Route Navigation Panel -->
            <div
                id="usu-route-panel"
                x-data="usuRoutePanel()"
                x-show="isOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                class="absolute top-16 right-3 z-[998] w-[calc(100vw-1.5rem)] sm:w-[390px] max-h-[calc(100vh-10rem)] flex flex-col bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
                style="display: none;"
            >
                <div class="px-4 py-3 bg-gradient-to-r from-[#0F8F53] to-[#10A760] text-white flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fa-solid fa-route text-sm"></i>
                        <div class="min-w-0">
                            <h3 class="text-xs font-extrabold tracking-wide uppercase">Navigasi Rute Kampus</h3>
                            <p class="text-[11px] text-emerald-100 truncate">Jalan &amp; selasar resmi USU (anti-tembok)</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="close()"
                        class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition-colors cursor-pointer"
                        title="Tutup Panel Rute"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <div class="p-3.5 space-y-2.5 overflow-y-auto flex-1">
                    <!-- Origin selector -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label for="route-origin-select" class="text-[11px] font-bold text-gray-600 uppercase tracking-wide flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                                Titik Asal
                            </label>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="useGpsOrigin()"
                                    :disabled="isLocatingGps"
                                    :class="originSource === 'gps' ? 'border-sky-500 bg-sky-600 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border transition-colors cursor-pointer disabled:opacity-50"
                                    title="Deteksi / Perbarui Lokasi GPS Saya"
                                >
                                    <i class="fa-solid" :class="isLocatingGps ? 'fa-spinner fa-spin' : 'fa-crosshairs'"></i>
                                    <span>GPS</span>
                                </button>
                                <template x-if="originSource === 'gps'">
                                    <button
                                        type="button"
                                        @click="toggleLiveTracking()"
                                        :class="liveGpsTracking ? 'bg-sky-100 text-sky-800 border-sky-300' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition-colors cursor-pointer"
                                        title="Ikuti pergerakan GPS secara ringan"
                                    >
                                        <i class="fa-solid fa-satellite-dish"></i>
                                        <span x-text="liveGpsTracking ? 'Live ON' : 'Live'"></span>
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    @click="togglePickOriginOnMap()"
                                    :class="pickingOriginOnMap ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'"
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border transition-colors cursor-pointer"
                                    title="Klik sembarang titik di peta sebagai titik asal"
                                >
                                    <i class="fa-solid fa-map-pin"></i>
                                    <span x-text="pickingOriginOnMap ? 'Klik Peta...' : 'Pilih di Peta'"></span>
                                </button>
                            </div>
                        </div>
                        <select
                            id="route-origin-select"
                            x-model="originKey"
                            @change="onOriginChanged()"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-800 focus:border-[#10A760] focus:ring-2 focus:ring-[#10A760]/20"
                        >
                            <option value="">-- Pilih titik asal (Gedung / GPS / Peta) --</option>
                            <option :value="customOriginKey" x-text="customOriginLabel" :hidden="!customOriginKey" :disabled="!customOriginKey"></option>
                            <optgroup label="Gedung Kampus USU">
                                <template x-for="b in destinations.buildings" :key="'orig-' + b.key">
                                    <option :value="b.key" x-text="b.label"></option>
                                </template>
                            </optgroup>
                            <optgroup label="Mitra Fotokopi & Percetakan">
                                <template x-for="f in destinations.fotocopy" :key="'orig-' + f.key">
                                    <option :value="f.key" x-text="f.label"></option>
                                </template>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Swap + Destination -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label for="route-destination-select" class="text-[11px] font-bold text-gray-600 uppercase tracking-wide flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block"></span>
                                Tujuan
                            </label>
                            <button
                                type="button"
                                @click="swapEndpoints()"
                                class="px-2 py-0.5 rounded-md text-[11px] font-bold border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer"
                                title="Tukar Asal & Tujuan"
                            >
                                <i class="fa-solid fa-right-left"></i> Tukar
                            </button>
                        </div>
                        <select
                            id="route-destination-select"
                            x-model="destinationKey"
                            @change="onDestinationChanged()"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-800 focus:border-[#10A760] focus:ring-2 focus:ring-[#10A760]/20"
                        >
                            <option value="">-- Pilih tujuan kampus --</option>
                            <option :value="customDestinationKey" x-text="customDestinationLabel" :hidden="!customDestinationKey" :disabled="!customDestinationKey"></option>
                            <optgroup label="Gedung Kampus USU">
                                <template x-for="b in destinations.buildings" :key="'dest-' + b.key">
                                    <option :value="b.key" x-text="b.label"></option>
                                </template>
                            </optgroup>
                            <optgroup label="Mitra Fotokopi & Percetakan">
                                <template x-for="f in destinations.fotocopy" :key="'dest-' + f.key">
                                    <option :value="f.key" x-text="f.label"></option>
                                </template>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Transport mode & CTA -->
                    <div class="flex items-center gap-1.5 pt-0.5">
                        <button
                            type="button"
                            @click="setMode('walking')"
                            :class="mode === 'walking' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'"
                            class="flex-1 py-1.5 px-2.5 rounded-xl border text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <i class="fa-solid fa-person-walking"></i>
                            <span>Jalan Kaki</span>
                        </button>
                        <button
                            type="button"
                            @click="setMode('driving')"
                            :class="mode === 'driving' ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'"
                            class="flex-1 py-1.5 px-2.5 rounded-xl border text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <i class="fa-solid fa-car-side"></i>
                            <span>Kendaraan</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            id="calculate-inapp-route-btn"
                            @click="calculateRoute()"
                            :disabled="isLoading || !originKey || !destinationKey"
                            class="flex-1 py-2 px-3 rounded-xl bg-[#10A760] hover:bg-[#0D8A4E] disabled:opacity-50 text-white text-xs font-extrabold flex items-center justify-center gap-1.5 shadow-sm transition-colors cursor-pointer"
                        >
                            <i class="fa-solid" :class="isLoading ? 'fa-spinner fa-spin' : 'fa-diamond-turn-right'"></i>
                            <span x-text="isLoading ? 'Menghitung Rute...' : 'Hitung & Tampilkan Rute'"></span>
                        </button>
                        <template x-if="routeResult">
                            <button
                                type="button"
                                @click="focusRoute()"
                                class="py-2 px-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold cursor-pointer"
                                title="Fokus Kamera ke Garis Rute"
                            >
                                <i class="fa-solid fa-expand"></i>
                            </button>
                        </template>
                        <template x-if="routeResult || originKey || destinationKey">
                            <button
                                type="button"
                                @click="clearRoute()"
                                class="py-2 px-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold cursor-pointer"
                                title="Bersihkan Rute"
                            >
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </template>
                    </div>

                    <!-- Pick on map prompt -->
                    <div
                        x-show="pickingOriginOnMap"
                        class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] font-semibold text-amber-800 flex items-center gap-2"
                    >
                        <i class="fa-solid fa-hand-pointer"></i>
                        <span>Klik posisi asal di atas peta kampus USU sekarang.</span>
                    </div>

                    <!-- Status / Error -->
                    <div
                        x-show="statusMsg"
                        :class="isError ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-blue-50 border-blue-200 text-blue-700'"
                        class="rounded-xl border px-3 py-2 text-[11px] font-semibold"
                        x-text="statusMsg"
                    ></div>

                    <!-- Already at Destination Info Banner (< 20m) -->
                    <div
                        x-show="alreadyAtDestinationMsg"
                        id="route-already-arrived-banner"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs font-bold text-emerald-800 flex items-start gap-2"
                    >
                        <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5"></i>
                        <span x-text="alreadyAtDestinationMsg"></span>
                    </div>

                    <!-- GPS / External Routing Diagnostic Banner + Shortcut Buttons -->
                    <template x-if="diagnostic">
                        <div
                            id="route-gps-diagnostic-banner"
                            class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-[11px] font-semibold text-amber-900 space-y-2"
                        >
                            <div class="flex items-start gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 shrink-0"></i>
                                <span x-text="diagnostic.message"></span>
                            </div>
                            <template x-if="diagnostic.showFallbackButtons">
                                <div class="flex items-center gap-1.5 pt-0.5">
                                    <button
                                        type="button"
                                        @click="focusOriginDropdown()"
                                        class="flex-1 py-1.5 px-2.5 rounded-lg bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 font-bold text-[11px] transition-colors cursor-pointer"
                                    >
                                        <i class="fa-solid fa-building mr-1"></i>Pilih Gedung Asal
                                    </button>
                                    <button
                                        type="button"
                                        @click="togglePickOriginOnMap()"
                                        class="flex-1 py-1.5 px-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] transition-colors cursor-pointer"
                                    >
                                        <i class="fa-solid fa-map-pin mr-1"></i>Klik Titik di Peta
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Summary Card & Steps -->
                    <template x-if="routeResult">
                        <div class="space-y-2.5 pt-1 border-t border-gray-100">
                            <div class="rounded-xl bg-emerald-50/80 border border-emerald-200 p-3 space-y-2">
                                <div class="flex items-baseline justify-between gap-2">
                                    <div>
                                        <div class="text-lg font-black text-emerald-900 leading-none" x-text="routeResult.distance_text"></div>
                                        <div class="text-[11px] font-bold text-emerald-700 mt-1">
                                            Estimasi <span x-text="routeResult.duration_text"></span>
                                        </div>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wide"
                                        :class="mode === 'driving' ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800'"
                                        x-text="engineLabel()"
                                    ></span>
                                </div>

                                <!-- Geometry Audit Verification Badge -->
                                <div
                                    class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold flex items-center gap-1.5"
                                    :class="routeResult.geometry_audit?.verified ? 'bg-white/90 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                >
                                    <i
                                        class="fa-solid"
                                        :class="routeResult.geometry_audit?.verified ? 'fa-shield-halved text-emerald-600' : 'fa-triangle-exclamation text-amber-600'"
                                    ></i>
                                    <span x-text="auditText()"></span>
                                </div>

                                <template x-if="routeResult.destination?.is_manual_entrance">
                                    <div class="text-[10px] font-semibold text-emerald-700 flex items-center gap-1">
                                        <i class="fa-solid fa-door-open"></i>
                                        <span>Diarahkan tepat ke titik pintu masuk terverifikasi admin.</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Turn-by-turn steps -->
                            <div class="space-y-1">
                                <div class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider px-0.5">
                                    Panduan Belokan (<span x-text="(routeResult.steps || []).length"></span> langkah)
                                </div>
                                <div class="max-h-52 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar">
                                    <template x-for="(step, idx) in (routeResult.steps || [])" :key="'step-' + idx">
                                        <button
                                            type="button"
                                            @click="focusStep(step, idx)"
                                            :class="activeStepIndex === idx ? 'border-emerald-500 bg-emerald-50/70' : 'border-gray-100 bg-gray-50/70 hover:bg-gray-100'"
                                            class="w-full text-left rounded-xl border px-2.5 py-2 flex items-start gap-2 transition-colors cursor-pointer"
                                        >
                                            <span class="w-5 h-5 rounded-full bg-white border border-gray-200 text-[10px] font-extrabold text-gray-700 flex items-center justify-center shrink-0 mt-0.5">
                                                <i class="fa-solid" :class="maneuverIcon(step.maneuver)"></i>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[11px] font-semibold text-gray-800 leading-snug" x-text="step.instruction"></div>
                                                <div class="text-[10px] font-bold text-gray-500 mt-0.5" x-text="formatStepDistance(step.distance_meters)"></div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div id="map" class="w-full h-full min-h-[400px] z-0"></div>
        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MULTI-MODE MEDIA VIEWER & ROUTE NAVIGATION MODAL (PHASE 6.5)              -->
    <!-- ========================================================================= -->
    <div 
        x-data="usuRoomDetailModal()"
        x-show="isOpen"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-5"
        style="display: none;"
    >
        <!-- Backdrop Hitam Transparan -->
        <div 
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeModal()" 
            class="fixed inset-0 bg-slate-950/70 backdrop-blur-md"
        ></div>

        <!-- Modal Content Card (Dark Navy Elegance) -->
        <div 
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative z-10 flex w-full max-w-4xl max-h-[92vh] flex-col overflow-hidden rounded-3xl border border-gray-200 bg-white text-gray-900 shadow-2xl ring-1 ring-black/10"
        >
            <!-- Modal Header with Modern Segmented Control -->
            <div class="flex shrink-0 flex-col justify-between gap-4 border-b border-gray-100 bg-white px-4 py-4 sm:flex-row sm:items-center sm:px-7 sm:py-5">
                <!-- Left: Room & Building Title -->
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-700 shadow-sm">
                            <span x-text="building.code || 'GEDUNG'"></span>
                        </span>
                        <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600">Lt. <span x-text="room.floor || 1"></span> • Kapasitas <span x-text="room.capacity || 30"></span> Org</span>
                    </div>
                    <h3 class="mt-2 flex items-center gap-2 text-lg font-bold leading-tight text-gray-900 sm:text-xl">
                        <i class="fa-solid fa-door-open"></i>
                        <span x-text="room.name ? 'Ruang ' + room.name : 'Detail Ruangan'"></span>
                    </h3>
                    <p class="mt-1 max-w-sm truncate text-xs text-gray-500" x-text="building.name || 'Universitas Sumatera Utara'"></p>
                </div>

                <!-- Right: Segmented Control & Close Button -->
                <div class="flex items-center gap-2.5 self-end sm:self-center">
                    <!-- Segmented Control View Selector -->
                    <div class="inline-flex rounded-2xl border border-green-100 bg-green-50 p-1.5 shadow-inner">
                        <button 
                            type="button" 
                            @click="switchMode('photo')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all duration-200 sm:px-4"
                            :class="currentMode === 'photo' ? 'bg-[#10A760] text-white shadow-md shadow-green-900/20' : 'bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800'"
                        >
                            <i class="fa-regular fa-image"></i>
                            <span>Foto Pintu</span>
                        </button>
                        <button 
                            type="button" 
                            @click="switchMode('360')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all duration-200 sm:px-4"
                            :class="currentMode === '360' ? 'bg-[#10A760] text-white shadow-md shadow-green-900/20' : 'bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800'"
                        >
                            <i class="fa-solid fa-rotate"></i>
                            <span>Panorama 360°</span>
                        </button>
                        <button 
                            type="button" 
                            x-show="Boolean(room.video_url)"
                            @click="switchMode('video')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all duration-200 sm:px-4"
                            :class="currentMode === 'video' ? 'bg-[#10A760] text-white shadow-md shadow-green-900/20' : 'bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800'"
                        >
                            <i class="fa-solid fa-video"></i>
                            <span>Video</span>
                        </button>
                    </div>

                    <!-- Tombol [X] Tutup -->
                    <button 
                        type="button"
                        @click="closeModal()" 
                        class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-green-200 bg-green-50 text-green-700 transition-colors hover:border-green-600 hover:bg-green-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
                        title="Tutup Jendela"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Dynamic Body (Scrollable) -->
            <div class="flex-1 space-y-5 overflow-y-auto bg-white p-3 custom-scrollbar sm:p-6">
                <!-- 1. Media Canvas Area -->
                <div class="relative h-56 w-full overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 shadow-lg sm:h-72 lg:h-80">
                    <!-- Photo Mode -->
                    <div x-show="currentMode === 'photo'" class="relative w-full h-full bg-slate-900 flex items-center justify-center">
                        <img 
                            id="modal-door-photo-img"
                            :src="room.photo_url || '/images/stores/preset-modern-copy.webp'" 
                            :alt="room.name" 
                            class="h-full w-full rounded-2xl object-contain"
                        />
                        <span
                            class="pointer-events-none absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-black/65 px-3 py-1 text-[11px] font-bold text-white backdrop-blur-sm"
                            x-text="room.door_source === 'real' ? 'Foto Pintu Asli' : 'Foto Pintu (Perspektif 360°)'"
                        ></span>
                    </div>

                    <!-- 360 Mode (Clean Interactive Panorama Container) -->
                    <div x-show="currentMode === '360'" class="w-full h-full relative">
                        <div id="modal-panorama-viewer" class="h-full w-full overflow-hidden rounded-2xl bg-gray-100"></div>
                        <div class="pointer-events-none absolute bottom-3 left-3 flex items-center gap-1.5 rounded-full border border-white/20 bg-black/60 px-3 py-1.5 text-[11px] text-white backdrop-blur-sm">
                            <i class="fa-solid fa-rotate animate-pulse"></i>
                            <span>Geser kursor / sentuh layar untuk tur 360°</span>
                        </div>
                    </div>

                    <!-- Video Mode -->
                    <div x-show="currentMode === 'video'" class="w-full h-full bg-black flex items-center justify-center">
                        <video 
                            :src="room.video_url || 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4'" 
                            controls 
                            autoplay 
                            loop 
                            playsinline 
                            class="h-full w-full rounded-2xl object-contain"
                        >
                            Browser Anda tidak mendukung tag video.
                        </video>
                    </div>
                </div>

                <!-- 2. JADWAL PERKULIAHAN DI RUANGAN INI -->
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-sm sm:p-5">
                    <div class="mb-3 flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-600">
                        <span>JADWAL PERKULIAHAN DI RUANGAN INI:</span>
                        <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-[11px] font-bold text-green-700" x-text="(room.schedules?.length || 0) + ' Kelas Terdaftar'"></span>
                    </div>
                    <div class="max-h-48 space-y-2 overflow-y-auto custom-scrollbar sm:max-h-56">
                        <template x-if="room.schedules && room.schedules.length > 0">
                            <template x-for="s in room.schedules" :key="s.id || Math.random()">
                                <div 
                                    class="rounded-xl border p-3 text-xs shadow-sm transition-colors duration-200 sm:p-4"
                                    :class="s.is_my_class ? 'bg-green-50 border-green-300 text-green-900 shadow-sm' : 'bg-white border-gray-200 text-gray-800 hover:bg-green-50 hover:border-green-200'"
                                >
                                    <div class="flex items-center justify-between font-bold">
                                        <span class="flex items-center gap-2">
                                            <i x-show="s.is_my_class" class="fa-solid fa-star text-amber-400"></i>
                                            <span x-text="s.mata_kuliah?.nama || 'Mata Kuliah'"></span>
                                        </span>
                                        <span class="rounded-lg border border-gray-200 bg-gray-100 px-2 py-1 font-mono text-[10px] text-gray-700" x-text="s.hari + ', ' + s.jam_mulai + '-' + s.jam_selesai"></span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-600">
                                        <span><i class="fa-solid fa-chalkboard-user"></i> <span x-text="s.dosen?.nama || 'Dosen Pengampu'"></span></span>
                                        <span class="text-[10px] text-gray-500" x-text="'Kelas ' + (s.kelompok_kelas || s.kelas || 'A')"></span>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <template x-if="!room.schedules || room.schedules.length === 0">
                            <p class="py-4 text-center text-xs italic text-gray-500">Tidak ada jadwal perkuliahan terdaftar di ruangan ini.</p>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Modal Footer: Big Navigation CTA -->
            <div class="shrink-0 border-t border-gray-100 bg-white px-4 py-4 sm:px-7 sm:py-5">
                <button 
                    type="button"
                    @click="startNavigation(building.latitude, building.longitude)" 
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#10A760] to-[#0D8A4E] px-5 py-4 text-sm font-bold text-white shadow-lg shadow-green-900/20 transition-all duration-200 hover:-translate-y-0.5 hover:from-[#0D8A4E] hover:to-[#0B7442] hover:shadow-xl hover:shadow-green-900/25 active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                >
                    <i class="fa-solid fa-route text-lg"></i>
                    <span>Mulai Navigasi Rute Kampus (In-App)</span>
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- Mapbox GL JS 3D Script & Token Injection -->
        <script>
            window.MAPBOX_ACCESS_TOKEN = '{{ env('VITE_MAPBOX_ACCESS_TOKEN', '') }}';

            window.usuRoomDetailModal = function () {
                return {
                    isOpen: false,
                    currentMode: 'photo',
                    room: {},
                    building: {},
                    pannellumViewer: null,
                    init() {
                        window.addEventListener('open-room-detail-modal', (e) => {
                            this.room = e.detail?.room || {};
                            this.building = e.detail?.building || {};
                            this.currentMode = e.detail?.defaultMode || 'photo';
                            this.isOpen = true;
                            this.$nextTick(() => {
                                if (this.currentMode === '360') {
                                    this.init360Viewer();
                                }
                            });
                        });
                    },
                    switchMode(mode) {
                        this.currentMode = mode;
                        if (mode === '360') {
                            this.$nextTick(() => {
                                this.init360Viewer();
                            });
                        }
                        if (mode !== 'video') {
                            const videoEl = this.$el.querySelector('video');
                            if (videoEl) {
                                videoEl.pause();
                            }
                        }
                    },
                    init360Viewer() {
                        const container = document.getElementById('modal-panorama-viewer');
                        if (!container) return;

                        if (this.pannellumViewer) {
                            try { this.pannellumViewer.destroy(); } catch (e) {}
                            this.pannellumViewer = null;
                        }
                        container.innerHTML = '';

                        const photoUrl = this.room?.photo_360_url || this.room?.photo_360 || '/images/stores/preset-modern-copy.webp';
                        const meta = this.room?.photo_360_meta || {};
                        const haov = Number(meta.haov) || 360;
                        const vaov = Number(meta.vaov) || 180;
                        const yaw = Number.isFinite(Number(meta.yaw)) ? Number(meta.yaw) : 0;
                        const pitch = Number.isFinite(Number(meta.pitch)) ? Number(meta.pitch) : 0;
                        const hfov = Number(meta.hfov) || (vaov >= 150 ? 95 : Math.min(90, Math.max(60, vaov * 1.35)));
                        const minPitch = Number.isFinite(Number(meta.min_pitch)) ? Number(meta.min_pitch) : (vaov >= 150 ? -85 : -(vaov * 0.42));
                        const maxPitch = Number.isFinite(Number(meta.max_pitch)) ? Number(meta.max_pitch) : (vaov >= 150 ? 85 : (vaov * 0.42));

                        if (typeof pannellum !== 'undefined') {
                            try {
                                this.pannellumViewer = pannellum.viewer('modal-panorama-viewer', {
                                    type: 'equirectangular',
                                    panorama: photoUrl,
                                    haov: haov,
                                    vaov: vaov,
                                    yaw: yaw,
                                    pitch: pitch,
                                    hfov: hfov,
                                    minPitch: minPitch,
                                    maxPitch: maxPitch,
                                    minHfov: 40,
                                    maxHfov: 115,
                                    autoLoad: true,
                                    autoRotate: -1.5,
                                    showControls: true,
                                    showFullscreenCtrl: false,
                                    compass: false
                                });
                            } catch (err) {
                                console.warn('Pannellum init fallback:', err);
                            }
                        }
                    },
                    closeModal() {
                        this.isOpen = false;
                        if (this.pannellumViewer) {
                            try { this.pannellumViewer.destroy(); } catch (e) {}
                            this.pannellumViewer = null;
                        }
                        const videoEl = this.$el.querySelector('video');
                        if (videoEl) {
                            videoEl.pause();
                            videoEl.currentTime = 0;
                        }
                    },
                    startNavigation(lat, lng) {
                        const destLat = lat || this.building?.latitude;
                        const destLng = lng || this.building?.longitude;
                        const targetKey = this.building?.id
                            ? ('building:' + this.building.id)
                            : ((destLat && destLng) ? (destLat + ',' + destLng) : '');

                        if (!targetKey) return;
                        this.closeModal();

                        if (window.USUClassMap && typeof window.USUClassMap.startRouteTo === 'function') {
                            window.USUClassMap.startRouteTo(targetKey);
                        } else {
                            window.location.href = '/map?route_to=' + encodeURIComponent(targetKey);
                        }
                    }
                };
            };

            window.usuRoutePanel = function () {
                return {
                    isOpen: false,
                    isLoading: false,
                    isLocatingGps: false,
                    pickingOriginOnMap: false,
                    liveGpsTracking: false,
                    originSource: 'none',
                    mode: 'walking',
                    originKey: '',
                    destinationKey: '',
                    customOriginKey: '',
                    customOriginLabel: '',
                    customDestinationKey: '',
                    customDestinationLabel: '',
                    destinations: window.__usuRoutingDestinations || { buildings: [], fotocopy: [] },
                    routeResult: null,
                    statusMsg: '',
                    isError: false,
                    diagnostic: null,
                    alreadyAtDestinationMsg: '',
                    activeStepIndex: null,

                    init() {
                        if (window.__usuRoutingDestinations) {
                            this.destinations = window.__usuRoutingDestinations;
                        }

                        window.addEventListener('usu-routing-destinations-loaded', (e) => {
                            if (e.detail) {
                                this.destinations = e.detail;
                                this.$nextTick(() => {
                                    if (this.originKey) {
                                        this.syncCustomEndpointOption('origin', this.originKey, this.customOriginLabel);
                                    }
                                    if (this.destinationKey) {
                                        this.syncCustomEndpointOption('destination', this.destinationKey, this.customDestinationLabel);
                                    }
                                });
                            }
                        });

                        window.addEventListener('usu-route-state-changed', (e) => {
                            const d = e.detail || {};
                            if (typeof d.isOpen === 'boolean') this.isOpen = d.isOpen;
                            if (typeof d.isLoading === 'boolean') this.isLoading = d.isLoading;
                            if (typeof d.isLocatingGps === 'boolean') this.isLocatingGps = d.isLocatingGps;
                            if (typeof d.pickingOriginOnMap === 'boolean') this.pickingOriginOnMap = d.pickingOriginOnMap;
                            if (typeof d.liveGpsTracking === 'boolean') this.liveGpsTracking = d.liveGpsTracking;
                            if (d.originSource !== undefined) this.originSource = d.originSource;
                            if (d.mode) this.mode = d.mode;
                            if (d.originKey !== undefined) {
                                this.syncCustomEndpointOption('origin', d.originKey, d.originLabel);
                                this.$nextTick(() => {
                                    this.originKey = d.originKey;
                                });
                            }
                            if (d.destinationKey !== undefined) {
                                this.syncCustomEndpointOption('destination', d.destinationKey, d.destinationLabel);
                                this.$nextTick(() => {
                                    this.destinationKey = d.destinationKey;
                                });
                            }
                            if (d.routeResult !== undefined) {
                                this.routeResult = d.routeResult;
                                this.activeStepIndex = null;
                            }
                            if (d.statusMsg !== undefined) this.statusMsg = d.statusMsg;
                            if (d.isError !== undefined) this.isError = d.isError;
                            if (d.diagnostic !== undefined) this.diagnostic = d.diagnostic;
                            if (d.alreadyAtDestinationMsg !== undefined) this.alreadyAtDestinationMsg = d.alreadyAtDestinationMsg;
                        });
                    },

                    syncCustomEndpointOption(side, key, label) {
                        if (!key) {
                            if (side === 'origin') {
                                this.customOriginKey = '';
                                this.customOriginLabel = '';
                            } else {
                                this.customDestinationKey = '';
                                this.customDestinationLabel = '';
                            }
                            return;
                        }

                        const known = [...(this.destinations.buildings || []), ...(this.destinations.fotocopy || [])]
                            .some((item) => item.key === key);

                        if (known) {
                            if (side === 'origin') {
                                this.customOriginKey = '';
                                this.customOriginLabel = '';
                            } else {
                                this.customDestinationKey = '';
                                this.customDestinationLabel = '';
                            }
                            return;
                        }

                        const fallbackLabel = label || (key.includes(',') ? ('Koordinat: ' + key) : key);
                        if (side === 'origin') {
                            this.customOriginKey = key;
                            this.customOriginLabel = fallbackLabel;
                        } else {
                            this.customDestinationKey = key;
                            this.customDestinationLabel = fallbackLabel;
                        }
                    },

                    open() {
                        if (window.USUClassMap && typeof window.USUClassMap.openRoutePanel === 'function') {
                            window.USUClassMap.openRoutePanel();
                        } else {
                            this.isOpen = true;
                        }
                    },

                    close() {
                        if (window.USUClassMap && typeof window.USUClassMap.closeRoutePanel === 'function') {
                            window.USUClassMap.closeRoutePanel();
                        } else {
                            this.isOpen = false;
                        }
                    },

                    setMode(nextMode) {
                        this.mode = nextMode;
                        if (window.USUClassMap && typeof window.USUClassMap.setRouteMode === 'function') {
                            window.USUClassMap.setRouteMode(nextMode);
                            return;
                        }
                        if (this.originKey && this.destinationKey) {
                            this.calculateRoute();
                        }
                    },

                    onOriginChanged() {
                        this.statusMsg = '';
                        this.isError = false;
                        this.diagnostic = null;
                        this.alreadyAtDestinationMsg = '';
                        if (window.USUClassMap && typeof window.USUClassMap.setManualOrigin === 'function') {
                            window.USUClassMap.setManualOrigin(this.originKey, this.originKey === this.customOriginKey ? this.customOriginLabel : '');
                            return;
                        }
                        if (this.originKey && this.destinationKey) {
                            this.calculateRoute();
                        }
                    },

                    onDestinationChanged() {
                        this.statusMsg = '';
                        this.isError = false;
                        this.diagnostic = null;
                        this.alreadyAtDestinationMsg = '';
                        if (!this.destinationKey) {
                            return;
                        }
                        if (window.USUClassMap && typeof window.USUClassMap.selectRouteDestination === 'function') {
                            window.USUClassMap.selectRouteDestination(this.destinationKey);
                            return;
                        }
                        if (this.originKey && this.destinationKey) {
                            this.calculateRoute();
                        }
                    },

                    focusOriginDropdown() {
                        const sel = document.getElementById('route-origin-select');
                        if (sel) {
                            sel.focus();
                        }
                    },

                    toggleLiveTracking() {
                        if (window.USUClassMap && typeof window.USUClassMap.toggleLiveGpsTracking === 'function') {
                            window.USUClassMap.toggleLiveGpsTracking();
                        }
                    },

                    swapEndpoints() {
                        const prevOrig = this.originKey;
                        const prevDest = this.destinationKey;
                        const prevOrigLabel = this.customOriginLabel;
                        const prevDestLabel = this.customDestinationLabel;

                        this.syncCustomEndpointOption('origin', prevDest, prevDestLabel);
                        this.syncCustomEndpointOption('destination', prevOrig, prevOrigLabel);
                        this.$nextTick(() => {
                            this.originKey = prevDest;
                            this.destinationKey = prevOrig;
                            if (this.originKey && this.destinationKey) {
                                if (window.USUClassMap && typeof window.USUClassMap.setManualOrigin === 'function') {
                                    window.USUClassMap.setManualOrigin(this.originKey, this.originKey === this.customOriginKey ? this.customOriginLabel : '', false);
                                }
                                this.calculateRoute();
                            }
                        });
                    },

                    useGpsOrigin() {
                        if (window.USUClassMap && typeof window.USUClassMap.useGpsAsOrigin === 'function') {
                            window.USUClassMap.useGpsAsOrigin();
                        }
                    },

                    togglePickOriginOnMap() {
                        if (window.USUClassMap && typeof window.USUClassMap.togglePickOriginOnMap === 'function') {
                            window.USUClassMap.togglePickOriginOnMap();
                        }
                    },

                    calculateRoute() {
                        if (window.USUClassMap && typeof window.USUClassMap.calculateInAppRoute === 'function') {
                            window.USUClassMap.calculateInAppRoute(this.originKey, this.destinationKey, this.mode);
                        }
                    },

                    focusRoute() {
                        if (window.USUClassMap && typeof window.USUClassMap.focusCurrentRoute === 'function') {
                            window.USUClassMap.focusCurrentRoute();
                        }
                    },

                    clearRoute() {
                        this.routeResult = null;
                        this.originKey = '';
                        this.destinationKey = '';
                        this.customOriginKey = '';
                        this.customOriginLabel = '';
                        this.customDestinationKey = '';
                        this.customDestinationLabel = '';
                        this.originSource = 'none';
                        this.liveGpsTracking = false;
                        this.statusMsg = '';
                        this.isError = false;
                        this.diagnostic = null;
                        this.alreadyAtDestinationMsg = '';
                        this.activeStepIndex = null;
                        if (window.USUClassMap && typeof window.USUClassMap.clearRoute === 'function') {
                            window.USUClassMap.clearRoute();
                        }
                    },

                    focusStep(step, idx) {
                        this.activeStepIndex = idx;
                        if (window.USUClassMap && typeof window.USUClassMap.focusRouteStep === 'function') {
                            window.USUClassMap.focusRouteStep(step);
                        }
                    },

                    engineLabel() {
                        return this.routeResult?.engine_label
                            || this.routeResult?.engine_source
                            || (this.mode === 'driving' ? 'Graf Jalan Kendaraan USU' : 'Graf Pejalan Kaki USU');
                    },

                    auditText() {
                        const audit = this.routeResult?.geometry_audit;
                        if (!audit) return 'Geometri rute telah diverifikasi';
                        if (audit.verified) {
                            return 'Rute aman: 0 tembus gedung, 0 lompati parit';
                        }
                        return `Perlu perhatian: ${audit.building_intersections || 0} gedung, ${audit.barrier_intersections || 0} barrier`;
                    },

                    maneuverIcon(maneuver) {
                        switch (maneuver) {
                            case 'start':
                            case 'depart': return 'fa-play text-emerald-600';
                            case 'arrive': return 'fa-flag-checkered text-blue-600';
                            case 'bridge': return 'fa-bridge text-sky-600';
                            case 'access': return 'fa-door-open text-amber-600';
                            case 'turn-left':
                            case 'turn_left':
                            case 'slight_left': return 'fa-arrow-turn-up -rotate-90 text-gray-700';
                            case 'turn-right':
                            case 'turn_right':
                            case 'slight_right': return 'fa-arrow-turn-up rotate-90 text-gray-700';
                            case 'uturn': return 'fa-rotate-left text-gray-700';
                            default: return 'fa-arrow-up text-gray-700';
                        }
                    },

                    formatStepDistance(meters) {
                        const num = Number(meters || 0);
                        if (!Number.isFinite(num) || num <= 0) return 'Di titik ini';
                        if (num >= 1000) return (num / 1000).toFixed(2).replace('.', ',') + ' km';
                        return Math.round(num) + ' m';
                    }
                };
            };
        </script>
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>

        <!-- Pannellum 360 JS CDN -->
        <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
        
        <!-- App Map Module via Vite -->
        @vite(['resources/js/features/map.js'])
    @endpush
</x-layouts.app>
