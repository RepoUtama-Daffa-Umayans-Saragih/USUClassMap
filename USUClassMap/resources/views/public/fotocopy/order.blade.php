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

                let itemsText = '';
                Object.values(this.cart).forEach((item, index) => {
                    itemsText += `${index + 1}. ${item.name} (${item.qty} ${item.unit}) = ${this.formatRupiah(item.price * item.qty)}\n`;
                });

                let paymentLabel = 'QRIS';
                if (this.paymentMethod === 'cod') paymentLabel = 'Tunai / Bayar di Tempat';
                if (this.paymentMethod === 'transfer') paymentLabel = 'Transfer Bank';

                let notes = this.orderNotes ? `\n*Catatan Khusus:*\n${this.orderNotes}\n` : '';
                let cust = this.customerName ? `\n*Nama Pemesan:* ${this.customerName}` : '';

                let message = `Halo *${this.placeName}*, saya ingin memesan jasa cetak/fotokopi melalui *USUClassMap*:\n`
                    + cust
                    + `\n\n*Rincian Pesanan:*\n${itemsText}`
                    + `\n*Total Pembayaran:* ${this.formatRupiah(this.getSubtotal())}`
                    + `\n*Metode Pembayaran:* ${paymentLabel}`
                    + notes
                    + `\nMohon dikonfirmasi proses dan estimasi waktu selesainya. Terima kasih!`;

                let phone = this.placeWa.replace(/[^0-9]/g, '');
                if (phone.startsWith('08')) {
                    phone = '62' + phone.substring(1);
                }

                let waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                window.open(waUrl, '_blank');
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
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#E8F5EE] text-[#10A760] rounded-2xl flex items-center justify-center font-bold text-xl sm:text-2xl shrink-0">
                    <i class="fa-solid fa-print"></i>
                </div>
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
                    <div class="space-y-1.5 pt-2 border-t border-gray-100">
                        <label class="block text-[11px] font-bold text-gray-700">Pilihan Pembayaran:</label>
                        <div class="grid grid-cols-3 gap-1.5 text-[11px]">
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'qris' ? 'border-[#10A760] bg-green-50/60 font-bold text-[#10A760]' : 'border-gray-200 text-gray-600'">
                                <input type="radio" value="qris" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-qrcode text-sm mb-1"></i>
                                <span>QRIS</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'cod' ? 'border-[#10A760] bg-green-50/60 font-bold text-[#10A760]' : 'border-gray-200 text-gray-600'">
                                <input type="radio" value="cod" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-money-bill-wave text-sm mb-1"></i>
                                <span>Tunai</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'transfer' ? 'border-[#10A760] bg-green-50/60 font-bold text-[#10A760]' : 'border-gray-200 text-gray-600'">
                                <input type="radio" value="transfer" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-building-columns text-sm mb-1"></i>
                                <span>Transfer</span>
                            </label>
                        </div>
                    </div>

                    <!-- Subtotal & Total -->
                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-600">Total Harga:</span>
                        <span class="text-base sm:text-lg font-black text-[#10A760]" x-text="formatRupiah(getSubtotal())"></span>
                    </div>

                    <!-- BUTTONS: PESAN & LOKASI (SEJAJAR) -->
                    <div class="pt-2 flex items-center gap-2">
                        <!-- Button Pesan (Laman Baru / WA Otomatis) -->
                        <button
                            type="button"
                            @click="submitOrder()"
                            class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-brands fa-whatsapp text-sm sm:text-base"></i>
                            <span>Pesan via WhatsApp</span>
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
                        Pesanan dan rincian item akan otomatis terisi ke WhatsApp pemilik toko.
                    </p>
                </div>

            </div>
        </div>

    </div>
</x-layouts.app>
