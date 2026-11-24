# Audit Sistem Menyeluruh & Verifikasi Otomatisasi WhatsApp — USUClassMap

**Tanggal Verifikasi Akhir:** 24 November 2026 (17:41 WIB)  
**Target Proyek:** `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`  
**Gateway WhatsApp Pengirim Laptop:** `081283575656` (`+62 812-8357-5656`)

---

## 1. Ringkasan Eksekutif

Seluruh modul aplikasi **USUClassMap** lintas **5 role** (User Publik, Mahasiswa, Admin Fotokopi, Kasir POS, Super Admin / Admin Akademik) beserta API REST, **Apache Jena Fuseki (SPARQL 1.1)**, dan **Gateway WhatsApp Pengirim Laptop `081283575656`** telah diaudit, diperbaiki, dan diverifikasi ulang pasca-perbaikan (*post-fix*):

- **Automated Test Suite (`php artisan test`):** **92 passed (630 assertions), 0 failed** (24.01 detik), teruji baik sebelum maupun sesudah `.env` mengaktifkan `WHATSAPP_DRIVER=waha` berkat isolasi `phpunit.xml`.
- **Verifikasi Browser End-to-End (Playwright + Microsoft Edge Headless):** Seluruh halaman utama pada 5 role mengembalikan **HTTP 200**, **0 uncaught JS errors (`page_errors: []`)**, **0 broken local images**, **0 kebocoran kode Alpine/JS**, dan seluruh tombol interaktif utama berfungsi normal.
- **Temuan Bonus Bug Kritis Produksi (`vite.config.js`):** Ditemukan bahwa `resources/js/copilot.js` dipanggil oleh `@vite(['resources/js/copilot.js'])` pada layout publik (`app.blade.php`, `welcome.blade.php`), tetapi belum terdaftar di `vite.config.js`, sehingga memicu **HTTP 500** (`Unable to locate file in Vite manifest`) saat Vite dev server (`public/hot`) mati. Sudah diperbaiki dan dibuild ulang (`73` modul).
- **Apache Jena Fuseki (`fuseki-usuclassmap`):** Aktif di `http://localhost:3030` (**HTTP 200**) dengan grafik RDF terverifikasi penuh sebesar **5.025 triples** (`SPARQL 1.1 AS (Apache Jena Fuseki)`).
- **WhatsApp Automation Gateway (`081283575656`):** Aktif di `http://localhost:3000` dengan driver Laravel `WHATSAPP_DRIVER=waha` dan pengiriman sinkron real-time `WHATSAPP_SYNC_DISPATCH=true`. Status sesi aktual adalah **`SCAN_QR_CODE`** (QR Code PNG live `5.616` bytes siap di-scan dari HP `081283575656`).
- **Kebersihan Database & Sinkronisasi Snapshot:** Total order di `database.sqlite` tetap **8 baris** (0 data dummy tersisa), dan `php artisan app:git-sync --status` berstatus **`SINKRON`**.

---

## 2. Matriks Audit Penuh Halaman, Rute, API & Tombol per Role

### 2.1 Role User Publik & Mahasiswa (`daffa@students.usu.ac.id`)

