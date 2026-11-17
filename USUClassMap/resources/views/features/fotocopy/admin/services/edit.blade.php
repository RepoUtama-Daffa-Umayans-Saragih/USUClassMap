<x-layouts.admin-fotokopi>
    <div class="mb-6">
        <a href="{{ route('admin-fotokopi.services.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-600 transition-colors mb-2">
            &larr; Kembali ke Katalog
        </a>
        <h1 class="text-2xl font-black text-gray-900">Edit Layanan: {{ $service->name }}</h1>
        <p class="text-xs text-gray-500 mt-1">Perbarui rincian harga dan spesifikasi item katalog.</p>
    </div>

    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-xs max-w-2xl">
        <form method="POST" action="{{ route('admin-fotokopi.services.update', $service) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Pilih Mitra Toko *</label>
                <select name="store_id" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ old('store_id', $service->store_id) == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
                @error('store_id')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Layanan / Produk *</label>
                <input type="text" name="name" value="{{ old('name', $service->name) }}" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                @error('name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kategori *</label>
                    <select name="category" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="Print" {{ old('category', $service->category) === 'Print' ? 'selected' : '' }}>Print</option>
                        <option value="Fotocopy" {{ old('category', $service->category) === 'Fotocopy' ? 'selected' : '' }}>Fotocopy</option>
                        <option value="Jilid" {{ old('category', $service->category) === 'Jilid' ? 'selected' : '' }}>Jilid</option>
                        <option value="ATK" {{ old('category', $service->category) === 'ATK' ? 'selected' : '' }}>ATK</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Harga Satuan (Rp) *</label>
                    <input type="number" name="price" value="{{ old('price', $service->price) }}" min="0" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Satuan *</label>
                    <input type="text" name="unit" value="{{ old('unit', $service->unit) }}" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Keterangan / Spesifikasi</label>
                <input type="text" name="description" value="{{ old('description', $service->description) }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Ketersediaan</label>
                <select name="is_available" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    <option value="1" {{ old('is_available', $service->is_available ? '1' : '0') == '1' ? 'selected' : '' }}>Tersedia</option>
                    <option value="0" {{ old('is_available', $service->is_available ? '1' : '0') == '0' ? 'selected' : '' }}>Habis / Tidak Tersedia</option>
                </select>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin-fotokopi.services.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</x-layouts.admin-fotokopi>
