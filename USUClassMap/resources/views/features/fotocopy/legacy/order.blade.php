<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Layanan - {{ $store->name }} - USUStarterMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-[#F3F4F6] text-gray-900 font-sans antialiased" x-data="orderApp()">

    <!-- Public Navigation Bar -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-xs h-16 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex justify-between items-center">
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-2.5 group">
                    <x-application-logo class="w-9 h-9 object-contain transition-transform group-hover:scale-105" />
                    <span class="font-extrabold text-xl text-[#10B981] tracking-tight">USUStarterMap</span>
                </a>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="/map" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm">Peta</a>
                <a href="/faq" class="text-gray-600 hover:text-[#10B981] font-semibold text-sm">FAQ</a>
                <a href="/fotocopy" class="text-[#10B981] font-bold text-sm">Fotocopy</a>
            </div>

            <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200">
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-[#10B981]">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 text-xs font-bold text-white bg-[#10B981] hover:bg-emerald-600 rounded-xl shadow-xs">Daftar</a>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-emerald-700">{{ auth()->user()->name }}</a>
                @endguest
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-20 pb-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-xs text-gray-500 font-medium">
            <a href="/" class="hover:text-emerald-600">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-emerald-600">Fotocopy</a>
            <span>/</span>
            <a href="{{ route('fotocopy.show', $store->id) }}" class="hover:text-emerald-600">{{ $store->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-bold">Pemesanan</span>
        </nav>

        <!-- Store Header Banner -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center text-xl shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-black text-gray-900 leading-tight">{{ $store->name }}</h1>
                        @if($store->is_open)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Buka</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-600 border border-red-200">Tutup</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5">
                        <span>📍 {{ $store->location_name }}</span>
                        <span>•</span>
                        <span>🕒 {{ $store->opening_hours }}</span>
                    </p>
                </div>
            </div>

            @if($store->latitude && $store->longitude)
            <a href="{{ route('map', ['route_to' => 'fotocopy:' . $store->id]) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-colors shadow-xs">
                <span>📍</span>
                <span>Rute di Peta</span>
            </a>
            @endif
        </div>

        <!-- Main Split: Services Catalog vs Order Cart -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- LEFT PANEL: Pilih Item Layanan (65%) -->
            <div class="lg:col-span-7 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-[#10B981] rounded-full inline-block"></span>
                        Pilih Item Layanan
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Klik "+ Tambah" untuk memasukkan item ke keranjang pesanan</p>
                </div>

                @foreach(['Print', 'Fotocopy', 'Jilid', 'ATK'] as $cat)
                    @php $items = $servicesByCategory->get($cat) ?? collect(); @endphp
                    @if($items->count() > 0)
                    <div class="space-y-3">
                        <div class="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                            <span>{{ strtoupper($cat) }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            @foreach($items as $srv)
                                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs flex flex-col justify-between hover:border-emerald-200 transition-all">
                                    <div>
                                        <div class="flex items-start justify-between gap-2 mb-1">
                                            <h3 class="text-xs font-bold text-gray-900 leading-snug">{{ $srv->name }}</h3>
                                            <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100 uppercase">
                                                {{ $srv->category }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mb-3">{{ $srv->description ?? 'Layanan cepat dan berkualitas' }}</p>
                                    </div>

                                    <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                        <div>
                                            <span class="text-xs font-extrabold text-[#10B981]">
                                                Rp {{ number_format($srv->price, 0, ',', '.') }}
                                            </span>
                                            <span class="text-[10px] text-gray-400">/ {{ $srv->unit }}</span>
                                        </div>
                                        <button 
                                            type="button"
                                            @click="addToCart({{ $srv->id }}, '{{ addslashes($srv->name) }}', '{{ $srv->category }}', {{ $srv->price }}, '{{ $srv->unit }}')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-[#10B981] hover:bg-emerald-600 text-white text-xs font-bold rounded-lg shadow-xs transition-colors cursor-pointer"
                                        >
                                            <span>+ Tambah</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            <!-- RIGHT PANEL: Keranjang Pesanan (35% Sticky) -->
            <div class="lg:col-span-5 sticky top-20">
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h2 class="text-base font-black text-gray-900 flex items-center gap-2">
                            <span>🛒</span>
                            <span>Keranjang Pesanan</span>
                        </h2>
                        <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md" x-text="cartTotalCount() + ' Item'"></span>
                    </div>

                    <!-- Cart Empty State -->
                    <template x-if="cart.length === 0">
                        <div class="py-8 text-center text-gray-400 space-y-2">
                            <div class="w-12 h-12 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto text-xl">
                                🛒
                            </div>
                            <p class="text-xs font-semibold text-gray-500">Keranjang masih kosong.</p>
                            <p class="text-[11px] text-gray-400">Pilih item di sebelah kiri untuk memesan.</p>
                        </div>
                    </template>

                    <!-- Cart Items List -->
                    <template x-if="cart.length > 0">
                        <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                            <template x-for="(item, idx) in cart" :key="item.id">
                                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-xs font-bold text-gray-900 truncate" x-text="item.name"></h4>
                                        <span class="text-[10px] text-gray-400" x-text="'Rp ' + formatRupiah(item.price) + ' / ' + item.unit"></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center border border-gray-200 rounded-lg bg-white overflow-hidden text-xs">
                                            <button @click="decreaseQty(idx)" type="button" class="px-2 py-1 hover:bg-gray-100 text-gray-600 font-bold">-</button>
                                            <span class="px-2.5 py-1 font-bold text-gray-800" x-text="item.quantity"></span>
                                            <button @click="increaseQty(idx)" type="button" class="px-2 py-1 hover:bg-gray-100 text-gray-600 font-bold">+</button>
                                        </div>
                                        <span class="text-xs font-extrabold text-gray-800 min-w-14 text-right" x-text="'Rp ' + formatRupiah(item.price * item.quantity)"></span>
                                        <button @click="removeItem(idx)" type="button" class="text-red-400 hover:text-red-600 p-1" title="Hapus">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Order Inputs -->
                    <form @submit.prevent="submitOrder()" class="space-y-4 pt-3 border-t border-gray-100">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Nama Pemesan (Opsional):</label>
                            <input 
                                type="text" 
                                x-model="customerName"
                                placeholder="Contoh: Budi Santoso (NIM 21140...)" 
                                class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2 px-3 bg-gray-50/50"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Khusus:</label>
                            <textarea 
                                x-model="notes"
                                rows="2" 
                                placeholder="Contoh: Tolong rangkap 2, jilid lakban hitam, kertas 80gr" 
                                class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2 px-3 bg-gray-50/50"
                            ></textarea>
                        </div>

                        <!-- Pilihan Pembayaran -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-2">Pilihan Pembayaran:</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'QRIS'"
                                    :class="paymentMethod === 'QRIS' ? 'border-[#10B981] bg-emerald-50 text-emerald-800 font-bold ring-1 ring-emerald-500' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                    class="p-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 text-center transition-all cursor-pointer"
                                >
                                    <span class="text-base">📱</span>
                                    <span class="text-[11px]">QRIS</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'Tunai'"
                                    :class="paymentMethod === 'Tunai' ? 'border-[#10B981] bg-emerald-50 text-emerald-800 font-bold ring-1 ring-emerald-500' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                    class="p-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 text-center transition-all cursor-pointer"
                                >
                                    <span class="text-base">💵</span>
                                    <span class="text-[11px]">Tunai</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'Transfer'"
                                    :class="paymentMethod === 'Transfer' ? 'border-[#10B981] bg-emerald-50 text-emerald-800 font-bold ring-1 ring-emerald-500' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                    class="p-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 text-center transition-all cursor-pointer"
                                >
                                    <span class="text-base">🏛️</span>
                                    <span class="text-[11px]">Transfer</span>
                                </button>
                            </div>
                        </div>

                        <!-- Total Price -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-600">Total Harga:</span>
                            <span class="text-lg font-black text-[#10B981]" x-text="'Rp ' + formatRupiah(cartTotalPrice())"></span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2">
                            <button 
                                type="submit" 
                                :disabled="cart.length === 0 || isSubmitting"
                                class="sm:col-span-2 w-full py-3 px-4 bg-[#10B981] hover:bg-emerald-600 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-sm cursor-pointer disabled:cursor-not-allowed"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                <span x-text="isSubmitting ? 'Mengirim...' : 'Pesan via WhatsApp'"></span>
                            </button>

                            @if($store->latitude && $store->longitude)
                            <a href="{{ route('map', ['route_to' => 'fotocopy:' . $store->id]) }}" class="w-full py-3 px-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                                <span>📍 Rute Peta</span>
                            </a>
                            @endif
                        </div>

                        <p class="text-[10px] text-gray-400 text-center leading-relaxed">
                            Pesanan dan rincian item akan otomatis tersimpan ke sistem kasir dan terisi ke WhatsApp pemilik toko.
                        </p>
                    </form>

                </div>
            </div>

        </div>

    </main>

    <footer class="bg-white border-t border-gray-200/80 py-6 text-center text-xs text-gray-500">
        &copy; {{ date('Y') }} USUStarterMap — Sistem Peta Interaktif & Layanan Fotocopy Universitas Sumatera Utara
    </footer>

    <script>
        function orderApp() {
            return {
                cart: [],
                customerName: '{{ auth()->check() ? auth()->user()->name : "" }}',
                notes: '',
                paymentMethod: 'QRIS',
                isSubmitting: false,
                addToCart(id, name, category, price, unit) {
                    const existing = this.cart.find(item => item.id === id);
                    if (existing) {
                        existing.quantity++;
                    } else {
                        this.cart.push({ id, name, category, price, unit, quantity: 1 });
                    }
                },
                increaseQty(idx) {
                    this.cart[idx].quantity++;
                },
                decreaseQty(idx) {
                    if (this.cart[idx].quantity > 1) {
                        this.cart[idx].quantity--;
                    } else {
                        this.removeItem(idx);
                    }
                },
                removeItem(idx) {
                    this.cart.splice(idx, 1);
                },
                cartTotalCount() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },
                cartTotalPrice() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },
                formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID').format(num);
                },
                async submitOrder() {
                    if (this.cart.length === 0) return;
                    this.isSubmitting = true;

                    const payload = {
                        customer_name: this.customerName || 'Mahasiswa USU',
                        customer_phone: '',
                        notes: this.notes,
                        payment_method: this.paymentMethod,
                        items: this.cart.map(i => ({
                            service_id: i.id,
                            service_name: i.name,
                            category: i.category,
                            price: i.price,
                            quantity: i.quantity,
                        }))
                    };

                    try {
                        const response = await fetch('{{ route("fotocopy.order.store", $store->id) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });

                        const result = await response.json();

                        // Siapkan pesan otomatis WhatsApp
                        let waText = `Halo *{{ $store->name }}*, saya ingin memesan layanan fotocopy/print dari USUStarterMap:%0A%0A`;
                        waText += `*Kode Pesanan:* ${result.order_code || 'ORD'}%0A`;
                        waText += `*Nama Pemesan:* ${encodeURIComponent(this.customerName || 'Mahasiswa USU')}%0A`;
                        waText += `*Metode Pembayaran:* ${this.paymentMethod}%0A`;
                        if (this.notes) {
                            waText += `*Catatan Khusus:* ${encodeURIComponent(this.notes)}%0A`;
                        }
                        waText += `%0A*Rincian Pesanan:*%0A`;
                        this.cart.forEach((item, i) => {
                            waText += `${i+1}. ${encodeURIComponent(item.name)} (${item.quantity} ${item.unit}) - Rp ${this.formatRupiah(item.price * item.quantity)}%0A`;
                        });
                        waText += `%0A*Total: Rp ${this.formatRupiah(this.cartTotalPrice())}*%0A%0AMohon konfirmasi dan proses pesanan saya, terima kasih!`;

                        const waNumber = '{{ preg_replace("/[^0-9]/", "", $store->whatsapp ?: "6282277809900") }}';
                        window.open(`https://wa.me/${waNumber}?text=${waText}`, '_blank');

                        alert('Pesanan berhasil dibuat dengan kode #' + (result.order_code || 'ORD') + '! Halaman WhatsApp telah dibuka.');
                        window.location.href = '{{ route("fotocopy.show", $store->id) }}';

                    } catch (err) {
                        console.error(err);
                        alert('Terjadi kesalahan saat memproses pesanan.');
                    } finally {
                        this.isSubmitting = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