| Halaman / Rute | Akses | HTTP Status | Uji Tombol & Fitur Interaktif (Post-Fix) | Hasil Audit |
| :--- | :--- | :--- | :--- | :--- |
| `/` | Publik | `200` | Landing page, navbar, tabel bookmark, Rexa Copilot asset | Bersih (`0` broken img, `0` JS error) |
| `/map` | Publik | `200` | Klik `#map-perspective-toggle-btn`, `#map-satellite-toggle-btn`, `#map-reset-center-btn`, `#map-floating-photocopy-btn`, serta navigasi `/map` -> `/faq` | **`0` `appendChild` / `pageerror` saat unload.** *Catatan: saat belum login, fetch latar `/api/my-schedules` mengembalikan `401` sesuai proteksi auth.* |
| `/map` | Mahasiswa (Auth) | `200` | Muat peta, jadwal mahasiswa (`/api/my-schedules` 200), 77 gedung, 32 toko fotokopi | **100% bersih (`0` console error, `0` HTTP >= 400)** |
| `/faq` | Publik | `200` | Klik tombol accordion pertanyaan FAQ | Jawaban accordion terbuka normal (`faq_accordion_opened: true`) |
| `/profile` | Mahasiswa (Auth) | `200` | Halaman profil akun & foto | Bersih (`0` error) |
| `/fotocopy` | Mahasiswa (Auth) | `200` | Direktori 32 mitra toko fotokopi USU | Bersih (`0` broken img, `0` error) |
| `/fotocopy/32` | Mahasiswa (Auth) | `200` | Detail `Fotocopy Trial Admin` (`+62 857-6973-7410`) & verifikasi heading `Daftar Harga` | Heading `Katalog Layanan & Daftar Harga Produk ATK` tampil normal |
| `/fotocopy/32/order` | Mahasiswa (Auth) | `200` | 1) Klik layanan `digital_print` (`Pilih & Unggah PDF`) -> kartu kuota `Terisi 0/1 File` & tombol checkout terkunci (`Lengkapi PDF Dahulu` disabled)<br>2) Klik layanan `hybrid_finishing` (`Pilih Opsi`) -> modal 2 opsi (`Opsi A` Offline / `Opsi B` Bundel Print+PDF) terbuka & ditutup via `Batal` | Kedua alur interaktif **PASS**; `node --check` 2 inline script = **0 error** |
| `/fotocopy/orders/ORD-20261124-VLEM/payment` | Mahasiswa (Auth) | `200` | Verifikasi status Lunas, tombol & href bertanda tangan (*signed URL*) `Download Bukti Invoice PDF` | `node --check` 1 inline script = **0 error**; request ke *signed invoice URL* mengembalikan **HTTP 200** + byte valid **`%PDF-`** |

### 2.2 Role Admin Fotokopi (`adminfotokopi@usu.ac.id`)

| Halaman / Rute | HTTP Status | Uji Tombol & Fitur Interaktif (Post-Fix) | Hasil Audit |
| :--- | :--- | :--- | :--- |
| `/admin-fotokopi/dashboard` | `200` | Ringkasan metrik mitra toko & layanan | Bersih (`0` error) |
| `/admin-fotokopi/stores` | `200` | Verifikasi banner WAHA Gateway menampilkan `+62 812-8357-5656`, status `WAHA` (bukan `sandbox`), tombol `Buka Dashboard WAHA (Scan QR)`, format nomor `+62` pada tabel, serta klik `Simulasi Chat WA (Sandbox Viewer)` -> modal terbuka | **100% PASS**; `node --check` 1 inline script = **0 error**; **0 kebocoran `x-data`** |
| `/admin-fotokopi/stores/33/edit` | `200` | Form edit mitra `Fotocopy Trial Admin` & proteksi *self-exclusion* sinkronisasi lokasi by `slug`/`name` + badge `77` gedung & `31` fotokopi lain | Bersih (`0` error) |
| `/admin-fotokopi/services` | `200` | Manajemen katalog produk/layanan & uji buat layanan tanpa `item_type` eksplisit (tercover PHPUnit `92/92`) | Bersih (`0` error) |
| `/admin-fotokopi/reports` | `200` | Laporan pesanan dengan format `+62` & badge status dispatch WA (`WA: ...`) | Bersih (`0` error) |

### 2.3 Role Kasir POS (`cashierfotokopi@usu.ac.id`, pemilik Toko #5 `Berkah Abadi Copy & ATK Perpustakaan`)

| Halaman / Rute | HTTP Status | Uji Tombol & Fitur Interaktif (Post-Fix) | Hasil Audit |
| :--- | :--- | :--- | :--- |
| `/cashier-fotokopi/orders` | `200` | Antrean pesanan toko kasir & tombol aksi cetak/status | Bersih (`0` error); `node --check` 1 inline script = **0 error** |
| `/cashier-fotokopi/pos` | `200` | Klik 1 item katalog (`Rp ...`) -> masuk keranjang & total tagihan terupdate -> klik tombol hapus `✕` (tanpa submit agar DB bersih) | **100% PASS**; `node --check` 3 inline scripts = **0 error** |
| `/cashier-fotokopi/orders/3/print` | `200` | Tiket cetak kasir untuk order Toko #5 lengkap dengan nomor WA terformat `+62` | Bersih (`0` error). *Catatan keamanan: akses kasir Toko #5 ke `/cashier-fotokopi/orders/9/print` (milik Toko #33) ditolak `403 Forbidden` sesuai isolasi tenant.* |

