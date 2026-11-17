<x-layouts.admin-fotokopi>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Katalog Layanan & Daftar Harga</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola daftar harga cetak (Print, Fotocopy, Jilid, ATK) untuk seluruh mitra toko.</p>
        </div>
        <a href="{{ route('admin-fotokopi.services.create') }}" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2.5 rounded-xl shadow-xs font-bold text-sm transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Item Layanan
        </a>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-xs border border-gray-100 mb-6">
        <form method="GET" action="{{ route('admin-fotokopi.services.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <select name="store_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
                    <option value="">-- Semua Mitra Toko --</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="category" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
                    <option value="">-- Semua Kategori --</option>
                    @foreach(['Print', 'Fotocopy', 'Jilid', 'ATK'] as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama layanan atau deskripsi..." class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-3 rounded-xl text-xs transition-colors cursor-pointer border border-gray-200">
                    Terapkan Filter
                </button>
                @if(request('store_id') || request('category') || request('q'))
                    <a href="{{ route('admin-fotokopi.services.index') }}" class="px-3 py-2 bg-red-50 text-red-600 hover:bg-red-100 text-xs font-semibold rounded-xl border border-red-200">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Services Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">No</th>
                        <th class="p-4">Mitra Toko</th>
                        <th class="p-4">Nama Item Layanan</th>
                        <th class="p-4 text-center">Kategori</th>
                        <th class="p-4 text-right">Harga Satuan</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($services as $index => $srv)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-4 text-gray-400 font-medium text-xs">{{ $services->firstItem() + $index }}</td>
                        <td class="p-4 font-semibold text-gray-800 text-xs">
                            {{ $srv->store->name ?? '-' }}
                        </td>
                        <td class="p-4">
                            <span class="font-bold text-gray-900 text-xs block">{{ $srv->name }}</span>
                            <span class="text-[11px] text-gray-400 block mt-0.5">{{ $srv->description ?? '-' }}</span>
                        </td>
                        <td class="p-4 text-center">
                            @php
                                $badgeClass = match($srv->category) {
                                    'Print' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'Fotocopy' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'Jilid' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'ATK' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                                };
                            @endphp
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeClass }}">
                                {{ $srv->category }}
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <span class="font-black text-[#10B981] text-xs">Rp {{ number_format($srv->price, 0, ',', '.') }}</span>
                            <span class="text-[10px] text-gray-400 block font-normal">/ {{ $srv->unit }}</span>
                        </td>
                        <td class="p-4 text-center">
                            @if($srv->is_available)
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Tersedia
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-gray-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Habis
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin-fotokopi.services.edit', $srv) }}" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Layanan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>

                                <form method="POST" action="{{ route('admin-fotokopi.services.destroy', $srv) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus item katalog ini?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Hapus Layanan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-gray-400">
                            Belum ada item layanan di katalog yang sesuai filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $services->links() }}
    </div>
</x-layouts.admin-fotokopi>
