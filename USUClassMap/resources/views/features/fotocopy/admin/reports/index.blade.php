<x-layouts.admin-fotokopi>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Laporan & Pemantauan Pesanan</h1>
            <p class="text-sm text-gray-500 mt-1">Rekapitulasi seluruh pesanan fotocopy & percetakan mahasiswa di lingkungan kampus USU.</p>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Pesanan Tercatat</p>
                <h3 class="text-2xl font-black text-gray-900 mt-1">{{ $totalOrdersCount }} <span class="text-xs font-normal text-gray-400">Transaksi</span></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Nilai Transaksi Lunas</p>
                <h3 data-total-paid="{{ $totalRevenueSum }}" class="text-2xl font-black text-[#10B981] mt-1">Rp {{ number_format($totalRevenueSum, 0, ',', '.') }}</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center">
                <svg class="w-6 h-6 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Visual Analytics: 14-Day Order Trend & Payment Method Breakdown -->
    @if(!empty($dailyTrend))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6 items-stretch">
        <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-gray-100 shadow-xs h-full flex flex-col">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                <div>
                    <h3 class="text-sm font-black text-gray-900">Grafik Tren Transaksi 14 Hari Terakhir</h3>
                    <p class="text-[11px] text-gray-500">Volume pesanan masuk per hari (WIB) beserta akumulasi omzet terbayar</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-50 text-gray-700 border border-gray-200">
                        {{ $trendTotals['orders'] ?? 0 }} pesanan · Rp {{ number_format($trendTotals['revenue'] ?? 0, 0, ',', '.') }}
                    </span>
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Live Report</span>
                </div>
            </div>

            <div class="overflow-x-auto pb-2 flex-1 flex flex-col justify-end" style="padding-top: 3.5rem;">
                <div class="relative" style="min-width: 580px;">
                    <div class="pointer-events-none absolute inset-x-0 top-0" style="height: 200px;">
                        <div class="absolute inset-x-0 border-t border-dashed border-gray-200 flex items-center" style="top: 20px;">
                            <span class="text-[10px] font-mono text-gray-400 bg-white pr-1.5 -mt-2">{{ $maxTrendOrders }}</span>
                        </div>
                        <div class="absolute inset-x-0 border-t border-dashed border-gray-200 flex items-center" style="top: 110px;">
                            <span class="text-[10px] font-mono text-gray-400 bg-white pr-1.5 -mt-2">{{ (int) round(($maxTrendOrders ?? 1) / 2) }}</span>
                        </div>
                        <div class="absolute inset-x-0 border-t border-gray-200 flex items-center" style="top: 200px;">
                            <span class="text-[10px] font-mono text-gray-400 bg-white pr-1.5 -mt-2">0</span>
                        </div>
                    </div>

                    <div class="relative z-10 pl-6" style="display: grid; grid-template-columns: repeat(14, minmax(36px, 1fr)); gap: 0.25rem;">
                        @foreach($dailyTrend as $point)
                            @php
                                $tooltipPosClass = $loop->index <= 1
                                    ? 'left-0'
                                    : ($loop->index >= 12 ? 'right-0' : 'left-1/2 -translate-x-1/2');
                            @endphp
                            <div
                                data-trend-day="{{ $point['date'] }}"
                                data-orders="{{ $point['orders_count'] }}"
                                class="group relative flex flex-col items-center cursor-default"
                                title="{{ $point['full_label'] }} — {{ $point['orders_count'] }} pesanan (Omzet terbayar: Rp {{ number_format($point['paid_revenue'], 0, ',', '.') }})"
                            >
                                <div class="pointer-events-none absolute bottom-full {{ $tooltipPosClass }} mb-1.5 z-20 hidden group-hover:block whitespace-nowrap rounded-lg bg-slate-900 text-white text-[11px] px-2.5 py-1.5 shadow-lg">
                                    <div class="font-bold text-emerald-300">{{ $point['full_label'] }}</div>
                                    <div>{{ $point['orders_count'] }} pesanan masuk</div>
                                    <div>Omzet terbayar: Rp {{ number_format($point['paid_revenue'], 0, ',', '.') }}</div>
                                </div>
                                <div class="relative w-full flex items-end justify-center" style="height: 200px;">
                                    <div class="flex flex-col items-center justify-end w-full">
                                        <span class="text-xs font-semibold text-slate-700 mb-1 leading-none">{{ $point['orders_count'] }}</span>
                                        <div
                                            data-trend-bar
                                            class="w-full max-w-[28px] rounded-t-md transition-all {{ $point['orders_count'] > 0 ? 'bg-gradient-to-t from-emerald-600 to-emerald-400 group-hover:from-emerald-700 group-hover:to-emerald-500' : 'bg-gray-200' }}"
                                            style="height: {{ $point['bar_px'] }}px;"
                                        ></div>
                                    </div>
                                </div>
                                <span class="mt-2 text-[11px] text-slate-500 whitespace-nowrap">{{ $point['date_label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @php
            $methodColorMap = [
                'qris' => 'bg-emerald-500',
                'bank_transfer' => 'bg-blue-500',
                'cash' => 'bg-amber-500',
                'other' => 'bg-slate-400',
            ];
        @endphp
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs h-full flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-black text-gray-900">Ringkasan Metode Pembayaran</h3>
                    <p class="text-[11px] text-gray-500">Distribusi transaksi lunas QRIS, Transfer Bank &amp; Tunai</p>
                </div>
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                    Total Lunas: Rp {{ number_format($paymentBreakdownTotal ?? 0, 0, ',', '.') }}
                </span>
            </div>

            <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100">
                @foreach($paymentBreakdown ?? [] as $mKey => $mStats)
                    @if(($mStats['revenue'] ?? 0) > 0)
                        <div
                            class="h-full {{ $methodColorMap[$mKey] ?? 'bg-slate-400' }}"
                            style="width: {{ min(100, max(0, $mStats['percentage'] ?? 0)) }}%;"
                            title="{{ $mStats['label'] }}: {{ number_format($mStats['percentage'] ?? 0, 1, ',', '.') }}% (Rp {{ number_format($mStats['revenue'], 0, ',', '.') }})"
                        ></div>
                    @endif
                @endforeach
            </div>

            <div class="flex flex-col gap-3">
                @foreach($paymentBreakdown ?? [] as $mKey => $mStats)
                    @php
                        $colorCls = $methodColorMap[$mKey] ?? 'bg-slate-400';
                        $pctVal = min(100, max(0, (float) ($mStats['percentage'] ?? 0)));
                    @endphp
                    <div
                        data-payment-method="{{ $mKey }}"
                        data-payment-revenue="{{ $mStats['revenue'] }}"
                        class="p-3 rounded-xl bg-gray-50 border border-gray-100 space-y-2"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $colorCls }}"></span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-800 truncate">{{ $mStats['label'] }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $mStats['count'] }} transaksi lunas</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-black text-[#10B981]">Rp {{ number_format($mStats['revenue'], 0, ',', '.') }}</div>
                                <div class="text-[10px] font-semibold text-gray-500">{{ number_format($pctVal, 1, ',', '.') }}%</div>
                            </div>
                        </div>
                        <div class="w-full h-1.5 bg-gray-200/80 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $colorCls }}" style="width: {{ $pctVal }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-xs border border-gray-100 mb-6">
        <form method="GET" action="{{ route('admin-fotokopi.reports.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <select name="store_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
                    <option value="">-- Semua Toko --</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="order_status" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
                    <option value="">-- Semua Status --</option>
                    <option value="Menunggu" {{ request('order_status') === 'Menunggu' ? 'selected' : '' }}>Menunggu</option>
                    <option value="Diproses" {{ request('order_status') === 'Diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="Selesai" {{ request('order_status') === 'Selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="Dibatalkan" {{ request('order_status') === 'Dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>

            <div>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kode pesanan, nama pemesan..." class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 py-2">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-3 rounded-xl text-xs transition-colors cursor-pointer border border-gray-200">
                    Filter Laporan
                </button>
                @if(request('store_id') || request('order_status') || request('q'))
                    <a href="{{ route('admin-fotokopi.reports.index') }}" class="px-3 py-2 bg-red-50 text-red-600 hover:bg-red-100 text-xs font-semibold rounded-xl border border-red-200">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Reports Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">Kode & Tanggal</th>
                        <th class="p-4">Mitra Toko</th>
                        <th class="p-4">Pemesan</th>
                        <th class="p-4">Detail Item & Catatan</th>
                        <th class="p-4 text-center">Metode & Status Bayar</th>
                        <th class="p-4 text-center">Status Pesanan</th>
                        <th class="p-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($orders as $order)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-4">
                            <span class="font-bold text-gray-900 font-mono text-xs block">{{ $order->order_code }}</span>
                            <span class="text-[11px] text-gray-400 block mt-0.5">{{ $order->created_at->format('d M Y, H:i') }}</span>
                        </td>
                        <td class="p-4 font-semibold text-gray-800 text-xs">
                            {{ $order->store->name ?? '-' }}
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-gray-900 text-xs">{{ $order->customer_name }}</div>
                            @if($order->customer_whatsapp || $order->customer_phone)
                                <div class="text-[11px] text-gray-500 font-mono mt-0.5">{{ \App\Support\WhatsAppPhoneNormalizer::formatDisplay($order->customer_whatsapp ?: $order->customer_phone) }}</div>
                            @endif
                        </td>
                        <td class="p-4">
                            <div class="text-xs text-gray-700 space-y-1">
                                @foreach($order->items as $item)
                                    <div>• {{ $item->service_name }} ({{ $item->quantity }}x)</div>
                                @endforeach
                            </div>
                            @if($order->notes)
                                <div class="mt-2 text-[11px] text-amber-800 bg-amber-50 p-2 rounded-lg border border-amber-200 italic max-w-sm">
                                    "{{ $order->notes }}"
                                </div>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-800 font-mono">
                                {{ $order->payment_method }}
                            </span>
                            <div class="mt-1 flex flex-col items-center gap-1">
                                @if($order->payment_status === 'paid')
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">Lunas</span>
                                @else
                                    <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-100">Belum Bayar</span>
                                @endif
                                @if($order->wa_dispatch_status && $order->wa_dispatch_status !== 'not_ready')
                                    <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                        WA: {{ strtoupper($order->wa_dispatch_status) }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            @if($order->order_status === 'Menunggu')
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Menunggu</span>
                            @elseif($order->order_status === 'Diproses')
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Diproses</span>
                            @elseif($order->order_status === 'Selesai')
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Selesai</span>
                            @else
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">{{ $order->order_status }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-right">
                            <span class="font-black text-[#10B981] text-xs">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-gray-400">
                            Tidak ada data pesanan yang sesuai filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</x-layouts.admin-fotokopi>
