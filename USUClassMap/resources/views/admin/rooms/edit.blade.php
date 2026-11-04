<x-layouts.admin>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Ruangan</h1>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gedung</label>
                <select name="building_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Gedung --</option>
                    @foreach($buildings as $building)
                        <option value="{{ $building->id }}" {{ old('building_id', $room->building_id) == $building->id ? 'selected' : '' }}>
                            {{ $building->name }}
                        </option>
                    @endforeach
                </select>
                @error('building_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ruangan</label>
                <input type="text" name="name" value="{{ old('name', $room->name) }}" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lantai</label>
                    <input type="number" name="floor" value="{{ old('floor', $room->floor) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    @error('floor')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kapasitas</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $room->capacity) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    @error('capacity')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Foto 360 (Kosongkan jika tidak ingin mengubah)</label>
                @if($room->photo_360_path)
                    <div class="mb-2">
                        <p class="text-sm text-gray-500 mb-1">Foto saat ini:</p>
                        <img src="{{ Storage::url($room->photo_360_path) }}" alt="Foto 360" class="w-32 h-32 object-cover rounded">
                    </div>
                @endif
                <input type="file" name="photo_360" accept="image/*" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('photo_360')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#10A760] text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow">Simpan Perubahan</button>
                <a href="{{ route('admin.rooms.index') }}" class="text-gray-600 hover:text-gray-800">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
