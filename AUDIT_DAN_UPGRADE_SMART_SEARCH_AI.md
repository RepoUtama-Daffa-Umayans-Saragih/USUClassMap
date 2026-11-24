# AUDIT & ARSITEKTUR UPGRADE: SMART SEARCH & AI "TANYA REXA" (USUClassMap)

**Tanggal Audit:** 24 November 2026  
**Target Proyek:** `USUClassMap` (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)  
**Acuan Blueprint:** `PROMPT_DAN_BLUEPRINT_UPGRADE_SMART_SEARCH_AI.md`  

---

## BAB 1: AUDIT SEARCH & AI EXISTING

Hasil pemindaian menyeluruh terhadap seluruh layer pencarian dan AI pada proyek `USUClassMap` menemukan celah arsitektural utama berikut:

### 1.1 Pencarian Jadwal & Peta (`/map`, `ScheduleApiController`, `SparqlService`)
- **Sudah Kuat di Akademik:** `SparqlService::searchSchedules()` telah memiliki 4-tier query (AND exact -> multi-token OR + relevance ranking -> fuzzy Levenshtein typo fallback -> colloquial stem expansion) dengan Apache Jena Fuseki (5.025 triples) dan auto-fallback SQLite/MySQL.
- **Keterbatasan Alias 77 Gedung:** `SparqlService::getFacultyAliases()` baru memetakan sebagian gedung awal (~31 gedung) dan belum mencakup seluruh 77 gedung di database (mis. `71: FMIPA-U4`, `72: FMIPA-U5`, `73: FT-Elektro_K`, `74: FT-Industri_J`, `75: FT-SIPIL_SC`, `76: FKEP`, `77: FKM-B`, `78: FKM-E`, `48/50-61: FIB-*`, `62: FF-Lt.2`, `63: FH-GB`, `64: GDP / Gedung Pancasila`, `66-70`, `79`, dan landmark pusat seperti `#1 REKTORAT`, `#2 AUDITORIUM`, `#3 PERPUS-USU`, `#6 PSI-USU`, `#7 LPPM-USU`).
- **Nol Penalaran Spasial Relatif:** Baik `SparqlService` maupun sidebar `/map` (`resources/js/features/map.js`) tidak memahami clue spasial mahasiswa (*"di depan FIB di samping FMIPA"*, *"di samping fakultas B"*, *"antara GKB dan Perpustakaan"*). Query seperti itu langsung dikirim ke `/api/schedules/search` dan mengembalikan **0 hasil**.
- **Belum Mendukung Direct Focus URL & Produk Fotokopi di Sidebar:** `/map` belum membaca query string `?focus_building={id}` dan sidebar `/map` tidak dapat mencari produk/jasa fotokopi-ATK lintas 32 toko.

### 1.2 Pencarian Fotokopi & ATK (`/fotocopy`, `PhotocopyController`, `CopyShopApiController`)
- **Naive Exact Phrase `LIKE`:** `PhotocopyController::index()` dan `CopyShopApiController::index()` melakukan query SQL mentah `WHERE name LIKE "%{$search}%"`. Akibatnya, kalimat natural seperti `"cari pulpen pink murah terdekat"` dicari secara literal sebagai satu substring utuh dan langsung menghasilkan **0 toko / 0 produk**.
- **Fakta Katalog Database (`32` Toko, `2.689` Item):**
  - Terdapat **337 item** cocok untuk `pulpen` (`Pulpen Standard AE7 0.5mm Hitam/Biru/Merah`, `Pulpen Faster C6`, `Pulpen Joyko GP-265 Q-Gel`, `Pulpen Zebra Sarasa Clip 0.5mm`, dll).
  - Namun **0 item pulpen** menyebut warna `pink` secara eksplisit (hanya terdapat 9 item bertema `pink`/`pastel` pada `Kertas HVS Warna` dan `Highlighter Joyko Pastel`). Sistem lama langsung menyerah (0 hasil) tanpa memberikan alternatif jujur yang tersedia.
  - Tidak ada parser preferensi harga (`murah`, `di bawah 2000`), pemisahan satuan eceran (`pcs`) vs grosir (`1 Box 12 pcs`), pengenalan sinonim (`pena`/`bolpen` -> `pulpen`), jarak Haversine + estimasi waktu jalan kaki dari GPS atau patokan gedung, maupun tombol **Rute GPS** langsung per hasil produk.

