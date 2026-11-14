<x-layouts.admin-fotokopi>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Manajemen Mitra Toko Fotocopy</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola data seluruh gerai mitra percetakan resmi di lingkungan Universitas Sumatera Utara.</p>
        </div>
        <a href="{{ route('admin-fotokopi.stores.create') }}" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2.5 rounded-xl shadow-xs font-bold text-sm transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Mitra Toko
        </a>
    </div>

    <!-- Search Form -->
    <div class="bg-white p-4 rounded-2xl shadow-xs border border-gray-100 mb-6">
        <form method="GET" action="{{ route('admin-fotokopi.stores.index') }}" class="flex gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama toko, lokasi kampus, nomor WhatsApp..." class="w-full pl-10 pr-3 py-2 rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500 border">
            </div>
            <button type="submit" class="bg-gray-100 px-4 py-2 border border-gray-200 rounded-xl hover:bg-gray-200 text-gray-700 font-semibold text-sm transition-colors cursor-pointer">
                Cari
            </button>
            @if(request('q'))
                <a href="{{ route('admin-fotokopi.stores.index') }}" class="px-3 py-2 border border-gray-200 rounded-xl hover:bg-gray-100 text-gray-600 text-sm flex items-center">Reset</a>
            @endif
        </form>
    </div>

    <!-- Stores Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">No</th>
                        <th class="p-4">Nama Toko & Lokasi</th>
                        <th class="p-4">Jam & Hari Operasional</th>
                        <th class="p-4">Kontak Person</th>
                        <th class="p-4 text-center">Status & Rating</th>
                        <th class="p-4 text-center">Katalog</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($stores as $index => $store)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-4 text-gray-400 font-medium text-xs">{{ $stores->firstItem() + $index }}</td>
                        <td class="p-4">
                            <div class="font-bold text-gray-900">{{ $store->name }}</div>
                            <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>{{ $store->location_name }}</span>
                            </div>
                        </td>
                        <td class="p-4 text-xs">
                            <span class="font-bold text-gray-800">{{ $store->opening_hours }}</span>
                            <span class="block text-[11px] text-gray-400 mt-0.5">{{ $store->operating_days }}</span>
                        </td>
                        <td class="p-4 text-xs space-y-1">
                            @if($store->whatsapp)
                                <span class="flex items-center gap-1.5 text-gray-700 font-mono text-[11px]">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    {{ $store->whatsapp }}
                                </span>
                            @endif
                            @if($store->instagram)
                                <span class="flex items-center gap-1.5 text-pink-600 text-[11px]">
                                    <svg class="w-3.5 h-3.5 text-pink-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    {{ $store->instagram }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            @if($store->is_open)
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Buka
                                </span>
                            @else
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-600 border border-red-200">
                                    Tutup
                                </span>
                            @endif
                            <div class="text-[11px] text-amber-700 font-bold mt-1 inline-flex items-center justify-center gap-1">
                                <svg class="w-3 h-3 fill-amber-400 text-amber-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                {{ $store->rating }}
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-100">
                                {{ $store->services_count }} Item
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin-fotokopi.stores.edit', $store) }}" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Toko">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>

                                <div x-data="{ showDelete: false }">
                                    <button @click="showDelete = true" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Hapus Toko">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    
                                    <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                                        <div class="bg-white rounded-2xl p-6 max-w-sm mx-4 shadow-xl text-left">
                                            <h3 class="text-base font-bold text-gray-900 mb-1">Hapus Mitra Toko</h3>
                                            <p class="text-xs text-gray-500 mb-4">Apakah Anda yakin ingin menghapus data toko "{{ $store->name }}" beserta seluruh katalog layanannya?</p>
                                            <div class="flex justify-end gap-2">
                                                <button @click="showDelete = false" class="px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</button>
                                                <form method="POST" action="{{ route('admin-fotokopi.stores.destroy', $store) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold">Hapus</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-gray-400">
                            Belum ada mitra toko yang terdaftar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $stores->links() }}
    </div>
</x-layouts.admin-fotokopi>
