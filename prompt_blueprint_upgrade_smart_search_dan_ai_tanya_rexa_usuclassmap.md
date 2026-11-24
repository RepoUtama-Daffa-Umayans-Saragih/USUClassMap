# Prompt & Blueprint Eksekusi: Upgrade Smart Search & AI "Tanya Rexa" (`USUClassMap`)

> **Tujuan Dokumen:** Panduan dan prompt *self-contained* untuk dianalisis serta dieksekusi di **Antigravity** (`Orchestrator → Claude Opus 5.5` / `Gemini 4 Neon`, mode `Turbo`).  
> **Target Proyek:** `USUClassMap` (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)  
> **Output Laporan Hasil Eksekusi:** `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md`

---

## Ringkasan Kemampuan Baru yang Dibangun

1. **Smart Photocopy & ATK Search (Pencarian Produk Berbasis Atribut, Harga & Jarak):**
   - Memahami kueri bahasa sehari-hari seperti *"cari pulpen pink murah terdekat"*, *"print warna A4 paling murah dekat Fasilkom"*, *"jilid hardcover skripsi yang lagi buka"*, atau *"materai 10rb di bawah 15000 terdekat"*.
   - Menggabungkan pencocokan item + atribut (warna, merek, ukuran, sinonim, toleransi typo) + ranking harga + jarak nyata (GPS user atau patokan gedung) + status operasional toko, serta memberikan penjelasan jujur jika atribut spesifik (mis. warna `"pink"`) belum tercatat eksplisit di katalog sambil tetap menampilkan rekomendasi item utama termurah & terdekat.
2. **Spatial & Landmark Reasoning (Pencarian Gedung/Kelas Berbasis Petunjuk Posisi):**
   - Memahami kueri natural seperti *"saya ingatnya kelas saya di depan FIB di samping fakultas B"*, *"gedung antara FIB dan Perpustakaan"*, *"di belakang rektorat"*, atau *"kelas literasi di sebelah FMIPA"*.
   - Menyelesaikan relasi spasial nyata antar **77 gedung** dan **32 mitra fotokopi** berdasarkan koordinat, vektor arah hadap pintu masuk (*facing bearing*), interseksi multi-patokan, serta integrasi langsung dengan jadwal kuliah/MKWK.
3. **Grounded AI Copilot ("Tanya Rexa") & Universal Search Bar:**
   - Mengintegrasikan ketiga engine lokal tersebut ke AI **Tanya Rexa** (anti-halusinasi, tetap 100% jalan meski LLM eksternal sedang offline/limit) lengkap dengan kartu hasil interaktif (**Pesan di Toko**, **Lihat di Peta**, **Rute GPS ke Sini**), serta meng-upgrade kotak pencarian di `/map` dan `/fotocopy`.

---

## Salin Prompt Berikut ke Antigravity

```markdown
<role>
Anda adalah Principal AI Search Architect, Geospatial NLP Engineer, dan Full-Stack Laravel Specialist.
Target Proyek: USUClassMap (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`).
Dokumen Analisis & Laporan Upgrade Target: `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md`.
</role>

<mission_and_strict_rules>
1. ANALISIS DULU, LALU UPGRADE MENYELURUH:
   - Pada **FASE 1**, lakukan pemindaian dan analisis mendalam terhadap SELURUH fitur pencarian dan AI yang sudah ada di proyek ini (controller, service, API, SPARQL, frontend search bar, dan widget AI **Tanya Rexa**). Simpan hasil analisis lengkap + rancangan arsitektur upgrade ke `AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md`.
   - Setelah FASE 1 selesai didokumentasikan, langsung lanjutkan mengeksekusi **FASE 2 sampai FASE 5** secara tuntas tanpa stub/placeholder.