### 2.4 Role Super Admin / Admin Akademik (`superadmin@usu.ac.id`)

| Halaman / Rute | HTTP Status | Uji Tombol & Fitur Interaktif (Post-Fix) | Hasil Audit |
| :--- | :--- | :--- | :--- |
| `/` (Navbar Dropdown) | `200` | Klik profil dropdown -> verifikasi seluruh link lintas role (`Admin Dashboard (Akademik)`, `Panel Admin Fotokopi`, `Panel Kasir & POS`, `Pengaturan Profil`) | **Semua 4 link role tampil & terhubung normal** |
| `/admin/dashboard` | `200` | Dashboard metrik akademik | Bersih (`0` error) |
| `/admin/buildings` | `200` | Daftar gedung (77 gedung) | Bersih (`0` error) |
| `/admin/buildings/create` | `200` | Form tambah gedung + badge awal terbarui (`77`, `32`) | Bersih (`0` error) |
| `/admin/rooms` | `200` | Daftar ruangan (133 ruangan) | Bersih (`0` error) |
| `/admin/schedules` | `200` | Manajemen jadwal kuliah (318 jadwal) | Bersih (`0` error) |
| `/admin/lecturers` | `200` | Manajemen dosen (132 dosen) | Bersih (`0` error) |
| `/admin/courses` | `200` | Manajemen mata kuliah (142 mata kuliah) | Bersih (`0` error) |
| `/admin/import` | `200` | Halaman impor CSV/Excel jadwal | Bersih (`0` error) |
| `/admin-sementara-/map` | `200` | Peta interaktif admin (Mapbox GL) | Bersih (`0` error) |
| `/admin/cek-foto` | `200` | Inspektor foto 360° | Bersih (`0` error) |

### 2.5 API REST & Apache Jena Fuseki (SPARQL 1.1)

| Endpoint | HTTP Status | Hasil Live Terukur |
| :--- | :--- | :--- |
| `GET http://127.0.0.1:8000/api/buildings` | `200` | `77` gedung |
| `GET http://127.0.0.1:8000/api/copy-shops?per_page=50` | `200` | `32` mitra fotokopi |
| `GET http://127.0.0.1:8000/api/copy-shops/services` | `200` | `112` item layanan sampel/API |
| `GET http://127.0.0.1:8000/api/sparql/regex-search?keyword=literacy` | `200` | `25` hasil pencarian jadwal |
| `GET http://127.0.0.1:8000/api/sparql/building-stats` | `200` | `source: "SPARQL 1.1 AS (Apache Jena Fuseki)"` |
| `GET http://localhost:3030/$/ping` | `200` | Server Fuseki aktif normal |
| SPARQL `SELECT (COUNT(*) AS ?c) WHERE { ?s ?p ?o }` | `200` | **`5.025` triples** aktif di dataset `/usuclassmap` |

---

## 3. Daftar Bug yang Ditemukan & Perbaikan yang Dilakukan

