# BLUEPRINT & TRACKING: IN-APP CAMPUS PEDESTRIAN & VEHICLE ROUTING (USUClassMap)

**Status Keseluruhan:** `[x] SELESAI (TAHAP 1 – TAHAP 5 TERVERIFIKASI)`
**Tanggal Pembaruan:** `2026-11-24`
**Proyek Target:** `USUClassMap` (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)

---

## Checklist Progres Pelaksanaan

- [x] **TAHAP 1 — Fondasi Skema Database, Model & Ekstraksi Graf Jalan Kampus USU (`SELESAI`)**
  - Migrasi koordinat pintu masuk `entrance_lat` & `entrance_lng` pada tabel `buildings` dan `photocopy_places` (`2026_11_24_200001_add_entrance_coords_to_buildings_and_photocopy_places.php`).
  - Migrasi tabel `campus_routing_overrides` untuk custom `walkway`, `bridge`, `barrier`, dan `entrance` (`2026_11_24_200002_create_campus_routing_overrides_table.php`).
  - Model `App\Models\CampusRoutingOverride`, pembaruan `$fillable` + `$casts` pada `Building` & `PhotocopyPlace`, serta pengalihan `PhotocopyPlace::getDirectionUrl()` ke `route('map', ['route_to' => 'fotocopy:' . $this->id])`.
  - Integrasi `campus_routing_overrides` & `campus_routing_graph.json` ke `GitSnapshotSyncService` dan `AppServiceProvider` (`GitSnapshotObserver`).
  - Ekstraksi graf OSM Kampus USU melalui `php artisan routing:sync-campus-graph` (menggunakan tile pooled `osm_map_api_v06` + pembersihan komponen terisolasi) dan penyimpanan ke `storage/app/routing/usu_campus_graph.json` + `database/seeders/snapshots/campus_routing_graph.json`.

- [x] **TAHAP 2 — Engine A* Routing, Geometry Safety Guard & API Publik (`SELESAI`)**
  - `App\Services\Routing\GeometryGuardService`: deteksi perpotongan segmen 2D, ray-casting *point-in-polygon* gedung, proyeksi pintu masuk aman anti-halangan, *visibility detour* lokal, serta `auditRouteGeometry()` (audit bebas gedung & bebas parit/barrier).
  - `App\Services\Routing\CampusGraphBuilderService`: penyusun graf jalan & selasar OSM (`walkable`/`drivable`), poligon gedung, barrier/waterway, serta ringkasan konektivitas entitas.
  - `App\Services\Routing\TurnByTurnGenerator`: generator panduan belokan bahasa Indonesia (masuk selasar/jalan, belok kiri/kanan, jembatan, masuk pintu gedung, tiba di tujuan).
  - `App\Services\Routing\CampusRoutingService`: A* multiterminal, snapping bebas tembok/parit, penggabungan dinamis `campus_routing_overrides` (memotong segmen yang terkena `barrier`, menyambung `walkway`/`bridge`), dan fallback hybrid Mapbox Directions untuk titik di luar BBOX inti kampus.
  - `App\Http\Controllers\Api\CampusRoutingController` & `routes/api.php`: endpoint `GET/POST /api/route` (mendukung format ringkas `?origin=building:id&destination=fotocopy:id` maupun parameter terstruktur) dan `GET /api/routing/destinations`.

- [x] **TAHAP 3 — UI Navigasi In-App `/map` & Eliminasi Redirect Google Maps (`SELESAI`)**
  - Pembaruan `resources/views/features/map/index.blade.php` & `resources/js/features/map.js`:
    - Tombol HUD **Rute Kampus** dan panel mengambang **Navigasi Rute Kampus USU** berbasis `usuRoutePanel()` di dalam `<script>` (tanpa kebocoran logika JS di atribut HTML).
    - Dukungan Asal (GPS, Pilih di Peta via klik, atau daftar Gedung/Mitra Fotokopi), Tujuan, tukar arah, mode `Jalan Kaki` vs `Kendaraan`, kartu jarak/estimasi waktu, badge verifikasi audit geometri (`Rute aman: 0 tembus gedung, 0 lompati parit`), dan panduan langkah belokan yang dapat diklik untuk fokus kamera.
    - Render garis rute Mapbox GL kontras tinggi (`usu-route-main-casing`, `usu-route-main-line`, `usu-route-arrows`, `usu-route-access-line`) di atas citra Google Satellite Hybrid & bangunan 3D, serta penanda `Asal`/`Tujuan` dan penanganan parameter URL `?route_to=...`.
    - Refactor modal detail ruangan ke `window.usuRoomDetailModal()` di `<script>` dengan tombol navigasi rute in-app.
  - Penggantian seluruh tautan arah `google.com/maps` & `maps.google.com` menjadi navigasi in-app `/map?route_to=...` pada `FaqController`, `faq/index`, `landing/index`, `fotocopy/*`, `map/index`, `welcome`, `public/bookmarks`, `public/faq`, `resources/js/map.js`, dan `resources/js/features/map.js` (scan rekursif: `0` kemunculan).