2. DUA KEMAMPUAN PINTAR WAJIB (GROUNDED — ZERO HALLUCINATION):
   - **Smart Photocopy & ATK Search:** Harus paham kueri natural seperti *"cari pulpen pink murah terdekat"*, *"print warna A4 paling murah dekat Fasilkom"*, *"jilid hardcover skripsi yang lagi buka"*, *"materai 10rb terdekat"* — menggabungkan kecocokan barang/atribut (warna, merek, ukuran, sinonim, toleransi typo) + pengurutan harga + jarak nyata (dari GPS user atau patokan gedung) + status buka, serta memberikan **saran jujur** apabila atribut spesifik (mis. warna `"pink"`) tidak tertulis eksplisit di katalog namun item utamanya (`pulpen`) tersedia.
   - **Spatial & Landmark Reasoning (Pencarian Gedung/Kelas Berbasis Petunjuk Posisi):** Harus paham kueri natural seperti *"saya ingatnya kelas saya di depan FIB di samping fakultas B"*, *"gedung antara FIB dan Perpustakaan"*, *"di belakang rektorat"*, *"kelas literasi di sebelah FMIPA"* — menyelesaikan relasi spasial nyata antar 77 gedung & 32 fotokopi berbasis koordinat, arah hadap jalan/pintu masuk, interseksi multi-patokan, dan opsional gabungan dengan data jadwal mata kuliah/MKWK.
3. ZERO FEATURE LOSS & STABILITAS:
   - Pertahankan 100% fitur existing: Mapbox GL 3D/2D + Google Satellite Hybrid (`lyrs=y`) + In-App Campus Routing + Auto-GPS + Layer 32 Fotokopi + 360° Pannellum, Apache Jena Fuseki (`5.025` triples) + Auto-Fallback SQLite, Cart-Bound Multi-PDF 1:1 Quota + Invoice PDF + WA Gateway `:3000`, serta `'resources/js/copilot.js'` di `vite.config.js`.
   - Seluruh logika Alpine.js kompleks wajib di dalam `<script>` (0 kebocoran JS di `x-data="..."`).
</mission_and_strict_rules>

---

## FASE 1: AUDIT & ANALISIS MENYELURUH SEMUA FITUR SEARCH & AI EKSISTING

Inspeksi seluruh file berikut secara menyeluruh sebelum menulis kode upgrade:
1. **Pencarian Jadwal & Peta:** `app/Http/Controllers/Api/ScheduleApiController.php`, `app/Services/SparqlService.php`, `resources/js/features/map.js`, `resources/views/features/map/index.blade.php`.
2. **Pencarian Fotokopi & Katalog ATK:** `app/Http/Controllers/PhotocopyController.php`, `app/Http/Controllers/Api/CopyShopApiController.php`, `resources/views/features/fotocopy/public/index.blade.php`, `show.blade.php`, `order.blade.php`.
3. **AI Copilot ("Tanya Rexa"):** `app/Http/Controllers/AiCopilotController.php`, seluruh service AI terkait di `app/Services/`, `resources/js/copilot.js`, dan `resources/views/components/chatbot-widget.blade.php` (atau view widget terkait).
4. **Data Aktual Database & Graf:** Periksa variasi nama gedung (77 gedung + kode/singkatan fakultas), nama ruangan (133 ruangan), jadwal (318 jadwal), serta isi atribut katalog 32 mitra fotokopi / 2.689 item (`name`, `category`, `sub_category`, `brand`, `variant`/`specs`, `description`, `price`, `stock`, `lat`/`lng`, `open_time`/`close_time`).

**Catat temuan FASE 1 ke Bab 1 & Bab 2 `AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md`:**
- Inventaris setiap titik pencarian & AI saat ini (endpoint, algoritma, keterbatasan).
- Analisis celah mengapa kueri seperti *"cari pulpen pink murah terdekat"* dan *"kelas saya di depan FIB di samping fakultas B"* belum bisa dijawab akurat oleh sistem saat ini.
- Arsitektur solusi terpadu yang akan dieksekusi di Fase 2–3.

---

## FASE 2: BANGUN 3 ENGINE PENCARIAN CERDAS DI BACKEND (`app/Services/Search/`)

