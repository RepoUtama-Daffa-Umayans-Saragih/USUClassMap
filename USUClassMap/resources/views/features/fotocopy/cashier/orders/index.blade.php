<x-layouts.cashier-fotokopi>
<div x-data="ordersApp()">
    <!-- Store Identity Banner -->
    <div class="mb-6 bg-gradient-to-r from-emerald-600 via-[#10B981] to-teal-600 rounded-2xl p-5 sm:p-6 text-white shadow-lg relative overflow-hidden flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="flex items-center gap-3.5 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center shadow-inner">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-100">Toko yang Dikelola</span>
                <h1 class="text-xl font-black text-white leading-tight">{{ $myStore->name ?? 'Semua Gerai Mitra' }}</h1>
                <p class="text-xs text-emerald-50 mt-0.5 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        {{ $myStore->location_name ?? 'Kampus USU' }}
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ $myStore->opening_hours ?? '07:30 - 20:30' }}
                    </span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto relative z-10">
            <a href="{{ route('cashier-fotokopi.orders.pos') }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-white hover:bg-emerald-50 text-emerald-800 rounded-xl text-xs font-bold transition-all shadow-md">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>+ Input Pesanan Walk-in (POS)</span>
            </a>
        </div>
    </div>

    <!-- Status Tabs & Filter -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        
        <!-- Filter Tabs -->
        <div class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-2xl border border-gray-100 shadow-xs">
            <a href="{{ route('cashier-fotokopi.orders.index') }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ !request('status') || request('status') === 'semua' ? 'bg-[#10B981] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                <span>Semua</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ !request('status') || request('status') === 'semua' ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-700' }}">{{ $totalOrdersCount }}</span>
            </a>

            <a href="{{ route('cashier-fotokopi.orders.index', ['status' => 'Menunggu']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ request('status') === 'Menunggu' ? 'bg-amber-500 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                <span>Menunggu</span>
                @if($countMenunggu > 0)
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('status') === 'Menunggu' ? 'bg-white/25 text-white' : 'bg-amber-100 text-amber-800 animate-pulse font-extrabold' }}">{{ $countMenunggu }}</span>
                @endif
            </a>

            <a href="{{ route('cashier-fotokopi.orders.index', ['status' => 'Diproses']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ request('status') === 'Diproses' ? 'bg-blue-600 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                <span>Sedang Diproses</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('status') === 'Diproses' ? 'bg-white/25 text-white' : 'bg-blue-100 text-blue-800' }}">{{ $countDiproses }}</span>
            </a>

            <a href="{{ route('cashier-fotokopi.orders.index', ['status' => 'Selesai']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ request('status') === 'Selesai' ? 'bg-[#10B981] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                <span>Selesai</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('status') === 'Selesai' ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $countSelesai }}</span>
            </a>
        </div>

        <!-- Quick Search -->
        <form method="GET" action="{{ route('cashier-fotokopi.orders.index') }}" class="w-full sm:w-72">
            <div class="relative">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode pesanan..." class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 border bg-white shadow-xs">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
        </form>

    </div>

    <!-- Orders Operational Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] uppercase font-bold text-gray-500 tracking-wider">
                        <th class="p-4">Pesanan & Waktu</th>
                        <th class="p-4">Pemesan</th>
                        <th class="p-4">Rincian Layanan & Catatan Khusus</th>
                        <th class="p-4 text-center">Pembayaran</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi Operasional Kasir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($orders as $order)
                    <tr class="hover:bg-gray-50/60 transition-colors {{ $order->order_status === 'Menunggu' ? 'bg-amber-50/30' : '' }}">
                        <td class="p-4">
                            <span class="font-mono font-bold text-xs text-gray-900 block">{{ $order->order_code }}</span>
                            @if($order->pickup_code || $order->invoice_number)
                                <div class="flex flex-wrap items-center gap-1 mt-1">
                                    @if($order->pickup_code)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 font-mono text-[10px] font-black">{{ $order->pickup_code }}</span>
                                    @endif
                                    @if($order->invoice_number)
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[10px] font-bold">{{ $order->invoice_number }}</span>
                                    @endif
                                </div>
                            @endif
                            <span class="text-[11px] text-gray-400 block mt-0.5">{{ $order->created_at->format('H:i • d M Y') }}</span>
                            <span class="text-[10px] text-emerald-600 font-semibold">{{ $order->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-gray-900 text-xs">{{ $order->customer_name }}</div>
                            @if($order->customer_whatsapp || $order->customer_phone)
                                @php
                                    $custWaRaw = $order->customer_whatsapp ?: $order->customer_phone;
                                    $custWaUrl = \App\Support\WhatsAppPhoneNormalizer::toWaMeUrl($custWaRaw);
                                @endphp
                                <a href="{{ $custWaUrl }}" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] text-emerald-600 font-mono mt-0.5 hover:underline">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <span>{{ \App\Support\WhatsAppPhoneNormalizer::formatDisplay($custWaRaw) }}</span>
                                </a>
                            @endif
                        </td>
                        <td class="p-4">
                            <!-- Items List -->
                            <div class="space-y-1">
                                @foreach($order->items as $item)
                                    <div class="text-xs text-gray-800 flex items-center justify-between gap-3">
                                        <span class="font-medium">• {{ $item->service_name }} ({{ $item->quantity }}x)</span>
                                        <span class="font-mono text-[11px] text-gray-500">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Lampiran Multi-PDF Dokumen & Note Khusus Per File -->
                            @if($order->files && $order->files->isNotEmpty())
                                <div class="mt-2.5 space-y-2 max-w-xl">
                                    @foreach($order->files as $fIdx => $oFile)
                                        @php
                                            $previewUrl = route('cashier-fotokopi.orders.files.download', ['order' => $order, 'file' => $oFile, 'inline' => 1]);
                                            $downloadUrl = route('cashier-fotokopi.orders.files.download', ['order' => $order, 'file' => $oFile]);
                                        @endphp
                                        <div class="p-2.5 rounded-xl bg-blue-50/70 border border-blue-200/80 space-y-1.5">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <div class="w-8 h-8 rounded-lg bg-red-500 text-white flex items-center justify-center font-black text-[10px] uppercase shrink-0 shadow-2xs">
                                                        PDF#{{ $fIdx + 1 }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <span class="text-xs font-bold text-gray-900 truncate block">{{ $oFile->original_name }}</span>
                                                        <span class="text-[10px] text-blue-800 font-semibold block">
                                                            {{ $oFile->page_count }} hal &times; {{ $oFile->copies }}x &bull; {{ $oFile->print_mode }} ({{ $oFile->paper_size }})
                                                            @if($oFile->finishing_option) &bull; + {{ $oFile->finishing_option }} @endif
                                                        </span>
                                                        <span class="text-[10px] text-gray-500">{{ $oFile->formatted_size }}{{ $oFile->is_encrypted ? ' • PDF Terkunci Password' : '' }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openPreview({{ \Illuminate\Support\Js::from($order->order_code) }}, {{ \Illuminate\Support\Js::from($order->customer_name) }}, {{ \Illuminate\Support\Js::from($oFile->original_name) }}, {{ \Illuminate\Support\Js::from($oFile->formatted_size) }}, 'PDF', {{ \Illuminate\Support\Js::from($previewUrl) }}, {{ \Illuminate\Support\Js::from(route('cashier-fotokopi.orders.print', $order)) }}, {{ \Illuminate\Support\Js::from($oFile->note ?? '') }})"
                                                        class="inline-flex items-center gap-1 px-2 py-1 bg-white hover:bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-[11px] font-bold transition cursor-pointer"
                                                    >
                                                        <span>Preview</span>
                                                    </button>
                                                    <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-1 px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold transition">
                                                        <span>Unduh</span>
                                                    </a>
                                                </div>
                                            </div>
                                            @if($oFile->note)
                                                <div class="p-2 rounded-lg bg-amber-100/80 border border-amber-300 text-[11px] text-amber-950 font-semibold">
                                                    <span class="uppercase text-[9px] font-black text-amber-800 tracking-wider mr-1">Note File #{{ $fIdx + 1 }}:</span>
                                                    <span class="italic">"{{ $oFile->note }}"</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <a href="{{ route('cashier-fotokopi.orders.download-zip', $order) }}" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-[11px] font-bold transition shadow-2xs">
                                            <span>Unduh Semua PDF (.zip)</span>
                                        </a>
                                        <a href="{{ route('cashier-fotokopi.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-[11px] font-bold transition shadow-2xs">
                                            <span>Print Now</span>
                                        </a>
                                        @if($order->isPaid())
                                            <a href="{{ route('cashier-fotokopi.orders.invoice', $order) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-[11px] font-bold transition">
                                                <span>Invoice PDF</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @elseif($order->hasFile())
                                <div class="mt-2.5 p-2.5 rounded-xl bg-blue-50/70 border border-blue-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 max-w-lg">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-8 h-8 rounded-lg {{ $order->fileIconType() === 'pdf' ? 'bg-red-500' : ($order->fileIconType() === 'doc' ? 'bg-blue-600' : 'bg-emerald-600') }} text-white flex items-center justify-center font-black text-[10px] uppercase shrink-0 shadow-2xs">
                                            {{ strtoupper($order->file_type ?? 'PDF') }}
                                        </div>
                                        <div class="min-w-0">
                                            <span class="text-xs font-bold text-gray-900 truncate block">{{ $order->file_name }}</span>
                                            <span class="text-[10px] text-gray-500 block">{{ $order->file_size ?? 'Dokumen Siap Cetak' }} &bull; Lampiran Mahasiswa</span>
                                        </div>
                                    </div>
                                    @php
                                        $legacyPreviewUrl = $order->fileUrl();
                                        if ($legacyPreviewUrl && !str_contains($legacyPreviewUrl, 'inline=')) {
                                            $legacyPreviewUrl .= (str_contains($legacyPreviewUrl, '?') ? '&' : '?') . 'inline=1';
                                        }
                                    @endphp
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button
                                            type="button"
                                            @click="openPreview({{ \Illuminate\Support\Js::from($order->order_code) }}, {{ \Illuminate\Support\Js::from($order->customer_name) }}, {{ \Illuminate\Support\Js::from($order->file_name) }}, {{ \Illuminate\Support\Js::from($order->file_size) }}, {{ \Illuminate\Support\Js::from($order->file_type) }}, {{ \Illuminate\Support\Js::from($legacyPreviewUrl) }}, {{ \Illuminate\Support\Js::from(route('cashier-fotokopi.orders.print', $order)) }}, {{ \Illuminate\Support\Js::from($order->notes ?? '') }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white hover:bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold transition-all shadow-2xs cursor-pointer"
                                            title="Pratinjau Dokumen"
                                        >
                                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            <span>Preview</span>
                                        </button>
                                        <a
                                            href="{{ route('cashier-fotokopi.orders.print', $order) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-bold transition-all shadow-xs"
                                            title="Cetak Dokumen Sekarang (Print Now)"
                                        >
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                            <span>Print Now</span>
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="mt-2.5 p-2 rounded-xl bg-gray-50 border border-gray-200/70 flex items-center justify-between gap-2 max-w-lg">
                                    <div class="flex items-center gap-1.5 text-[11px] text-gray-500 min-w-0">
                                        <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                        <span class="truncate">File via WhatsApp / Flashdisk / Pembelian ATK</span>
                                    </div>
                                    <a
                                        href="{{ route('cashier-fotokopi.orders.print', $order) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg text-xs font-semibold transition-all shadow-2xs shrink-0"
                                        title="Cetak SPK Kerja & Nota"
                                    >
                                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        <span>Cetak SPK</span>
                                    </a>
                                </div>
                            @endif

                            <!-- Special Notes -->
                            @if($order->notes)
                                <div class="mt-2.5 p-2 rounded-xl bg-amber-50/80 border border-amber-200/80 text-[11px] text-amber-900 flex items-start gap-1.5 max-w-md">
                                    <svg class="w-3.5 h-3.5 text-amber-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                                    <div>
                                        <span class="font-bold block">Catatan Khusus Mahasiswa:</span>
                                        <span class="italic leading-snug">{{ $order->notes }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-2 pt-1 border-t border-gray-100 flex justify-between text-xs font-bold text-gray-700">
                                <span>Total Tagihan:</span>
                                <span class="text-sm font-black text-[#10B981]">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-800 font-mono">
                                {{ $order->payment_method }}
                            </span>
                            <div class="mt-1">
                                @if($order->payment_status === 'paid')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Lunas
                                    </span>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        Belum Lunas
                                    </span>
                                @endif
                            </div>

                            <!-- WA Dispatch Status Badge & Modal Trigger -->
                            @php
                                $waSt = $order->wa_dispatch_status ?: 'not_ready';
                                $waBadgeMap = [
                                    'sent' => ['Terkirim WA', 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                                    'sandbox' => ['Sandbox WA', 'bg-sky-100 text-sky-800 border-sky-200'],
                                    'queued' => ['Queued WA', 'bg-blue-100 text-blue-800 border-blue-200'],
                                    'partial' => ['Sebagian WA', 'bg-amber-100 text-amber-800 border-amber-200'],
                                    'failed' => ['Gagal WA', 'bg-red-100 text-red-700 border-red-200'],
                                    'not_ready' => ['Belum Lunas WA', 'bg-gray-100 text-gray-500 border-gray-200'],
                                ];
                                [$waLabel, $waClass] = $waBadgeMap[$waSt] ?? ['Status WA', 'bg-gray-100 text-gray-600 border-gray-200'];
                                $waLogsPayload = $order->whatsappLogs->map(fn ($l) => [
                                    'role' => $l->recipient_role,
                                    'phone' => $l->recipient_phone,
                                    'type' => $l->message_type,
                                    'status' => $l->status,
                                    'driver' => $l->driver,
                                    'file' => $l->payload_json['file_name'] ?? null,
                                    'text' => $l->payload_json['text'] ?? $l->payload_json['caption'] ?? '',
                                    'error' => $l->error_message,
                                    'sent_at' => ($l->sent_at ?? $l->created_at)?->timezone('Asia/Jakarta')->format('d M H:i:s'),
                                ])->values()->all();
                            @endphp
                            <div class="mt-1.5 flex flex-col items-center gap-1">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $waClass }}">
                                    {{ $waLabel }}
                                </span>
                                @if($order->whatsappLogs->isNotEmpty())
                                    <button
                                        type="button"
                                        @click="openWaLogs({{ \Illuminate\Support\Js::from($order->order_code) }}, {{ \Illuminate\Support\Js::from($waLogsPayload) }})"
                                        class="text-[10px] font-bold text-blue-600 hover:underline cursor-pointer"
                                    >
                                        Log WA ({{ $order->whatsappLogs->count() }})
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            @if($order->order_status === 'Menunggu')
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">
                                    Menunggu
                                </span>
                            @elseif($order->order_status === 'Diproses')
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                    Diproses
                                </span>
                            @elseif($order->order_status === 'Selesai')
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <svg class="w-3 h-3 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Selesai
                                </span>
                            @else
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                                    {{ $order->order_status }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex flex-col items-end gap-1.5">
                                <!-- Tombol Cetak Dokumen / Print Now -->
                                <a 
                                    href="{{ route('cashier-fotokopi.orders.print', $order) }}" 
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-emerald-50 text-gray-700 hover:text-emerald-800 border border-gray-200 hover:border-emerald-300 rounded-lg text-xs font-bold transition-all shadow-2xs cursor-pointer"
                                    title="Cetak SPK Lembar Kerja & Dokumen"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    <span>Cetak Dokumen</span>
                                </a>

                                @if($order->isPaid())
                                    <form method="POST" action="{{ route('cashier-fotokopi.orders.resend-wa', $order) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 rounded-lg text-xs font-bold transition-colors cursor-pointer">
                                            <span>Kirim Ulang ke WA</span>
                                        </button>
                                    </form>
                                @endif

                                @if($order->order_status === 'Menunggu')
                                    <form method="POST" action="{{ route('cashier-fotokopi.orders.status', $order) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="order_status" value="Diproses">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-colors shadow-xs cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span>Mulai Proses</span>
                                        </button>
                                    </form>
                                @endif

                                @if($order->order_status === 'Diproses')
                                    <form method="POST" action="{{ route('cashier-fotokopi.orders.status', $order) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="order_status" value="Selesai">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-lg text-xs font-bold transition-colors shadow-xs cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Selesaikan</span>
                                        </button>
                                    </form>
                                @endif

                                @if($order->order_status !== 'Selesai' && $order->order_status !== 'Dibatalkan')
                                    <form method="POST" action="{{ route('cashier-fotokopi.orders.status', $order) }}" onsubmit="return confirm('Batalkan pesanan ini?');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="order_status" value="Dibatalkan">
                                        <button type="submit" class="text-[11px] font-semibold text-red-500 hover:text-red-700 hover:underline cursor-pointer">
                                            Batalkan Pesanan
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-gray-400">
                            <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2 border border-gray-100">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            </div>
                            <p class="text-xs font-semibold text-gray-600">Tidak ada antrean pesanan pada filter ini.</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Pesanan baru dari mahasiswa akan otomatis muncul di sini.</p>
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

    <!-- Modal Pratinjau Dokumen File Mahasiswa -->
    <div 
        x-show="previewModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
        @keydown.escape.window="closePreview()"
    >
        <div 
            x-show="previewModalOpen" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="closePreview()"
            class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-2xl w-full overflow-hidden flex flex-col"
        >
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-[#10B981] text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-white shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-black leading-tight">Pratinjau Dokumen Mahasiswa</h3>
                        <p class="text-[11px] text-emerald-100 flex items-center gap-1.5 mt-0.5">
                            <span class="font-mono font-bold" x-text="previewData.orderCode"></span>
                            <span>&bull;</span>
                            <span x-text="previewData.customerName"></span>
                        </p>
                    </div>
                </div>
                <button @click="closePreview()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <!-- Info Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Nama Dokumen</span>
                        <span class="font-bold text-gray-900 truncate block mt-0.5" x-text="previewData.fileName"></span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Format</span>
                        <span class="font-bold text-emerald-600 block mt-0.5" x-text="previewData.fileType"></span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Ukuran File</span>
                        <span class="font-bold text-gray-900 block mt-0.5" x-text="previewData.fileSize"></span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Status Dokumen</span>
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Terverifikasi
                        </span>
                    </div>
                </div>

                <!-- Visual Document Preview Container -->
                <div class="bg-gray-100 rounded-xl border border-gray-200 p-6 flex flex-col items-center justify-center min-h-64 text-center">
                    <template x-if="previewData.fileUrl">
                        <div class="w-full h-72 rounded-lg overflow-hidden border border-gray-300 bg-white">
                            <iframe :src="previewData.fileUrl" class="w-full h-full border-none"></iframe>
                        </div>
                    </template>
                    <template x-if="!previewData.fileUrl">
                        <div class="max-w-md space-y-3">
                            <div class="w-16 h-20 bg-white rounded-lg shadow-md border border-gray-300 mx-auto flex flex-col items-center justify-center p-2 relative">
                                <div class="w-full h-1.5 bg-red-400 rounded-xs mb-1.5"></div>
                                <div class="w-3/4 h-1 bg-gray-200 rounded-xs mb-1"></div>
                                <div class="w-full h-1 bg-gray-200 rounded-xs mb-1"></div>
                                <div class="w-2/3 h-1 bg-gray-200 rounded-xs mb-1"></div>
                                <span class="text-[9px] font-black text-red-600 mt-2" x-text="previewData.fileType"></span>
                            </div>
                            <div>
                                <h4 class="text-sm font-extrabold text-gray-800" x-text="previewData.fileName"></h4>
                                <p class="text-xs text-gray-500 mt-1">
                                    Dokumen lampiran tugas mahasiswa siap dikirim ke printer gerai.
                                </p>
                            </div>
                            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-left text-xs text-amber-800" x-show="previewData.notes">
                                <span class="font-bold block">Instruksi Khusus Mahasiswa:</span>
                                <span class="italic" x-text="previewData.notes"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-3">
                <button 
                    type="button" 
                    @click="closePreview()" 
                    class="px-4 py-2 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                >
                    Tutup
                </button>

                <div class="flex items-center gap-2">
                    <template x-if="previewData.fileUrl">
                        <a 
                            :href="previewData.fileUrl" 
                            download 
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            <span>Unduh File</span>
                        </a>
                    </template>

                    <a
                        :href="previewData.printUrl" 
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-5 py-2 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Cetak Dokumen Sekarang (Print Now)</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Log Pengiriman WhatsApp -->
    <div x-show="waModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" @keydown.escape.window="closeWaLogs()">
        <div @click.away="closeWaLogs()" class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-2xl w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black">Riwayat Pengiriman WhatsApp Otomatis</h3>
                    <p class="text-[11px] text-slate-300 font-mono" x-text="'Order #' + waModalOrderCode"></p>
                </div>
                <button type="button" @click="closeWaLogs()" class="text-slate-300 hover:text-white px-2 py-1 text-sm font-bold cursor-pointer">&times;</button>
            </div>
            <div class="p-5 max-h-[28rem] overflow-y-auto space-y-3 bg-[#EFEAE2]">
                <template x-for="(log, i) in waModalLogs" :key="i">
                    <div class="bg-white rounded-xl p-3 border border-gray-200 shadow-2xs space-y-1.5 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                    :class="log.status === 'sent' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'"
                                    x-text="log.status + ' (' + log.driver + ')'"></span>
                                <span class="font-bold text-gray-800" x-text="log.role === 'customer' ? 'Ke Mahasiswa: ' + log.phone : 'Ke Toko: ' + log.phone"></span>
                            </div>
                            <span class="text-[10px] text-gray-400 font-mono" x-text="log.type + ' • ' + (log.sent_at || '-')"></span>
                        </div>
                        <template x-if="log.file">
                            <div class="px-2 py-1 rounded bg-blue-50 border border-blue-200 text-[11px] font-bold text-blue-800" x-text="'Lampiran: ' + log.file"></div>
                        </template>
                        <pre class="whitespace-pre-wrap font-sans text-[11px] text-gray-700 bg-gray-50 p-2 rounded-lg border border-gray-100" x-text="log.text"></pre>
                        <template x-if="log.error">
                            <div class="text-[11px] text-red-600 font-semibold" x-text="'Error: ' + log.error"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
    function ordersApp() {
        return {
            previewModalOpen: false,
            previewData: {
                orderCode: '',
                customerName: '',
                fileName: '',
                fileSize: '',
                fileType: 'PDF',
                fileUrl: '',
                printUrl: '',
                notes: ''
            },
            waModalOpen: false,
            waModalOrderCode: '',
            waModalLogs: [],
            openPreview(orderCode, customerName, fileName, fileSize, fileType, fileUrl, printUrl, notes) {
                this.previewData = {
                    orderCode: orderCode,
                    customerName: customerName,
                    fileName: fileName,
                    fileSize: fileSize || 'Dokumen Mahasiswa',
                    fileType: (fileType || 'PDF').toUpperCase(),
                    fileUrl: fileUrl,
                    printUrl: printUrl,
                    notes: notes || ''
                };
                this.previewModalOpen = true;
            },
            closePreview() {
                this.previewModalOpen = false;
            },
            openWaLogs(orderCode, logs) {
                this.waModalOrderCode = orderCode;
                this.waModalLogs = Array.isArray(logs) ? logs : [];
                this.waModalOpen = true;
            },
            closeWaLogs() {
                this.waModalOpen = false;
            }
        };
    }
</script>
</x-layouts.cashier-fotokopi>
