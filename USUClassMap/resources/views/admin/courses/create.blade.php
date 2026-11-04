<x-layouts.admin>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Tambah Mata Kuliah</h1>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form method="POST" action="{{ route('admin.courses.store') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Mata Kuliah</label>
                <input type="text" name="code" value="{{ old('code') }}" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('code')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mata Kuliah</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">SKS</label>
                <input type="number" name="credits" value="{{ old('credits') }}" min="1" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('credits')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                <select name="type" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Tipe --</option>
                    <option value="MKWK" {{ old('type') == 'MKWK' ? 'selected' : '' }}>MKWK</option>
                    <option value="MKWU" {{ old('type') == 'MKWU' ? 'selected' : '' }}>MKWU</option>
                    <option value="Prodi" {{ old('type') == 'Prodi' ? 'selected' : '' }}>Prodi</option>
                </select>
                @error('type')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#10A760] text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow">Simpan</button>
                <a href="{{ route('admin.courses.index') }}" class="text-gray-600 hover:text-gray-800">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
