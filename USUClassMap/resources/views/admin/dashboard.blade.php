<x-layouts.admin>
    {{-- Welcome Banner - TOP position --}}
    <div class="bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-xl shadow-lg mb-8 text-white p-6 relative overflow-hidden">
        <div class="absolute right-0 top-0 w-64 h-full opacity-10">
            <svg viewBox="0 0 200 200" class="w-full h-full" fill="currentColor">
                <circle cx="150" cy="50" r="80"/>
                <circle cx="180" cy="120" r="60"/>
            </svg>
        </div>
        <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold">Selamat Datang di Admin Panel USUClassMap</h2>
                </div>
                <p class="text-white/90 text-sm ml-13">Kelola data jadwal perkuliahan MKWK/MKWU, dosen, gedung, dan ruangan untuk ditampilkan pada aplikasi peta interaktif.</p>
            </div>
            <a href="{{ route('fotocopy.index') }}" target="_blank" class="bg-white text-emerald-700 hover:bg-emerald-50 px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs shrink-0 flex items-center gap-2">
                <span>Lihat Layanan Fotocopy</span>
                <span>&rarr;</span>
            </a>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->isSuperAdmin())
    <!-- Super Admin Universal Control Hub -->
    <div class="mb-8 bg-white rounded-2xl p-6 border border-emerald-100 shadow-xs">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-gray-900">Akses Penuh Super Admin (Universal Hub)</h3>
                    <p class="text-xs text-gray-500">Anda memiliki wewenang mengelola seluruh modul dan panel operasional USUClassMap.</p>
                </div>
            </div>
            <span class="text-xs font-bold px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full inline-flex items-center gap-1.5 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Mode Universal Aktif
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Modul Akademik -->
            <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-extrabold text-emerald-900">1. Modul Akademik</span>
                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">Panel Ini</span>
                    </div>
                    <p class="text-xs text-emerald-800/80 leading-relaxed">Jadwal perkuliahan MKWU, dosen pengajar, gedung kampus, ruangan kelas & foto 360.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-emerald-200/60 flex items-center justify-between text-xs font-bold text-emerald-700">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Sedang Aktif
                    </span>
                    <span class="text-[11px] font-normal text-emerald-600">Admin Utama</span>
                </div>
            </div>

            <!-- 2. Modul Admin Fotokopi -->
            <a href="{{ route('admin-fotokopi.dashboard') }}" class="group p-4 rounded-xl bg-gray-50 hover:bg-emerald-50/50 border border-gray-200 hover:border-emerald-300 transition-all flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-900 group-hover:text-emerald-700">2. Modul Admin Fotokopi</span>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Kelola jaringan gerai mitra percetakan kampus, pembaruan katalog tarif harga, dan laporan pesanan.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs font-bold text-emerald-600">
                    <span>Masuk ke Panel &rarr;</span>
                    <span class="text-[11px] font-normal text-gray-400">Mitra & Katalog</span>
                </div>
            </a>

            <!-- 3. Modul Kasir / POS -->
            <a href="{{ route('cashier-fotokopi.orders.index') }}" class="group p-4 rounded-xl bg-gray-50 hover:bg-emerald-50/50 border border-gray-200 hover:border-emerald-300 transition-all flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-900 group-hover:text-emerald-700">3. Modul Kasir & POS</span>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Pantau antrean pesanan masuk mahasiswa, catat transaksi walk-in kasir, dan update status proses.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs font-bold text-emerald-600">
                    <span>Masuk ke Kasir &rarr;</span>
                    <span class="text-[11px] font-normal text-gray-400">Operasional POS</span>
                </div>
            </a>
        </div>
    </div>
    @endif

    {{-- Clickable Metric Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Jadwal --}}
        <a href="{{ route('admin.schedules.index') }}" class="group bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 p-6 flex items-center transition-all duration-200 hover:-translate-y-0.5">
            <div class="p-3 rounded-xl bg-blue-50 text-blue-600 mr-4 group-hover:bg-blue-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Jadwal</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['schedules'] ?? 0 }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 ml-auto group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>

        {{-- Total Dosen --}}
        <a href="{{ route('admin.lecturers.index') }}" class="group bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 p-6 flex items-center transition-all duration-200 hover:-translate-y-0.5">
            <div class="p-3 rounded-xl bg-green-50 text-green-600 mr-4 group-hover:bg-green-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Dosen</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['lecturers'] ?? 0 }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 ml-auto group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>

        {{-- Total Gedung --}}
        <a href="{{ route('admin.buildings.index') }}" class="group bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 p-6 flex items-center transition-all duration-200 hover:-translate-y-0.5">
            <div class="p-3 rounded-xl bg-amber-50 text-amber-600 mr-4 group-hover:bg-amber-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Gedung</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['buildings'] ?? 0 }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 ml-auto group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>

        {{-- Total Ruangan --}}
        <a href="{{ route('admin.rooms.index') }}" class="group bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 p-6 flex items-center transition-all duration-200 hover:-translate-y-0.5">
            <div class="p-3 rounded-xl bg-purple-50 text-purple-600 mr-4 group-hover:bg-purple-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Ruangan</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['rooms'] ?? 0 }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 ml-auto group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>

    {{-- Bottom Grid: Recent Schedules + Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Jadwal Terbaru --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Jadwal Terbaru</h3>
                <a href="{{ route('admin.schedules.index') }}" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">Lihat Semua →</a>
            </div>
            <div class="p-5">
                @if(isset($recentSchedules) && $recentSchedules->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentSchedules as $schedule)
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $schedule->course->name ?? 'N/A' }}</p>
                                <p class="text-xs text-gray-500">{{ $schedule->lecturer->name ?? '-' }} · {{ $schedule->room->building->name ?? '' }} {{ $schedule->room->name ?? '' }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <p class="text-gray-500 text-sm mb-4">Belum ada jadwal yang ditambahkan.</p>
                        <a href="{{ route('admin.schedules.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Jadwal Pertama
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Aktivitas Terkini --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Aktivitas Terkini</h3>
            </div>
            <div class="p-5">
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <p class="text-gray-500 text-sm mb-2">Belum ada aktivitas yang tercatat.</p>
                    <p class="text-gray-400 text-xs">Aktivitas akan muncul saat Anda mulai mengelola data gedung, ruangan, dan jadwal.</p>
                    <div class="flex flex-wrap justify-center gap-2 mt-4">
                        <a href="{{ route('admin.buildings.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Gedung
                        </a>
                        <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Ruangan
                        </a>
                        <a href="{{ route('admin.import.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            Import Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->isSuperAdmin())
    <!-- Super Admin Universal Control Hub -->
    <div class="mb-8 bg-white rounded-2xl p-6 border border-emerald-100 shadow-xs">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-gray-900">Akses Penuh Super Admin (Universal Hub)</h3>
                    <p class="text-xs text-gray-500">Anda memiliki wewenang mengelola seluruh modul dan panel operasional USUClassMap.</p>
                </div>
            </div>
            <span class="text-xs font-bold px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full inline-flex items-center gap-1.5 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Mode Universal Aktif
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Modul Akademik -->
            <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-extrabold text-emerald-900">1. Modul Akademik</span>
                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">Panel Ini</span>
                    </div>
                    <p class="text-xs text-emerald-800/80 leading-relaxed">Jadwal perkuliahan MKWU, dosen pengajar, gedung kampus, ruangan kelas & foto 360.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-emerald-200/60 flex items-center justify-between text-xs font-bold text-emerald-700">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Sedang Aktif
                    </span>
                    <span class="text-[11px] font-normal text-emerald-600">Admin Utama</span>
                </div>
            </div>

            <!-- 2. Modul Admin Fotokopi -->
            <a href="{{ route('admin-fotokopi.dashboard') }}" class="group p-4 rounded-xl bg-gray-50 hover:bg-emerald-50/50 border border-gray-200 hover:border-emerald-300 transition-all flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-900 group-hover:text-emerald-700">2. Modul Admin Fotokopi</span>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Kelola jaringan gerai mitra percetakan kampus, pembaruan katalog tarif harga, dan laporan pesanan.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs font-bold text-emerald-600">
                    <span>Masuk ke Panel &rarr;</span>
                    <span class="text-[11px] font-normal text-gray-400">Mitra & Katalog</span>
                </div>
            </a>

            <!-- 3. Modul Kasir / POS -->
            <a href="{{ route('cashier-fotokopi.orders.index') }}" class="group p-4 rounded-xl bg-gray-50 hover:bg-emerald-50/50 border border-gray-200 hover:border-emerald-300 transition-all flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-900 group-hover:text-emerald-700">3. Modul Kasir & POS</span>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Pantau antrean pesanan masuk mahasiswa, catat transaksi walk-in kasir, dan update status proses.</p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs font-bold text-emerald-600">
                    <span>Masuk ke Kasir &rarr;</span>
                    <span class="text-[11px] font-normal text-gray-400">Operasional POS</span>
                </div>
            </a>
        </div>
    </div>
    @endif
</x-layouts.admin>
