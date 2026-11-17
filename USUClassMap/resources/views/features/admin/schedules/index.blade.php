<x-layouts.admin>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manajemen Jadwal</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola data jadwal perkuliahan MKWK & MKWU, ruang kuliah, dan dosen pengampu.</p>
        </div>
        <a href="{{ route('admin.schedules.create') }}" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2.5 rounded-lg shadow-sm font-semibold text-sm transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Jadwal
        </a>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="{{ url()->current() }}" class="flex gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari mata kuliah, dosen, atau ruangan..." class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-200 shadow-xs focus:border-emerald-500 focus:ring-emerald-500 text-sm border">
            </div>
            <button type="submit" class="bg-gray-100 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-200 text-gray-700 font-medium text-sm transition-colors cursor-pointer">Cari</button>
            @if(request('q'))
                <a href="{{ route('admin.schedules.index') }}" class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600 text-sm flex items-center">Reset</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-xs uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">No</th>
                        <th class="p-4">Mata Kuliah</th>
                        <th class="p-4">Dosen</th>
                        <th class="p-4">Ruangan</th>
                        <th class="p-4">Hari</th>
                        <th class="p-4">Jam</th>
                        <th class="p-4 text-center">Semester</th>
                        <th class="p-4 text-center">Kelompok</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($schedules as $index => $schedule)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-4 text-gray-400 font-medium">{{ $schedules->firstItem() + $index }}</td>
                        <td class="p-4 font-semibold text-gray-900">{{ $schedule->course->name ?? '-' }}</td>
                        <td class="p-4 text-gray-700">{{ $schedule->lecturer->name ?? '-' }}</td>
                        <td class="p-4">
                            <span class="inline-flex items-center gap-1 font-mono text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-800">
                                {{ $schedule->room->name ?? '-' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                {{ $schedule->day }}
                            </span>
                        </td>
                        <td class="p-4 font-mono text-xs text-gray-600">
                            {{ substr($schedule->time_start, 0, 5) }} - {{ substr($schedule->time_end, 0, 5) }}
                        </td>
                        <td class="p-4 text-center text-gray-700">{{ $schedule->semester }}</td>
                        <td class="p-4 text-center font-mono text-xs text-gray-600">{{ $schedule->class_group }}</td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.schedules.edit', $schedule) }}" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Jadwal">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                
                                <div x-data="{ showDelete: false }">
                                    <button @click="showDelete = true" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Hapus Jadwal">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                                        <div class="bg-white rounded-xl p-6 max-w-sm mx-4 shadow-xl text-left">
                                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            </div>
                                            <h3 class="text-base font-bold text-gray-900 mb-1">Hapus Jadwal</h3>
                                            <p class="text-sm text-gray-500 mb-5">Apakah Anda yakin ingin menghapus jadwal mata kuliah ini?</p>
                                            <div class="flex justify-end gap-2">
                                                <button @click="showDelete = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors cursor-pointer">Batal</button>
                                                <form method="POST" action="{{ route('admin.schedules.destroy', $schedule) }}">
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
                        <td colspan="9" class="p-10 text-center">
                            <div class="max-w-sm mx-auto">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <p class="text-gray-600 font-semibold mb-1">Belum ada data jadwal</p>
                                <p class="text-xs text-gray-400 mb-4">Tambahkan jadwal kuliah baru untuk ditampilkan di sistem peta dan kalender.</p>
                                <a href="{{ route('admin.schedules.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Tambah Jadwal Pertama
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
        {{ $schedules->links() }}
    </div>
</x-layouts.admin>
