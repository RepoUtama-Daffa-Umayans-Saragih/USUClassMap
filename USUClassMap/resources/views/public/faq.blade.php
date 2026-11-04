<x-layouts.app>
    <div class="max-w-4xl mx-auto px-4 py-10">
        <!-- Header -->
        <div class="text-center mb-10">
            <span class="px-3 py-1 bg-green-100 text-[#10A760] font-bold text-xs rounded-full uppercase tracking-wider">
                Pusat Bantuan & Informasi
            </span>
            <h1 class="text-3xl font-extrabold text-gray-900 mt-3 tracking-tight sm:text-4xl">
                Pertanyaan yang Sering Diajukan
            </h1>
            <p class="mt-3 text-base text-gray-600 max-w-2xl mx-auto">
                Pelajari cara memanfaatkan fitur Peta Interaktif, Tur 360°, Navigasi, dan Pencarian Jadwal MKWK/MKWU di kampus USU.
            </p>
        </div>

        <!-- Accordion FAQ Container -->
        <div class="space-y-3.5" x-data="{ active: null }">
            @foreach($faqs as $index => $faq)
            <div class="bg-white rounded-xl shadow-xs border border-gray-200/80 overflow-hidden transition-all duration-200 hover:border-[#10A760]">
                <button
                    type="button"
                    @click="active = active === {{ $index }} ? null : {{ $index }}"
                    class="w-full flex items-center justify-between px-6 py-4.5 text-left transition-colors focus:outline-hidden"
                    :class="{ 'bg-green-50/40 text-[#10A760]': active === {{ $index }} }"
                >
                    <div class="flex items-center gap-3 pr-4">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold" :class="{ 'bg-[#10A760] text-white': active === {{ $index }} }">
                            {{ $index + 1 }}
                        </span>
                        <span class="font-bold text-gray-900 text-sm sm:text-base" :class="{ 'text-[#10A760]': active === {{ $index }} }">
                            {{ $faq['question'] }}
                        </span>
                    </div>
                    
                    <div class="flex-shrink-0 ml-2">
                        <svg
                            class="w-5 h-5 text-gray-400 transition-transform duration-300"
                            :class="{ 'rotate-180 text-[#10A760]': active === {{ $index }} }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </button>

                <div
                    x-show="active === {{ $index }}"
                    x-collapse
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="px-6 pb-5 pt-1 border-t border-gray-100 bg-white"
                    style="display: none;"
                >
                    <p class="text-sm text-gray-600 leading-relaxed pl-9">
                        {{ $faq['answer'] }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Quick Help Card -->
        <div class="mt-12 p-6 bg-gradient-to-r from-[#10A760]/10 via-emerald-50 to-[#10A760]/5 rounded-2xl border border-[#10A760]/20 text-center">
            <h3 class="text-base font-bold text-gray-900">Belum menemukan jawaban yang Anda cari?</h3>
            <p class="text-xs text-gray-600 mt-1">Tim administrator USUClassMap siap membantu Anda.</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="/map" class="px-4 py-2 bg-[#10A760] text-white rounded-lg text-xs font-semibold hover:bg-[#0D8A4E] shadow-xs transition-colors">
                    Buka Peta Kampus
                </a>
                <a href="mailto:admin@usu.ac.id" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">
                    Hubungi Dukungan
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
