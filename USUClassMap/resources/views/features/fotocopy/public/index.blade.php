<x-layouts.app>
    <x-slot name="title">Layanan Fotocopy & Percetakan Kampus</x-slot>

    <!-- Custom Style for horizontal scrolling and card effects -->
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 16px 32px rgba(16,167,96,0.12); }
        .status-pulse { animation: pulse-green 2s infinite; }
        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,167,96,0.4); }
            50% { box-shadow: 0 0 0 6px rgba(16,167,96,0); }
        }
        .scroll-snap { scroll-snap-type: x mandatory; }
        .snap-card { scroll-snap-align: start; }
    </style>

    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-[#10A760] via-[#0D8A4E] to-[#087A3F] text-white">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <svg width="100%" height="100%"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/></pattern></defs><rect width="100%" height="100%" fill="url(#grid)"/></svg>
        </div>
        <div class="absolute -top-10 -right-10 w-72 h-72 bg-[#D4A017] rounded-full opacity-15 blur-3xl pointer-events-none"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 md:py-14">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md px-3.5 py-1 rounded-full mb-3 shadow-xs">
                    <i class="fa-solid fa-print text-[#D4A017] text-xs"></i>
                    <span class="text-white text-xs font-semibold">Integrasi Layanan Mahasiswa USU</span>
                </div>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-white tracking-tight mb-2">
                    Layanan Fotocopy & Percetakan <span class="text-[#D4A017]">Kampus USU</span>
                </h1>
                <p class="text-white/80 text-xs sm:text-sm max-w-2xl mx-auto mb-6 px-2">
                    Temukan tempat fotocopy terdekat dari ruang kelasmu, bandingkan harga cetak, pesan langsung via online, atau hubungi pemilik toko dengan pesan otomatis.
                </p>

                <!-- Search Form -->
                <form action="{{ route('fotocopy.index') }}" method="GET" class="max-w-2xl mx-auto px-1 sm:px-0">
                    <div class="relative flex items-center bg-white rounded-2xl shadow-xl shadow-black/10 overflow-hidden p-1 sm:p-1.5">
                        <div class="pl-3.5 pr-2 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </div>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari fotocopy, fakultas terdekat, jilid skripsi..."
                            class="flex-1 py-2 sm:py-2.5 px-2 text-xs sm:text-sm text-gray-800 placeholder-gray-400 border-0 focus:ring-0 focus:outline-hidden"
                        />
                        @if($search)
                            <a href="{{ route('fotocopy.index') }}" class="px-2 text-xs text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                        <button type="submit" class="bg-[#10A760] hover:bg-[#0D8A4E] text-white px-4 sm:px-6 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shadow-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            <span class="hidden sm:inline">Cari</span>
                        </button>
                    </div>

                    <!-- Quick filters -->
                    <div class="flex flex-wrap justify-center gap-1.5 sm:gap-2 mt-3 text-xs">
                        <a href="{{ route('fotocopy.index', ['status' => 'open']) }}" class="px-3 py-1 rounded-full {{ request('status') === 'open' ? 'bg-[#D4A017] text-white font-bold' : 'bg-white/20 text-white hover:bg-white/30' }} transition-colors flex items-center gap-1.5">
                            <i class="fa-solid fa-circle text-[9px] text-[#10A760]"></i>
                            <span>Buka Sekarang</span>
                        </a>
                        <a href="{{ route('fotocopy.index', ['q' => 'Teknik']) }}" class="bg-white/15 text-white hover:bg-white/25 px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[10px] text-amber-300"></i>
                            <span>Dekat FT</span>
                        </a>
                        <a href="{{ route('fotocopy.index', ['q' => 'FISIP']) }}" class="bg-white/15 text-white hover:bg-white/25 px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[10px] text-amber-300"></i>
                            <span>Dekat FISIP</span>
                        </a>
                        <a href="{{ route('fotocopy.index', ['q' => 'Kedokteran']) }}" class="bg-white/15 text-white hover:bg-white/25 px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[10px] text-amber-300"></i>
                            <span>Dekat FK</span>
                        </a>
                        <a href="{{ route('fotocopy.index', ['q' => 'Perpustakaan']) }}" class="bg-white/15 text-white hover:bg-white/25 px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[10px] text-amber-300"></i>
                            <span>Dekat Perpus</span>
                        </a>
                        <a href="{{ route('fotocopy.index', ['q' => 'Skripsi']) }}" class="bg-white/15 text-white hover:bg-white/25 px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-graduation-cap text-[10px] text-amber-300"></i>
                            <span>Jilid Skripsi</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="bg-white border-b border-gray-100 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs sm:text-sm">
                <div class="flex items-center gap-3 sm:gap-4 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 bg-[#10A760] rounded-full status-pulse"></div>
                        <span class="font-medium text-gray-700">
                            <span class="text-[#10A760] font-bold">{{ $openCount }}</span> tempat buka sekarang
                        </span>
                    </div>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-500 font-medium">Total: {{ $totalCount }} Mitra Fotocopy USU</span>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-center">
                    <a href="{{ route('fotocopy.index') }}" class="px-3 py-1 rounded-lg text-xs font-semibold {{ !request('status') ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Semua
                    </a>
                    <a href="{{ route('fotocopy.index', ['status' => 'open']) }}" class="px-3 py-1 rounded-lg text-xs font-semibold {{ request('status') === 'open' ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Buka Saja
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8 sm:space-y-10">

        <!-- SECTION 0: Smart Product & Service Search (Cross 32 Stores & 2,689 Catalog Items) -->
        <section
            id="smart-fotocopy-section"
            x-data="usuFotocopySmartSearch()"
            class="bg-white rounded-3xl border border-emerald-200/80 shadow-sm p-4 sm:p-6 space-y-4"
        >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[#0D8A4E] text-[11px] font-bold mb-1">
                        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                        <span>Smart Search Lintas 32 Toko & 2.600+ Katalog ATK</span>
                    </div>
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900">Cari Produk ATK, Print, Jilid & Harga Terbaik</h2>
                    <p class="text-xs text-gray-500">Ketik natural seperti <em>"cari pulpen pink murah terdekat"</em>, <em>"print warna di bawah 1000"</em>, atau <em>"jilid hardcover dekat FIB"</em>.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="detectGps()"
                        :disabled="gpsLoading"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                        :class="userLat !== null ? 'bg-emerald-50 border-[#10A760] text-[#0D8A4E]' : 'bg-gray-50 hover:bg-emerald-50 border-gray-300 text-gray-700 hover:border-[#10A760]'"
                    >
                        <i class="fa-solid fa-location-crosshairs" :class="gpsLoading ? 'animate-spin' : ''"></i>
                        <span x-text="gpsLoading ? 'Mendeteksi GPS...' : (userLat !== null ? 'GPS Aktif (' + gpsAccuracyLabel + ')' : 'Gunakan Lokasi GPS Saya')"></span>
                    </button>
                </div>
            </div>

            <!-- GPS Notice / Diagnostic -->
            <template x-if="gpsNotice">
                <div class="text-xs px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800" x-text="gpsNotice"></div>
            </template>

            <!-- Smart Search Bar & Filter Controls -->
            <form @submit.prevent="runSearch()" class="space-y-3">
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </div>
                        <input
                            id="smart-fotocopy-input"
                            type="text"
                            x-model="query"
                            placeholder="Cari pulpen pink murah terdekat, print warna, jilid skripsi..."
                            class="w-full pl-10 pr-9 py-2.5 text-sm border border-gray-300 rounded-xl bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#10A760] focus:border-transparent"
                        />
                        <button
                            type="button"
                            x-show="query.trim().length > 0"
                            @click="clearSearch()"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                            aria-label="Hapus pencarian"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="px-5 py-2.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs sm:text-sm font-bold rounded-xl shadow-xs transition-all disabled:opacity-60 flex items-center justify-center gap-2 cursor-pointer shrink-0"
                    >
                        <i class="fa-solid fa-bolt"></i>
                        <span x-text="isLoading ? 'Mencari...' : 'Cari Pintar'"></span>
                    </button>
                </div>

                <!-- Sort & Filters Row -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <label for="smart-sort-select" class="font-semibold text-gray-600">Urutkan:</label>
                        <select
                            id="smart-sort-select"
                            x-model="sort"
                            @change="if (query.trim() || hasSearched) runSearch()"
                            class="px-2.5 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-800 text-xs font-semibold focus:ring-[#10A760] focus:border-[#10A760]"
                        >
                            <option value="recommended">Rekomendasi Pintar</option>
                            <option value="cheapest">Harga Termurah</option>
                            <option value="nearest">Jarak Terdekat</option>
                            <option value="rating">Rating Tertinggi</option>
                        </select>
                    </div>

                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 cursor-pointer select-none font-semibold text-gray-700">
                        <input type="checkbox" x-model="openNow" @change="if (query.trim() || hasSearched) runSearch()" class="rounded text-[#10A760] focus:ring-[#10A760]" />
                        <span>Buka Sekarang Saja</span>
                    </label>

                    <div class="flex items-center gap-1.5">
                        <label for="smart-max-price" class="font-semibold text-gray-600">Maks Rp:</label>
                        <input
                            id="smart-max-price"
                            type="number"
                            min="100"
                            step="500"
                            x-model="maxPrice"
                            @change="if (query.trim() || hasSearched) runSearch()"
                            placeholder="contoh 5000"
                            class="w-28 px-2.5 py-1 rounded-lg border border-gray-300 text-xs focus:ring-[#10A760] focus:border-[#10A760]"
                        />
                    </div>
                </div>
            </form>

            <!-- Quick Prompt Chips -->
            <div class="flex flex-wrap gap-1.5 pt-1">
                <template x-for="(chip, idx) in quickChips" :key="idx">
                    <button
                        type="button"
                        @click="applyChip(chip)"
                        class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 hover:bg-[#10A760] text-[#0D8A4E] hover:text-white border border-emerald-200 transition-colors cursor-pointer"
                        x-text="chip"
                    ></button>
                </template>
            </div>

            <!-- Smart Results Container -->
            <div x-show="hasSearched" class="pt-3 border-t border-gray-100 space-y-4" style="display: none;">
                <!-- Honest Relaxed-Attribute Advisory Banner -->
                <template x-if="advisoryNote">
                    <div id="smart-advisory-banner" class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs sm:text-sm flex items-start gap-2.5">
                        <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 shrink-0"></i>
                        <div>
                            <div class="font-bold text-amber-950 mb-0.5">Catatan Ketersediaan Katalog</div>
                            <div x-text="advisoryNote"></div>
                        </div>
                    </div>
                </template>

                <!-- Results Summary Header -->
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="font-bold text-gray-700">
                        Menampilkan <span class="text-[#10A760]" x-text="results.length"></span> rekomendasi produk/jasa terbaik
                        <template x-if="anchorLabel">
                            <span class="text-gray-500 font-normal" x-text="' • Acuan jarak: ' + anchorLabel"></span>
                        </template>
                    </div>
                    <template x-if="relaxedFallbackUsed">
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[11px]">Alternatif Terdekat Tersedia</span>
                    </template>
                </div>

                <!-- Product / Service Cards Grid -->
                <template x-if="results.length > 0">
                    <div id="smart-fotocopy-results" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="item in results" :key="item.item_id + '-' + item.store.id">
                            <div class="bg-gray-50/70 hover:bg-white rounded-2xl border border-gray-200 hover:border-[#10A760] p-4 flex flex-col justify-between gap-3 transition-all shadow-2xs hover:shadow-md">
                                <div class="space-y-2.5">
                                    <div class="flex items-start gap-3">
                                        <template x-if="item.image_url">
                                            <img :src="item.image_url" :alt="item.name" class="w-16 h-16 rounded-xl object-cover border border-gray-200 bg-white shrink-0" />
                                        </template>
                                        <template x-if="!item.image_url">
                                            <div class="w-16 h-16 rounded-xl bg-emerald-50 text-[#10A760] flex items-center justify-center shrink-0 border border-emerald-100">
                                                <i class="fa-solid fa-print text-xl"></i>
                                            </div>
                                        </template>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center flex-wrap gap-1 mb-1">
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800" x-text="item.category"></span>
                                                <span
                                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                                    :class="item.exact_attribute_match ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                                    x-text="item.exact_attribute_match ? 'Cocok' : 'Alternatif'"
                                                ></span>
                                            </div>
                                            <h3 class="font-extrabold text-sm text-gray-900 leading-snug line-clamp-2" x-text="item.name"></h3>
                                        </div>
                                    </div>

                                    <div class="flex items-baseline justify-between gap-2 pt-1">
                                        <div>
                                            <span class="text-base font-black text-[#10A760]" x-text="item.formatted_price"></span>
                                            <span class="text-xs text-gray-500" x-text="'/' + item.unit"></span>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                            :class="item.store.is_open_now ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700'"
                                            x-text="item.store.is_open_now ? 'Buka Sekarang' : 'Tutup'"
                                        ></span>
                                    </div>

                                    <div class="text-xs bg-white rounded-xl p-2.5 border border-gray-100 space-y-1">
                                        <div class="font-bold text-gray-800 flex items-center justify-between gap-2">
                                            <span class="truncate" x-text="item.store.name"></span>
                                            <span class="text-amber-500 font-black shrink-0"><i class="fa-solid fa-star text-[10px]"></i> <span x-text="Number(item.store.rating || 4.5).toFixed(1)"></span></span>
                                        </div>
                                        <div class="text-[11px] text-gray-500 flex items-center gap-1.5">
                                            <i class="fa-solid fa-person-walking text-[#10A760]"></i>
                                            <span x-text="'~' + item.store.distance_meters + ' m (' + item.store.walk_minutes + ' mnt jalan)'"></span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 line-clamp-1" x-text="item.ranking_reason"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <a
                                        :href="item.actions.order_url"
                                        class="flex-1 py-2 px-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold rounded-xl text-center transition-colors flex items-center justify-center gap-1.5"
                                    >
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        <span>Pesan di Toko Ini</span>
                                    </a>
                                    <a
                                        :href="item.actions.route_url"
                                        class="py-2 px-3 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-xs font-bold rounded-xl text-center transition-colors flex items-center justify-center gap-1"
                                        title="Arahkan Rute In-App di Peta"
                                    >
                                        <i class="fa-solid fa-route"></i>
                                        <span>Rute di Peta</span>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!isLoading && results.length === 0">
                    <div class="p-6 rounded-2xl bg-gray-50 border border-dashed border-gray-300 text-center text-xs text-gray-600">
                        Belum ditemukan produk/jasa yang cocok dengan kriteria Anda. Coba hapus filter harga maksimal atau gunakan kata kunci lain.
                    </div>
                </template>
            </div>
        </section>

        <!-- SECTION 1: Tempat Fotocopy Buka (Card Kesamping / Horizontal Scrolling) -->
        @if($openPlaces->isNotEmpty())
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 sm:h-6 bg-[#10A760] rounded-full"></span>
                        Tempat Fotocopy Buka Sekarang
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 ml-3.5">Geser ke samping untuk melihat tempat yang aktif saat ini</p>
                </div>
                <div class="hidden sm:flex gap-2">
                    <button onclick="document.getElementById('openScrollContainer').scrollBy({ left: -320, behavior: 'smooth' })" class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-100 transition shadow-xs text-gray-600" title="Geser ke Kiri">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <button onclick="document.getElementById('openScrollContainer').scrollBy({ left: 320, behavior: 'smooth' })" class="w-8 h-8 rounded-full bg-[#10A760] text-white flex items-center justify-center hover:bg-[#0D8A4E] transition shadow-xs" title="Geser ke Kanan">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Sideways Scrollable Cards -->
            <div id="openScrollContainer" class="flex gap-4 overflow-x-auto hide-scrollbar scroll-snap pb-3 -mx-3 px-3 sm:mx-0 sm:px-0">
                @foreach($openPlaces as $place)
                <div class="min-w-[85vw] max-w-[85vw] sm:min-w-[320px] sm:max-w-[320px] bg-white rounded-2xl border border-gray-100 overflow-hidden card-hover snap-card shadow-xs flex flex-col justify-between">
                    <div>
                        <!-- Card Header Image/Thumbnail -->
                        <div class="relative h-36 sm:h-40 bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if($place->image_url)
                                <img src="{{ $place->image_url }}" alt="{{ $place->name }}" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            @else
                                <div class="text-center">
                                    <div class="w-12 sm:w-14 h-12 sm:h-14 bg-[#10A760]/15 rounded-2xl flex items-center justify-center mx-auto mb-1.5 text-[#10A760]">
                                        <i class="fa-solid fa-print text-xl sm:text-2xl"></i>
                                    </div>
                                    <span class="text-[10px] sm:text-[11px] font-bold text-[#0D8A4E] tracking-wider uppercase">Mitra Fotocopy USU</span>
                                </div>
                            @endif
                            <!-- Status Badge -->
                            <div class="absolute top-3 left-3 z-10">
                                <span class="bg-[#10A760] text-white text-[10px] sm:text-[11px] font-bold px-2.5 py-0.5 rounded-full flex items-center gap-1.5 shadow-xs">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                                    Buka
                                </span>
                            </div>
                            <!-- Rating -->
                            <div class="absolute top-3 right-3 z-10">
                                <span class="bg-white/95 backdrop-blur-xs text-[#D4A017] text-xs font-black px-2 py-0.5 rounded-full shadow-xs flex items-center gap-1">
                                    <i class="fa-solid fa-star text-[10px]"></i> {{ number_format($place->rating, 1) }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-3.5 sm:p-4">
                            <div class="flex items-start justify-between gap-1 mb-1">
                                <h3 class="font-extrabold text-gray-900 text-sm sm:text-base leading-snug line-clamp-1 hover:text-[#10A760] transition-colors">
                                    <a href="{{ route('fotocopy.show', $place->id) }}">{{ $place->name }}</a>
                                </h3>
                            </div>

                            <p class="text-xs text-gray-500 flex items-center gap-1.5 mb-2.5">
                                <i class="fa-solid fa-location-dot text-[#10A760] shrink-0 text-xs"></i>
                                <span class="line-clamp-1">{{ $place->location_name }}</span>
                            </p>

                            <!-- Operating Hours -->
                            <div class="bg-gray-50 rounded-xl p-2.5 mb-3 flex items-center justify-between text-xs text-gray-600">
                                <div class="flex items-center gap-2">
                                    <i class="fa-regular fa-clock text-[#10A760] text-sm"></i>
                                    <div>
                                        <div class="font-bold text-gray-800 text-xs">{{ $place->open_time }} - {{ $place->close_time }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $place->operating_days }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] text-gray-400 block">Mulai</span>
                                    <span class="font-black text-[#10A760] text-xs">Rp 200/lbr</span>
                                </div>
                            </div>

                            <!-- Services Tags -->
                            <div class="flex flex-wrap gap-1 mb-3">
                                @foreach($place->items->take(3) as $item)
                                    <span class="bg-[#E8F5EE] text-[#0D8A4E] text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                        {{ $item->name }}
                                    </span>
                                @endforeach
                            </div>

                            <!-- Social Contact Direct Buttons (ICON ONLY, NO TEXT as per request) -->
                            <div class="border-t border-gray-100 pt-2.5 mb-2 flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-400">Kontak Person:</span>
                                <div class="flex items-center gap-2">
                                    <!-- WhatsApp (Icon Only) -->
                                    <a
                                        href="{{ $place->getWhatsappUrl() }}"
                                        target="_blank"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#25D366]/10 text-[#128C7E] hover:bg-[#25D366] hover:text-white flex items-center justify-center transition-all shadow-2xs"
                                        title="Hubungi via WhatsApp"
                                    >
                                        <i class="fa-brands fa-whatsapp text-sm sm:text-base"></i>
                                    </a>

                                    @if($place->instagram)
                                    <!-- Instagram (Icon Only) -->
                                    <a
                                        href="{{ $place->getInstagramUrl() }}"
                                        target="_blank"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-pink-50 text-pink-600 hover:bg-gradient-to-tr hover:from-amber-500 hover:via-pink-500 hover:to-purple-600 hover:text-white flex items-center justify-center transition-all shadow-2xs"
                                        title="Buka Instagram"
                                    >
                                        <i class="fa-brands fa-instagram text-xs sm:text-sm"></i>
                                    </a>
                                    @endif

                                    @if($place->facebook)
                                    <!-- Facebook (Icon Only) -->
                                    <a
                                        href="{{ $place->getFacebookUrl() }}"
                                        target="_blank"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-all shadow-2xs"
                                        title="Buka Facebook"
                                    >
                                        <i class="fa-brands fa-facebook-f text-xs sm:text-sm"></i>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons: Detail & Pesan -->
                    <div class="p-3.5 sm:p-4 pt-0">
                        <div class="flex items-center gap-2">
                            <!-- Button Detail (Laman Baru) -->
                            <a
                                href="{{ route('fotocopy.show', $place->id) }}"
                                class="flex-1 bg-white border border-[#10A760] text-[#10A760] hover:bg-green-50 text-xs font-bold py-2 rounded-xl text-center transition-all shadow-2xs flex items-center justify-center gap-1.5"
                            >
                                <i class="fa-regular fa-eye text-xs"></i>
                                <span>Detail</span>
                            </a>

                            <!-- Button Pesan (Laman Baru) -->
                            <a
                                href="{{ route('fotocopy.order', $place->id) }}"
                                class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold py-2 rounded-xl text-center transition-all shadow-xs flex items-center justify-center gap-1.5"
                            >
                                <i class="fa-solid fa-cart-shopping text-xs"></i>
                                <span>Pesan</span>
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- SECTION 2: Semua Tempat Fotocopy (Lengkap Grid) -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 sm:h-6 bg-[#D4A017] rounded-full"></span>
                        Semua Tempat Fotocopy Kampus USU
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 ml-3.5">
                        @if($search)
                            Menampilkan hasil pencarian untuk "<span class="text-[#10A760] font-bold">{{ $search }}</span>"
                        @else
                            Daftar lengkap mitra jasa fotocopy & percetakan di lingkungan USU
                        @endif
                    </p>
                </div>
            </div>

            @if($places->isEmpty())
                <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center my-6">
                    <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400 text-xl">
                        <i class="fa-solid fa-store-slash"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-800 mb-1">Tidak Ditemukan Tempat Fotocopy</h3>
                    <p class="text-xs text-gray-500 mb-4">Coba cari dengan kata kunci lain atau tampilkan semua tempat.</p>
                    <a href="{{ route('fotocopy.index') }}" class="inline-block bg-[#10A760] text-white text-xs font-bold px-4 py-2 rounded-xl">
                        Lihat Semua Tempat
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6">
                    @foreach($places as $place)
                    @php $isOpen = $place->isOpenNow(); @endphp
                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden card-hover shadow-xs flex flex-col justify-between h-full">
                        <div>
                            <!-- Google Maps Photo Thumbnail -->
                            <div class="relative h-36 bg-gray-100 overflow-hidden">
                                @if($place->image_url)
                                    <img src="{{ $place->image_url }}" alt="{{ $place->name }}" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-[#E8F5EE] to-[#D1F0DF] flex items-center justify-center text-[#10A760]">
                                        <i class="fa-solid fa-print text-2xl"></i>
                                    </div>
                                @endif
                                <!-- Status Badge -->
                                <div class="absolute top-2.5 left-2.5 z-10">
                                    <span class="{{ $isOpen ? 'bg-[#10A760]' : 'bg-gray-800' }} text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-xs">
                                        {{ $isOpen ? 'Buka' : 'Tutup' }}
                                    </span>
                                </div>
                                <!-- Rating -->
                                <div class="absolute top-2.5 right-2.5 z-10">
                                    <span class="bg-white/95 backdrop-blur-xs text-[#D4A017] text-[11px] font-black px-2 py-0.5 rounded-full shadow-xs flex items-center gap-1">
                                        <i class="fa-solid fa-star text-[10px]"></i> {{ number_format($place->rating, 1) }}
                                        <span class="text-gray-400 font-normal text-[9px]">({{ $place->user_rating_count }})</span>
                                    </span>
                                </div>
                            </div>

                            <div class="p-4 sm:p-4.5">
                                <!-- Header: Title and Location -->
                                <div class="mb-2">
                                    <h3 class="font-extrabold text-gray-900 text-sm sm:text-base leading-snug hover:text-[#10A760]">
                                        <a href="{{ route('fotocopy.show', $place->id) }}">{{ $place->name }}</a>
                                    </h3>
                                    <p class="text-xs text-gray-500 flex items-center gap-1.5 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-[#10A760] text-xs"></i>
                                        <span>{{ $place->location_name }}</span>
                                    </p>
                                </div>

                                <p class="text-xs text-gray-600 line-clamp-2 mb-3">
                                    {{ $place->description }}
                                </p>

                                <!-- Operational Schedule & Quick Prices -->
                                <div class="bg-gray-50 rounded-xl p-2.5 mb-3 text-xs flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-regular fa-clock text-[#10A760] text-sm"></i>
                                        <div>
                                            <div class="text-[10px] text-gray-400">Jadwal Operasional:</div>
                                            <div class="font-bold text-gray-800">{{ $place->open_time }} - {{ $place->close_time }} WIB</div>
                                            <div class="text-[10px] text-gray-500">{{ $place->operating_days }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] text-gray-400">Rating:</div>
                                        <div class="font-black text-[#D4A017] flex items-center gap-1 justify-end">
                                            <i class="fa-solid fa-star text-[10px]"></i>
                                            <span>{{ number_format($place->rating, 1) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Social Direct Contact Row (ICON ONLY, NO TEXT as per request) -->
                                <div class="flex items-center justify-between text-xs mb-3.5 pb-2.5 border-b border-gray-100">
                                    <span class="text-[11px] font-semibold text-gray-400">Kontak Person:</span>
                                    <div class="flex items-center gap-2">
                                        <!-- WhatsApp -->
                                        <a
                                            href="{{ $place->getWhatsappUrl() }}"
                                            target="_blank"
                                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#25D366]/10 hover:bg-[#25D366] hover:text-white text-[#128C7E] flex items-center justify-center transition-all shadow-2xs"
                                            title="WhatsApp Otomatis"
                                        >
                                            <i class="fa-brands fa-whatsapp text-sm sm:text-base"></i>
                                        </a>
                                        @if($place->instagram)
                                        <!-- Instagram -->
                                        <a
                                            href="{{ $place->getInstagramUrl() }}"
                                            target="_blank"
                                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-pink-50 hover:bg-gradient-to-tr hover:from-amber-500 hover:via-pink-500 hover:to-purple-600 hover:text-white text-pink-600 flex items-center justify-center transition-all shadow-2xs"
                                            title="Instagram"
                                        >
                                            <i class="fa-brands fa-instagram text-xs sm:text-sm"></i>
                                        </a>
                                        @endif
                                        @if($place->facebook)
                                        <!-- Facebook -->
                                        <a
                                            href="{{ $place->getFacebookUrl() }}"
                                            target="_blank"
                                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-600 flex items-center justify-center transition-all shadow-2xs"
                                            title="Facebook"
                                        >
                                            <i class="fa-brands fa-facebook-f text-xs sm:text-sm"></i>
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Action Buttons: Detail, Rute & Pesan -->
                            <div class="p-4 sm:p-4.5 pt-0">
                                <div class="flex items-center gap-2">
                                    <!-- Button Detail (Laman Baru) -->
                                    <a
                                        href="{{ route('fotocopy.show', $place->id) }}"
                                        class="flex-1 bg-white border border-[#10A760] text-[#10A760] hover:bg-green-50 text-xs font-bold py-2 rounded-xl text-center transition shadow-2xs flex items-center justify-center gap-1.5"
                                    >
                                        <i class="fa-regular fa-eye text-xs"></i>
                                        <span>Detail</span>
                                    </a>

                                    <a
                                        href="{{ $place->getDirectionUrl() }}"
                                        class="px-3 py-2 bg-amber-50 border border-amber-300 text-amber-800 hover:bg-amber-100 text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1"
                                        title="Rute In-App di Peta Kampus"
                                    >
                                        <i class="fa-solid fa-route text-xs"></i>
                                        <span>Rute</span>
                                    </a>

                                    <!-- Button Pesan (Laman Baru) -->
                                    <a
                                        href="{{ route('fotocopy.order', $place->id) }}"
                                        class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold py-2 rounded-xl text-center transition shadow-xs flex items-center justify-center gap-1.5"
                                    >
                                        <i class="fa-solid fa-cart-shopping text-xs"></i>
                                        <span>Pesan</span>
                                    </a>
                                </div>
                            </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <script id="usu-fotocopy-smart-boot" type="application/json">@json(['initialQuery' => $search ?? '', 'initialSmartResult' => $smartInitialResult ?? null])</script>
    <script>
        window.usuFotocopySmartSearch = function () {
            let boot = { initialQuery: '', initialSmartResult: null };
            try {
                const rawBoot = document.getElementById('usu-fotocopy-smart-boot')?.textContent;
                if (rawBoot) boot = JSON.parse(rawBoot);
            } catch (e) {}

            return {
                query: boot.initialQuery || '',
                sort: 'recommended',
                openNow: false,
                maxPrice: '',
                userLat: null,
                userLng: null,
                gpsAccuracyLabel: '',
                gpsLoading: false,
                gpsNotice: '',
                isLoading: false,
                hasSearched: false,
                results: [],
                advisoryNote: null,
                relaxedFallbackUsed: false,
                anchorLabel: '',
                quickChips: [
                    'cari pulpen pink murah terdekat',
                    'print warna murah',
                    'jilid hardcover skripsi',
                    'kertas hvs a4',
                    'fotocopy buka sekarang'
                ],

                init() {
                    try {
                        const savedGps = sessionStorage.getItem('usu_last_gps_fix');
                        if (savedGps) {
                            const parsed = JSON.parse(savedGps);
                            if (parsed && Number.isFinite(Number(parsed.lat)) && Number.isFinite(Number(parsed.lng))) {
                                this.userLat = Number(parsed.lat);
                                this.userLng = Number(parsed.lng);
                                this.gpsAccuracyLabel = parsed.accuracy ? ('±' + Math.round(Number(parsed.accuracy)) + ' m') : 'Tersimpan';
                            }
                        }
                    } catch (e) {}

                    if (boot.initialSmartResult && boot.initialSmartResult.results) {
                        this.applyApiResponse(boot.initialSmartResult);
                    } else if (this.query.trim().length > 0) {
                        this.runSearch();
                    }
                },

                applyApiResponse(data) {
                    this.hasSearched = true;
                    this.results = Array.isArray(data.results) ? data.results : [];
                    this.advisoryNote = data.advisory_note || null;
                    this.relaxedFallbackUsed = !!data.relaxed_fallback_used;
                    this.anchorLabel = data.anchor?.label || 'Pusat Kampus USU';
                },

                applyChip(text) {
                    this.query = text;
                    this.runSearch();
                },

                clearSearch() {
                    this.query = '';
                    this.results = [];
                    this.advisoryNote = null;
                    this.relaxedFallbackUsed = false;
                    this.hasSearched = false;
                },

                async runSearch() {
                    const q = this.query.trim();
                    if (!q) {
                        this.clearSearch();
                        return;
                    }
                    this.isLoading = true;
                    this.hasSearched = true;
                    try {
                        const params = new URLSearchParams({
                            q: q,
                            sort: this.sort,
                            limit: '18'
                        });
                        if (this.openNow) params.set('open_now', '1');
                        if (this.maxPrice && Number(this.maxPrice) > 0) params.set('max_price', String(this.maxPrice));
                        if (this.userLat !== null && this.userLng !== null) {
                            params.set('lat', String(this.userLat));
                            params.set('lng', String(this.userLng));
                        }

                        const resp = await fetch('/api/fotocopy/smart-search?' + params.toString(), {
                            headers: { Accept: 'application/json' }
                        });
                        const data = await resp.json();
                        this.applyApiResponse(data);
                    } catch (err) {
                        console.error('Smart fotocopy search failed:', err);
                        this.advisoryNote = 'Gagal menghubungi layanan Smart Search. Silakan coba lagi.';
                    } finally {
                        this.isLoading = false;
                    }
                },

                detectGps() {
                    if (!window.isSecureContext || !navigator.geolocation) {
                        this.gpsNotice = 'GPS browser tidak tersedia pada konteks ini; jarak tetap dihitung dari pusat Kampus USU atau nama gedung di query.';
                        return;
                    }
                    this.gpsLoading = true;
                    this.gpsNotice = '';

                    const acceptFix = (pos) => {
                        this.userLat = Number(pos.coords.latitude);
                        this.userLng = Number(pos.coords.longitude);
                        const acc = Math.round(Number(pos.coords.accuracy || 30));
                        this.gpsAccuracyLabel = '±' + acc + ' m';
                        this.gpsLoading = false;
                        try {
                            sessionStorage.setItem('usu_last_gps_fix', JSON.stringify({
                                lat: this.userLat,
                                lng: this.userLng,
                                accuracy: acc,
                                timestamp: Date.now()
                            }));
                        } catch (e) {}
                        if (this.query.trim()) {
                            this.runSearch();
                        }
                    };

                    navigator.geolocation.getCurrentPosition(
                        acceptFix,
                        () => {
                            navigator.geolocation.getCurrentPosition(
                                acceptFix,
                                () => {
                                    this.gpsLoading = false;
                                    this.gpsNotice = 'Izin lokasi GPS ditolak atau belum tersedia; jarak menggunakan pusat Kampus USU.';
                                },
                                { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                            );
                        },
                        { enableHighAccuracy: true, timeout: 7000, maximumAge: 30000 }
                    );
                }
            };
        };
    </script>
</x-layouts.app>
