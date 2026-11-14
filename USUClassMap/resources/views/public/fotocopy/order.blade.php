<x-layouts.app>
    <x-slot name="title">Pesan Layanan - {{ $place->name }}</x-slot>

    <div
        class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6"
        x-data="{
            cart: {},
            customerName: '',
            customerPhone: '',
            orderNotes: '',
            paymentMethod: 'qris',
            selectedBank: 'BCA',
            submitting: false,
            placeName: '{{ addslashes($place->name) }}',
            placeWa: '{{ $place->whatsapp }}',

            addItem(item) {
                if (this.cart[item.id]) {
                    this.cart[item.id].qty += 1;
                } else {
                    this.cart[item.id] = {
                        id: item.id,
                        name: item.name,
                        price: item.price,
                        unit: item.unit,
                        qty: 1
                    };
                }
            },

            removeItem(id) {
                if (this.cart[id]) {
                    if (this.cart[id].qty > 1) {
                        this.cart[id].qty -= 1;
                    } else {
                        delete this.cart[id];
                    }
                }
            },

            deleteItem(id) {
                delete this.cart[id];
            },

            getItemCount() {
                return Object.values(this.cart).reduce((sum, item) => sum + item.qty, 0);
            },

            getSubtotal() {
                return Object.values(this.cart).reduce((sum, item) => sum + (item.price * item.qty), 0);
            },

            formatRupiah(number) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
            },

            submitOrder() {
                if (this.getItemCount() === 0) {
                    alert('Keranjang belanja masih kosong! Silakan pilih item yang ingin dipesan.');
                    return;
                }

                if (!this.customerName.trim()) {
                    alert('Silakan masukkan nama pemesan terlebih dahulu.');
                    return;
                }

                this.submitting = true;

                // Map paymentMethod to strategy keys: 'qris', 'bank_transfer', 'cash'
                let backendMethod = 'qris';
                let paymentChannel = null;

                if (this.paymentMethod === 'cod') {
                    backendMethod = 'cash';
                    paymentChannel = 'CASHIER';
                } else if (this.paymentMethod === 'transfer') {
                    backendMethod = 'bank_transfer';
                    paymentChannel = this.selectedBank || 'BCA';
                } else {
                    backendMethod = 'qris';
                    paymentChannel = 'QRIS';
                }

                let orderItems = Object.values(this.cart).map(item => ({
                    service_id: item.id,
                    name: item.name,
                    price: item.price,
                    quantity: item.qty,
                    category: 'Fotocopy'
                }));

                fetch('{{ route('fotocopy.order.store', $place->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        customer_name: this.customerName,
                        customer_phone: this.customerPhone || null,
                        notes: this.orderNotes || null,
                        payment_method: backendMethod,
                        payment_channel: paymentChannel,
                        items: orderItems
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.payment_url) {
                        window.location.href = data.payment_url;
                    } else {
                        alert(data.message || 'Terjadi kesalahan saat memproses pesanan.');
                        this.submitting = false;
                    }
                })
                .catch(err => {
                    console.error('Error submitting order:', err);
                    alert('Gagal menghubungi server. Silakan coba lagi.');
                    this.submitting = false;
                });
            }
        }"
    >
        <!-- Breadcrumb -->
        <nav class="flex items-center text-xs text-gray-500 gap-2 mb-2 overflow-x-auto whitespace-nowrap pb-1">
            <a href="/" class="hover:text-[#10A760]">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760]">Fotocopy</a>
            <span>/</span>
            <a href="{{ route('fotocopy.show', $place->id) }}" class="hover:text-[#10A760]">{{ $place->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-semibold">Pemesanan</span>
        </nav>

        <!-- Header Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 sm:gap-4">
                @if($place->image_url)
                    <img src="{{ $place->image_url }}" alt="{{ $place->name }}" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl object-cover shrink-0 shadow-xs border border-gray-100">
                @else
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#E8F5EE] text-[#10A760] rounded-2xl flex items-center justify-center font-bold text-xl sm:text-2xl shrink-0">
                        <i class="fa-solid fa-print"></i>
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-lg sm:text-2xl font-black text-gray-900 leading-tight">{{ $place->name }}</h1>
                        <span class="{{ $isOpen ? 'bg-[#10A760]' : 'bg-gray-400' }} text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                            {{ $isOpen ? 'Buka' : 'Tutup' }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[#10A760]"></i>
                            <span>{{ $place->location_name }}</span>
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1">
                            <i class="fa-regular fa-clock text-[#10A760]"></i>
                            <span>{{ $place->open_time }} - {{ $place->close_time }}</span>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Lokasi Button -->
            <a
                href="{{ $place->getDirectionUrl() }}"
                target="_blank"
                class="inline-flex items-center gap-2 bg-[#D4A017] hover:bg-[#B8880F] text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs w-full sm:w-auto justify-center"
            >
                <i class="fa-solid fa-location-dot text-xs"></i>
                <span>Pergi ke Maps</span>
            </a>
        </div>

        <!-- Main Layout: Items Grid & Sticky Cart -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT 2 COLS: Card Item & Harga Item -->
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-gray-900 flex items-center gap-2 mb-1">
                        <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                        <span>Pilih Item Layanan</span>
                    </h2>
                    <p class="text-xs text-gray-500">Klik "+ Tambah" untuk memasukkan item ke keranjang pesanan</p>
                </div>

                @foreach($itemsByCategory as $category => $items)
                <div class="space-y-3">
                    <h3 class="font-bold text-xs text-[#0D8A4E] uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#10A760]"></span>
                        <span>{{ $category }}</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-3.5">
                        @foreach($items as $item)
                        <!-- Card Item -->
                        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs hover:border-[#10A760] transition-all flex flex-col justify-between">
                            <div class="mb-3">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <h4 class="font-extrabold text-gray-900 text-sm leading-snug">{{ $item->name }}</h4>
                                    <span class="text-[10px] font-bold text-[#D4A017] bg-amber-50 px-2 py-0.5 rounded-full shrink-0">
                                        {{ $item->category }}
                                    </span>
                                </div>
                                @if($item->description)
                                    <p class="text-xs text-gray-500 line-clamp-2">{{ $item->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                <div>
                                    <span class="text-xs sm:text-sm font-black text-[#10A760]">{{ $item->formatted_price }}</span>
                                    <span class="text-[10px] text-gray-400">/ {{ $item->unit }}</span>
                                </div>

                                <button
                                    type="button"
                                    @click="addItem({ id: {{ $item->id }}, name: '{{ addslashes($item->name) }}', price: {{ $item->price }}, unit: '{{ $item->unit }}' })"
                                    class="bg-[#10A760] hover:bg-[#0D8A4E] text-white px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-2xs cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Tambah</span>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            <!-- RIGHT 1 COL: Keranjang, Pembayaran, Pesan & Lokasi -->
            <div class="space-y-6">

                <!-- KERANJANG CARD -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 sticky top-20 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-cart-shopping text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm sm:text-base">Keranjang Pesanan</h3>
                        </div>
                        <span class="text-xs font-bold text-[#10A760] bg-green-50 px-2.5 py-0.5 rounded-full" x-text="getItemCount() + ' Item'"></span>
                    </div>

                    <!-- Cart Items List -->
                    <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                        <template x-if="Object.keys(cart).length === 0">
                            <div class="text-center py-8 text-gray-400 text-xs">
                                <i class="fa-solid fa-cart-shopping text-2xl text-gray-300 mb-2 block"></i>
                                Keranjang masih kosong.<br>Pilih item di sebelah kiri untuk memesan.
                            </div>
                        </template>

                        <template x-for="item in Object.values(cart)" :key="item.id">
                            <div class="flex items-center justify-between bg-gray-50 p-2.5 rounded-xl border border-gray-200/60 text-xs">
                                <div class="flex-1 pr-2">
                                    <div class="font-bold text-gray-800" x-text="item.name"></div>
                                    <div class="text-[11px] text-[#10A760] font-semibold" x-text="formatRupiah(item.price * item.qty)"></div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="removeItem(item.id)"
                                        class="w-6 h-6 rounded-md bg-white border border-gray-300 text-gray-700 font-bold flex items-center justify-center hover:bg-gray-100 cursor-pointer"
                                    >-</button>
                                    <span class="w-6 text-center font-bold text-gray-900" x-text="item.qty"></span>
                                    <button
                                        type="button"
                                        @click="addItem(item)"
                                        class="w-6 h-6 rounded-md bg-[#10A760] text-white font-bold flex items-center justify-center hover:bg-[#0D8A4E] cursor-pointer"
                                    >+</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Input Data Pemesan -->
                    <div class="space-y-1.5 pt-2 border-t border-gray-100">
                        <label class="block text-[11px] font-bold text-gray-700">Nama Pemesan (Opsional):</label>
                        <input
                            type="text"
                            x-model="customerName"
                            placeholder="Contoh: Budi Santoso (NIM 21140...)"
                            class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                        />
                    </div>

                    <!-- Catatan Pesanan -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-gray-700">Catatan Khusus:</label>
                        <textarea
                            x-model="orderNotes"
                            rows="2"
                            placeholder="Contoh: Tolong rangkap 2, jilid lakban hitam, kertas 80gr"
                            class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                        ></textarea>
                    </div>

                    <!-- PEMBAYARAN -->
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-gray-700">Metode Pembayaran:</label>
                            <span class="text-[10px] text-gray-400 font-medium">Pilih salah satu</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 text-[11px]">
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'qris' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="qris" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-qrcode text-base mb-1"></i>
                                <span>QRIS</span>
                                <span class="text-[9px] font-normal text-emerald-600 mt-0.5">Otomatis</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'transfer' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="transfer" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-building-columns text-base mb-1"></i>
                                <span>Bank Transfer</span>
                                <span class="text-[9px] font-normal text-blue-600 mt-0.5">Virtual Acc</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'cod' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="cod" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-money-bill-wave text-base mb-1"></i>
                                <span>Tunai</span>
                                <span class="text-[9px] font-normal text-amber-600 mt-0.5">Di Kasir</span>
                            </label>
                        </div>

                        <!-- Sub-opsi Bank Transfer -->
                        <div x-show="paymentMethod === 'transfer'" x-cloak class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-slate-700">Pilih Bank Tujuan:</span>
                                <span class="text-[10px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold border border-emerald-200">PoC Static VA</span>
                            </div>
                            <div class="grid grid-cols-5 gap-1 text-[11px] font-semibold text-center">
                                <button type="button" @click="selectedBank = 'BCA'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BCA' ? 'bg-[#005EAA] text-white border-[#005EAA] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BCA</button>
                                <button type="button" @click="selectedBank = 'Mandiri'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'Mandiri' ? 'bg-[#003366] text-white border-[#003366] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">Mandiri</button>
                                <button type="button" @click="selectedBank = 'BNI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BNI' ? 'bg-[#E05A2B] text-white border-[#E05A2B] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BNI</button>
                                <button type="button" @click="selectedBank = 'BRI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BRI' ? 'bg-[#00529C] text-white border-[#00529C] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BRI</button>
                                <button type="button" @click="selectedBank = 'BSI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BSI' ? 'bg-[#00A39D] text-white border-[#00A39D] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BSI</button>
                            </div>
                            <div class="text-[10px] text-gray-500 flex items-center gap-1.5 pt-1">
                                <i class="fa-solid fa-circle-info text-blue-500"></i>
                                <span>No. VA: <strong class="font-mono text-gray-700">88019920260001</strong> a.n. USUClassMap Fotokopi</span>
                            </div>
                        </div>

                        <!-- Info singkat QRIS -->
                        <div x-show="paymentMethod === 'qris'" x-cloak class="p-2 bg-emerald-50/60 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center gap-2">
                            <i class="fa-solid fa-qrcode text-emerald-600 text-sm"></i>
                            <span>QR Code EMVCo unik akan otomatis digenerate saat klik lanjut pembayaran.</span>
                        </div>

                        <!-- Info singkat Tunai -->
                        <div x-show="paymentMethod === 'cod'" x-cloak class="p-2 bg-amber-50/70 border border-amber-100 rounded-xl text-[11px] text-amber-800 flex items-center gap-2">
                            <i class="fa-solid fa-store text-amber-600 text-sm"></i>
                            <span>Bayar tunai langsung di kasir toko saat mengambil dokumen/cetakan Anda.</span>
                        </div>
                    </div>

                    <!-- Subtotal & Total -->
                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-600">Total Harga:</span>
                        <span class="text-base sm:text-lg font-black text-[#10A760]" x-text="formatRupiah(getSubtotal())"></span>
                    </div>

                    <!-- BUTTONS: PESAN & LOKASI (SEJAJAR) -->
                    <div class="pt-2 flex items-center gap-2">
                        <!-- Button Pesan & Bayar -->
                        <button
                            type="button"
                            @click="submitOrder()"
                            :disabled="submitting"
                            class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <i class="fa-solid fa-spinner fa-spin" x-show="submitting"></i>
                            <i class="fa-solid fa-credit-card" x-show="!submitting"></i>
                            <span x-text="submitting ? 'Memproses Pesanan...' : 'Lanjut ke Pembayaran'"></span>
                        </button>

                        <!-- Button Lokasi (Sejajar dengan Button Pesan -> Ke Maps) -->
                        <a
                            href="{{ $place->getDirectionUrl() }}"
                            target="_blank"
                            class="bg-[#D4A017] hover:bg-[#B8880F] text-white px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs font-bold transition-all shadow-md flex items-center justify-center gap-1.5 shrink-0"
                            title="Pergi ke Maps"
                        >
                            <i class="fa-solid fa-location-dot"></i>
                            <span class="hidden sm:inline">Maps</span>
                        </a>
                    </div>

                    <p class="text-[10px] text-gray-400 text-center">
                        Pesanan dicatat di sistem USUClassMap dan terintegrasi langsung dengan kasir toko.
                    </p>
                </div>

            </div>
        </div>

    </div>
</x-layouts.app>
