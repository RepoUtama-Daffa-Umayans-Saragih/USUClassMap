# 🏛️ AUDIT KODE FITUR MAPS & DEPENDENSINYA (USUClassMap)
**Dokumen:** Laporan Audit Arsitektur Spasial, Kontrak JavaScript Global, dan Analisis Risiko Migrasi  
**Target Proyek:** `USUClassMap` (Universitas Sumatera Utara)  
**Status Audit:** READ-ONLY Deep Inspection  
**Tanggal Audit:** 22 November 2026  
**Auditor:** Senior Software Architect & Technical Auditor (Laravel + Frontend Geospatial)

---

## DAFTAR ISI
1. [Eksekutif Summary](#1-eksekutif-summary)
2. [Inventaris File & Dependensi Peta](#2-inventaris-file--dependensi-peta)
3. [Konfigurasi Peta & Aset Visual (Current State: Leaflet 2D)](#3-konfigurasi-peta--aset-visual-current-state-leaflet-2d)
4. [Daftar Lengkap Fitur Interaktif Maps & Event Flow](#4-daftar-lengkap-fitur-interaktif-maps--event-flow)
5. [Kontrak Data & Alur Backend (SPARQL/Fuseki, SQLite/MySQL)](#5-kontrak-data--alur-backend-sparqlfuseki-sqlitemysql)
6. [Integrasi Lintas Fitur & Global JavaScript Contract (KRITIS)](#6-integrasi-lintas-fitur--global-javascript-contract-kritis)
7. [Analisis Risiko Migrasi (Leaflet 2D ke Mapbox GL JS 3D)](#7-analisis-risiko-migrasi-leaflet-2d-ke-mapbox-gl-js-3d)
8. [Checklist Anti-Regresi & Kriteria Penerimaan (Acceptance Criteria)](#8-checklist-anti-regresi--kriteria-penerimaan-acceptance-criteria)

---

## 1. EKSEKUTIF SUMMARY

Fitur peta pada USUClassMap merupakan **inti pengalaman pengguna (core user experience)** aplikasi. Peta tidak hanya berfungsi sebagai visualisasi pasif, melainkan menjadi *hub* interaktif yang menghubungkan:
1. **Query Graph Semantik (SPARQL 1.1 / Apache Jena Fuseki)** dengan fallback transparan ke SQLite/MySQL.
2. **AI Copilot (Rexa Kampus)** yang secara otonom mematuk koordinat dan membuka ruangan target pada peta.
3. **Multi-Mode Media Viewer** (Foto Resolusi Tinggi, Panorama 360° interaktif via Pannellum, dan Video POV jalan kaki).
4. **Navigasi Spasial Pejalan Kaki** terintegrasi GPS Geolocation ke Google Maps Walking Directions.
5. **Admin Spatial Control Hub** untuk penataan letak koordinat gedung dan ruangan secara drag-and-drop.
6. **Ekosistem Layanan Kampus** (Layer 31 titik mitra fotokopi & percetakan USU).

Codebase saat ini mengandalkan **Leaflet.js v1.9.4** sebagai engine peta 2D dengan styling kustom penuh (*full-bleed popups*, SVG marker *ping-pulse*, dan multi-pin state). Rencana migrasi ke **Mapbox GL JS 3D** membawa potensi visual yang sangat tinggi (3D building extrusions, terrain elevation, smooth vector transitions), namun memiliki titik kritis regresi tinggi terutama pada pembalikan urutan koordinat (`[lat, lng]` vs `[lng, lat]`), siklus hidup marker DOM, dan kontrak objek global `window.USUClassMap`.

---

## 2. INVENTARIS FILE & DEPENDENSI PETA

Berikut adalah pemetaan lengkap seluruh berkas di dalam sistem yang berhubungan langsung maupun tidak langsung dengan fitur peta:

### 2.1. File Tampilan (Blade Views)

| No | Path File | Status / Peran | Deskripsi & Komponen Peta |
|:---|:---|:---|:---|
| 1 | `resources/views/features/map/index.blade.php` | **AKTIF (Utama)** | Halaman peta publik aktif (`/map`). Memuat container `#map`, sidebar pencarian, filter hari, layer fotokopi, modal multimedia multi-mode (Foto/360°/Video), dan tombol rute navigasi. |
| 2 | `resources/views/map/index.blade.php` | Legacy / Cadangan | Versi awal sebelum refactoring struktur folder `features/`. Menggunakan `@vite(['resources/js/map.js'])`. |
| 3 | `resources/views/features/admin/map.blade.php` | **AKTIF (Admin)** | Dashboard spasial admin (`/admin-sementara-/map`). Memuat `#admin-map`, draggable marker pin, integrasi Alpine.js, panel form gedung/ruangan/jadwal, dan media manager. |
| 4 | `resources/views/admin/map.blade.php` | Legacy / Cadangan | Template admin spasial cadangan (isi identik dengan versi `features/`). |
| 5 | `resources/views/features/landing/index.blade.php` | **AKTIF** | Halaman Beranda (`/`). Memuat tabel jadwal tersimpan (bookmarks), CTA menuju `/map`, dan modal multimedia preview. |
| 6 | `resources/views/welcome.blade.php` | Legacy | Template home alternatif. |
| 7 | `resources/views/public/bookmarks.blade.php` | **AKTIF** | Halaman daftar jadwal tersimpan (`/bookmarks` -> redirect ke `/#bookmarks`). Memuat link deep-link ke `/map?q={kodeMk}`. |
| 8 | `resources/views/components/chatbot-widget.blade.php` | **AKTIF** | Komponen UI floating drawer Rexa Copilot. Memuat tombol aksi `@click="triggerMapAction(msg.mapAction)"` yang memanggil API peta. |
| 9 | `resources/views/components/layouts/app.blade.php` | **AKTIF** | Master layout publik. Memuat FontAwesome 6, Google Fonts Inter, asset Vite, dan menyuntikkan widget chatbot di semua halaman. |
| 10 | `resources/views/components/navbar/public.blade.php` | **AKTIF** | Navigasi atas desktop & mobile. Memuat tautan menu navigasi ke `/map`. |
| 11 | `resources/views/admin/cek-foto.blade.php` | Utilitas | Halaman inspeksi foto 360° equirectangular dengan kalibrasi pitch & yaw ke Apache Jena Fuseki. |
| 12 | `resources/views/admin/cek-foto-p.blade.php` | Utilitas | Halaman inspeksi foto 360° partial equirectangular (cylinder mode dari kamera HP) dengan parameter `haov: 360, vaov: 50, vOffset: 0`. |

### 2.2. File JavaScript & Logika Frontend

| No | Path File | Ukuran / Baris | Deskripsi & Peran |
|:---|:---|:---|:---|
| 1 | `resources/js/features/map.js` | 70.3 KB / 1.400 baris | **Engine Peta Publik Aktif**. Mengelola inisialisasi Leaflet, fetch data API, layer marker gedung, pin jadwal saya (oranye/emas), layer fotokopi, pencarian SPARQL dengan debounce 400ms, filter hari, riwayat pencarian (localStorage), bookmarking, smart room cards, dan objek global `window.USUClassMap`. |
| 2 | `resources/js/map.js` | 69.6 KB / 1.380 baris | Engine peta publik legacy (sebelum restrukturisasi folder). |
| 3 | `resources/js/features/admin/map.js` | 16.7 KB / 407 baris | **Engine Peta Admin Aktif**. Mengelola inisialisasi peta Leaflet untuk admin, marker gedung tersimpan, marker draggable admin untuk gedung baru, mode edit posisi GPS gedung, dan event bus komunikasi dua arah dengan Alpine.js. |
| 4 | `public/js/admin-map.js` | 16.7 KB / 407 baris | Asset statis hasil salinan langsung dari `features/admin/map.js` yang dimuat pada template admin. |
| 5 | `resources/js/copilot.js` | 13.5 KB / 366 baris | Komponen Alpine.js `usuCopilot()` untuk chatbot Rexa. Bertanggung jawab mematuk aksi peta melalui `window.USUClassMap.focusBuildingWithTarget(...)`. |
| 6 | `resources/js/app.js` | 0.8 KB / 25 baris | Entry point Vite yang menginisialisasi Alpine.js dan Axios. |
| 7 | `vite.config.js` | 16 baris | Konfigurasi build Vite. Mendaftarkan entry point: `resources/css/app.css`, `resources/js/app.js`, `resources/js/features/map.js`, dan `resources/js/features/admin/map.js`. |

### 2.3. File Styling & CSS

| No | Path File | Deskripsi & Peran |
|:---|:---|:---|
| 1 | `resources/css/features/map.css` | Override styling Leaflet: `.custom-popup`, `.usu-custom-popup` (full-bleed, rounded 12px, shadow-xl, width 340px), close button hover effect, dan custom thin scrollbar (`.custom-scrollbar`). |
| 2 | `resources/css/app.css` | Mengimpor `./features/map.css` serta modul dasar Tailwind CSS v4. |

### 2.4. Backend Controllers, Services, & Routes

| No | Path File | Endpoint / Method | Deskripsi & Kontrak Data |
|:---|:---|:---|:---|
| 1 | `app/Http/Controllers/Api/BuildingApiController.php` | `GET /api/buildings`<br>`GET /api/buildings/{id}` | Mengembalikan daftar seluruh gedung kampus dengan koordinat GPS presisi (`latitude`, `longitude`), jumlah ruangan, daftar ruangan, dan jadwal kelas kontekstual. |
| 2 | `app/Http/Controllers/Api/ScheduleApiController.php` | `GET /api/schedules/search`<br>`POST /api/schedule/sync` | Endpoint pencarian jadwal semantik berbasis kata kunci (`q`) dan hari (`day`) yang diteruskan ke `SparqlService`. |
| 3 | `app/Http/Controllers/ScheduleController.php` | `GET /api/my-schedules` | Mengembalikan seluruh jadwal perkuliahan kampus dengan atribut dinamis `is_my_class: bool` sesuai semester user yang login untuk pewarnaan pin emas/oranye. |
| 4 | `app/Http/Controllers/Api/CopyShopApiController.php` | `GET /api/copy-shops`<br>`GET /api/copy-shops/{id}` | Mengembalikan 31 data lokasi mitra fotokopi/percetakan kampus USU beserta koordinat GPS, nomor WhatsApp, jam buka, dan rating. |
| 5 | `app/Http/Controllers/AdminMapController.php` | `GET /admin-sementara-/map`<br>`POST /admin-sementara-/map/building`<br>`PUT /admin-sementara-/map/building/{id}`<br>`POST /admin-sementara-/map/room`<br>`POST /admin-sementara-/map/schedule`<br>`GET /admin-sementara-/map/rooms/{building}`<br>`POST /admin-sementara-/map/rooms/{room}/media` | Controller CRUD spasial admin untuk manajemen gedung, ruangan, jadwal, dan multimedia 360°/video. |
| 6 | `app/Http/Controllers/AiCopilotController.php` | `POST /copilot/chat` | Memproses percakapan mahasiswa dengan AI Claude Haiku 4.5 via Bedrock/OpenAI SDK. Mengembalikan respon teks dan payload `mapAction` koordinat spasial. |
| 7 | `app/Services/SparqlService.php` | `searchSchedules(?string $keyword, ?string $day)` | Core Semantic Layer: Menyusun query SPARQL 1.1 `SELECT`, mengirim ke Fuseki (`POST /usuclassmap/query`), mem-parsing binding JSON, dan otomatis fallback ke Eloquent MySQL/SQLite jika Fuseki offline. |
| 8 | `app/Services/AiCopilotService.php` | `chat(string $prompt, array $history)` | Menyusun system prompt spasial dan mendeteksi kebutuhan aksi navigasi peta. |
| 9 | `routes/web.php` | Rute Web Publik & Admin | Mendefinisikan rute `/map`, `/bookmarks`, `/copilot/chat`, `/admin-sementara-/map/*`, `/admin/cek-foto`, `/admin/cek-foto-p`. |
| 10 | `routes/api.php` | Rute API RESTful | Mendefinisikan rute `/api/buildings`, `/api/schedules/search`, `/api/my-schedules`, `/api/copy-shops`. |

### 2.5. Library Pihak Ketiga & Dependensi Eksternal

| Nama Library | Versi | Metode Pemuatan | File Pemuat | Tujuan Penggunaan |
|:---|:---|:---|:---|:---|
| **Leaflet.js** | `1.9.4` | CDN (unpkg.com) + Subresource Integrity | `features/map/index.blade.php`<br>`features/admin/map.blade.php` | Engine visualisasi peta 2D, render marker HTML, tile layer OSM, popups, dan fitBounds. |
| **Pannellum** | `2.5.6` | CDN (jsdelivr.net) | `features/map/index.blade.php`<br>`features/admin/map.blade.php`<br>`admin/cek-foto.blade.php`<br>`admin/cek-foto-p.blade.php` | Engine WebGL untuk rendering panorama 360° ruang kelas (equirectangular & partial cylinder). |
| **OpenStreetMap Tile Server** | - | HTTP Tile Layer (`https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`) | `resources/js/features/map.js`<br>`resources/js/features/admin/map.js` | Penyedia peta raster ubin (tiles) gratis. |
| **FontAwesome** | `6.5.2` | CDN (cdnjs.cloudflare.com) | `components/layouts/app.blade.php` | Ikonografi marker, status jadwal, tombol navigasi, dan kontrol UI. |
| **Alpine.js** | `3.16.2` | NPM Bundle via Vite | `resources/js/app.js`<br>`package.json` | Reaktivitas frontend: sidebar drawer, modal multimedia, tab admin, dan drawer chat Rexa. |
| **Tailwind CSS** | `4.3.3` | NPM Bundle via Vite | `resources/css/app.css`<br>`package.json` | Styling layout responsive, utilitas kelas flexbox/grid, dan efek transisi. |
| **Axios** | `1.7.4` | NPM Bundle via Vite | `resources/js/bootstrap.js`<br>`package.json` | HTTP Client AJAX dengan header `X-Requested-With` dan CSRF token. |

---

## 3. KONFIGURASI PETA & ASET VISUAL (CURRENT STATE: LEAFLET 2D)

### 3.1. Koordinat Default & Parameter Kamera
* **Titik Pusat Kampus (Default Center):**
  * Lintang (*Latitude*): `3.565245`
  * Bujur (*Longitude*): `98.656520`
  * Lokasi Geografis: Area Kampus USU Padang Bulan, Medan, Sumatera Utara.
* **Tingkat Zoom (*Zoom Levels*):**
  * `DEFAULT_ZOOM`: `16` (tampilan area fakultas/gedung kampus).
  * `maxZoom` Tile Layer: `19`.
  * `Focus Zoom` (saat klik gedung/ruangan target): `18` (tampilan sangat dekat dengan gedung).
  * `FitBounds Max Zoom`: `17` dengan padding `[50, 50]` atau `[60, 60]`.
* **Kontrol Peta (*Controls*):**
  * Inisialisasi awal menonaktifkan kontrol zoom bawaan: `{ zoomControl: false }`.
  * Kontrol zoom kustom dipasang manual di pojok kanan atas: `L.control.zoom({ position: 'topright' }).addTo(map);`.

### 3.2. Spesifikasi Desain Marker (Custom HTML `L.divIcon`)

Aplikasi mendefinisikan 5 tipe marker kustom menggunakan elemen HTML dan SVG murni:

1. **Pin Gedung Normal (`createBuildingIcon` - Default):**
   * Lingkaran diameter 36px berwarna hijau USU (`#10A760`), border putih 2px, shadow besar.
   * Ikon SVG gedung di bagian tengah.
   * Label kode gedung di bagian bawah (`bg-gray-900/80` backdrop blur, teks putih 11px font-semibold).
2. **Pin Gedung Hasil Pencarian (`createBuildingIcon` - isHighlighted = true):**
   * Lingkaran diameter 36px berwarna emas/kuning (`#D4A017`).
   * Dilengkapi elemen animasi radar/denyut: `<div class="absolute -inset-2 rounded-full bg-yellow-400 opacity-40 animate-ping"></div>`.
3. **Pin Jadwal Kuliah Saya (`createUserScheduleIcon` - Khusus Mahasiswa Login):**
   * Lingkaran diameter 40px berwarna oranye tua (`#EA580C`), ring ganda kuning (`ring-2 ring-yellow-400`), shadow ekstra tebal.
   * Ikon SVG buku terbuka.
   * Badge bintang emas di sudut kanan atas memuat jumlah kelas mahasiswa: `<i class="fa-solid fa-star"></i><span>${myClassCount}</span>`.
   * Label bawah bernuansa oranye tua (`bg-orange-950/90`, border `border-orange-500/40`).
4. **Pin Mitra Fotokopi & Percetakan (`createPhotocopyIcon`):**
   * Badge horizontal memanjang berwarna hijau zamrud (`bg-emerald-600`), border putih 2px, font ekstra tebal.
   * Ikon printer + teks nama toko (maksimal 16 karakter terpotong elipsis).
   * Panah segitiga lancip di bawah marker (rotasi 45 derajat).
5. **Pin Admin Draggable (`adminMarker` & `editIcon`):**
   * Pin tambah gedung baru: bulatan emas 40px draggable dengan animasi denyut `pulse 2s infinite`.
   * Pin edit posisi gedung: bulatan amber 38px draggable dengan animasi `editPulse 1.5s infinite`.
   * Tooltip permanen di atas pin menampilkan live koordinat saat digeser: `Lat: 3.565245 | Lng: 98.656520`.

### 3.3. Arsitektur Popup Spasial (Full-Bleed Card)
Popup diatur dengan CSS khusus (`resources/css/features/map.css`):
* Lebar paten: `width: 340px !important; max-width: 340px !important;`.
* Radius: `border-radius: 12px;` tanpa margin luar (*padding 0*).
* Struktur Bertingkat:
  1. **Sticky Header:** Kode gedung (badge warna), tag kelas saya, jumlah ruangan, nama gedung, deskripsi singkat, dan tombol CTA hijau "Petunjuk Arah (Google Maps)".
  2. **Scrollable Room Body:** Daftar kartu ruangan dengan tinggi maksimal `max-h-[260px]`.
  3. **Visual Room Card:** Menampilkan foto thumbnail kelas ukuran 80x80px (`w-20 h-20`), badge lantai, nama ruangan, dan daftar jadwal kelas di ruangan tersebut.

---

## 4. DAFTAR LENGKAP FITUR INTERAKTIF MAPS & EVENT FLOW

### 4.1. Katalog Fitur Interaktif Pengguna

| No | Nama Fitur | Pemicu (Trigger) | Komponen Terlibat | Hasil Akhir pada DOM / Peta |
|:---:|:---|:---|:---|:---|
| 1 | **Pencarian Semantik Instan** | Mengetik di `#map-search-input` (debounce 400ms) | `searchSchedules()` -> API `/api/schedules/search?q=...` | Marker gedung yang cocok disorot kuning, gedung lain dibuat transparan (opacity 0.4), peta auto-zoom via `fitBounds`, dan sidebar menampilkan daftar kartu jadwal. |
| 2 | **Filter Hari Perkuliahan** | Klik tombol hari (Senin s/d Sabtu) | Event click pada `#day-filters button` | Tombol hari aktif berubah hijau, request API dikirim dengan param `day=...`, marker dan sidebar tersaring sesuai hari. |
| 3 | **Riwayat Pencarian Cepat** | Klik salah satu chip di `#recent-searches-container` | `applyRecentSearch(keyword)` (localStorage) | Input pencarian terisi otomatis dan fungsi pencarian dieksekusi. |
| 4 | **Klik Marker Gedung** | Klik pin gedung pada kanvas peta | Event `marker.on('click')` | Popup gedung terbuka menampilkan detail seluruh ruangan dan jadwal yang ada di gedung tersebut. |
| 5 | **Smart Focus & Highlight Target** | Klik tombol "Lihat di Peta" pada kartu jadwal di sidebar ATAU klik tombol rekomendasi Rexa Copilot | `window.USUClassMap.focusBuildingWithTarget(...)` | Kamera peta meluncur (*flyTo*) ke koordinat gedung (zoom 18). Popup terbuka dengan **ruangan target dan jadwal target dipindahkan ke urutan teratas (index 0)** serta disorot dengan ring biru menyala dan badge "Hasil Pencarian". |
| 6 | **Multi-Mode Media Viewer** | Klik kartu ruangan pada popup gedung | `window.USUClassMap.openRoomDetailModal(room, building)` -> CustomEvent `open-room-detail-modal` | Modal Alpine.js terbuka dengan 3 tab: **Foto Ruangan**, **Panorama 360° Interaktif** (Pannellum), dan **Video POV Jalan Kaki**. |
| 7 | **Navigasi Rute Pejalan Kaki** | Klik tombol "Jalankan Rute Kelas" di footer modal atau popup | `startNavigation(lat, lng)` (Geolocation API) | Mengambil koordinat GPS user aktif via `navigator.geolocation.getCurrentPosition`, lalu membuka Google Maps Walking Navigation di tab baru (`travelmode=walking`). |
| 8 | **Bookmark Jadwal Kuliah** | Klik ikon pita bookmark (🔖) pada kartu jadwal di sidebar | `handleBookmarkToggle(schedule)` (localStorage) | Jadwal disimpan/dihapus dari `localStorage['usuclassmap_bookmarks']`, ikon berubah warna emas, dan event `usuclassmap:bookmarks-updated` disiarkan. |
| 9 | **Fokus Jadwal Kuliah Saya** | Klik tombol "Fokus" pada banner mahasiswa di sidebar | `window.USUClassMap.focusUserSchedules()` | Peta melakukan `fitBounds` ke seluruh gedung yang memiliki jadwal kuliah mahasiswa yang sedang login, lalu otomatis membuka popup gedung pertama. |
| 10 | **Layer Mitra Fotokopi Kampus** | Klik tombol "Mitra Fotokopi" di sidebar atau floating button di kanan atas peta | `window.USUClassMap.togglePhotocopyLayer()` | Mengambil 31 titik dari `/api/copy-shops`, merender marker khusus fotokopi, dan melakukan auto-fitBounds. Klik marker menampilkan jam buka, rating, link order, dan chat WhatsApp. |
| 11 | **Admin: Geser Pin Gedung Baru** | Drag marker admin emas pada dashboard admin | Event `adminMarker.on('dragend')` | Mengirim event `coordinate-updated` ke Alpine.js, otomatis mengisi input latitude & longitude pada form tambah gedung. |
| 12 | **Admin: Edit Posisi Spasial Gedung** | Klik tombol "Pilih Konteks" pada popup gedung admin | `enterEditMode(buildingData, marker)` | Pin gedung berubah warna amber dengan animasi denyut dan menjadi `draggable`. Saat digeser, form edit gedung menerima koordinat baru secara real-time. |

### 4.2. Diagram Alur Kerja (Event Flow) Fitur Kritis

#### Alur 1: Smart Focus dari Pencarian Sidebar ke Peta
```
[User Input Kata Kunci / Klik Hari]
          │
          ▼
   Debounce 400ms ──► fetch('/api/schedules/search?q=...')
          │
          ▼
  Render Search Markers & Sidebar Cards
          │
          ▼
 [User Klik Tombol "Lihat di Peta" pada Jadwal]
          │
          ▼
  window.USUClassMap.focusBuildingWithTarget(
      gedungId, gedungNama, lat, lng,
      ruanganId, ruanganNama,
      scheduleId, kodeMk, namaMk
  )
          │
          ├─────────────────────────────────────────────────┐
          ▼                                                 ▼
map.flyTo([lat, lng], 18, {duration: 0.8})     generateBuildingPopupContent(...)
          │                                                 │
          ▼                                                 ▼
Buka Marker Popup                              • Target Room dipindah ke Index 0
          │                                    • Border Biru + Ring Menyala
          ▼                                    • Target Jadwal dipindah ke Index 0
Auto-scroll halus ke elemen                    • Badge "Pilihan Anda" menyala
.target-room-highlight
```

#### Alur 2: Integrasi AI Copilot (Rexa) ke Peta
```
[Mahasiswa Bertanya ke Chatbot Rexa]
"Dimana ruangan kelas Rekayasa Perangkat Lunak?"
          │
          ▼
POST /copilot/chat ──► AiCopilotService ──► SPARQL Query
          │
          ▼
Respon JSON memuat:
{
  "message": "Kelas RPL berada di Gedung Fasilkom-TI Ruang 204...",
  "mapAction": {
      "building_id": 4,
      "building_name": "Gedung Fasilkom-TI",
      "lat": 3.564812,
      "lng": 98.657124,
      "room_id": 12,
      "room_name": "Lab 204",
      "schedule_id": 88
  }
}
          │
          ▼
Komponen Chatbot me-render tombol:
"📍 Lihat di Peta: Gedung Fasilkom-TI"
          │
          ▼
[User Mengeklik Tombol Rekomendasi Bot]
          │
          ▼
copilot.js: triggerMapAction(action)
          │
          ▼
Memanggil window.USUClassMap.focusBuildingWithTarget(...)
(Peta langsung bermanuver dan menyorot Ruang 204 secara otomatis)
```

---

## 5. KONTRAK DATA & ALUR BACKEND (SPARQL/FUSEKI, SQLITE/MYSQL)

### 5.1. Endpoint API & Skema Respons JSON

#### 1. `GET /api/buildings`
* **Controller:** `App\Http\Controllers\Api\BuildingApiController@index`
* **Query Params:** Tidak ada (mengambil seluruh gedung aktif).
* **Struktur JSON Payload:**
```json
{
  "status": "success",
  "count": 18,
  "data": [
    {
      "id": 1,
      "name": "Gedung A Fakultas Ilmu Budaya",
      "code": "FIB-A",
      "faculty": "Fakultas Ilmu Budaya",
      "category": "Akademik",
      "latitude": 3.565821,
      "longitude": 98.656912,
      "description": "Gedung perkuliahan umum FIB",
      "floor_count": 3,
      "rooms_count": 8,
      "rooms": [
        {
          "id": 101,
          "name": "Ruang 101",
          "floor": 1,
          "capacity": 40,
          "photo_360": "http://127.0.0.1:8000/storage/foto360/101.jpg",
          "schedules": [
            {
              "id": 501,
              "is_my_class": false,
              "mata_kuliah": {
                "id": 12,
                "kode": "UNI101",
                "nama": "Pendidikan Kewarganegaraan",
                "sks": 2
              },
              "dosen": {
                "id": 4,
                "nama": "Dra. Siti Aminah, M.Hum"
              },
              "hari": "Senin",
              "jam_mulai": "08:00",
              "jam_selesai": "09:40",
              "semester": "1",
              "kelas": "A",
              "kelompok_kelas": "A"
            }
          ]
        }
      ]
    }
  ]
}
```

#### 2. `GET /api/schedules/search`
* **Controller:** `App\Http\Controllers\Api\ScheduleApiController@search` -> `SparqlService`
* **Query Params:**
  * `q` atau `keyword`: teks pencarian (nama matkul, kode matkul, nama dosen, ruangan, atau nama gedung).
  * `day`: filter hari (`Senin`, `Selasa`, `Rabu`, `Kamis`, `Jumat`, `Sabtu`).
* **Struktur JSON Payload:**
```json
{
  "status": "success",
  "source": "SPARQL (Apache Jena Fuseki)", // atau "MySQL (Fallback)"
  "count": 5,
  "data": [
    {
      "uri": "http://usuclassmap.usu.ac.id/ontology#Jadwal_501",
      "id": 501,
      "hari": "Senin",
      "jam_mulai": "08:00",
      "jam_selesai": "09:40",
      "semester": "1",
      "kelompok_kelas": "A",
      "mata_kuliah": {
        "kode": "UNI101",
        "nama": "Pendidikan Kewarganegaraan",
        "sks": 2,
        "tipe": "MKWU"
      },
      "dosen": {
        "nama": "Dra. Siti Aminah, M.Hum",
        "nip": "197508122002122001",
        "email": "sitiaminah@usu.ac.id"
      },
      "ruangan": {
        "nama": "Ruang 101",
        "lantai": 1,
        "kapasitas": 40,
        "foto_360": "http://127.0.0.1:8000/storage/foto360/101.jpg",
        "foto_360_raw": "foto360/101.jpg"
      },
      "gedung": {
        "nama": "Gedung A Fakultas Ilmu Budaya",
        "kode": "FIB-A",
        "latitude": 3.565821,
        "longitude": 98.656912
      }
    }
  ]
}
```

#### 3. `GET /api/my-schedules`
* **Controller:** `App\Http\Controllers\ScheduleController@mySchedules`
* **Autentikasi:** Wajib login (`auth` middleware).
* **Fungsi:** Mengembalikan jadwal kampus dengan pencocokan semester mahasiswa aktif untuk menandai `is_my_class = true`.

#### 4. `GET /api/copy-shops`
* **Controller:** `App\Http\Controllers\Api\CopyShopApiController@index`
* **Transformer:** `App\Http\Resources\CopyShopResource`
* **Query Params:** `per_page=50`.
* **Struktur Payload JSON:**
  * `id`: ID integer mitra fotokopi
  * `name`, `slug`, `description`: Info dasar toko
  * `location`: Objek bersarang `{ name: location_name, latitude: float, longitude: float }` *(Catatan kritis: Frontend wajib membaca `place.location?.latitude ?? place.latitude`)*
  * `status`: Objek bersarang `{ is_open_now: bool, schedule_status_text, operating_days, open_time, close_time }`
  * `rating`: Objek bersarang `{ score: float, user_rating_count: int, review_summary }` *(Catatan: Nilai rating skalar diambil dari `place.rating?.score`)*
  * `contacts`: Objek bersarang `{ phone, whatsapp, whatsapp_url, ... }`
  * `services`, `catalog_items`, `reviews`: Relasi bila di-load.

### 5.2. Konfigurasi Blade ke JS (`ADMIN_MAP_CONFIG`)
Pada dashboard admin spasial (`features/admin/map.blade.php`), data di-pass langsung dari Blade ke JavaScript melalui window objek:
```javascript
window.ADMIN_MAP_CONFIG = {
    csrfToken: '{{ csrf_token() }}',
    buildings: @json($buildings),
    courses: @json($courses),
    lecturers: @json($lecturers),
    endpoints: {
        building: '{{ url("/admin-sementara-/map/building") }}',
        updateBuilding: '{{ url("/admin-sementara-/map/building") }}',
        room: '{{ url("/admin-sementara-/map/room") }}',
        updateRoom: '{{ url("/admin-sementara-/map/room") }}',
        schedule: '{{ url("/admin-sementara-/map/schedule") }}',
        fetchRooms: '{{ url("/admin-sementara-/map/rooms") }}',
        roomMedia: '{{ url("/admin-sementara-/map/rooms") }}',
        updateMetadata: '{{ url("/admin-sementara-/map/media") }}',
        deleteMedia: '{{ url("/admin-sementara-/map/media") }}'
    }
};
```

---

## 6. INTEGRASI LINTAS FITUR & GLOBAL JAVASCRIPT CONTRACT (KRITIS)

Bagian ini adalah **area paling sensitif**. Jika properti, nama method, atau tanda tangan fungsi berikut diubah sembarangan saat migrasi ke Mapbox GL JS, maka **fitur Chatbot AI Rexa, Halaman Beranda, dan Halaman Bookmark akan langsung rusak (broken)**.

### 6.1. Objek Publik Global `window.USUClassMap`

Didefinisikan di `resources/js/features/map.js` (baris 1218 - 1396):

```javascript
window.USUClassMap = {
    // 1. Membuka modal detail ruangan & tur multimedia
    openRoomDetailModal: function(room, building, defaultMode = 'photo'),

    // 2. Kompatibilitas mundur untuk modal multimedia langsung
    openMultimediaModal: function(type, src, title),

    // 3. Menghidupkan/mematikan layer 31 titik mitra fotokopi
    togglePhotocopyLayer: async function(),

    // 4. [SANGAT KRITIS] Smart Focus & Highlight Gedung, Ruangan, dan Jadwal
    focusBuildingWithTarget: function(
        buildingId,        // integer | null
        buildingName,      // string | null
        lat,               // float | null
        lng,               // float | null
        targetRoomId,      // integer | null
        targetRoomName,    // string | null
        targetScheduleId,  // integer | null
        targetCourseCode,  // string | null
        targetCourseName   // string | null
    ),

    // 5. Fokus ke gedung tertentu berdasarkan ID
    focusBuilding: function(buildingId),

    // 6. Fokus gedung berdasarkan koordinat GPS
    focusBuildingByCoordinates: function(lat, lng, buildingName, targetRoomId, targetRoomName, targetScheduleId, targetCourseCode, targetCourseName),

    // 7. Auto-fitBounds ke seluruh gedung perkuliahan milik user yang sedang login
    focusUserSchedules: function(),

    // 8. Menjalankan filter pencarian berdasarkan kata kunci riwayat
    applyRecentSearch: function(keyword),

    // 9. Menghapus seluruh riwayat pencarian di localStorage
    clearAllRecentSearches: function(),

    // 10. Reset semua filter pencarian dan hari ke kondisi default
    resetFilters: function(),

    // 11. Toggle simpan/hapus bookmark jadwal ke localStorage
    handleBookmarkToggle: function(schedule, buttonElement)
};
```

### 6.2. Custom DOM Events Bus

| Nama Event | Tipe Dispatcher | Pendengar (Listener) | Detail Payload (`e.detail`) | Kegunaan |
|:---|:---|:---|:---|:---|
| `open-room-detail-modal` | `map.js` | `features/map/index.blade.php`<br>(Alpine modal) | `{ room: {...}, building: {...}, defaultMode: 'photo'\|'360'\|'video' }` | Membuka jendela dialog pop-up multi-mode media dan tur 360°. |
| `usuclassmap:bookmarks-updated` | `map.js` | Navbar / Bookmark counters | `{ count: totalBookmarks }` | Menyiarkan pembaruan jumlah jadwal yang disimpan agar badge di navbar berubah secara reaktif. |
| `edit-building` | `admin/map.js` | `features/admin/map.blade.php`<br>(Alpine form) | `{ id, name, code, faculty, latitude, longitude, ... }` | Memasukkan form admin ke dalam konteks edit gedung tertentu setelah marker diklik. |
| `coordinate-updated` | `admin/map.js` | `features/admin/map.blade.php`<br>(Alpine form) | `{ lat: "-3.565...", lng: "98.656..." }` | Mengirim pembaruan koordinat GPS saat marker draggable admin digeser di peta. |
| `cancel-edit-building` | Alpine form | `admin/map.js` | Kosong | Memberitahu peta untuk membatalkan mode drag edit dan mengembalikan pin ke koordinat semula. |
| `building-created-on-map` | Alpine form | `admin/map.js` | Objek Gedung Baru | Menambahkan marker baru ke peta admin seketika setelah gedung berhasil disimpan via AJAX. |
| `building-updated-on-map` | Alpine form | `admin/map.js` | Objek Gedung Terupdate | Memperbarui koordinat data marker pada peta admin setelah sukses diedit. |

### 6.3. Integrasi URL Query Strings
Halaman `/map` mengenali parameter URL query string berikut saat halaman pertama kali dimuat (`handleInitialUrlParams()`):
* `?q=...` atau `?keyword=...` : Mengisi input pencarian dan langsung mengeksekusi pencarian otomatis (digunakan oleh Halaman Bookmark dan tombol pencarian di Beranda).
* `?day=...` : Mengaktifkan filter hari otomatis (`Senin` s/d `Sabtu`).

---

## 7. ANALISIS RISIKO MIGRASI (LEAFLET 2D KE MAPBOX GL JS 3D)

Migrasi dari Leaflet 2D (DOM/SVG raster tiles) ke Mapbox GL JS (WebGL vector canvas) merupakan lompatan teknologi besar. Berikut adalah matriks risiko arsitektural dan potensi kegagalan (*breaking changes*) yang wajib diantisipasi:

```
+-----------------------------------------------------------------------------------+
|                        PERBEDAAN PARADIGMA ARSITEKTUR                             |
+-----------------------------------------------------------------------------------+
| LEAFLET 2D (Current)                 | MAPBOX GL JS 3D (Target)                   |
|--------------------------------------|--------------------------------------------|
| 1. Format Koordinat: [Lat, Lng]      | 1. Format Koordinat: [Lng, Lat] (TERBALIK) |
| 2. Marker: L.divIcon (DOM HTML)      | 2. Marker: mapboxgl.Marker (DOM Element)   |
| 3. Popup: L.popup (Attached to DOM)  | 3. Popup: mapboxgl.Popup (Canvas Anchor)   |
| 4. Rendering: CPU/DOM Raster Layers  | 4. Rendering: GPU WebGL Vector Tiles       |
| 5. Camera: setView, flyTo [lat, lng] | 5. Camera: flyTo({center:[lng,lat], pitch})|
| 6. Lisensi/Token: Open, No Token     | 6. Lisensi/Token: Wajib Mapbox API Token   |
+-----------------------------------------------------------------------------------+
```

### 7.1. Analisis Risiko Detail

#### 🔴 Risiko 1: Inversi Koordinat Sumbu X dan Y (Latitude vs Longitude) — TINGKAT RISIKO: EKSTREM
* **Masalah:** Leaflet menggunakan urutan `[Latitude, Longitude]` (contoh: `[3.565245, 98.656520]`). Sebaliknya, Mapbox GL JS mengikuti format GeoJSON standar yaitu `[Longitude, Latitude]` (`[98.656520, 3.565245]`).
* **Dampak Kegagalan:** Jika koordinat tidak dibalik secara sistematis pada semua panggilan fungsi, marker akan terlempar ke Somalia/Samudra Hindia dan kanvas peta akan blank putih/biru.
* **Titik Terdampak:** `DEFAULT_CENTER`, `flyTo`, `setCenter`, `fitBounds`, `L.marker()`, pemanggilan dari `copilot.js` (`action.lat`, `action.lng`), dan form admin.

#### 🔴 Risiko 2: Kerusakan Kontrak Global `window.USUClassMap` — TINGKAT RISIKO: TINGGI
* **Masalah:** Rexa Chatbot (`copilot.js`) dan template Blade memanggil `window.USUClassMap.focusBuildingWithTarget(...)`.
* **Dampak Kegagalan:** Jika engine diganti namun method ini lupa diekspos dengan nama dan parameter yang persis sama, fitur chatbot AI akan *crash* dengan error `TypeError: window.USUClassMap.focusBuildingWithTarget is not a function`.

#### 🔴 Risiko 3: Kerusakan Styling Custom Popup 340px — TINGKAT RISIKO: TINGGI
* **Masalah:** Styling popup di `resources/css/features/map.css` ditulis khusus untuk kelas Leaflet (`.leaflet-popup-content-wrapper`, `.leaflet-popup-content`, `.leaflet-popup-tip`).
* **Dampak Kegagalan:** Mapbox GL menggunakan kelas CSS `.mapboxgl-popup`, `.mapboxgl-popup-content`, `.mapboxgl-popup-tip`. Jika CSS tidak dialihkan, popup akan berantakan, teks jadwal meluap keluar layar, dan thumbnail kelas rusak.

#### 🟡 Risiko 4: Kerugian Memori WebGL (*Context Loss*) — TINGKAT RISIKO: SEDANG
* **Masalah:** Leaflet sangat ringan pada memori DOM. Mapbox GL menginisialisasi konteks WebGL hardware acceleration.
* **Dampak Kegagalan:** Jika peta di-mount dan di-unmount berulang kali (misal saat tab admin diganti atau pada single-page workflow) tanpa memanggil `map.remove()`, browser mobile akan mengalami crash memori (*WebGL Context Lost*).

#### 🟡 Risiko 5: Dependensi Draggable Marker pada Admin Dashboard — TINGKAT RISIKO: SEDANG
* **Masalah:** Di Leaflet, marker admin menggunakan `marker.dragging.enable()` dan event `dragstart`, `drag`, `dragend`.
* **Dampak Kegagalan:** Pada Mapbox GL, API marker draggable menggunakan `marker.setDraggable(true)` dan event `drag`, `dragend` mengembalikan `marker.getLngLat()` (bukan `e.latlng`). Format event listener harus disesuaikan agar integrasi drag-and-drop koordinat admin tetap berjalan mulus.

#### 🟡 Risiko 6: Penyediaan Token Mapbox & Kuota Request — TINGKAT RISIKO: SEDANG
* **Masalah:** Leaflet menggunakan OpenStreetMap gratis tanpa kredensial. Mapbox GL JS membutuhkan `mapboxgl.accessToken`.
* **Dampak Kegagalan:** Jika token belum disetel di `.env` (misal `VITE_MAPBOX_TOKEN`) atau habis kuota, peta tidak akan merender tile apa pun. Wajib ada mekanisme graceful fallback atau peringatan visual yang jelas.

---

## 8. CHECKLIST ANTI-REGRESI & KRITERIA PENERIMAAN (ACCEPTANCE CRITERIA)

Daftar periksa ini **wajib digunakan sebagai panduan verifikasi** selama dan setelah proses migrasi ke Mapbox GL JS 3D dilakukan. Seluruh item harus berstatus centang `[x]` sebelum fitur dinyatakan siap rilis (*production-ready*).

### A. Inisialisasi Peta & Konfigurasi Dasar
- [ ] Peta berhasil diinisialisasi pada elemen `#map` (publik) dan `#admin-map` (admin) tanpa error WebGL di Console browser.
- [ ] Titik pusat awal peta mengarah tepat ke Kampus USU Padang Bulan (`Longitude: 98.656520, Latitude: 3.565245`).
- [ ] Fitur 3D Terrain & Building Extrusion (gedung 3D) dapat dimuat dengan pitch sudut miring tanpa lag.
- [ ] Kontrol navigasi zoom in/out dan tombol kompas rotasi 3D berfungsi normal di pojok kanan atas.
- [ ] Token Mapbox dikonfigurasi secara aman melalui variabel environment Laravel / Vite (`.env`).

### B. Pin Marker & Legenda Peta
- [ ] Marker Gedung Normal (hijau `#10A760`) tampil di seluruh koordinat gedung yang terdaftar di database.
- [ ] Marker Jadwal Kuliah Saya (oranye `#EA580C` dengan lencana bintang emas dan jumlah kelas) tampil benar saat user login.
- [ ] Marker Gedung Hasil Pencarian berubah warna menjadi kuning/emas (`#D4A017`) dengan animasi radar berdenyut.
- [ ] Gedung yang tidak cocok dengan filter pencarian otomatis diturunkan opasitasnya (transparan) atau disembunyikan.
- [ ] Tooltip kode gedung tetap terbaca jelas di atas atau di bawah pin marker.

### C. Popup Spasial & Smart Room Cards
- [ ] Mengklik marker gedung membuka popup responsif selebar 340px dengan header gedung dan daftar ruangan.
- [ ] Thumbnail foto kelas (w-20 h-20) ter-render sempurna tanpa distorsi rasio aspek.
- [ ] Tag badge lantai ruangan (`Lt. 1`, `Lt. 2`) dan kapasitas ruangan tampil akurat.
- [ ] Jadwal kelas di dalam ruangan menampilkan nama matkul, jam kuliah, nama dosen, dan kelompok kelas.
- [ ] Tombol "Petunjuk Arah (Google Maps)" pada popup membuka navigasi pejalan kaki Google Maps dengan koordinat yang valid.

### D. Fitur Smart Focus & Highlight Target (Kritis)
- [ ] Menekan tombol "Lihat di Peta" pada kartu jadwal di sidebar memicu `flyTo` halus ke gedung target (zoom level 18).
- [ ] Ruangan yang dicari otomatis berpindah ke **posisi paling atas (index 0)** di dalam scrollable popup gedung.
- [ ] Kartu ruangan target mendapatkan highlight visual: border biru tebal, ring biru menyala, dan lencana `"fa-bullseye Hasil Pencarian"`.
- [ ] Jadwal kelas yang dipilih mendapatkan lencana `"Pilihan Anda"` berkedip (*animate-pulse*).
- [ ] Area popup otomatis melakukan scroll halus (*smooth auto-scroll*) agar ruangan target langsung terlihat tanpa harus digeser manual oleh user.

### E. Integrasi Chatbot AI (Rexa Copilot)
- [ ] Menanyakan lokasi gedung/ruangan ke Rexa Copilot menghasilkan tombol rekomendasi `"📍 Lihat di Peta: [Nama Gedung]"`.
- [ ] Mengeklik tombol rekomendasi di drawer chat memicu fungsi `window.USUClassMap.focusBuildingWithTarget(...)` tanpa melempar error di console.
- [ ] Pada layar ponsel (mobile viewport < 768px), drawer chat otomatis menutup saat peta bermanuver agar peta terlihat leluasa.

### F. Multi-Mode Media Modal (Foto, 360°, Video)
- [ ] Mengeklik kartu ruangan pada popup gedung membuka modal dialog Alpine.js secara mulus.
- [ ] Tab **Foto** menampilkan gambar resolusi tinggi ruangan kelas.
- [ ] Tab **360°** menginisialisasi viewer Pannellum secara interaktif (dapat diputar dan di-zoom).
- [ ] Tab **Video** memutar video panduan jalan kaki POV ruangan dan otomatis berhenti (*pause*) saat modal ditutup.
- [ ] Tombol CTA "Jalankan Rute Kelas (Google Maps Walk Navigation)" di footer modal mengambil GPS user dan membuka rute navigasi.

### G. Layer Mitra Fotokopi & Percetakan
- [ ] Menekan tombol toggle "Mitra Fotokopi" menampilkan 31 pin khusus fotokopi berwarna hijau zamrud.
- [ ] Kanvas peta otomatis melakukan `fitBounds` mencakup seluruh titik fotokopi yang tampil.
- [ ] Klik pada pin fotokopi membuka popup berisi status buka/tutup, rating bintang, tombol order, dan tombol WhatsApp.
- [ ] Menekan tombol toggle kembali menghapus seluruh marker fotokopi dari kanvas peta secara bersih.

### H. Sistem Bookmark & Riwayat Pencarian
- [ ] Menandai jadwal pada sidebar menyimpan data ke `localStorage['usuclassmap_bookmarks']`.
- [ ] Halaman Beranda (`/#bookmarks`) dan `/bookmarks` menampilkan tabel jadwal tersimpan secara reaktif.
- [ ] Tombol "Buka di Peta" pada tabel bookmark membuka halaman `/map?q={kodeMk}` dan langsung memfilter jadwal tersebut.
- [ ] Riwayat pencarian tersimpan maksimal 5 kata kunci terakhir dan dapat dihapus atau diklik kembali.

### I. Dashboard Spasial Admin (`/admin-sementara-/map`)
- [ ] Seluruh gedung tersimpan muncul dengan marker hijau di peta admin.
- [ ] Marker admin emas dapat di-drag secara mulus, dan koordinat form latitude/longitude berubah secara real-time.
- [ ] Klik "Pilih Konteks" pada gedung mengubah pin menjadi mode edit amber berdenyut dan memungkinkan penggeseran posisi GPS gedung.
- [ ] Menyimpan gedung baru atau memperbarui posisi gedung mengirim request AJAX sukses dan mengembalikan marker ke kondisi normal.
- [ ] Toast notifikasi admin (hijau/merah) muncul dengan transisi mulus di pojok kanan atas.

---

## 9. REKOMENDASI TEKNIS PELAKSANAAN MIGRASI

1. **Jaga Lapisan Abstraksi (Adapter Pattern):**
   Buat file `resources/js/features/map-mapbox.js` sebagai implementasi baru, tetapi **pertahankan wrapper API `window.USUClassMap`**. Dengan cara ini, kode di `copilot.js`, `welcome.blade.php`, dan `public/bookmarks.blade.php` tidak memerlukan perubahan sama sekali.
2. **Buat Helper Konversi Koordinat:**
   Sediakan fungsi utilitas global:
   ```javascript
   const toMapboxCoord = (lat, lng) => [parseFloat(lng), parseFloat(lat)];
   const toLeafletCoord = (lng, lat) => [parseFloat(lat), parseFloat(lng)];
   ```
   Gunakan helper ini pada setiap pintu masuk dan keluar data untuk mengeliminasi risiko tertukarnya sumbu koordinat.
3. **Migrasi CSS Popup:**
   Duplikasi deklarasi di `resources/css/features/map.css` dengan menambahkan selektor Mapbox:
   ```css
   .custom-popup .leaflet-popup-content-wrapper,
   .custom-popup .mapboxgl-popup-content {
       border-radius: 12px !important;
       padding: 0 !important;
       /* ... */
   }
   ```
4. **Pembersihan Bersih (Garbage Collection):**
   Pastikan memanggil `map.remove()` jika elemen kanvas dihancurkan untuk mencegah kebocoran memori WebGL.

---
*Laporan audit ini disusun sebagai dokumen acuan resmi (Single Source of Truth) sebelum pelaksanaan migrasi engine spasial USUClassMap.*
