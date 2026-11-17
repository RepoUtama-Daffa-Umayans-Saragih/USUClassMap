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

                        <!-- Action Buttons: Detail & Pesan -->
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
</x-layouts.app>
