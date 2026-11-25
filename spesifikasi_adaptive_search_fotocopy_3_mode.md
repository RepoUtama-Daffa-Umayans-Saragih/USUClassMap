# Prompt & Spesifikasi Teknis: Intent-Aware Adaptive Search (`/fotocopy`)

## Peran & Target Proyek
- **Peran:** Principal Laravel Architect, Search UX Designer, dan Senior Full-Stack Developer (Laravel, Blade, Alpine.js, Tailwind CSS).
- **Target Proyek:** `USUClassMap` (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`).
- **Target Utama:** Halaman `/fotocopy` (`resources/views/features/fotocopy/public/index.blade.php`), `PhotocopyController`, `QueryIntentParserService`, dan `SmartPhotocopySearchService`.

---

## Misi Utama & Aturan Ketat

1. **Rombak Alur Hasil Pencarian `/fotocopy` Menjadi 1 Panggung Adaptif 3-Mode (Anti Bingung & Anti Spam Kartu Kembar):**
   - **Mode `store` (Cari Tempat Fotokopi):** Saat user mengetik nama lokasi/fakultas (`deket teknik`, `fotocopy dekat FT`, `dekat perpus`), nama toko (`USU Press`, `Pak Budi`), kata generik tempat (`fotocopy buka sekarang`, `fotocopy terdekat`), atau saat pencarian kosong → tampilkan **Store Card** (fokus gerai & jarak, **bukan** 12 kartu produk generik *"Fotokopi Hitam Putih A4"*).
   - **Mode `product` (Cari Produk/Jasa):** Saat user mengetik barang/jasa spesifik (`pulpen pink`, `tipec`, `print warna murah`, `jilid hardcover`, `kertas hvs a4`) → tampilkan **Product-First Card** dengan *keyword highlight* (`<mark>`), badge pembeda (`Termurah` / `Terdekat` / `Paling Cocok`), harga menonjol, dan info toko + jarak ringkas.
   - **Mode `mixed` (Campuran Produk + Lokasi/Toko):** Saat user menggabungkan keduanya (`pulpen pink deket teknik`, `print warna dekat FIB`, `jilid hardcover di USU Press`) → tampilkan **Store-Grouped Combo Card** (1 kartu = 1 gerai di area/toko yang diminta + daftar produk yang dicari beserta harga & tombol pesan langsung di dalam kartu tersebut).
   - **Hapus Duplikasi Section Daftar:** Jangan lagi menampilkan 2 grid besar bertumpuk (grid Smart Search di atas + grid *"Semua Tempat Fotocopy Kampus USU"* di bawah). Gabungkan ke **1 area hasil tunggal** yang dikendalikan oleh *Smart Intent Bar* + *Tab Switcher* (`Tempat Fotokopi (Z)` | `Produk & Jasa (Y)` | `Terpadu (X Toko)` saat `mixed`).

2. **Zero Feature Loss & Anti-JS Leak:**
   - Pertahankan hero hijau (tanpa search bar), bar status live WIB, kotak Smart Search terpadu (judul `Cari Tempat Fotokopy, Produk ATK, Print & Jilid Terbaik`, Auto-GPS real-time, filter `Urutkan`, `Buka Sekarang Saja`, `Maks Rp`, chip hijau muda), serta chatbot **Tanya Ucup**.
   - Pertahankan seluruh fitur di luar `/fotocopy` (`/map`, FIB 360°, Admin/Kasir, Laporan, `vite.config.js` dengan `copilot.js` & `sourcemap: false`, serta Apache Jena Fuseki `5.025` triples).
   - Seluruh logika Alpine.js/JS kompleks **wajib** berada di dalam blok `<script>` (`0` kebocoran kode JS di atribut HTML `x-data`).

---

## FASE 1: Deteksi Niat 3 Mode (`store` | `product` | `mixed`) di Backend

Perbarui `App\Services\QueryIntentParserService` dan `App\Services\SmartPhotocopySearchService` (serta endpoint `GET /api/fotocopy/smart-search`):

### 1.1 Klasifikasi `search_mode` yang Presisi
Deteksi dua sinyal terpisah dari kueri user:
- `has_location_or_store_intent`: `true` jika kueri menyebut lokasi/fakultas (`teknik`, `ft`, `fib`, `fisip`, `fk`, `perpus`, `fasilkom`, `fmipa`, `hukum`, `feb`, `fkm`, `farmasi`, `psikologi`, `deket ...`, `dekat ...`), nama toko (`usu press`, `usupress`, `pak budi`, `berkah abadi`, `trial admin`, dll.), atau kata generik gerai (`fotocopy`, `fotokopi`, `toko`, `gerai`, `tempat`).
- `has_specific_product_intent`: `true` **hanya** jika kueri menyebut barang ATK atau jasa cetak/finishing spesifik (`pulpen`, `pena`, `tipec`, `tipe-x`, `tipex`, `correction tape`, `stabilo`, `highlighter`, `kertas`, `hvs`, `print`, `cetak`, `jilid`, `hardcover`, `softcover`, `spiral`, `laminating`, `scan`, `stiker`, `pas foto`, `sertifikat`, `poster`, `map`, `binder`, `materai`, `pensil`, `penghapus`, `penggaris`, `spidol`, `sticky notes`, `stapler`, `cutter`, `gunting`, `lem`, `lakban`, `flashdisk`, atau spesifikasi cetak seperti `bolak balik`, `warna`, `a4`, `f4`, `a3`, `skripsi`).
- **Aturan Kritis:** Kata generik `"fotocopy"` / `"fotokopi"` (seperti `"fotocopy dekat FT"`, `"fotocopy buka sekarang"`, `"deket teknik"`) **tidak boleh** dianggap sebagai `has_specific_product_intent`.

Penentuan `search_mode`:
1. Kueri kosong → `'store'`
2. `has_specific_product_intent && has_location_or_store_intent` (ada produk spesifik + lokasi fakultas/nama toko spesifik, bukan sekadar kata `"fotocopy"`/`"terdekat"`) → `'mixed'`
3. `has_specific_product_intent` saja (termasuk dengan modifier `"murah"`, `"terdekat"`, `"buka sekarang"`) → `'product'`
4. Lainnya (hanya lokasi, nama toko, atau kata generik fotokopi) → `'store'`

### 1.2 Format Respons JSON `/api/fotocopy/smart-search`
Pertahankan seluruh field existing agar **Tanya Ucup** dan tes lama tetap backward-compatible, lalu tambahkan field terstruktur berikut:
- `search_mode`: `'store' | 'product' | 'mixed'`
- `intent_summary`:
  - `mode_label`: `'Tempat Fotokopi' | 'Produk & Jasa' | 'Produk + Lokasi'`
  - `headline`: kalimat singkat & jelas (mis. `"Tempat fotokopi terdekat dari Departemen Teknik Mesin (FT-MESIN)"`, `"Produk 'pulpen pink' termurah & terdekat"`, atau `"Toko dekat Teknik yang menyediakan 'pulpen pink'"`).
  - `pills`: array chip ringkasan konteks terbaca (`[{ type: 'mode'|'product'|'location'|'filter', label: '...' }]`).
  - `highlight_terms`: array kata kunci bersih untuk di-highlight di kartu (`['pulpen', 'pink']` atau `['usu', 'press']`).
- `stores`: daftar **tempat fotokopi** yang sudah terfilter & terurut cerdas (jarak dari lokasi acuan / GPS, prioritas `is_open_now`, nama toko cocok), berisi:
  `id`, `name`, `slug`, `address`, `photo_url`, `rating`, `distance_m`, `walking_minutes`, `anchor_label`, `is_open_now`, `status_label`, `hours_label`, `starting_price`, `service_tags` (maks 4 kategori populer toko, mis. `['Print', 'Fotokopi', 'Jilid', 'ATK']`), `matching_items_count`, `order_url`, `detail_url`, `map_route_url`.
- `items`: daftar **produk/jasa** yang sudah terfilter & terurut cerdas, plus flag pembeda `highlight_badge` (`'Termurah'` untuk harga terendah, `'Terdekat'` untuk jarak terdekat, `'Paling Cocok'` untuk skor tertinggi — maksimal 1 badge per kartu agar bersih).
- `mixed_groups`: daftar **pengelompokan per toko** khusus mode `mixed` (hanya toko yang relevan dengan lokasi/nama toko dan memiliki produk yang dicari), setiap grup memuat:
  - `store`: objek toko lengkap (nama, foto, status buka/tutup, rating, jarak dari lokasi acuan, `order_url`, `map_route_url`),
  - `matched_items`: array produk cocok milik toko tersebut (terurut harga termurah/skor tertinggi, berisi `id`, `name`, `category`, `price`, `unit`, `image_url`, `is_exact_match`),
  - `total_matched_items`: total item cocok di toko itu,
  - `lowest_matched_price`: harga termurah di toko itu untuk kueri ini.

---

## FASE 2: Desain 3 Jenis Kartu Adaptif & 1 Alur Bersih di `/fotocopy`

Rombak area hasil di `resources/views/features/fotocopy/public/index.blade.php` dengan tetap mempertahankan tema hijau USU, desain kartu Tailwind yang bersih, dan seluruh fungsi Auto-GPS:

### 2.1 Smart Intent Bar + Manual Mode Switcher (Tepat di Bawah Chip Hijau Muda)
- Tampilkan 1 bar kontras ringan (`bg-emerald-50/70 border border-emerald-100 rounded-2xl px-4 py-3 flex flex-wrap items-center justify-between gap-3`):
  - **Sisi Kiri:** Ikon mode + **Headline singkat** (tebal) + deretan **Intent Pills** kecil (mis. pill biru `Area: Teknik (FT)`, pill hijau `Produk: Pulpen Pink`, pill amber `Filter: Buka Saja`).
  - **Sisi Kanan:** **Segmented Tab Switcher** yang otomatis menyala mengikuti `search_mode`:
    - Jika `search_mode === 'mixed'`: tampilkan 3 tab rapi: **`Terpadu (X Toko)`** *(aktif default)* | **`Produk (Y)`** | **`Semua Toko (Z)`**.
    - Jika `search_mode === 'store'` atau `'product'`: tampilkan 2 tab rapi: **`Tempat Fotokopi (Z)`** | **`Produk & Jasa (Y)`** (otomatis memilih tab sesuai `search_mode`, dan user bebas klik tab sebelahnya kapan saja).
- Jika ada `advisory_note` (mis. pencarian warna yang tidak ada), tampilkan banner info ramah tepat di bawah bar ini.

### 2.2 Desain 3 Jenis Kartu yang Berbeda Jelas

1. **Tampilan 1 — Store Card (Saat tab `Tempat Fotokopi` aktif, mis. `deket teknik`, `USU Press`, atau halaman awal):**
   - Grid `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5`.
   - **Header Visual Gerai:** Foto toko (`h-40 w-full object-cover rounded-t-2xl`, fallback gambar toko valid tanpa broken image) + overlay badge pojok kiri atas **`Buka Sekarang`** (hijau) / **`Tutup`** (merah `#DC2626`) + badge pojok kanan atas **Rating** (`★ 4.9`) + badge jarak cepat (`~237 m`).
   - **Badan Kartu:**
     - **Nama Toko Besar & Jelas** (`text-lg font-bold text-slate-900`, dengan highlight `<mark>` bila user mengetik nama toko seperti `USU Press`).
     - **Baris Jarak & Acuan Menonjol** (`text-sm font-medium text-emerald-700`, mis. `~237 m (4 mnt jalan) dari Departemen Teknik Mesin`).
     - Alamat singkat + jam operasional WIB (`07:30 - 21:00 WIB`).
     - Baris layanan & harga: `Mulai Rp 200` + maks 4 pill kecil kategori (`Print`, `Fotokopi`, `Jilid`, `ATK`).
   - **Footer 2 Tombol Proporsional:** **`Buka Toko & Pesan`** (hijau utama) + **`Rute Peta`** (outline amber/slate).
   - **Catatan Penting:** Hapus section terpisah *"Semua Tempat Fotokopy Kampus USU"* yang dulu menumpuk di bawah — saat input kosong atau mode `store`, grid ini menjadi satu-satunya daftar 33 mitra fotokopi (bersih, tidak dobel, dan sinkron langsung dengan filter `Buka Saja` & `Urutkan`).

2. **Tampilan 2 — Product-First Card (Saat tab `Produk & Jasa` aktif, mis. `pulpen pink`, `tipec`, `print warna`):**
   - Grid `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5`.
   - **Header Produk:** Foto produk (`w-20 h-20 rounded-xl object-cover border`) di sebelah kiri + **Badge Kategori & Badge Pembeda** (`Termurah` warna emerald solid, `Terdekat` warna biru, `Paling Cocok` warna amber) + **Judul Produk Besar** dengan **highlight kata kunci (`<mark class="bg-amber-100 text-amber-950 px-1 rounded">`)** pada kata yang dicari user.
   - **Baris Harga Menonjol:** **`Rp 3.000`** (`text-2xl font-extrabold text-emerald-700`) + satuan (`/pcs`, `/lembar`, `/jilid`).
   - **Strip Info Penjual Ringkas (`bg-slate-50 rounded-xl p-2.5`):** Nama Toko (tebal) + `★ 4.9` + pill **`Buka`** / **`Tutup`** + jarak (`~180 m • 3 mnt jalan`). Hapus teks redundan 1 baris abu-abu panjang yang mengulang harga & jarak dua kali.
   - **Footer 2 Tombol:** **`Pesan di Toko Ini`** + **`Rute Peta`**.

3. **Tampilan 3 — Store-Grouped Combo Card (Saat tab `Terpadu` aktif di mode `mixed`, mis. `pulpen pink deket teknik` atau `print warna di USU Press`):**
   - Grid `grid-cols-1 lg:grid-cols-2 gap-5` (2 kolom lega agar isi toko + mini-list produk lega terbaca tanpa sesak).
   - **Lapis Atas (Header Gerai & Lokasi):** Foto gerai mini (`w-14 h-14 rounded-xl object-cover`) + **Nama Toko** + `★ Rating` + badge **`Buka`/`Tutup`** + teks jarak menonjol ke area tujuan (`~237 m dari Departemen Teknik Mesin • Mulai Rp 3.000`) + tombol ringkas **`Rute Peta`** & **`Lihat Toko`**.
   - **Lapis Bawah (Daftar Produk yang Dicari di Toko Ini):** Tampilkan hingga **3 item produk paling cocok** milik toko tersebut dalam baris rapi (`divide-y divide-slate-100`):
     - Thumbnail produk (`w-11 h-11 rounded-lg object-cover`),
     - **Nama produk dengan highlight `<mark>`** + kategori kecil,
     - **Harga tebal hijau** (`Rp 3.000/pcs`),
     - Tombol langsung **`Pesan →`** menuju halaman order toko tersebut.
   - Jika toko memiliki > 3 item cocok, tampilkan tautan ringkas `+ X item cocok lainnya di toko ini →` ke halaman detail/order toko.

### 2.3 Integrasi & Sinkronisasi Filter
- Pastikan tombol `Semua` / `Buka Saja` di bar status atas, checkbox `Buka Sekarang Saja`, dropdown `Urutkan`, input `Maks Rp`, tombol `×` (clear), dan klik chip hijau muda semuanya sinkron ke **1 state Alpine terpusat** (`fotocopyUnifiedSearch()` di dalam `<script>`) dan memperbarui URL `?q=...` secara halus (`history.replaceState`) agar refresh tetap mempertahankan mode & hasilnya.
- Jika user mengisi `Maks Rp` (mis. `5000`) saat sedang di mode `store`, otomatis pindah ke mode `product` (atau `mixed` bila ada lokasi) agar filter harga langsung terlihat dampaknya.

---

## FASE 3: Verifikasi Menyeluruh & Quality Gates Wajib

Jalankan seluruh verifikasi berikut dari folder `USUClassMap`:

1. **Automated Feature Test (`tests/Feature/AdaptiveFotocopySearchTest.php`):**
   - **Uji Mode `store`:** `GET /api/fotocopy/smart-search?q=deket+teknik`, `q=USU+Press`, `q=fotocopy+buka+sekarang`, dan `q=` → wajib mengembalikan `search_mode === 'store'` dengan daftar `stores` terurut sesuai lokasi/nama toko.
   - **Uji Mode `product`:** `GET /api/fotocopy/smart-search?q=pulpen+pink+murah+terdekat` dan `q=tipec` → wajib mengembalikan `search_mode === 'product'`, `items` terisi lengkap, dan `highlight_terms` valid.
   - **Uji Mode `mixed`:** `GET /api/fotocopy/smart-search?q=pulpen+pink+deket+teknik` dan `q=print+warna+di+USU+Press` → wajib mengembalikan `search_mode === 'mixed'` dan `mixed_groups` berisi toko relevan beserta `matched_items`-nya.

2. **Quality Gates:**
   - `php -l` pada seluruh file PHP yang diubah (`0` error).
   - `php artisan config:clear && php artisan view:cache && php artisan view:clear`.
   - `node --check` pada seluruh blok `<script>` hasil render `GET /fotocopy` (`0` syntax error).
   - `npm run build` (exit code `0`, `copilot.js` & `map.js` tetap ter-build).
   - `php artisan test` penuh (`100% PASS`, `0` failed).
   - **Pulihkan Apache Jena Fuseki:** `php artisan rdf:generate --upload` dan verifikasi **`5.025` triples** aktif di `http://localhost:3030/usuclassmap`.
   - **Verifikasi Playwright Edge Headless pada `http://127.0.0.1:8000/fotocopy`:**
     - `?q=deket+teknik`: tab **`Tempat Fotokopi`** aktif otomatis, **Store Card** dirender (bukan kartu `Fotokopi Hitam Putih A4`), dan tidak ada section daftar toko dobel di bawah.
     - `?q=pulpen+pink+murah+terdekat`: tab **`Produk & Jasa`** aktif otomatis, **Product-First Card** tampil dengan `<mark>` highlight dan badge `Termurah`/`Terdekat`.
     - `?q=pulpen+pink+deket+teknik` / `?q=print+warna+di+USU+Press`: tab **`Terpadu`** aktif otomatis dan **Store-Grouped Combo Card** tampil rapi.
     - `0` broken image dan `0` uncaught JS `pageerror` pada ketiga skenario.
   - `php artisan app:git-sync --export` & `php artisan app:git-sync --status` (wajib **`SINKRON`**).

Tampilkan ringkasan akhir perubahan 3 mode kartu (`store`, `product`, `mixed`), contoh output tiap kueri, dan status seluruh Quality Gates.