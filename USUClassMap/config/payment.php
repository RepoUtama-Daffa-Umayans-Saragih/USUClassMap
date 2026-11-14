<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Strategy Configuration for USUClassMap Photocopy Orders
    |--------------------------------------------------------------------------
    |
    | Configuration for Cash, dynamic QRIS, and Proof of Concept Bank Transfer.
    |
    */

    'bank_transfer' => [
        // PoC static VA number shared across all supported banks
        'static_bank_code' => env('PAYMENT_STATIC_BANK_CODE', '88019920260001'),
        'account_name' => 'USUClassMap Fotokopi',
        'supported_banks' => [
            'BCA' => [
                'name' => 'Bank Central Asia',
                'code' => '014',
                'color' => '#005EAA',
                'prefix' => 'BCA-VA',
                'instructions' => [
                    'Buka BCA mobile > m-Transfer > BCA Virtual Account.',
                    'Masukkan Nomor Virtual Account: 88019920260001.',
                    'Pastikan nama penerima adalah USUClassMap Fotokopi.',
                    'Periksa nominal transfer dan selesaikan transaksi dengan PIN Anda.'
                ],
            ],
            'Mandiri' => [
                'name' => 'Bank Mandiri',
                'code' => '008',
                'color' => '#003366',
                'prefix' => 'MANDIRI-VA',
                'instructions' => [
                    'Buka Livin\' by Mandiri > Bayar > Cari penyedia jasa USUClassMap.',
                    'Masukkan Kode Bayar: 88019920260001.',
                    'Pastikan nama penerima adalah USUClassMap Fotokopi.',
                    'Konfirmasi pembayaran dan masukkan PIN Livin\' Anda.'
                ],
            ],
            'BNI' => [
                'name' => 'Bank Negara Indonesia',
                'code' => '009',
                'color' => '#E05A2B',
                'prefix' => 'BNI-VA',
                'instructions' => [
                    'Buka BNI Mobile Banking > Transfer > Virtual Account Billing.',
                    'Masukkan Nomor VA: 88019920260001.',
                    'Tagihan akan muncul secara otomatis di layar.',
                    'Selesaikan transaksi dengan memasukkan password transaksi.'
                ],
            ],
            'BRI' => [
                'name' => 'Bank Rakyat Indonesia',
                'code' => '002',
                'color' => '#00529C',
                'prefix' => 'BRIVA',
                'instructions' => [
                    'Buka aplikasi BRImo > Pembayaran > BRIVA.',
                    'Masukkan Nomor BRIVA: 88019920260001.',
                    'Periksa kesesuaian rincian pemesanan dan total tagihan.',
                    'Konfirmasi dan masukkan PIN BRImo Anda.'
                ],
            ],
            'BSI' => [
                'name' => 'Bank Syariah Indonesia',
                'code' => '451',
                'color' => '#00A39D',
                'prefix' => 'BSI-VA',
                'instructions' => [
                    'Buka BSI Mobile > Bayar > Institusi Akademik/Mitra.',
                    'Masukkan Kode Pembayaran: 88019920260001.',
                    'Pastikan nama akun adalah USUClassMap Fotokopi.',
                    'Masukkan PIN BSI Mobile Anda untuk menyelesaikan pembayaran.'
                ],
            ],
        ],
    ],

    'qris' => [
        'merchant_name' => 'USUClassMap Fotokopi',
        'merchant_id' => 'ID1020260000001',
        'acquirer' => 'ASPI / Bank Indonesia',
        'city' => 'Medan',
        'postal_code' => '20155',
        'expiry_minutes' => 15,
        'instructions' => [
            'Buka aplikasi perbankan atau e-wallet pilihan Anda (BCA, Mandiri, GoPay, OVO, ShopeePay, Dana, dll).',
            'Pindai (Scan) gambar QR Code di atas menggunakan menu scan QRIS.',
            'Periksa nama merchant: USUClassMap Fotokopi dan nominal tagihan.',
            'Lanjutkan pembayaran hingga transaksi dinyatakan berhasil di aplikasi Anda.'
        ],
    ],

    'cash' => [
        'instructions' => [
            'Datangi langsung kasir toko fotokopi yang Anda pilih sesuai lokasi tertera.',
            'Tunjukkan Kode Referensi Pesanan (#ORD-...) kepada staf kasir.',
            'Lakukan pembayaran tunai pas sesuai total tagihan saat mengambil cetakan/dokumen Anda.'
        ],
    ],
];
