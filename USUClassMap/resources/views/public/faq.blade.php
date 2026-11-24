<x-layouts.app>
    <x-slot name="title">FAQ & Kontak - USUClassMap</x-slot>

    <!-- Main Container -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-10">

        <!-- Header FAQ Asli USUClassMap (Sesuai Permintaan User) -->
        <div class="text-center">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-green-100 text-[#10A760] font-bold text-xs rounded-full uppercase tracking-wider">
                <i class="fa-solid fa-circle-question text-xs"></i>
                <span>Pusat Bantuan & Informasi</span>
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-gray-900 mt-3 tracking-tight">
                Pertanyaan yang Sering Diajukan (FAQ)
            </h1>
            <p class="mt-2.5 text-xs sm:text-base text-gray-600 max-w-2xl mx-auto px-2 leading-relaxed">
                Temukan jawaban seputar fitur peta interaktif, pencarian jadwal kuliah, tur 360°, dan layanan fotocopy kampus USU.
            </p>
        </div>

        <!-- Section FAQ: Dropdown Mengikuti contohFAQ.png (Garis Minimalis, Tanda + dan —) -->
        <div>
            <div class="flex items-center gap-2 mb-4">
                <span class="w-1.5 h-6 bg-[#10A760] rounded-full"></span>
                <h2 class="text-base sm:text-xl font-bold text-gray-900">
                    Pertanyaan Seputar Aplikasi Web USUClassMap
                </h2>
            </div>

            <!-- Accordion List Minimalis & Clean (Sesuai contohFAQ.png) -->
            <div class="border-t border-gray-300" x-data="{ active: null }">
                @foreach($faqs as $index => $faq)
                <div class="border-b border-gray-200 transition-colors">
                    <button
                        type="button"
                        @click="active = active === {{ $index }} ? null : {{ $index }}"
                        class="w-full py-4.5 sm:py-5 text-left flex items-center justify-between text-gray-900 hover:text-[#10A760] transition-colors focus:outline-hidden cursor-pointer group"
                    >
                        <span class="font-bold text-sm sm:text-base pr-4 leading-snug">
                            {{ $faq['question'] }}
                        </span>

                        <!-- Tanda + dan — (Sesuai contohFAQ.png) -->
                        <span class="shrink-0 text-xl sm:text-2xl font-light text-gray-500 group-hover:text-[#10A760] w-6 text-center select-none">
                            <span x-show="active !== {{ $index }}">+</span>
                            <span x-show="active === {{ $index }}" style="display: none;">&mdash;</span>
                        </span>
                    </button>

                    <!-- Answer Body -->
                    <div
                        x-show="active === {{ $index }}"
                        x-collapse
                        class="pb-5 text-xs sm:text-sm text-gray-600 leading-relaxed font-normal"
                        style="display: none;"
                    >
                        {{ $faq['answer'] }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- FOOTER: SESUAI contohFooter.png (Disesuaikan Permintaan User)             -->
    <!-- ========================================================================= -->
    <footer class="bg-white border-t border-gray-200 mt-16 text-gray-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 sm:pt-12 pb-8">

            <!-- Main Top Grid -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-10">

                <!-- Col 1: Logo & Locations & Social Media -->
                <div class="md:col-span-5 space-y-5">
                    <!-- Brand Logo -->
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-[#10A760] text-white flex items-center justify-center font-black text-sm shadow-xs">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span class="text-xl font-extrabold tracking-tight text-gray-900">
                            USU<span class="text-[#10A760]">ClassMap</span>
                        </span>
                    </div>

                    <!-- Location 1 (Pusat Sistem Informasi) -->
                    <div class="text-xs leading-relaxed">
                        <div class="font-bold text-gray-900 text-sm mb-0.5">
                            Pusat Sistem Informasi (PSI) USU
                        </div>
                        <p class="text-gray-600">Jl. Universitas No. 9, Kampus Padang Bulan</p>
                        <p class="text-gray-600">Medan, Sumatera Utara 20155</p>
                        <p class="text-gray-600 mt-0.5">Phone: +62 812-6000-8787</p>
                    </div>

                    <!-- Location 2 (Biro Rektorat USU) -->
                    <div class="text-xs leading-relaxed">
                        <div class="font-bold text-gray-900 text-sm mb-0.5">
                            Biro Pusat Administrasi USU
                        </div>
                        <p class="text-gray-600">Gedung Biro Rektorat Kampus USU</p>
                        <p class="text-gray-600">Jl. dr. T. Mansur No. 9, Medan 20155</p>
                        <p class="text-gray-600 mt-0.5">Phone: (061) 8211633</p>
                    </div>

                    <!-- Social Icons: Instagram, WhatsApp, dan Email (Facebook, X, YouTube dihilangkan sesuai permintaan) -->
                    <div class="flex items-center gap-4 text-lg text-gray-700 pt-1">
                        <a href="https://instagram.com/usuclassmap" target="_blank" class="hover:text-[#10A760] transition-colors" title="Instagram Resmi">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="https://wa.me/6281260008787" target="_blank" class="hover:text-[#10A760] transition-colors" title="WhatsApp Helpdesk">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                        <a href="mailto:support.classmap@usu.ac.id" class="hover:text-[#10A760] transition-colors" title="Email Bantuan">
                            <i class="fa-solid fa-envelope"></i>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Navigation Links -->
                <div class="md:col-span-3 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <h3 class="font-bold text-gray-900 text-xs sm:text-sm mb-3">Tentang USU</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Peta Kampus</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Tur 360°</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Gedung Kelas</a></li>
                            <li><a href="/map" class="hover:text-[#10A760] transition-colors">Daftar Dosen</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 text-xs sm:text-sm mb-3">Layanan</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li><a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760] transition-colors">Fotocopy</a></li>
                            <li><a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760] transition-colors">Pesan Cetak</a></li>
                            <li><a href="/bookmarks" class="hover:text-[#10A760] transition-colors">Bookmark</a></li>
                            <li><a href="{{ route('faq') }}" class="hover:text-[#10A760] transition-colors">Bantuan FAQ</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Col 3: Callout Box Navigasi Peta -->
                <div class="md:col-span-4">
                    <div class="border border-gray-300 sm:border-[#10A760]/30 rounded-xl p-4 sm:p-5 bg-white shadow-xs">
                        <h4 class="font-bold text-gray-900 text-sm mb-1">
                            Akses Peta & Navigasi Kampus.
                        </h4>
                        <p class="text-xs text-gray-600 leading-relaxed mb-4">
                            Dapatkan rute jalan presisi ke gedung ruang kelas, lokasi sekretariat PSI USU, dan mitra fotocopy terdekat.
                        </p>

                        <!-- Buttons Mengarah ke Peta -->
                        <div class="space-y-2">
                            <a
                                href="/map"
                                class="w-full flex items-center justify-between px-4 py-2.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white rounded-lg text-xs font-bold transition-colors shadow-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                    <span>Buka di Peta Kampus USU</span>
                                </div>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>

                            <a
                                href="{{ route('map', ['route_to' => '3.5620,98.6565']) }}"
                                class="w-full flex items-center justify-between px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-lg text-xs font-semibold transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-diamond-turn-right text-[#D4A017]"></i>
                                    <span>Lihat Rute di Peta Kampus</span>
                                </div>
                                <i class="fa-solid fa-arrow-right text-[10px] text-gray-400"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Sub-Footer: HANYA Copyright Center (Bahasa dan Syarat Dihapus Sesuai Permintaan) -->
            <div class="mt-10 pt-6 border-t border-gray-200 text-center text-xs text-gray-500">
                &copy; 2000&ndash;2026 Universitas Sumatera Utara. All rights reserved.
            </div>

        </div>
    </footer>
</x-layouts.app>