### 1.3 AI "Tanya Rexa" (`AiCopilotController`, `AiCopilotService`, `copilot.js`, `chatbot-widget.blade.php`)
- **Single Point of Failure (HTTP 500 saat Offline / API Key Kosong):** `AiCopilotService::chat()` langsung melempar `\RuntimeException` bila `CLASSAI_API_KEY` kosong atau bila ClassAI Router gagal merespons HTTP, sehingga `AiCopilotController` membalas **HTTP 500** dan menjatuhkan widget ke fungsi regex statis `prosesOffline()` yang sangat terbatas.
- **Belum Grounded Lintas Domain:** AI hanya memiliki 5 tool akademik (`cari_jadwal_kuliah`, `detail_gedung`, `cari_dosen`, `ambil_jadwal_saya`, `cek_kemiripan_fakultas`). Belum ada tool/engine untuk **Smart Fotokopi/ATK Search**, **Spatial Landmark Guessing**, **In-App Route Action**, penerimaan **GPS pengguna (`user_lat`, `user_lng`)**, maupun keluaran terstruktur `cards[]`, `actions[]`, dan `advisory_note`.
- **Risiko Halusinasi:** Saat pengguna bertanya produk fotokopi dengan atribut yang tidak ada (contoh: `"pulpen pink"`), LLM murni tanpa pre-query terstruktur berisiko mengarang atau sekadar meminta maaf pasif.

---

## BAB 2: ARSITEKTUR UPGRADE 3 ENGINE CERDAS & TANYA REXA GROUNDED

Seluruh celah di atas diselesaikan melalui arsitektur deterministik lokal (**100% tanpa halusinasi**, tetap bekerja sempurna walaupun offline atau tanpa API key eksternal) yang terintegrasi ke seluruh endpoint API, Tanya Rexa, `/map`, dan `/fotocopy`:

### 2.1 Tiga Engine Backend Deterministik (`USUClassMap/app/Services/Search/`)
1. **`QueryIntentParserService`**
   - *Normalizer & Filler Stripper:* Menormalkan teks Indonesia, menoleransi typo via Levenshtein/fuzzy matching (`polpen` -> `pulpen`, `fotokopi` -> `fotocopy`), mengekspansi sinonim ATK (`pena|bolpen|bulpen|ballpoint` -> `pulpen`, `stabilo` -> `highlighter`, `hvs` -> `kertas`, `merah muda|jambu` -> `pink`).
   - *Full 77-Building Alias Resolver:* Memuat seluruh 77 record `Building` secara dinamis + kamus alias lengkap 16 fakultas & landmark utama + deteksi cerdas referensi sayap/blok (`fakultas B` / `gedung B` -> mendahulukan `-B` yang sefakultas atau terdekat dari landmark lain di query).
   - *Modifier & Spatial Extractor:* Mengekstrak `item_terms`, `attributes`, `price_preference`, `max_price` (mis. `di bawah 2rb` -> `2000`), `proximity_preference`, `open_now`, `bulk_requested`, `anchor_landmark`, serta `spatial_constraints` (`front`, `side`, `back`, `between`, `near`) beserta `course_terms` dan `day`.
   - *Multi-Intent Classifier:* Mengklasifikasikan ke `smart_fotocopy_search`, `spatial_building_finder`, `route_navigation`, `schedule_search`, atau `general_campus_qa`.