- [x] **TAHAP 4 — Admin Spatial Routing Editor di `/admin-sementara-/map` (`SELESAI`)**
  - Pembaruan `EnsureUserIsAdmin`: mengembalikan respons JSON `403` saat request JSON tidak berhak.
  - `AdminMapController` + rute `routes/web.php` berproteksi `['auth', 'admin']`: `GET /routing-data`, `POST /routing-overrides`, `DELETE /routing-overrides/{override}`, `POST /routing-entrance`, dan `POST /routing-rebuild`.
  - Penambahan tab ke-4 **🛣️ Routing** pada `resources/views/features/admin/map.blade.php` dan engine visual overlay/draw mode (`walkway`, `bridge`, `barrier`, `entrance`, *draft polyline*, pin pintu masuk, toggle overlay, & `__usuAdminMapDestroyed` WebGL guard) pada `resources/js/features/admin/map.js`.

- [x] **TAHAP 5 — Pengujian Otomatis, Quality Gates, Sinkronisasi & E2E Playwright (`SELESAI`)**
  - Suite `tests/Feature/CampusRoutingTest.php` (`9` skenario pengujian) — **9/9 PASS (129 assertions)** dalam `15.46s`.
  - Full suite `php artisan test` — **101 passed (759 assertions)** (`44.96s`), 0 gagal.
  - Pemulihan triple RDF Apache Jena Fuseki (`php artisan rdf:generate --upload`) — **5.025 triples** terverifikasi.
  - Verifikasi produksi Vite build (`npm run build`, `copilot.js` tetap terjaga, `7.91s`) & `node --check` pada skrip inline `/map` (`1` skrip) serta `/admin-sementara-/map` (`2` skrip) — 0 error.
  - Verifikasi E2E headless Edge Playwright untuk 3 skenario GPS:
    - Skenario 1 (GPS dalam kampus `3.5625, 98.6596` → `FIB #27`): auto-fill `Lokasi Saya (GPS • ±18 m)`, marker biru berdenyut, rute `378 m` (`Graf Pejalan Kaki USU`), switch ke `Kendaraan` → `603 m` (`Graf Jalan Kendaraan USU`).
    - Skenario 2 (GPS luar kampus `3.5685, 98.6435` → `Fotocopy #32`): auto-fill `Lokasi Saya (GPS • ±24 m)`, rute hybrid `2,35 km` (`Rute Jalan Medan + Graf Kampus USU`).
    - Skenario 3 (GPS ditolak `PERMISSION_DENIED`): tampil banner diagnostik jujur berbahasa Indonesia + pintasan `Pilih Gedung Asal` / `Klik Titik di Peta`, **tanpa** rute palsu `2 m`.
    - Total `pageerror`: **0**.
  - Sinkronisasi Git Snapshot (`php artisan app:git-sync --export` & `--status`) — **SINKRON**.

- [x] **UPGRADE KRITIS — Auto-GPS 2-Tahap Saat Tujuan Dipastikan & Hardening Jaringan Jalan (`SELESAI`)**
  - **2-Stage Geolocation + Visual Marker GPS:** cek `window.isSecureContext`, cache fix `< 60 dtk`, Tahap 1 `enableHighAccuracy: true` (`7s`) → auto-fallback Tahap 2 `enableHighAccuracy: false` (`10s`) untuk laptop Windows, marker titik biru berdenyut + lingkaran radius akurasi, opsi `Live Tracking` ringan, banner diagnostik error spesifik (`PERMISSION_DENIED`, `POSITION_UNAVAILABLE`/`TIMEOUT`, `INSECURE_CONTEXT`), serta perbaikan rendering `<option>` dinamis pada `<select>` `#route-origin-select` agar label `Lokasi Saya (GPS • ±... m)` selalu muncul.
  - **Anti-Jalan Sembarangan & Larangan Rute Palsu `2 m`:**
    - `ExternalRoutingUnavailableException` + fallback HTTP ke **OSRM** apabila Mapbox Directions gagal (`503 external_routing_unavailable` jujur jika keduanya gagal — **tidak pernah** menarik garis lurus antarkota).
    - `GeometryGuardService::projectPointOnSegment()` & `polylineLengthMeters()` serta *projection-aware edge snapping* + virtual edge split pada `CampusRoutingService` (menjepit proyeksi dalam toleransi lokalitas `<= best + 18 m` agar kendaraan tidak melompati rumput `55 m`).
    - Pemisahan tegas `NON_DRIVABLE_ROAD_CLASSES` (`footway`, `steps`, `corridor`, `path`, `pedestrian`, `campus_walkway`, `bridge_foot`) dan label kanonis `Graf Pejalan Kaki USU`, `Graf Jalan Kendaraan USU`, serta `Rute Jalan Medan + Graf Kampus USU`.
    - Status jujur `< 20 m` (`already_at_destination` -> *"Anda sudah berada di sekitar lokasi tujuan (±X m)"*) + validasi HTTP `422` pada koordinat kosong, `(0,0)`, luar Indonesia, atau asal=tujuan.
    - Pembersihan instruksi `TurnByTurnGenerator` (`cleanSteps()` memfilter segmen 0–2 m semu dan menggabungkan duplikat berurutan).

