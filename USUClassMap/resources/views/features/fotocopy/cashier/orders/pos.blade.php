<x-layouts.cashier-fotokopi>
    <div class="mb-6">
        <a href="{{ route('cashier-fotokopi.orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-700 transition-colors mb-2">
            &larr; Kembali ke Antrean Pesanan
        </a>
        <h1 class="text-2xl font-black text-gray-900">Input Kasir (Point of Sale Walk-in)</h1>
        <p class="text-xs text-gray-500 mt-1">Catat transaksi langsung mahasiswa di meja kasir {{ $myStore->name ?? 'Toko' }}.</p>
    </div>

    @include('features.fotocopy.partials.catalog-filter-script')
    <script>
        window.usuPosCatalog = {!! json_encode($catalog ?? [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!};
    </script>

    @push('scripts')
        @vite(['resources/js/features/fotocopy-pdf-uploader.js'])
    @endpush

    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-xs max-w-5xl" x-data="posApp()">
        <form method="POST" action="{{ route('cashier-fotokopi.orders.pos.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Mahasiswa / Pelanggan *</label>
                    <input type="text" name="customer_name" placeholder="Contoh: Rian Pratama (NIM 221402...)" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">No. WhatsApp / HP (Opsional)</label>
                    <input type="text" name="customer_phone" placeholder="081234567890" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <!-- Pilih Item Layanan & Barang ATK -->
            <div class="space-y-3">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Pilih Item Layanan & Barang ATK:</label>

                @include('features.fotocopy.partials.catalog-filter-bar')

                <template x-if="catalogFiltered().length === 0">
                    <div class="p-6 text-center rounded-xl border border-dashed border-gray-200 text-xs text-gray-400">
                        Tidak ada item katalog yang sesuai filter pencarian.
                    </div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[28rem] overflow-y-auto pr-1" x-show="catalogFiltered().length > 0">
                    <template x-for="srv in catalogFiltered()" :key="srv.id">
                        <button
                            type="button"
                            @click="addItem(srv.id, srv.name, srv.price, srv.unit)"
                            class="p-3 rounded-xl border border-gray-200 hover:border-emerald-500 hover:bg-emerald-50/40 text-left transition-all flex items-start gap-2.5 cursor-pointer bg-white"
                        >
                            <div class="w-12 h-12 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                                <template x-if="srv.image_url && !srv._imgError">
                                    <img
                                        :src="srv.image_url"
                                        :alt="srv.name"
                                        loading="lazy"
                                        x-on:error="srv._imgError = true"
                                        class="w-full h-full object-contain p-0.5"
                                    >
                                </template>
                                <template x-if="!srv.image_url || srv._imgError">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1 mb-0.5">
                                    <span
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded-full"
                                        :class="srv.item_type === 'barang' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700'"
                                        x-text="srv.type_label"
                                    ></span>
                                    <template x-if="srv.brand">
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700" x-text="srv.brand"></span>
                                    </template>
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600" x-text="srv.pack_label"></span>
                                </div>

                                <span class="text-xs font-bold text-gray-800 block line-clamp-2 leading-snug" x-text="srv.name"></span>
                                <span class="text-[10px] text-gray-400 block truncate" x-show="metadataSummary(srv.metadata)" x-text="metadataSummary(srv.metadata)"></span>

                                <div class="flex items-center justify-between mt-1.5 pt-1 border-t border-gray-100">
                                    <span class="text-xs font-extrabold text-[#10B981]" x-text="'Rp ' + formatRupiah(srv.price)"></span>
                                    <span class="text-[10px] text-gray-400" x-text="'/' + srv.unit"></span>
                                </div>
                                <template x-if="srv.item_type === 'barang' && srv.stock !== null">
                                    <span
                                        class="text-[9px] font-semibold block"
                                        :class="srv.stock <= 5 ? 'text-red-600' : 'text-gray-400'"
                                        x-text="'Stok: ' + srv.stock"
                                    ></span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Daftar Item yang Dipilih -->
            <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50/50">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">Item yang Dipesan:</h3>
                
                <template x-if="items.length === 0">
                    <p class="text-xs text-gray-400 italic py-2">Klik tombol layanan di atas untuk menambahkan ke nota kasir.</p>
                </template>

                <div class="space-y-2">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-gray-100 text-xs">
                            <input type="hidden" :name="'items[' + index + '][service_id]'" :value="item.service_id">
                            <div class="flex-1">
                                <span class="font-bold text-gray-900" x-text="item.name"></span>
                                <span class="text-gray-400 text-[11px]" x-text="' @ Rp ' + formatRupiah(item.price)"></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                    <button type="button" @click="if(item.quantity > 1) item.quantity--" class="px-2 py-0.5 hover:bg-gray-100 text-gray-600 font-bold">-</button>
                                    <input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" min="1" class="w-12 text-center text-xs py-0.5 border-none focus:ring-0">
                                    <button type="button" @click="item.quantity++" class="px-2 py-0.5 hover:bg-gray-100 text-gray-600 font-bold">+</button>
                                </div>
                                <span class="font-black text-gray-900 min-w-16 text-right" x-text="'Rp ' + formatRupiah(item.price * item.quantity)"></span>
                                <button type="button" @click="items.splice(index, 1)" class="text-red-400 hover:text-red-600 p-1">✕</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
                    <span class="text-xs font-bold text-gray-600">Total Tagihan Kasir:</span>
                    <span class="text-lg font-black text-[#10B981]" x-text="'Rp ' + formatRupiah(calculateTotal())"></span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Metode Pembayaran *</label>
                    <select name="payment_method" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="Tunai">Tunai (Cash di Counter)</option>
                        <option value="QRIS">QRIS (Scan Statis/Dinamis)</option>
                        <option value="Transfer">Transfer Bank</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Awal Pesanan *</label>
                    <select name="order_status" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="Selesai">Langsung Selesai & Lunas (Cetak Langsung Jadi)</option>
                        <option value="Diproses">Sedang Diproses (Ditinggal / Ambil Nanti)</option>
                    </select>
                </div>
            </div>

            <!-- Area Upload Multi-PDF / Dokumen Flashdisk -->
            <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50/50 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                        Unggah Multi-File Dokumen / Flashdisk (Opsional)
                    </label>
                    <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                        Multi-PDF + Auto Page Count + Note per File
                    </span>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">
                    Pilih satu atau beberapa file PDF/dokumen dari flashdisk pelanggan. File PDF otomatis dihitung jumlah halamannya dan dilengkapi kolom catatan khusus per file.
                </p>

                <input
                    type="file"
                    id="fileUploadInput"
                    name="pos_files[]"
                    multiple
                    @change="handleFileChange($event)"
                    class="hidden"
                    accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
                >

                <div
                    @click="document.getElementById('fileUploadInput').click()"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop($event)"
                    :class="dragOver ? 'border-[#10B981] bg-emerald-50/60 ring-2 ring-emerald-400' : 'border-gray-300 hover:border-emerald-500 hover:bg-emerald-50/20'"
                    class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2 group"
                >
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-[#10B981] flex items-center justify-center group-hover:scale-110 transition-transform shadow-xs">
                        <svg class="w-5 h-5 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-700 group-hover:text-emerald-700">
                            Pilih banyak file dari flashdisk atau tarik & lepas ke sini
                        </p>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            Mendukung Multi-PDF (auto halaman), Word, PowerPoint, Excel, Gambar (Maks 25 MB/file)
                        </p>
                    </div>
                </div>

                <!-- Daftar File Terpilih -->
                <div class="space-y-2.5" x-show="uploadedFiles.length > 0" x-cloak>
                    <template x-for="(f, idx) in uploadedFiles" :key="f.uid">
                        <div class="bg-white rounded-xl p-3 border border-emerald-200 shadow-xs space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs uppercase text-white shrink-0" :class="fileBadgeColor(f.ext)">
                                        <span x-text="f.ext"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-900 truncate" x-text="f.name"></p>
                                        <p class="text-[11px] text-gray-500">
                                            <span x-text="f.size"></span> &bull;
                                            <span class="text-emerald-600 font-semibold" x-text="f.pageCount + ' halaman'"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        x-show="f.previewUrl"
                                        @click="openFilePreview(f)"
                                        class="px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition-colors cursor-pointer inline-flex items-center gap-1"
                                    >
                                        <i class="fa-regular fa-eye"></i>
                                        <span>Preview</span>
                                    </button>
                                    <button type="button" @click="removeFile(idx)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                                        Hapus
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 block mb-0.5">Halaman</label>
                                    <input type="number" :name="'pos_file_pages[' + idx + ']'" x-model.number="f.pageCount" min="1" class="w-full text-xs rounded-lg border-gray-200 py-1.5 px-2.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 block mb-0.5">Rangkap</label>
                                    <input type="number" :name="'pos_file_copies[' + idx + ']'" x-model.number="f.copies" min="1" class="w-full text-xs rounded-lg border-gray-200 py-1.5 px-2.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 block mb-0.5">Note Khusus File Ini</label>
                                    <input type="text" :name="'pos_file_notes[' + idx + ']'" x-model="f.note" placeholder="Contoh: Jilid lakban mika biru" class="w-full text-xs rounded-lg border-gray-200 py-1.5 px-2.5">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Catatan Pesanan</label>
                <input type="text" name="notes" placeholder="Contoh: Kertas 80gr, jilid lakban hitam rangkap 3" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('cashier-fotokopi.orders.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" :disabled="items.length === 0" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">
                    Simpan Transaksi Kasir
                </button>
            </div>
        </form>

        <!-- POS In-Page File Preview Modal -->
        <div
            x-show="previewModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.escape.window="closeFilePreview()"
        >
            <div @click.away="closeFilePreview()" class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] font-black uppercase" x-text="(previewFile.ext || 'PDF').toUpperCase()"></span>
                            <h3 class="text-xs sm:text-sm font-black truncate" x-text="previewFile.name"></h3>
                        </div>
                        <p class="text-[11px] text-slate-300 mt-0.5" x-text="previewFile.size + ' • ' + previewFile.pageCount + ' halaman'"></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            :href="previewFile.url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition"
                        >
                            <span>Buka di Tab Baru</span>
                        </a>
                        <button type="button" @click="closeFilePreview()" class="text-slate-300 hover:text-white px-2.5 py-1 text-lg font-bold cursor-pointer">&times;</button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-800 min-h-[65vh] flex items-center justify-center">
                    <template x-if="previewModalOpen && previewFile.url && ['jpg', 'jpeg', 'png', 'webp'].includes(previewFile.ext)">
                        <img :src="previewFile.url" :alt="previewFile.name" class="max-h-[70vh] max-w-full object-contain bg-white" />
                    </template>
                    <template x-if="previewModalOpen && previewFile.url && previewFile.ext === 'pdf'">
                        <iframe :src="previewFile.url" class="w-full h-[70vh] border-0 bg-white" title="Pratinjau PDF POS"></iframe>
                    </template>
                    <template x-if="previewModalOpen && previewFile.url && !['pdf', 'jpg', 'jpeg', 'png', 'webp'].includes(previewFile.ext)">
                        <div class="p-8 text-center text-white space-y-3 text-xs">
                            <p>Browser tidak mendukung pratinjau langsung format <span class="font-bold uppercase" x-text="previewFile.ext"></span>.</p>
                            <a :href="previewFile.url" target="_blank" rel="noopener" class="inline-block px-4 py-2 rounded-xl bg-emerald-600 font-bold">Buka / Unduh File</a>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function posApp() {
            return {
                ...usuCatalogFilter(window.usuPosCatalog),
                items: [],
                uploadedFiles: [],
                dragOver: false,
                previewModalOpen: false,
                previewFile: { name: '', size: '', pageCount: 1, url: '', ext: 'pdf' },
                init() {
                    window.addEventListener('beforeunload', () => {
                        this.uploadedFiles.forEach(entry => this.revokeFilePreview(entry));
                    });
                },
                revokeFilePreview(entry) {
                    if (entry && entry.previewUrl) {
                        try {
                            URL.revokeObjectURL(entry.previewUrl);
                        } catch (e) {}
                        entry.previewUrl = '';
                    }
                },
                openFilePreview(entry) {
                    if (!entry || !entry.previewUrl) return;
                    this.previewFile = {
                        name: entry.name || 'Dokumen',
                        size: entry.size || '',
                        pageCount: Number(entry.pageCount || 1),
                        url: entry.previewUrl,
                        ext: (entry.ext || 'pdf').toLowerCase()
                    };
                    this.previewModalOpen = true;
                },
                closeFilePreview() {
                    this.previewModalOpen = false;
                },
                addItem(id, name, price, unit) {
                    const existing = this.items.find(i => i.service_id === id);
                    if (existing) {
                        existing.quantity++;
                    } else {
                        this.items.push({ service_id: id, name, price, unit, quantity: 1 });
                    }
                },
                calculateTotal() {
                    return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },
                formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID').format(num);
                },
                syncFileInput() {
                    const input = document.getElementById('fileUploadInput');
                    const dataTransfer = new DataTransfer();
                    this.uploadedFiles.forEach(entry => {
                        if (entry.file) {
                            dataTransfer.items.add(entry.file);
                        }
                    });
                    input.files = dataTransfer.files;
                },
                handleFileChange(event) {
                    const files = Array.from(event.target.files || []);
                    if (files.length > 0) {
                        this.enqueuePosFiles(files);
                    }
                },
                handleDrop(event) {
                    this.dragOver = false;
                    const files = Array.from(event.dataTransfer?.files || []);
                    if (files.length > 0) {
                        this.enqueuePosFiles(files);
                    }
                },
                async enqueuePosFiles(files) {
                    for (const file of files) {
                        if (this.uploadedFiles.length >= 10) break;
                        const ext = (file.name.split('.').pop() || '').toLowerCase();
                        const sizeStr = file.size >= 1048576
                            ? (file.size / 1048576).toFixed(1) + ' MB'
                            : Math.max(1, Math.round(file.size / 1024)) + ' KB';

                        const uid = 'pos-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
                        let pageCount = 1;
                        if (ext === 'pdf' && typeof window.usuInspectPdfFile === 'function') {
                            const info = await window.usuInspectPdfFile(file);
                            if (info && info.valid && info.pageCount) {
                                pageCount = info.pageCount;
                            }
                        }

                        const previewUrl = typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : '';

                        this.uploadedFiles.push({
                            uid,
                            file,
                            previewUrl,
                            name: file.name,
                            size: sizeStr,
                            ext,
                            pageCount,
                            copies: 1,
                            note: ''
                        });
                    }
                    this.syncFileInput();
                },
                removeFile(idx) {
                    this.revokeFilePreview(this.uploadedFiles[idx]);
                    this.uploadedFiles.splice(idx, 1);
                    this.syncFileInput();
                },
                fileBadgeColor(ext) {
                    if (['pdf'].includes(ext)) return 'bg-red-500';
                    if (['doc', 'docx'].includes(ext)) return 'bg-blue-600';
                    if (['ppt', 'pptx'].includes(ext)) return 'bg-amber-500';
                    if (['jpg', 'jpeg', 'png'].includes(ext)) return 'bg-emerald-600';
                    return 'bg-purple-600';
                }
            };
        }
    </script>
</x-layouts.cashier-fotokopi>
