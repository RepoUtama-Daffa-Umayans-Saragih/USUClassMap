<x-layouts.app>
    <x-slot name="title">Detail {{ $place->name }} - Layanan Fotocopy USU</x-slot>

    @push('meta')
        <!-- Open Graph Meta Tags for Rich Social Sharing (WhatsApp, IG, FB, X) -->
        <meta property="og:title" content="{{ $place->name }} - Layanan Fotocopy & Percetakan Kampus USU">
        <meta property="og:description" content="Rating ⭐{{ number_format($place->rating, 1) }} ({{ $place->user_rating_count }} ulasan) • {{ $place->current_schedule_status }} • {{ $place->location_name }}">
        <meta property="og:url" content="{{ route('fotocopy.show', $place->id) }}">
        <meta property="og:type" content="business.business">
        <meta property="og:image" content="{{ $place->image_url ?: 'https://images.unsplash.com/photo-1568667256549-094345857637?w=800&auto=format&fit=crop&q=80' }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $place->name }} - Layanan Fotocopy USU">
        <meta name="twitter:description" content="{{ $place->current_schedule_status }} • {{ $place->location_name }}">
    @endpush

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
            <div class="relative h-64 sm:h-72 md:h-80 flex items-center justify-center text-white p-4 overflow-hidden">
                @if($place->image_url)
                    <img src="{{ $place->image_url }}" alt="{{ $place->name }}" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/60 to-black/35"></div>
                @else
                    <div class="absolute inset-0 bg-gradient-to-r from-[#10A760] to-[#087A3F]"></div>
                    <div class="absolute inset-0 bg-black/25"></div>
                @endif
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

                    <!-- Real-Time Rating Badge -->
                    <div class="flex items-center justify-center gap-2 mt-2">
                        <span class="inline-flex items-center gap-1 bg-black/30 backdrop-blur-sm text-amber-300 font-extrabold text-xs px-2.5 py-0.5 rounded-full">
                            <i class="fa-solid fa-star text-[10px]"></i>
                            <span>{{ number_format($place->rating, 1) }}</span>
                            <span class="text-white/80 font-normal">({{ $place->user_rating_count }} ulasan Google)</span>
                        </span>
                    </div>
                </div>

                <!-- Status Badge Real-Time Google Maps Style -->
                <div class="absolute top-3 right-3 sm:top-4 sm:right-4 z-10">
                    <span class="{{ $place->is_open_now ? 'bg-[#10A760]' : 'bg-gray-800' }} text-white text-[11px] sm:text-xs font-bold px-3 py-1 rounded-full shadow-md flex items-center gap-1.5">
                        <span class="w-2 h-2 {{ $place->is_open_now ? 'bg-white animate-pulse' : 'bg-gray-400' }} rounded-full"></span>
                        <span>{{ $place->current_schedule_status }}</span>
                    </span>
                </div>
            </div>

            <!-- Detail Information Body -->
            <div class="p-4 sm:p-6 md:p-8 space-y-6 sm:space-y-8">

                <!-- Quick Action Bar: Pesan, Pergi ke Maps, dan Bagikan (Share) -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-[#E8F5EE] rounded-2xl border border-[#10A760]/20">
                    <div class="text-center sm:text-left">
                        <div class="text-xs sm:text-sm font-bold text-[#0D8A4E]">Siap mencetak tugas atau fotocopy berkas?</div>
                        <div class="text-xs text-gray-600 mt-0.5">Pesan langsung secara online, navigasi via Maps, atau bagikan info ini ke teman kelas.</div>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Button Pesan -->
                        <a
                            href="{{ route('fotocopy.order', $place->id) }}"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs sm:text-sm px-4 sm:px-5 py-2.5 rounded-xl shadow-xs transition-all"
                        >
                            <i class="fa-solid fa-cart-shopping text-xs"></i>
                            <span>Pesan Sekarang</span>
                        </a>

                        <!-- Button Lokasi (Pergi ke Maps) -->
                        <a
                            href="{{ $place->getDirectionUrl() }}"
                            target="_blank"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-[#D4A017] hover:bg-[#B8880F] text-white font-bold text-xs sm:text-sm px-3.5 sm:px-4 py-2.5 rounded-xl shadow-xs transition-all"
                        >
                            <i class="fa-solid fa-location-dot text-xs"></i>
                            <span>Pergi ke Maps</span>
                        </a>

                        <!-- Button Bagikan (Web Share API) -->
                        <button
                            type="button"
                            onclick="sharePlace('{{ addslashes($place->name) }}', '{{ addslashes($place->current_schedule_status . ' • ' . $place->location_name) }}', '{{ route('fotocopy.show', $place->id) }}')"
                            class="inline-flex items-center justify-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold text-xs sm:text-sm px-3 py-2.5 rounded-xl shadow-xs transition-all cursor-pointer"
                            title="Bagikan ke WhatsApp / Media Sosial"
                        >
                            <i class="fa-solid fa-share-nodes text-xs text-[#10A760]"></i>
                            <span class="hidden sm:inline">Bagikan</span>
                        </button>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">

                    <!-- Kolom 1: Jadwal Buka dan Tutup Real-time -->
                    <div class="bg-gray-50 rounded-2xl p-4 sm:p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-regular fa-clock text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm">Jadwal Buka & Tutup Real-time</h3>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Status Saat Ini:</span>
                                <span class="font-bold {{ $place->is_open_now ? 'text-[#10A760]' : 'text-red-500' }}">
                                    {{ $place->current_schedule_status }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Jam Operasional:</span>
                                <span class="font-bold text-gray-800 text-sm">{{ $place->open_time }} - {{ $place->close_time }} WIB</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-gray-200">
                                <span class="text-gray-500">Hari Beroperasi:</span>
                                <span class="font-bold text-gray-800">{{ $place->operating_days }}</span>
                            </div>
                            @if($place->last_synced_at)
                            <div class="flex items-center justify-between py-1.5 text-[11px] text-gray-400">
                                <span>Terakhir Disinkronkan:</span>
                                <span>{{ $place->last_synced_at->diffForHumans() }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Kolom 2: Kontak Person & WhatsApp Dummy Anda -->
                    <div class="bg-gray-50 rounded-2xl p-4 sm:p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-address-book text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm">Kontak Resmi & Bantuan</h3>
                        </div>

                        <div class="space-y-2.5">
                            <!-- WhatsApp -->
                            <a
                                href="{{ $place->getWhatsappUrl('Halo *' . $place->name . '*, saya mahasiswa USU ingin bertanya mengenai ketersediaan layanan fotokopi/print melalui USUStarterMap.') }}"
                                target="_blank"
                                class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200 hover:border-[#25D366] hover:bg-green-50/50 transition-all group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center text-sm font-bold shadow-xs">
                                        <i class="fa-brands fa-whatsapp text-base"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900 group-hover:text-[#128C7E]">Nomor WhatsApp</div>
                                        <div class="text-[11px] text-gray-500">{{ \App\Support\WhatsAppPhoneNormalizer::formatDisplay($place->whatsapp ?: $place->phone) }}</div>
                                    </div>
                                </div>
                                <span class="text-[11px] font-bold text-[#128C7E] bg-green-100 px-2.5 py-1 rounded-lg flex items-center gap-1">
                                    <span>Chat WA</span>
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
                        </div>
                    </div>

                </div>

                <!-- Layanan Unggulan Terdeteksi (Master Services & NLP) -->
                @if($place->services && $place->services->count() > 0)
                <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5">
                    <h3 class="font-extrabold text-gray-900 text-sm mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-tags text-[#10A760]"></i>
                        <span>Layanan Unggulan & Fitur Usaha</span>
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($place->services as $srv)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#E8F5EE] border border-[#10A760]/30 rounded-xl text-xs font-bold text-[#0D8A4E]">
                            <i class="{{ $srv->icon }} text-xs"></i>
                            <span>{{ $srv->name }}</span>
                            @if($srv->pivot->starting_price)
                                <span class="text-[10px] bg-white px-1.5 py-0.5 rounded-md text-[#10A760]">Rp {{ number_format($srv->pivot->starting_price, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

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

                <!-- Daftar Layanan & Harga Katalog -->
                @include('features.fotocopy.partials.catalog-filter-script')
                <script>
                    window.usuFotocopyCatalog = {!! json_encode($catalogItems ?? [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!};
                </script>
                <div x-data="usuCatalogFilter(window.usuFotocopyCatalog)" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-gray-900 text-sm sm:text-base flex items-center gap-2">
                            <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                            <span>Katalog Layanan & Produk ATK</span>
                        </h3>
                        <a href="{{ route('fotocopy.order', $place->id) }}" class="text-xs font-bold text-[#10A760] hover:underline flex items-center gap-1">
                            <span>Pesan Sekarang</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    @include('features.fotocopy.partials.catalog-filter-bar')

                    <template x-if="catalogFiltered().length === 0">
                        <div class="bg-gray-50 rounded-2xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-500">
                            <i class="fa-solid fa-magnifying-glass text-2xl text-gray-300 mb-2 block"></i>
                            Tidak ada item katalog yang cocok dengan filter saat ini.
                        </div>
                    </template>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5" x-show="catalogFiltered().length > 0">
                        <template x-for="item in catalogFiltered()" :key="item.id">
                            <div class="bg-white rounded-2xl p-3.5 border border-gray-200/75 hover:border-[#10A760] transition flex flex-col justify-between gap-2.5 shadow-2xs">
                                <div class="flex items-start gap-3">
                                    <div class="w-16 h-16 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                                        <template x-if="item.image_url && !item._imgError">
                                            <img
                                                :src="item.image_url"
                                                :alt="item.name"
                                                loading="lazy"
                                                x-on:error="item._imgError = true"
                                                class="w-full h-full object-contain p-1"
                                            >
                                        </template>
                                        <template x-if="!item.image_url || item._imgError">
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fa-solid text-lg" :class="item.item_type === 'barang' ? 'fa-box-open text-sky-500' : 'fa-print text-emerald-500'"></i>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-1 mb-1">
                                            <span
                                                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                :class="item.item_type === 'barang' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                                x-text="item.type_label"
                                            ></span>
                                            <template x-if="item.brand">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200" x-text="item.brand"></span>
                                            </template>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600" x-text="item.pack_label"></span>
                                        </div>

                                        <div class="font-bold text-gray-900 text-xs sm:text-sm leading-snug" x-text="item.name"></div>
                                        <div class="text-[10px] text-emerald-700 font-semibold mt-0.5" x-show="item.sub_category" x-text="item.sub_category"></div>
                                        <p class="text-[11px] text-gray-500 line-clamp-2 mt-1" x-show="item.description" x-text="item.description"></p>
                                        <p class="text-[10px] text-gray-400 mt-1 truncate" x-show="metadataSummary(item.metadata)" x-text="metadataSummary(item.metadata)"></p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-gray-100 text-xs">
                                    <div>
                                        <span class="font-black text-[#10A760] text-sm" x-text="item.formatted_price || rupiah(item.price)"></span>
                                        <span class="text-[10px] text-gray-400" x-text="'/ ' + item.unit"></span>
                                        <template x-if="item.item_type === 'barang' && item.stock !== null">
                                            <span
                                                class="text-[10px] font-semibold ml-2"
                                                :class="item.stock <= 5 ? 'text-red-600' : 'text-gray-400'"
                                                x-text="'Stok: ' + item.stock"
                                            ></span>
                                        </template>
                                    </div>
                                    <a
                                        href="{{ route('fotocopy.order', $place->id) }}"
                                        class="text-[11px] font-bold text-[#0D8A4E] bg-[#E8F5EE] hover:bg-[#10A760] hover:text-white px-2.5 py-1 rounded-lg transition"
                                    >
                                        Pesan
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Cuplikan Ulasan Pelanggan Google Maps -->
                @if($place->reviews && $place->reviews->count() > 0)
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-extrabold text-gray-900 text-sm sm:text-base flex items-center gap-2">
                            <i class="fa-solid fa-comments text-[#10A760]"></i>
                            <span>Ulasan Pengunjung Google Maps</span>
                        </h3>
                        <span class="text-xs text-gray-400">{{ $place->reviews->count() }} ulasan disinkronkan</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($place->reviews as $rev)
                        <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[#10A760]/20 text-[#10A760] font-bold text-xs flex items-center justify-center">
                                        {{ substr($rev->author_name, 0, 1) }}
                                    </div>
                                    <span class="font-bold text-xs text-gray-800">{{ $rev->author_name }}</span>
                                </div>
                                <div class="text-amber-400 text-xs">
                                    @for($i = 0; $i < $rev->rating; $i++)
                                        <i class="fa-solid fa-star text-[10px]"></i>
                                    @endfor
                                </div>
                            </div>
                            <p class="text-xs text-gray-600 italic leading-relaxed">
                                "{{ $rev->text }}"
                            </p>
                            @if($rev->relative_publish_time)
                            <div class="text-[10px] text-gray-400 text-right">
                                {{ $rev->relative_publish_time }}
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

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

    <!-- Web Share API Script & Toast Notification -->
    <script>
        function sharePlace(title, text, url) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: text,
                    url: url
                }).catch(err => {
                    console.log('Error sharing:', err);
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Tautan telah disalin ke clipboard!');
                }).catch(() => {
                    prompt('Salin tautan ini:', url);
                });
            }
        }

        function showToast(message) {
            let toast = document.createElement('div');
            toast.className = 'fixed bottom-5 right-5 bg-gray-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-lg z-50 transition-opacity duration-300 flex items-center gap-2';
            toast.innerHTML = '<i class="fa-solid fa-check text-green-400"></i><span>' + message + '</span>';
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</x-layouts.app>