| No | Area & File | Akar Masalah | Perbaikan | Status Verifikasi |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Validasi layanan Admin Fotokopi (`app/Http/Controllers/AdminFotokopi/ServiceController.php`) | `item_type` wajib diisi (`required`), menyebabkan kegagalan test saat form/request mengandalkan default kategori | Mengubah validasi ke `nullable|in:jasa,barang` dengan penentuan default otomatis (`ATK` -> `barang`, lainnya -> `jasa`) | Lulus PHPUnit (`FotocopyRoleAndPanelTest`) |
| 2 | Heading katalog toko (`resources/views/features/fotocopy/public/show.blade.php`) | Teks heading belum memuat frasa `Daftar Harga` sesuai kontrak fitur & test | Memperbarui heading menjadi `Katalog Layanan & Daftar Harga Produk ATK` | Lulus PHPUnit & Playwright `/fotocopy/32` |
| 3 | Console error `appendChild` saat meninggalkan `/map` (`resources/js/features/map.js`) | Async fetch (`/api/buildings`, `/api/my-schedules`, `/api/copy-shops`) masih mencoba merender marker setelah `beforeunload` menghancurkan instance Mapbox | Menambahkan flag `window.__usuMapDestroyed` dan guard `map.getCanvasContainer()` pada `beforeunload`, `initMap`, `fetchBuildings`, `fetchUserSchedules`, `renderBuildingMarkers`, dan `renderPhotocopyMarkers` | Terverifikasi Playwright `/map` -> `/faq`: `0` error `appendChild` |
| 4 | **Vite manifest missing entry (`vite.config.js`)** | `resources/js/copilot.js` direferensikan oleh `@vite(['resources/js/copilot.js'])` di layout publik, namun tidak ada di `vite.config.js` `input`, memicu **HTTP 500** saat `public/hot` tidak aktif | Menambahkan `'resources/js/copilot.js'` ke `vite.config.js` dan menjalankan `npm run build` (`73` modul termasuk `copilot-5aOOmijA.js`) | Terverifikasi `200` di seluruh halaman publik tanpa Vite dev server |
| 5 | Kebocoran/inline `x-data` & status WA Gateway (`resources/views/features/fotocopy/admin/stores/index.blade.php`) | Blok `x-data` multi-baris masih inline; banner gateway belum menampilkan nomor bot laptop & status sesi WAHA secara informatif | Memindahkan state Alpine ke `<script>` (`window.usuAdminStoresPage`), memformat no WA tabel lewat `WhatsAppPhoneNormalizer::formatDisplay()`, serta memperbarui banner WAHA + tombol `Buka Dashboard WAHA (Scan QR)` | Terverifikasi `node --check`, PHPUnit, dan Playwright |
| 6 | Self-exclusion peta & edit toko (`resources/views/features/fotocopy/admin/stores/edit.blade.php`) | Pencocokan pengecualian diri sendiri memakai `place.id !== store.id` padahal ID `photocopy_places` (`32`) dan `fotocopy_stores` (`33`) berbeda | Mencocokkan berdasarkan `slug` / `name` (`currentStoreSlug`) serta memperbarui badge awal ke `77` gedung & `31` fotokopi lain | Terverifikasi di `/admin-fotokopi/stores/33/edit` |
| 7 | Konsistensi badge awal peta (`stores/create.blade.php`, `buildings/{create,edit}.blade.php`, `map/index.blade.php`) | Angka awal hardcoded masih tertulis `31` / `48` sebelum API selesai dimuat | Diperbarui konsisten ke `32` fotokopi & `77`/`76` gedung | Terverifikasi di Playwright |
| 8 | Navigasi role di Navbar (`resources/views/components/navbar/public.blade.php`) | Dropdown profil & menu mobile belum membedakan tautan panel untuk Admin Akademik, Admin Fotokopi, dan Kasir POS | Menambahkan tautan role-aware (`Admin Dashboard (Akademik)`, `Panel Admin Fotokopi`, `Panel Kasir & POS`, `Pengaturan Profil`) di dropdown desktop & menu mobile | Terverifikasi pada akun `superadmin@usu.ac.id` di Playwright |
| 9 | Format nomor WA & status dispatch di struk/laporan (`cashier/orders/print.blade.php`, `admin/reports/index.blade.php`) | Nomor telepon toko/pelanggan masih raw digit (`628...`) dan status pengiriman WA belum terlihat sekilas di laporan | Menerapkan `WhatsAppPhoneNormalizer::formatDisplay()` serta menambahkan badge `WA: ...` di `reports/index.blade.php` | Terverifikasi HTTP 200 di Playwright |
| 10 | Pengiriman WA real-time lokal tanpa antrean macet (`OrderPaymentFulfillmentService.php`, `CashierFotokopi/OrderController.php`, `config/whatsapp.php`) | `.env` memakai `QUEUE_CONNECTION=database` sehingga tanpa worker daemon `queue:work`, job WhatsApp menumpuk belum terkirim | Menambahkan `WHATSAPP_SYNC_DISPATCH=true` (`dispatchSync` saat true) sehingga pesan ringkasan, Multi-PDF, dan Invoice langsung dieksekusi saat `paid` / `Selesai` / `resend-wa` | Terverifikasi oleh 2 unit/feature test baru & flow controller |
| 11 | Konfigurasi pengirim `081283575656` & diagnosa WAHA (`config/whatsapp.php`, `WahaWhatsAppDriver.php`, `WhatsAppManager.php`, `StoreController.php`, `phpunit.xml`) | Belum ada nomor pengirim terpusat (`sender_phone`), pengecekan status sesi `/api/sessions/{session}` secara terstruktur, maupun isolasi `WHATSAPP_DRIVER=sandbox` saat PHPUnit | Menambahkan `sender_phone`, `sessionInfo()`, `statusSummary()`, pesan diagnosa berbahasa Indonesia yang jelas, info `No. Pengirim` di ping `testWhatsApp`, dan `<env name="WHATSAPP_DRIVER" value="sandbox"/>` di `phpunit.xml` | Lulus PHPUnit `92/92` walaupun `.env` aktif `WHATSAPP_DRIVER=waha` |

