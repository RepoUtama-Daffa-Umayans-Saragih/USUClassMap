<x-layouts.admin>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manajemen Dosen</h1>
        <a href="{{ route('admin.lecturers.create') }}" class="bg-[#10A760] text-white px-4 py-2 rounded-lg shadow hover:bg-green-700">
            Tambah Dosen
        </a>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
        <form method="GET" action="{{ url()->current() }}" class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari dosen..." class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
            <button type="submit" class="bg-gray-100 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-200">Cari</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="p-3 font-semibold text-gray-600">No</th>
                    <th class="p-3 font-semibold text-gray-600">Nama</th>
                    <th class="p-3 font-semibold text-gray-600">NIP</th>
                    <th class="p-3 font-semibold text-gray-600">Email</th>
                    <th class="p-3 font-semibold text-gray-600">Telepon</th>
                    <th class="p-3 font-semibold text-gray-600">Jumlah Jadwal</th>
                    <th class="p-3 font-semibold text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lecturers as $index => $lecturer)
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3">{{ $lecturers->firstItem() + $index }}</td>
                    <td class="p-3">{{ $lecturer->name }}</td>
                    <td class="p-3">{{ $lecturer->nip ?? '-' }}</td>
                    <td class="p-3">{{ $lecturer->email ?? '-' }}</td>
                    <td class="p-3">{{ $lecturer->phone ?? '-' }}</td>
                    <td class="p-3">{{ $lecturer->schedules_count }}</td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.lecturers.edit', $lecturer) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                            
                            <div x-data="{ showDelete: false }">
                                <button @click="showDelete = true" class="text-red-600 hover:text-red-800">Hapus</button>
                                <div x-show="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                                    <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
                                        <h3 class="text-lg font-semibold mb-2">Konfirmasi Hapus</h3>
                                        <p class="text-gray-600 mb-4">Apakah Anda yakin ingin menghapus data ini?</p>
                                        <div class="flex justify-end gap-2">
                                            <button @click="showDelete = false" class="px-4 py-2 bg-gray-200 rounded-lg">Batal</button>
                                            <form method="POST" action="{{ route('admin.lecturers.destroy', $lecturer) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg">Hapus</button>
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
                    <td colspan="7" class="p-6 text-center text-gray-500">
                        Belum ada data dosen.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $lecturers->links() }}
    </div>
</x-layouts.admin>
