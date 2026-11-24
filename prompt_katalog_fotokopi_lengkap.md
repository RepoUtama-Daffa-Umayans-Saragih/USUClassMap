# FASE BERAT: RISET MENDALAM, SCRAPING GAMBAR ASLI & PENGISIAN KATALOG LENGKAP UNTUK SEMUA 31 TOKO FOTOKOPI

**Peran Anda:** Principal Full-Stack Laravel Engineer, Data Researcher & Web Scraping Specialist.  
**Target Proyek:** USUClassMap (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`).

## OTORISASI PENUH PENGGUNAAN TOOLS
Anda diizinkan dan **WAJIB** menggunakan seluruh tool yang tersedia di lingkungan Anda—termasuk **Web Search, Browser Agent, Terminal (Python/Node/PHP/cURL), Database Migration, Seeder, dan File Editor**—untuk melakukan riset internet mendalam, mencari & mengunduh gambar produk asli, memverifikasi gambar agar tidak rusak, serta menanamkan seluruh data secara otomatis ke sistem.

---

## FASE 1: AUDIT SKEMA DATABASE & KOMPATIBILITAS DUAL-TABLE (WAJIB PERTAMA)
1. Inspeksi tabel dan model berikut:
   - `fotocopy_stores` (`FotocopyStore`) & `photocopy_places` (`PhotocopyPlace`) — seluruh 31 mitra toko.
   - `fotocopy_services` (`FotocopyService`) & `photocopy_items` (`PhotocopyItem`) — katalog layanan & produk.
   - Controller & Resource: `PhotocopyController`, `Api\CopyShopApiController`, `CopyShopResource`, `AdminFotokopi\ServiceController`, dan `CashierFotokopi\OrderController`.
2. **Perluasan Skema Aman (Backward-Compatible Migration):**
   - Jika tabel `fotocopy_services` dan `photocopy_items` belum memiliki kolom untuk katalog kaya metadata, buat migrasi baru yang **hanya menambah kolom opsional (`nullable`)** tanpa menghapus/mengubah kolom lama agar fitur Kasir POS & Checkout tetap 100% jalan:
     - `item_type` (`enum`/string: `'jasa'` atau `'barang'`, default `'jasa'`)
     - `sub_category` (string, misal: `'Pulpen Gel'`, `'Print Warna'`, `'Kertas HVS'`, `'Alat Ukur'`, `'Perlengkapan Skripsi'`)
     - `brand` (string nullable, misal: `'Standard'`, `'Pilot'`, `'Joyko'`, `'Kenko'`, `'Snowman'`, `'Sinar Dunia'`, `'Faber-Castell'`, `'Zebra'`, `'Staedtler'`, `'Casio'`, `'Kangaro'`, atau `null` untuk jasa)
     - `pack_qty` (integer, default `1` — jumlah pcs/lembar per satuan beli, misal `1` untuk eceran, `12` untuk 1 lusin/pak, `500` untuk 1 rim)
     - `unit` (string, misal: `'pcs'`, `'lembar'`, `'pak (12 pcs)'`, `'box (12 pcs)'`, `'rim (500 lbr)'`, `'jilid'`, `'set'`, `'roll'`, `'botol'`)
     - `image_path` (string nullable — path relatif gambar lokal di `storage/app/public/fotocopy_catalog/...`)
     - `description` (text nullable — penjelasan detail kegunaan, ukuran, warna tinta, gramasi kertas, atau spesifikasi jasa)
     - `metadata` (json nullable — menyimpan detail teknis terstruktur seperti `{"merk": "...", "ukuran": "0.5mm", "warna": "Hitam/Biru/Merah", "isi_per_unit": "1 pcs", "gramasi": "80 gsm", "kode_sku": "..."}`)
     - `stock` (integer nullable — stok tersedia untuk barang fisik, `null` untuk jasa)
   - Jalankan `php artisan migrate`.

---

## FASE 2: RISET MENDALAM DI INTERNET & PENYUSUNAN MASTER KATALOG (MINIMAL 120–160+ SKU UNIK)
Lakukan **riset mendalam di internet** tentang seluruh barang dan jasa yang dijual di fotokopi & toko ATK sekitar kampus Indonesia (termasuk barang-barang kecil/niche yang sering dibutuhkan mahasiswa namun jarang terpikirkan). Simpan hasil riset ke file master `USUClassMap/database/seeders/data/master_fotocopy_catalog.json`.

**Aturan Wajib Penyusunan Katalog:**
1. **Harus Mencakup Penjualan Terpisah (Eceran / Satuan 1 pcs) DAN Paket/Grosir:**
   - Contoh pulpen: sediakan varian beli satuan (**1 pcs**) sekaligus varian beli **1 pak / 1 lusin (12 pcs)** dengan harga grosir lebih hemat.
   - Contoh kertas HVS/Folio: sediakan eceran (**1 lembar / 10 lembar**) sekaligus **1 pak / 1 rim (500 lembar)**.
   - Contoh binder clip, amplop, stopmap: sediakan **1 pcs** dan **1 box / 1 pak**.
2. **Keragaman Merek & Jenis Nyata di Setiap Sub-Kategori:**
   - **Jasa Print & Cetak (Harga Realistis Kampus):**
     - Print Hitam Putih HVS A4 70gsm (Rp 250–Rp 500/lembar) & 80gsm (Rp 350–Rp 600/lembar)
     - Print Hitam Putih HVS F4/Folio (Rp 300–Rp 500/lembar)
     - Print Warna Teks/Grafik/Logo A4 (Rp 500–Rp 1.000/lembar) & F4 (Rp 750–Rp 1.250/lembar)
     - Print Warna Full Block / Foto / Poster A4 (Rp 1.500–Rp 3.000/lembar)
     - Print Bolak-Balik (Duplex) Hitam Putih & Warna
     - Print Kertas Khusus: Glossy Photo Paper, Art Paper / Art Carton 260gsm, Kertas Stiker HVS/Chromo/Vinyl, Kertas Concorde/Sertifikat, Kertas Kalkir, Kertas Transparansi OHP, Print A3 Hitam Putih & Warna
   - **Jasa Fotokopi:**
     - Fotokopi A4 & F4 1 Sisi (Rp 200–Rp 350/lembar) & Bolak-Balik (Rp 350–Rp 500/lembar)
     - Fotokopi A3, Fotokopi Perbesar/Perkecil, Fotokopi Buku/Modul, Fotokopi KTP/KK/Ijazah
   - **Jasa Jilid, Laminating & Finishing:**
     - Jilid Lakban Biasa + Mika Bening & Buffalo, Jilid Softcover/Direct Binding, Jilid Hardcover Skripsi (Tinta Emas/Emboss + Pita Pembatas), Jilid Spiral Plastik, Jilid Spiral Kawat (Ring)
     - Laminating KTP/KTM, Laminating A4, Laminating F4, Laminating A3, Press Panas, Potong Kertas Mesin
   - **Jasa Digital, Scan & Cetak Foto:**
     - Scan Dokumen ke PDF/JPG per lembar & paket per bab, Kirim/Upload File, Burning CD-R/DVD-R Skripsi
     - Cetak Pas Foto (2x3, 3x4, 4x6 — paket isi 4/6/8 lembar) & Ganti Background Merah/Biru
     - Jasa Merapikan Format/Margin Skripsi & Pengetikan per halaman
   - **ATK — Pulpen, Pensil, Spidol & Stabilo (Banyak Merek & Ketebalan):**
     - Pulpen: Standard AE7 0.5mm (Hitam/Biru/Merah), Pilot G2 Gel 0.5mm, Kenko Gel K-1 0.5mm, Joyko GP-265 Q-Gel, Snowman V-5, Zebra Sarasa Clip 0.5mm, Faber-Castell Grip X5, Faster C6, Pulpen 4 Warna, Refill Tinta Gel
     - Pensil: Faber-Castell 2B Ujian, Staedtler Mars Lumograph 2B, Joyko 2B, Pensil Mekanik 0.5mm/0.7mm + Isi Pensil (Lead)
     - Spidol & Highlighter: Snowman Whiteboard BG-12 + Tinta Refill, Snowman Permanent G-12, Spidol Kecil 12 Warna, Stabilo Boss Original, Joyko Highlighter Pastel
   - **Koreksi, Penghapus & Perekat:**
     - Tipe-X Cair (Kenko KE-01, Joyko CF-S205), Correction Tape/Tipe-X Kertas (5mm x 12m), Penghapus Staedtler Rasoplast, Faber-Castell Dust Free, Joyko B40
     - Lem Kertas (Glukol, Povinal Cair, Glue Stick Kenko/UHU, Lem UHU All Purpose, Lem Korea/Alteco)
     - Lakban Bening 2 inch, Lakban Coklat Packing, Lakban Hitam Jilid Kain (Cloth Tape 1.5" & 2"), Isolasi Bening Kecil, Masking Tape (Lakban Kertas), Double Tape (12mm & 24mm), Foam Double Tape
   - **Kertas, Buku & Media Gambar Teknik:**
     - HVS Sinar Dunia (SiDU) & PaperOne A4/F4 70gsm & 80gsm (eceran & 1 rim 500 lbr)
     - Kertas Double Folio Bergaris (eceran 1 lbr & 1 pak 100 lbr), Kertas Milimeter Blok A4/F4, Kertas Kalkir A4/A3, Kertas Buffalo Warna, Kertas Manila/Karton, Kertas Concorde/Linen, Kertas Origami, Kertas HVS Warna
     - Buku Tulis SiDU (38 & 58 lbr), Buku Campus Big Boss / Boxy, Loose Leaf B5 & A5 (Garis/Polos/Grid 50 & 100 lbr), Buku Gambar A4/A3, Buku Nota Kontan 2 Ply & 3 Ply, Buku Kwitansi
   - **Map, Amplop & Pengarsipan:**
     - Stopmap Kertas Biola/Folio (eceran & pak 50 pcs), Map Snellhecter Plastik & Kertas (Akselerasi Tugas), Map Kancing Plastik (Document Keeper), Map L Plastik Bening, Zipper Bag / Map Resleting Jaring, Clear Holder / Display Book (20/40/60 pocket), Binder Ring A5 & B5 (Plastik & Kulit Sintetis)
     - Amplop Putih Polos Merpati/Paperline (No. 90 & No. 104 — satuan & 1 box 100 pcs), Amplop Coklat Tali A4/F4/Kabinet/Map Lamaran Kerja (satuan & pak)
     - Sticky Notes / Post-it 3x3 & Page Marker Panah Warna
   - **Perkakas Meja, Pengukur & Penjepit:**
     - Stapler Kangaro/Joyko HD-10 & HD-50 + Isi Staples No. 10 & No. 3 (eceran 1 box kecil & 1 pak besar)
     - Binder Clip Joyko/Kenko semua ukuran (No. 105, 107, 111, 155, 200, 260 — satuan & 1 box 12 pcs)
     - Paper Clip Trigonal No. 3 & No. 5, Cutter Joyko L-500 Besar & A-300 Kecil + Isi Pisau Cutter, Gunting Stainless (Kecil/Sedang/Besar), Pembolong Kertas (Puncher 2 Lubang), Rautan Pensil
     - Penggaris Plastik Butterfly 30cm, Penggaris Besi 30cm, Penggaris Segitiga Sepasang, Busur Derajat, Jangka Matematika Joyko/Faber-Castell
   - **Barang Niche / Jarang Terpikirkan Tapi Wajib Ada di Fotokopi Kampus:**
     - **Materai 10.000 Asli (1 pcs)**
     - **ID Card Holder / Name Tag Panitia B1/B2/B3/B4 + Tali Lanyard (satuan & paket)**
     - **Flashdisk 16GB / 32GB / 64GB (SanDisk Cruzer Blade / Kingston)**
     - **Keping CD-R & DVD-R Kosong (Verbatim/GT-Pro) + Kotak/Sleeve CD Skripsi**
     - **Baterai AA, AAA, & Baterai Kancing LR44 Kalkulator (ABC/Alkaline)**
     - **Kalkulator Ilmiah / Scientific Calculator (Casio fx-991EX / Joyko CC-23)**
     - **Papan Ujian / Clipboard Kayu & Plastik A4/F4**
     - **Plastik Sampul Buku / Mika Roll Bening & Sampul Coklat**
     - **Bantalan Stempel (Stamp Pad) + Tinta Stempel (Biru/Ungu/Hitam)**
     - **Push Pin / Paku Mading, Peniti, Karet Gelang Pak, Paper Bag/Totebag Skripsi, Label Stiker Tom & Jerry (No. 103/107/121)**

---

## FASE 3: SCAN INTERNET & UNDUH GAMBAR ASLI TERVERIFIKASI (ZERO BROKEN IMAGES)
1. Buat skrip Python (`USUClassMap/scripts/fetch_fotocopy_catalog_images.py`) yang melakukan pencarian gambar nyata di internet untuk setiap produk & kategori jasa pada `master_fotocopy_catalog.json` (gunakan pencarian web/gambar publik yang stabil seperti DuckDuckGo Image Search / Wikimedia / sumber gambar produk terbuka yang bebas proteksi hotlink).
2. **Simpan Lokal & Verifikasi Mutlak:**
   - Unduh setiap gambar ke `USUClassMap/storage/app/public/fotocopy_catalog/<slug>.jpg` (atau `.webp`).
   - Skrip wajib memvalidasi setiap file: status HTTP `200`, ukuran file `> 2 KB`, bisa dibuka valid oleh `Pillow (PIL)`, lalu resize otomatis ke maksimal lebar `500px` (kompresi berkualitas baik ~15–45 KB per gambar agar ringan untuk Git).
   - **Dilarang keras** menyimpan URL eksternal mentah yang bisa 404 atau gambar kosong/corrupt. Jika satu sumber gagal untuk suatu SKU, skrip otomatis mencoba query pencarian alternatif sampai gambar valid terunduh.
   - Pastikan `php artisan storage:link` aktif dan tambahkan pengecualian `!fotocopy_catalog/` serta `!fotocopy_catalog/**` di `USUClassMap/storage/app/public/.gitignore` agar seluruh gambar katalog ikut tersinkron via Git.

---

## FASE 4: DISTRIBUSI LENGKAP, BERVARIASI & SINKRON KE SEMUA 31 TOKO FOTOKOPI
1. Buat seeder idempoten `USUClassMap/database/seeders/RichFotocopyCatalogSeeder.php`.
2. Distribusikan katalog dari `master_fotocopy_catalog.json` ke **SEMUA 31 toko** yang ada di `fotocopy_stores` dan `photocopy_places`:
   - Setiap toko memiliki **50 hingga 95+ item & jasa** (total **~2.000+ baris katalog** di seluruh 31 toko).
   - Buat variasi realistis antar toko (tidak 100% copy-paste identik):
     - Berikan profil spesialisasi berbeda per toko (misal: *Spesialis Skripsi & Hardcover*, *ATK & Gambar Teknik Terlengkap*, *Cetak Kilat & Print Murah*, *Serba Ada Kampus*).
     - Variasikan harga jasa secara wajar antar toko (misal Print B&W antar toko berkisar Rp 250 / Rp 300 / Rp 400 / Rp 500; Print Warna Rp 500 / Rp 750 / Rp 1.000; ATK selisih wajar Rp 500–Rp 2.000).
     - Variasikan pilihan merek dan paket eceran/lusinan per toko, namun pastikan seluruh barang inti (Print B&W, Print Warna, Fotokopi, Jilid, Pulpen eceran & pak, Kertas, Map, Materai, dll.) hadir melimpah.
   - **Wajib Sinkron Dual-Table:** Pastikan data pada **`fotocopy_services`** (untuk Admin Fotokopi & Kasir POS) dan **`photocopy_items`** (untuk Publik `/fotocopy/{id}` & API `/api/copy-shops`) tersinkron 100% tanpa duplikasi kotor.
3. Jalankan seeder tersebut.

---

## FASE 5: UPGRADE TAMPILAN KATALOG (FRONTEND & API) — ZERO FEATURE LOSS
1. Perbarui `CopyShopResource` dan `PhotocopyController` agar mengekspos `image_url`, `item_type` (`jasa`/`barang`), `sub_category`, `brand`, `pack_qty`, `unit`, `description`, `metadata`, dan `stock`.
2. Perbarui tampilan halaman detail toko publik (`/fotocopy/{id}`), halaman order (`/fotocopy/{id}/order`), panel Admin Fotokopi (`/admin-fotokopi/services`), dan Kasir POS (`/cashier-fotokopi/pos`) agar:
   - Menampilkan **gambar produk**, **badge Jasa vs Barang**, **badge Merek & Isi (misal: `1 pcs` / `1 pak (12 pcs)`)**, **deskripsi/metadata teknis**, serta **filter kategori + kotak pencarian cepat** (sangat penting karena setiap toko kini memiliki 50–95+ item!).
   - Gunakan `loading="lazy"` pada seluruh tag `<img>` katalog agar halaman tetap ringan dan cepat.
   - Dilarang merusak alur keranjang order, kalkulator harga, pembayaran QRIS/Transfer/Tunai, maupun cetak SPK Kasir POS.

---

## FASE 6: AUTO-SYNC GIT SNAPSHOT & VERIFIKASI AKHIR
1. Jalankan `php artisan app:git-sync --export` agar seluruh ribuan data katalog terbaru pada `fotocopy_services.json`, `photocopy_items.json`, dan `manifest.json` langsung tersimpan ke folder snapshot Git (`database/seeders/snapshots/`).
2. Jalankan verifikasi menyeluruh:
   - Cek jumlah total toko terisi (harus 31/31), total baris `fotocopy_services` & `photocopy_items`, dan jumlah gambar valid di `storage/app/public/fotocopy_catalog/` (pastikan 0 gambar corrupt/missing).
   - Jalankan `php -l` pada seluruh file PHP yang dibuat/diubah.
   - Jalankan `npm run build` di folder `USUClassMap`.
3. Laporkan ringkasan lengkap hasil riset kategori, statistik data per toko, total gambar terverifikasi, serta perubahan file yang dilakukan.