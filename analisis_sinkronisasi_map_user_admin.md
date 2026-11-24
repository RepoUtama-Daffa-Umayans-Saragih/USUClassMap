# ANALISIS MENDALAM: SINKRONISASI TOTAL PETA ADMIN DENGAN PETA USER
**Proyek:** USUClassMap (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)  
**Penulis:** Principal Full-Stack Laravel & Geospatial Mapbox Engineer  
**Tanggal:** 23 November 2026  
**Prinsip Utama:**
1. **Map User Adalah Panduan Utama:** Standar engine peta Mapbox GL JS 3D, kontrol navigasi spasial, layer Google Satellite Hybrid (`lyrs=y`), mode 3D extrusi, reset kamera kampus, layer 31 mitra fotokopi, anti-crash WebGL singleton guard, dan fallback UI wajib disinkronkan ke Map Admin.
2. **Zero Feature Loss (Anti-Regresi):** Semua fitur khusus Admin (draggable marker gedung baru, draggable edit GPS gedung, CRUD gedung/ruangan/jadwal, manajemen multimedia 360/video POV, event-driven Alpine.js) 100% dikunci dan dipertahankan.

---

## BAGIAN 1: INVENTARIS ARSITEKTUR FILE (USER VS ADMIN)

### 1.1 Berkas Map User (Panduan)
- **Tampilan (Blade Views):**
  - `USUClassMap/resources/views/features/map/index.blade.php`: Tampilan publik aktif rute `/map`, memuat container `#map`, HUD spatial controls (Mode 3D, Satelit, Reset Kamera, Fotokopi 31), dan `@vite(['resources/js/features/map.js'])`.
  - `USUClassMap/resources/views/map/index.blade.php`: Salinan cadangan tersinkronisasi.
- **Frontend Engine (JavaScript):**
  - `USUClassMap/resources/js/features/map.js`: Logika utama Mapbox GL JS v3, Google Satellite Hybrid layer, 3D buildings extrusion, 31 marker mitra fotokopi, singleton WebGL guard, dan error fallback card.
  - `USUClassMap/resources/js/map.js`: Salinan root tersinkronisasi.
- **Backend & API:**
  - `USUClassMap/routes/web.php`: Route `/map` mengarah ke `features.map.index`.
  - `USUClassMap/routes/api.php`: Menyediakan endpoint `/api/buildings`, `/api/schedules/search`, `/api/copy-shops`, `/api/sparql/*`.
  - `USUClassMap/app/Http/Controllers/Api/BuildingApiController.php`: Query GeoJSON / pin gedung kampus.
  - `USUClassMap/app/Http/Controllers/Api/CopyShopApiController.php`: Query data 31 mitra fotokopi USU.
  - `USUClassMap/app/Http/Resources/CopyShopResource.php`: Transformator data fotokopi (status buka, rating, koordinat GPS, link WA/pesan).

### 1.2 Berkas Map Admin
- **Tampilan (Blade Views):**
  - `USUClassMap/resources/views/features/admin/map.blade.php`: Tampilan spasial admin aktif, dirender oleh `AdminMapController::index()`. Mengandung Alpine.js dashboard, form interaktif sidebar, container `#admin-map`, dan modal multimedia.
  - `USUClassMap/resources/views/admin/map.blade.php`: Salinan legacy admin map.
- **Frontend Engine (JavaScript):**
  - `USUClassMap/resources/js/features/admin/map.js`: Entry point Vite untuk Mapbox GL JS interaksi admin (draggable admin marker, edit marker, event listener Alpine).
  - `USUClassMap/public/js/admin-map.js`: File statis di folder `public/js/` yang sebelumnya dimuat oleh template legacy.
- **Backend & API:**
  - `USUClassMap/routes/web.php`: Grup rute prefix `admin-sementara-/map` dengan controller `AdminMapController`.
  - `USUClassMap/app/Http/Controllers/AdminMapController.php`: Menangani CRUD gedung, ruangan, jadwal, dan multimedia hotspot 360/video.

---

## BAGIAN 2: MATRIKS PERBANDINGAN FITUR (USER VS ADMIN)

