<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail {{ $store->name }} - USUClassMap</title>
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
                    <div class="w-9 h-9 rounded-xl bg-[#10B981] text-white flex items-center justify-center shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <span class="font-extrabold text-xl text-[#10B981] tracking-tight">USUClassMap</span>
                </a>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="/map" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm transition-colors">Peta</a>
                <a href="/bookmarks" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm transition-colors">Bookmark</a>
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
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-20 pb-16 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-xs text-gray-500 font-medium">
            <a href="/" class="hover:text-emerald-600">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-emerald-600">Fotocopy</a>
            <span>/</span>
            <span class="text-gray-800 font-bold truncate">{{ $store->name }}</span>
        </nav>

        <!-- Flash Success -->
        @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-sm font-medium flex items-center gap-3 shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        <!-- Big Hero Card -->
        <div class="bg-gradient-to-br from-[#10B981] to-emerald-800 rounded-3xl p-6 sm:p-10 text-white relative shadow-lg overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-3xl shadow-inner">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </div>
                @if($store->is_open)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white text-emerald-800 shadow-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sedang Buka
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/20 text-white">
                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                        Sedang Tutup
                    </span>
                @endif
            </div>

            <div class="text-center max-w-2xl mx-auto space-y-2">
                <span class="inline-block text-[11px] font-extrabold uppercase tracking-wider bg-white/15 px-3 py-0.5 rounded-full text-emerald-100">
                    Mitra Percetakan & Fotocopy Resmi USU
                </span>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                    {{ $store->name }}
                </h1>
                <p class="text-sm text-emerald-100 flex items-center justify-center gap-1.5 font-medium">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                    <span>{{ $store->location_name }}</span>
                </p>
            </div>

            <!-- Action Sub-banner -->
            <div class="mt-8 bg-white/10 backdrop-blur-md rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-4 border border-white/15">
                <div>
                    <h3 class="text-sm font-bold text-white">Siap mencetak tugas atau fotocopy berkas?</h3>
                    <p class="text-xs text-emerald-100 mt-0.5">Pesan langsung secara online atau datangi gerai dengan panduan rute.</p>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('fotocopy.order', $store->id) }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Pesan Sekarang
                    </a>
                    @if($store->latitude && $store->longitude)
                    <a href="{{ route('map', ['route_to' => 'fotocopy:' . $store->id]) }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                        Rute di Peta Kampus
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- 2 Column Info Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Jadwal Buka & Tutup -->
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-xs">
                <h3 class="text-sm font-black text-gray-900 flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                    <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Jadwal Buka & Tutup
                </h3>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between py-1 border-b border-gray-50">
                        <dt class="text-gray-500">Jam Operasional:</dt>
                        <dd class="font-bold text-gray-800">{{ $store->opening_hours }} WIB</dd>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-50">
                        <dt class="text-gray-500">Hari Beroperasi:</dt>
                        <dd class="font-bold text-gray-800">{{ $store->operating_days }}</dd>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-50">
                        <dt class="text-gray-500">Status Saat Ini:</dt>
                        <dd class="font-bold text-emerald-600">{{ $store->is_open ? 'Buka (Menerima Pesanan)' : 'Tutup Sementara' }}</dd>
                    </div>
                    <div class="flex justify-between py-1">
                        <dt class="text-gray-500">Rating Pengguna:</dt>
                        <dd class="font-bold text-amber-500">★ {{ number_format($store->rating, 1) }} / 5.0</dd>
                    </div>
                </dl>
            </div>

            <!-- Kontak Person & Media Sosial -->
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-xs">
                <h3 class="text-sm font-black text-gray-900 flex items-center gap-2 mb-2 pb-2">
                    <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    Kontak Person & Media Sosial
                </h3>
                <p class="text-[11px] text-gray-400 mb-4">Klik tombol di bawah ini untuk langsung terhubung dengan pemilik toko:</p>

                <div class="space-y-2.5">
                    @if($store->whatsapp)
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">WA</span>
                            <div>
                                <span class="font-bold text-gray-800 block">Nomor WhatsApp</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ $store->whatsapp }}</span>
                            </div>
                        </div>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp) }}" target="_blank" class="px-3 py-1.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-bold transition-colors">
                            Kirim WA &rarr;
                        </a>
                    </div>
                    @endif

                    @if($store->instagram)
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="w-8 h-8 rounded-lg bg-pink-100 text-pink-700 flex items-center justify-center font-bold">IG</span>
                            <div>
                                <span class="font-bold text-gray-800 block">Instagram</span>
                                <span class="text-[11px] text-gray-400">{{ $store->instagram }}</span>
                            </div>
                        </div>
                        <a href="https://instagram.com/{{ ltrim($store->instagram, '@') }}" target="_blank" class="px-3 py-1.5 bg-pink-600 hover:bg-pink-700 text-white rounded-lg text-xs font-bold transition-colors">
                            Buka Profil &rarr;
                        </a>
                    </div>
                    @endif

                    @if($store->facebook)
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold">FB</span>
                            <div>
                                <span class="font-bold text-gray-800 block">Facebook Page</span>
                                <span class="text-[11px] text-gray-400">{{ $store->facebook }}</span>
                            </div>
                        </div>
                        <a href="https://facebook.com/{{ $store->facebook }}" target="_blank" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-colors">
                            Kunjungi FB &rarr;
                        </a>
                    </div>
                    @endif
                </div>
            </div>

        </div>

        <!-- Tentang Tempat Fotocopy Ini -->
        @if($store->description)
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-xs">
            <h3 class="text-sm font-black text-gray-900 flex items-center gap-2 mb-2">
                <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Tentang Tempat Fotocopy Ini
            </h3>
            <p class="text-xs text-gray-600 leading-relaxed">
                {{ $store->description }}
            </p>
        </div>
        @endif

        <!-- Katalog Layanan & Daftar Harga -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-xs space-y-6">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-black text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#10B981] rounded-full inline-block"></span>
                    Katalog Layanan & Daftar Harga
                </h3>
                <a href="{{ route('fotocopy.order', $store->id) }}" class="text-xs font-bold text-[#10B981] hover:underline flex items-center gap-1">
                    Pesan Layanan &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach(['Print', 'Fotocopy', 'Jilid', 'ATK'] as $cat)
                    @php $items = $servicesByCategory->get($cat) ?? collect(); @endphp
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-emerald-800 mb-3">
                            <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                            <span>KATEGORI: {{ strtoupper($cat) }}</span>
                        </div>
                        
                        <div class="space-y-2.5">
                            @forelse($items as $item)
                                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between hover:bg-emerald-50/40 transition-colors">
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900">{{ $item->name }}</h4>
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $item->description ?? '-' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-extrabold text-[#10B981]">
                                            Rp {{ number_format($item->price, 0, ',', '.') }}
                                        </span>
                                        <span class="block text-[10px] text-gray-400">/ {{ $item->unit }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">Belum ada item untuk kategori ini.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Back Link -->
        <div class="text-center pt-4">
            <a href="{{ route('fotocopy.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-gray-600 hover:text-[#10B981] transition-colors">
                &larr; Kembali ke Daftar Fotocopy
            </a>
        </div>

    </main>

    <footer class="bg-white border-t border-gray-200/80 py-6 text-center text-xs text-gray-500">
        &copy; {{ date('Y') }} USUClassMap — Sistem Peta Interaktif & Layanan Fotocopy Universitas Sumatera Utara
    </footer>

</body>
</html>