> **Catatan Operasional Terkait `php artisan test` & Fuseki:** Saat `php artisan test` dijalankan di lingkungan lokal yang menyalakan Fuseki port `3030`, test seperti `ScheduleImportTest` / `ApiTest` tetap memanggil endpoint Fuseki lokal dan mengganti graph default menjadi fixture minimal (`26` triples). Oleh karena itu, setelah menjalankan `php artisan test`, jalankan `php artisan rdf:generate --upload` untuk memulihkan **5.025 triples** (telah dijalankan dan diverifikasi di akhir audit ini).

---

## 4. Status Lengkap Otomatisasi WhatsApp Pengirim Laptop `081283575656`

### 4.1 Arsitektur Gateway `:3000` (Path A vs Path B — Laporan Transparan)

1. **Percobaan Path A (`devlikeapro/waha:noweb` Docker):** Pull image Docker `722,3 MB` compressed berulang kali mengalami `unexpected EOF` dan pipe error HTTP 500 dari `dockerDesktopLinuxEngine` akibat keterbatasan RAM host laptop 8 GB (~700 MB RAM fisik bebas sebelum WSL2 menyala).
2. **Implementasi Path B (Aktif di Port `3000`):** Di-deploy **Native WAHA-Compatible Gateway ringan** berbasis **Node.js `v24.22.1` + `@whiskeysockets/baileys@6.7.24`** di direktori [whatsapp-gateway/](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/whatsapp-gateway/server.mjs) yang hanya memakai **~45 MB RAM** dan menerapkan **kontrak REST API WAHA 100% kompatibel** dengan `WahaWhatsAppDriver.php`:
   - `GET /dashboard` (**HTTP 200** HTML Dashboard QR lokal)
   - `GET /api/sessions/default` (terproteksi `X-Api-Key`)
   - `POST /api/sessions/start` (terproteksi `X-Api-Key`)
   - `GET /api/default/auth/qr` (mengembalikan `image/png` QR live **5.616 bytes** saat `SCAN_QR_CODE`, terproteksi `X-Api-Key`)
   - `POST /api/sendText` & `POST /api/sendFile` (mendukung dokumen PDF base64 hingga 50 MB, terproteksi `X-Api-Key`)
   - Uji penolakan tanpa API Key mengembalikan **HTTP `401 Unauthorized`**.

### 4.2 Parameter Koneksi & Status Aktual

