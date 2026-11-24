<x-layouts.admin-fotokopi>
    <div class="mb-6">
        <a href="{{ route('admin-fotokopi.services.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-600 transition-colors mb-2">
            &larr; Kembali ke Katalog
        </a>
        <h1 class="text-2xl font-black text-gray-900">Tambah Item Layanan / Produk ATK</h1>
        <p class="text-xs text-gray-500 mt-1">Daftarkan jasa cetak atau produk ATK eceran/paket baru beserta harga, stok, dan foto produk.</p>
    </div>

    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-xs max-w-2xl">
        <form method="POST" action="{{ route('admin-fotokopi.services.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Pilih Mitra Toko *</label>
                <select name="store_id" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    <option value="">-- Pilih Toko --</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ old('store_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
                @error('store_id')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Layanan / Produk *</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Pulpen Standard AE7 0.5mm Hitam" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                @error('name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Tipe Item *</label>
                    <select name="item_type" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="jasa" {{ old('item_type', 'jasa') === 'jasa' ? 'selected' : '' }}>Jasa Cetak & Jilid</option>
                        <option value="barang" {{ old('item_type') === 'barang' ? 'selected' : '' }}>Barang ATK & Niche</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kategori Utama *</label>
                    <select name="category" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        @foreach(['Print', 'Fotocopy', 'Jilid', 'Finishing', 'Digital', 'ATK'] as $cat)
                            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Sub-Kategori</label>
                    <input type="text" name="sub_category" list="subCategoryList" value="{{ old('sub_category') }}" placeholder="Misal: Pulpen / Print Warna" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    <datalist id="subCategoryList">
                        @foreach($subCategories ?? [] as $sub)
                            <option value="{{ $sub }}">
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Harga Satuan (Rp) *</label>
                    <input type="number" name="price" value="{{ old('price', 200) }}" min="0" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Satuan *</label>
                    <input type="text" name="unit" value="{{ old('unit', 'lembar') }}" placeholder="lembar / pcs / pak (12 pcs)" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Isi Kemasan (Qty)</label>
                    <input type="number" name="pack_qty" value="{{ old('pack_qty', 1) }}" min="1" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Stok (Barang)</label>
                    <input type="number" name="stock" value="{{ old('stock') }}" min="0" placeholder="Kosongkan u/ jasa" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Merek (Brand)</label>
                    <input type="text" name="brand" value="{{ old('brand') }}" placeholder="Contoh: Standard, Pilot, Joyko, PaperOne" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Ketersediaan</label>
                    <select name="is_available" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="1" {{ old('is_available', '1') == '1' ? 'selected' : '' }}>Tersedia</option>
                        <option value="0" {{ old('is_available') == '0' ? 'selected' : '' }}>Habis / Tidak Tersedia</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Keterangan / Spesifikasi (Maks 255 karakter)</label>
                <input type="text" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Contoh: Kertas 80gr PaperOne, hasil tajam anti luntur" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Foto Produk / Layanan (Opsional, JPG/PNG/WEBP Maks 2MB)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="w-full text-xs rounded-xl border border-gray-200 p-2 bg-gray-50">
                @error('image')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin-fotokopi.services.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">Simpan Layanan</button>
            </div>
        </form>
    </div>
</x-layouts.admin-fotokopi>