---

## Statistik Graf Jalan Kampus USU (`usu_campus_graph.json`)

| Metrik Graf | Nilai Terukur |
| :--- | :--- |
| Sumber Ekstraksi | `osm_map_api_v06` |
| Jumlah Simpul (`node_count`) | `3.141` |
| Segmen Jalan (`edge_count` / `walkable_edge_count`) | `3.378` |
| Segmen Kendaraan (`drivable_edge_count`) | `3.058` |
| Poligon Gedung Penghalang (`building_polygon_count`) | `6.243` |
| Garis Penghalang / Saluran (`barrier_count` / `waterway_count`) | `41` (`40` waterway) |
| Pulau Terisolasi Dihapus (`removed_isolated_islands`) | `7` (`28` simpul) |
| Konektivitas Gedung (`buildings_connected / total`) | `77 / 77` |
| Konektivitas Fotokopi BBOX Inti (`places_in_bbox_connected / total`) | `29 / 32` *(3 gerai luar BBOX dilayani fallback Hybrid)* |

---

## Hasil Uji Rute & Audit Geometri Nyata

| Pasangan Asal → Tujuan | Mode | Jarak & Durasi | Hasil Audit Geometri |
| :--- | :--- | :--- | :--- |
| `FT-MESIN (#18)` → `FMIPA-U4 (#71)` | `walking` | `236 m` (`4 mnt jalan kaki`) | `building_intersections: 0`, `barrier_intersections: 0`, `verified: true` |
| `FT-MESIN (#18)` → `Fotocopy Trial Admin (#32)` | `walking` | `643 m` (`9 mnt jalan kaki`) | `building_intersections: 0`, `barrier_intersections: 0`, `verified: true` |
| `GKB (#4)` → `FMIPA-U4 (#71)` | `walking` | `731 m` (`10 mnt jalan kaki`) | `building_intersections: 0`, `barrier_intersections: 0`, `verified: true` |
| `FT-MESIN (#18)` → `GKB (#4)` | `driving` | `1,13 km` (`4 mnt berkendara`) | `building_intersections: 0`, `barrier_intersections: 0`, `verified: true` |

---

## Daftar Berkas Dibuat & Dimodifikasi (Ringkas)

### Berkas Baru
- `USUClassMap/database/migrations/2026_11_24_200001_add_entrance_coords_to_buildings_and_photocopy_places.php`
- `USUClassMap/database/migrations/2026_11_24_200002_create_campus_routing_overrides_table.php`
- `USUClassMap/app/Models/CampusRoutingOverride.php`
- `USUClassMap/app/Services/Routing/ExternalRoutingUnavailableException.php`
- `USUClassMap/app/Services/Routing/GeometryGuardService.php`
- `USUClassMap/app/Services/Routing/CampusGraphBuilderService.php`
- `USUClassMap/app/Services/Routing/TurnByTurnGenerator.php`
- `USUClassMap/app/Services/Routing/CampusRoutingService.php`
- `USUClassMap/app/Console/Commands/SyncCampusRoutingGraphCommand.php`
- `USUClassMap/app/Http/Controllers/Api/CampusRoutingController.php`
- `USUClassMap/storage/app/routing/usu_campus_graph.json`
- `USUClassMap/database/seeders/snapshots/campus_routing_graph.json`
- `USUClassMap/tests/Feature/CampusRoutingTest.php`

### Berkas Dimodifikasi
- Backend & Config: `Building.php`, `PhotocopyPlace.php`, `config/services.php`, `GitSnapshotSyncService.php`, `AppServiceProvider.php`, `EnsureUserIsAdmin.php`, `AdminMapController.php`, `FaqController.php`, `routes/api.php`, `routes/web.php`
- Frontend & Views: `resources/views/features/map/index.blade.php`, `resources/js/features/map.js`, `resources/views/features/admin/map.blade.php`, `resources/js/features/admin/map.js`, `resources/views/features/landing/index.blade.php`, `resources/views/features/faq/index.blade.php`, `resources/views/features/fotocopy/public/{index,show,order,payment}.blade.php`, `resources/views/features/fotocopy/legacy/{show,order}.blade.php`, `resources/views/fotocopy/{show,order}.blade.php`, `resources/views/public/fotocopy/{show,order,payment}.blade.php`, `resources/views/public/{bookmarks,faq}.blade.php`, `resources/views/map/index.blade.php`, `resources/views/welcome.blade.php`, `resources/js/map.js`