### 2.1 `QueryIntentParserService` (NLP Bahasa Indonesia + Sinonim + Typo + Ekstraksi Entitas)
Buat parser deterministik yang cepat (bekerja 100% andal baik saat LLM eksternal aktif maupun offline/fallback):
- **Normalisasi & Typo Tolerance:** Bersihkan kata pengisi percakapan (*"saya ingatnya tuh"*, *"tolong carikan"*, *"dong"*, *"yang"*, *"dimana yah"*), perbaiki typo umum (mis. `lireacy` -> `literacy`, `fotocopy`/`potokopi` -> `fotokopi`, `pena`/`bolpen`/`ballpoint` -> `pulpen`, `ngeprint`/`nge-print` -> `print`, `lakban` -> `jilid lakban`), dan dukung Levenshtein / *trigram similarity* untuk nama gedung, mata kuliah, dan produk.
- **Klasifikasi Multi-Intent:** Deteksi otomatis apakah kueri adalah:
  1. `smart_fotocopy_search` (mencari barang ATK / jasa cetak / toko fotokopi),
  2. `spatial_building_finder` (mencari gedung/kelas berdasarkan petunjuk arah/lokasi relatif seperti `depan`, `seberang`, `samping`, `sebelah`, `belakang`, `antara`, `dekat`, `sekitar`),
  3. `schedule_search` (mencari jadwal kuliah, mata kuliah, dosen, hari, ruangan — bisa dikombinasikan dengan `spatial_building_finder`!),
  4. `route_navigation` (meminta diarahkan ke suatu tujuan),
  5. `general_campus_qa`.
- **Ekstraksi Modifier Fotokopi/ATK:**
  - `item_terms` (mis. `pulpen`, `map`, `kertas a4`, `print warna`, `laminating`, `materai`),
  - `attributes` (warna: `pink`/`merah muda`, `hitam`, `biru`, `merah`, `hijau`, `kuning`, `pastel`; merek: `Joyko`, `Pilot`, `Snowman`, `SIDU`, `Kenko`, `Faber-Castell`; ukuran: `A4`, `F4`, `A3`, `KTP`, `0.5mm`),
  - `price_preference` (`cheapest` bila ada `murah`/`termurah`/`hemat`/`ekonomis`, atau `max_price` bila ada `di bawah 5000` / `< 10rb` / `maksimal 15000`),
  - `proximity_preference` (`nearest` bila ada `terdekat`/`dekat sini`/`paling dekat`, atau `anchor_landmark` bila ada `dekat FIB` / `sekitar GKB`),
  - `open_now` (`true` bila ada `buka`/`lagi buka`/`sekarang`).
- **Kamus Alias Gedung & Fakultas USU:** Petakan seluruh singkatan dan sebutan informal mahasiswa USU ke data aktual tabel `buildings` (mis. `FIB` / `sastra` / `budaya`, `Fasilkom` / `Fasilkom-TI` / `TI` / `Ilkom`, `FT` / `Teknik`, `FMIPA` / `MIPA`, `FH` / `Hukum`, `FK` / `Kedokteran`, `FEB` / `Ekonomi`, `FISIP`, `FP` / `Pertanian`, `FF` / `Farmasi`, `FKM`, `FKG`, `FKEP`, `FPsi` / `Psikologi`, `Fahutan` / `Kehutanan`, `GKB` / `Gedung Kuliah Bersama`, `Perpus` / `Perpustakaan`, `Rektorat` / `Biro Pusat`, `Audit` / `Auditorium`, `Panca Budi`, dll. — sesuaikan dengan nama aktual di tabel `buildings`).

