<div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col justify-between group">
    <div>
        <!-- Card Header: Status & Rating -->
        <div class="p-4 pb-0 flex items-center justify-between">
            @if($store->is_open)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Buka
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-600 border border-red-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                    Tutup
                </span>
            @endif

            <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-500 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100">
                ★ {{ number_format($store->rating, 1) }}
            </span>
        </div>

        <!-- Store Icon & Brand -->
        <div class="px-5 pt-3 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-[#10B981] flex items-center justify-center text-2xl shadow-inner group-hover:scale-105 transition-transform">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            </div>
            <span class="inline-block mt-2 text-[10px] font-extrabold tracking-wider uppercase text-emerald-600">
                Mitra Fotocopy USU
            </span>
            <h3 class="text-base font-black text-gray-900 mt-0.5 line-clamp-1 leading-snug">
                <a href="{{ route('fotocopy.show', $store->id) }}" class="hover:text-[#10B981] transition-colors">
                    {{ $store->name }}
                </a>
            </h3>
            <p class="text-xs text-gray-500 flex items-center justify-center gap-1 mt-1 line-clamp-1">
                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                <span>{{ $store->location_name }}</span>
            </p>
        </div>

        <!-- Operating Hours & Starting Price -->
        <div class="px-5 py-3 mt-3 bg-gray-50/80 border-y border-gray-100 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 text-gray-600 font-medium">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <span class="font-bold text-gray-800">{{ $store->opening_hours }}</span>
                    <span class="block text-[10px] text-gray-400">{{ $store->operating_days }}</span>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] text-gray-400 block uppercase">Mulai</span>
                <span class="font-extrabold text-[#10B981] text-xs">Rp {{ number_format($store->starting_price, 0, ',', '.') }}/lbr</span>
            </div>
        </div>

        <!-- Sample Services Tags -->
        <div class="p-4 flex flex-wrap gap-1.5">
            @foreach($store->services->take(3) as $srv)
                <span class="inline-block text-[10px] font-semibold bg-gray-100 text-gray-700 px-2 py-0.5 rounded-md border border-gray-200">
                    {{ $srv->name }}
                </span>
            @endforeach
        </div>
    </div>

    <!-- Contact Icons & Action Buttons -->
    <div class="p-4 pt-0 space-y-3">
        <div class="flex items-center justify-between text-xs text-gray-400 pt-2 border-t border-gray-100">
            <span class="text-[11px] font-medium text-gray-500">Kontak Person:</span>
            <div class="flex items-center gap-2">
                @if($store->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp) }}" target="_blank" class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white flex items-center justify-center transition-colors text-xs" title="Chat WhatsApp">
                        💬
                    </a>
                @endif
                @if($store->instagram)
                    <a href="https://instagram.com/{{ ltrim($store->instagram, '@') }}" target="_blank" class="w-6 h-6 rounded-full bg-pink-50 text-pink-600 hover:bg-pink-500 hover:text-white flex items-center justify-center transition-colors text-xs" title="Instagram">
                        📷
                    </a>
                @endif
                @if($store->facebook)
                    <a href="https://facebook.com/{{ $store->facebook }}" target="_blank" class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white flex items-center justify-center transition-colors text-xs" title="Facebook">
                        f
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('fotocopy.show', $store->id) }}" class="inline-flex items-center justify-center gap-1.5 py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold text-xs transition-colors cursor-pointer border border-gray-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Detail
            </a>
            <a href="{{ route('fotocopy.order', $store->id) }}" class="inline-flex items-center justify-center gap-1.5 py-2 px-3 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pesan
            </a>
        </div>
    </div>
</div>