2. **`SmartPhotocopySearchService`**
   - *Penentuan Anchor Lokasi Berjenjang:* (1) `anchor_landmark` eksplisit pada query (mis. *"dekat FIB"*), (2) GPS pengguna (`lat`, `lng`), (3) pusat kampus USU `(3.5612, 98.6565)`.
   - *Honest Relaxed-Attribute Fallback:* Jika pengguna meminta `item + atribut` (mis. *"pulpen pink"*), mesin mencari kecocokan atribut persis terlebih dahulu (`exact_attribute_match`). Bila tidak ada yang cocok pada katalog (0 pulpen pink), mesin otomatis melonggarkan atribut warna menjadi `pulpen` terbaik, menyetel `relaxed_fallback_used = true`, `missing_attributes = ['pink']`, dan menyusun `advisory_note` Indonesia yang jujur dan jelas.
   - *Eceran vs Grosir Intelligence & Multi-Factor Ranking:* Saat pengguna meminta `murah`/`cheapest` tanpa kata `box`/`lusin`/`rim`, item eceran mendapat prioritas tinggi di atas paket grosir/dus. Peringkat menggabungkan relevansi, harga, jarak Haversine + estimasi jalan kaki (`75 m/menit`), status buka saat ini, rating toko, serta pembatasan variasi toko agar pengguna melihat pilihan dari beberapa toko terdekat.

3. **`SpatialLandmarkResolverService`**
   - *Arah Hadap Gedung Berbasis Data Nyata:* Menggunakan vektor `center -> entrance_lat/lng` atau node jaringan jalan terdekat pada `storage/app/routing/usu_campus_graph.json`.
   - *Skoring 5 Relasi Spasial + Multi-Constraint Intersection:* Mengevaluasi relasi `front` (`depan`/`seberang`), `side` (`samping`/`sebelah`), `back` (`belakang`), `between` (`antara X dan Y`), dan `near` (`dekat`/`sekitar`) terhadap seluruh 77 gedung menggunakan kurva peluruhan jarak kontinu sehingga pasangan landmark jauh sekalipun (seperti `FIB` ↔ `FMIPA` ~473 m) tetap menghasilkan kandidat koridor persimpangan yang masuk akal, sambil mengeluarkan gedung landmark acuan dari daftar tebakan.
   - *Fusi Jadwal & Skor Keyakinan (`confidence_percent`):* Menggabungkan kecocokan mata kuliah/hari (jika disebutkan), melampirkan jumlah ruangan, indikator tur 360°, alasan spasial konkret berbasis jarak meter & arah mata angin, serta URL in-app `/map?focus_building={id}` dan `/map?route_to=building:{id}`.

### 2.2 Orkestrasi API, AI "Tanya Rexa", `/map`, dan `/fotocopy`
- **API Terpadu (`routes/api.php`):**
  - `GET /api/smart-search` (`q`, `lat`, `lng`, `day`, `scope`, `limit`)
  - `GET /api/fotocopy/smart-search` (`q`, `lat`, `lng`, `sort`, `open_now`, `max_price`, `limit`)
- **AI Tanya Rexa Grounded (`AiCopilotController` + `AiCopilotService`):**
  - Menerima `user_lat`, `user_lng`, dan `current_page`.
  - Selalu mengeksekusi **Local-First Grounded Pipeline** melalui `QueryIntentParserService` + engine terkait terlebih dahulu sehingga menghasilkan `reply` faktual + `cards[]` + `actions[]` + `advisory_note` + `mapAction` berstatus **HTTP 200** bahkan ketika `CLASSAI_API_KEY` kosong atau offline (zero HTTP 500, zero halusinasi).
