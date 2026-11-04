<x-layouts.app>
    <div class="min-h-[calc(100vh-12rem)] flex items-center justify-center px-4 py-12">
        <div class="max-w-lg w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8 sm:p-12 text-center relative overflow-hidden">
            <!-- Decorative Green Glow -->
            <div class="absolute -top-16 -right-16 w-36 h-36 bg-[#10A760]/10 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-16 -left-16 w-36 h-36 bg-[#10A760]/10 rounded-full blur-2xl"></div>

            <!-- 404 Illustration / Icon -->
            <div class="w-24 h-24 rounded-full bg-green-50 text-[#10A760] mx-auto flex items-center justify-center mb-6 shadow-inner">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>

            <!-- Status Code & Title -->
            <span class="px-3 py-1 bg-red-100 text-red-700 font-extrabold text-xs rounded-full uppercase tracking-wider">
                Error 404
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-4 tracking-tight">
                Halaman Tidak Ditemukan
            </h1>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed max-w-sm mx-auto">
                Maaf, lokasi atau halaman yang Anda tuju tidak tersedia atau telah dipindahkan.
            </p>

            <!-- Action Buttons -->
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="/map" class="w-full sm:w-auto px-6 py-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    Kembali ke Peta
                </a>
                <a href="/" class="w-full sm:w-auto px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Beranda
                </a>
            </div>

            <!-- Footer note -->
            <div class="mt-8 pt-6 border-t border-gray-100 text-xs text-gray-400">
                USUClassMap • Kelompok 3 Web Semantik
            </div>
        </div>
    </div>
</x-layouts.app>
