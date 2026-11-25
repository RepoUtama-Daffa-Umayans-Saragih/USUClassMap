<x-layouts.app>
    <x-slot name="title">Layanan Fotocopy & Percetakan Kampus</x-slot>

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
        [x-cloak] { display: none !important; }
    </style>

    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-[#10A760] via-[#0D8A4E] to-[#087A3F] text-white">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <svg width="100%" height="100%"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/></pattern></defs><rect width="100%" height="100%" fill="url(#grid)"/></svg>
        </div>
        <div class="absolute -top-10 -right-10 w-72 h-72 bg-[#D4A017] rounded-full opacity-15 blur-3xl pointer-events-none"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 sm:py-9 md:py-10">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md px-3.5 py-1 rounded-full mb-3 shadow-xs">
                    <i class="fa-solid fa-print text-[#D4A017] text-xs"></i>
                    <span class="text-white text-xs font-semibold">Integrasi Layanan Mahasiswa USU</span>
                </div>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-white tracking-tight mb-2">
                    Layanan Fotocopy & Percetakan <span class="text-[#D4A017]">Kampus USU</span>
                </h1>
                <p class="text-white/80 text-xs sm:text-sm max-w-2xl mx-auto px-2">
                    Temukan tempat fotocopy terdekat dari ruang kelasmu, bandingkan harga cetak, pesan langsung via online, atau hubungi pemilik toko dengan pesan otomatis.
                </p>
            </div>
        </div>
    </div>

    <div id="fotocopy-unified-root" x-data="fotocopyUnifiedSearch()">
        <!-- Status Bar -->
        <div class="bg-white border-b border-gray-100 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs sm:text-sm">
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 w-full sm:w-auto justify-between sm:justify-start">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 bg-[#10A760] rounded-full status-pulse"></div>
                            <span class="font-medium text-gray-700">
                                <span id="live-open-count" class="text-[#10A760] font-bold" x-text="campusOpenCount">{{ $openCount }}</span> tempat buka sekarang
                            </span>
                        </div>
                        <span class="text-gray-300 hidden sm:inline">|</span>
                        <span class="text-gray-500 font-medium">
                            Total: {{ $totalActiveCount ?? $totalCount }} Mitra Fotocopy USU
                            <span
                                id="live-filter-count"
                                x-show="isFilterActive"
                                x-text="'(' + activeFilterCountLabel() + ' hasil filter)'"
                                class="text-xs text-[#10A760] font-semibold {{ ($search || $filter === 'open' || $initialMaxPrice) ? '' : 'hidden' }}"
                            >({{ $totalCount }} hasil filter)</span>
                        </span>
                        <span class="text-gray-300 hidden sm:inline">|</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                            <i class="fa-regular fa-clock text-[#10A760]"></i>
                            <span>WIB <span id="live-wib-clock" class="font-mono font-bold text-gray-900">{{ $wibClock ?? now('Asia/Jakarta')->format('H:i') }}</span></span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-center">
                        <a
                            href="{{ route('fotocopy.index') }}"
                            @click.prevent="setStatusFilter(false)"
                            :class="!openNow ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-lg text-xs font-semibold transition-colors {{ !request('status') ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                        >
                            Semua
                        </a>
                        <a
                            href="{{ route('fotocopy.index', ['status' => 'open']) }}"
                            @click.prevent="setStatusFilter(true)"
                            :class="openNow ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-lg text-xs font-semibold transition-colors {{ request('status') === 'open' ? 'bg-[#10A760] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                        >
                            Buka Saja
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Unified Main Container -->
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
            <section
                id="smart-fotocopy-section"
                class="bg-white rounded-3xl border border-emerald-200/80 shadow-sm p-4 sm:p-6 space-y-5"
            >
                <!-- Search Header & Auto-GPS -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[#0D8A4E] text-[11px] font-bold mb-1">
                            <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                            <span>Smart Search Lintas {{ $totalActiveCount ?? $totalCount }} Toko &amp; {{ number_format($totalCatalogCount ?? 2600, 0, ',', '.') }}+ Katalog ATK</span>
                        </div>
                        <h2 class="text-base sm:text-xl font-extrabold text-gray-900">Cari Tempat Fotocopy, Produk ATK, Print & Jilid Terbaik</h2>
                        <p class="text-xs text-gray-500">Ketik nama toko, lokasi fakultas, atau produk seperti <em>"fotocopy dekat FT"</em>, <em>"USU Press"</em>, <em>"pulpen pink murah terdekat"</em>, atau <em>"pulpen pink deket teknik"</em>.</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <div
                            id="fotocopy-gps-header-badge"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-bold"
                            :class="userLat !== null ? 'bg-emerald-50 border-[#10A760] text-[#0D8A4E]' : (gpsLoading ? 'bg-sky-50 border-sky-200 text-sky-800' : 'bg-amber-50 border-amber-200 text-amber-800')"
                        >
                            <span class="w-2 h-2 rounded-full" :class="userLat !== null ? 'bg-[#10A760] status-pulse' : (gpsLoading ? 'bg-sky-500 animate-ping' : 'bg-amber-500')"></span>
                            <span x-text="userLat !== null ? ('Acuan jarak: Lokasi GPS Anda (' + gpsAccuracyLabel + ') • Real-Time') : (gpsLoading ? 'Mendeteksi GPS real-time...' : 'GPS belum aktif')"></span>
                        </div>
                        <button
                            type="button"
                            id="fotocopy-refresh-gps-btn"
                            @click="detectGps(true)"
                            :disabled="gpsLoading"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                            :class="userLat !== null ? 'bg-white hover:bg-emerald-50 border-emerald-300 text-[#0D8A4E]' : 'bg-[#10A760] hover:bg-[#0D8A4E] border-[#10A760] text-white shadow-xs'"
                        >
                            <i class="fa-solid fa-location-crosshairs" :class="gpsLoading ? 'animate-spin' : ''"></i>
                            <span x-text="userLat !== null ? 'Perbarui GPS Saya' : 'Gunakan Lokasi GPS Saya'">Gunakan Lokasi GPS Saya</span>
                        </button>
                    </div>
                </div>

                <!-- GPS Notice / Friendly Banner -->
                <template x-if="gpsNotice">
                    <div id="fotocopy-gps-denied-banner" class="text-xs px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-location-dot text-amber-600"></i>
                            <span x-text="gpsNotice"></span>
                        </span>
                        <button
                            type="button"
                            @click="detectGps(true)"
                            class="px-3 py-1 rounded-lg bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-[11px] cursor-pointer shrink-0"
                        >
                            Aktifkan GPS Sekarang
                        </button>
                    </div>
                </template>

                <!-- Unified Search Form & Filters -->
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
                                placeholder="Cari tempat fotocopy, lokasi fakultas, pulpen pink, print warna, atau jilid..."
                                class="w-full pl-10 pr-9 py-2.5 text-sm border border-gray-300 rounded-xl bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#10A760] focus:border-transparent"
                            />
                            <button
                                type="button"
                                x-show="query.trim().length > 0"
                                x-cloak
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

                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
                        <div class="flex items-center gap-1.5">
                            <label for="smart-sort-select" class="font-semibold text-gray-600">Urutkan:</label>
                            <select
                                id="smart-sort-select"
                                x-model="sort"
                                @change="runSearch()"
                                class="px-2.5 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-800 text-xs font-semibold focus:ring-[#10A760] focus:border-[#10A760]"
                            >
                                <option value="recommended">Rekomendasi Pintar</option>
                                <option value="cheapest">Harga Termurah</option>
                                <option value="nearest">Jarak Terdekat</option>
                                <option value="rating">Rating Tertinggi</option>
                            </select>
                        </div>

                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 cursor-pointer select-none font-semibold text-gray-700">
                            <input type="checkbox" x-model="openNow" @change="runSearch()" class="rounded text-[#10A760] focus:ring-[#10A760]" />
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
                                @change="runSearch()"
                                @input.debounce.450ms="if (maxPrice !== '') runSearch()"
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

                <!-- 2.1 Smart Intent Bar + Segmented Tab Switcher -->
                <div
                    id="smart-intent-bar"
                    class="bg-emerald-50/70 border border-emerald-100 rounded-2xl px-4 py-3 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2 text-sm font-bold text-slate-900">
                            <span class="w-7 h-7 rounded-lg bg-[#10A760] text-white inline-flex items-center justify-center text-xs shrink-0">
                                <i class="fa-solid" :class="modeIconClass()"></i>
                            </span>
                            <span id="smart-intent-headline" x-text="intentHeadline()" class="truncate sm:whitespace-normal"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="(pill, pIdx) in intentSummary.pills || []" :key="'pill-' + pIdx">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                    :class="intentPillClass(pill.type)"
                                    x-text="pill.label"
                                ></span>
                            </template>
                        </div>
                    </div>

                    <!-- Segmented Mode Tab Switcher -->
                    <div
                        id="adaptive-mode-tabs"
                        role="tablist"
                        class="inline-flex items-center bg-white border border-emerald-200 rounded-xl p-1 gap-1 shadow-2xs shrink-0 flex-wrap"
                    >
                        <template x-if="searchMode === 'mixed'">
                            <button
                                type="button"
                                role="tab"
                                data-mode-tab="mixed"
                                :aria-selected="activeTab === 'mixed'"
                                @click="setTab('mixed')"
                                :class="activeTab === 'mixed' ? 'bg-[#10A760] text-white shadow-xs' : 'text-slate-600 hover:bg-emerald-50'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                            >
                                <span>Terpadu</span>
                                <span class="opacity-90" x-text="'(' + mixedGroups.length + ' Toko)'"></span>
                            </button>
                        </template>

                        <button
                            type="button"
                            role="tab"
                            data-mode-tab="store"
                            :aria-selected="activeTab === 'store'"
                            @click="setTab('store')"
                            :class="activeTab === 'store' ? 'bg-[#10A760] text-white shadow-xs' : 'text-slate-600 hover:bg-emerald-50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        >
                            <span x-text="searchMode === 'mixed' ? 'Semua Toko' : 'Tempat Fotokopi'"></span>
                            <span class="opacity-90" x-text="'(' + stores.length + ')'"></span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-mode-tab="product"
                            :aria-selected="activeTab === 'product'"
                            @click="setTab('product')"
                            :class="activeTab === 'product' ? 'bg-[#10A760] text-white shadow-xs' : 'text-slate-600 hover:bg-emerald-50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        >
                            <span x-text="searchMode === 'mixed' ? 'Produk' : 'Produk & Jasa'"></span>
                            <span class="opacity-90" x-text="'(' + results.length + ')'"></span>
                        </button>
                    </div>
                </div>

                <!-- Honest Advisory Banner -->
                <template x-if="advisoryNote">
                    <div id="smart-advisory-banner" class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs sm:text-sm flex items-start gap-2.5">
                        <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 shrink-0"></i>
                        <div>
                            <div class="font-bold text-amber-950 mb-0.5">Catatan Ketersediaan Katalog</div>
                            <div x-text="advisoryNote"></div>
                        </div>
                    </div>
                </template>

                <!-- 2.2 SINGLE ADAPTIVE RESULT STAGE (3 Distinct Card Designs) -->
                <div id="adaptive-results-stage" class="pt-1">
                    <!-- SSR Semantic Fallback for Non-JS Requests (Removed cleanly once Alpine initializes) -->
                    <div data-ssr-fallback class="sr-only">
                        @foreach($places as $place)
                            <div data-ssr-store="{{ $place->id }}">
                                <a href="{{ route('fotocopy.show', $place->id) }}">{{ $place->name }}</a>
                                <span>{{ $place->location_name }}</span>
                                <a href="{{ route('fotocopy.order', $place->id) }}">Pesan</a>
                                <a href="{{ $place->getDirectionUrl() }}">Rute Peta</a>
                            </div>
                        @endforeach
                    </div>

                    <!-- VIEW 1: Store Cards (Mode 'store' atau Tab 'Tempat Fotokopi') -->
                    <div
                        id="adaptive-store-grid"
                        x-show="activeTab === 'store' && stores.length > 0"
                        x-cloak
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"
                    >
                        <template x-for="store in stores" :key="'store-' + store.id">
                            <article class="bg-white rounded-2xl border overflow-hidden card-hover shadow-xs flex flex-col justify-between" :class="store.is_open_now ? 'border-slate-200/80' : 'border-red-200/80'">
                                <div>
                                    <!-- Store Visual Header -->
                                    <div class="relative h-40 w-full bg-slate-100 overflow-hidden rounded-t-2xl">
                                        <img
                                            :src="store.photo_url || fallbackStorePhoto(store.id)"
                                            :alt="store.name"
                                            x-on:error="onImgError($event, store.id)"
                                            class="h-40 w-full object-cover"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent pointer-events-none"></div>

                                        <!-- Top-Left Status Badge -->
                                        <div class="absolute top-3 left-3 z-10">
                                            <span
                                                class="text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1.5 shadow-xs"
                                                :class="store.is_open_now ? 'bg-[#10A760]' : 'bg-[#DC2626]'"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-white" :class="store.is_open_now ? 'animate-pulse' : ''"></span>
                                                <span x-text="store.is_open_now ? 'Buka Sekarang' : 'Tutup'"></span>
                                            </span>
                                        </div>

                                        <!-- Top-Right Rating & Quick Distance Badge -->
                                        <div class="absolute top-3 right-3 z-10 flex items-center gap-1.5">
                                            <span class="bg-white/95 backdrop-blur-xs text-slate-800 text-[11px] font-bold px-2 py-0.5 rounded-full shadow-xs" x-text="'~' + store.distance_m + ' m'"></span>
                                            <span class="bg-white/95 backdrop-blur-xs text-[#D4A017] text-[11px] font-black px-2 py-0.5 rounded-full shadow-xs inline-flex items-center gap-1">
                                                <i class="fa-solid fa-star text-[10px]"></i>
                                                <span x-text="Number(store.rating || 4.5).toFixed(1)"></span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Store Body -->
                                    <div class="p-4 space-y-2.5">
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900 leading-snug">
                                                <a :href="store.detail_url" class="hover:text-[#10A760] transition-colors" x-html="highlightText(store.name)"></a>
                                            </h3>
                                            <p class="text-sm font-medium text-emerald-700 mt-1 flex items-center gap-1.5">
                                                <i class="fa-solid fa-person-walking text-[#10A760] shrink-0"></i>
                                                <span x-text="formatStoreDistanceLine(store)"></span>
                                            </p>
                                        </div>

                                        <div class="text-xs text-slate-500 space-y-1">
                                            <p class="flex items-start gap-1.5">
                                                <i class="fa-solid fa-location-dot text-[#10A760] mt-0.5 shrink-0"></i>
                                                <span class="line-clamp-1" x-text="store.address"></span>
                                            </p>
                                            <p class="flex items-center gap-1.5">
                                                <i class="fa-regular fa-clock text-[#10A760] shrink-0"></i>
                                                <span x-text="store.hours_label + ' (' + store.operating_days + ')'"></span>
                                            </p>
                                        </div>

                                        <div class="pt-1 flex items-center justify-between gap-2 flex-wrap">
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="(tag, tIdx) in (store.service_tags || []).slice(0, 4)" :key="'tag-' + store.id + '-' + tIdx">
                                                    <span class="bg-emerald-50 text-[#0D8A4E] border border-emerald-200/70 text-[10px] font-semibold px-2 py-0.5 rounded-md" x-text="tag"></span>
                                                </template>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="text-[10px] text-slate-400">Mulai </span>
                                                <span class="text-xs font-extrabold text-[#10A760]" x-text="store.formatted_starting_price"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Store Card Footer (2 Proportional Buttons) -->
                                <div class="p-4 pt-0">
                                    <div class="flex items-center gap-2">
                                        <a
                                            :href="store.order_url"
                                            class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold py-2.5 px-3 rounded-xl text-center transition-colors shadow-xs inline-flex items-center justify-center gap-1.5"
                                        >
                                            <i class="fa-solid fa-store"></i>
                                            <span>Buka Toko & Pesan</span>
                                        </a>
                                        <a
                                            :href="store.map_route_url"
                                            class="py-2.5 px-3.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-xs font-bold rounded-xl transition-colors inline-flex items-center justify-center gap-1.5 shrink-0"
                                            title="Arahkan Rute di Peta Kampus"
                                        >
                                            <i class="fa-solid fa-route"></i>
                                            <span>Rute Peta</span>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <!-- VIEW 2: Product-First Cards (Mode 'product' atau Tab 'Produk & Jasa') -->
                    <div
                        id="adaptive-product-grid"
                        x-show="activeTab === 'product' && results.length > 0"
                        x-cloak
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"
                    >
                        <template x-for="item in results" :key="'prod-' + item.item_id + '-' + item.store.id">
                            <article class="bg-white rounded-2xl border border-slate-200/80 hover:border-[#10A760] p-4 flex flex-col justify-between gap-3.5 card-hover shadow-xs">
                                <div class="space-y-3">
                                    <!-- Product Header (Image + Badges + Highlighted Title) -->
                                    <div class="flex items-start gap-3">
                                        <img
                                            :src="item.image_url || fallbackStorePhoto(item.store.id)"
                                            :alt="item.name"
                                            x-on:error="onImgError($event, item.store.id)"
                                            class="w-20 h-20 rounded-xl object-cover border border-slate-200 bg-white shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center flex-wrap gap-1.5 mb-1.5">
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800" x-text="item.category"></span>
                                                <template x-if="item.highlight_badge">
                                                    <span
                                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                                        :class="productBadgeClass(item.highlight_badge)"
                                                        x-text="item.highlight_badge"
                                                    ></span>
                                                </template>
                                            </div>
                                            <h3 class="font-bold text-base text-slate-900 leading-snug line-clamp-2" x-html="highlightText(item.name)"></h3>
                                        </div>
                                    </div>

                                    <!-- Prominent Price Row -->
                                    <div class="flex items-baseline gap-1.5 pt-0.5">
                                        <span class="text-2xl font-extrabold text-emerald-700" x-text="item.formatted_price"></span>
                                        <span class="text-xs font-medium text-slate-500" x-text="'/' + item.unit"></span>
                                    </div>

                                    <!-- Compact Seller Strip -->
                                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 space-y-1 text-xs">
                                        <div class="font-bold text-slate-800 flex items-center justify-between gap-2">
                                            <a :href="item.actions.detail_url" class="truncate hover:text-[#10A760] transition-colors" x-text="item.store.name"></a>
                                            <span class="text-amber-500 font-black shrink-0 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-star text-[10px]"></i>
                                                <span x-text="Number(item.store.rating || 4.5).toFixed(1)"></span>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2 text-[11px]">
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                                                :class="item.store.is_open_now ? 'bg-emerald-100 text-emerald-800' : 'bg-[#DC2626] text-white'"
                                                x-text="item.store.is_open_now ? 'Buka' : 'Tutup'"
                                            ></span>
                                            <span class="font-semibold text-emerald-700 truncate flex items-center gap-1">
                                                <i class="fa-solid fa-person-walking text-[#10A760]"></i>
                                                <span x-text="formatCompactDistance(item.store.distance_meters, item.store.walk_minutes)"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Product Card Footer -->
                                <div class="flex items-center gap-2">
                                    <a
                                        :href="item.actions.order_url"
                                        class="flex-1 py-2.5 px-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold rounded-xl text-center transition-colors inline-flex items-center justify-center gap-1.5 shadow-xs"
                                    >
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        <span>Pesan di Toko Ini</span>
                                    </a>
                                    <a
                                        :href="item.actions.route_url"
                                        class="py-2.5 px-3.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-xs font-bold rounded-xl text-center transition-colors inline-flex items-center justify-center gap-1.5 shrink-0"
                                        title="Arahkan Rute di Peta Kampus"
                                    >
                                        <i class="fa-solid fa-route"></i>
                                        <span>Rute Peta</span>
                                    </a>
                                </div>
                            </article>
                        </template>
                    </div>

                    <!-- VIEW 3: Store-Grouped Combo Cards (Mode 'mixed' / Tab 'Terpadu') -->
                    <div
                        id="adaptive-mixed-grid"
                        x-show="activeTab === 'mixed' && mixedGroups.length > 0"
                        x-cloak
                        class="grid grid-cols-1 lg:grid-cols-2 gap-5"
                    >
                        <template x-for="grp in mixedGroups" :key="'mix-' + grp.store.id">
                            <article class="bg-white rounded-2xl border border-slate-200/80 hover:border-[#10A760] overflow-hidden card-hover shadow-xs flex flex-col justify-between">
                                <!-- Top Layer: Store & Location Header -->
                                <div class="p-4 bg-emerald-50/40 border-b border-emerald-100/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <img
                                            :src="grp.store.photo_url || fallbackStorePhoto(grp.store.id)"
                                            :alt="grp.store.name"
                                            x-on:error="onImgError($event, grp.store.id)"
                                            class="w-14 h-14 rounded-xl object-cover border border-slate-200 bg-white shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <div class="flex items-center flex-wrap gap-1.5">
                                                <h3 class="text-base font-bold text-slate-900 leading-snug" x-html="highlightText(grp.store.name)"></h3>
                                                <span class="text-amber-500 text-xs font-black inline-flex items-center gap-0.5 shrink-0">
                                                    <i class="fa-solid fa-star text-[10px]"></i>
                                                    <span x-text="Number(grp.store.rating || 4.5).toFixed(1)"></span>
                                                </span>
                                                <span
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                                                    :class="grp.store.is_open_now ? 'bg-[#10A760] text-white' : 'bg-[#DC2626] text-white'"
                                                    x-text="grp.store.is_open_now ? 'Buka' : 'Tutup'"
                                                ></span>
                                            </div>
                                            <p class="text-xs font-semibold text-emerald-700 mt-1 flex items-center gap-1.5 flex-wrap">
                                                <i class="fa-solid fa-location-dot text-[#10A760] shrink-0"></i>
                                                <span x-text="'~' + grp.store.distance_m + ' m dari ' + grp.store.anchor_label + ' • Mulai ' + grp.formatted_lowest_price"></span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <a
                                            :href="grp.store.map_route_url"
                                            class="px-2.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-[11px] font-bold inline-flex items-center gap-1 transition-colors"
                                        >
                                            <i class="fa-solid fa-route"></i>
                                            <span>Rute Peta</span>
                                        </a>
                                        <a
                                            :href="grp.store.detail_url"
                                            class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-emerald-50 border border-emerald-300 text-[#0D8A4E] text-[11px] font-bold inline-flex items-center gap-1 transition-colors"
                                        >
                                            <span>Lihat Toko</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Bottom Layer: Up to 3 Matched Products/Services -->
                                <div class="p-4 pt-2 pb-3 flex-1 flex flex-col justify-between">
                                    <div class="divide-y divide-slate-100">
                                        <template x-for="mItem in grp.matched_items.slice(0, 3)" :key="'mix-item-' + grp.store.id + '-' + mItem.item_id">
                                            <div class="py-2.5 flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <img
                                                        :src="mItem.image_url || fallbackStorePhoto(grp.store.id)"
                                                        :alt="mItem.name"
                                                        x-on:error="onImgError($event, grp.store.id)"
                                                        class="w-11 h-11 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                                    />
                                                    <div class="min-w-0">
                                                        <div class="text-xs sm:text-sm font-bold text-slate-900 leading-snug line-clamp-1" x-html="highlightText(mItem.name)"></div>
                                                        <div class="flex items-center gap-1.5 mt-0.5">
                                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold" x-text="mItem.category"></span>
                                                            <span class="text-xs font-extrabold text-emerald-700" x-text="mItem.formatted_price + '/' + mItem.unit"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <a
                                                    :href="grp.store.order_url"
                                                    class="px-3 py-1.5 rounded-lg bg-[#10A760] hover:bg-[#0D8A4E] text-white text-[11px] font-bold transition-colors shrink-0 inline-flex items-center gap-1"
                                                >
                                                    <span>Pesan</span>
                                                    <span aria-hidden="true">→</span>
                                                </a>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="grp.total_matched_items > 3">
                                        <div class="pt-2 border-t border-slate-100 text-right">
                                            <a
                                                :href="grp.store.detail_url"
                                                class="text-xs font-bold text-[#0D8A4E] hover:underline"
                                                x-text="'+ ' + (grp.total_matched_items - 3) + ' item cocok lainnya di toko ini →'"
                                            ></a>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </template>
                    </div>

                    <!-- Unified Empty State -->
                    <div
                        id="adaptive-empty-state"
                        x-show="!isLoading && activeTabCount() === 0"
                        x-cloak
                        class="bg-slate-50 rounded-2xl border border-dashed border-slate-300 p-8 text-center my-2"
                    >
                        <div class="w-14 h-14 bg-white rounded-full border border-slate-200 flex items-center justify-center mx-auto mb-3 text-slate-400 text-xl">
                            <i class="fa-solid fa-store-slash"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1">Tidak Ditemukan Hasil yang Cocok</h3>
                        <p class="text-xs text-slate-500 mb-4">Coba ubah kata kunci, matikan filter Buka Sekarang, atau hapus batas harga maksimal.</p>
                        <button
                            type="button"
                            @click="resetAllFilters()"
                            class="inline-block bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-bold px-4 py-2 rounded-xl cursor-pointer transition-colors"
                        >
                            Reset Filter & Lihat Semua Toko
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @php
        $smartBootPayload = [
            'initialQuery' => $search ?? '',
            'initialStatus' => $filter ?? '',
            'initialSort' => $initialSort ?? 'recommended',
            'initialMaxPrice' => $initialMaxPrice,
            'campusOpenCount' => $openCount ?? 0,
            'totalActiveCount' => $totalActiveCount ?? $totalCount,
            'initialSmartResult' => $smartInitialResult ?? null,
        ];
    @endphp
    <script id="usu-fotocopy-smart-boot" type="application/json">@json($smartBootPayload)</script>
    <script>
        (function () {
            const USU_CENTER = { lat: 3.5612, lng: 98.6565 };
            const MAX_GPS_AGE_MS = 300000;
            const STORE_FALLBACKS = [
                '/images/stores/preset-modern-copy.webp',
                '/images/stores/preset-campus-press.webp',
                '/images/stores/preset-atk-stationery.webp',
            ];

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function buildHighlightedHtml(rawText, terms) {
                const text = String(rawText ?? '');
                if (!text || !Array.isArray(terms) || terms.length === 0) {
                    return escapeHtml(text);
                }
                const cleanTerms = terms
                    .map((t) => String(t ?? '').trim())
                    .filter((t) => t.length >= 2)
                    .sort((a, b) => b.length - a.length);
                if (cleanTerms.length === 0) {
                    return escapeHtml(text);
                }

                const escapedPatterns = cleanTerms.map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
                const regex = new RegExp('(' + escapedPatterns.join('|') + ')', 'gi');
                let out = '';
                let lastIdx = 0;
                let m;
                while ((m = regex.exec(text)) !== null) {
                    if (m.index > lastIdx) {
                        out += escapeHtml(text.slice(lastIdx, m.index));
                    }
                    out += '<mark class="bg-amber-100 text-amber-950 px-1 rounded">' + escapeHtml(m[0]) + '</mark>';
                    lastIdx = m.index + m[0].length;
                    if (m[0].length === 0) {
                        regex.lastIndex++;
                    }
                }
                if (lastIdx < text.length) {
                    out += escapeHtml(text.slice(lastIdx));
                }
                return out;
            }

            function haversineMeters(lat1, lng1, lat2, lng2) {
                const R = 6371000;
                const toRad = (deg) => (deg * Math.PI) / 180;
                const dLat = toRad(lat2 - lat1);
                const dLng = toRad(lng2 - lng1);
                const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
            }

            function readCachedGps() {
                try {
                    for (const key of ['usu_last_gps', 'usu_last_gps_fix']) {
                        const raw = sessionStorage.getItem(key);
                        if (!raw) continue;
                        const parsed = JSON.parse(raw);
                        if (parsed && Number.isFinite(Number(parsed.lat)) && Number.isFinite(Number(parsed.lng))) {
                            const age = Date.now() - Number(parsed.timestamp || 0);
                            if (age < MAX_GPS_AGE_MS) {
                                return {
                                    lat: Number(parsed.lat),
                                    lng: Number(parsed.lng),
                                    accuracy: Number(parsed.accuracy || 30),
                                    timestamp: Number(parsed.timestamp || Date.now()),
                                };
                            }
                        }
                    }
                } catch (e) {}
                return null;
            }

            function saveCachedGps(fix) {
                try {
                    const serialized = JSON.stringify({
                        lat: Number(fix.lat),
                        lng: Number(fix.lng),
                        accuracy: Math.round(Number(fix.accuracy || 30)),
                        timestamp: Number(fix.timestamp || Date.now()),
                    });
                    sessionStorage.setItem('usu_last_gps', serialized);
                    sessionStorage.setItem('usu_last_gps_fix', serialized);
                } catch (e) {}
            }

            function getWibNowParts() {
                const dtf = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Jakarta',
                    weekday: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                });
                const parts = {};
                dtf.formatToParts(new Date()).forEach((p) => { parts[p.type] = p.value; });
                const wdMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
                const hour = Number(parts.hour || 0) % 24;
                const minute = Number(parts.minute || 0);
                return {
                    dayIndex: wdMap[parts.weekday] ?? 1,
                    hour,
                    minute,
                    minutesNow: (hour * 60) + minute,
                    hhmm: String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0'),
                };
            }

            function toMin(hhmm) {
                const p = String(hhmm || '08:00').replace('.', ':').split(':');
                return ((Number(p[0] || 8)) * 60) + Number(p[1] || 0);
            }

            function isOperatingDay(daysStr, dayIdx) {
                const s = String(daysStr || 'Senin - Sabtu').toLowerCase().trim();
                if (!s || s.includes('setiap hari') || s.includes('senin - minggu') || s.includes('senin-minggu') || s.includes('24 jam')) return true;
                if (s.includes('senin - jumat') || s.includes('senin-jumat')) return dayIdx >= 1 && dayIdx <= 5;
                if (s.includes('senin - sabtu') || s.includes('senin-sabtu')) return dayIdx !== 0;
                return dayIdx !== 0;
            }

            function evaluateStoreWib(openTime, closeTime, operatingDays, isActive, wib) {
                if (!isActive) {
                    return { isOpen: false, label: 'Tutup' };
                }
                const dayOk = isOperatingDay(operatingDays, wib.dayIndex);
                const openMin = toMin(openTime);
                const closeMin = toMin(closeTime);
                const isOpen = dayOk && (closeMin < openMin
                    ? (wib.minutesNow >= openMin || wib.minutesNow < closeMin)
                    : (wib.minutesNow >= openMin && wib.minutesNow < closeMin));
                return {
                    isOpen,
                    label: isOpen ? 'Buka Sekarang' : 'Tutup',
                };
            }

            window.fotocopyUnifiedSearch = function () {
                let boot = {
                    initialQuery: '',
                    initialStatus: '',
                    initialSort: 'recommended',
                    initialMaxPrice: null,
                    campusOpenCount: 0,
                    totalActiveCount: 33,
                    initialSmartResult: null,
                };
                try {
                    const rawBoot = document.getElementById('usu-fotocopy-smart-boot')?.textContent;
                    if (rawBoot) boot = Object.assign(boot, JSON.parse(rawBoot));
                } catch (e) {}

                let gpsWatchId = null;
                let wibTickerId = null;

                return {
                    query: boot.initialQuery || '',
                    sort: boot.initialSort || 'recommended',
                    openNow: boot.initialStatus === 'open',
                    maxPrice: boot.initialMaxPrice !== null && boot.initialMaxPrice !== undefined ? String(boot.initialMaxPrice) : '',
                    userLat: null,
                    userLng: null,
                    gpsAccuracyLabel: '',
                    gpsLoading: false,
                    gpsNotice: '',
                    isLoading: false,
                    hasSearched: false,
                    searchMode: 'store',
                    activeTab: 'store',
                    intentSummary: { mode: 'store', mode_label: 'Tempat Fotokopi', headline: '', pills: [], highlight_terms: [] },
                    stores: [],
                    results: [],
                    mixedGroups: [],
                    advisoryNote: null,
                    relaxedFallbackUsed: false,
                    anchorLabel: 'Pusat Kampus USU',
                    anchorSource: 'campus_center',
                    campusOpenCount: Number(boot.campusOpenCount || 0),
                    totalActiveCount: Number(boot.totalActiveCount || 33),
                    quickChips: [
                        'fotocopy buka sekarang',
                        'fotocopy dekat FT',
                        'fotocopy dekat Perpus',
                        'fotocopy dekat FISIP',
                        'USU Press',
                        'cari pulpen pink murah terdekat',
                        'print warna murah',
                        'jilid hardcover skripsi'
                    ],

                    get isFilterActive() {
                        return this.query.trim().length > 0
                            || this.openNow
                            || (this.maxPrice !== '' && Number(this.maxPrice) > 0);
                    },

                    init() {
                        document.querySelectorAll('[data-ssr-fallback]').forEach((el) => el.remove());

                        const cached = readCachedGps();
                        if (cached) {
                            this.applyGpsCoordinates(cached, false);
                        }

                        if (boot.initialSmartResult) {
                            this.applyApiResponse(boot.initialSmartResult, true);
                            if (this.userLat !== null && boot.initialSmartResult.anchor?.source === 'campus_center') {
                                this.runSearch();
                            }
                        } else {
                            this.runSearch();
                        }

                        this.detectGps(false);
                        this.refreshWibStatuses();
                        if (wibTickerId) clearInterval(wibTickerId);
                        wibTickerId = setInterval(() => this.refreshWibStatuses(), 30000);

                        window.addEventListener('beforeunload', () => {
                            if (gpsWatchId !== null && navigator.geolocation) {
                                try { navigator.geolocation.clearWatch(gpsWatchId); } catch (e) {}
                            }
                            if (wibTickerId) clearInterval(wibTickerId);
                        });
                    },

                    highlightText(text) {
                        const terms = this.intentSummary?.highlight_terms || [];
                        return buildHighlightedHtml(text, terms);
                    },

                    fallbackStorePhoto(storeId) {
                        return STORE_FALLBACKS[Math.abs(Number(storeId || 0)) % STORE_FALLBACKS.length];
                    },

                    onImgError(evt, storeId) {
                        const img = evt && evt.target;
                        if (!img || img.dataset.fallbackApplied === '1') return;
                        img.dataset.fallbackApplied = '1';
                        img.src = this.fallbackStorePhoto(storeId);
                    },

                    modeIconClass() {
                        if (this.activeTab === 'mixed') return 'fa-layer-group';
                        if (this.activeTab === 'product') return 'fa-bag-shopping';
                        return 'fa-store';
                    },

                    intentHeadline() {
                        if (this.intentSummary && this.intentSummary.headline) {
                            return this.intentSummary.headline;
                        }
                        return 'Daftar Mitra Fotocopy & Percetakan Kampus USU';
                    },

                    intentPillClass(type) {
                        if (type === 'location') return 'bg-sky-100 text-sky-800 border-sky-200';
                        if (type === 'product') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
                        if (type === 'filter') return 'bg-amber-100 text-amber-800 border-amber-200';
                        return 'bg-white text-slate-700 border-emerald-200';
                    },

                    productBadgeClass(badge) {
                        if (badge === 'Termurah') return 'bg-emerald-600 text-white';
                        if (badge === 'Terdekat') return 'bg-blue-600 text-white';
                        if (badge === 'Paling Cocok') return 'bg-amber-500 text-white';
                        return 'bg-slate-200 text-slate-700';
                    },

                    activeTabCount() {
                        if (this.activeTab === 'mixed') return this.mixedGroups.length;
                        if (this.activeTab === 'product') return this.results.length;
                        return this.stores.length;
                    },

                    activeFilterCountLabel() {
                        if (this.searchMode === 'mixed') return this.mixedGroups.length;
                        if (this.searchMode === 'product') return this.results.length;
                        return this.stores.length;
                    },

                    setTab(tab) {
                        this.activeTab = tab;
                    },

                    setStatusFilter(onlyOpen) {
                        this.openNow = Boolean(onlyOpen);
                        this.runSearch();
                    },

                    formatStoreDistanceLine(store) {
                        const m = Math.round(Number(store.distance_m ?? store.distance_meters ?? 0));
                        const mins = Math.max(1, Number(store.walking_minutes ?? store.walk_minutes ?? Math.ceil(m / 75)));
                        const distStr = m >= 1000 ? ((m / 1000).toFixed(2) + ' km') : (m + ' m');
                        const label = store.anchor_label || this.anchorLabel || 'Pusat Kampus USU';
                        return `~${distStr} (${mins} mnt jalan) dari ${label}`;
                    },

                    formatCompactDistance(meters, walkMins) {
                        const m = Math.round(Number(meters || 0));
                        const distStr = m >= 1000 ? ((m / 1000).toFixed(2) + ' km') : (m + ' m');
                        return `~${distStr} • ${walkMins} mnt jalan`;
                    },

                    syncBrowserUrl() {
                        try {
                            const url = new URL(window.location.href);
                            const q = this.query.trim();
                            if (q) url.searchParams.set('q', q);
                            else url.searchParams.delete('q');

                            if (this.openNow) url.searchParams.set('status', 'open');
                            else url.searchParams.delete('status');

                            if (this.sort && this.sort !== 'recommended') url.searchParams.set('sort', this.sort);
                            else url.searchParams.delete('sort');

                            if (this.maxPrice && Number(this.maxPrice) > 0) url.searchParams.set('max_price', String(this.maxPrice));
                            else url.searchParams.delete('max_price');

                            const nextSearch = url.searchParams.toString();
                            window.history.replaceState({}, '', url.pathname + (nextSearch ? ('?' + nextSearch) : '') + url.hash);
                        } catch (e) {}
                    },

                    applyApiResponse(data, autoSwitchTab = true) {
                        this.hasSearched = true;
                        this.searchMode = data.search_mode || 'store';
                        this.intentSummary = data.intent_summary || {
                            mode: this.searchMode,
                            mode_label: 'Tempat Fotokopi',
                            headline: '',
                            pills: [],
                            highlight_terms: [],
                        };
                        this.stores = Array.isArray(data.stores) ? data.stores : [];
                        this.results = Array.isArray(data.items) ? data.items : (Array.isArray(data.results) ? data.results : []);
                        this.mixedGroups = Array.isArray(data.mixed_groups) ? data.mixed_groups : [];
                        this.advisoryNote = data.advisory_note || null;
                        this.relaxedFallbackUsed = !!data.relaxed_fallback_used;
                        this.anchorLabel = data.anchor?.label || 'Pusat Kampus USU';
                        this.anchorSource = data.anchor?.source || data.anchor_type || 'campus_center';

                        if (autoSwitchTab) {
                            this.activeTab = this.searchMode;
                        }
                        this.refreshWibStatuses();
                    },

                    applyChip(text) {
                        this.query = text;
                        this.runSearch();
                    },

                    clearSearch() {
                        this.query = '';
                        this.runSearch();
                    },

                    resetAllFilters() {
                        this.query = '';
                        this.openNow = false;
                        this.maxPrice = '';
                        this.sort = 'recommended';
                        this.runSearch();
                    },

                    refreshClientGpsDistances() {
                        if (this.userLat === null || this.userLng === null || this.anchorSource === 'landmark') {
                            return;
                        }
                        this.anchorLabel = 'Lokasi GPS Anda';
                        const updateStoreMetrics = (st) => {
                            if (!st) return;
                            const lat = parseFloat(st.latitude);
                            const lng = parseFloat(st.longitude);
                            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                            const dist = Math.round(haversineMeters(this.userLat, this.userLng, lat, lng));
                            const mins = Math.max(1, Math.ceil(dist / 75));
                            st.distance_m = dist;
                            st.distance_meters = dist;
                            st.walking_minutes = mins;
                            st.walk_minutes = mins;
                            st.anchor_label = 'Lokasi GPS Anda';
                        };

                        this.stores.forEach(updateStoreMetrics);
                        this.results.forEach((it) => updateStoreMetrics(it.store));
                        this.mixedGroups.forEach((g) => updateStoreMetrics(g.store));
                    },

                    refreshWibStatuses() {
                        const wib = getWibNowParts();
                        const clockEl = document.getElementById('live-wib-clock');
                        if (clockEl) clockEl.textContent = wib.hhmm;

                        const syncStoreStatus = (st) => {
                            if (!st) return false;
                            const ev = evaluateStoreWib(
                                st.open_time || '08:00',
                                st.close_time || '21:00',
                                st.operating_days || 'Senin - Sabtu',
                                st.is_active !== false,
                                wib
                            );
                            st.is_open_now = ev.isOpen;
                            st.status_label = ev.label;
                            return ev.isOpen;
                        };

                        let openInStores = 0;
                        this.stores.forEach((st) => {
                            if (syncStoreStatus(st)) openInStores++;
                        });
                        this.results.forEach((it) => syncStoreStatus(it.store));
                        this.mixedGroups.forEach((g) => syncStoreStatus(g.store));

                        if (!this.isFilterActive && this.stores.length >= this.totalActiveCount) {
                            this.campusOpenCount = openInStores;
                        }
                    },

                    applyGpsCoordinates(fix, triggerSearch = true) {
                        const prevLat = this.userLat;
                        const prevLng = this.userLng;
                        this.userLat = Number(fix.lat);
                        this.userLng = Number(fix.lng);
                        const acc = Math.round(Number(fix.accuracy || 30));
                        this.gpsAccuracyLabel = '±' + acc + ' m';
                        this.gpsNotice = '';
                        this.gpsLoading = false;

                        saveCachedGps(fix);
                        this.refreshClientGpsDistances();

                        const movedEnough = prevLat === null || haversineMeters(prevLat, prevLng, this.userLat, this.userLng) >= 15;
                        if (triggerSearch && this.anchorSource !== 'landmark' && (movedEnough || this.anchorSource !== 'gps')) {
                            this.runSearch();
                        }
                    },

                    async runSearch() {
                        this.isLoading = true;
                        try {
                            const params = new URLSearchParams({
                                q: this.query.trim(),
                                sort: this.sort || 'recommended',
                                limit: '18',
                            });
                            if (this.openNow) params.set('open_now', '1');
                            if (this.maxPrice && Number(this.maxPrice) > 0) params.set('max_price', String(this.maxPrice));
                            if (this.userLat !== null && this.userLng !== null) {
                                params.set('lat', String(this.userLat));
                                params.set('lng', String(this.userLng));
                            }

                            const resp = await fetch('/api/fotocopy/smart-search?' + params.toString(), {
                                headers: { Accept: 'application/json' },
                            });
                            const data = await resp.json();
                            this.applyApiResponse(data, true);
                            this.syncBrowserUrl();
                        } catch (err) {
                            console.error('Adaptive fotocopy search failed:', err);
                            this.advisoryNote = 'Gagal menghubungi layanan Smart Search. Silakan coba lagi.';
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    startLightWatchPosition() {
                        if (gpsWatchId !== null || !navigator.geolocation || typeof navigator.geolocation.watchPosition !== 'function') {
                            return;
                        }
                        gpsWatchId = navigator.geolocation.watchPosition(
                            (pos) => {
                                const lat = Number(pos.coords.latitude);
                                const lng = Number(pos.coords.longitude);
                                if (this.userLat !== null && haversineMeters(this.userLat, this.userLng, lat, lng) < 15) {
                                    return;
                                }
                                this.applyGpsCoordinates({
                                    lat,
                                    lng,
                                    accuracy: pos.coords.accuracy || 30,
                                    timestamp: Date.now(),
                                }, true);
                            },
                            () => {},
                            { enableHighAccuracy: false, maximumAge: 30000, timeout: 10000 }
                        );
                    },

                    detectGps(isManual = false) {
                        if (!window.isSecureContext || !navigator.geolocation) {
                            if (this.userLat === null) {
                                this.gpsNotice = 'GPS browser tidak tersedia di konteks ini — jarak sementara mengacu ke Pusat Kampus USU.';
                            }
                            return;
                        }
                        this.gpsLoading = true;
                        if (isManual) this.gpsNotice = '';

                        const onSuccess = (pos) => {
                            this.applyGpsCoordinates({
                                lat: Number(pos.coords.latitude),
                                lng: Number(pos.coords.longitude),
                                accuracy: pos.coords.accuracy || 30,
                                timestamp: Date.now(),
                            }, true);
                            this.startLightWatchPosition();
                        };

                        const onFail = () => {
                            this.gpsLoading = false;
                            if (this.userLat === null) {
                                this.gpsNotice = 'Izin GPS belum aktif — jarak sementara mengacu ke Pusat Kampus USU. Aktifkan Lokasi untuk jarak real-time dari posisi Anda.';
                            }
                        };

                        navigator.geolocation.getCurrentPosition(
                            onSuccess,
                            () => {
                                navigator.geolocation.getCurrentPosition(
                                    onSuccess,
                                    onFail,
                                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
                                );
                            },
                            { enableHighAccuracy: true, timeout: 6000, maximumAge: 30000 }
                        );
                    },
                };
            };

            window.usuFotocopySmartSearch = window.fotocopyUnifiedSearch;
        })();
    </script>
</x-layouts.app>