| No | Fitur / Standar Spasial | Map User (Panduan) | Kondisi Map Admin Saat Ini | Status Kesenjangan | Aksi Sinkronisasi Tahap 2 |
|---|---|---|---|---|---|
| 1 | **Mapbox GL JS Version** | v3.9.0 (CSS & JS) | v3.9.0 | **Sudah Sama** | Pertahankan versi v3.9.0. |
| 2 | **Koordinat Awal & Kamera** | `[98.656520, 3.565245]`, Zoom 16, Pitch 50, Bearing -15 | `[98.656520, 3.565245]`, Zoom 16, Pitch 50, Bearing -15 | **Sudah Sama** | Pertahankan konfigurasi koordinat kampus Padang Bulan. |
| 3 | **Style Peta Dasar** | `mapbox://styles/mapbox/streets-v12` | `mapbox://styles/mapbox/streets-v12` | **Sudah Sama** | Tetap jadikan base vector style tunggal. |
| 4 | **Floating Spatial HUD** | Ada di kanan atas: Mode 3D, Satelit, Reset Kamera, Fotokopi (31) | **Belum Ada** (Hanya zoom & compass navigasi) | **Belum Ada** | Tambahkan HUD kanan atas dengan styling Huawei C.O.R.E. identik ke Blade admin. |
| 5 | **Toggle Mode Satelit** | Google Satellite Hybrid (`mt0-mt3...lyrs=y`), sembunyikan 3D extrusi saat aktif, tanpa reload style/context WebGL | **Belum Ada** | **Belum Ada** | Terapkan `injectSatelliteLayer()` dan `toggleSatelliteMode()` pada admin map. |
| 6 | **Toggle Mode 3D / 2D** | Beralih antara pitch 50 / bearing -15 dan pitch 0 / bearing 0, toggle layer `3d-buildings` | Layer `3d-buildings` di-load statis tanpa opsi toggle | **Berbeda** | Tambahkan fungsi `togglePerspective()` pada admin map. |
| 7 | **Tombol Reset Kamera** | `flyTo` halus mengembalikan pandangan ke center USU (pitch 50, bearing -15, zoom 16) | **Belum Ada** | **Belum Ada** | Tambahkan fungsi `resetCampusView()` pada admin map. |
| 8 | **Layer Mitra Fotokopi (31)** | Menampilkan 31 pin fotokopi emerald, rating, jam buka, link WA, dan toggle show/hide | **Belum Ada** di peta admin | **Belum Ada** | Integrasikan fetch `/api/copy-shops` dan layer marker fotokopi di admin map. |
| 9 | **Pencegahan WebGL Crash** | `failIfMajorPerformanceCaveat: false`, Singleton cleanup `window.__usuMapInstance`, `beforeunload` listener | Belum ada cleanup WebGL, rentan error `Failed to initialize WebGL` saat refresh | **Bug/Rentan** | Terapkan singleton guard dan event `beforeunload` yang sama persis. |
| 10 | **Fallback UI WebGL** | Kartu UI elegan + tombol "Muat Ulang Peta" (`retryInitMap`) & Refresh | Blank/kosong saat inisialisasi WebGL gagal | **Belum Ada** | Terapkan `renderFallbackMapError()` ke elemen `#admin-map`. |
| 11 | **Token Access .env** | `VITE_MAPBOX_ACCESS_TOKEN` via `window.MAPBOX_ACCESS_TOKEN`, no hardcoded tokens | `VITE_MAPBOX_ACCESS_TOKEN` via `window.ADMIN_MAP_CONFIG.mapboxToken`, no hardcoded tokens | **Sudah Aman** | Pertahankan pola passing dari Blade config. |
| 12 | **Duplikasi Script Bundle** | Hanya memuat Vite bundle `resources/js/features/map.js` | `features/admin/map.blade.php` memuat Vite bundle, tetapi `admin/map.blade.php` masih memuat `/js/admin-map.js` | **Inkonsistensi** | Sinkronkan kedua blade admin dan salin hasil sinkronisasi ke `public/js/admin-map.js`. |

---

## BAGIAN 3: DAFTAR FITUR WAJIB PERTAHANKAN (ADMIN & USER)

### 3.1 Fitur Admin yang Wajib Dikunci (Dilarang Hilang / Rusak)
1. **Marker Tambah Gedung Baru (Gold Draggable Pin `#D4A017`):**
   - Muncul otomatis di center peta saat tab "Gedung" aktif.
   - Mengirim event `coordinate-updated` saat `dragend` untuk mengisi input `latitude` dan `longitude` di form penambahan gedung.
2. **Marker Edit Posisi Gedung (Amber Draggable Pin `#f59e0b`):**
   - Menggantikan pin gedung biasa saat admin memilih gedung untuk diedit atau menekan "🎯 Pilih Konteks" pada popup marker.
   - Dapat digeser untuk memindahkan lokasi GPS gedung di database.
   - Mendengarkan event Alpine `cancel-edit-building` untuk mengembalikan posisi jika dibatalkan.
   - Mendengarkan event Alpine `building-updated-on-map` untuk menyimpan koordinat baru secara visual.
3. **Marker Gedung Kampus Eksisting (Hijau Emerald `#10A760`):**
   - Menampilkan popup informasi: Nama Gedung, Kode Gedung, Fakultas, dan Tombol Interaktif "🎯 Pilih Konteks".
   - Event klik marker memicu pemilihan konteks gedung di sidebar kiri.
