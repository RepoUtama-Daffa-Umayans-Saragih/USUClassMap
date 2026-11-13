<x-layouts.app>
    <x-slot name="title">Detail {{ $place->name }} - Layanan Fotocopy USU</x-slot>

    <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center text-xs text-gray-500 gap-2 mb-2 overflow-x-auto whitespace-nowrap pb-1">
            <a href="/" class="hover:text-[#10A760]">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760]">Fotocopy</a>
            <span>/</span>
            <span class="text-gray-800 font-semibold truncate">{{ $place->name }}</span>
        </nav>

        <!-- Main Detail Card -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <!-- Hero Photo Header -->
            <div class="relative h-60 sm:h-72 md:h-80 bg-gradient-to-r from-[#10A760] to-[#087A3F] flex items-center justify-center text-white p-4">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="relative z-10 text-center max-w-xl mx-auto">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-white/20 backdrop-blur-md rounded-2xl sm:rounded-3xl flex items-center justify-center mx-auto mb-3 shadow-md">
                        <i class="fa-solid fa-print text-2xl sm:text-3xl text-white"></i>
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold bg-white/25 backdrop-blur-sm mb-2 uppercase tracking-wider">
                        Mitra Percetakan & Fotocopy Resmi USU
                    </span>
                    <h1 class="text-xl sm:text-3xl md:text-4xl font-black tracking-tight drop-shadow-sm leading-snug">
                        {{ $place->name }}
                    </h1>
                    <p class="text-xs sm:text-sm text-green-100 mt-1 flex items-center justify-center gap-1.5 px-2">
                        <i class="fa-solid fa-location-dot text-[#D4A017] text-xs"></i>
                        <span>{{ $place->location_name }}</span>
                    </p>
                </div>

                <!-- Status Badge -->
                <div class="absolute top-3 right-3 sm:top-4 sm:right-4 z-10">
                    <span class="{{ $isOpen ? 'bg-[#10A760]' : 'bg-gray-700' }} text-white text-[11px] sm:text-xs font-bold px-3 py-1 rounded-full shadow-md flex items-center gap-1.5">
                        <span class="w-2 h-2 {{ $isOpen ? 'bg-white animate-pulse' : 'bg-gray-400' }} rounded-full"></span>
                        {{ $isOpen ? 'Sedang Buka' : 'Sedang Tutup' }}
                    </span>
                </div>
            </div>

            <!-- Detail Information Body -->
            <div class="p-4 sm:p-6 md:p-8 space-y-6 sm:space-y-8">

                <!-- Quick Action Bar (Pesan & Lokasi Sejajar) -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-[#E8F5EE] rounded-2xl border border-[#10A760]/20">
                    <div class="text-center sm:text-left">
                        <div class="text-xs sm:text-sm font-bold text-[#0D8A4E]">Siap mencetak tugas atau fotocopy berkas?</div>
                        <div class="text-xs text-gray-600 mt-0.5">Pesan langsung secara online atau datangi gerai dengan panduan rute.</div>
                    </div>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto">
                        <!-- Button Pesan -->
                        <a
                            href="{{ route('fotocopy.order', $place->id) }}"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl shadow-xs transition-all"
                        >
                            <i class="fa-solid fa-cart-shopping text-xs"></i>
                            <span>Pesan Sekarang</span>
                        </a>

                        <!-- Button Lokasi (Sejajar dengan Button Pesan) -->
                        <a
                            href="{{ $place->getDirectionUrl() }}"
                            target="_blank"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-[#D4A017] hover:bg-[#B8880F] text-white font-bold text-xs sm:text-sm px-4 py-2.5 rounded-xl shadow-xs transition-all"
                        >
                            <i class="fa-solid fa-location-dot text-xs"></i>
                            <span>Pergi ke Maps</span>
                        </a>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">

                    <!-- Kolom 1: Jadwal Buka dan Tutup -->
                    <div class="bg-gray-50 rounded-2xl p-4 sm:p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-regular fa-clock text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm">Jadwal Buka & Tutup</h3>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Jam Operasional:</span>
                                <span class="font-bold text-gray-800 text-sm">{{ $place->open_time }} - {{ $place->close_time }} WIB</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Hari Beroperasi:</span>
                                <span class="font-bold text-gray-800">{{ $place->operating_days }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Status Saat Ini:</span>
                                <span class="font-bold {{ $isOpen ? 'text-[#10A760]' : 'text-red-500' }}">
                                    {{ $isOpen ? 'Buka (Menerima Pesanan)' : 'Tutup (Di luar jam buka)' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-gray-500">Rating Pengguna:</span>
                                <span class="font-bold text-[#D4A017] flex items-center gap-1">
                                    <i class="fa-solid fa-star text-xs"></i> {{ number_format($place->rating, 1) }} / 5.0
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 2: Kontak Person (WhatsApp, Instagram, Facebook dengan Pesan Otomatis) -->
                    <div class="bg-gray-50 rounded-2xl p-4 sm:p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-address-book text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm">Kontak Person & Media Sosial</h3>
                        </div>

                        <p class="text-xs text-gray-500 mb-3">
                            Klik tombol di bawah ini untuk langsung terhubung dengan pemilik toko disertai <strong>pesan otomatis</strong>:
                        </p>

                        <div class="space-y-2.5">
                            <!-- WhatsApp -->
                            <a
                                href="{{ $place->getWhatsappUrl('Halo *' . $place->name . '*, saya mahasiswa USU ingin bertanya mengenai ketersediaan layanan fotokopi/print melalui USUClassMap.') }}"
                                target="_blank"
                                class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200 hover:border-[#25D366] hover:bg-green-50/50 transition-all group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center text-sm font-bold shadow-xs">
                                        <i class="fa-brands fa-whatsapp text-base"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900 group-hover:text-[#128C7E]">Nomor WhatsApp</div>
                                        <div class="text-[11px] text-gray-500">+{{ $place->whatsapp }}</div>
                                    </div>
                                </div>
                                <span class="text-[11px] font-bold text-[#128C7E] bg-green-100 px-2.5 py-1 rounded-lg flex items-center gap-1">
                                    <span>Kirim WA</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </span>
                            </a>

                            <!-- Instagram -->
                            @if($place->instagram)
                            <a
                                href="{{ $place->getInstagramUrl() }}"
                                target="_blank"
                                class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200 hover:border-pink-500 hover:bg-pink-50/50 transition-all group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 text-white flex items-center justify-center text-sm font-bold shadow-xs">
                                        <i class="fa-brands fa-instagram text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900 group-hover:text-pink-600">Instagram</div>
                                        <div class="text-[11px] text-gray-500">{{ '@' . $place->instagram }}</div>
                                    </div>
                                </div>
                                <span class="text-[11px] font-bold text-pink-600 bg-pink-100 px-2.5 py-1 rounded-lg flex items-center gap-1">
                                    <span>Buka Profil</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </span>
                            </a>
                            @endif

                            <!-- Facebook -->
                            @if($place->facebook)
                            <a
                                href="{{ $place->getFacebookUrl() }}"
                                target="_blank"
                                class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-600 hover:bg-blue-50/50 transition-all group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold shadow-xs">
                                        <i class="fa-brands fa-facebook-f text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900 group-hover:text-blue-600">Facebook Page</div>
                                        <div class="text-[11px] text-gray-500">{{ $place->facebook }}</div>
                                    </div>
                                </div>
                                <span class="text-[11px] font-bold text-blue-600 bg-blue-100 px-2.5 py-1 rounded-lg flex items-center gap-1">
                                    <span>Kunjungi FB</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </span>
                            </a>
                            @endif
                        </div>
                    </div>

                </div>

                <!-- Deskripsi Lengkap -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4 sm:p-5">
                    <h3 class="font-extrabold text-gray-900 text-sm mb-2 flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-[#10A760]"></i>
                        <span>Tentang Tempat Fotocopy Ini</span>
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                        {{ $place->description }}
                    </p>
                </div>

                <!-- Daftar Layanan & Harga -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-extrabold text-gray-900 text-sm sm:text-base flex items-center gap-2">
                            <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                            <span>Katalog Layanan & Daftar Harga</span>
                        </h3>
                        <a href="{{ route('fotocopy.order', $place->id) }}" class="text-xs font-bold text-[#10A760] hover:underline flex items-center gap-1">
                            <span>Pesan Layanan</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($itemsByCategory as $category => $items)
                        <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                            <h4 class="font-bold text-xs text-[#0D8A4E] uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[#10A760]"></span>
                                Kategori: {{ $category }}
                            </h4>
                            <div class="space-y-2">
                                @foreach($items as $item)
                                <div class="flex items-center justify-between bg-white p-2.5 rounded-xl border border-gray-200/60 text-xs">
                                    <div>
                                        <div class="font-bold text-gray-800">{{ $item->name }}</div>
                                        @if($item->description)
                                            <div class="text-[10px] text-gray-400">{{ $item->description }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0 ml-2">
                                        <span class="font-black text-[#10A760]">{{ $item->formatted_price }}</span>
                                        <span class="text-[10px] text-gray-400 block">/ {{ $item->unit }}</span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        <!-- Tombol Kembali -->
        <div class="text-center pt-2">
            <a href="{{ route('fotocopy.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-gray-500 hover:text-[#10A760] transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali ke Daftar Fotocopy</span>
            </a>
        </div>

    </div>
</x-layouts.app>
