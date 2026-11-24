<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $order->invoice_number }} - {{ $order->order_code }}</title>
    <style>
        @page { margin: 22px 26px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #10a760;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .brand {
            font-size: 16px;
            font-weight: bold;
            color: #0d8a4e;
            margin: 0 0 2px 0;
        }
        .store-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 2px 0;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        .badge-paid {
            display: inline-block;
            background-color: #dcfce7;
            color: #166534;
            border: 1.5px solid #16a34a;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .badge-pending {
            display: inline-block;
            background-color: #fef3c7;
            color: #92400e;
            border: 1.5px solid #f59e0b;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .meta-box {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .meta-box td {
            padding: 8px 10px;
            vertical-align: top;
            width: 50%;
            border-right: 1px solid #e2e8f0;
        }
        .meta-box td:last-child {
            border-right: none;
        }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }
        .section-heading {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0d8a4e;
            letter-spacing: 0.4px;
            margin: 12px 0 6px 0;
            border-left: 3px solid #10a760;
            padding-left: 6px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data-table th {
            background-color: #ecfdf5;
            color: #065f46;
            font-size: 9.5px;
            text-transform: uppercase;
            padding: 7px 8px;
            text-align: left;
            border-top: 1px solid #a7f3d0;
            border-bottom: 1px solid #a7f3d0;
        }
        table.data-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 10.5px;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .note-highlight {
            margin-top: 3px;
            padding: 3px 6px;
            background-color: #fffbeb;
            border-left: 2px solid #f59e0b;
            color: #92400e;
            font-size: 9.5px;
        }
        .total-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .total-box {
            background-color: #ecfdf5;
            border: 1px solid #10a760;
            border-radius: 6px;
            padding: 10px 14px;
        }
        .pickup-pill {
            display: inline-block;
            background-color: #111827;
            color: #ffffff;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 62%; vertical-align: top;">
                <p class="brand">USUClassMap &bull; Bukti Pembayaran & SPK Resmi</p>
                <p class="store-title">{{ $storeName }}</p>
                <p class="muted">{{ $storeLocation }}</p>
                <p class="muted">WhatsApp Toko: {{ $storeWhatsapp }}</p>
            </td>
            <td style="width: 38%; text-align: right; vertical-align: top;">
                @if($order->isPaid())
                    <span class="badge-paid">LUNAS</span>
                @else
                    <span class="badge-pending">MENUNGGU BAYAR</span>
                @endif
                <div style="margin-top: 6px; font-weight: bold; font-size: 12px;">{{ $order->invoice_number }}</div>
                <div class="muted">Order: #{{ $order->order_code }}</div>
                @if($order->pickup_code)
                    <div style="margin-top: 4px;"><span class="pickup-pill">KODE AMBIL: {{ $order->pickup_code }}</span></div>
                @endif
            </td>
        </tr>
    </table>

    <table class="meta-box">
        <tr>
            <td>
                <span class="label">Pemesan / Mahasiswa</span>
                <strong>{{ $order->customer_name }}</strong><br>
                <span class="muted">
                    No. WA / HP: {{ \App\Support\WhatsAppPhoneNormalizer::formatDisplay($order->customer_whatsapp ?: $order->customer_phone) }}
                </span>
                @if($order->pickup_estimate)
                    <br><span class="muted">Estimasi Selesai: <strong>{{ $order->pickup_estimate }}</strong></span>
                @endif
            </td>
            <td>
                <span class="label">Rincian Transaksi</span>
                Metode: <strong>{{ $order->getPaymentMethodLabel() }} ({{ $order->getPaymentChannelLabel() }})</strong><br>
                <span class="muted">Dibuat: {{ $order->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span><br>
                @if($order->paid_at)
                    <span class="muted">Dilunasi: <strong>{{ $order->paid_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</strong></span>
                @endif
                @if(!empty($order->payment_payload['settlement_reference']) || !empty($order->payment_payload['bank_reference']))
                    <br><span class="muted">Ref: {{ $order->payment_payload['settlement_reference'] ?? $order->payment_payload['bank_reference'] }}</span>
                @endif
            </td>
        </tr>
    </table>

    @if($files->isNotEmpty())
        <div class="section-heading">Rincian Dokumen Cetak ({{ $files->count() }} File PDF)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 47%;">Nama File PDF &amp; Instruksi Khusus</th>
                    <th style="width: 28%;">Spesifikasi Cetak</th>
                    <th style="width: 20%;" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($files as $idx => $file)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $file->original_name }}</strong>
                            <div class="muted">{{ $file->formatted_size }}{{ $file->is_encrypted ? ' • PDF Terkunci Password' : '' }}</div>
                            @if($file->note)
                                <div class="note-highlight"><strong>Note:</strong> {{ $file->note }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $file->page_count }} hal &times; {{ $file->copies }} rangkap<br>
                            <span class="muted">{{ $file->print_mode }} ({{ $file->paper_size }})</span>
                            @if($file->finishing_option)
                                <br><span style="color: #0d8a4e; font-weight: bold; font-size: 9.5px;">+ Bundel: {{ $file->finishing_option }}</span>
                            @endif
                        </td>
                        <td class="text-right"><strong>Rp {{ number_format($file->subtotal_estimated, 0, ',', '.') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(isset($offlinePhysicalItems) && $offlinePhysicalItems->isNotEmpty())
        <div class="section-heading">Layanan Berkas Fisik (Offline di Toko — Bawa Berkas Asli)</div>
        <div class="note-highlight" style="margin-bottom: 8px; font-size: 9.5px;">
            <strong>Pengingat Pelanggan:</strong> Layanan berikut dikerjakan secara <strong>offline</strong>. Harap membawa dokumen/berkas fisik asli ke kasir toko.
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Layanan Berkas Fisik</th>
                    <th style="width: 15%;" class="text-center">Qty</th>
                    <th style="width: 17%;" class="text-right">Tarif</th>
                    <th style="width: 18%;" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offlinePhysicalItems as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->service_name }}</strong>
                            <div class="muted">{{ $item->category }}</div>
                        </td>
                        <td class="text-center">{{ $item->quantity }}x</td>
                        <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                        <td class="text-right"><strong>Rp {{ number_format($item->subtotal ?? ($item->price * $item->quantity), 0, ',', '.') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $displayGoods = $atkGoodsItems ?? $otherItems; @endphp
    @if($displayGoods->isNotEmpty())
        <div class="section-heading">Daftar Barang ATK &amp; Layanan Tambahan</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Nama Item / Barang ATK</th>
                    <th style="width: 15%;" class="text-center">Qty</th>
                    <th style="width: 17%;" class="text-right">Harga</th>
                    <th style="width: 18%;" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($displayGoods as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->service_name }}</strong>
                            <div class="muted">{{ $item->category }}</div>
                        </td>
                        <td class="text-center">{{ $item->quantity }}x</td>
                        <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                        <td class="text-right"><strong>Rp {{ number_format($item->subtotal ?? ($item->price * $item->quantity), 0, ',', '.') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($order->notes)
        <div class="note-highlight" style="margin-bottom: 12px; font-size: 10px;">
            <strong>Catatan Umum Pelanggan:</strong> "{{ $order->notes }}"
        </div>
    @endif

    <table class="total-table">
        <tr>
            <td style="width: 58%; vertical-align: middle;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="padding-right: 10px;">
                            <img src="{{ $qrDataUri }}" alt="QR Ambil" width="84" height="84">
                        </td>
                        <td style="vertical-align: middle;">
                            <strong style="color: #111827; font-size: 11px;">QR Verifikasi Ambil Berkas</strong>
                            <div class="muted">Tunjukkan kode <strong>{{ $order->pickup_code ?: $order->order_code }}</strong> atau QR ini ke kasir {{ $storeName }} saat pengambilan.</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 42%; vertical-align: middle;">
                <div class="total-box">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td class="muted">Biaya Layanan Kampus</td>
                            <td class="text-right" style="color: #0d8a4e; font-weight: bold;">GRATIS</td>
                        </tr>
                        <tr>
                            <td style="padding-top: 6px; font-weight: bold; font-size: 12px;">TOTAL TAGIHAN</td>
                            <td class="text-right" style="padding-top: 6px; font-weight: bold; font-size: 15px; color: #0d8a4e;">
                                {{ $order->getFormattedTotal() }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini diterbitkan secara otomatis oleh sistem USUClassMap pada {{ now('Asia/Jakarta')->format('d M Y, H:i') }} WIB.
        Arsip digital sah untuk pelanggan dan mitra {{ $storeName }}.
    </div>
</body>
</html>
