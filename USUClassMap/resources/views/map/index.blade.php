<x-layouts.app>
    @push('styles')
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <!-- Pannellum 360 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css" />
        <style>
            /* Custom Full-Bleed Popup styling to match USU identity (Phase 6.2) */
            .custom-popup .leaflet-popup-content-wrapper,
            .usu-custom-popup .leaflet-popup-content-wrapper {
                border-radius: 12px !important;
                padding: 0 !important;
                overflow: hidden !important;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.18), 0 10px 10px -5px rgba(0, 0, 0, 0.08) !important;
                border: 1px solid rgba(229, 231, 235, 0.9) !important;
                background: #ffffff !important;
            }
            .custom-popup .leaflet-popup-content,
            .usu-custom-popup .leaflet-popup-content {
                margin: 0 !important;
                width: 340px !important;
                max-width: 340px !important;
                line-height: 1.4 !important;
            }
            .custom-popup .leaflet-popup-tip,
            .usu-custom-popup .leaflet-popup-tip {
                background: white !important;
            }
            .custom-leaflet-marker {
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
                        placeholder="Cari mata kuliah atau dosen..."
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
            </div>

            <!-- Results Summary Bar -->
            <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 flex items-center justify-between text-xs">
                <span id="results-count" class="font-bold text-gray-700">Memuat data gedung...</span>
                <span id="source-badge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800">
                    Semua Gedung
                </span>
            </div>

            <!-- Scrollable Search Results List -->
            <div class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-3" id="search-results">
                <!-- Populated dynamically via resources/js/map.js -->
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
                        <span class="w-3 h-3 rounded-full bg-[#10A760] inline-block shadow-xs"></span>
                        <span>Pin Hijau: Gedung Perkuliahan USU</span>
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
            <div id="map" class="w-full h-full z-0"></div>
        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MULTI-MODE MEDIA VIEWER & ROUTE NAVIGATION MODAL (PHASE 6.5)              -->
    <!-- ========================================================================= -->
    <div 
        x-data="{
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
                    try { this.pannellumViewer.destroy(); } catch(e) {}
                    this.pannellumViewer = null;
                }
                container.innerHTML = '';
                
                const photoUrl = this.room?.photo_360_url || this.room?.photo_360 || 'https://images.unsplash.com/photo-1557971370-e7298ee473fb?auto=format&fit=crop&w=2000&q=80';
                
                if (typeof pannellum !== 'undefined') {
                    try {
                        this.pannellumViewer = pannellum.viewer('modal-panorama-viewer', {
                            type: 'equirectangular',
                            panorama: photoUrl,
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
                    try { this.pannellumViewer.destroy(); } catch(e) {}
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
                if (!destLat || !destLng) return;
                
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const userLat = position.coords.latitude;
                            const userLng = position.coords.longitude;
                            const navUrl = `https://www.google.com/maps/dir/?api=1&origin=${userLat},${userLng}&destination=${destLat},${destLng}&travelmode=walking`;
                            window.open(navUrl, '_blank');
                        },
                        (error) => {
                            console.warn('Geolocation error or permission denied:', error);
                            const navUrl = `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}&travelmode=walking`;
                            window.open(navUrl, '_blank');
                        },
                        { timeout: 5000, enableHighAccuracy: true }
                    );
                } else {
                    const navUrl = `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}&travelmode=walking`;
                    window.open(navUrl, '_blank');
                }
            }
        }"
        x-show="isOpen"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-6"
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
            class="fixed inset-0 bg-black/80 backdrop-blur-sm"
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
            class="relative w-full max-w-3xl bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl overflow-hidden z-10 text-white flex flex-col max-h-[90vh]"
        >
            <!-- Modal Header with Modern Segmented Control -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-3.5 bg-gray-900 border-b border-gray-800 gap-3 shrink-0">
                <!-- Left: Room & Building Title -->
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block px-2 py-0.5 bg-[#10A760] text-white text-[10px] font-black rounded-md uppercase tracking-wider shadow-2xs">
                            <span x-text="building.code || 'GEDUNG'"></span>
                        </span>
                        <span class="text-xs font-semibold text-gray-400">Lt. <span x-text="room.floor || 1"></span> • Kapasitas <span x-text="room.capacity || 30"></span> Org</span>
                    </div>
                    <h3 class="text-lg font-bold text-white mt-1 leading-tight flex items-center gap-1.5">
                        <span>🚪</span>
                        <span x-text="room.name ? 'Ruang ' + room.name : 'Detail Ruangan'"></span>
                    </h3>
                    <p class="text-xs text-gray-400 truncate max-w-sm mt-0.5" x-text="building.name || 'Universitas Sumatera Utara'"></p>
                </div>

                <!-- Right: Segmented Control & Close Button -->
                <div class="flex items-center gap-2 self-end sm:self-center">
                    <!-- Segmented Control View Selector -->
                    <div class="inline-flex p-1 bg-gray-800 border border-gray-700/80 rounded-xl">
                        <button 
                            type="button" 
                            @click="switchMode('photo')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                            :class="currentMode === 'photo' ? 'bg-[#10A760] text-white shadow-xs' : 'text-gray-400 hover:text-gray-200'"
                        >
                            <span>🖼️</span>
                            <span>Foto</span>
                        </button>
                        <button 
                            type="button" 
                            @click="switchMode('360')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                            :class="currentMode === '360' ? 'bg-[#10A760] text-white shadow-xs' : 'text-gray-400 hover:text-gray-200'"
                        >
                            <span>🔄</span>
                            <span>360°</span>
                        </button>
                        <button 
                            type="button" 
                            @click="switchMode('video')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                            :class="currentMode === 'video' ? 'bg-[#10A760] text-white shadow-xs' : 'text-gray-400 hover:text-gray-200'"
                        >
                            <span>🎥</span>
                            <span>Video</span>
                        </button>
                    </div>

                    <!-- Tombol [X] Tutup -->
                    <button 
                        type="button"
                        @click="closeModal()" 
                        class="w-9 h-9 rounded-xl bg-gray-800 hover:bg-red-600/80 text-gray-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer border border-gray-700/80"
                        title="Tutup Jendela"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Dynamic Body (Scrollable) -->
            <div class="p-4 sm:p-5 bg-black/60 space-y-4 overflow-y-auto custom-scrollbar flex-1">
                <!-- 1. Media Canvas Area -->
                <div class="relative w-full h-64 sm:h-72 rounded-xl overflow-hidden bg-gray-950 border border-gray-800 shadow-inner">
                    <!-- Photo Mode -->
                    <div x-show="currentMode === 'photo'" class="w-full h-full">
                        <img 
                            :src="room.photo_url || 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=800&q=80'" 
                            :alt="room.name" 
                            class="w-full h-full object-cover rounded-xl"
                        />
                    </div>

                    <!-- 360 Mode (Clean Interactive Panorama Container) -->
                    <div x-show="currentMode === '360'" class="w-full h-full relative">
                        <div id="modal-panorama-viewer" class="w-full h-full rounded-xl overflow-hidden bg-gray-900"></div>
                        <div class="absolute bottom-2.5 left-2.5 px-2.5 py-1 bg-black/60 backdrop-blur-xs rounded-full text-[11px] text-gray-300 pointer-events-none flex items-center gap-1.5 border border-white/10">
                            <span class="animate-pulse">🔄</span>
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
                            class="w-full h-full object-contain rounded-xl"
                        >
                            Browser Anda tidak mendukung tag video.
                        </video>
                    </div>
                </div>

                <!-- 2. JADWAL PERKULIAHAN DI RUANGAN INI -->
                <div class="bg-gray-800/80 rounded-xl p-3.5 border border-gray-700/60">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>JADWAL PERKULIAHAN DI RUANGAN INI:</span>
                        <span class="text-[11px] text-[#10A760] font-semibold" x-text="(room.schedules?.length || 0) + ' Kelas Terdaftar'"></span>
                    </div>
                    <div class="max-h-36 overflow-y-auto custom-scrollbar space-y-1.5">
                        <template x-if="room.schedules && room.schedules.length > 0">
                            <template x-for="s in room.schedules" :key="s.id || Math.random()">
                                <div 
                                    class="p-2.5 rounded-lg text-xs"
                                    :class="s.is_my_class ? 'bg-amber-500/20 border border-amber-400/50 text-amber-200' : 'bg-gray-700/60 border border-gray-600/40 text-gray-300'"
                                >
                                    <div class="flex items-center justify-between font-bold">
                                        <span class="flex items-center gap-1">
                                            <span x-show="s.is_my_class" class="text-amber-400">⭐</span>
                                            <span x-text="s.mata_kuliah?.nama || 'Mata Kuliah'"></span>
                                        </span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-black/30 font-mono" x-text="s.hari + ', ' + s.jam_mulai + '-' + s.jam_selesai"></span>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-1 flex items-center justify-between">
                                        <span x-text="'👨‍🏫 ' + (s.dosen?.nama || 'Dosen Pengampu')"></span>
                                        <span class="text-[10px] text-gray-500" x-text="'Kelas ' + (s.kelompok_kelas || s.kelas || 'A')"></span>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <template x-if="!room.schedules || room.schedules.length === 0">
                            <p class="text-gray-400 text-xs italic py-2 text-center">Tidak ada jadwal perkuliahan terdaftar di ruangan ini.</p>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Modal Footer: Big Navigation CTA -->
            <div class="p-4 sm:p-5 bg-gray-900 border-t border-gray-800 shrink-0">
                <button 
                    type="button"
                    @click="startNavigation(building.latitude, building.longitude)" 
                    class="w-full bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold py-3.5 px-4 rounded-xl shadow-lg hover:shadow-green-900/30 flex items-center justify-center gap-2 text-sm transition-all duration-200 cursor-pointer active:scale-[0.99]"
                >
                    <span class="text-lg">📍</span>
                    <span>Jalankan Rute Kelas (Google Maps Walk Navigation)</span>
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- Leaflet JS CDN -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <!-- Pannellum 360 JS CDN -->
        <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
        
        <!-- App Map Module via Vite -->
        @vite(['resources/js/map.js'])
    @endpush
</x-layouts.app>