### 2.2 `SmartPhotocopySearchService` (Pencarian Produk/Jasa Fotokopi Berbasis Atribut, Harga & Jarak)
Buat layanan pencarian cerdas untuk 32 mitra fotokopi dan 2.689 item (`photocopy_places` & `photocopy_items`):
- **Multi-Field Matching + Attribute Awareness:** Cocokkan `item_terms` dan `attributes` terhadap `name`, `category`, `sub_category`, `brand`, `description`, dan spesifikasi produk dengan pembobotan (*exact item match* > *partial/fuzzy match* > *category match*).
- **Penanganan Jujur untuk Atribut Spesifik yang Belum Tercatat (Kasus `"pulpen pink"`):**
  - Jika ada item yang cocok `pulpen` sekaligus memuat kata `pink`/`merah muda`, taruh di urutan teratas dengan badge **`Cocok Persis`**.
  - Jika TIDAK ada item yang secara literal memuat kata `pink` di katalog, **JANGAN mengembalikan hasil kosong!** Lakukan *relaxed attribute fallback* pada `item_terms` (`pulpen`), urutkan tetap berdasarkan `murah` + `terdekat`, lalu sertakan metadata penjelasan jujur: `missing_attributes: ['pink']`, `advisory_note: "Varian warna 'pink' belum tercatat spesifik di katalog, berikut pulpen termurah & terdekat yang tersedia — Anda dapat menambahkan catatan warna 'pink' saat memesan atau menanyakan stok ke toko."`
- **Multi-Factor Ranking Score (Harga + Jarak + Status Buka):**
  - Hitung jarak nyata (Haversine meter + estimasi menit jalan kaki $75\text{ m/menit}$) dari koordinat GPS user (jika dikirim) atau dari koordinat gedung patokan (jika user menyebut `"dekat FIB"`), atau pusat Kampus USU sebagai fallback informatif.
  - Gabungkan skor relevansi teks + skor harga (normalisasi harga terendah jika `price_preference === 'cheapest'`, filter keras jika `max_price` disebut) + skor jarak (bila `nearest` atau `anchor_landmark` aktif) + bonus status toko sedang buka (`open_now`).
- **Output Terstruktur:** Setiap hasil mengembalikan info item (nama, harga, satuan, gambar `.webp`, kategori, perilaku order), info toko (`place_id`, nama toko, alamat, status buka, jarak meter & menit jalan kaki), alasan ranking (*"Termurah Rp 2.500 • 140 m (2 mnt jalan kaki)"*), dan direct action links (`/fotocopy/{id}/order`, `/map?route_to=fotocopy:{id}`).

### 2.3 `SpatialLandmarkResolverService` (Penalaran Posisi Relatif Antar Gedung & Kelas)
Buat engine penalaran spasial nyata untuk menjawab petunjuk lokasi seperti *"saya ingatnya kelas saya di depan FIB di samping fakultas B"*:
- **Pemodelan Arah & Relasi Spasial Nyata:**
  - Untuk setiap gedung di tabel `buildings` (77 gedung), hitung orientasi arah hadap depan (*facing bearing*) menggunakan vektor `centroid -> entrance` (dari `entrance_lat`/`entrance_lng` atau node jalan terdekat pada `usu_campus_graph.json`).
  - Evaluasi setiap pasangan gedung `(Landmark L, Kandidat C)` dengan 5 relasi geometris nyata:
    1. **`di depan` / `seberang` L:** Sudut dari arah hadap `L` menuju `C` berada di sektor depan ($\le 65^\circ$ dari facing bearing, atau berada di seberang jalan utama terdekat dari `L`) dalam radius wajar ($15\text{ m} - 220\text{ m}$), dengan bobot tertinggi pada kandidat terdekat yang paling segaris depan.
    2. **`di samping` / `sebelah` L:** Sudut berada di sektor lateral ($45^\circ - 135^\circ$ kiri/kanan dari facing bearing, atau jarak sangat berdekatan $10\text{ m} - 160\text{ m}$ sebagai tetangga langsung di sisi yang sama).
    3. **`di belakang` L:** Sudut berada di sektor belakang ($\ge 115^\circ$ dari facing bearing) dalam radius $15\text{ m} - 200\text{ m}$.
    4. **`di antara` L1 `dan` L2:** Evaluasi rasio elips/koridor: $\frac{d(L_1, C) + d(C, L_2)}{d(L_1, L_2)} \le 1.35$ dan jarak tegak lurus $C$ ke segmen $\overline{L_1 L_2}$ kecil ($\le 120\text{ m}$).
    5. **`dekat` / `sekitar` L:** Berdasarkan jarak Haversine terdekat ($\le 250\text{ m}$).
