# DATA EMPIRIS HASIL DAN PEMBAHASAN JURNAL — USUClassMap

Dokumen ini berisi data pengukuran empiris nyata, verifikasi fakta arsitektur kode (*code-level audit*), rumusan **Batasan Studi (Ancaman Validitas)**, dan rekap aset visual beresolusi HD (1920x1080) untuk penyusunan Bab **Hasil dan Pembahasan** jurnal ilmiah proyek **USUClassMap** (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`).

Seluruh angka di bawah diukur langsung pada lingkungan lokal (`Windows 11`, PHP `8.4.19` CLI/`php artisan serve` di `http://127.0.0.1:8000`, basis data relasional `SQLite`, serta kontainer Docker `fuseki-usuclassmap` / `stain/jena-fuseki` pada `http://localhost:3030/usuclassmap`) tanggal `24 November 2026`.

---

## 1. Spesifikasi Dataset & Endpoint yang Diuji

- **Entitas Relasional Terdata (`database/database.sqlite`):**
  - `buildings`: **77** gedung kampus USU
  - `rooms`: **133** ruangan kuliah
  - `courses`: **142** mata kuliah (`MKWU`: 133, `Prodi`: 5, `MKWK`: 4)
  - `lecturers`: **132** dosen pengampu
  - `schedules`: **318** entri jadwal kuliah aktif
  - Modul Layanan Fotokopi & POS: **32** mitra toko (`fotocopy_stores`), **2.689** item katalog (`fotocopy_services`), **6** transaksi order awal (`fotocopy_orders` tetap tidak berubah sebelum dan sesudah pengujian / *non-destructive*).
- **Graf Pengetahuan Semantik (`http://localhost:3030/usuclassmap`):**
  - Ukuran file RDF/XML (`storage/app/rdf/schedules.rdf`): **341.695 bytes** (`333,69 KB` / `0,3259 MB`)
  - Jumlah total RDF triple terindeks di Apache Jena Fuseki: **5.025 triple**
- **Endpoint API Peta Interaktif yang Diuji:**
  - Dipanggil dari modul frontend [map.js](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/resources/js/features/map.js#L1079) -> rute [api.php](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/routes/api.php#L15) -> `ScheduleApiController::search()` -> `SparqlService::searchSchedules()` -> `SparqlService::queryFusekiOrFallback()`:
  - **Kueri Uji Utama:** `GET http://127.0.0.1:8000/api/schedules/search?q=Literacy` (`count = 91` jadwal cocok)
  - **Kueri Uji Sekunder (Filter Hari):** `GET http://127.0.0.1:8000/api/schedules/search?q=Literacy&day=Senin` (`count = 58` jadwal cocok)

---

## 2. Pengujian Waktu Eksekusi Kueri (ms): Fuseki SPARQL Aktif vs Resilient Auto-Fallback Relasional

Setiap konfigurasi diuji sebanyak **$n = 10$ iterasi sekuensial** (1 *cold request* + 9 *warm requests*) menggunakan koneksi HTTP terisolasi (`CURLOPT_FRESH_CONNECT`, `CURLOPT_FORBID_REUSE`) dengan timer resolusi tinggi `hrtime(true)`.

### Tabel 1. Perbandingan Latensi Kueri Semantik (Fuseki Aktif) vs Resilient Auto-Fallback & SQLite Relasional Murni

| Skenario Pengujian | Endpoint / Jalur Eksekusi | Sumber Data (`source`) | Hasil (`count`) | Cold (ms) | Min (ms) | Max (ms) | Rata-rata 10 Req (ms) | Warm Mean #2–#10 (ms) | Median (ms) |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **1A. Fuseki Aktif (End-to-End API)** | `GET /api/schedules/search?q=Literacy` | `SPARQL (Apache Jena Fuseki)` | 91 (5.025 triple) | 1.336,06 | 394,49 | 1.336,06 | **544,29** | 456,32 | 439,59 |
| **1B. Fuseki Aktif (+ Filter Hari)** | `GET /api/schedules/search?q=Literacy&day=Senin` | `SPARQL (Apache Jena Fuseki)` | 58 (5.025 triple) | 436,03 | 401,37 | 445,82 | **426,16** | 425,06 | 430,12 |
| **1C. Engine SPARQL Fuseki Langsung** | `POST localhost:3030/usuclassmap/query` (kueri `Literacy`) | Fuseki Engine | 91 bindings | 55,64 | 37,21 | 61,26 | **48,32** | 47,50 | 50,12 |
| **Baseline Boot Laravel HTTP** | `GET /up` (`php artisan serve`, 5 req) | Laravel Healthcheck | - | 626,20 | 325,23 | 626,20 | **480,12** | - | - |
| **2A. Fuseki Mati — End-to-End Fallback** | `GET /api/schedules/search?q=Literacy` | `MySQL (Fallback)` (SQLite) | 91 | 3.348,59 | 2.741,25 | 3.348,59 | **2.847,96** | 2.792,33 | 2.787,78 |
| **2B. Fuseki Mati — Fallback (+ Filter Hari)** | `GET /api/schedules/search?q=Literacy&day=Senin` | `MySQL (Fallback)` (SQLite) | 58 | 2.681,96 | 2.681,96 | 2.788,64 | **2.725,05** | 2.729,83 | 2.720,59 |
| **2C. Fallback Eloquent Murni (tanpa penalti port 3030)** | `SparqlService::fallbackToMysqlWithTokens(['literacy'])` | `MySQL (Fallback)` (SQLite) | 91 | 62,90 | 44,57 | 62,90 | **48,96** | 47,41 | 46,43 |
| **2D. SQL SQLite Murni (Query Builder/PDO)** | SQL via `DB::getQueryLog()` (query `Literacy`) | SQLite Engine | 91 baris | 2,00 | 1,04 | 2,00 | **1,34** | 1,27 | 1,28 |
| **2E. Fallback Eloquent Murni (+ Filter Hari)** | `fallbackToMysqlWithTokens(['literacy'], 'Senin')` | `MySQL (Fallback)` (SQLite) | 58 | 80,83 | 29,99 | 80,83 | **40,94** | 36,50 | 33,01 |
| **2F. SQL SQLite Murni (+ Filter Hari)** | SQL via `DB::getQueryLog()` (`Literacy` + `Senin`) | SQLite Engine | 58 baris | 4,37 | 1,00 | 4,37 | **1,52** | 1,21 | 1,15 |

### Rincian Sampel Tiap Iterasi (ms)
- **Skenario 1A (`/api/schedules/search?q=Literacy`):** `[1336.06, 601.86, 525.42, 446.51, 415.81, 432.67, 394.49, 414.84, 449.94, 425.31]`
- **Skenario 1B (`/api/schedules/search?q=Literacy&day=Senin`):** `[436.03, 441.85, 401.37, 445.82, 436.99, 406.54, 419.36, 440.78, 408.60, 424.22]`
- **Skenario 1C (Direct Fuseki `POST /usuclassmap/query`):** `[55.64, 37.25, 39.71, 61.26, 45.48, 37.21, 54.76, 55.66, 37.34, 58.86]`
- **Skenario 2A (Fallback End-to-End `?q=Literacy`):** `[3348.59, 2806.26, 2744.54, 2741.25, 2742.82, 2860.12, 2769.31, 2745.60, 2807.62, 2913.46]`
- **Skenario 2B (Fallback End-to-End `?q=Literacy&day=Senin`):** `[2681.96, 2706.67, 2706.17, 2687.39, 2741.84, 2762.71, 2692.14, 2734.50, 2748.41, 2788.64]`
- **Skenario 2C (Eloquent Service Murni `?q=Literacy`):** `[62.90, 45.50, 45.90, 46.57, 44.57, 53.15, 46.29, 45.18, 50.22, 49.31]`
- **Skenario 2D (Raw SQL SQLite `?q=Literacy`):** `[2.00, 1.28, 1.04, 1.72, 1.29, 1.17, 1.27, 1.05, 1.48, 1.10]`

### Temuan Kritis untuk Pembahasan Jurnal
1. **Efisiensi Engine SPARQL vs Overhead Web Request:** Engine Apache Jena Fuseki hanya memerlukan rata-rata **48,32 ms** (warm **47,50 ms**) untuk mengeksekusi kueri `SELECT` pola graf bersarang (`OPTIONAL` + `FILTER CONTAINS/LCASE`) pada `5.025` triple. Selisih hingga **426,16–544,29 ms** pada endpoint publik terutama berasal dari *bootstrap* proses tunggal PHP development server (`GET /up` = **480,12 ms**) serta middleware `AutoSyncGitSnapshotMiddleware`.
2. **Keberhasilan Resilient Auto-Fallback & Sumber Penalti Latensi Fallback:** Saat kontainer `fuseki-usuclassmap` dimatikan, seluruh 20 permintaan HTTP tetap mengembalikan **HTTP 200 OK** dengan hasil persis sama (**91** dan **58** jadwal) dari `MySQL (Fallback)` (SQLite). Total waktu *end-to-end* fallback rata-rata **2.847,96 ms** (utama) / **2.725,05 ms** (+ hari) bukan disebabkan lambatnya SQLite — melainkan penalti kegagalan koneksi TCP *loopback dual-stack* IPv6 `::1` & IPv4 `127.0.0.1` ke port `3030` pada Windows (~**2,2–2,3 detik** sebelum exception `ConnectionException` ditangkap oleh `queryFusekiOrFallback()`).
3. **Kinerja Kueri Relasional SQLite Murni:** Tanpa penalti deteksi port `3030` mati, metode `fallbackToMysqlWithTokens()` menyelesaikan kueri Eloquent + *hydration* + format JSON dalam **48,96 ms** (dengan eksekusi SQL SQLite murni hanya **1,34 ms** untuk 91 jadwal, dan **1,52 ms** untuk 58 jadwal).

---

## 3. Pengukuran Empiris `RdfGeneratorService::generate()` & Audit Arsitektur Dual-Persistence

### A. Hasil Pengukuran Regenerasi & Sinkronisasi RDF
Pengukuran dijalankan via skrip terisolasi pada dataset produksi lokal (`77` gedung, `133` ruang, `142` mata kuliah, `132` dosen, `318` jadwal):

- **Durasi `RdfGeneratorService::generate()`:** **357,00 ms** (`0,36 detik`)
- **Durasi `RdfGeneratorService::uploadToFuseki()` (`PUT /usuclassmap/data?default`):** **747,36 ms** (`0,75 detik`)
- **Total In-Process (`generate` + `uploadToFuseki`):** **1.104,36 ms** (`1,10 detik`)
- **Durasi Eksekusi CLI Penuh (`php artisan rdf:generate --upload`):** **6.792,68 ms** (`6,79 detik`, mencakup *bootstrap* CLI Laravel)
- **Ukuran Output `storage/app/rdf/schedules.rdf`:** **341.695 bytes** (`333,69 KB` / `0,3259 MB`), menghasilkan **5.025 triple RDF**
- **Konsumsi Memori (`memory_get_peak_usage`):**
  - *Baseline* sebelum `generate()`: `20,67 MB` (allocated) | Peak `20,72 MB` (`22,00 MB` real OS)
  - Sesudah `generate()`: current `28,55 MB` (`+7,88 MB` graf di RAM) | **Peak Memory PHP = `30,52 MB`** (**`32,00 MB` real OS allocation**), delta puncak bersih **`+9,80 MB`**.
- **Catatan Kompatibilitas Runtime PHP 8.4 (Temuan Empiris):** Operasi serialisasi `$graph->serialise('rdfxml')` di `RdfGeneratorService::generate()` berjalan sukses di PHP 8.4.19, namun pemanggilan ulang parser `$graph->parseFile(..., 'rdfxml')` pada `easyrdf/easyrdf` 1.x melempar `ValueError` di `vendor/easyrdf/easyrdf/lib/Parser/RdfXml.php:90` (`xml_set_element_handler()` terkait perubahan deprecation `xml_set_object()` di PHP 8.4).

### B. Verifikasi Klaim Rancangan Batasan Studi
> **Klaim Awal:** *"Sistem regenerasi file RDF via EasyRdf masih mengonsumsi memori yang cukup tinggi saat ada pembaruan masal, dan Fuseki belum bisa menangani sistem transaksi kasir secepat database SQL."*

- **Evaluasi Bagian 1 (EasyRdf & Memori — Perlu Diperhalus agar Akurat secara Ilmiah):**
  Pada skala data pengujian saat ini (`318` jadwal -> `5.025` triple), penggunaan memori puncak EasyRdf tergolong ringan (`30,52 MB` PHP peak; delta `+9,80 MB`; waktu serialisasi `357,00 ms`). Oleh karena itu, klaim konsumsi memori tinggi **tidak terjadi pada skala saat ini**, namun **terbukti secara struktural untuk skenario penskalaan masal**: kode `RdfGeneratorService::generate()` menerapkan mekanisme *full-graph in-memory rebuild* (`$graph = new Graph()`) yang memuat ulang seluruh tabel `Building`, `Room`, dan `Schedule` (`O(N)` di RAM), menulis ulang penuh file `schedules.rdf`, lalu menimpa seluruh graf Fuseki via HTTP `PUT ?default` setiap kali `ScheduleObserver` atau `GitSnapshotObserver` terpanggil.
- **Evaluasi Bagian 2 (Fuseki vs SQL untuk Transaksi Kasir — Akurat & Terbukti di Kode):**
  Arsitektur aplikasi memang secara eksplisit memisahkan tanggung jawab (*Dual-Persistence*):
  1. **Graf Pengetahuan Akademik (`schedules.rdf`, `5.025` triple)** disimpan di **Apache Jena Fuseki** untuk pencarian berbasis graf & fitur SPARQL 1.1 (`FILTER REGEX`, `ASK`, `COUNT/BIND AS`).
  2. **Modul Transaksional Kasir POS & Checkout Multi-PDF** ([PhotocopyController::storeOrder()](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/app/Http/Controllers/PhotocopyController.php#L386) menggunakan `DB::transaction()`, [CashierFotokopi\OrderController::storePos()](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/app/Http/Controllers/CashierFotokopi/OrderController.php#L217), serta tabel `fotocopy_orders`, `fotocopy_order_items`, `fotocopy_order_files`, `whatsapp_dispatch_logs`) sepenuhnya dipertahankan di **SQLite** dan sengaja dikecualikan dari `GitSnapshotSyncService::TABLES` maupun serialisasi RDF. Secara empiris, SQLite mengeksekusi kueri hanya dalam **1,34–1,52 ms** dengan dukungan isolasi transaksi **ACID** penuh, jauh lebih cepat dan lebih aman dibanding eksekusi graf HTTP SPARQL (`48,32 ms`) atau pola pembaruan graf penuh Fuseki (`747,36 ms`).

---

## 4. Rumusan Final "Batasan Studi / Ancaman Validitas" (Siap Kutip)

1. **Mekanisme Regenerasi RDF Penuh (*Full-Graph Rebuild*) pada EasyRdf (Ancaman Skalabilitas Memori & Kompatibilitas Parser PHP 8.4):**
   Meskipun pada skala `5.025` triple (`318` jadwal) proses `RdfGeneratorService::generate()` hanya memerlukan `357,00 ms` dengan puncak memori `30,52 MB` (delta `+9,80 MB`), pembangunan graf menggunakan objek `EasyRdf\Graph` di memori untuk seluruh entitas (`Building`, `Room`, `Schedule`) serta penggantian graf penuh di Fuseki melalui `HTTP PUT /data?default` (`747,36 ms`). Untuk pembaruan masal puluhan ribu jadwal secara serentak, pendekatan ini berpotensi meningkatkan beban memori dan latensi I/O secara linier dibanding pembaruan inkremental berbasis *SPARQL 1.1 Update* (`INSERT DATA`/`DELETE DATA`). Selain itu, ketergantungan pada pustaka `easyrdf/easyrdf` 1.x menunjukkan keterbatasan kompatibilitas pada parser RDF/XML-nya saat dijalankan di atas runtime PHP 8.4 (`xml_set_object` deprecation).
2. **Pemisahan Persistensi Ganda (*Dual-Persistence*) untuk Transaksi Kasir POS & Non-ACID Triplestore:**
   Apache Jena Fuseki difokuskan khusus untuk graf pengetahuan jadwal akademik (`schedules.rdf`), sedangkan transaksi kasir POS *walk-in*, unggah berkas Multi-PDF, dan status pembayaran (`fotocopy_orders`, `fotocopy_order_files`, `whatsapp_dispatch_logs`) sepenuhnya bergantung pada basis data relasional SQLite. Pemisahan ini diperlukan karena kueri relasional murni SQLite berjalan dalam `1,34 ms` (dibanding `48,32 ms` SPARQL HTTP di Fuseki) serta menyediakan jaminan transaksi atomik **ACID** (`DB::transaction`) dan *foreign-key constraint* yang belum sepadan jika ditangani langsung melalui protokol HTTP Fuseki.
3. **Penalti Deteksi Kegagalan (*Failure Detection Penalty*) Saat Server Triplestore Mati di Lingkungan Pengembangan:**
   Mekanisme *Resilient Auto-Fallback* menjamin ketersediaan layanan tetap 100% (`HTTP 200 OK` dari SQLite saat Fuseki mati), namun pengujian menunjukkan waktu respons *end-to-end* naik menjadi rata-rata `2.847,96 ms` (`Cold: 3.348,59 ms`) dibanding waktu eksekusi Eloquent fallback murni (`48,96 ms`). Hal ini disebabkan oleh upaya jabat tangan TCP *connection-refused* berulang pada *loopback dual-stack* (`localhost` IPv6 `::1` dan IPv4 `127.0.0.1`) serta *single-threaded bootstrap* `php artisan serve` (`baseline /up = 480,12 ms`), bukan dari kompleksitas kueri relasionalnya. Penerapan *circuit breaker* jangka pendek atau pengalamatan eksplisit `127.0.0.1` dapat mengurangi latensi fallback pada produksi.
4. **Ketergantungan Layanan & Aset Eksternal pada Rendering Peta 3D WebGL dan Gateway Pengiriman:**
   Visualisasi peta interaktif bergantung pada konektivitas jaringan eksternal untuk mengambil *vector tiles* `mapbox://styles/mapbox/streets-v12`, raster satelit Google (`mt*.google.com`), pustaka CDN Pannellum 360°, serta dukungan akselerasi grafis **WebGL** pada peramban klien. Selain itu, seluruh pengukuran kueri dilakukan di lingkungan mesin tunggal (*localhost* Docker Fuseki + SQLite) dan notifikasi WhatsApp otomatis terverifikasi dalam mode integrasi terkontrol (`sandbox`/`sync`), sehingga metrik latensi di jaringan produksi terdistribusi dapat berbeda bergantung topologi host dan penyedia gateway WhatsApp eksternal.

---

## 5. Status & Detail 2 Tangkapan Layar HD (1920x1080)

Kedua tangkapan layar berhasil diambil bersih (tanpa panel DevTools) pada resolusi **1920x1080** dan disimpan di `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\screenshots_jurnal\`:

1. **[Gambar_1_Peta_Interaktif_SmartFocus_360.png](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/screenshots_jurnal/Gambar_1_Peta_Interaktif_SmartFocus_360.png)** (`607.001 bytes`)
   - **Konten Terverifikasi:** Kanvas 3D WebGL Mapbox GL JS v3.9.0 ter-render sempurna dengan ekstrusi bangunan kampus USU, HUD kanan atas (`Mode 3D`, `Satelit`, `Reset Kamera`, `Fotokopi (32)`), sidebar kiri hasil pencarian SPARQL `Literacy` (**91 Jadwal**, badge **`SPARQL (Apache Jena Fuseki)`**), kartu mata kuliah MKWU, popup interaktif **Smart Focus** pada **Gedung Kuliah Bersama (GKB)** menyorot **Ruang 2.5** (`Hasil Pencarian` / `Pilihan Anda`) beserta thumbnail gedung, serta widget AI **Tanya Rexa**.
2. **[Gambar_2_Antarmuka_POS_Kasir_Fotokopi.png](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/screenshots_jurnal/Gambar_2_Antarmuka_POS_Kasir_Fotokopi.png)** (`168.934 bytes`)
   - **Konten Terverifikasi:** Halaman `/cashier-fotokopi/pos` setelah login terautentikasi (`superadmin@usu.ac.id`), menampilkan navigasi Multi-Panel (`Panel Akademik USU`, `Panel Admin Fotokopi`, `Panel Kasir & POS`), filter katalog (`Semua (70)`, `Jasa Cetak & Jilid (18)`, `Barang ATK & Niche (52)`), kotak pencarian & sub-kategori, kartu layanan lengkap dengan gambar `.webp` yang termuat rapi, serta simulasi pengisian keranjang kasir tanpa menekan tombol submit (`fotocopy_orders` tetap `6` baris).
