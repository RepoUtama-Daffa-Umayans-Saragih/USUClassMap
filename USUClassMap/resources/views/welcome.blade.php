<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USUClassMap - Peta Ruang Kelas Kampus USU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body 
    class="bg-[#F3F4F6] text-gray-900 font-sans antialiased"
    x-data="{ 
        mobileMenu: false, 
        bookmarkCount: 0,
        init() {
            const updateCount = () => {
                try {
                    const b = JSON.parse(localStorage.getItem('usuclassmap_bookmarks') || '[]');
                    this.bookmarkCount = b.length;
                } catch(e) {
                    this.bookmarkCount = 0;
                }
            };
            updateCount();
            window.addEventListener('storage', updateCount);
            window.addEventListener('usuclassmap:bookmarks-updated', (e) => {
                if (e.detail && typeof e.detail.count === 'number') {
                    this.bookmarkCount = e.detail.count;
                } else {
                    updateCount();
                }
            });
        }
    }"
>
    <!-- Navbar -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-xs h-16 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex justify-between items-center">
            <!-- Left: Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-[#10A760] text-white flex items-center justify-center shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <span class="font-extrabold text-xl text-[#10A760] tracking-tight">USUClassMap</span>
                </a>
            </div>

            <!-- Center: Nav links (Desktop) -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/map" class="text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors {{ request()->is('map*') ? 'text-[#10A760]' : '' }}">
                    Peta
                </a>
                <a href="/bookmarks" class="relative text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors flex items-center gap-1.5 {{ request()->is('bookmarks*') ? 'text-[#10A760]' : '' }}">
                    <span>Bookmark</span>
                    <span 
                        x-show="bookmarkCount > 0" 
                        x-text="bookmarkCount" 
                        class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-extrabold text-white bg-[#10A760] rounded-full min-w-4.5 h-4.5 shadow-xs"
                        style="display: none;"
                    ></span>
                </a>
                <a href="/faq" class="text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors {{ request()->is('faq*') ? 'text-[#10A760]' : '' }}">
                    FAQ
                </a>
            </div>

            <!-- Right: Dynamic Profile & Auth (Desktop) -->
            @guest
                <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200">
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-[#10A760] transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-4 py-2 text-xs font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] rounded-xl shadow-xs transition-all">
                        Daftar
                    </a>
                </div>
            @endguest

            @auth
                <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200 relative" x-data="{ userMenu: false }">
                    <button @click="userMenu = !userMenu" type="button" class="flex items-center gap-3 text-left focus:outline-hidden group cursor-pointer">
                        <div class="text-right">
                            <div class="text-xs font-bold text-gray-900 group-hover:text-[#10A760] transition-colors">{{ Auth::user()->name }}</div>
                            <div class="text-[11px] text-gray-500 font-medium">
                                {{ Auth::user()->nim ?? 'Mahasiswa' }} • Semester {{ Auth::user()->semester ?? '1' }}
                            </div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-[#10A760] text-white flex items-center justify-center font-bold text-sm shadow-xs ring-2 ring-green-100 group-hover:ring-[#10A760] transition-all">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div 
                        x-show="userMenu" 
                        @click.away="userMenu = false" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 top-12 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-50"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-xs text-gray-500">Masuk sebagai</p>
                            <p class="text-xs font-bold text-gray-800 truncate">{{ Auth::user()->email }}</p>
                        </div>

                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-[#10A760]">
                                Admin Dashboard
                            </a>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-[#10A760]">
                            Pengaturan Profil
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                                Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </div>
            @endauth

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center gap-2">
                <a href="/bookmarks" class="relative p-2 text-gray-600 hover:text-[#10A760]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                    </svg>
                    <span 
                        x-show="bookmarkCount > 0" 
                        x-text="bookmarkCount" 
                        class="absolute top-1 right-1 inline-flex items-center justify-center px-1 text-[9px] font-bold text-white bg-[#10A760] rounded-full min-w-4 h-4"
                        style="display: none;"
                    ></span>
                </a>
                <button @click="mobileMenu = !mobileMenu" type="button" class="p-2 text-gray-500 hover:text-gray-700 focus:outline-hidden">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileMenu, 'inline-flex': !mobileMenu }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !mobileMenu, 'inline-flex': mobileMenu }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenu" @click.away="mobileMenu = false" class="md:hidden bg-white border-t border-gray-100 shadow-xl absolute w-full" style="display: none;">
            <div class="px-3 pt-2 pb-3 space-y-1">
                <a href="/map" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('map*') ? 'bg-green-50 text-[#10A760]' : '' }}">Peta</a>
                <a href="/bookmarks" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('bookmarks*') ? 'bg-green-50 text-[#10A760]' : '' }}">
                    <span>Bookmark</span>
                    <span x-show="bookmarkCount > 0" x-text="bookmarkCount" class="px-2 py-0.5 text-xs font-bold text-white bg-[#10A760] rounded-full"></span>
                </a>
                <a href="/faq" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('faq*') ? 'bg-green-50 text-[#10A760]' : '' }}">FAQ</a>
            </div>

            @guest
                <div class="pt-3 pb-4 border-t border-gray-100 px-4 bg-gray-50 space-y-2">
                    <a href="{{ route('login') }}" class="block w-full text-center py-2 text-sm font-semibold text-gray-700 hover:text-[#10A760] bg-white border border-gray-200 rounded-xl">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="block w-full text-center py-2 text-sm font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] rounded-xl shadow-xs">
                        Daftar Akun
                    </a>
                </div>
            @endguest

            @auth
                <div class="pt-3 pb-4 border-t border-gray-100 px-4 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#10A760] text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-gray-900">{{ Auth::user()->name }}</div>
                                <div class="text-[11px] text-gray-500 font-medium">{{ Auth::user()->nim ?? 'Mahasiswa' }} • Semester {{ Auth::user()->semester ?? '1' }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-xs font-bold text-red-600 hover:underline">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="pt-16 min-h-[calc(100vh-4rem)]">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <!-- Hero Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 sm:p-12 text-center">
                <div class="inline-flex justify-center items-center w-20 h-20 rounded-3xl bg-green-50 text-[#10A760] mb-6 shadow-xs">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight mb-4">
                    <span class="text-[#10A760]">USU</span>ClassMap
                </h1>
                <p class="text-xl sm:text-2xl text-gray-700 font-bold mb-4">Temukan Kelas MKWK/MKWU Anda di Kampus USU</p>
                <p class="text-gray-500 max-w-2xl mx-auto mb-8 text-base sm:text-lg leading-relaxed">
                    Aplikasi interaktif untuk memudahkan mahasiswa Universitas Sumatera Utara dalam mencari dan menemukan lokasi kelas mata kuliah wajib kurikulum dan universitas beserta jadwal dan informasi dosen secara presisi.
                </p>
                
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="/map" class="w-full sm:w-auto px-8 py-3.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">
                        Buka Peta Interaktif
                    </a>
                    <a href="/faq" class="w-full sm:w-auto px-8 py-3.5 bg-white text-[#10A760] border-2 border-[#10A760] hover:bg-green-50 font-bold rounded-xl shadow-xs transition-all">
                        Lihat FAQ
                    </a>
                </div>
            </div>

            <!-- Feature Cards Grid -->
            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center border-t-4 border-[#10A760] hover:shadow-md transition-shadow">
                    <div class="inline-flex justify-center items-center w-14 h-14 rounded-2xl bg-green-50 text-[#10A760] mb-5">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Peta Interaktif</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Jelajahi kampus USU dan temukan lokasi fakultas serta ruangan kelas dengan presisi melalui peta interaktif OpenStreetMap.
                    </p>
                </div>
                
                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center border-t-4 border-[#D4A017] hover:shadow-md transition-shadow">
                    <div class="inline-flex justify-center items-center w-14 h-14 rounded-2xl bg-amber-50 text-[#D4A017] mb-5">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Tur 360°</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Lihat pratinjau pemandangan sekitar bangunan secara imersif dengan fitur panorama 360 derajat untuk memudahkan navigasi.
                    </p>
                </div>
                
                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center border-t-4 border-[#10A760] hover:shadow-md transition-shadow">
                    <div class="inline-flex justify-center items-center w-14 h-14 rounded-2xl bg-green-50 text-[#10A760] mb-5">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Smart Search</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Cari jadwal, dosen, atau mata kuliah dengan cepat dan dapatkan hasil pencarian semantik SPARQL yang relevan seketika.
                    </p>
                </div>
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
                        <div id="modal-panorama-viewer-welcome" class="w-full h-full rounded-xl overflow-hidden bg-gray-900"></div>
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

    @stack('scripts')
</body>
</html>
