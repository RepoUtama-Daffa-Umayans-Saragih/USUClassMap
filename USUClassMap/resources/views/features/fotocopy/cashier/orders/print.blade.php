<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak SPK & Nota #{{ $order->order_code }} - USUStarterMap</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            padding: 24px;
        }
        .ticket-card {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px dashed #d1d5db;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: 900;
            color: #10b981;
            letter-spacing: -0.5px;
        }
        .spk-badge {
            display: inline-block;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .store-info {
            font-size: 12px;
            color: #4b5563;
            margin-top: 4px;
        }
        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #f9fafb;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            border: 1px solid #f3f4f6;
        }
        .info-item label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #9ca3af;
        }
        .info-item span {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }
        .file-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }
        .file-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #3b82f6;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .file-details {
            flex: 1;
            min-width: 0;
        }
        .file-title {
            font-size: 12px;
            font-weight: 800;
            color: #1e3a8a;
            word-break: break-all;
        }
        .file-meta {
            font-size: 11px;
            color: #3b82f6;
        }
        .notes-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            color: #92400e;
            margin-bottom: 18px;
            line-height: 1.4;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #6b7280;
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table td {
            font-size: 12px;
            padding: 8px 6px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .items-table td.qty {
            text-align: center;
            font-weight: 700;
        }
        .items-table td.subtotal {
            text-align: right;
            font-weight: 800;
            color: #111827;
            font-family: monospace;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 6px;
            border-top: 2px dashed #d1d5db;
            margin-top: 8px;
        }
        .total-label {
            font-size: 14px;
            font-weight: 800;
            color: #374151;
        }
        .total-amount {
            font-size: 18px;
            font-weight: 900;
            color: #10b981;
            font-family: monospace;
        }
        .footer-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
        .sig-box {
            width: 160px;
        }
        .sig-line {
            margin-top: 48px;
            border-bottom: 1px solid #9ca3af;
        }
        .no-print-bar {
            max-width: 640px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-print {
            background: #10b981;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #059669;
        }
        .btn-back {
            background: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .btn-back:hover {
            background: #f9fafb;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .ticket-card {
                box-shadow: none;
                border: none;
                padding: 10px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Bar Aksi Atas (Hanya tampil di layar) -->
    <div class="no-print-bar">
        <a href="{{ route('cashier-fotokopi.orders.index') }}" class="btn btn-back">
            &larr; Kembali ke Antrean Kasir
        </a>
        <div style="display: flex; gap: 8px;">
            @if($order->hasFile() && $order->file_path)
            <a href="{{ route('cashier-fotokopi.orders.file', $order) }}" class="btn btn-back">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Unduh File Asli
            </a>
            @endif
            <button onclick="window.print()" class="btn btn-print">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak Dokumen Sekarang (Print)
            </button>
        </div>
    </div>

    <!-- Lembar Surat Perintah Kerja (SPK) Cetak & Nota Transaksi -->
    <div class="ticket-card">
        
        <!-- Header -->
        <div class="header">
            <div>
                <h1 class="brand-title">USUStarterMap Copy Center</h1>
                <p class="store-info">{{ $order->store->name ?? 'Gerai Fotocopy USU' }}</p>
                <p class="store-info">📍 {{ $order->store->location_name ?? 'Kampus USU' }} &bull; WA: {{ $order->store->whatsapp ?? '-' }}</p>
            </div>
            <div style="text-align: right;">
                <span class="spk-badge">Lembar Kerja Cetak</span>
                <p style="font-size: 13px; font-weight: 800; font-family: monospace; margin-top: 6px;">{{ $order->order_code }}</p>
                <p style="font-size: 10px; color: #9ca3af;">{{ $order->created_at->format('d M Y, H:i') }} WIB</p>
            </div>
        </div>

        <!-- Identitas Pemesan & Status -->
        <div class="info-grid">
            <div class="info-item">
                <label>Nama Pemesan / Mahasiswa</label>
                <span>{{ $order->customer_name }}</span>
            </div>
            <div class="info-item">
                <label>Nomor WhatsApp</label>
                <span>{{ $order->customer_phone ?? '-' }}</span>
            </div>
            <div class="info-item">
                <label>Metode Pembayaran</label>
                <span>{{ $order->payment_method }} ({{ strtoupper($order->payment_status) }})</span>
            </div>
            <div class="info-item">
                <label>Status Pesanan</label>
                <span>{{ $order->order_status }}</span>
            </div>
        </div>

        <!-- Detail Lampiran File Dokumen -->
        @if($order->hasFile())
        <div class="section-title">Dokumen yang Dicetak:</div>
        <div class="file-banner">
            <div class="file-icon">
                {{ strtoupper($order->file_type ?? 'PDF') }}
            </div>
            <div class="file-details">
                <p class="file-title">{{ $order->file_name ?? 'Dokumen Mahasiswa.pdf' }}</p>
                <p class="file-meta">
                    Ukuran: {{ $order->file_size ?? 'Dokumen Siap Cetak' }} &bull; Format: {{ strtoupper($order->file_type ?? 'PDF') }} &bull; Status: Lampiran Tersedia
                </p>
            </div>
        </div>
        @else
        <div class="section-title">Dokumen yang Dicetak:</div>
        <div class="file-banner" style="background: #f0fdf4; border-color: #bbf7d0;">
            <div class="file-icon" style="background: #10b981;">
                WA
            </div>
            <div class="file-details">
                <p class="file-title" style="color: #065f46;">File Dikirim via WhatsApp / Flashdisk Walk-in</p>
                <p class="file-meta" style="color: #047857;">
                    Silakan periksa chat WhatsApp pemilik toko atau transfer flashdisk pelanggan
                </p>
            </div>
        </div>
        @endif

        <!-- Catatan Khusus Mahasiswa -->
        @if($order->notes)
        <div class="section-title">Catatan Khusus / Instruksi Tambahan:</div>
        <div class="notes-box">
            <strong>Instruksi:</strong> "{{ $order->notes }}"
        </div>
        @endif

        <!-- Rincian Layanan & Jumlah Lembar -->
        <div class="section-title">Rincian Layanan & Tarif:</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Layanan Cetak</th>
                    <th class="qty">Jumlah</th>
                    <th style="text-align: right;">Tarif Satuan</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->service_name }}</strong>
                        <span style="font-size: 10px; color: #9ca3af; display: block;">Kategori: {{ $item->category }}</span>
                    </td>
                    <td class="qty">{{ $item->quantity }}x</td>
                    <td style="text-align: right; font-family: monospace;">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="subtotal">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Total Tagihan -->
        <div class="total-row">
            <span class="total-label">Total Tagihan Kasir:</span>
            <span class="total-amount">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
        </div>

        <!-- Tanda Tangan -->
        <div class="footer-signatures">
            <div class="sig-box">
                <p>Operator Kasir</p>
                <div class="sig-line"></div>
                <p style="margin-top: 4px;">{{ auth()->user()->name ?? 'Staf Kasir' }}</p>
            </div>
            <div class="sig-box">
                <p>Pelanggan / Mahasiswa</p>
                <div class="sig-line"></div>
                <p style="margin-top: 4px;">{{ $order->customer_name }}</p>
            </div>
        </div>
    </div>

    <script>
        // Otomatis picu dialog pencetakan browser
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') !== '0') {
                setTimeout(() => {
                    window.print();
                }, 500);
            }
        });
    </script>
</body>
</html>