- **Multi-Constraint Intersection Solver:**
  - Jika user memberikan **2 atau lebih petunjuk spasial** (contoh: Clue 1 = `di depan FIB`, Clue 2 = `di samping FMIPA` / `Fakultas Teknik`), hitung skor gabungan seluruh petunjuk sehingga gedung yang memenuhi **kedua syarat sekaligus** otomatis menempati peringkat #1!
  - **Integrasi Jadwal/Kelas:** Jika dalam kalimat yang sama user juga menyebut petunjuk kelas/mata kuliah/hari (contoh: *"kelas bahasa inggris di depan FIB"* atau *"ruang kelas saya di samping GKB hari Senin"*), gabungkan skor spasial dengan pencocokan jadwal (`schedules` / SPARQL) sehingga ruangan & jadwal persis ikut ditampilkan!
- **Output Transparan & Bisa Diverifikasi User:**
  - Mengembalikan Top 3–5 kandidat gedung teratas lengkap dengan `confidence_score` (persen), **penjelasan spasial konkret** (mis. *"Berada ~85 m di depan/seberang FIB dan ~60 m di samping FMIPA • Memiliki 14 ruangan & 28 jadwal"*), daftar ruangan/jadwal terkait, serta aksi langsung (**Fokus di Peta**, **Rute GPS ke Sini**, **Lihat 360°** jika punya panorama).

---

## FASE 3: UPGRADE AI COPILOT ("TANYA REXA") & INTEGRASI SEARCH BAR DI SELURUH HALAMAN

1. **Upgrade `AiCopilotController` & Engine AI "Tanya Rexa" (Grounded Multi-Tool Orchestrator):**
   - Terima parameter opsional `user_lat`, `user_lng`, dan `current_page` dari frontend `copilot.js` (otomatis kirim koordinat GPS terakhir jika user sudah mengizinkan lokasi).
   - Sebelum merespons (atau sebagai konteks RAG terstruktur untuk LLM jika API key LLM aktif), jalankan `QueryIntentParserService` dan panggil engine backend lokal yang sesuai (`SmartPhotocopySearchService`, `SpatialLandmarkResolverService`, `SparqlService`, dan `CampusRoutingService`).
   - **Zero Hallucination Guarantee:** Semua nama toko, harga produk, jarak meter, nama gedung, dan jadwal yang dijawab Tanya Rexa **WAJIB** berasal dari hasil database & engine lokal (tidak boleh mengarang harga/gedung).
   - Jika API LLM eksternal sedang aktif, gunakan LLM untuk menyusun jawaban percakapan yang natural & ramah berdasarkan data terverifikasi tersebut; jika API LLM sedang tidak tersedia/limit/offline, buat formatter respons percakapan bahasa Indonesia yang tetap sangat pintar dan informatif langsung dari hasil engine lokal (100% zero downtime!).
   - Kembalikan properti `cards[]` & `actions[]` terstruktur pada JSON respons Copilot agar frontend `copilot.js` / `chatbot-widget.blade.php` merender **kartu hasil interaktif langsung di dalam bubble chat Tanya Rexa**:
     - Kartu Produk Fotokopi/ATK: thumbnail `.webp`, harga, nama toko, jarak & estimasi jalan kaki, tombol **`Pesan di Toko`** (`/fotocopy/{id}/order`) & **`Rute GPS`** (`/map?route_to=fotocopy:{id}`).
     - Kartu Kandidat Gedung Spasial: nama gedung, badge kecocokan (mis. `92% cocok`), alasan posisi (*"Depan FIB • Samping FMIPA"*), tombol **`Lihat di Peta`** (fokus kamera langsung jika sedang di `/map`, atau buka `/map?focus_building={id}`) & **`Rute GPS ke Sini`** (`/map?route_to=building:{id}`).
     - Kartu Jadwal Kuliah: mata kuliah, dosen, hari/jam, ruangan, tombol **`Fokus Gedung`** & **`Rute ke Kelas`**.

