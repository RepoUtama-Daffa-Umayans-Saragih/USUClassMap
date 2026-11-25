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
                    <select id="service-category-select" name="category" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
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

            @php
                $categoryDefaultUrlsResolved = $categoryDefaultUrls ?? \App\Http\Controllers\AdminFotokopi\ServiceController::categoryDefaultUrls();
                $presetImagesResolved = $presetImages ?? \App\Http\Controllers\AdminFotokopi\ServiceController::presetImageOptions();
                $initialCat = old('category', 'Print');
                $initialPreviewUrl = $categoryDefaultUrlsResolved[$initialCat] ?? asset('storage/fotocopy_catalog/print-hitam-putih-a4-70gsm.webp');
            @endphp
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200/80 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Foto Produk / Layanan (Opsional, JPG/PNG/WEBP Maks 3MB)</label>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Auto-Fallback Kategori</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 items-start">
                    <div class="w-24 h-24 rounded-xl bg-white border border-gray-200 overflow-hidden flex items-center justify-center shrink-0 p-1">
                        <img
                            id="service-image-preview"
                            src="{{ $initialPreviewUrl }}"
                            alt="Preview Gambar Produk"
                            class="w-full h-full object-contain"
                        >
                    </div>
                    <div class="flex-1 w-full space-y-2.5">
                        <input
                            type="file"
                            id="service-image-input"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                            class="w-full text-xs rounded-xl border border-gray-200 bg-white p-2 cursor-pointer"
                        >
                        <div>
                            <label for="service-image-preset" class="block text-[11px] font-semibold text-gray-600 mb-1">Atau Pilih Gambar Preset Lokal (Jika Tidak Upload):</label>
                            <select id="service-image-preset" name="image_preset" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2 border bg-white">
                                <option value="">-- Otomatis Sesuai Kategori --</option>
                                @foreach($presetImagesResolved as $pImg)
                                    <option value="{{ $pImg['path'] }}" data-url="{{ $pImg['url'] }}" {{ old('image_preset') === $pImg['path'] ? 'selected' : '' }}>{{ $pImg['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" id="service-reset-image-btn" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 cursor-pointer">
                            &olarr; Reset ke Gambar Default Kategori
                        </button>
                        @error('image')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                        @error('image_preset')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin-fotokopi.services.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">Simpan Layanan</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const catSelect = document.getElementById('service-category-select');
            const imgInput = document.getElementById('service-image-input');
            const presetSelect = document.getElementById('service-image-preset');
            const resetBtn = document.getElementById('service-reset-image-btn');
            const previewEl = document.getElementById('service-image-preview');
            const categoryDefaultUrls = @json($categoryDefaultUrlsResolved);
            let activeObjectUrl = null;

            function resolveDefaultOrPreset() {
                if (presetSelect && presetSelect.value) {
                    const opt = presetSelect.options[presetSelect.selectedIndex];
                    if (opt && opt.dataset && opt.dataset.url) {
                        return opt.dataset.url;
                    }
                }
                const cat = catSelect ? catSelect.value : 'Print';
                return categoryDefaultUrls[cat] || categoryDefaultUrls.Print || '';
            }

            function refreshPreview() {
                if (!previewEl) return;
                if (imgInput && imgInput.files && imgInput.files[0]) {
                    return;
                }
                previewEl.src = resolveDefaultOrPreset();
            }

            if (imgInput) {
                imgInput.addEventListener('change', () => {
                    if (activeObjectUrl) {
                        URL.revokeObjectURL(activeObjectUrl);
                        activeObjectUrl = null;
                    }
                    const file = imgInput.files && imgInput.files[0];
                    if (file && previewEl) {
                        activeObjectUrl = URL.createObjectURL(file);
                        previewEl.src = activeObjectUrl;
                    } else {
                        refreshPreview();
                    }
                });
            }

            if (presetSelect) {
                presetSelect.addEventListener('change', refreshPreview);
            }

            if (catSelect) {
                catSelect.addEventListener('change', refreshPreview);
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (activeObjectUrl) {
                        URL.revokeObjectURL(activeObjectUrl);
                        activeObjectUrl = null;
                    }
                    if (imgInput) imgInput.value = '';
                    if (presetSelect) presetSelect.value = '';
                    refreshPreview();
                });
            }
        });
    </script>
</x-layouts.admin-fotokopi>
