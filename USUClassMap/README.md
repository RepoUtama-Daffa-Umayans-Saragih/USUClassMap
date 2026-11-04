# 🗺️ USUClassMap — Peta Jadwal Kelas Interaktif Universitas Sumatera Utara

> **Aplikasi web berbasis Semantic Web** untuk memetakan dan mencari jadwal perkuliahan secara spasial di kampus USU. Mahasiswa dapat melihat lokasi gedung, daftar ruangan, dan jadwal kelas secara real-time di atas peta interaktif Leaflet.js.

---

## 📋 Daftar Isi

1. [Tech Stack](#-tech-stack)
2. [Arsitektur Sistem](#-arsitektur-sistem)
3. [Struktur Folder](#-struktur-folder)
4. [Persiapan & Instalasi (Setup Guide)](#-persiapan--instalasi-setup-guide)
5. [Konfigurasi Environment (.env)](#-konfigurasi-environment-env)
6. [Menjalankan Seeder Data](#-menjalankan-seeder-data)
7. [Alur Routing & Controller](#-alur-routing--controller)
8. [Fitur-Fitur Utama](#-fitur-fitur-utama)
9. [Semantic Web Stack (Phase 4)](#-semantic-web-stack-phase-4)
10. [Catatan Developer Krusial](#-catatan-developer-krusial)

---

## 🛠️ Tech Stack

### Backend

| Kategori | Teknologi | Versi |
|---|---|---|
| Bahasa Pemrograman | **PHP** | `^8.2` |
| Framework | **Laravel** | `^11.31` |
| Database (Default) | **SQLite** | (file `database/database.sqlite`) |
| Database (Opsional) | **MySQL / MariaDB** | kompatibel via konfigurasi `.env` |
| Triplestore (Semantic Web) | **Apache Jena Fuseki** | `localhost:3030` (opsional, ada fallback) |
| ORM | **Eloquent** (via Laravel) | - |
| Auth Scaffolding | **Laravel Breeze** | `^2.4` |
| Excel Import/Export | **Maatwebsite/Excel** | `^3.1` |
| RDF Generator | **EasyRdf** | `^1.1` |
| Queue / Job | **Laravel Jobs** | (driver: database) |
| Testing | **PHPUnit** | `^11.0.1` |

### Frontend

| Kategori | Teknologi | Versi |
|---|---|---|
| CSS Framework | **Tailwind CSS** | `^4.3.3` |
| Plugin Tailwind Form | **@tailwindcss/forms** | `^0.5.2` |
| Reaktivitas UI | **Alpine.js** | `^3.16.2` |
| HTTP Client Frontend | **Axios** | `^1.7.4` |
| Build Tool | **Vite** | `^6.0.11` |
| Plugin Vite Laravel | **laravel-vite-plugin** | `^1.2.0` |

### Peta & Visualisasi

| Kategori | Teknologi |
|---|---|
| Peta Interaktif | **Leaflet.js** (via CDN, bukan npm) |
| Tile Provider | **OpenStreetMap** |
| 360° Photo Viewer | **Pannellum** (via CDN) |
| Spatial Admin Dashboard | Custom `map-admin.js` + Draggable Leaflet Marker |

---

## 🏛️ Arsitektur Sistem

Aplikasi ini menggunakan arsitektur **Laravel MVC + Repository Pattern + Service Layer**.

```
Request (Browser/API)
        │
        ▼
   routes/web.php   ──► AdminMapController, ScheduleController, dll.
        │
        ▼
  Controller Layer
        │
        ├──► Repository Layer (app/Repositories/)
        │         └── Eloquent Model (app/Models/)
        │                    └── SQLite / MySQL Database
        │
        └──► Service Layer (app/Services/)
                  ├── SparqlService     → Query ke Apache Jena Fuseki
                  │                       (auto-fallback ke MySQL jika Fuseki offline)
                  └── RdfGeneratorService → Generate file schedules.rdf
                                            dari data MySQL via EasyRdf
```

### Pola Repository

Setiap entitas utama memiliki pasangan Interface dan Eloquent Implementation:

```
app/Repositories/
├── Contracts/
│   ├── BuildingRepositoryInterface.php
│   ├── RoomRepositoryInterface.php
│   ├── CourseRepositoryInterface.php
│   ├── LecturerRepositoryInterface.php
│   └── ScheduleRepositoryInterface.php
└── Eloquent/
    ├── BuildingRepository.php
    ├── RoomRepository.php
    └── ...
```

### Alur Data Semantic Web

```
Data MySQL (Schedules, Rooms, Buildings)
        │
        ▼ [php artisan rdf:generate  ATAU  otomatis via ScheduleObserver]
        │
RegenerateRdfJob (Queue) → RdfGeneratorService
        │
        ▼
storage/app/rdf/schedules.rdf   (EasyRdf → RDF/XML)
        │
        ▼ [Upload manual ke Apache Jena Fuseki — opsional]
        │
SparqlService.searchSchedules()
├── Coba SPARQL query ke Fuseki (timeout 5 detik)
└── Jika gagal/timeout → fallback ke MySQL Eloquent query otomatis
```

---

## 📂 Struktur Folder

```
USUClassMap/
├── app/
│   ├── Console/Commands/
│   │   └── GenerateRdfCommand.php       # Artisan: php artisan rdf:generate
│   ├── Exports/
│   │   └── ScheduleTemplateExport.php   # Template Excel untuk import jadwal
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                   # Controller CRUD admin (Building, Room, dll)
│   │   │   ├── AdminMapController.php   # Controller Spatial Map Dashboard (Phase 7.0)
│   │   │   ├── ScheduleController.php   # API endpoint jadwal & sinkronisasi
│   │   │   ├── FaqController.php
│   │   │   └── PhotoInspectorController.php  # 360° photo calibration tool
│   │   └── Middleware/
│   │       └── EnsureUserIsAdmin.php
│   ├── Imports/
│   │   └── ScheduleImport.php           # Maatwebsite Excel importer
│   ├── Jobs/
│   │   └── RegenerateRdfJob.php         # Async job regenerasi RDF
│   ├── Models/
│   │   ├── User.php                     # Role: 'admin', 'superadmin', user biasa
│   │   ├── Building.php                 # Gedung (lat/lng, faculty, floor_count)
│   │   ├── Room.php                     # Ruangan (photo_360_path, floor, capacity)
│   │   ├── RoomMedia.php                # Media tambahan per ruangan (Phase 7.3)
│   │   ├── Course.php                   # Mata Kuliah
│   │   ├── Lecturer.php                 # Dosen
│   │   └── Schedule.php                 # Jadwal (penghubung semua entitas)
│   ├── Observers/
│   │   └── ScheduleObserver.php         # Auto-trigger RegenerateRdfJob saat data berubah
│   ├── Repositories/                    # Repository Pattern (lihat Arsitektur)
│   └── Services/
│       ├── RdfGeneratorService.php      # EasyRdf → RDF/XML
│       └── SparqlService.php            # SPARQL query + MySQL fallback
├── database/
│   ├── migrations/                      # 12 migration files
│   └── seeders/
│       ├── DatabaseSeeder.php           # Entry point utama seeder
│       ├── AdminUserSeeder.php
│       ├── BuildingSeeder.php
│       ├── RoomSeeder.php
│       ├── LecturerSeeder.php
│       ├── CourseSeeder.php
│       ├── ScheduleSeeder.php
│       └── FibDummySeeder.php           # 100 jadwal MKWU FIB (hardcoded)
├── public/
│   ├── js/
│   │   └── admin-map.js                 # Leaflet admin spatial dashboard
│   └── build/                           # Output Vite (auto-generated)
├── resources/
│   ├── css/app.css                      # Tailwind CSS + custom Leaflet popup overrides
│   ├── js/
│   │   ├── app.js                       # Alpine.js bootstrap
│   │   └── map.js                       # Logic peta publik (popup, search, sorting)
│   └── views/
│       ├── layouts/                     # app.blade.php, admin.blade.php, guest.blade.php
│       ├── admin/                       # View admin spatial dashboard
│       ├── map/index.blade.php          # Halaman peta publik utama
│       ├── welcome.blade.php            # Landing page
│       ├── public/                      # Bookmarks, FAQ
│       └── components/                  # Reusable Blade components
├── routes/
│   ├── web.php                          # Semua routing aplikasi
│   └── auth.php                         # Routing auth (Breeze)
├── storage/app/rdf/                     # Output file RDF (schedules.rdf)
├── .env.example
├── composer.json
├── package.json
└── vite.config.js
```

---

## ⚙️ Persiapan & Instalasi (Setup Guide)

### Prerequisites

Pastikan software berikut sudah terinstal di komputer Anda:

| Software | Versi Minimum | Cek Versi |
|---|---|---|
| **PHP** | 8.2+ | `php -v` |
| **Composer** | 2.x | `composer -V` |
| **Node.js** | 18+ | `node -v` |
| **npm** | 9+ | `npm -v` |
| **Git** | - | `git --version` |
| **SQLite** | 3.x (umumnya sudah built-in PHP) | `php -m \| grep sqlite` |

> **Opsional:** Apache Jena Fuseki untuk fitur SPARQL penuh. Tanpanya, aplikasi tetap berjalan menggunakan fallback MySQL.

---

### Langkah Instalasi

#### 1. Clone Repository

```bash
git clone <URL_REPOSITORY> USUClassMap
cd USUClassMap
```

#### 2. Install Dependensi PHP

```bash
composer install
```

#### 3. Install Dependensi JavaScript

```bash
npm install
```

#### 4. Buat File Environment

```bash
cp .env.example .env
php artisan key:generate
```

#### 5. Konfigurasi Database

Buka file `.env` dan sesuaikan bagian database:

**Opsi A — SQLite (Default, Paling Mudah):**
```env
DB_CONNECTION=sqlite
```
Lalu buat file database SQLite-nya:
```bash
# Windows
type nul > database\database.sqlite

# Linux / macOS
touch database/database.sqlite
```

**Opsi B — MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=usuclassmap
DB_USERNAME=root
DB_PASSWORD=your_password
```
Jangan lupa buat database MySQL-nya terlebih dahulu:
```sql
CREATE DATABASE usuclassmap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 6. Jalankan Migrasi Database

```bash
php artisan migrate
```

#### 7. Jalankan Seeder (Data Awal)

```bash
php artisan db:seed
```

Ini akan menjalankan seluruh seeder secara berurutan (Admin user, Gedung, Ruangan, Dosen, Matkul, Jadwal, dan 100 data dummy FIB).

#### 8. Buat Storage Symlink

> **WAJIB!** Tanpa langkah ini, foto 360° dan media ruangan tidak akan tampil.

```bash
php artisan storage:link
```

#### 9. Build Aset Frontend

**Untuk Development (dengan hot-reload):**
```bash
npm run dev
```

**Untuk Production:**
```bash
npm run build
```

#### 10. Jalankan Server Lokal

```bash
php artisan serve
```

Aplikasi akan berjalan di: **http://localhost:8000**

---

### Menjalankan Semua Service Sekaligus (Rekomendasi Development)

```bash
composer dev
```

Perintah ini menjalankan secara paralel:
- `php artisan serve` — Web server
- `php artisan queue:listen` — Queue worker untuk job RDF
- `php artisan pail` — Log viewer real-time
- `npm run dev` — Vite dev server dengan HMR

---

## 🔐 Konfigurasi Environment (.env)

Variabel penting yang perlu dikonfigurasi selain database:

```env
# Nama Aplikasi
APP_NAME="USUClassMap"
APP_URL=http://localhost:8000

# Timezone (penting untuk jadwal)
APP_TIMEZONE=Asia/Jakarta

# Queue untuk job RDF Regeneration
QUEUE_CONNECTION=database

# SPARQL / Apache Jena Fuseki (opsional)
# Jika tidak dikonfigurasi, sistem otomatis pakai MySQL fallback
SPARQL_ENDPOINT=http://localhost:3030/usuclassmap/query
SPARQL_TIMEOUT=5
```

---

## 🌱 Menjalankan Seeder Data

### Seeder Utama (Semua Sekaligus)
```bash
php artisan db:seed
```

### Seeder Spesifik Per Kelas

| Seeder | Perintah | Keterangan |
|---|---|---|
| Admin User | `php artisan db:seed --class=AdminUserSeeder` | Membuat akun admin default |
| Gedung | `php artisan db:seed --class=BuildingSeeder` | Data gedung USU |
| Ruangan | `php artisan db:seed --class=RoomSeeder` | Data ruangan per gedung |
| Dosen | `php artisan db:seed --class=LecturerSeeder` | Data dosen |
| Matkul | `php artisan db:seed --class=CourseSeeder` | Data mata kuliah |
| Jadwal | `php artisan db:seed --class=ScheduleSeeder` | Jadwal perkuliahan |
| FIB Dummy | `php artisan db:seed --class=FibDummySeeder` | 100 data MKWU FIB (hardcoded) |

> **Catatan:** `FibDummySeeder` menggunakan array PHP hardcoded (tidak bergantung file CSV/Excel), sehingga aman dijalankan di semua OS termasuk Windows.

---

## 🧭 Alur Routing & Controller

### Public Routes (Tanpa Auth)

| Method | URL | Controller | Keterangan |
|---|---|---|---|
| GET | `/` | Closure | Landing page (welcome.blade.php) |
| GET | `/map` | Closure | Peta interaktif publik |
| GET | `/bookmarks` | Closure | Halaman bookmark jadwal |
| GET | `/faq` | `FaqController@index` | Halaman FAQ |
| GET | `/my-schedules` | `ScheduleController@mySchedules` | **API** — JSON jadwal + flag `is_my_class` |
| GET | `/admin/cek-foto` | `PhotoInspectorController@index` | Tool inspeksi & kalibrasi foto 360° |

### Admin Routes (`/admin/*`, Auth + Role Admin)

| Method | URL | Keterangan |
|---|---|---|
| GET | `/admin/dashboard` | Dashboard statistik admin |
| Resource | `/admin/buildings` | CRUD Gedung |
| Resource | `/admin/rooms` | CRUD Ruangan |
| Resource | `/admin/courses` | CRUD Mata Kuliah |
| Resource | `/admin/lecturers` | CRUD Dosen |
| Resource | `/admin/schedules` | CRUD Jadwal |
| GET/POST | `/admin/import` | Import jadwal via Excel |
| GET | `/admin/import/template` | Download template Excel |

### Admin Spatial Map Dashboard (Phase 7.0, Tanpa Auth)

> ⚠️ Route ini sengaja tidak memerlukan auth untuk kemudahan development. Tambahkan middleware sebelum production.

| Method | URL | Controller Method | Keterangan |
|---|---|---|---|
| GET | `/admin-sementara-/map/` | `AdminMapController@index` | Halaman utama spatial dashboard |
| POST | `/admin-sementara-/map/building` | `AdminMapController@storeBuilding` | Simpan gedung baru via AJAX |
| PUT | `/admin-sementara-/map/building/{id}` | `AdminMapController@updateBuilding` | Update gedung via AJAX |
| POST | `/admin-sementara-/map/room` | `AdminMapController@storeRoom` | Simpan ruangan baru via AJAX |
| POST | `/admin-sementara-/map/schedule` | `AdminMapController@storeSchedule` | Simpan jadwal baru via AJAX |
| GET | `/admin-sementara-/map/rooms/{building}` | `AdminMapController@getRoomsByBuilding` | Fetch ruangan per gedung (JSON) |
| GET/POST/PUT/DELETE | `/admin-sementara-/map/rooms/{room}/media` | `AdminMapController@*` | CRUD media ruangan (Phase 7.3) |

---

## ✨ Fitur-Fitur Utama

### 1. Peta Interaktif (`/map`)

- Peta berbasis **Leaflet.js** dengan tile OpenStreetMap.
- Marker per gedung, klik membuka **"Smart Room Card"** popup.
- Popup scrollable berisi daftar ruangan + jadwal per ruangan.
- **Smart Focus & Double Sorting (Phase 6.6 & 6.7):**
  - Pencarian matkul di sidebar → klik "Lihat di Peta" → peta fly ke gedung, popup terbuka, **ruangan target naik ke index 0** (highlight biru + badge `🎯 Hasil Pencarian`), **jadwal target naik ke index 0** dalam card ruangan (highlight biru + badge `🎯 Pilihan Anda` animasi pulse).
- **Modal Multimedia Multi-Mode:**
  - Mode **Foto Biasa**, **360° (Pannellum)**, **Video Walkthrough**.
  - Tombol **"Jalankan Rute Kelas"** untuk navigasi ke gedung.
- Jadwal milik user di-highlight warna **emas** (`is_my_class: true`).
- Riwayat pencarian tersimpan di `localStorage`.

### 2. Pencarian Berbasis SPARQL + MySQL Fallback

- Endpoint `/my-schedules?keyword=...&day=...` menggunakan `SparqlService`.
- Jika Apache Jena Fuseki aktif → query SPARQL ke triplestore.
- Jika Fuseki offline/timeout (5 detik) → **auto-fallback** ke Eloquent MySQL query dengan hasil format identik.

### 3. Admin CRUD Dashboard

- Manajemen lengkap entitas: Gedung, Ruangan, Dosen, Matkul, Jadwal.
- Import massal jadwal via file Excel (`.xlsx`).
- Download template Excel dengan format kolom yang benar.

### 4. Admin Spatial Map Dashboard (`/admin-sementara-/map`)

- UI split-panel: Sidebar form CRUD (kiri) + Peta Leaflet fullscreen (kanan).
- **Draggable Marker** berwarna merah/emas untuk menentukan koordinat gedung.
- **Live Tooltip Koordinat**: saat marker digeser, tooltip menampilkan `Lat/Lng` real-time.
- Setelah drag selesai, field `latitude` dan `longitude` di sidebar **terisi otomatis** dengan efek highlight animasi.
- Tab-based navigation (Alpine.js): Tab Gedung → Tab Ruangan → Tab Jadwal.
- Semua form submit via **AJAX (Fetch API)**, halaman tidak reload.

### 5. Tool Inspeksi Foto 360°

- Akses di `/admin/cek-foto` dan `/admin/cek-foto-p`.
- Memungkinkan admin melihat dan mengkalibrasi parameter pitch/yaw/hFov foto 360° sebelum dipublish.

### 6. Regenerasi RDF Otomatis

- `ScheduleObserver` memantau event `created`, `updated`, `deleted` pada model `Schedule`.
- Setiap perubahan data jadwal **otomatis men-dispatch `RegenerateRdfJob`** ke antrian.
- Job memperbarui file `storage/app/rdf/schedules.rdf` tanpa interaksi manual.
- Dapat juga dijalankan manual: `php artisan rdf:generate`.

---

## 🔗 Semantic Web Stack (Phase 4)

### Ontologi Kustom

Namespace: `http://usuclassmap.usu.ac.id/ontology#` (prefix: `usu:`)

**Kelas Utama:**
- `usu:Jadwal` — Jadwal perkuliahan
- `usu:MataKuliah` — Mata kuliah
- `usu:Dosen` — Dosen pengajar
- `usu:Ruangan` — Ruang kelas
- `usu:Gedung` — Gedung kampus

**Properti Kunci:**
- `usu:hari`, `usu:jamMulai`, `usu:jamSelesai`, `usu:semester`
- `usu:memilikiMataKuliah`, `usu:diajarOleh`, `usu:berlokasiDi`, `usu:beradaDiGedung`
- `usu:latitude`, `usu:longitude` (koordinat spasial)

### Contoh SPARQL Query

```sparql
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

SELECT ?namaMk ?hari ?jamMulai ?namaGedung WHERE {
  ?jadwal a usu:Jadwal ;
          usu:hari ?hari ;
          usu:jamMulai ?jamMulai ;
          usu:memilikiMataKuliah ?mk ;
          usu:berlokasiDi ?ruangan .
  ?mk usu:namaMataKuliah ?namaMk .
  ?ruangan usu:beradaDiGedung ?gedung .
  ?gedung usu:namaGedung ?namaGedung .
  FILTER(CONTAINS(LCASE(?namaMk), "fisika"))
}
```

### Setup Apache Jena Fuseki (Opsional)

1. Download Fuseki dari https://jena.apache.org/download/
2. Jalankan: `fuseki-server --update --mem /usuclassmap`
3. Buka `http://localhost:3030`
4. Upload file `storage/app/rdf/schedules.rdf` ke dataset `usuclassmap`
5. Set di `.env`: `SPARQL_ENDPOINT=http://localhost:3030/usuclassmap/query`

---

## ⚠️ Catatan Developer Krusial

### 1. Storage Symlink — WAJIB Dijalankan

```bash
php artisan storage:link
```

Tanpa ini, semua path foto (termasuk `photo_360_path` pada tabel `rooms`) tidak akan bisa diakses via URL `public/storage/...`. **Foto 360° dan media ruangan tidak akan tampil sama sekali.**

### 2. Queue Worker Harus Aktif

Regenerasi RDF berjalan via Queue. Jika queue worker tidak aktif, perubahan data jadwal tidak akan memperbarui file RDF.

```bash
# Development
php artisan queue:listen --tries=1

# Production
php artisan queue:work --daemon
```

Gunakan **Supervisor** di server Linux untuk menjaga queue worker tetap berjalan.

### 3. Database Default: SQLite

Proyek dikonfigurasi menggunakan SQLite (`DB_CONNECTION=sqlite`). File database ada di `database/database.sqlite`. Jika ingin beralih ke MySQL, ubah konfigurasi `.env` dan jalankan `php artisan migrate:fresh --seed`.

### 4. `FibDummySeeder` — Hardcoded, Tanpa File CSV

Seeder ini sengaja menggunakan array PHP hardcoded untuk menghindari error `File Not Found` di Windows saat akses path file. Jika perlu menambah data, edit langsung array `$schedulesData` di dalam file seeder.

### 5. Leaflet.js & Pannellum Dimuat via CDN

Library Leaflet.js dan Pannellum (360° viewer) tidak diinstal via npm, melainkan dimuat via `<script>` CDN di dalam file Blade. Pastikan server memiliki koneksi internet saat development, atau ganti dengan path lokal untuk deployment offline.

### 6. Route Admin Spatial Map Tanpa Auth

Route group `/admin-sementara-/map` **tidak memiliki middleware auth** (sengaja untuk kemudahan pengembangan). **Sebelum deploy ke production**, tambahkan middleware:

```php
// routes/web.php
Route::prefix('admin-sementara-/map')
    ->middleware(['auth', 'admin'])  // ← Tambahkan ini
    ->group(function () { ... });
```

### 7. `is_my_class` Saat Ini Berbasis `semester`

Logika penentuan jadwal milik user (`is_my_class: true`) di `ScheduleController@mySchedules` saat ini membandingkan field `semester` user dengan `semester` jadwal. Ini adalah implementasi sementara — untuk implementasi penuh, perlu tabel `user_schedules` (many-to-many antara `users` dan `schedules`).

### 8. Artisan Command Tersedia

```bash
php artisan rdf:generate        # Generate ulang file schedules.rdf secara manual
php artisan migrate:fresh --seed # Reset & seed ulang database (HAPUS SEMUA DATA)
php artisan queue:listen        # Jalankan queue worker
php artisan storage:link        # Buat symlink storage (wajib setelah clone)
php artisan test                # Jalankan seluruh test suite (49 tests)
```

---

## 👥 Kontribusi & Pengembangan

### Menjalankan Test Suite

```bash
php artisan test
```

Hasil yang diharapkan: **49 tests, 219 assertions — semua PASS**.

### Konvensi Kode

- Format kode PHP menggunakan **Laravel Pint**: `./vendor/bin/pint`
- Semua controller baru di area admin ditempatkan di `app/Http/Controllers/Admin/`
- Ikuti Repository Pattern yang sudah ada untuk entitas baru

---

*Dokumentasi ini di-generate secara otomatis berdasarkan analisis source code pada **November 2026**.*