2. **Endpoint Smart Search Terpadu (`routes/api.php`):**
   - `GET /api/smart-search`: endpoint pencarian universal (menerima `q`, `lat`, `lng`, `day`, `scope` = `all|schedule|building|fotocopy`) yang mengembalikan hasil terstruktur `intent`, `advisory_note`, `schedules[]`, `spatial_buildings[]`, dan `fotocopy_items[]`.
   - `GET /api/fotocopy/smart-search`: endpoint khusus pencarian pintar direktori & produk fotokopi/ATK (mendukung `q`, `lat`, `lng`, `sort`, `open_now`, `max_price`).

3. **Upgrade Search Bar di `/map` (`map/index.blade.php` & `map.js`):**
   - Biarkan kotak pencarian utama `/map` tetap super cepat untuk pencarian jadwal SPARQL, **DAN** sekarang juga mampu otomatis mengenali:
     - Kueri petunjuk posisi gedung (*"depan fib samping fmipa"*) → menampilkan kartu **Tebakan Lokasi Gedung (Spatial Reasoning)** + langsung menyorot/zoom ke kandidat gedung di peta dengan tombol **Rute GPS ke Sini**.
     - Kueri pencarian produk/jasa fotokopi (*"pulpen pink murah terdekat"*) → menampilkan kartu **Hasil Fotokopi & ATK Terdekat/Termurah** (mengirim koordinat GPS user bila tersedia) + menyorot pin toko di peta dengan tombol **Pesan** & **Rute GPS ke Toko**.
   - Dukung query parameter baru `/map?focus_building={id}` agar tombol **Lihat di Peta** dari Tanya Rexa langsung memfokuskan kamera dan membuka popup gedung tujuan.

4. **Upgrade Search Bar di `/fotocopy` (`fotocopy/public/index.blade.php` & `PhotocopyController`):**
   - Tambahkan bar **Pencarian Pintar Produk & Jasa Fotokopi** (contoh placeholder: *"Coba ketik: pulpen pink murah terdekat, print warna A4 murah, jilid skripsi..."*) + tombol **`Gunakan Lokasi GPS Saya`** untuk mengurutkan hasil berdasarkan jarak nyata ke lokasi user.
   - Tampilkan hasil produk/jasa lintas 32 toko secara langsung (gambar `.webp`, harga, nama toko, jarak, status buka, catatan jujur jika atribut warna/varian tidak tercatat eksplisit, tombol **Pesan di Toko Ini**, dan **Rute di Peta** — tanpa keluar ke Google Maps).

---

## FASE 4: AUTOMATED TESTS, VERIFIKASI BROWSER & QUALITY GATES

