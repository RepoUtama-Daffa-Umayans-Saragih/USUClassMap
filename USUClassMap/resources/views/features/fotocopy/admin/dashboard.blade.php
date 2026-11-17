<x-layouts.admin-fotokopi>
    <!-- Welcome Banner (Top Priority) -->
    <div class="mb-8 bg-gradient-to-r from-emerald-600 via-[#10B981] to-teal-600 rounded-2xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-xs text-xs font-semibold mb-3 border border-white/20">
                <span class="w-2 h-2 rounded-full bg-amber-300 animate-pulse"></span>
                Sistem Terintegrasi Kampus USU
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight leading-tight">
                Selamat Datang di Panel Admin Fotokopi USU
            </h1>
            <p class="text-emerald-50 text-xs sm:text-sm mt-2 max-w-2xl leading-relaxed">
                Kelola jaringan mitra toko percetakan & fotocopy resmi di lingkungan kampus USU, perbarui katalog harga layanan mahasiswa, serta pantau seluruh pesanan masuk secara terpusat.
            </p>
        </div>
    </div>

    <!-- Interactive Metric Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        
        <!-- Total Toko -->
        <a href="{{ route('admin-fotokopi.stores.index') }}" class="group block bg-white rounded-2xl p-5 border border-gray-100 shadow-xs hover:shadow-md hover:border-emerald-200 transition-all cursor-pointer">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <span class="text-xs font-bold text-emerald-600 group-hover:translate-x-0.5 transition-transform flex items-center gap-0.5">
                    Kelola &rarr;
                </span>
            </div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Mitra Toko</p>
            <h3 class="text-2xl font-black text-gray-900 mt-1">{{ $totalStores }} <span class="text-xs font-semibold text-gray-400 font-normal">Toko Aktif</span></h3>
        </a>

        <!-- Total Katalog Layanan -->
        <a href="{{ route('admin-fotokopi.services.index') }}" class="group block bg-white rounded-2xl p-5 border border-gray-100 shadow-xs hover:shadow-md hover:border-emerald-200 transition-all cursor-pointer">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <span class="text-xs font-bold text-blue-600 group-hover:translate-x-0.5 transition-transform flex items-center gap-0.5">
                    Katalog &rarr;
                </span>
            </div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Item Layanan</p>
            <h3 class="text-2xl font-black text-gray-900 mt-1">{{ $totalServices }} <span class="text-xs font-semibold text-gray-400 font-normal">Katalog Terdaftar</span></h3>
        </a>

        <!-- Total Pesanan -->
        <a href="{{ route('admin-fotokopi.reports.index') }}" class="group block bg-white rounded-2xl p-5 border border-gray-100 shadow-xs hover:shadow-md hover:border-emerald-200 transition-all cursor-pointer">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <span class="text-xs font-bold text-amber-600 group-hover:translate-x-0.5 transition-transform flex items-center gap-0.5">
                    Laporan &rarr;
                </span>
            </div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Pesanan</p>
            <h3 class="text-2xl font-black text-gray-900 mt-1">{{ $totalOrders }} <span class="text-xs font-semibold text-gray-400 font-normal">Transaksi</span></h3>
        </a>

        <!-- Total Estimasi Transaksi -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                    Lunas
                </span>
            </div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Estimasi Omzet Lunas</p>
            <h3 class="text-xl font-black text-[#10B981] mt-1">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
        </div>

    </div>

    <!-- Status Overview Chips -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                <div>
                    <h4 class="text-xs font-bold text-amber-900">Menunggu Proses</h4>
                    <p class="text-[11px] text-amber-700">Pesanan baru masuk dari mahasiswa</p>
                </div>
            </div>
            <span class="text-lg font-black text-amber-800">{{ $pendingOrders }}</span>
        </div>

        <div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                <div>
                    <h4 class="text-xs font-bold text-blue-900">Sedang Diproses</h4>
                    <p class="text-[11px] text-blue-700">Dalam pengerjaan oleh operator/kasir</p>
                </div>
            </div>
            <span class="text-lg font-black text-blue-800">{{ $processingOrders }}</span>
        </div>

        <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <div>
                    <h4 class="text-xs font-bold text-emerald-900">Pesanan Selesai</h4>
                    <p class="text-[11px] text-emerald-700">Telah dicetak dan diambil</p>
                </div>
            </div>
            <span class="text-lg font-black text-emerald-800">{{ $completedOrders }}</span>
        </div>
    </div>

    <!-- Two-column: Recent Orders & Top Stores -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Recent Orders (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-2xl p-6 border border-gray-100 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-black text-gray-900">Pesanan Masuk Terbaru</h3>
                    <p class="text-xs text-gray-500">Aktivitas pesanan cetak & fotocopy terkini</p>
                </div>
                <a href="{{ route('admin-fotokopi.reports.index') }}" class="text-xs font-bold text-[#10B981] hover:underline">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($recentOrders as $order)
                <div class="py-3.5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-gray-50 text-gray-600 flex items-center justify-center font-bold text-xs shrink-0 border border-gray-100">
                            {{ substr($order->order_code, -4) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h4 class="text-xs font-bold text-gray-900 truncate">{{ $order->customer_name }}</h4>
                                @if($order->order_status === 'Menunggu')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Menunggu</span>
                                @elseif($order->order_status === 'Diproses')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Diproses</span>
                                @elseif($order->order_status === 'Selesai')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Selesai</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">{{ $order->order_status }}</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-400 truncate mt-0.5 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <span>{{ $order->store->name ?? '-' }} • {{ $order->items->count() }} item • {{ $order->created_at->diffForHumans() }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-black text-gray-900 block">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                        <span class="text-[10px] font-semibold text-gray-400 uppercase font-mono">{{ $order->payment_method }}</span>
                    </div>
                </div>
                @empty
                <div class="py-8 text-center text-gray-400">
                    <p class="text-xs">Belum ada pesanan masuk.</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Top Mitra Stores (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-2xl p-6 border border-gray-100 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-black text-gray-900">Mitra Toko Terdaftar</h3>
                    <p class="text-xs text-gray-500">Rating & kapasitas katalog layanan</p>
                </div>
                <a href="{{ route('admin-fotokopi.stores.index') }}" class="text-xs font-bold text-[#10B981] hover:underline">
                    Kelola &rarr;
                </a>
            </div>

            <div class="space-y-3">
                @foreach($stores as $st)
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-xs font-bold text-gray-900 truncate">{{ $st->name }}</h4>
                            <span class="text-[10px] text-amber-700 font-bold bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200 inline-flex items-center gap-0.5">
                                <svg class="w-2.5 h-2.5 fill-amber-400 text-amber-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                {{ $st->rating }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate mt-0.5 flex items-center gap-1">
                            <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span>{{ $st->location_name }}</span>
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-100">
                            {{ $st->services_count }} Layanan
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

</x-layouts.admin-fotokopi>
