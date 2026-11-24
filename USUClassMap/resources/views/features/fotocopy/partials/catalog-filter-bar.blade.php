<div class="bg-white rounded-2xl border border-gray-100 p-3.5 sm:p-4 shadow-2xs space-y-3">
    <!-- Tabs: Semua / Jasa / Barang ATK -->
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            @click="setCatalogTab('all')"
            :class="catalogTab === 'all' ? 'bg-[#10A760] text-white border-[#10A760] shadow-xs' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'"
            class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer inline-flex items-center gap-1.5"
        >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            <span>Semua (<span x-text="catalogCount('all')"></span>)</span>
        </button>

        <button
            type="button"
            @click="setCatalogTab('jasa')"
            :class="catalogTab === 'jasa' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-emerald-50'"
            class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer inline-flex items-center gap-1.5"
        >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            <span>Jasa Cetak & Jilid (<span x-text="catalogCount('jasa')"></span>)</span>
        </button>

        <button
            type="button"
            @click="setCatalogTab('barang')"
            :class="catalogTab === 'barang' ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-sky-50'"
            class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer inline-flex items-center gap-1.5"
        >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            <span>Barang ATK & Niche (<span x-text="catalogCount('barang')"></span>)</span>
        </button>
    </div>

    <!-- Search & Sub-Category Filter -->
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
        <div class="sm:col-span-7 relative">
            <svg class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input
                type="text"
                x-model.debounce.150ms="catalogQuery"
                placeholder="Cari layanan, merek (Pilot, Joyko, SiDU), ukuran, materai..."
                class="w-full text-xs pl-8 pr-8 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
            >
            <button
                type="button"
                x-show="catalogQuery.length > 0"
                @click="catalogQuery = ''"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
                title="Hapus pencarian"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="sm:col-span-5">
            <select
                x-model="catalogSub"
                class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760] bg-white"
            >
                <option value="">-- Semua Sub-Kategori --</option>
                <template x-for="sub in catalogSubOptions()" :key="sub">
                    <option :value="sub" x-text="sub"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="flex items-center justify-between text-[11px] text-gray-500 pt-0.5">
        <span>
            Menampilkan <strong class="text-gray-800" x-text="catalogFiltered().length"></strong> dari
            <strong class="text-gray-800" x-text="catalog.length"></strong> item katalog
        </span>
        <button
            type="button"
            x-show="catalogTab !== 'all' || catalogSub !== '' || catalogQuery !== ''"
            @click="catalogTab = 'all'; catalogSub = ''; catalogQuery = ''"
            class="text-[#10A760] hover:underline font-semibold cursor-pointer"
        >
            Reset Filter
        </button>
    </div>
</div>
