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
                <h3 class="text-2xl font-black text-[#10B981] mt-1">Rp {{ number_format($totalRevenueSum, 0, ',', '.') }}</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center">
                <svg class="w-6 h-6 text-[#10B981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>
    </div>

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