- **Integrasi Frontend:**
  - `copilot.js` & `chatbot-widget.blade.php` mengirim koordinat GPS terakhir + halaman aktif, merender kartu interaktif `fotocopy_item` (thumbnail `.webp`, harga, toko, jarak, tombol **Pesan di Toko** & **Rute GPS**), `spatial_building` (`%` cocok, alasan spasial, **Lihat di Peta** & **Rute GPS ke Sini**), dan `schedule`, serta banner `advisory_note`.
  - `/map` (`map.js` + `index.blade.php`) mendukung `?focus_building={id}` dan merutekan pencarian spasial maupun fotokopi/ATK dari sidebar langsung ke `/api/smart-search` dengan tetap mempertahankan kecepatan pencarian jadwal SPARQL.
  - `/fotocopy` (`public/index.blade.php` + `PhotocopyController`) menambahkan panel **Smart Product & Service Search** dengan tombol **"Gunakan Lokasi GPS Saya"** dan hasil real-time `/api/fotocopy/smart-search` lintas 32 toko.

---

## BAB 3: HASIL PENGUJIAN & VERIFIKASI AKTUAL (FASE 4 – FASE 5)

### 3.1 Daftar Lengkap File Baru & File yang Dimodifikasi

#### File Baru
1. `USUClassMap/app/Services/Search/QueryIntentParserService.php` — Engine 1 (NLP Indonesia, normalisasi typo Levenshtein & sinonim, alias 77 gedung + disambiguasi sayap `fakultas B`, ekstraktor harga/jarak/buka/grosir, ekstraktor relasi spasial, dan klasifikasi multi-intent).
2. `USUClassMap/app/Services/Search/SmartPhotocopySearchService.php` — Engine 2 (pencarian cerdas lintas 32 toko & 2.689 item, *honest relaxed-attribute fallback*, prioritas eceran vs grosir, ranking multi-faktor + Haversine & estimasi menit jalan kaki, tautan order & rute in-app `/map?route_to=fotocopy:{id}`).
3. `USUClassMap/app/Services/Search/SpatialLandmarkResolverService.php` — Engine 3 (estimasi arah hadap gedung via `entrance_lat`/`entrance_lng` atau node `usu_campus_graph.json`, skoring 5 relasi spasial `front`/`side`/`back`/`between`/`near` + koridor multi-landmark, pengecualian gedung landmark dari kandidat tebakan, fusi jadwal kuliah/hari, skor `% cocok`).
4. `USUClassMap/app/Http/Controllers/Api/SmartSearchApiController.php` — Controller API terpadu `GET /api/smart-search` & `GET /api/fotocopy/smart-search`.
5. `USUClassMap/tests/Feature/SmartSearchAndAiTest.php` — 6 automated feature test terintegrasi penuh dengan snapshot 77 gedung & 2.689 produk fotokopi.