| Parameter | Nilai Aktif |
| :--- | :--- |
| **Nomor Bot Pengirim Laptop** | `6281283575656` (`+62 812-8357-5656`) |
| **Driver Aktif di `.env`** | `WHATSAPP_DRIVER=waha` |
| **Mode Eksekusi Lokal** | `WHATSAPP_SYNC_DISPATCH=true` (langsung kirim real-time tanpa butuh worker antrean terpisah) |
| **Fallback Keamanan** | `WHATSAPP_FALLBACK_ON_FAILURE=true` (Layer 2 `wa.me` + Download ZIP + log tetap aktif tanpa HTTP 500 bila QR belum di-scan) |
| **Endpoint Gateway** | `http://localhost:3000` (`BAILEYS-NOWEB-NATIVE`) |
| **Dashboard Scan QR** | `http://localhost:3000/dashboard` (tanpa login untuk akses dashboard localhost) |
| **API Key (`/api/*`)** | `usuclassmap-waha-2026` |
| **Skrip Launcher 1-Klik** | [start-wa-gateway.ps1](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/scripts/start-wa-gateway.ps1) |
| **Status Sesi Saat Ini** | **`SCAN_QR_CODE`** (`me: null`, QR Code PNG aktif **5.616 bytes** — *handshake* Multi-Device ke server WhatsApp berhasil) |

### 4.3 Panduan 3 Langkah Aktivasi QR Pairing (HP `081283575656`)

1. Pastikan gateway `:3000` aktif (saat ini sudah menyala; jika laptop baru di-restart, jalankan `powershell -ExecutionPolicy Bypass -File scripts/start-wa-gateway.ps1`) lalu buka **`http://localhost:3000/dashboard`** di browser.
2. Buka **WhatsApp** di HP pengirim **`081283575656`** -> ketuk menu titik tiga / Pengaturan -> **Perangkat Tertaut (Linked Devices)** -> **Tautkan Perangkat (Link a Device)** -> **Scan QR Code** yang tampil di halaman dashboard.
3. Tunggu hingga badge di dashboard & di halaman `/admin-fotokopi/stores` berubah menjadi **`WORKING`**. Sejak saat itu, setiap pesanan yang berstatus **`paid`** atau **`Selesai`** otomatis mengirimkan ringkasan order, seluruh file PDF + Note masing-masing PDF, dan file Invoice PDF (`INV-...pdf`) melalui WhatsApp nomor `081283575656` secara langsung. *(Jika belum sempat men-scan QR, Layer 2 tombol `Kirim ke WhatsApp Toko` + paket `.zip` tetap berjalan normal tanpa error HTTP 500).*

---

## 5. Hasil Verifikasi Teknis Keseluruhan

| Pemeriksaan | Perintah / Alat | Hasil Terukur |
| :--- | :--- | :--- |
| Sintaks PHP (8 file diubah) | `php -l` | **8/8 `No syntax errors detected`** |
| Kompilasi Blade View | `php artisan config:clear && php artisan view:cache && php artisan view:clear` | **Exit 0 (sukses tanpa error)** |
| Build Frontend Vite | `npm run build` | **Exit 0 (`73` modul termasuk `copilot.js`, `11.34s`)** |
| Validasi JS `<script>` Inline | `node --check` pada HTML terender 5 halaman kunci | **8 `<script>` diperiksa -> `0` syntax errors** |
| Suite Uji Otomatis Laravel | `php artisan test` (sesudah `.env` pakai `WHATSAPP_DRIVER=waha`) | **`92 passed (630 assertions), 0 failed` (`24.01s`)** |
| Audit UI & Interaksi 5 Role | Playwright (`msedge` headless) + `verify_post_fix.py` | Seluruh role (`public_map_faq`, `student`, `admin_fotokopi`, `kasir_pos`, `superadmin`) & `:3000/dashboard` **PASS**, `0` `page_errors`, `0` broken local images, `0` leaks, seluruh 10 cek interaktif **PASS** |
| Sinkronisasi Git Snapshot | `php artisan app:git-sync --export` & `--status` | **`SINKRON`** (Hash `619cc5e3...`, 77 gedung, 133 ruang, 318 jadwal, 32 toko, 2.689 layanan) |
| Kesehatan Service & Data | Live check `:8000`, `:3030`, `:3000`, SQLite | Laravel `:8000` (`200`), Fuseki `:3030` (`200`, **`5.025` triples**), WA Gateway `:3000` (`200`, `SCAN_QR_CODE`), `fotocopy_orders` = **`8` baris** (bersih tanpa dummy order) |
