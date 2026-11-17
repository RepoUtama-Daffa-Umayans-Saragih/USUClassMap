<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Fotokopi' }} - USUStarterMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        [x-cloak] { display: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; width: 0; height: 0; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    @stack('styles')
</head>
<body class="bg-[#F3F4F6] text-gray-900 font-sans antialiased min-h-screen flex flex-col" x-data="{ sidebarOpen: false }">
    
    <!-- Sidebar Admin Fotokopi -->
    <aside :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}" class="fixed inset-y-0 left-0 w-64 bg-[#10B981] text-white transition-transform duration-300 ease-in-out z-50 md:translate-x-0 flex flex-col shadow-xl">
        <!-- Logo Area -->
        <div class="p-5 border-b border-white/15 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-application-logo class="w-10 h-10 object-contain rounded-xl bg-white p-0.5 shadow-xs" />
                <div>
                    <h1 class="text-base font-black tracking-tight leading-tight">Admin Fotokopi</h1>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-100 uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                        USUStarterMap
                    </span>
                </div>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden text-white/80 hover:text-white p-1 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Menu Navigation -->
        <nav class="flex-1 px-3 py-5 space-y-1.5 overflow-y-auto no-scrollbar">
            @if(auth()->check() && auth()->user()->isSuperAdmin())
            <div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-emerald-100/70 flex items-center justify-between">
                <span>Akses Multi-Panel</span>
                <span class="text-[9px] px-1.5 py-0.2 rounded bg-white/20 font-bold uppercase tracking-wider text-white">Super</span>
            </div>

            <div class="space-y-1 mb-4 pb-3 border-b border-white/15">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-between px-3.5 py-2 rounded-xl transition-all text-xs {{ request()->routeIs('admin.*') ? 'bg-white text-emerald-800 font-bold shadow-xs' : 'text-white/80 hover:bg-white/10 hover:text-white font-medium' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 {{ request()->routeIs('admin.*') ? 'text-emerald-600' : 'text-emerald-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                        <span class="truncate">Panel Akademik USU</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-white/50 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>

                <a href="{{ route('admin-fotokopi.dashboard') }}" class="flex items-center justify-between px-3.5 py-2 rounded-xl transition-all text-xs {{ request()->routeIs('admin-fotokopi.*') ? 'bg-white text-emerald-800 font-bold shadow-xs' : 'text-white/80 hover:bg-white/10 hover:text-white font-medium' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 {{ request()->routeIs('admin-fotokopi.*') ? 'text-emerald-600' : 'text-emerald-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        <span class="truncate">Panel Admin Fotokopi</span>
                    </div>
                    @if(request()->routeIs('admin-fotokopi.*'))
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    @endif
                </a>

                <a href="{{ route('cashier-fotokopi.orders.index') }}" class="flex items-center justify-between px-3.5 py-2 rounded-xl transition-all text-xs {{ request()->routeIs('cashier-fotokopi.*') ? 'bg-white text-emerald-800 font-bold shadow-xs' : 'text-white/80 hover:bg-white/10 hover:text-white font-medium' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 {{ request()->routeIs('cashier-fotokopi.*') ? 'text-emerald-600' : 'text-emerald-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        <span class="truncate">Panel Kasir & POS</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-white/50 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            @endif

            <div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-emerald-100/70">
                Menu Utama
            </div>

            <a href="{{ route('admin-fotokopi.dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm {{ request()->routeIs('admin-fotokopi.dashboard') ? 'bg-white text-emerald-800 font-bold shadow-sm' : 'text-white/90 hover:bg-white/15 hover:text-white font-medium' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('admin-fotokopi.dashboard') ? 'text-emerald-600' : 'text-emerald-100' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>

            <div class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wider text-emerald-100/70">
                Manajemen Mitra
            </div>

            <a href="{{ route('admin-fotokopi.stores.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm {{ request()->routeIs('admin-fotokopi.stores*') ? 'bg-white text-emerald-800 font-bold shadow-sm' : 'text-white/90 hover:bg-white/15 hover:text-white font-medium' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('admin-fotokopi.stores*') ? 'text-emerald-600' : 'text-emerald-100' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Mitra Toko Fotocopy
            </a>

            <a href="{{ route('admin-fotokopi.services.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm {{ request()->routeIs('admin-fotokopi.services*') ? 'bg-white text-emerald-800 font-bold shadow-sm' : 'text-white/90 hover:bg-white/15 hover:text-white font-medium' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('admin-fotokopi.services*') ? 'text-emerald-600' : 'text-emerald-100' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Katalog & Daftar Harga
            </a>

            <a href="{{ route('admin-fotokopi.reports.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm {{ request()->routeIs('admin-fotokopi.reports*') ? 'bg-white text-emerald-800 font-bold shadow-sm' : 'text-white/90 hover:bg-white/15 hover:text-white font-medium' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('admin-fotokopi.reports*') ? 'text-emerald-600' : 'text-emerald-100' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Laporan & Transaksi
            </a>

            <div class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wider text-emerald-100/70">
                Pintasan
            </div>

            <a href="{{ route('fotocopy.index') }}" target="_blank" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm text-white/90 hover:bg-white/15 hover:text-white font-medium">
                <svg class="w-5 h-5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                Lihat Layanan Publik
            </a>
        </nav>

        <!-- User Profile & Logout -->
        <div class="p-4 border-t border-white/15 bg-black/5">
            <div class="flex items-center gap-3 mb-3 px-1">
                <div class="w-9 h-9 rounded-full bg-white text-emerald-700 flex items-center justify-center shadow-xs">
                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-white truncate">{{ (auth()->check() && auth()->user()->isSuperAdmin()) ? 'Super Admin USU' : (auth()->user()->name ?? 'Admin Fotokopi USU') }}</p>
                    <p class="text-[11px] text-emerald-100 truncate font-mono">{{ (auth()->check() && auth()->user()->isSuperAdmin()) ? 'superadmin@usu.ac.id' : (auth()->user()->email ?? 'adminfotokopi@usu.ac.id') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Keluar Sesi
                </button>
            </form>
        </div>
    </aside>

    <!-- Overlay for mobile -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/50 z-40 md:hidden" style="display: none;"></div>

    <!-- Main Content wrapper -->
    <div class="md:ml-64 flex flex-col flex-1 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-2xs border-b border-gray-100 h-16 flex items-center justify-between px-4 sm:px-6 z-10 sticky top-0">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="md:hidden text-gray-500 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#10B981]"></span>
                    <h2 class="text-base sm:text-lg font-bold text-gray-800">Panel Admin Fotokopi USU</h2>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                @if(auth()->check() && auth()->user()->isSuperAdmin())
                <span class="text-xs font-semibold px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full inline-flex items-center gap-1.5 shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Super Admin
                </span>
                @else
                <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full inline-flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Admin Fotokopi
                </span>
                @endif
                <div class="w-8 h-8 rounded-full bg-[#10B981] text-white flex items-center justify-center shadow-xs">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 relative">
            
            <!-- Flash Messages -->
            @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl shadow-xs flex items-center justify-between" role="alert">
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
                <button @click="show = false" class="text-emerald-500 hover:text-emerald-700 p-1 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            @endif

            @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3.5 rounded-xl shadow-xs flex items-center justify-between" role="alert">
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
                <button @click="show = false" class="text-red-500 hover:text-red-700 p-1 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            @endif

            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="px-6 py-4 border-t border-gray-200/60 bg-white text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} USUStarterMap — Modul Admin Fotokopi & Percetakan Kampus USU
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