#### File yang Dimodifikasi
1. `USUClassMap/routes/api.php` — Registrasi `api.smart-search` dan `api.fotocopy.smart-search`.
2. `USUClassMap/app/Services/SparqlService.php` — Kelengkapan ID & kode gedung di `getFacultyAliases()` mencakup seluruh 77 gedung (`FMIPA-U4`, `FMIPA-U5`, `FT-*`, `FKEP`, `FKM-*`, `FIB-*`, `GDP`, dll).
3. `USUClassMap/app/Http/Controllers/AiCopilotController.php` — Dukungan `user_lat`, `user_lng`, `current_page`, pelonggaran batas `history.*.content` (`max:4000`), dan response payload terstruktur (`intent`, `advisory_note`, `cards`, `actions`, `grounded_source`).
4. `USUClassMap/app/Services/AiCopilotService.php` — Orkestrasi *Local-First Grounded* (HTTP 200 tanpa halusinasi meski offline/`CLASSAI_API_KEY` kosong), 2 tool tambahan (`cari_fotokopi_atk`, `tebak_gedung_spasial`), dan fallback lokal jadwal/dosen/gedung.
5. `USUClassMap/resources/js/copilot.js` — Pengiriman otomatis koordinat GPS terakhir (`sessionStorage` / `window.USUClassMap.getLastGpsFix()`), pemotongan aman riwayat (`slice(0, 1200)`), handler `focusCardBuilding()`, `routeCardTarget()`, dan `start_route`.
6. `USUClassMap/resources/views/components/chatbot-widget.blade.php` — UI banner `advisory_note` serta kartu interaktif `fotocopy_item` (`.webp`, harga, jarak, `Pesan di Toko`, `Rute GPS`) dan `spatial_building` (`% cocok`, alasan spasial, `Lihat di Peta`, `Rute GPS ke Sini`).
7. `USUClassMap/resources/views/features/map/index.blade.php` — Pembaruan placeholder sidebar `#map-search-input`.
8. `USUClassMap/resources/js/features/map.js` — Dukungan `?focus_building={id}`, persistensi GPS di `sessionStorage`, perutean pencarian spasial & fotokopi sidebar via `/api/smart-search`, inisialisasi default sidebar pada `fetchBuildings()`, serta penjadwalan `loadPhotocopyPlaces()` setelah `fetchBuildings()` agar boot ringan.
9. `USUClassMap/app/Http/Controllers/PhotocopyController.php` — Dukungan `smartInitialResult` + fallback pencarian pintar saat query natural di `/fotocopy?q=...` tidak cocok dengan `LIKE` mentah.
10. `USUClassMap/resources/views/features/fotocopy/public/index.blade.php` — Panel **Smart Product & Service Search** lintas 32 toko (`x-data="usuFotocopySmartSearch()"` bersih di `<script>`), tombol **"Gunakan Lokasi GPS Saya"**, filter sort/harga maks/buka sekarang, dan grid kartu produk in-app.
11. `USUClassMap/vite.config.js` — Penambahan konfigurasi `build: { sourcemap: false, reportCompressedSize: false, chunkSizeWarningLimit: 2500 }` agar build produksi Vite aman pada host Windows 8 GB RAM.

---

### 3.2 Contoh Output Aktual API & UI (Terobservasi Langsung)

#### A. Query Fotokopi & ATK dengan Atribut Tidak Tersedia: `"cari pulpen pink murah terdekat"` (`lat=3.5612, lng=98.6565`)
- **HTTP Status:** `200 OK`
- **`relaxed_fallback_used`:** `true`
- **`missing_attributes`:** `["pink"]`
- **`advisory_note`:** *"Belum ada pulpen dengan spesifikasi/warna \"pink\" tercatat di katalog 32 mitra fotokopi USU. Menampilkan alternatif pulpen terbaik, termurah & terdekat yang tersedia — kamu bisa konfirmasi ketersediaan pink langsung ke toko sebelum memesan."*
- **Top 3 Rekomendasi Eceran (18 hasil total):**
  1. `Pulpen Standard AE7 0.5mm Merah (Eceran)` — **Rp 2.000/pcs** (`is_bulk: false`) di *Berkah Abadi Copy & ATK Perpustakaan* (`~94 m`) -> `/fotocopy/5/order` & `/map?route_to=fotocopy:5`
  2. `Pulpen Standard AE7 0.5mm Hitam (Eceran)` — **Rp 2.500/pcs** (`is_bulk: false`) di *Berkah Abadi Copy & ATK Perpustakaan* (`~94 m`) -> `/fotocopy/5/order` & `/map?route_to=fotocopy:5`
  3. `Pulpen Standard AE7 0.5mm Biru (Eceran)` — **Rp 2.500/pcs** (`is_bulk: false`) di *New Nasional Fotocopy* (`~157 m`) -> `/fotocopy/16/order` & `/map?route_to=fotocopy:16`

