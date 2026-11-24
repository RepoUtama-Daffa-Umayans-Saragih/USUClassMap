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

            <!-- Area Upload / Drop Zone File Mahasiswa (Flashdisk / Direct File) -->
            <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50/50">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                        Unggah File Dokumen / Flashdisk (Opsional)
                    </label>
                    <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                        Direct File Preview & Print
                    </span>
                </div>
                <p class="text-xs text-gray-500 mb-3 leading-relaxed">
                    Unggah file cetak yang dibawa mahasiswa melalui flashdisk atau media penyimpanan untuk pratinjau dan cetak langsung di counter kasir.
                </p>

                <!-- Input File Hidden -->
                <input 
                    type="file" 
                    id="fileUploadInput" 
                    name="file" 
                    @change="handleFileChange($event)" 
                    class="hidden" 
                    accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
                >

                <!-- Dropzone Area (Belum Ada File) -->
                <div 
                    x-show="!fileInfo" 
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
                            Pilih file dari flashdisk atau tarik & lepas file ke sini
                        </p>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            Mendukung PDF, Word (DOCX/DOC), PowerPoint, Gambar (Maks 25 MB)
                        </p>
                    </div>
                </div>

                <!-- Preview File Terpilih -->
                <div x-show="fileInfo" class="bg-white rounded-xl p-3 border border-emerald-200 shadow-xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs uppercase text-white shrink-0" :class="fileBadgeColor()">
                            <span x-text="fileInfo ? fileInfo.ext : 'FILE'"></span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-gray-900 truncate" x-text="fileInfo ? fileInfo.name : ''"></p>
                            <p class="text-[11px] text-gray-500">
                                Ukuran: <span class="font-medium" x-text="fileInfo ? fileInfo.size : ''"></span> &bull; Status: <span class="text-emerald-600 font-semibold">Siap Cetak & Simpan</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="removeFile()" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                            Hapus File
                        </button>
                    </div>
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
    </div>

    <script>
        function posApp() {
            return {
                ...usuCatalogFilter(window.usuPosCatalog),
                items: [],
                fileInfo: null,
                dragOver: false,
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
                handleFileChange(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.processFile(file);
                    }
                },
                handleDrop(event) {
                    this.dragOver = false;
                    const file = event.dataTransfer.files[0];
                    if (file) {
                        const input = document.getElementById('fileUploadInput');
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        input.files = dataTransfer.files;
                        this.processFile(file);
                    }
                },
                processFile(file) {
                    const ext = file.name.split('.').pop().toLowerCase();
                    let sizeStr = '';
                    if (file.size >= 1048576) {
                        sizeStr = (file.size / 1048576).toFixed(1) + ' MB';
                    } else {
                        sizeStr = Math.round(file.size / 1024) + ' KB';
                    }
                    this.fileInfo = {
                        name: file.name,
                        size: sizeStr,
                        ext: ext
                    };
                },
                removeFile() {
                    const input = document.getElementById('fileUploadInput');
                    input.value = '';
                    this.fileInfo = null;
                },
                fileBadgeColor() {
                    if (!this.fileInfo) return 'bg-gray-500';
                    const ext = this.fileInfo.ext;
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
