<x-layouts.admin>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Import Jadwal dari Excel</h1>
    </div>

    @if(session('import_errors'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
        <h3 class="font-bold mb-2">Terjadi kesalahan saat import:</h3>
        <ul class="list-disc pl-5">
            @foreach(session('import_errors') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <form method="POST" action="{{ route('admin.import.store') }}" enctype="multipart/form-data">
            @csrf
            
            <div x-data="{ fileName: '' }" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">File Excel</label>
                
                <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:bg-gray-50 transition">
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required 
                           @change="fileName = $event.target.files[0].name" 
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    
                    <div class="text-gray-500">
                        <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p x-show="!fileName" class="text-sm">Klik atau drag file ke area ini untuk upload (.xlsx, .xls, .csv)</p>
                        <p x-show="fileName" x-text="fileName" class="text-sm font-semibold text-[#10A760]"></p>
                    </div>
                </div>
                @error('file')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#10A760] text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow">Import</button>
                <a href="{{ route('admin.import.download-template') }}" class="text-blue-600 hover:text-blue-800">Download Template</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold mb-4">Instruksi Import</h3>
        <p class="text-gray-600 mb-4">File Excel harus memiliki kolom-kolom berikut pada baris pertama (header):</p>
        
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse border border-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-200 p-2 font-mono">kode_mk</th>
                        <th class="border border-gray-200 p-2 font-mono">nama_mk</th>
                        <th class="border border-gray-200 p-2 font-mono">sks</th>
                        <th class="border border-gray-200 p-2 font-mono">tipe_mk</th>
                        <th class="border border-gray-200 p-2 font-mono">nama_dosen</th>
                        <th class="border border-gray-200 p-2 font-mono">nama_ruangan</th>
                        <th class="border border-gray-200 p-2 font-mono">hari</th>
                        <th class="border border-gray-200 p-2 font-mono">jam_mulai</th>
                        <th class="border border-gray-200 p-2 font-mono">jam_selesai</th>
                        <th class="border border-gray-200 p-2 font-mono">semester</th>
                        <th class="border border-gray-200 p-2 font-mono">kelompok_kelas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-gray-200 p-2">TIF101</td>
                        <td class="border border-gray-200 p-2">Algoritma</td>
                        <td class="border border-gray-200 p-2">3</td>
                        <td class="border border-gray-200 p-2">Prodi</td>
                        <td class="border border-gray-200 p-2">Budi Susanto</td>
                        <td class="border border-gray-200 p-2">Lab Kom 1</td>
                        <td class="border border-gray-200 p-2">Senin</td>
                        <td class="border border-gray-200 p-2">08:00</td>
                        <td class="border border-gray-200 p-2">10:30</td>
                        <td class="border border-gray-200 p-2">Ganjil 2024</td>
                        <td class="border border-gray-200 p-2">A</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
