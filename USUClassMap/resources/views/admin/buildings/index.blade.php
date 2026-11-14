<x-layouts.admin>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manajemen Gedung</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola data gedung, titik koordinat peta, dan informasi lantai.</p>
        </div>
        <a href="{{ route('admin.buildings.create') }}" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2.5 rounded-lg shadow-sm font-semibold text-sm transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Gedung
        </a>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="{{ url()->current() }}" class="flex gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama gedung atau kode..." class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-200 shadow-xs focus:border-emerald-500 focus:ring-emerald-500 text-sm border">
            </div>
            <button type="submit" class="bg-gray-100 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-200 text-gray-700 font-medium text-sm transition-colors cursor-pointer">Cari</button>
            @if(request('q'))
                <a href="{{ route('admin.buildings.index') }}" class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600 text-sm flex items-center">Reset</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-xs uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">No</th>
                        <th class="p-4">Nama Gedung</th>
                        <th class="p-4">Kode</th>
                        <th class="p-4">Koordinat (Lat, Lng)</th>
                        <th class="p-4 text-center">Lantai</th>
                        <th class="p-4 text-center">Total Ruangan</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($buildings as $index => $building)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-4 text-gray-400 font-medium">{{ $buildings->firstItem() + $index }}</td>
                        <td class="p-4">
                            <span class="font-semibold text-gray-900 block">{{ $building->name }}</span>
                            @if($building->faculty)
                                <span class="text-xs text-gray-500">{{ $building->faculty }}</span>
                            @endif
                        </td>
                        <td class="p-4">
                            <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-700 font-mono text-xs rounded font-medium">
                                {{ $building->code ?? '-' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-1.5 text-xs font-mono text-gray-600">
                                <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                <span>{{ number_format((float)$building->latitude, 5) }}, {{ number_format((float)$building->longitude, 5) }}</span>
                            </div>
                        </td>
                        <td class="p-4 text-center text-gray-700">{{ $building->floor_count }}</td>
                        <td class="p-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                {{ $building->rooms_count ?? 0 }} Ruang
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.buildings.edit', $building) }}" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Gedung">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                
                                <div x-data="{ showDelete: false }">
                                    <button @click="showDelete = true" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Hapus Gedung">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                                        <div class="bg-white rounded-xl p-6 max-w-sm mx-4 shadow-xl text-left">
                                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            </div>
                                            <h3 class="text-base font-bold text-gray-900 mb-1">Hapus Gedung</h3>
                                            <p class="text-sm text-gray-500 mb-5">Apakah Anda yakin ingin menghapus gedung <strong>{{ $building->name }}</strong>? Data ruangan di gedung ini mungkin ikut terhapus.</p>
                                            <div class="flex justify-end gap-2">
                                                <button @click="showDelete = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors cursor-pointer">Batal</button>
                                                <form method="POST" action="{{ route('admin.buildings.destroy', $building) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition-colors cursor-pointer">Hapus</button>
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
                        <td colspan="7" class="p-10 text-center">
                            <div class="max-w-sm mx-auto">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </div>
                                <p class="text-gray-600 font-semibold mb-1">Belum ada data gedung</p>
                                <p class="text-xs text-gray-400 mb-4">Tambahkan gedung baru untuk mulai memetakan ruangan dan jadwal perkuliahan.</p>
                                <a href="{{ route('admin.buildings.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Tambah Gedung Pertama
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4">
        {{ $buildings->links() }}
    </div>
</x-layouts.admin>
