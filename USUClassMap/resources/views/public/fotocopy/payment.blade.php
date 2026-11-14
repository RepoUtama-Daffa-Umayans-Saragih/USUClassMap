<x-layouts.app>
    <x-slot name="title">Instruksi Pembayaran - #{{ $order->order_code }}</x-slot>

    <div
        class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6"
        x-data="{
            isPaid: {{ $order->isPaid() ? 'true' : 'false' }},
            paymentStatus: '{{ $order->payment_status }}',
            orderStatus: '{{ $order->order_status }}',
            paidAt: '{{ $order->paid_at ? $order->paid_at->format('d M Y, H:i') : '' }}',
            simulating: false,
            copied: false,
            copiedText: '',

            copyToClipboard(text, label) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copied = true;
                    this.copiedText = label;
                    setTimeout(() => { this.copied = false; }, 2500);
                });
            },

            simulatePayment() {
                if (this.isPaid || this.simulating) return;
                this.simulating = true;

                fetch('{{ route('fotocopy.orders.simulate-payment', $order->order_code) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.isPaid = true;
                        this.paymentStatus = data.payment_status || 'paid';
                        this.orderStatus = data.order_status || 'Diproses';
                        this.paidAt = data.paid_at || new Date().toLocaleString('id-ID');
                    } else {
                        alert(data.message || 'Simulasi pembayaran gagal.');
                    }
                    this.simulating = false;
                })
                .catch(err => {
                    console.error('Error simulating payment:', err);
                    alert('Gagal menghubungi endpoint simulasi.');
                    this.simulating = false;
                });
            }
        }"
    >
        <!-- Breadcrumb -->
        <nav class="flex items-center text-xs text-gray-500 gap-2 overflow-x-auto whitespace-nowrap pb-1">
            <a href="/" class="hover:text-[#10A760]">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760]">Fotocopy</a>
            <span>/</span>
            <a href="{{ route('fotocopy.show', $place->id) }}" class="hover:text-[#10A760]">{{ $place->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-bold">Pembayaran #{{ $order->order_code }}</span>
        </nav>

        <!-- Dynamic Status Banner -->
        <div
            x-show="isPaid"
            x-cloak
            class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start sm:items-center gap-3.5 shadow-sm transition-all"
        >
            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 text-lg shadow-sm">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-extrabold text-sm sm:text-base text-emerald-900">Pembayaran Berhasil Dikonfirmasi (Lunas)</h3>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Terima kasih! Pesanan Anda telah diterima oleh mitra toko dan masuk ke antrean pencetakan.
                    <span x-show="paidAt" class="font-semibold block sm:inline mt-0.5 sm:mt-0 sm:ml-1 text-emerald-800" x-text="'(Diverifikasi pada ' + paidAt + ')'"></span>
                </p>
            </div>
            <div class="hidden sm:block">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-600 text-white shadow-sm">
                    <i class="fa-solid fa-check-double"></i> LUNAS
                </span>
            </div>
        </div>

        <div
            x-show="!isPaid"
            class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start sm:items-center gap-3.5 shadow-sm transition-all"
        >
            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 text-lg shadow-sm">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-extrabold text-sm sm:text-base text-amber-900">Menunggu Pembayaran</h3>
                <p class="text-xs text-amber-700 mt-0.5">
                    Silakan selesaikan pembayaran sesuai metode <strong class="capitalize">{{ $order->getPaymentMethodLabel() }}</strong> di bawah untuk memproses pesanan Anda.
                </p>
            </div>
            <div class="hidden sm:block">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-500 text-white shadow-sm">
                    <i class="fa-solid fa-hourglass-half"></i> PENDING
                </span>
            </div>
        </div>

        <!-- Copy Toast Notification -->
        <div
            x-show="copied"
            x-transition
            x-cloak
            class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-xl flex items-center gap-2 border border-gray-700"
        >
            <i class="fa-solid fa-check text-emerald-400"></i>
            <span x-text="copiedText + ' berhasil disalin ke clipboard!'"></span>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: PAYMENT METHOD INSTRUCTIONS & ACTIONS (7 Cols) -->
            <div class="lg:col-span-7 space-y-6">

                <!-- 1. PAYMENT CARD -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-5">

                    <!-- METHOD: QRIS -->
                    @if($order->payment_method === 'qris')
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-green-50 text-[#10A760] flex items-center justify-center text-lg font-black">
                                        <i class="fa-solid fa-qrcode"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm sm:text-base font-black text-gray-900">QRIS Dinamis Real-Time</h2>
                                        <p class="text-[11px] text-gray-500">Scan melalui GoPay, OVO, Dana, ShopeePay, BCA, Mandiri, dll.</p>
                                    </div>
                                </div>
                                <span class="text-[10px] uppercase tracking-wider font-extrabold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                                    EMVCo QRIS
                                </span>
                            </div>

                            <!-- QR Code Visual Display -->
                            <div class="flex flex-col items-center justify-center py-4 bg-slate-50/70 border border-slate-200/80 rounded-2xl text-center space-y-3">
                                <div class="bg-white p-3 rounded-2xl border border-gray-200 shadow-sm max-w-[280px]">
                                    {!! $order->payment_payload['qr_code_svg'] ?? '' !!}
                                </div>

                                <div class="space-y-1">
                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $order->payment_payload['merchant_name'] ?? 'USUClassMap Fotokopi' }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 font-mono">
                                        Ref: {{ $order->payment_payload['qris_reference'] ?? $order->order_code }}
                                    </div>
                                    <div class="text-[10px] text-amber-700 bg-amber-50 px-3 py-1 rounded-full font-medium inline-flex items-center gap-1.5 border border-amber-200">
                                        <i class="fa-regular fa-clock"></i>
                                        <span>Berlaku 15 menit dari pembuatan pesanan</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Amount Display -->
                            <div class="p-3.5 bg-green-50/70 border border-green-200 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-[11px] text-gray-600 block">Total Nominal Pembayaran:</span>
                                    <span class="text-lg sm:text-xl font-black text-[#10A760]">{{ $order->getFormattedTotal() }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard('{{ (int)$order->total_price }}', 'Nominal Pembayaran')"
                                    class="text-xs bg-white text-[#10A760] hover:bg-green-100 font-bold px-3 py-1.5 rounded-lg border border-green-300 transition flex items-center gap-1.5 shadow-sm"
                                >
                                    <i class="fa-regular fa-copy"></i>
                                    <span>Salin</span>
                                </button>
                            </div>

                            <!-- Step-by-Step Instructions -->
                            <div class="space-y-2 pt-2">
                                <h4 class="text-xs font-extrabold text-gray-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-list-ol text-[#10A760]"></i>
                                    <span>Cara Pembayaran QRIS:</span>
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-xs text-gray-600 leading-relaxed bg-white p-3 rounded-xl border border-gray-100">
                                    @foreach($order->payment_payload['instructions'] ?? config('payment.qris.instructions', []) as $step)
                                        <li class="pl-1">{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </div>

                            <!-- PoC Simulation Button -->
                            <div class="pt-3 border-t border-gray-100 space-y-2">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider">Simulasi Pengujian (PoC):</span>
                                    <span class="text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full font-bold border border-purple-200">Demonstration Mode</span>
                                </div>
                                <button
                                    type="button"
                                    @click="simulatePayment()"
                                    :disabled="simulating || isPaid"
                                    class="w-full bg-[#10A760] hover:bg-[#0D8A4E] text-white py-3 px-4 rounded-xl text-xs sm:text-sm font-extrabold transition shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-spinner fa-spin" x-show="simulating"></i>
                                    <i class="fa-solid fa-bolt" x-show="!simulating"></i>
                                    <span x-text="isPaid ? 'Pembayaran Berhasil Diverifikasi' : (simulating ? 'Memverifikasi...' : 'Simulasikan Scan QRIS Berhasil (PoC Callback)')"></span>
                                </button>
                            </div>
                        </div>

                    <!-- METHOD: BANK TRANSFER -->
                    @elseif($order->payment_method === 'bank_transfer')
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-black">
                                        <i class="fa-solid fa-building-columns"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm sm:text-base font-black text-gray-900">Transfer Virtual Account ({{ $order->payment_channel ?? 'BCA' }})</h2>
                                        <p class="text-[11px] text-gray-500">Transfer melalui m-Banking, i-Banking, atau ATM.</p>
                                    </div>
                                </div>
                                <span class="text-[10px] uppercase tracking-wider font-extrabold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-200">
                                    {{ $order->payment_channel ?? 'BCA' }} VA
                                </span>
                            </div>

                            <!-- VA Account Number Highlight Box -->
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500 font-medium">Nomor Virtual Account (PoC):</span>
                                    <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">Kode Terpusat</span>
                                </div>

                                <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-gray-200">
                                    <div>
                                        <span class="text-base sm:text-xl font-mono font-black text-gray-900 tracking-wider">
                                            {{ $order->payment_payload['account_number'] ?? config('payment.bank_transfer.static_bank_code', '88019920260001') }}
                                        </span>
                                        <span class="block text-[11px] text-gray-500 mt-0.5">
                                            a.n. <strong>{{ $order->payment_payload['account_name'] ?? 'USUClassMap Fotokopi' }}</strong>
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyToClipboard('{{ $order->payment_payload['account_number'] ?? config('payment.bank_transfer.static_bank_code', '88019920260001') }}', 'Nomor Virtual Account')"
                                        class="bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold px-3 py-2 rounded-lg border border-blue-200 transition flex items-center gap-1.5 shadow-sm"
                                    >
                                        <i class="fa-regular fa-copy"></i>
                                        <span>Salin</span>
                                    </button>
                                </div>

                                <!-- Total Nominal -->
                                <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-gray-200">
                                    <div>
                                        <span class="text-[11px] text-gray-500 block">Total Nominal Transfer:</span>
                                        <span class="text-base sm:text-lg font-black text-[#10A760]">{{ $order->getFormattedTotal() }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyToClipboard('{{ (int)$order->total_price }}', 'Nominal Tagihan')"
                                        class="bg-green-50 hover:bg-green-100 text-[#10A760] text-xs font-bold px-3 py-2 rounded-lg border border-green-200 transition flex items-center gap-1.5 shadow-sm"
                                    >
                                        <i class="fa-regular fa-copy"></i>
                                        <span>Salin</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Step-by-Step Instructions -->
                            <div class="space-y-2 pt-2">
                                <h4 class="text-xs font-extrabold text-gray-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-list-ol text-blue-600"></i>
                                    <span>Instruksi Transfer {{ $order->payment_channel ?? 'BCA' }}:</span>
                                </h4>
                                <ol class="list-decimal list-inside space-y-1.5 text-xs text-gray-600 leading-relaxed bg-white p-3 rounded-xl border border-gray-100">
                                    @foreach($order->payment_payload['instructions'] ?? [] as $step)
                                        <li class="pl-1">{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </div>

                            <!-- PoC Simulation Button -->
                            <div class="pt-3 border-t border-gray-100 space-y-2">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider">Simulasi Pengujian (PoC):</span>
                                    <span class="text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full font-bold border border-purple-200">Demonstration Mode</span>
                                </div>
                                <button
                                    type="button"
                                    @click="simulatePayment()"
                                    :disabled="simulating || isPaid"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-xl text-xs sm:text-sm font-extrabold transition shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-spinner fa-spin" x-show="simulating"></i>
                                    <i class="fa-solid fa-bolt" x-show="!simulating"></i>
                                    <span x-text="isPaid ? 'Transfer Berhasil Diterima' : (simulating ? 'Memverifikasi...' : 'Simulasikan Transfer Bank Masuk (PoC Callback)')"></span>
                                </button>
                            </div>
                        </div>

                    <!-- METHOD: CASH (TUNAI) -->
                    @else
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-black">
                                        <i class="fa-solid fa-cash-register"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm sm:text-base font-black text-gray-900">Bayar Tunai di Kasir Toko</h2>
                                        <p class="text-[11px] text-gray-500">Lakukan pembayaran langsung saat mengambil dokumen pesanan Anda.</p>
                                    </div>
                                </div>
                                <span class="text-[10px] uppercase tracking-wider font-extrabold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">
                                    Kasir Tunai
                                </span>
                            </div>

                            <!-- Store Cashier Details Box -->
                            <div class="p-4 bg-amber-50/50 border border-amber-200 rounded-2xl space-y-3">
                                <div class="space-y-1">
                                    <span class="text-[11px] text-gray-500 block">Toko Tujuan Pembayaran:</span>
                                    <h3 class="text-sm sm:text-base font-extrabold text-gray-900">{{ $place->name }}</h3>
                                    <p class="text-xs text-gray-600 leading-snug">{{ $place->address }}</p>
                                </div>

                                <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-amber-200">
                                    <div>
                                        <span class="text-[11px] text-gray-500 block">Total Pembayaran Tunai:</span>
                                        <span class="text-base sm:text-xl font-black text-amber-700">{{ $order->getFormattedTotal() }}</span>
                                    </div>
                                    @if($place->latitude && $place->longitude)
                                        <a
                                            href="{{ $place->getDirectionUrl() }}"
                                            target="_blank"
                                            class="bg-[#D4A017] hover:bg-[#B8880F] text-white text-xs font-bold px-3 py-2 rounded-lg transition shadow-sm flex items-center gap-1.5"
                                        >
                                            <i class="fa-solid fa-location-arrow"></i>
                                            <span>Rute Toko</span>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <!-- Step-by-Step Instructions -->
                            <div class="space-y-2 pt-2">
                                <h4 class="text-xs font-extrabold text-gray-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-list-ol text-amber-600"></i>
                                    <span>Instruksi Pembayaran Tunai:</span>
                                </h4>
                                <ol class="list-decimal list-inside space-y-1.5 text-xs text-gray-600 leading-relaxed bg-white p-3 rounded-xl border border-gray-100">
                                    <li class="pl-1">Datangi langsung kasir <strong>{{ $place->name }}</strong> sesuai alamat di atas.</li>
                                    <li class="pl-1">Tunjukkan Kode Pesanan <strong>#{{ $order->order_code }}</strong> kepada staf kasir toko.</li>
                                    <li class="pl-1">Bayarkan uang tunai pas sebesar <strong>{{ $order->getFormattedTotal() }}</strong> saat mengambil dokumen/cetakan Anda.</li>
                                </ol>
                            </div>

                            <!-- PoC Simulation Button -->
                            <div class="pt-3 border-t border-gray-100 space-y-2">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider">Simulasi Pengujian (PoC):</span>
                                    <span class="text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full font-bold border border-purple-200">Demonstration Mode</span>
                                </div>
                                <button
                                    type="button"
                                    @click="simulatePayment()"
                                    :disabled="simulating || isPaid"
                                    class="w-full bg-amber-600 hover:bg-amber-700 text-white py-3 px-4 rounded-xl text-xs sm:text-sm font-extrabold transition shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-spinner fa-spin" x-show="simulating"></i>
                                    <i class="fa-solid fa-bolt" x-show="!simulating"></i>
                                    <span x-text="isPaid ? 'Uang Tunai Telah Diterima Kasir' : (simulating ? 'Memverifikasi...' : 'Simulasikan Kasir Terima Uang Tunai (PoC)')"></span>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>

            </div>

            <!-- RIGHT COLUMN: ORDER RECEIPT SUMMARY (5 Cols) -->
            <div class="lg:col-span-5 space-y-6">

                <!-- Order Receipt Card -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <span class="text-[10px] uppercase tracking-wider text-gray-400 font-extrabold">Kode Pesanan</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <h3 class="text-base sm:text-lg font-black text-gray-900 font-mono">#{{ $order->order_code }}</h3>
                                <button
                                    type="button"
                                    @click="copyToClipboard('{{ $order->order_code }}', 'Kode Pesanan')"
                                    class="text-gray-400 hover:text-gray-600 text-xs"
                                    title="Salin Kode Pesanan"
                                >
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-right">
                            <span
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold"
                                :class="isPaid ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200'"
                            >
                                <i :class="isPaid ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-clock text-amber-600'"></i>
                                <span x-text="isPaid ? 'Lunas' : 'Menunggu Bayar'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Customer & Store Info -->
                    <div class="text-xs space-y-1.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nama Pemesan:</span>
                            <span class="font-bold text-gray-900">{{ $order->customer_name }}</span>
                        </div>
                        @if($order->customer_phone)
                            <div class="flex justify-between">
                                <span class="text-gray-500">No. HP / WA:</span>
                                <span class="font-bold text-gray-900">{{ $order->customer_phone }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Mitra Toko:</span>
                            <span class="font-bold text-gray-900">{{ $place->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Metode Bayar:</span>
                            <span class="font-bold text-gray-900">{{ $order->getPaymentMethodLabel() }} ({{ $order->getPaymentChannelLabel() }})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Status Pesanan:</span>
                            <span class="font-bold text-[#10A760]" x-text="orderStatus"></span>
                        </div>
                    </div>

                    <!-- Ordered Items List -->
                    <div class="space-y-2 pt-1">
                        <span class="text-[11px] font-bold text-gray-700 block">Daftar Item & Layanan:</span>
                        <div class="divide-y divide-gray-100 max-h-56 overflow-y-auto pr-1">
                            @forelse($order->items as $item)
                                <div class="py-2 flex items-center justify-between text-xs">
                                    <div class="pr-2">
                                        <div class="font-bold text-gray-900">{{ $item->service_name }}</div>
                                        <div class="text-[10px] text-gray-500">
                                            Rp {{ number_format($item->price, 0, ',', '.') }} × {{ $item->quantity }}
                                        </div>
                                    </div>
                                    <div class="font-extrabold text-gray-800 text-right shrink-0">
                                        Rp {{ number_format($item->subtotal ?? ($item->price * $item->quantity), 0, ',', '.') }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-gray-400 py-2">Tidak ada detail item khusus.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Total Breakdown -->
                    <div class="pt-3 border-t border-gray-100 space-y-1">
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <span>Subtotal Item:</span>
                            <span>{{ $order->getFormattedTotal() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <span>Biaya Layanan Kampus:</span>
                            <span class="text-emerald-600 font-bold">GRATIS</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <span class="text-sm font-bold text-gray-800">Total Tagihan:</span>
                            <span class="text-lg font-black text-[#10A760]">{{ $order->getFormattedTotal() }}</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 space-y-2">
                        @php
                            $waNumber = preg_replace('/[^\d]/', '', $place->whatsapp ?: $place->phone ?: '6285769737410');
                            if (str_starts_with($waNumber, '08')) {
                                $waNumber = '62' . substr($waNumber, 1);
                            } elseif (str_starts_with($waNumber, '0')) {
                                $waNumber = '62' . substr($waNumber, 1);
                            }

                            $waMsg = "Halo {$place->name}, saya telah memesan melalui USUClassMap:\n";
                            $waMsg .= "Kode Pesanan: #{$order->order_code}\n";
                            $waMsg .= "Nama: {$order->customer_name}\n";
                            $waMsg .= "Total: {$order->getFormattedTotal()}\n";
                            $waMsg .= "Metode: " . $order->getPaymentMethodLabel() . " (" . $order->getPaymentChannelLabel() . ")\n";
                            $waMsg .= "Mohon dicek dan diproses cetakannya. Terima kasih!";
                            $waLink = 'https://wa.me/' . $waNumber . '?text=' . urlencode($waMsg);
                        @endphp

                        <a
                            href="{{ $waLink }}"
                            target="_blank"
                            class="w-full bg-[#10A760] hover:bg-[#0D8A4E] text-white py-2.5 px-4 rounded-xl text-xs font-bold transition shadow-sm flex items-center justify-center gap-2"
                        >
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>Kirim Bukti / Hubungi Toko</span>
                        </a>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="window.print()"
                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5"
                            >
                                <i class="fa-solid fa-print"></i>
                                <span>Cetak Invoice</span>
                            </button>

                            <a
                                href="{{ route('fotocopy.index') }}"
                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 text-center"
                            >
                                <i class="fa-solid fa-arrow-left"></i>
                                <span>Daftar Toko</span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</x-layouts.app>
