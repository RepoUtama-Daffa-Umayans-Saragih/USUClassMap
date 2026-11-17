<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USUStarterMap - Peta Ruang Kelas Kampus USU</title>
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body 
    class="bg-[#F3F4F6] text-gray-900 font-sans antialiased"
    x-data="{ mobileMenu: false }"
>
    <x-navbar.public />

    <!-- Main Content Area -->
    <main class="pt-16 min-h-[calc(100vh-4rem)]">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <!-- Hero Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 sm:p-12 text-center">
                <div class="inline-flex justify-center items-center w-20 h-20 rounded-3xl bg-green-50 text-[#10A760] mb-6 shadow-xs">
                    <x-application-logo class="w-16 h-16 object-contain" />
                </div>
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight mb-4">
                    <span class="text-[#10A760]">USU</span>StarterMap
                </h1>
                <p class="text-xl sm:text-2xl text-gray-700 font-bold mb-4">Temukan Kelas MKWK/MKWU Anda di Kampus USU</p>
                <p class="text-gray-500 max-w-2xl mx-auto mb-8 text-base sm:text-lg leading-relaxed">
                    Aplikasi interaktif untuk memudahkan mahasiswa Universitas Sumatera Utara dalam mencari dan menemukan lokasi kelas mata kuliah wajib kurikulum dan universitas beserta jadwal dan informasi dosen secara presisi.
                </p>
                
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="/map" class="w-full sm:w-auto px-8 py-3.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">
                        Buka Peta Interaktif
                    </a>
                    <a href="{{ route('fotocopy.index') }}" class="w-full sm:w-auto px-8 py-3.5 bg-[#D4A017] hover:bg-[#B8880F] text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                        Layanan Fotocopy
                    </a>
                    <a href="/faq" class="w-full sm:w-auto px-8 py-3.5 bg-white text-[#10A760] border-2 border-[#10A760] hover:bg-green-50 font-bold rounded-xl shadow-xs transition-all">
                        Lihat FAQ
                    </a>
                </div>
            </div>

            <section id="bookmarks" class="mt-10 scroll-mt-24 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-5 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div class="flex items-center gap-4">
                        @auth
                            @if (Auth::user()->profile_photo_path)
                                <img src="{{ Storage::disk('public')->url(Auth::user()->profile_photo_path) }}" alt="Foto {{ Auth::user()->name }}" class="h-16 w-16 rounded-full object-cover ring-4 ring-green-50">
                            @else
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-[#10A760] text-2xl font-bold text-white ring-4 ring-green-50">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#10A760]">Profil Mahasiswa</p>
                                <h2 class="mt-1 text-xl font-extrabold text-gray-900">{{ Auth::user()->name }}</h2>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ Auth::user()->nim ?? 'Mahasiswa USU' }}@if(Auth::user()->semester) • Semester {{ Auth::user()->semester }}@endif
                                </p>
                            </div>
                        @else
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-green-50 text-[#10A760] ring-4 ring-green-50">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#10A760]">Jadwal Favorit</p>
                                <h2 class="mt-1 text-xl font-extrabold text-gray-900">Jadwal Tersimpan</h2>
                                <p class="mt-1 text-xs text-gray-500">Masuk untuk menampilkan nama dan foto profil mahasiswa.</p>
                            </div>
                        @endauth
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @auth
                            <a href="{{ route('profile.edit') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-700 transition-colors hover:border-[#10A760] hover:text-[#10A760]">
                                Ubah Foto Profil
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-700 transition-colors hover:border-[#10A760] hover:text-[#10A760]">
                                Masuk
                            </a>
                        @endauth
                        <a href="/map" class="rounded-lg bg-[#10A760] px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-[#0D8A4E]">
                            Buka Peta
                        </a>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Jadwal Bookmark</h3>
                            <p class="mt-1 text-xs text-gray-500"><span id="bookmarks-count">0</span> jadwal tersimpan di browser ini.</p>
                        </div>
                        <button
                            id="clear-all-bookmarks-btn"
                            type="button"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-600 transition-colors hover:bg-red-100"
                            style="display: none;"
                        >
                            Hapus Semua
                        </button>
                    </div>

                    <div id="empty-bookmarks-state" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-10 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-[#10A760]">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                            </svg>
                        </div>
                        <h4 class="mt-3 text-sm font-bold text-gray-900">Belum ada jadwal tersimpan</h4>
                        <p class="mt-1 text-xs text-gray-500">Simpan jadwal dari peta dengan menekan ikon bookmark.</p>
                        <a href="/map" class="mt-4 inline-flex rounded-lg bg-[#10A760] px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-[#0D8A4E]">
                            Cari Jadwal di Peta
                        </a>
                    </div>

                    <div id="bookmarks-table-container" class="overflow-x-auto" style="display: none;">
                        <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-3 font-bold">Mata Kuliah</th>
                                    <th class="px-3 py-3 font-bold">Hari & Waktu</th>
                                    <th class="px-3 py-3 font-bold">Dosen</th>
                                    <th class="px-3 py-3 font-bold">Gedung / Ruang</th>
                                    <th class="px-3 py-3 text-right font-bold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="bookmarks-table-body" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </section>
            </div>
        </div>
    </main>

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
                const container = document.getElementById('modal-panorama-viewer-welcome');
                if (!container) return;
                
                if (this.pannellumViewer) {
                    try { this.pannellumViewer.destroy(); } catch(e) {}
                    this.pannellumViewer = null;
                }
                container.innerHTML = '';
                
                const photoUrl = this.room?.photo_360_url || this.room?.photo_360 || 'https://images.unsplash.com/photo-1557971370-e7298ee473fb?auto=format&fit=crop&w=2000&q=80';
                
                if (typeof pannellum !== 'undefined') {
                    try {
                        this.pannellumViewer = pannellum.viewer('modal-panorama-viewer-welcome', {
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
                            console.warn('Geolocation failed or permission denied:', error);
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
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-md"
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
                        <span>🚪</span>
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
                            <span>🖼️</span>
                            <span>Foto</span>
                        </button>
                        <button 
                            type="button" 
                            @click="switchMode('360')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all duration-200 sm:px-4"
                            :class="currentMode === '360' ? 'bg-[#10A760] text-white shadow-md shadow-green-900/20' : 'bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800'"
                        >
                            <span>🔄</span>
                            <span>360°</span>
                        </button>
                        <button 
                            type="button" 
                            @click="switchMode('video')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all duration-200 sm:px-4"
                            :class="currentMode === 'video' ? 'bg-[#10A760] text-white shadow-md shadow-green-900/20' : 'bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800'"
                        >
                            <span>🎥</span>
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
                    <div x-show="currentMode === 'photo'" class="w-full h-full">
                        <img 
                            :src="room.photo_url || 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=800&q=80'" 
                            :alt="room.name" 
                            class="h-full w-full rounded-2xl object-cover"
                        />
                    </div>

                    <!-- 360 Mode (Clean Interactive Panorama Container) -->
                    <div x-show="currentMode === '360'" class="w-full h-full relative">
                        <div id="modal-panorama-viewer-welcome" class="h-full w-full overflow-hidden rounded-2xl bg-gray-100"></div>
                        <div class="pointer-events-none absolute bottom-3 left-3 flex items-center gap-1.5 rounded-full border border-white/20 bg-black/60 px-3 py-1.5 text-[11px] text-white backdrop-blur-sm">
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
                                            <span x-show="s.is_my_class" class="text-amber-400">⭐</span>
                                            <span x-text="s.mata_kuliah?.nama || 'Mata Kuliah'"></span>
                                        </span>
                                        <span class="rounded-lg border border-gray-200 bg-gray-100 px-2 py-1 font-mono text-[10px] text-gray-700" x-text="s.hari + ', ' + s.jam_mulai + '-' + s.jam_selesai"></span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-600">
                                        <span x-text="'👨‍🏫 ' + (s.dosen?.nama || 'Dosen Pengampu')"></span>
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
                    <span class="text-lg">📍</span>
                    <span>Jalankan Rute Kelas (Google Maps Walk Navigation)</span>
                </button>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 mt-16 text-gray-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 sm:pt-12 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-10">
                <div class="md:col-span-5 space-y-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-[#10A760] text-white flex items-center justify-center font-black text-sm shadow-xs">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span class="text-xl font-extrabold tracking-tight text-gray-900">
                            USU<span class="text-[#10A760]">StarterMap</span>
                        </span>
                    </div>

                    <div class="text-xs leading-relaxed">
                        <div class="font-bold text-gray-900 text-sm mb-0.5">
                            Pusat Sistem Informasi (PSI) USU
                        </div>
                        <p class="text-gray-600">Jl. Universitas No. 9, Kampus Padang Bulan</p>
                        <p class="text-gray-600">Medan, Sumatera Utara 20155</p>
                        <p class="text-gray-600 mt-0.5">Phone: +62 812-6000-8787</p>
                    </div>

                    <div class="text-xs leading-relaxed">
                        <div class="font-bold text-gray-900 text-sm mb-0.5">
                            Biro Pusat Administrasi USU
                        </div>
                        <p class="text-gray-600">Gedung Biro Rektorat Kampus USU</p>
                        <p class="text-gray-600">Jl. dr. T. Mansur No. 9, Medan 20155</p>
                        <p class="text-gray-600 mt-0.5">Phone: (061) 8211633</p>
                    </div>

                    <div class="flex items-center gap-4 text-lg text-gray-700 pt-1">
                        <a href="https://instagram.com/usuclassmap" target="_blank" class="hover:text-[#10A760] transition-colors" title="Instagram Resmi">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="https://wa.me/6281260008787" target="_blank" class="hover:text-[#10A760] transition-colors" title="WhatsApp Helpdesk">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                        <a href="mailto:support.classmap@usu.ac.id" class="hover:text-[#10A760] transition-colors" title="Email Bantuan">
                            <i class="fa-solid fa-envelope"></i>
                        </a>
                    </div>
                </div>

                <div class="md:col-span-3 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <h3 class="font-bold text-gray-900 text-xs sm:text-sm mb-3">Tentang USU</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Peta Kampus</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Tur 360°</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Gedung Kelas</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Daftar Dosen</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 text-xs sm:text-sm mb-3">Layanan</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li><a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760] transition-colors">Fotocopy</a></li>
                            <li><a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760] transition-colors">Pesan Cetak</a></li>
                            <li><a href="/#bookmarks" class="hover:text-[#10A760] transition-colors">Bookmark</a></li>
                            <li><a href="{{ route('faq') }}" class="hover:text-[#10A760] transition-colors">Bantuan FAQ</a></li>
                        </ul>
                    </div>
                </div>

                <div class="md:col-span-4">
                    <div class="border border-gray-300 sm:border-[#10A760]/30 rounded-xl p-4 sm:p-5 bg-white shadow-xs">
                        <h4 class="font-bold text-gray-900 text-sm mb-1">
                            Akses Peta & Navigasi Kampus.
                        </h4>
                        <p class="text-xs text-gray-600 leading-relaxed mb-4">
                            Dapatkan rute jalan presisi ke gedung ruang kelas, lokasi sekretariat PSI USU, dan mitra fotocopy terdekat.
                        </p>

                        <div class="space-y-2">
                            <a
                                href="/map"
                                class="w-full flex items-center justify-between px-4 py-2.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white rounded-lg text-xs font-bold transition-colors shadow-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                    <span>Buka di Peta Kampus USU</span>
                                </div>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>

                            <a
                                href="https://maps.google.com/?q=Pusat+Sistem+Informasi+USU+Medan"
                                target="_blank"
                                class="w-full flex items-center justify-between px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-lg text-xs font-semibold transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-diamond-turn-right text-[#D4A017]"></i>
                                    <span>Petunjuk Arah Google Maps</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-gray-400"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10 pt-6 border-t border-gray-200 text-center text-xs text-gray-500">
                &copy; 2000&ndash;2026 Universitas Sumatera Utara. All rights reserved.
            </div>
        </div>
    </footer>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bookmarksKey = 'usuclassmap_bookmarks';
            const tableContainer = document.getElementById('bookmarks-table-container');
            const tableBody = document.getElementById('bookmarks-table-body');
            const emptyState = document.getElementById('empty-bookmarks-state');
            const countLabel = document.getElementById('bookmarks-count');
            const clearAllButton = document.getElementById('clear-all-bookmarks-btn');

            function getBookmarks() {
                try {
                    const bookmarks = JSON.parse(localStorage.getItem(bookmarksKey) || '[]');
                    return Array.isArray(bookmarks) ? bookmarks.filter(bookmark => bookmark && typeof bookmark === 'object') : [];
                } catch (error) {
                    console.error('Gagal membaca jadwal bookmark:', error);
                    return [];
                }
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                })[character]);
            }

            function renderBookmarks() {
                const bookmarks = getBookmarks();
                const hasBookmarks = bookmarks.length > 0;

                countLabel.textContent = bookmarks.length;
                tableContainer.style.display = hasBookmarks ? 'block' : 'none';
                emptyState.style.display = hasBookmarks ? 'none' : 'block';
                clearAllButton.style.display = hasBookmarks ? 'inline-flex' : 'none';

                tableBody.innerHTML = bookmarks.map((bookmark, index) => {
                    const latitude = Number.parseFloat(bookmark.latitude);
                    const longitude = Number.parseFloat(bookmark.longitude);
                    const destinationLatitude = Number.isFinite(latitude) ? latitude : 3.565245;
                    const destinationLongitude = Number.isFinite(longitude) ? longitude : 98.656520;
                    const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${destinationLatitude},${destinationLongitude}&travelmode=walking`;
                    const mapUrl = `/map?q=${encodeURIComponent(bookmark.kodeMk || bookmark.namaMk || '')}`;

                    return `
                        <tr class="align-top text-gray-700">
                            <td class="px-3 py-4">
                                <span class="inline-flex rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">${escapeHtml(bookmark.kodeMk || 'MKWK')}</span>
                                <div class="mt-1 font-bold text-gray-900">${escapeHtml(bookmark.namaMk || 'Mata Kuliah')}</div>
                            </td>
                            <td class="px-3 py-4">
                                <div class="font-semibold text-gray-900">${escapeHtml(bookmark.hari || '-')}</div>
                                <div class="mt-1 text-xs text-gray-500">${escapeHtml(bookmark.jamMulai || '')} - ${escapeHtml(bookmark.jamSelesai || '')}</div>
                            </td>
                            <td class="px-3 py-4">${escapeHtml(bookmark.dosen || 'Dosen Pengampu')}</td>
                            <td class="px-3 py-4">
                                <div class="font-semibold text-emerald-700">${escapeHtml(bookmark.gedung || '-')}</div>
                                <div class="mt-1 text-xs text-gray-500">Ruang ${escapeHtml(bookmark.ruangan || '-')}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-right">
                                <a href="${escapeHtml(mapUrl)}" class="inline-flex rounded-md bg-green-50 px-2.5 py-1.5 text-xs font-bold text-[#10A760] hover:bg-green-100">Peta</a>
                                <a href="${escapeHtml(routeUrl)}" target="_blank" rel="noopener noreferrer" class="ml-1 inline-flex rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-200">Rute</a>
                                <button type="button" data-remove-bookmark="${index}" class="ml-1 inline-flex rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-bold text-red-600 hover:bg-red-100">Hapus</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            function notifyBookmarkUpdate(count) {
                window.dispatchEvent(new CustomEvent('usuclassmap:bookmarks-updated', {
                    detail: { count },
                }));
            }

            tableBody.addEventListener('click', event => {
                const button = event.target.closest('[data-remove-bookmark]');
                if (!button) return;

                const bookmarks = getBookmarks();
                const index = Number(button.dataset.removeBookmark);
                if (!Number.isInteger(index) || index < 0 || index >= bookmarks.length) return;

                bookmarks.splice(index, 1);
                try {
                    localStorage.setItem(bookmarksKey, JSON.stringify(bookmarks));
                    notifyBookmarkUpdate(bookmarks.length);
                    renderBookmarks();
                } catch (error) {
                    console.error('Gagal menghapus jadwal bookmark:', error);
                    window.alert('Jadwal tidak dapat dihapus. Periksa pengaturan penyimpanan browser Anda.');
                }
            });

            clearAllButton.addEventListener('click', () => {
                if (!window.confirm('Apakah Anda yakin ingin menghapus seluruh jadwal tersimpan?')) return;

                try {
                    localStorage.removeItem(bookmarksKey);
                    notifyBookmarkUpdate(0);
                    renderBookmarks();
                } catch (error) {
                    console.error('Gagal menghapus semua jadwal bookmark:', error);
                    window.alert('Jadwal tidak dapat dihapus. Periksa pengaturan penyimpanan browser Anda.');
                }
            });

            window.addEventListener('storage', event => {
                if (!event.key || event.key === bookmarksKey) renderBookmarks();
            });
            window.addEventListener('usuclassmap:bookmarks-updated', renderBookmarks);
            renderBookmarks();
        });
    </script>
    @endpush

    @stack('scripts')
</body>
</html>
