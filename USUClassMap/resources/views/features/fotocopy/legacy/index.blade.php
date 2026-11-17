<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Fotocopy & Percetakan Kampus USU - USUStarterMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
</head>
<body class="bg-[#F3F4F6] text-gray-900 font-sans antialiased" x-data="{ mobileMenu: false }">

    <!-- Public Navigation Bar -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-xs h-16 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex justify-between items-center">
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-2.5 group">
                    <x-application-logo class="w-9 h-9 object-contain transition-transform group-hover:scale-105" />
                    <span class="font-extrabold text-xl text-[#10B981] tracking-tight">USUStarterMap</span>
                </a>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="/map" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm transition-colors">Peta</a>
                <a href="/faq" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm transition-colors">FAQ</a>
                <a href="/fotocopy" class="text-[#10B981] font-bold text-sm transition-colors flex items-center gap-1.5">
                    <span>Fotocopy</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-emerald-100 text-emerald-800 rounded-md uppercase">Mitra</span>
                </a>
            </div>

            <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200">
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-[#10B981] transition-colors">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 text-xs font-bold text-white bg-[#10B981] hover:bg-emerald-600 rounded-xl shadow-xs transition-all">Daftar</a>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">{{ auth()->user()->name }}</a>
                @endguest
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button @click="mobileMenu = !mobileMenu" class="p-2 text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenu" @click.away="mobileMenu = false" class="md:hidden bg-white border-t border-gray-100 shadow-xl" style="display: none;">
            <div class="px-3 pt-2 pb-3 space-y-1">
                <a href="/map" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700">Peta</a>
                <a href="/faq" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700">FAQ</a>
                <a href="/fotocopy" class="block px-3 py-2 rounded-lg text-sm font-bold text-[#10B981] bg-emerald-50">Fotocopy & Percetakan</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16 min-h-screen">
        
        <!-- Hero Header with Emerald Gradient & Pattern -->
        <div class="bg-gradient-to-b from-[#10B981] to-emerald-700 text-white py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
            <div class="max-w-4xl mx-auto text-center relative z-10">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/20 backdrop-blur-xs rounded-full text-xs font-semibold text-emerald-100 mb-4">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    Integrasi Layanan Mahasiswa USU
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight mb-3">
                    Layanan Fotocopy & Percetakan <span class="text-amber-300">Kampus USU</span>
                </h1>
                <p class="text-sm sm:text-base text-emerald-50 max-w-2xl mx-auto mb-8 font-medium">
                    Temukan tempat fotocopy terdekat dari ruang kelasmu, bandingkan harga cetak, pesan langsung via online, atau hubungi pemilik toko dengan pesan otomatis.
                </p>

                <!-- Search Bar -->
                <form method="GET" action="{{ route('fotocopy.index') }}" class="max-w-2xl mx-auto">
                    <div class="relative flex items-center bg-white rounded-2xl p-1.5 shadow-xl">
                        <div class="pl-3.5 pr-2 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari fotocopy, fakultas terdekat, jilid skripsi..." class="w-full bg-transparent border-none text-gray-800 text-sm focus:ring-0 placeholder-gray-400 px-2 py-2">
                        <button type="submit" class="bg-[#10B981] hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-colors cursor-pointer flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Cari
                        </button>
                    </div>
                </form>

                <!-- Filter Quick Pills -->
                <div class="flex flex-wrap justify-center items-center gap-2 mt-5">
                    @php
                        $tags = [
                            ['label' => 'Buka Sekarang', 'param' => 'buka sekarang', 'icon' => '🟢'],
                            ['label' => 'Dekat FT', 'param' => 'dekat FT', 'icon' => '📍'],
                            ['label' => 'Dekat FISIP', 'param' => 'dekat FISIP', 'icon' => '📍'],
                            ['label' => 'Dekat FK', 'param' => 'dekat FK', 'icon' => '📍'],
                            ['label' => 'Dekat Perpus', 'param' => 'dekat Perpus', 'icon' => '📍'],
                            ['label' => 'Jilid Skripsi', 'param' => 'jilid skripsi', 'icon' => '📚'],
                        ];
                    @endphp
                    @foreach($tags as $t)
                        <a href="{{ route('fotocopy.index', ['tag' => $t['param']]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold {{ request('tag') === $t['param'] ? 'bg-amber-400 text-gray-900 font-bold shadow-sm' : 'bg-white/15 hover:bg-white/25 text-white' }} transition-all">
                            <span>{{ $t['icon'] }}</span>
                            <span>{{ $t['label'] }}</span>
                        </a>
                    @endforeach
                    @if(request('q') || request('tag') || request('filter'))
                        <a href="{{ route('fotocopy.index') }}" class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-red-500/80 hover:bg-red-600 text-white transition-colors">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">
            
            <!-- Summary Bar -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-2xl shadow-xs border border-gray-100">
                <div class="flex items-center gap-4 text-sm font-semibold text-gray-700">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 font-bold">{{ $openStores->count() }} tempat</span> buka sekarang
                    </span>
                    <span class="text-gray-300">|</span>
                    <span>Total: {{ $allStores->count() }} Mitra Fotocopy USU</span>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('fotocopy.index') }}" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors {{ !request('filter') ? 'bg-[#10B981] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Semua
                    </a>
                    <a href="{{ route('fotocopy.index', ['filter' => 'buka']) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors {{ request('filter') === 'buka' ? 'bg-[#10B981] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Buka Saja
                    </a>
                </div>
            </div>

            <!-- SECTION 1: Tempat Fotocopy Buka Sekarang -->
            @if($openStores->count() > 0)
            <div>
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-[#10B981] rounded-full inline-block"></span>
                            Tempat Fotocopy Buka Sekarang
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Daftar mitra yang sedang aktif melayani pesanan saat ini</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    @foreach($openStores->take(4) as $store)
                        @include('features.fotocopy.legacy.partials.store-card', ['store' => $store])
                    @endforeach
                </div>
            </div>
            @endif

            <!-- SECTION 2: Semua Tempat Fotocopy Kampus USU -->
            <div>
                <div class="mb-4">
                    <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-6 bg-emerald-700 rounded-full inline-block"></span>
                        Semua Tempat Fotocopy Kampus USU
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Daftar lengkap mitra jasa fotocopy & percetakan di lingkungan USU</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @forelse($allStores as $store)
                        @include('features.fotocopy.legacy.partials.store-card', ['store' => $store])
                    @empty
                        <div class="col-span-full bg-white p-12 text-center rounded-2xl border border-gray-100 shadow-xs">
                            <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <h3 class="text-base font-bold text-gray-800">Tidak ada tempat fotocopy ditemukan</h3>
                            <p class="text-xs text-gray-500 mt-1 mb-4">Coba cari dengan kata kunci fakultas lain atau hapus filter.</p>
                            <a href="{{ route('fotocopy.index') }}" class="px-4 py-2 bg-[#10B981] text-white text-xs font-bold rounded-xl shadow-xs">Lihat Semua Mitra</a>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-16 bg-white border-t border-gray-200/80 py-8 text-center text-xs text-gray-500">
        &copy; {{ date('Y') }} USUStarterMap — Sistem Peta Interaktif & Layanan Fotocopy Universitas Sumatera Utara
    </footer>

</body>
</html>