4. **Form & Tab Sidebar Kiri (Alpine.js `adminMapDashboard()`):**
   - Tab 1: **Gedung** (Form Tambah & Edit Gedung).
   - Tab 2: **Ruangan** (Form Tambah Ruangan berdasarkan gedung yang dipilih).
   - Tab 3: **Jadwal** (Form Tambah Jadwal kuliah, mendukung pemilihan matkul eksisting atau input matkul baru).
   - Tab 4: **Multimedia** (Upload foto ruangan, foto 360° interaktif Pannellum, video walkthrough POV, dan kalibrasi hotspot).
5. **Toast Notifications:**
   - Helper global `window.showAdminToast(message, type)` untuk umpan balik instan operasi admin.

### 3.2 Fitur User yang Wajib Dikunci
- Halaman publik `/map` tidak boleh diubah atau diregresi karena sudah berjalan stabil dan lolos audit WebGL sebelumnya.

---

## BAGIAN 4: ANALISIS KESENJANGAN BACKEND & PAYLOAD DATA

### 4.1 Sinkronisasi Payload `CopyShopResource`
- **Kondisi:** Pada `CopyShopResource.php`, koordinat latitude dan longitude dibungkus di dalam array `'location' => ['latitude' => ..., 'longitude' => ...]`.
- **Perbaikan:** Menambahkan field `latitude` dan `longitude` bertipe `(float)` pada root JSON selain di dalam array `location`. Hal ini menjamin kompabilitas ganda untuk frontend yang mengakses `place.latitude` secara langsung maupun yang mengakses `place.location.latitude`.

### 4.2 Endpoint Data Fotokopi untuk Admin
- Pada `window.ADMIN_MAP_CONFIG.endpoints`:
  Tambahkan `copyShops: '{{ route("api.copy-shops.index") }}'` agar modul peta admin dapat mengambil data 31 mitra fotokopi secara otomatis tanpa hardcoded path.

### 4.3 Pembersihan & Keamanan Token
- Memastikan tidak ada token rahasia Mapbox (`sk.eyJ`) atau public token (`pk.eyJ`) yang ditulis secara statis di file template maupun file script. Nilai token tetap bersumber murni dari environment `.env` (`VITE_MAPBOX_ACCESS_TOKEN`).

---

## BAGIAN 5: RENCANA TEKNIS SINKRONISASI (TAHAP 2)

### Langkah 1: Penguatan Backend API Resource
- Edit `USUClassMap/app/Http/Resources/CopyShopResource.php`: Tambahkan root level `latitude` dan `longitude` float casting.

### Langkah 2: Pembaruan Blade Admin Map
- Edit `USUClassMap/resources/views/features/admin/map.blade.php`:
  1. Tambahkan endpoint `copyShops` ke dalam objek `window.ADMIN_MAP_CONFIG.endpoints`.
  2. Tambahkan deretan tombol Floating Spatial HUD di kanan atas `#admin-map`:
     - Tombol **Mode 3D / 2D** (`#admin-perspective-toggle-btn`).
     - Tombol **Mode Satelit** (`#admin-satellite-toggle-btn`).
     - Tombol **Reset Kamera** (`#admin-reset-center-btn`).
     - Tombol **Fotokopi (31)** (`#admin-floating-photocopy-btn`).
  3. Berikan dimensi eksplisit (`min-h-[500px]`, `w-full`, `h-full`) pada `#admin-map`.
- Sinkronkan `USUClassMap/resources/views/admin/map.blade.php` agar identik.

### Langkah 3: Pembaruan Engine JS Admin Map
- Edit `USUClassMap/resources/js/features/admin/map.js`:
  1. Tambahkan pembersih WebGL `beforeunload` dan pola singleton (`window.__usuAdminMapInstance`).
  2. Tambahkan opsi `failIfMajorPerformanceCaveat: false` pada Mapbox constructor.
  3. Tambahkan fungsi fallback UI `renderFallbackMapError()` jika WebGL gagal.
  4. Tambahkan `injectSatelliteLayer()` menggunakan Google Satellite Hybrid (`lyrs=y`).
  5. Tambahkan `toggleSatelliteMode()`, `togglePerspective()`, dan `resetCampusView()`.
  6. Tambahkan `loadPhotocopyPlaces()` dan `renderPhotocopyMarkers()` untuk menampilkan 31 mitra fotokopi.
  7. Pertahankan 100% fungsionalitas admin marker (drag gedung baru, drag edit gedung, popup konteks, toast notification).
  8. Expose fungsi kontrol ke `window.USUAdminMap` agar tombol HUD di Blade dapat memanggilnya.
- Salin kode tersinkronisasi ke `USUClassMap/public/js/admin-map.js`.

### Langkah 4: Verifikasi & Kompilasi
- Jalankan `npm run build` di folder `USUClassMap`.
- Jalankan pemeriksaan keamanan `git grep -i 'pk.eyJ'` dan `git grep -i 'sk.eyJ'` untuk memastikan tidak ada token yang bocor.