#### B. Query Spasial Multi-Constraint: `"saya ingatnya kelas saya di depan FIB di samping FMIPA"`
- **HTTP Status:** `200 OK` | **`intent`:** `spatial_building_finder` | **Total Kandidat:** `5` (seluruh landmark acuan `FIB` & `FMIPA` dikecualikan)
- **Top Kandidat Terobservasi:**
  1. **FIB D (`FIB-D`)** — **80% cocok** (*"Berjarak ~41 m di sebelah Utara dari FIB dan ~459 m di sebelah Timur dari FMIPA-U4. Arah hadap gedung menghadap Tenggara."*) -> `/map?focus_building=60` & `/map?route_to=building:60`
  2. **FIB H (`FIB-H`)** — **77% cocok** (*"Berjarak ~76 m di sebelah Barat Laut dari FIB dan ~420 m di sebelah Timur dari FMIPA-U4. Arah hadap gedung menghadap Selatan."*)
  3. **FIB N (`FIB-N`)** — **75% cocok** (*"Berjarak ~104 m di sebelah Barat Laut dari FIB dan ~374 m di sebelah Timur dari FMIPA-U4..."*)
  4. **FIB B (`FIB-B`)** — **72% cocok** (`~11 m` Barat Laut `FIB`, `~463 m` Timur `FMIPA-U4`)
  5. **FIB K (`FIB-K`)** — **69% cocok** (`~94 m` Barat `FIB`, `~381 m` Timur `FMIPA-U4`)

#### C. Query Spasial Sayap Ambigu: `"saya ingatnya kelas saya di depan FIB di samping fakultas B"`
- **`intent`:** `spatial_building_finder`
- **`advisory_note`:** *"Patokan \"fakultas b\" ditafsirkan sebagai Gedung/Sayap B (FIB-B)."*
- **Top 3 Kandidat:** `FIB-D` (88%), `FIB-A` (84%), `Gedung A / A FIB` (81%).

---

### 3.3 Ringkasan 8 Quality Gates (Eksekusi Sekuensial)

| Gate | Pemeriksaan | Hasil Aktual Terobservasi |
| :--- | :--- | :--- |
| **Gate 1** | PHP Lint (`php -l`) seluruh file baru & modifikasi | **PASS** — `0` syntax error di semua 10 file PHP yang diperiksa |
| **Gate 2** | Blade View Compilation (`config:clear`, `view:cache`, `view:clear`) | **PASS** — Seluruh template Blade berhasil dikompilasi dan dibersihkan |
| **Gate 3** | Vite Production Build (`npm run build`, `GOMAXPROCS=1`) | **PASS** — Selesai dalam `25.52s`, `public/build/manifest.json` memuat `copilot-CdBr4dc2.js`, `map-DdEpYF72.js`, `app-CiKDhjhC.js`, `app-Dr5QzfSa.css`, `fotocopy-pdf-uploader`, & `mapbox-gl` |
| **Gate 4** | Inline JS Syntax Audit (`node --check` hasil render `/map` & `/fotocopy`) | **PASS** — 2 blok inline `<script>` (19.513 karakter & 6.759 karakter) lulus dengan `0` syntax error |
| **Gate 5** | Full PHPUnit Suite (`php artisan test`) | **PASS** — **107 passed (945 assertions)** dalam `150.66s` (seluruh 101 test lama + 6 test baru `SmartSearchAndAiTest`) |
| **Gate 6** | Semantic Web RDF & Apache Jena Fuseki (`rdf:generate --upload`) | **PASS** — Upload ke Fuseki `:3030` berhasil dengan **5.025 triples** terverifikasi |
| **Gate 7** | Playwright Headless Edge Smoke Test (`/fotocopy`, `/map`, Tanya Rexa multi-turn) | **PASS** — Flow A (`18` kartu + banner jujur `pink`), Flow B (`77 Gedung` + sidebar spasial `FIB-D`), Flow C (Tanya Rexa spasial `% cocok`), Flow D (Tanya Rexa multi-turn fotokopi) — **`PAGE_ERRORS_COUNT: 0`** |
| **Gate 8** | Auto-Sync Git Snapshot (`app:git-sync --export` & `--status`) | **PASS** — `Status Sinkronisasi: SINKRON` (Content Hash `67447b8a8cba47f42f4692e81657b867ba5193add3c14547ab3c44443ff548dd`, 77 gedung, 318 jadwal, 32 toko, 2.689 item) |