1. **Buat Feature Test Komprehensif (`tests/Feature/SmartSearchAndAiTest.php`):**
   - **Test 1 (Smart Fotokopi — `"cari pulpen pink murah terdekat"`):** Memverifikasi `GET /api/fotocopy/smart-search?q=cari+pulpen+pink+murah+terdekat&lat=3.5625&lng=98.6596` mengembalikan HTTP `200`, mendeteksi intent `smart_fotocopy_search` dengan `price_preference = cheapest` & `proximity_preference = nearest`, mengembalikan daftar item pulpen relevan lintas toko lengkap dengan harga & `distance_meters`, serta memberikan `advisory_note` yang jujur jika warna `pink` tidak tertulis eksplisit di katalog.
   - **Test 2 (Smart Fotokopi — Harga Maksimal & Print Murah):** Uji `"print warna A4 di bawah 2000 terdekat"` memfilter `price <= 2000` dan mengurutkan secara masuk akal.
   - **Test 3 (Spatial Landmark Reasoning — Multi-Patokan):** Uji kueri *"saya ingatnya kelas saya di depan FIB di samping FMIPA"* (dan kueri `"gedung antara FIB dan Perpustakaan"`) pada `/api/smart-search` mengembalikan kandidat gedung nyata dengan `spatial_reason` yang akurat (tidak mengembalikan landmark itu sendiri sebagai jawaban utama).
   - **Test 4 (Spatial + Jadwal Gabungan):** Uji *"kelas literacy dekat GKB"* menggabungkan kandidat spasial + jadwal nyata dari database/SPARQL.
   - **Test 5 (AI Copilot Tanya Rexa End-to-End):** Uji `POST` endpoint Tanya Rexa untuk kedua kueri utama (*"cari pulpen pink murah terdekat"* dan *"saya ingatnya kelas saya di depan FIB di samping FMIPA"*) mengembalikan HTTP `200`, jawaban bahasa Indonesia grounded (tanpa error meski tanpa API key eksternal), serta `cards[]` interaktif yang memiliki tautan `/fotocopy/.../order` dan `/map?route_to=...`.
   - **Test 6 (Zero Regression & Anti-JS Leak):** Pastikan `/map`, `/fotocopy`, `/fotocopy/32/order` bebas kebocoran `x-data` dan tidak memunculkan redirect Google Maps.

2. **Quality Gates Wajib (Jalankan Semua di Folder `USUClassMap`):**
   - `php -l` pada seluruh file PHP baru/diubah (0 error).
   - `php artisan config:clear && php artisan view:cache && php artisan view:clear`.
   - Pastikan `vite.config.js` tetap menyertakan `'resources/js/copilot.js'` dan jalankan `npm run build` (exit code `0`).
   - Jalankan `node --check` pada blok `<script>` hasil render `/map` dan `/fotocopy` (0 syntax error).
   - Jalankan **SELURUH** `php artisan test` (wajib 100% PASS, 0 failed).
   - **WAJIB Pulihkan Apache Jena Fuseki:** jalankan `php artisan rdf:generate --upload` setelah `php artisan test` dan verifikasi kembali **`5.025` triples** aktif di `http://localhost:3030/usuclassmap`.
   - Uji interaktif browser dengan Playwright Edge Headless (jika tersedia):
     1. Buka `/fotocopy`, ketik `"pulpen pink murah terdekat"` → hasil produk muncul lengkap dengan harga, toko, dan tombol Rute/Pesan, `0` console error.
     2. Buka `/map`, buka widget **Tanya Rexa**, ketik `"saya ingatnya kelas saya di depan FIB di samping FMIPA"` → kartu kandidat gedung & tombol **Lihat di Peta** / **Rute GPS** tampil normal, `0` console error.
   - Jalankan `php artisan app:git-sync --export` dan `php artisan app:git-sync --status` (wajib **`SINKRON`**).

---

## FASE 5: FINALISASI DOKUMEN LAPORAN (`AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md`)

Lengkapi `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\AUDIT_DAN_UPGRADE_SMART_SEARCH_AI.md` dengan:
1. Hasil audit seluruh fitur search & AI sebelum upgrade (Bab 1).
2. Arsitektur 3 engine baru (`QueryIntentParserService`, `SmartPhotocopySearchService`, `SpatialLandmarkResolverService`) dan upgrade **Tanya Rexa** (Bab 2).
3. Contoh input–output terverifikasi nyata untuk kueri *"cari pulpen pink murah terdekat"* dan *"saya ingatnya kelas saya di depan FIB di samping ..."* (Bab 3).
4. Daftar file yang dibuat/diubah serta bukti hasil seluruh Quality Gates (`php artisan test`, `npm run build`, Playwright, Fuseki `5.025` triples, dan `app:git-sync --status`).

Tampilkan ringkasan eksekutifnya di chat setelah seluruh fase selesai.
```