<x-layouts.admin-fotokopi>
    <div class="mb-6">
        <a href="{{ route('admin-fotokopi.services.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-600 transition-colors mb-2">
            &larr; Kembali ke Katalog
        </a>
        <h1 class="text-2xl font-black text-gray-900">Edit Layanan: {{ $service->name }}</h1>
        <p class="text-xs text-gray-500 mt-1">Perbarui rincian harga, stok, kemasan, foto, dan spesifikasi item katalog.</p>
    </div>

    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-xs max-w-2xl">
        <form method="POST" action="{{ route('admin-fotokopi.services.update', $service) }}" enctype="multipart/form-data" class="space-y-5">
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
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Tipe Item *</label>
                    <select name="item_type" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="jasa" {{ old('item_type', $service->item_type ?? 'jasa') === 'jasa' ? 'selected' : '' }}>Jasa Cetak & Jilid</option>
                        <option value="barang" {{ old('item_type', $service->item_type) === 'barang' ? 'selected' : '' }}>Barang ATK & Niche</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kategori Utama *</label>
                    <select id="service-category-select" name="category" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        @foreach(['Print', 'Fotocopy', 'Jilid', 'Finishing', 'Digital', 'ATK'] as $cat)
                            <option value="{{ $cat }}" {{ old('category', $service->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Sub-Kategori</label>
                    <input type="text" name="sub_category" list="subCategoryList" value="{{ old('sub_category', $service->sub_category) }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
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
                    <input type="number" name="price" value="{{ old('price', $service->price) }}" min="0" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Satuan *</label>
                    <input type="text" name="unit" value="{{ old('unit', $service->unit) }}" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Isi Kemasan (Qty)</label>
                    <input type="number" name="pack_qty" value="{{ old('pack_qty', $service->pack_qty ?? 1) }}" min="1" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Stok (Barang)</label>
                    <input type="number" name="stock" value="{{ old('stock', $service->stock) }}" min="0" placeholder="Kosongkan u/ jasa" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Merek (Brand)</label>
                    <input type="text" name="brand" value="{{ old('brand', $service->brand) }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Ketersediaan</label>
                    <select name="is_available" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="1" {{ old('is_available', $service->is_available ? '1' : '0') == '1' ? 'selected' : '' }}>Tersedia</option>
                        <option value="0" {{ old('is_available', $service->is_available ? '1' : '0') == '0' ? 'selected' : '' }}>Habis / Tidak Tersedia</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Keterangan / Spesifikasi (Maks 255 karakter)</label>
                <input type="text" name="description" maxlength="255" value="{{ old('description', $service->description) }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
            </div>

            @php
                $categoryDefaultUrlsResolved = $categoryDefaultUrls ?? \App\Http\Controllers\AdminFotokopi\ServiceController::categoryDefaultUrls();
                $presetImagesResolved = $presetImages ?? \App\Http\Controllers\AdminFotokopi\ServiceController::presetImageOptions();
                $currentImageUrl = $service->image_url ?: ($categoryDefaultUrlsResolved[$service->category] ?? asset('storage/fotocopy_catalog/print-hitam-putih-a4-70gsm.webp'));
            @endphp
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200/80 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Foto Produk / Layanan (JPG/PNG/WEBP Maks 3MB)</label>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Auto-Sync Katalog Publik</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="w-24 h-24 rounded-xl bg-white border border-gray-200 overflow-hidden flex items-center justify-center shrink-0 p-1">
                        <img
                            id="service-image-preview"
                            src="{{ $currentImageUrl }}"
                            alt="{{ $service->name }}"
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
                            <label for="service-image-preset" class="block text-[11px] font-semibold text-gray-600 mb-1">Atau Pilih Gambar Preset Lokal:</label>
                            <select id="service-image-preset" name="image_preset" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2 border bg-white">
                                <option value="">-- Pertahankan Gambar Saat Ini --</option>
                                @foreach($presetImagesResolved as $pImg)
                                    <option value="{{ $pImg['path'] }}" data-url="{{ $pImg['url'] }}" {{ old('image_preset', $service->image_path) === $pImg['path'] ? 'selected' : '' }}>{{ $pImg['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-red-600 cursor-pointer pt-0.5">
                            <input type="checkbox" id="service-remove-image" name="remove_image" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <span>Hapus foto custom &amp; kembali ke gambar default kategori</span>
                        </label>
                        @error('image')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                        @error('image_preset')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            @if(is_array($service->metadata) && count($service->metadata) > 0)
            <div class="p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-100 text-xs">
                <div class="font-bold text-emerald-800 mb-1.5">Metadata Teknis Katalog (Read-Only):</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-[11px] text-gray-700">
                    @foreach($service->metadata as $mKey => $mVal)
                        <div><span class="font-semibold capitalize">{{ str_replace('_', ' ', $mKey) }}:</span> {{ $mVal }}</div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin-fotokopi.services.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const catSelect = document.getElementById('service-category-select');
            const imgInput = document.getElementById('service-image-input');
            const presetSelect = document.getElementById('service-image-preset');
            const removeCheckbox = document.getElementById('service-remove-image');
            const previewEl = document.getElementById('service-image-preview');
            const categoryDefaultUrls = @json($categoryDefaultUrlsResolved);
            const initialServiceUrl = @json($currentImageUrl);
            let activeObjectUrl = null;

            function refreshPreview() {
                if (!previewEl) return;
                if (imgInput && imgInput.files && imgInput.files[0]) {
                    return;
                }
                if (removeCheckbox && removeCheckbox.checked) {
                    const cat = catSelect ? catSelect.value : 'Print';
                    previewEl.src = categoryDefaultUrls[cat] || categoryDefaultUrls.Print || initialServiceUrl;
                    return;
                }
                if (presetSelect && presetSelect.value) {
                    const opt = presetSelect.options[presetSelect.selectedIndex];
                    if (opt && opt.dataset && opt.dataset.url) {
                        previewEl.src = opt.dataset.url;
                        return;
                    }
                }
                previewEl.src = initialServiceUrl;
            }

            if (imgInput) {
                imgInput.addEventListener('change', () => {
                    if (activeObjectUrl) {
                        URL.revokeObjectURL(activeObjectUrl);
                        activeObjectUrl = null;
                    }
                    const file = imgInput.files && imgInput.files[0];
                    if (file && previewEl) {
                        if (removeCheckbox) removeCheckbox.checked = false;
                        activeObjectUrl = URL.createObjectURL(file);
                        previewEl.src = activeObjectUrl;
                    } else {
                        refreshPreview();
                    }
                });
            }

            if (presetSelect) {
                presetSelect.addEventListener('change', () => {
                    if (presetSelect.value && removeCheckbox) {
                        removeCheckbox.checked = false;
                    }
                    refreshPreview();
                });
            }

            if (removeCheckbox) {
                removeCheckbox.addEventListener('change', () => {
                    if (removeCheckbox.checked) {
                        if (activeObjectUrl) {
                            URL.revokeObjectURL(activeObjectUrl);
                            activeObjectUrl = null;
                        }
                        if (imgInput) imgInput.value = '';
                        if (presetSelect) presetSelect.value = '';
                    }
                    refreshPreview();
                });
            }

            if (catSelect) {
                catSelect.addEventListener('change', () => {
                    if (removeCheckbox && removeCheckbox.checked) {
                        refreshPreview();
                    }
                });
            }
        });
    </script>
</x-layouts.admin-fotokopi>
