<x-layouts.admin>
    <div x-data="{ 
        previewModalOpen: false, 
        previewUrl: '', 
        previewTitle: '',
        previewBuilding: '',
        openPreview(url, title, building) {
            this.previewUrl = url;
            this.previewTitle = title;
            this.previewBuilding = building;
            this.previewModalOpen = true;
        }
    }">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Ruangan</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola data ruangan perkuliahan, kapasitas, dan foto panorama 360°.</p>
            </div>
            <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2.5 rounded-lg shadow-sm font-semibold text-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Ruangan
            </a>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
            <form method="GET" action="{{ url()->current() }}" class="flex gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama ruangan atau gedung..." class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-200 shadow-xs focus:border-emerald-500 focus:ring-emerald-500 text-sm border">
                </div>
                <button type="submit" class="bg-gray-100 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-200 text-gray-700 font-medium text-sm transition-colors cursor-pointer">Cari</button>
                @if(request('q'))
                    <a href="{{ route('admin.rooms.index') }}" class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600 text-sm flex items-center">Reset</a>
                @endif
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/75 border-b border-gray-100 text-xs uppercase font-bold text-gray-500 tracking-wider">
                            <th class="p-4">No</th>
                            <th class="p-4">Nama Ruangan</th>
                            <th class="p-4">Gedung</th>
                            <th class="p-4 text-center">Lantai</th>
                            <th class="p-4 text-center">Kapasitas</th>
                            <th class="p-4 text-center">Foto 360</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($rooms as $index => $room)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="p-4 text-gray-400 font-medium">{{ $rooms->firstItem() + $index }}</td>
                            <td class="p-4 font-semibold text-gray-900">{{ $room->name }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 text-gray-700 font-medium">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    {{ $room->building->name ?? '-' }}
                                </span>
                            </td>
                            <td class="p-4 text-center text-gray-700 font-mono">{{ $room->floor }}</td>
                            <td class="p-4 text-center text-gray-700">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium text-xs">
                                    {{ $room->capacity ?? '-' }} Mhs
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                @if($room->photo_360_path)
                                    {{-- Badge Hijau "Ada Foto" Clickable untuk Preview Cepat --}}
                                    <button 
                                        type="button"
                                        @click="openPreview('{{ $room->photo_360_url }}', '{{ $room->name }}', '{{ $room->building->name ?? '' }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300 shadow-2xs transition-all cursor-pointer group"
                                        title="Klik untuk melihat pratinjau foto 360"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Ada Foto</span>
                                        <svg class="w-3.5 h-3.5 text-emerald-600 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                @else
                                    {{-- Tanda Kosong yang Jelas --}}
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-gray-400 bg-gray-100 border border-gray-200">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        Kosong
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.rooms.edit', $room) }}" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Ruangan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    
                                    <div x-data="{ showDelete: false }">
                                        <button @click="showDelete = true" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Hapus Ruangan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                        <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                                            <div class="bg-white rounded-xl p-6 max-w-sm mx-4 shadow-xl text-left">
                                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                </div>
                                                <h3 class="text-base font-bold text-gray-900 mb-1">Hapus Ruangan</h3>
                                                <p class="text-sm text-gray-500 mb-5">Apakah Anda yakin ingin menghapus ruangan <strong>{{ $room->name }}</strong>? Jadwal yang terkait dengan ruangan ini akan terpengaruh.</p>
                                                <div class="flex justify-end gap-2">
                                                    <button @click="showDelete = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors cursor-pointer">Batal</button>
                                                    <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}">
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
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    </div>
                                    <p class="text-gray-600 font-semibold mb-1">Belum ada data ruangan</p>
                                    <p class="text-xs text-gray-400 mb-4">Tambahkan ruangan perkuliahan dan upload foto 360 untuk visualisasi peta interaktif.</p>
                                    <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Tambah Ruangan Pertama
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
            {{ $rooms->links() }}
        </div>

        {{-- Modal Pratinjau Cepat Foto 360 --}}
        <div 
            x-show="previewModalOpen" 
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
            style="display: none;"
            @keydown.escape.window="previewModalOpen = false"
        >
            <div 
                @click.outside="previewModalOpen = false"
                class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden transition-all transform"
                x-show="previewModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 font-bold text-xs">
                            360°
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-gray-900" x-text="previewTitle"></h3>
                            <p class="text-xs text-gray-500" x-text="previewBuilding"></p>
                        </div>
                    </div>
                    <button @click="previewModalOpen = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6">
                    <div class="relative rounded-xl overflow-hidden bg-black/5 aspect-video border border-gray-200 flex items-center justify-center">
                        <img :src="previewUrl" :alt="previewTitle" class="w-full h-full object-cover">
                        <div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-xs text-white text-xs px-2.5 py-1 rounded-full flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            <span>Pratinjau Foto Panorama</span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <span>File tersimpan di media penyimpanan publik</span>
                    <a :href="previewUrl" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-semibold">
                        Buka Ukuran Penuh
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
