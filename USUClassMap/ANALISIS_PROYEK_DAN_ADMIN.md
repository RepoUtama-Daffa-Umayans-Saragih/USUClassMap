# 🏛️ LAPORAN AUDIT KODE SUMBER & ARSITEKTUR SISTEM: USUStarterMap
**Fokus Utama:** Analisis Komprehensif Ekosistem Fitur Admin, Keamanan, dan Keterkaitannya dengan Fitur Non-Admin  
**Peran:** Senior Software Architect & Lead Laravel Full-Stack Auditor  
**Target Proyek:** USUStarterMap (Campus Semantic Web-GIS & Academic Service Hub)  
**Tanggal Audit:** 14 November 2026  
**Status Audit:** Verified against Source Code (Deep Codebase Inspection)  

---

## DAFTAR ISI
1. [Ringkasan Eksekutif & Arsitektur Sistem](#1-ringkasan-eksekutif--arsitektur-sistem)
   - 1.1. [Gambaran Umum Aplikasi](#11-gambaran-umum-aplikasi)
   - 1.2. [Identifikasi Stack Teknologi & Dependensi](#12-identifikasi-stack-teknologi--dependensi)
   - 1.3. [Arsitektur Multi-Tier & Dual-Engine Storage](#13-arsitektur-multi-tier--dual-engine-storage)
   - 1.4. [Diagram Arsitektur & Alur Keterhubungan Sistem](#14-diagram-arsitektur--alur-keterhubungan-sistem)
2. [Peta Struktur Direktori & File Kunci](#2-peta-struktur-direktori--file-kunci)
   - 2.1. [Struktur Pohon Direktori Repositori](#21-struktur-pohon-direktori-repositori)
   - 2.2. [Inventarisasi File Kunci Berdasarkan Tanggung Jawab Layer](#22-inventarisasi-file-kunci-berdasarkan-tanggung-jawab-layer)
3. [Inventarisasi Lengkap Route & Modul Admin](#3-inventarisasi-lengkap-route--modul-admin)
   - 3.1. [Tabel Inventarisasi Seluruh Route Admin & Turunannya](#31-tabel-inventarisasi-seluruh-route-admin--turunannya)
   - 3.2. [Matriks Hak Akses Peran Pengguna (RBAC)](#32-matriks-hak-akses-peran-pengguna-rbac)
4. [Analisis Mendalam Setiap Fitur Admin](#4-analisis-mendalam-setiap-fitur-admin)
   - 4.1. [Modul Admin Akademik: Dashboard & Master Data CRUD](#41-modul-admin-akademik-dashboard--master-data-crud)
   - 4.2. [Modul Bulk Ingestion: Batch Import Excel & Template Export](#42-modul-bulk-ingestion-batch-import-excel--template-export)
   - 4.3. [Modul Admin Fotokopi: Manajemen Mitra Toko, Katalog, & Laporan Keuangan](#43-modul-admin-fotokopi-manajemen-mitra-toko-katalog--laporan-keuangan)
   - 4.4. [Modul Kasir & POS Fotokopi: Antrean Pesanan, Cetak Dokumen, & Walk-In POS](#44-modul-kasir--pos-fotokopi-antrean-pesanan-cetak-dokumen--walk-in-pos)
   - 4.5. [Modul Spatial Map Dashboard: Manajemen Spasial Gedung, Ruangan, & Media 360](#45-modul-spatial-map-dashboard-manajemen-spasial-gedung-ruangan--media-360)
   - 4.6. [Modul 360 Photo Inspector: Kalibrasi Visual & Sinkronisasi SPARQL Fuseki](#46-modul-360-photo-inspector-kalibrasi-visual--sinkronisasi-sparql-fuseki)
   - 4.7. [Antarmuka Frontend & Aset Vite Admin](#47-antarmuka-frontend--aset-vite-admin)
5. [Matriks Keterkaitan Lintas-Modul (Admin ↔ Non-Admin)](#5-matriks-keterkaitan-lintas-modul-admin--non-admin)
   - 5.1. [Dampak Hilir (Downstream: Admin → Publik/User/API)](#51-dampak-hilir-downstream-admin--publikuserapi)
   - 5.2. [Aliran Hulu (Upstream: Publik/User/Eksternal → Admin)](#52-aliran-hulu-upstream-publikusereksternal--admin)
   - 5.3. [Koneksi Implisit, Parsial, & Potensi Keterhubungan yang Belum Terintegrasi](#53-koneksi-implisit-parsial--potensi-keterhubungan-yang-belum-terintegrasi)
6. [Temuan Keamanan, Bug, & Rekomendasi Perbaikan](#6-temuan-keamanan-bug--rekomendasi-perbaikan)
   - 6.1. [Temuan Tingkat Prioritas TINGGI (Critical / High)](#61-temuan-tingkat-prioritas-tinggi-critical--high)
   - 6.2. [Temuan Tingkat Prioritas SEDANG (Medium)](#62-temuan-tingkat-prioritas-sedang-medium)
   - 6.3. [Temuan Tingkat Prioritas RENDAH & Kualitas Kode (Low / Code Quality)](#63-temuan-tingkat-prioritas-rendah--kualitas-kode-low--code-quality)
   - 6.4. [Pelan Aksi Remediasi Bertahap (Action Plan)](#64-pelan-aksi-remediasi-bertahap-action-plan)

---

## 1. Ringkasan Eksekutif & Arsitektur Sistem

### 1.1. Gambaran Umum Aplikasi
**USUStarterMap** adalah platform sistem informasi geografis kampus (*Campus Web-GIS*) interaktif berbasis teknologi **Web Semantik (*Semantic Web / Linked Data*)** yang dikembangkan untuk memfasilitasi sivitas akademika Universitas Sumatera Utara (USU) dalam memetakan, menavigasi, dan mengelola jadwal perkuliahan lintas gedung dan fakultas. Fokus utama sistem berpusat pada penanganan logistik jadwal **Mata Kuliah Wajib Kurikulum (MKWK)** dan **Mata Kuliah Wajib Universitas (MKWU)** yang tersebar di puluhan gedung di area Kampus Padang Bulan Medan.

Selain modul navigasi jadwal dan pemetaan spasial gedung dengan media imersif (panorama 360° dan video POV rute), sistem ini telah berkembang mencakup ekosistem **Layanan Fotokopi & Percetakan Terintegrasi**, sinkronisasi otomatis jadwal dari portal resmi mahasiswa (`satu.usu.ac.id`) via Ekstensi Browser Google Chrome, serta scraping cerdas direktori percetakan sekitar kampus via Google Places API (SerpApi).

### 1.2. Identifikasi Stack Teknologi & Dependensi
Berdasarkan pembacaan berkas konfigurasi `composer.json`, `package.json`, `vite.config.js`, dan `.env`, konfigurasi stack teknologi proyek didefinisikan sebagai berikut:

| Komponen Stack | Teknologi / Library | Versi | Peran & Tanggung Jawab |
| :--- | :--- | :--- | :--- |
| **Backend Framework** | PHP / Laravel Framework | PHP ^8.2 / Laravel 11.31 | Inti logika bisnis MVC, routing, Eloquent ORM, Job Queue, Auth |
| **Semantic Triplestore** | Apache Jena Fuseki | Release 6.2.0 | RDF Graph Store Server & SPARQL 1.1 Query/Update Engine |
| **RDF Processing** | `easyrdf/easyrdf` | ^1.1 | Serialisasi graf RDF/XML dari entitas relasional Eloquent |
| **Relational Database** | MySQL / SQLite | Driver MySQL / SQLite | *Single Source of Truth* (SSOT) data transaksional dan otentikasi |
| **Spreadsheet Engine** | `maatwebsite/excel` | ^3.1 | Pemrosesan bulk data impor/ekspor jadwal kuliah format Excel/CSV |
| **Frontend Framework** | Blade + Alpine.js | Alpine.js ^3.16.2 | Reaktivitas UI sisi klien tanpa ketergantungan framework SPA berat |
| **Styling & CSS** | Tailwind CSS + Forms Plugin | Tailwind CSS ^4.3.3 / forms ^0.5.2 | Desain antarmuka responsif utilitas tinggi |
| **Build Tooling** | Vite + Laravel Vite Plugin | Vite ^6.0.11 / Plugin ^1.2.0 | Hot-Module Replacement (HMR) dan bundling aset frontend |
| **Geospatial & Maps** | Leaflet.js | 1.9.4 (CDN) | Visualisasi peta interaktif kampus, poligon gedung, dan rute navigasi |
| **Immersive Panorama** | Pannellum.js | 2.5.6 (CDN) | Viewer foto 360° equirectangular untuk pratinjau interior ruangan |
| **External Scraper** | Python (Playwright / Beautiful Soup) | 3.10+ | Ekstraksi gedung OSM, scraping detail Google Maps & SerpApi |
| **Browser Extension** | WebExtensions API (Manifest v3) | Custom Chrome Ext | Ingestion otomatis jadwal dari DOM portal `satu.usu.ac.id` |

### 1.3. Arsitektur Multi-Tier & Dual-Engine Storage
USUStarterMap mengimplementasikan pola **Dual-Engine Data Storage & Event-Driven Synchronization**:
1. **Relational Database (MySQL / SQLite):** Berfungsi sebagai *Single Source of Truth* (SSOT) yang mengelola transaksi ACID, data pengguna, data master akademik, katalog toko fotokopi, pesanan kasir, serta media ruangan.
2. **Graph Database Triplestore (Apache Jena Fuseki):** Menyimpan representasi graf ontologis RDF/XML (`usu:Jadwal`, `usu:MataKuliah`, `usu:Dosen`, `usu:Ruangan`, `usu:Gedung`). Triplestore ini menyediakan endpoint SPARQL 1.1 (`/usuclassmap/query` dan `/usuclassmap/update`) yang dikonsumsi oleh pencarian cerdas publik dan kalibrasi visual foto 360°.
3. **Synchronization Pipeline:**
   - Ketika data jadwal ditambahkan, diubah, atau dihapus pada database relasional melalui Controller Admin, `ScheduleObserver` memicu eksekusi antrean `RegenerateRdfJob`.
   - `RegenerateRdfJob` mengeksekusi `RdfGeneratorService`, membangun ulang graf RDF menggunakan `EasyRdf\Graph`, menyimpannya ke `storage/app/rdf/schedules.rdf`, lalu mengunggahnya ke Fuseki melalui *Graph Store Protocol HTTP PUT*.
4. **Resilience & Circuit Breaker:**
   `SparqlService` dilengkapi mekanisme *fallback* otomatis: jika server Fuseki offline atau lambat merespons (*timeout* > 5 detik), sistem secara transparan mengalihkan kueri pencarian ke Eloquent MySQL dengan format keluaran JSON yang identik.

### 1.4. Diagram Arsitektur & Alur Keterhubungan Sistem

```mermaid
flowchart TD
    subgraph Klien_Eksternal["Klien Eksternal & Pengguna"]
        MHS["Mahasiswa / Publik (Web Browser)"]
        EXT["USU Chrome Extension (satu.usu.ac.id)"]
        ADMIN_USER["Admin & Super Admin"]
        KASIR_USER["Kasir Fotokopi"]
    end

    subgraph Presentation_Layer["Presentation & Routing Layer (Laravel 11 + Vite)"]
        R_PUB["Public Web Routes (/, /map, /fotocopy, /faq)"]
        R_API["API Routes (/api/schedules/search, /api/buildings, /api/schedule/sync)"]
        R_ADM["Admin Academic Routes (/admin/*)"]
        R_FOT["Admin Fotokopi Routes (/admin-fotokopi/*)"]
        R_KAS["Cashier Routes (/cashier-fotokopi/*)"]
        R_SMAP["Spatial Map Dashboard (/admin-sementara-/map/*)"]
        R_FOTO["Photo Inspector (/admin/cek-foto/*)"]
    end

    subgraph Security_Guards["Security & Access Control (Middleware)"]
        MW_AUTH["Middleware: auth"]
        MW_ADM["Middleware: EnsureUserIsAdmin"]
        MW_AFOT["Middleware: EnsureUserIsAdminFotokopi"]
        MW_KFOT["Middleware: EnsureUserIsCashierFotokopi"]
        NO_GUARD["⚠️ TANPA GUARD AUTH (Hanya web middleware)"]
    end

    subgraph Domain_Services["Domain Logic & Processing Services"]
        OBS["ScheduleObserver"]
        JOB["RegenerateRdfJob (Database Queue)"]
        RDF_GEN["RdfGeneratorService (EasyRdf)"]
        SPARQL_SVC["SparqlService (Circuit Breaker)"]
        EXCEL_IMP["ScheduleImport (Maatwebsite Excel)"]
        GPLACES["GooglePlacesSyncService (SerpApi)"]
    end

    subgraph Storage_Layer["Data & Persistence Layer"]
        DB_SQL[("MySQL / SQLite Database\n(SSOT ACID)")]
        FUSEKI[("Apache Jena Fuseki Triplestore\n(SPARQL 1.1 Endpoint)")]
        STORAGE_DISK[("Local Filesystem Storage\n(storage/app/public/)")]
    end

    %% Hubungan Alur Klien ke Presentation
    MHS -->|Akses Peta, Jadwal & Pesan Fotokopi| R_PUB
    MHS -->|Query SPARQL & Pins| R_API
    EXT -->|Sync Jadwal (POST Payload)| R_API
    ADMIN_USER -->|Kelola Master Akademik & Impor| R_ADM
    ADMIN_USER -->|Kelola Spasial & Media 360| R_SMAP
    ADMIN_USER -->|Kalibrasi Visual 360| R_FOTO
    ADMIN_USER -->|Kelola Toko & Layanan| R_FOT
    KASIR_USER -->|Proses Antrean & POS| R_KAS

    %% Hubungan Routing ke Guard
    R_ADM --> MW_AUTH --> MW_ADM
    R_FOT --> MW_AUTH --> MW_AFOT
    R_KAS --> MW_AUTH --> MW_KFOT
    R_SMAP --> NO_GUARD
    R_FOTO --> NO_GUARD

    %% Hubungan ke Domain & Storage
    MW_ADM --> DB_SQL
    MW_ADM --> EXCEL_IMP --> DB_SQL
    MW_AFOT --> DB_SQL
    MW_KFOT --> DB_SQL
    NO_GUARD --> DB_SQL
    NO_GUARD --> STORAGE_DISK
    R_FOTO -->|SPARQL UPDATE Langsung| FUSEKI

    %% Event Synchronization Loop
    DB_SQL -->|Model Mutated (Schedule)| OBS
    OBS --> JOB
    JOB --> RDF_GEN
    RDF_GEN -->|Serialize XML| STORAGE_DISK
    RDF_GEN -->|HTTP PUT Graph Store| FUSEKI

    %% Query Pipeline
    R_API --> SPARQL_SVC
    SPARQL_SVC -->|1. Primary: SPARQL SELECT| FUSEKI
    SPARQL_SVC -.->|2. Fallback jika Offline| DB_SQL

    %% Downstream ke Frontend Publik
    R_PUB -->|Ambil Data Gedung & Media| DB_SQL
    R_PUB -->|Ambil Media 360 & Dokumen| STORAGE_DISK
```

---

## 2. Peta Struktur Direktori & File Kunci

### 2.1. Struktur Pohon Direktori Repositori
Berikut pemetaan struktur repositori secara mendalam pada proyek `USUStarterMap`:

```
USUClassMap/
 ├── app/
 │    ├── Console/Commands/
 │    │    ├── GenerateRdfCommand.php           # Artisan CLI: php artisan rdf:generate
 │    │    └── SyncUsuCopyShops.php             # Artisan CLI: shops:sync-usu (SerpApi Google Places)
 │    ├── Exports/
 │    │    └── ScheduleTemplateExport.php       # Generator template XLSX import jadwal
 │    ├── Http/
 │    │    ├── Controllers/
 │    │    │    ├── Admin/                      # Modul Admin Akademik Utama
 │    │    │    │    ├── BuildingController.php
 │    │    │    │    ├── CourseController.php
 │    │    │    │    ├── DashboardController.php
 │    │    │    │    ├── ImportController.php
 │    │    │    │    ├── LecturerController.php
 │    │    │    │    ├── RoomController.php
 │    │    │    │    └── ScheduleController.php
 │    │    │    ├── AdminFotokopi/              # Modul Admin Mitra Fotokopi
 │    │    │    │    ├── DashboardController.php
 │    │    │    │    ├── ReportController.php
 │    │    │    │    ├── ServiceController.php
 │    │    │    │    └── StoreController.php
 │    │    │    ├── CashierFotokopi/            # Modul Kasir & POS Operasional Fotokopi
 │    │    │    │    └── OrderController.php
 │    │    │    ├── Api/                        # Endpoint RESTful & SPARQL Search
 │    │    │    │    ├── BuildingApiController.php
 │    │    │    │    ├── CopyShopApiController.php
 │    │    │    │    └── ScheduleApiController.php
 │    │    │    ├── Auth/                       # Breeze Authentication & SATU Scraper Stub
 │    │    │    │    ├── AuthenticatedSessionController.php
 │    │    │    │    ├── RegisteredUserController.php
 │    │    │    │    └── ... (Password/Email Controllers)
 │    │    │    ├── AdminMapController.php       # Controller Spatial Dashboard (admin-sementara-/map)
 │    │    │    ├── PhotoInspectorController.php # Controller 360 Photo Inspector & SPARQL Pitch/Yaw
 │    │    │    ├── FotocopyController.php       # Controller Publik Pemesanan Layanan Fotokopi
 │    │    │    ├── PhotocopyController.php      # Controller Publik Direktori Mitra Fotokopi Tim
 │    │    │    ├── ScheduleController.php       # Controller Publik Visualisasi Jadwal Mahasiswa
 │    │    │    ├── ProfileController.php
 │    │    │    └── FaqController.php
 │    │    ├── Middleware/
 │    │    │    ├── EnsureUserIsAdmin.php            # Guard role: admin, superadmin, admin_usuclassmap
 │    │    │    ├── EnsureUserIsAdminFotokopi.php    # Guard role: admin_fotokopi, superadmin
 │    │    │    └── EnsureUserIsCashierFotokopi.php  # Guard role: cashier_fotokopi, superadmin
 │    │    ├── Requests/
 │    │    │    ├── Admin/                       # Form Request Validasi Khusus Admin
 │    │    │    │    ├── BuildingRequest.php
 │    │    │    │    ├── CourseRequest.php
 │    │    │    │    ├── LecturerRequest.php
 │    │    │    │    ├── RoomRequest.php
 │    │    │    │    └── ScheduleRequest.php
 │    │    │    └── Auth/LoginRequest.php
 │    │    └── Resources/
 │    │         └── CopyShopResource.php         # Transformasi JSON API Tempat Fotokopi
 │    ├── Imports/
 │    │    └── ScheduleImport.php               # Parser baris Excel ke Schedule, Course, & Lecturer
 │    ├── Jobs/
 │    │    └── RegenerateRdfJob.php             # Antrean asinkron regenerasi graf ontologi RDF
 │    ├── Models/
 │    │    ├── User.php                         # Entitas pengguna dengan pembagian RBAC multi-role
 │    │    ├── Building.php                     # Entitas master gedung kampus & koordinat GPS
 │    │    ├── Room.php                         # Entitas ruangan kuliah & referensi media 360
 │    │    ├── RoomMedia.php                    # Entitas multimedia ruangan (Foto, 360, Video POV, Hotspot)
 │    │    ├── Lecturer.php                     # Entitas dosen pengampu
 │    │    ├── Course.php                       # Entitas mata kuliah (MKWK, MKWU, Prodi)
 │    │    ├── Schedule.php                     # Entitas jadwal kuliah & relasi pivot
 │    │    ├── FotocopyStore.php                # Entitas mitra toko fotokopi (Domain Transaksional)
 │    │    ├── FotocopyService.php              # Entitas katalog jasa print/fotokopi/jilid
 │    │    ├── FotocopyOrder.php                # Entitas pesanan cetak & dokumen mahasiswa
 │    │    ├── FotocopyOrderItem.php            # Rincian item pesanan cetak
 │    │    ├── PhotocopyPlace.php               # Entitas direktori fotokopi (Domain Direktori Tim/Google)
 │    │    ├── CopyShop.php                     # Subclass alias pembungkus PhotocopyPlace
 │    │    ├── PhotocopyItem.php                # Entitas item harga direktori
 │    │    ├── Service.php                      # Master kategori tag layanan percetakan
 │    │    └── ShopReview.php                   # Ulasan Google Maps tempat fotokopi
 │    ├── Observers/
 │    │    └── ScheduleObserver.php             # Domain Event Listener mutasi model Schedule
 │    ├── Providers/
 │    │    └── AppServiceProvider.php           # Dependency Injection & IoC Repository Bindings
 │    ├── Repositories/                         # Clean Architecture Layer (Contracts & Implementations)
 │    │    ├── Contracts/                       # Interface Base, Building, Course, Lecturer, Room, Schedule
 │    │    └── Eloquent/                        # Implementasi Eloquent Repository
 │    └── Services/
 │         ├── GooglePlacesSyncService.php      # Integrasi SerpApi, NLP parser, Haversine matching
 │         ├── RdfGeneratorService.php          # Serializer RDF/XML EasyRdf & HTTP Fuseki Uploader
 │         └── SparqlService.php                # SPARQL 1.1 Client dengan Circuit Breaker ke MySQL
 ├── bootstrap/
 │    └── app.php                               # Definisi kernel, routing web/api, & middleware aliases
 ├── config/
 │    ├── app.php, auth.php, database.php...
 │    └── sparql.php                            # Konfigurasi endpoint Fuseki, URI namespaces, file paths
 ├── database/
 │    ├── migrations/                           # Skema DDL tabel relasional (users, schedules, fotocopy)
 │    └── seeders/                              # Seeder data awal (Admin, Kampus, Gedung, Jadwal, Toko)
 ├── resources/
 │    ├── css/app.css                           # Tailwind CSS v4 source
 │    ├── js/
 │    │    ├── app.js                           # Entry point Alpine.js
 │    │    ├── bootstrap.js                     # Inisialisasi Axios
 │    │    ├── map.js                           # Logika Leaflet GIS, routing Google Maps, filter SPARQL
 │    │    └── grep.exe.stackdump               # ⚠️ File dump error Windows (Dead file)
 │    └── views/
 │         ├── admin/                           # Blade View Admin Akademik & Spatial Map
 │         ├── admin-fotokopi/                  # Blade View Admin Toko & Katalog
 │         ├── cashier-fotokopi/                # Blade View Kasir, Antrean, & Cetak SPK
 │         ├── components/layouts/              # Layout Blade Modular (admin, fotokopi, kasir)
 │         ├── fotocopy/                        # Blade View Publik Pemesanan Fotokopi
 │         ├── public/                          # Blade View Direktori Fotokopi Alternatif
 │         └── map/index.blade.php              # Blade View Peta Spasial Publik
 ├── routes/
 │    ├── web.php                               # Definisi route Web Admin, Kasir, & Publik
 │    ├── api.php                               # Definisi endpoint API & integrasi Chrome Extension
 │    └── auth.php                              # Rute autentikasi Laravel Breeze
 ├── storage/app/rdf/
 │    └── schedules.rdf                         # Berkas RDF/XML graf ontologi jadwal
 ├── usu-extension/                             # Source code Google Chrome Extension (Manifest v3)
 └── usu-scraper/                               # Script otomasi scraping data kampus & foto riil
```

### 2.2. Inventarisasi File Kunci Berdasarkan Tanggung Jawab Layer
- **Layer Presentasi & Routing:** `routes/web.php`, `routes/api.php`, `app/Http/Controllers/Admin/*`, `resources/views/layouts/admin.blade.php`.
- **Layer Keamanan & Otorisasi:** `app/Http/Middleware/EnsureUserIsAdmin.php`, `EnsureUserIsAdminFotokopi.php`, `EnsureUserIsCashierFotokopi.php`, `app/Models/User.php`.
- **Layer Validasi Data:** `app/Http/Requests/Admin/*` (`BuildingRequest`, `CourseRequest`, `LecturerRequest`, `RoomRequest`, `ScheduleRequest`).
- **Layer Bisnis & Domain:** `app/Observers/ScheduleObserver.php`, `app/Jobs/RegenerateRdfJob.php`, `app/Services/RdfGeneratorService.php`, `app/Services/SparqlService.php`.
- **Layer Data Access (Divergent):** `app/Repositories/Contracts/*` dan `app/Repositories/Eloquent/*` (Terdaftar di IoC Container tapi dilewati oleh controller yang langsung menggunakan Model Eloquent).
- **Layer Integrasi Eksternal:** `app/Console/Commands/SyncUsuCopyShops.php`, `usu-extension/content.js`.

---

## 3. Inventarisasi Lengkap Route & Modul Admin

### 3.1. Tabel Inventarisasi Seluruh Route Admin & Turunannya
Berikut adalah pemetaan menyeluruh terhadap seluruh rute administratif, rute operasional kasir, rute spasial dev, serta rute inspeksi foto pada sistem USUStarterMap:

| Modul Fungsional | HTTP Method | Route Name | URI / Endpoint | Action (Controller@Method) | Middleware Terpasang | Deskripsi Singkat & Operasi Data |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Admin Akademik** | `GET` | `admin.dashboard` | `/admin/dashboard` | `Admin\DashboardController@index` | `web`, `auth`, `admin` | Menampilkan ringkasan metrik jadwal, dosen, gedung, ruangan, dan 5 jadwal terbaru |
| **Admin Akademik** | `GET` | `admin.schedules.index` | `/admin/schedules` | `Admin\ScheduleController@index` | `web`, `auth`, `admin` | Menampilkan tabel jadwal kuliah dengan pencarian nama MK, kode, atau dosen (Paginate 15) |
| **Admin Akademik** | `GET` | `admin.schedules.create` | `/admin/schedules/create` | `Admin\ScheduleController@create` | `web`, `auth`, `admin` | Form tambah jadwal baru; memuat data master MK, dosen, ruangan, dan hari |
| **Admin Akademik** | `POST` | `admin.schedules.store` | `/admin/schedules` | `Admin\ScheduleController@store` | `web`, `auth`, `admin` | Menyimpan jadwal baru (`Schedule::create`), memicu `ScheduleObserver` -> `RegenerateRdfJob` |
| **Admin Akademik** | `GET` | `admin.schedules.edit` | `/admin/schedules/{schedule}/edit` | `Admin\ScheduleController@edit` | `web`, `auth`, `admin` | Form edit jadwal yang dipilih berdasarkan ID |
| **Admin Akademik** | `PUT/PATCH` | `admin.schedules.update` | `/admin/schedules/{schedule}` | `Admin\ScheduleController@update` | `web`, `auth`, `admin` | Memperbarui jadwal; memicu observer update -> `RegenerateRdfJob` |
| **Admin Akademik** | `DELETE` | `admin.schedules.destroy` | `/admin/schedules/{schedule}` | `Admin\ScheduleController@destroy` | `web`, `auth`, `admin` | Menghapus jadwal; memicu observer delete -> `RegenerateRdfJob` |
| **Admin Akademik** | `GET` | `admin.lecturers.index` | `/admin/lecturers` | `Admin\LecturerController@index` | `web`, `auth`, `admin` | Daftar dosen dengan jumlah jadwal terkait (`schedules_count`) & pencarian NIP/nama |
| **Admin Akademik** | `GET` | `admin.lecturers.create` | `/admin/lecturers/create` | `Admin\LecturerController@create` | `web`, `auth`, `admin` | Form tambah master dosen baru |
| **Admin Akademik** | `POST` | `admin.lecturers.store` | `/admin/lecturers` | `Admin\LecturerController@store` | `web`, `auth`, `admin` | Menyimpan dosen baru (`Lecturer::create`) dengan validasi unik NIP dan email |
| **Admin Akademik** | `GET` | `admin.lecturers.edit` | `/admin/lecturers/{lecturer}/edit`| `Admin\LecturerController@edit` | `web`, `auth`, `admin` | Form edit profil dan kontak dosen |
| **Admin Akademik** | `PUT/PATCH` | `admin.lecturers.update` | `/admin/lecturers/{lecturer}` | `Admin\LecturerController@update` | `web`, `auth`, `admin` | Memperbarui data dosen (Nama, NIP, Email, Telepon) |
| **Admin Akademik** | `DELETE` | `admin.lecturers.destroy` | `/admin/lecturers/{lecturer}` | `Admin\LecturerController@destroy` | `web`, `auth`, `admin` | Menghapus dosen dari sistem |
| **Admin Akademik** | `GET` | `admin.buildings.index` | `/admin/buildings` | `Admin\BuildingController@index` | `web`, `auth`, `admin` | Daftar gedung kampus, koordinat GPS, dan jumlah ruangan terkait (`rooms_count`) |
| **Admin Akademik** | `GET` | `admin.buildings.create` | `/admin/buildings/create` | `Admin\BuildingController@create` | `web`, `auth`, `admin` | Form pendaftaran gedung baru (Nama, Kode, Fakultas, Latitude, Longitude, Lantai) |
| **Admin Akademik** | `POST` | `admin.buildings.store` | `/admin/buildings` | `Admin\BuildingController@store` | `web`, `auth`, `admin` | Menyimpan gedung baru (`Building::create`) |
| **Admin Akademik** | `GET` | `admin.buildings.edit` | `/admin/buildings/{building}/edit` | `Admin\BuildingController@edit` | `web`, `auth`, `admin` | Form edit gedung dan koordinat geografis |
| **Admin Akademik** | `PUT/PATCH` | `admin.buildings.update` | `/admin/buildings/{building}` | `Admin\BuildingController@update` | `web`, `auth`, `admin` | Memperbarui data gedung dan pin GPS peta |
| **Admin Akademik** | `DELETE` | `admin.buildings.destroy` | `/admin/buildings/{building}` | `Admin\BuildingController@destroy` | `web`, `auth`, `admin` | Menghapus gedung dari basis data |
| **Admin Akademik** | `GET` | `admin.rooms.index` | `/admin/rooms` | `Admin\RoomController@index` | `web`, `auth`, `admin` | Daftar ruangan per gedung, lantai, kapasitas, dan status ketersediaan foto 360 |
| **Admin Akademik** | `GET` | `admin.rooms.create` | `/admin/rooms/create` | `Admin\RoomController@create` | `web`, `auth`, `admin` | Form tambah ruangan beserta input file unggah foto panorama 360 |
| **Admin Akademik** | `POST` | `admin.rooms.store` | `/admin/rooms` | `Admin\RoomController@store` | `web`, `auth`, `admin` | Menyimpan ruangan & upload file foto 360 ke disk public `rooms/360` |
| **Admin Akademik** | `GET` | `admin.rooms.edit` | `/admin/rooms/{room}/edit` | `Admin\RoomController@edit` | `web`, `auth`, `admin` | Form edit data ruangan dan penggantian file foto panorama 360 |
| **Admin Akademik** | `PUT/PATCH` | `admin.rooms.update` | `/admin/rooms/{room}` | `Admin\RoomController@update` | `web`, `auth`, `admin` | Memperbarui ruangan; menghapus foto 360 lama di storage jika ada file baru |
| **Admin Akademik** | `DELETE` | `admin.rooms.destroy` | `/admin/rooms/{room}` | `Admin\RoomController@destroy` | `web`, `auth`, `admin` | Menghapus ruangan dan menghapus berkas fisik foto 360 di disk |
| **Admin Akademik** | `GET` | `admin.courses.index` | `/admin/courses` | `Admin\CourseController@index` | `web`, `auth`, `admin` | Daftar mata kuliah, kode, SKS, tipe (MKWK/MKWU/Prodi), dan jumlah jadwal |
| **Admin Akademik** | `GET` | `admin.courses.create` | `/admin/courses/create` | `Admin\CourseController@create` | `web`, `auth`, `admin` | Form tambah mata kuliah baru |
| **Admin Akademik** | `POST` | `admin.courses.store` | `/admin/courses` | `Admin\CourseController@store` | `web`, `auth`, `admin` | Menyimpan data mata kuliah (`Course::create`) |
| **Admin Akademik** | `GET` | `admin.courses.edit` | `/admin/courses/{course}/edit` | `Admin\CourseController@edit` | `web`, `auth`, `admin` | Form edit data mata kuliah |
| **Admin Akademik** | `PUT/PATCH` | `admin.courses.update` | `/admin/courses/{course}` | `Admin\CourseController@update` | `web`, `auth`, `admin` | Memperbarui kode, nama, SKS, atau kategori mata kuliah |
| **Admin Akademik** | `DELETE` | `admin.courses.destroy` | `/admin/courses/{course}` | `Admin\CourseController@destroy` | `web`, `auth`, `admin` | Menghapus mata kuliah |
| **Admin Akademik** | `GET` | `admin.import.index` | `/admin/import` | `Admin\ImportController@index` | `web`, `auth`, `admin` | Halaman antarmuka unggah berkas bulk import Excel jadwal |
| **Admin Akademik** | `POST` | `admin.import.store` | `/admin/import` | `Admin\ImportController@store` | `web`, `auth`, `admin` | Memproses impor file Excel (.xlsx/.xls/.csv) via `ScheduleImport` |
| **Admin Akademik** | `GET` | `admin.import.template` | `/admin/import/template` | `Admin\ImportController@downloadTemplate` | `web`, `auth`, `admin` | Mengunduh file template Excel resmi `template_jadwal_usuclassmap.xlsx` |
| **Admin Akademik** | `GET` | `admin.import.download` | `/admin/import/download-template` | `Admin\ImportController@downloadTemplate` | `web`, `auth`, `admin` | Alias rute unduh template Excel |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.dashboard` | `/admin-fotokopi/dashboard` | `AdminFotokopi\DashboardController@index` | `web`, `auth`, `admin.fotokopi` | Dashboard fotokopi: Metrik total omset, status pesanan, toko terlaris |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.stores.index` | `/admin-fotokopi/stores` | `AdminFotokopi\StoreController@index` | `web`, `auth`, `admin.fotokopi` | Daftar mitra toko fotokopi, lokasi, status operasional buka/tutup |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.stores.create`| `/admin-fotokopi/stores/create` | `AdminFotokopi\StoreController@create` | `web`, `auth`, `admin.fotokopi` | Form tambah toko fotokopi; memuat pilihan kasir penanggung jawab |
| **Admin Fotokopi** | `POST` | `admin-fotokopi.stores.store` | `/admin-fotokopi/stores` | `AdminFotokopi\StoreController@store` | `web`, `auth`, `admin.fotokopi` | Menyimpan mitra toko baru beserta koordinat GPS, kontak WA, medsos |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.stores.edit` | `/admin-fotokopi/stores/{store}/edit` | `AdminFotokopi\StoreController@edit` | `web`, `auth`, `admin.fotokopi` | Form edit data toko fotokopi |
| **Admin Fotokopi** | `PUT/PATCH` | `admin-fotokopi.stores.update` | `/admin-fotokopi/stores/{store}` | `AdminFotokopi\StoreController@update` | `web`, `auth`, `admin.fotokopi` | Memperbarui informasi toko mitra |
| **Admin Fotokopi** | `DELETE` | `admin-fotokopi.stores.destroy` | `/admin-fotokopi/stores/{store}` | `AdminFotokopi\StoreController@destroy` | `web`, `auth`, `admin.fotokopi` | Menghapus mitra toko fotokopi |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.services.index` | `/admin-fotokopi/services` | `AdminFotokopi\ServiceController@index` | `web`, `auth`, `admin.fotokopi` | Katalog master jasa cetak (Print, Fotocopy, Jilid, ATK) per toko |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.services.create`| `/admin-fotokopi/services/create` | `AdminFotokopi\ServiceController@create` | `web`, `auth`, `admin.fotokopi` | Form tambah item layanan cetak baru |
| **Admin Fotokopi** | `POST` | `admin-fotokopi.services.store` | `/admin-fotokopi/services` | `AdminFotokopi\ServiceController@store` | `web`, `auth`, `admin.fotokopi` | Menyimpan jasa cetak ke katalog toko |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.services.edit` | `/admin-fotokopi/services/{service}/edit` | `AdminFotokopi\ServiceController@edit` | `web`, `auth`, `admin.fotokopi` | Form edit item katalog layanan cetak |
| **Admin Fotokopi** | `PUT/PATCH` | `admin-fotokopi.services.update`| `/admin-fotokopi/services/{service}` | `AdminFotokopi\ServiceController@update` | `web`, `auth`, `admin.fotokopi` | Memperbarui tarif, nama layanan, atau status ketersediaan |
| **Admin Fotokopi** | `DELETE` | `admin-fotokopi.services.destroy`| `/admin-fotokopi/services/{service}` | `AdminFotokopi\ServiceController@destroy` | `web`, `auth`, `admin.fotokopi` | Menghapus item layanan cetak dari katalog |
| **Admin Fotokopi** | `GET` | `admin-fotokopi.reports.index` | `/admin-fotokopi/reports` | `AdminFotokopi\ReportController@index` | `web`, `auth`, `admin.fotokopi` | Rekapitulasi laporan transaksi pesanan, filter status, metode bayar, omset |
| **Kasir Fotokopi** | `GET` | `cashier-fotokopi.orders.index` | `/cashier-fotokopi/orders` | `CashierFotokopi\OrderController@index` | `web`, `auth`, `cashier.fotokopi` | Antarmuka kasir antrean pesanan masuk (Menunggu, Diproses, Selesai) |
| **Kasir Fotokopi** | `PATCH` | `cashier-fotokopi.orders.status` | `/cashier-fotokopi/orders/{order}/status` | `CashierFotokopi\OrderController@updateStatus` | `web`, `auth`, `cashier.fotokopi` | Kasir mengubah status pesanan (Otomatis tandai lunas jika selesai) |
| **Kasir Fotokopi** | `GET` | `cashier-fotokopi.orders.file` | `/cashier-fotokopi/orders/{order}/file` | `CashierFotokopi\OrderController@downloadFile` | `web`, `auth`, `cashier.fotokopi` | Mengunduh file dokumen cetak mahasiswa dari server storage |
| **Kasir Fotokopi** | `GET` | `cashier-fotokopi.orders.print`| `/cashier-fotokopi/orders/{order}/print` | `CashierFotokopi\OrderController@printJob` | `web`, `auth`, `cashier.fotokopi` | Surat Perintah Kerja (SPK) Cetak dan nota transaksi format printer thermal |
| **Kasir Fotokopi** | `GET` | `cashier-fotokopi.orders.pos` | `/cashier-fotokopi/pos` | `CashierFotokopi\OrderController@createPos` | `web`, `auth`, `cashier.fotokopi` | Form kasir Point-of-Sale (POS) untuk pesanan walk-in di tempat |
| **Kasir Fotokopi** | `POST` | `cashier-fotokopi.orders.pos.store` | `/cashier-fotokopi/pos` | `CashierFotokopi\OrderController@storePos` | `web`, `auth`, `cashier.fotokopi` | Menyimpan transaksi walk-in kasir dan item pesanan langsung |
| **Dev Spatial Map** | `GET` | `admin.map.index` | `/admin-sementara-/map` | `AdminMapController@index` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | GIS Dashboard visual untuk memetakan gedung, ruangan, jadwal, dan 360 media |
| **Dev Spatial Map** | `POST` | `admin.map.storeBuilding` | `/admin-sementara-/map/building` | `AdminMapController@storeBuilding` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | API AJAX pembuatan gedung baru langsung dari peta |
| **Dev Spatial Map** | `PUT` | `admin.map.updateBuilding`| `/admin-sementara-/map/building/{building}` | `AdminMapController@updateBuilding` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | API AJAX pergeseran pin GPS gedung di peta |
| **Dev Spatial Map** | `POST` | `admin.map.storeRoom` | `/admin-sementara-/map/room` | `AdminMapController@storeRoom` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | API AJAX pembuatan ruangan baru pada gedung terpilih |
| **Dev Spatial Map** | `PUT` | `admin.map.updateRoom` | `/admin-sementara-/map/room/{room}` | `AdminMapController@updateRoom` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | API AJAX pembaruan atribut ruangan |
| **Dev Spatial Map** | `POST` | `admin.map.storeSchedule` | `/admin-sementara-/map/schedule` | `AdminMapController@storeSchedule` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | API AJAX penambahan jadwal kuliah dan auto-create mata kuliah |
| **Dev Spatial Map** | `GET` | `admin.map.roomsByBuilding` | `/admin-sementara-/map/rooms/{building}` | `AdminMapController@getRoomsByBuilding` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Mengambil data ruangan berdasarkan ID gedung untuk dropdown dinamis |
| **Dev Spatial Map** | `GET` | `admin.map.roomMedia.index` | `/admin-sementara-/map/rooms/{room}/media` | `AdminMapController@getRoomMedia` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Mengambil daftar multimedia ruangan (Foto, 360, Video POV) |
| **Dev Spatial Map** | `POST` | `admin.map.roomMedia.store` | `/admin-sementara-/map/rooms/{room}/media` | `AdminMapController@storeRoomMedia` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Mengunggah multimedia ruangan hingga 50MB tanpa autentikasi |
| **Dev Spatial Map** | `PUT` | `admin.map.roomMedia.updateMetadata` | `/admin-sementara-/map/media/{media}/metadata` | `AdminMapController@updateMediaMetadata` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Menyimpan konfigurasi hotspot JSON panorama 360° |
| **Dev Spatial Map** | `DELETE`| `admin.map.roomMedia.destroy` | `/admin-sementara-/map/media/{media}` | `AdminMapController@destroyRoomMedia` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Menghapus berkas media ruangan dari server storage |
| **Photo Inspector** | `GET` | `admin.cek-foto` | `/admin/cek-foto` | `PhotoInspectorController@index` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Utilitas inspeksi visual panorama 360 dan query Pitch/Yaw SPARQL |
| **Photo Inspector** | `GET` | `admin.cek-foto-p` | `/admin/cek-foto-p` | `PhotoInspectorController@indexCylinder` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Inspeksi panorama 360 mode silinder |
| **Photo Inspector** | `POST` | `admin.cek-foto.saveCalibration` | `/admin/cek-foto/kalibrasi` | `PhotoInspectorController@saveCalibration` | **HANYA `web`** ⚠️ *(UNPROTECTED)* | Menyimpan koordinat Pitch & Yaw langsung ke Fuseki via SPARQL UPDATE |

### 3.2. Matriks Hak Akses Peran Pengguna (RBAC)
Berdasarkan implementasi pada `app/Models/User.php` dan middleware di `app/Http/Middleware/`:

| Nama Peran (`role`) | Akses `/admin/*` (Akademik) | Akses `/admin-fotokopi/*` | Akses `/cashier-fotokopi/*` | Akses `/admin-sementara-/map` | Akses `/admin/cek-foto` |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `superadmin` | ✅ Penuh (Universal) | ✅ Penuh (Universal) | ✅ Penuh (Universal) | ✅ Terbuka (Publik) | ✅ Terbuka (Publik) |
| `admin_usuclassmap` / `admin` | ✅ Penuh | ❌ Ditolak | ❌ Ditolak | ✅ Terbuka (Publik) | ✅ Terbuka (Publik) |
| `admin_fotokopi` | ❌ Ditolak | ✅ Penuh | ❌ Ditolak | ✅ Terbuka (Publik) | ✅ Terbuka (Publik) |
| `cashier_fotokopi` | ❌ Ditolak | ❌ Ditolak | ✅ Penuh | ✅ Terbuka (Publik) | ✅ Terbuka (Publik) |
| `user` (Mahasiswa / Tamu) | ❌ Ditolak | ❌ Ditolak | ❌ Ditolak | ⚠️ **Bisa Akses (Vulnerable)** | ⚠️ **Bisa Akses (Vulnerable)** |

---

## 4. Analisis Mendalam Setiap Fitur Admin

### 4.1. Modul Admin Akademik: Dashboard & Master Data CRUD
Modul ini bertindak sebagai panel pusat pengelolaan data operasional perkuliahan Universitas Sumatera Utara.

#### A. Alur Kerja End-to-End
1. **Pencatatan Master Data Dependen:** Admin menginput data gedung (`Building`), ruangan (`Room`), dosen pengampu (`Lecturer`), dan mata kuliah (`Course`).
2. **Penyusunan Jadwal Kuliah (`Schedule`):** Admin menyusun jadwal yang memetakan hubungan antara Mata Kuliah, Dosen, Ruangan, Hari, Jam Mulai, Jam Selesai, Semester, dan Kelompok Kelas.
3. **Penyelarasan Triplestore:** Ketika jadwal disimpan (`Schedule::create`), `ScheduleObserver` menangkap event `created` dan men-dispatch `RegenerateRdfJob` ke antrean.

#### B. File-File Terlibat
- **Controllers:** `App\Http\Controllers\Admin\DashboardController`, `ScheduleController`, `LecturerController`, `BuildingController`, `RoomController`, `CourseController`.
- **FormRequests:** `App\Http\Requests\Admin\ScheduleRequest`, `LecturerRequest`, `BuildingRequest`, `RoomRequest`, `CourseRequest`.
- **Models:** `App\Models\Schedule`, `Lecturer`, `Building`, `Room`, `Course`.
- **Views:** `resources/views/admin/dashboard.blade.php`, `resources/views/admin/{schedules,lecturers,buildings,rooms,courses}/*.blade.php`.
- **Layout:** `resources/views/layouts/admin.blade.php`.

#### C. Operasi Database & Pemrosesan Khusus
- Menggunakan Eloquent ORM dengan *eager loading* berantai (`with(['room.building', 'lecturer', 'course'])`) untuk meminimalisir N+1 query.
- Pada `RoomController@store` dan `update`, terdapat penanganan berkas upload citra 360° yang disimpan ke disk public `rooms/360`, serta penghapusan berkas lama via `Storage::disk('public')->delete()` saat data ruangan diperbarui atau dihapus.

---

### 4.2. Modul Bulk Ingestion: Batch Import Excel & Template Export
Modul ini memungkinkan staf akademik mengunggah ribuan data jadwal perkuliahan secara massal menggunakan file Excel (.xlsx, .xls) atau CSV.

#### A. Alur Kerja End-to-End
1. Admin mengunduh template resmi melalui rute `admin.import.template` yang diekspor oleh `App\Exports\ScheduleTemplateExport`.
2. Admin mengunggah file spreadsheet terisi melalui form pada rute `admin.import.store`.
3. File divalidasi oleh `ImportController` (maksimal 10MB, tipe mimes valid).
4. `Maatwebsite\Excel\Facades\Excel::import(new ScheduleImport, $file)` mengeksekusi pemetaan per baris (*row-by-row mapping*).
5. Pada setiap baris:
   - Sistem mencari atau membuat mata kuliah baru via `Course::firstOrCreate(['code' => $row['kode_mk']], ...)`.
   - Sistem mencari atau membuat dosen baru via `Lecturer::firstOrCreate(['name' => $row['nama_dosen']], ...)`.
   - Sistem memverifikasi keberadaan ruangan berdasarkan nama (`Room::where('name', $row['nama_ruangan'])->first()`). Jika ruangan tidak ditemukan, baris dicatat sebagai kegagalan.
   - Instansiasi objek `new Schedule([...])` disimpan ke basis data.

#### B. File-File Terlibat
- **Controller:** `App\Http\Controllers\Admin\ImportController`.
- **Import Engine:** `App\Imports\ScheduleImport` (mengimplementasikan `ToModel`, `WithHeadingRow`, `WithValidation`, `SkipsOnError`).
- **Export Engine:** `App\Exports\ScheduleTemplateExport`.
- **View:** `resources/views/admin/import/index.blade.php`.

---

### 4.3. Modul Admin Fotokopi: Manajemen Mitra Toko, Katalog, & Laporan Keuangan
Modul ini dikhususkan bagi administrator layanan percetakan untuk mengelola jaringan mitra fotokopi yang beroperasi di dalam lingkungan kampus USU.

#### A. Alur Kerja End-to-End
1. **Manajemen Mitra Toko (`StoreController`):** Admin mendaftarkan toko baru, mengatur titik koordinat GPS (*latitude* & *longitude*), jam operasional, status buka/tutup, nomor WhatsApp, serta menugaskan akun kasir (`user_id` dengan role `cashier_fotokopi`). Sistem secara otomatis men-generate URL slug unik menggunakan `Str::slug($name) . '-' . Str::random(5)`.
2. **Katalog Layanan Cetak (`ServiceController`):** Admin mendefinisikan item jasa per toko (contoh: Cetak Skripsi Hardcover, Print Warna A4, Fotokopi Bolak-Balik, Jilid Lakban) lengkap dengan kategori, harga satuan, dan unit perhitungan.
3. **Rekapitulasi Laporan Transaksi (`ReportController`):** Memonitor seluruh transaksi dari seluruh toko, memfilter berdasarkan status pembayaran (*paid* / *pending*), status pengerjaan (*Menunggu*, *Diproses*, *Selesai*, *Dibatalkan*), dan mengakumulasi total omset riil.

#### B. File-File Terlibat
- **Controllers:** `App\Http\Controllers\AdminFotokopi\DashboardController`, `StoreController`, `ServiceController`, `ReportController`.
- **Models:** `App\Models\FotocopyStore`, `FotocopyService`, `FotocopyOrder`, `FotocopyOrderItem`, `User`.
- **Views:** Direktori `resources/views/admin-fotokopi/**/*.blade.php`.
- **Layout:** `resources/views/components/layouts/admin-fotokopi.blade.php`.

---

### 4.4. Modul Kasir & POS Fotokopi: Antrean Pesanan, Cetak Dokumen, & Walk-In POS
Modul operasional lapangan yang digunakan oleh operator gerai percetakan di kampus.

#### A. Alur Kerja End-to-End
1. **Manajemen Antrean Pesanan Masuk (`OrderController@index`):** Kasir melihat pesanan yang masuk dari mahasiswa secara real-time. Jika kasir bukan admin global, query otomatis dibatasi hanya pada toko yang ditugaskan kepada kasir tersebut (`FotocopyStore::where('user_id', $user->id)->first()`).
2. **Pengunduhan Berkas Cetak (`OrderController@downloadFile`):** Kasir mengunduh dokumen tugas/skripsi mahasiswa (PDF/Word) yang tersimpan di `storage/app/public/fotocopy_docs/`.
3. **Pencetakan SPK / Nota Thermal (`OrderController@printJob`):** Menampilkan tampilan ramah cetak (*print-friendly dialog*) berukuran 58mm/80mm untuk Surat Perintah Kerja (SPK) instruksi cetak dan struk kasir.
4. **Pembaruan Status (`OrderController@updateStatus`):** Mengubah status pesanan dari *Menunggu* ke *Diproses*, dan saat diubah menjadi *Selesai*, status pembayaran otomatis diperbarui menjadi *paid*.
5. **Point-of-Sale (POS) Walk-In (`OrderController@storePos`):** Mencatat pelanggan langsung di meja kasir, memilih layanan dari katalog, menghitung total harga secara otomatis di backend, mencatat metode pembayaran (Tunai/QRIS), dan mengunggah dokumen fisik jika ada.

#### B. File-File Terlibat
- **Controller:** `App\Http\Controllers\CashierFotokopi\OrderController`.
- **Models:** `App\Models\FotocopyOrder`, `FotocopyOrderItem`, `FotocopyStore`, `FotocopyService`.
- **Views:** `resources/views/cashier-fotokopi/orders/index.blade.php`, `pos.blade.php`, `print.blade.php`.
- **Layout:** `resources/views/components/layouts/cashier-fotokopi.blade.php`.

---

### 4.5. Modul Spatial Map Dashboard: Manajemen Spasial Gedung, Ruangan, & Media 360
Merupakan modul canggih berbasis peta interaktif (Leaflet.js + Alpine.js + Pannellum) yang dirancang untuk memudahkan admin mengelola aset spasial kampus secara visual.

#### A. Alur Kerja End-to-End
1. **Visual GIS Mapping:** Admin membuka peta kampus, melihat seluruh marker gedung, dan dapat menggeser (*drag*) posisi pin koordinat GPS secara langsung untuk memperbarui *latitude* & *longitude* gedung via AJAX PUT `/admin-sementara-/map/building/{id}`.
2. **Hierarchical Room & Schedule Creation:** Admin dapat memilih gedung, melihat daftar ruangan, menambah ruangan baru di lantai tertentu, dan langsung menjadwalkan mata kuliah pada ruangan tersebut dari satu panel antarmuka tanpa berpindah halaman.
3. **Multimedia Imersif Ruangan (Phase 7.3):**
   - Admin dapat mengunggah tiga jenis media untuk sebuah ruangan: Foto Standar, Foto 360° Equirectangular, dan Video POV Rute Jalan Pejalan Kaki (MP4) hingga ukuran 50MB.
   - Admin menandai salah satu media sebagai *primary* yang otomatis menyinkronkan kolom `rooms.photo_360_path`.
   - Admin dapat mengatur konfigurasi hotspot panorama 360° interaktif (titik lompat antar ruangan) yang disimpan dalam format JSON pada kolom `room_media.metadata`.

#### B. File-File Terlibat
- **Controller:** `App\Http\Controllers\AdminMapController`.
- **Models:** `App\Models\Building`, `Room`, `Schedule`, `Course`, `Lecturer`, `RoomMedia`.
- **View:** `resources/views/admin/map.blade.php` (Memuat lebih dari 1.330 baris kode Blade dan komponen Alpine.js `adminMapDashboard()`).

---

### 4.6. Modul 360 Photo Inspector: Kalibrasi Visual & Sinkronisasi SPARQL Fuseki
Modul diagnostik dan kalibrasi visual berbasis Web Semantik untuk mengatur sudut pandang awal (*pitch* dan *yaw*) foto panorama 360°.

#### A. Alur Kerja End-to-End
1. **Pemindaian Berkas Fisik & Penggabungan Data Semantik (`index` / `indexCylinder`):**
   - Sistem memindai berkas foto `.jpg`/`.jpeg` di disk fisik `storage/app/public/foto360` atau `foto360p`.
   - Mengirim kueri SPARQL `SELECT ?filename ?pitch ?yaw WHERE { ... }` ke Apache Jena Fuseki endpoint (`http://localhost:3030/usuclassmap/query`).
   - Menggabungkan data ukuran berkas, waktu modifikasi, dan nilai kalibrasi Pitch & Yaw dari Fuseki ke dalam array terpadu.
2. **Kalibrasi Interaktif via Pannellum Viewer:** Operator memutar panorama 360° ke orientasi pintu masuk/papan tulis utama, lalu mengklik tombol simpan orientasi.
3. **SPARQL Update UPSERT (`saveCalibration`):**
   Controller mengeksekusi SPARQL `DELETE { ... } INSERT { ... } WHERE { ... }` langsung melalui HTTP POST ke endpoint Fuseki Update (`http://localhost:3030/usuclassmap/update`) tanpa melalui database MySQL:
   ```sparql
   PREFIX ucm: <http://example.org/usuclassmap/>
   PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
   DELETE {
       ucm:Photo_424 ucm:hasFileName ?oldFilename ;
                     ucm:hasPitch ?oldPitch ;
                     ucm:hasYaw ?oldYaw .
   }
   INSERT {
       ucm:Photo_424 ucm:hasFileName "424.jpg" ;
                     ucm:hasPitch "-2.5"^^xsd:float ;
                     ucm:hasYaw "115.4"^^xsd:float .
   }
   WHERE { ... }
   ```

#### B. File-File Terlibat
- **Controller:** `App\Http\Controllers\PhotoInspectorController`.
- **Views:** `resources/views/admin/cek-foto.blade.php`, `resources/views/admin/cek-foto-p.blade.php`.
- **Triplestore Endpoint:** Apache Jena Fuseki (`FUSEKI_QUERY_URL`, `FUSEKI_UPDATE_URL`).

---

### 4.7. Antarmuka Frontend & Aset Vite Admin
Antarmuka admin USUStarterMap dirancang dengan tata letak modern bernuansa *Emerald Green* khas Universitas Sumatera Utara.

1. **Komposisi Layout Modular:**
   - `resources/views/layouts/admin.blade.php`: Layout utama Admin Akademik dengan sidebar dinamis yang mendeteksi hak akses Super Admin untuk berpindah instan ke panel fotokopi atau kasir.
   - `resources/views/components/layouts/admin-fotokopi.blade.php`: Layout sidebar khusus fitur operasional toko fotokopi.
   - `resources/views/components/layouts/cashier-fotokopi.blade.php`: Layout khusus antrean pesanan dan POS kasir dengan badge indikator pesanan aktif.
2. **Aset Vite & Frontend Bundling:**
   - Konfigurasi `vite.config.js` mendaftarkan tiga berkas input utama:
     - `resources/css/app.css` (Tailwind CSS v4).
     - `resources/js/app.js` (Inisialisasi global Alpine.js).
     - `resources/js/map.js` (Logika peta publik Leaflet, pencarian SPARQL, dan rute navigasi).
3. **Penyimpangan Aset (Frontend Inconsistency):**
   - Halaman `admin/map.blade.php` tidak mengimpor bundle JS terkompilasi, melainkan menanamkan script Alpine.js *inline* sebesar 1.300+ baris di dalam tag `<script>`.
   - Halaman `admin/cek-foto.blade.php` menggunakan CDN publik luar (`https://cdn.tailwindcss.com` dan `https://cdn.jsdelivr.net/npm/pannellum`) alih-alih mengandalkan pipeline build Vite lokal.

---

## 5. Matriks Keterkaitan Lintas-Modul (Admin ↔ Non-Admin)

### 5.1. Dampak Hilir (Downstream: Admin → Publik/User/API)
Tabel ini memetakan bagaimana setiap tindakan manipulasi data oleh Administrator berdampak langsung pada antarmuka publik mahasiswa dan endpoint API:

| Data / Konfigurasi yang Diubah Admin | File / Method Admin | Mekanisme Penyebaran (Propagation Pipeline) | Komponen Non-Admin yang Terdampak | Pengalaman Pengguna (UX Impact) |
| :--- | :--- | :--- | :--- | :--- |
| **Penyusunan / Edit Jadwal Kuliah** | `Admin\ScheduleController@store`, `update`, `destroy` | `ScheduleObserver` mendeteksi event -> `RegenerateRdfJob` dieksekusi di queue -> Graf RDF/XML dibuat -> Di-upload ke Fuseki via HTTP PUT | 1. API `/api/schedules/search`<br>2. Endpoint `/api/my-schedules`<br>3. Peta GIS Leaflet (`map/index.blade.php` & `map.js`) | Hasil pencarian jadwal mata kuliah publik langsung terbarui; penyorotan kelas mahasiswa berubah warna sesuai jadwal baru |
| **Perubahan Koordinat GPS Gedung** | `Admin\BuildingController@update` & `AdminMapController@updateBuilding` | Update baris tabel `buildings` di MySQL (Catatan: Belum otomatis memicu update Fuseki!) | 1. API `/api/buildings` & `/api/buildings/{id}`<br>2. Pin Marker Peta Leaflet<br>3. Tombol Rute Google Maps pejalan kaki | Posisi pin penanda gedung di peta Leaflet berpindah; rute navigasi Google Maps diarahkan ke koordinat baru |
| **Unggah Foto Panorama 360 Ruangan** | `Admin\RoomController@update` & `AdminMapController@storeRoomMedia` | Berkas disimpan ke `storage/app/public/rooms/360` atau `room_media`; kolom `photo_360_path` di-update | 1. Modal Dialog Detail Ruangan di `/map`<br>2. Viewer Pannellum 360 publik | Mahasiswa dapat melihat tur virtual 360 interior ruangan sebelum mendatangi lokasi kelas |
| **Konfigurasi Hotspot Navigasi 360** | `AdminMapController@updateMediaMetadata` | Update kolom JSON `metadata` pada tabel `room_media` | Viewer 360 pada Peta Spasial | Mahasiswa dapat mengklik panah navigasi (*hotspot*) untuk berpindah ke ruangan sebelahnya di dalam foto 360 |
| **Kalibrasi Pitch & Yaw 360** | `PhotoInspectorController@saveCalibration` | Eksekusi kueri SPARQL UPDATE langsung ke Apache Jena Fuseki dataset | API SPARQL `/api/schedules/search` yang menyertakan data pitch/yaw | Sudut pandang kamera 360 pertama kali terbuka langsung menghadap papan tulis / pintu ruangan |
| **Manajemen Item Katalog Layanan Fotokopi** | `AdminFotokopi\ServiceController@store`, `update`, `destroy` | Update baris tabel `fotocopy_services` di MySQL | 1. Katalog Toko di `/fotocopy/{id}`<br>2. Form Pemesanan di `/fotocopy/{id}/order` | Mahasiswa melihat harga cetak terbaru, item yang sedang habis / tersedia, serta estimasi biaya |
| **Pembaruan Status Pesanan oleh Kasir** | `CashierFotokopi\OrderController@updateStatus` | Update baris tabel `fotocopy_orders` (`order_status` & `payment_status`) | Halaman Pelacakan Pesanan Mahasiswa | Mahasiswa mengetahui dokumennya sedang dicetak atau sudah selesai dan siap diambil di toko |

---

### 5.2. Aliran Hulu (Upstream: Publik/User/Eksternal → Admin)
Tabel ini memetakan bagaimana data atau aktivitas dari sisi mahasiswa, publik, dan sistem eksternal mengalir masuk ke panel admin:

| Sumber Aliran Data (Upstream Source) | Titik Masuk (Entry Point / Route) | Kontroler / Handler Backend | Dampak pada Panel Admin & Kasir | Status Integrasi Saat Ini |
| :--- | :--- | :--- | :--- | :--- |
| **Pemesanan Cetak oleh Mahasiswa** | `POST /fotocopy/{id}/order` | `App\Http\Controllers\FotocopyController@storeOrder` | Muncul seketika sebagai antrean baru di dashboard kasir (`cashier-fotokopi.orders.index`) dan menambah akumulasi omset di laporan admin | **Terintegrasi Penuh** (Namun memiliki celah manipulasi harga yang perlu diperbaiki) |
| **Ekstensi Chrome Mahasiswa (`satu.usu.ac.id`)** | `POST /api/schedule/sync` dan `/api/schedules/sync` | `App\Http\Controllers\Api\ScheduleApiController@sync` | Diharapkan menyinkronkan jadwal kuliah mahasiswa ke database admin | ⚠️ **Stub / Belum Menyimpan**: Hanya dicatat di `storage/logs/laravel.log`, tidak disimpan ke database |
| **Otomasi Login Scraping SATU USU** | `POST /login` (Input NIM & Password) | `App\Http\Controllers\Auth\AuthenticatedSessionController@store` | Diharapkan meng-auto create akun mahasiswa dan jadwal kuliahnya ke database | ⚠️ **Mock / Placeholder**: Method `attemptSatuUsuSync()` di-hardcode me-return `['success' => false]` |
| **Scraping Tempat Fotokopi SerpApi Google** | `php artisan shops:sync-usu` | `App\Console\Commands\SyncUsuCopyShops` -> `GooglePlacesSyncService` | Mengisi data ke tabel `photocopy_places`, `services`, dan `photocopy_reviews` | ⚠️ **Terisolasi dari Panel Admin**: Data masuk ke tabel direktori, tetapi tidak dapat diedit melalui panel Admin Fotokopi |
| **Pendaftaran Mandiri Mahasiswa** | `POST /register` | `App\Http\Controllers\Auth\RegisteredUserController@store` | Membuat entitas baru di tabel `users` dengan role `'user'` | **Terintegrasi Penuh** (Tersedia untuk visualisasi jadwal personal di `/my-schedules`) |

---

### 5.3. Koneksi Implisit, Parsial, & Potensi Keterhubungan yang Belum Terintegrasi

Berdasarkan audit mendalam terhadap basis kode, ditemukan beberapa koneksi tersembunyi, komponen *stub*, dan fitur yang belum terintegrasi utuh:

#### 1. Fragmentasi & Duplikasi Domain Data Fotokopi
Terdapat **dua set model dan tabel database yang berdiri sendiri** untuk menangani bisnis fotokopi kampus tanpa jembatan relasi:
- **Domain A (Direktori Tim & Google Scraper):** Menggunakan tabel `photocopy_places`, `photocopy_items`, `services`, `photocopy_place_services`, dan `photocopy_reviews`. Dikelola oleh `PhotocopyController`, `Api\CopyShopApiController`, dan `SyncUsuCopyShops`. **Sama sekali tidak memiliki antarmuka CRUD di panel Admin**.
- **Domain B (Sistem Transaksional & Kasir):** Menggunakan tabel `fotocopy_stores`, `fotocopy_services`, `fotocopy_orders`, dan `fotocopy_order_items`. Dikelola secara lengkap oleh `AdminFotokopi` dan `CashierFotokopi`.
- **Potensi Integrasi:** Model `CopyShop` yang saat ini hanya berupa alias subclass dari `PhotocopyPlace` dapat dikonsolidasikan, atau data dari `photocopy_places` (hasil scraping SerpApi Google Maps) dapat dimigrasikan ke `fotocopy_stores` agar seluruh tempat fotokopi di sekitar kampus dapat dikelola oleh Admin Fotokopi.

#### 2. Celah Desinkronisasi Dual-Engine (Semantic Web vs Relasional)
- Mekanisme sinkronisasi RDF otomatis (`ScheduleObserver` -> `RegenerateRdfJob`) **hanya dipasang pada model `Schedule`** di `AppServiceProvider::boot()`.
- Model `Building`, `Room`, `Course`, dan `Lecturer` **tidak memiliki Observer**.
- **Dampaknya:** Jika admin memperbarui nama gedung, menggeser koordinat GPS latitude/longitude gedung, mengubah nama dosen, atau mengganti kapasitas ruangan melalui menu Master Data Admin, perubahan tersebut **TIDAK PERNAH dikirim ke Apache Jena Fuseki**. Data pencarian SPARQL publik akan tetap menyajikan data lama (*stale*) hingga ada mutasi pada jadwal kuliah atau perintah manual `php artisan rdf:generate` dijalankan.

#### 3. Endpoint Sinkronisasi Jadwal Mahasiswa Masih Berupa Stub (Mock)
- File `usu-extension/content.js` pada ekstensi Google Chrome mengirimkan payload JSON jadwal mahasiswa ke `http://127.0.0.1:8000/api/schedule/sync`.
- Namun, implementasi `ScheduleApiController@sync` dan `ScheduleController@sync` hanya berisi:
  ```php
  Log::info('[USUStarterMap Sync] Menerima data sinkronisasi...', [...]);
  return response()->json(['status' => 'success', ...]);
  ```
  Data jadwal mahasiswa tidak pernah diparsing atau disimpan ke tabel relasional `schedules` maupun tabel relasi user.

#### 4. Otomasi Scraping Background SATU USU Dinonaktifkan
- Pada `App\Http\Controllers\Auth\AuthenticatedSessionController`:
  Method `attemptSatuUsuSync()` dan `syncSchedulesToLocal()` memiliki blueprint logika scraping menggunakan HTTP Client dan DOMDocument, namun di-hardcode mengembalikan `['success' => false]` dengan seluruh kode parser berada di dalam blok komentar.

#### 5. Dead Code: Lapisan Repository Pattern Tidak Digunakan
- Pada `app/Repositories/`, terdapat arsitektur Clean Architecture lengkap: 6 interface (`BaseRepositoryInterface`, `BuildingRepositoryInterface`, dll.) dan 6 implementasi Eloquent (`BuildingRepository`, dll.) yang di-bind ke container di `AppServiceProvider::register()`.
- **Namun tidak ada satupun Controller di seluruh aplikasi yang meng-inject repository tersebut**. Seluruh Controller Admin, Kasir, dan API memanggil Eloquent secara langsung (`Schedule::query()`, `Building::create()`, dll.). Hal ini menimbulkan *architectural debt* berupa kode mati (*dead code*).

---

## 6. Temuan Keamanan, Bug, & Rekomendasi Perbaikan

### 6.1. Temuan Tingkat Prioritas TINGGI (Critical / High)

#### 🔴 TEMUAN 1: Broken Access Control pada Admin Spatial Map Dashboard
- **Lokasi Kode:** `routes/web.php` (baris 131–146), `App\Http\Controllers\AdminMapController.php`.
- **Deskripsi Masalah:**
  Grup rute `admin-sementara-/map` didefinisikan tanpa middleware autentikasi apa pun (hanya middleware default `web`):
  ```php
  Route::prefix('admin-sementara-/map')->group(function () {
      Route::get('/', [AdminMapController::class, 'index'])->name('admin.map.index');
      Route::post('/building', [AdminMapController::class, 'storeBuilding']);
      Route::put('/building/{building}', [AdminMapController::class, 'updateBuilding']);
      Route::post('/room', [AdminMapController::class, 'storeRoom']);
      Route::put('/room/{room}', [AdminMapController::class, 'updateRoom']);
      Route::post('/schedule', [AdminMapController::class, 'storeSchedule']);
      Route::post('/rooms/{room}/media', [AdminMapController::class, 'storeRoomMedia']);
      Route::delete('/media/{media}', [AdminMapController::class, 'destroyRoomMedia']);
  });
  ```
- **Dampak Keamanan:** Siapa pun pengguna internet tanpa login dapat mengakses URL `/admin-sementara-/map`, membuat/mengubah/menghapus gedung dan ruangan, menambah jadwal perkuliahan, menghapus media ruangan, serta mengunggah berkas multimedia hingga 50MB per request (berpotensi *Storage Flooding* dan serangan *Denial-of-Service*).
- **Rekomendasi Perbaikan:** Pasang middleware `['auth', 'admin']` pada grup rute tersebut, atau pindahkan seluruh endpoint ini ke dalam grup prefix `admin/map` yang telah terlindungi:
  ```php
  Route::prefix('admin/map')
      ->middleware(['auth', 'admin'])
      ->name('admin.map.')
      ->group(function () { ... });
  ```

---

#### 🔴 TEMUAN 2: Broken Access Control & SPARQL Injection/Tampering pada Modul Cek Foto
- **Lokasi Kode:** `routes/web.php` (baris 46–49), `App\Http\Controllers\PhotoInspectorController.php`.
- **Deskripsi Masalah:**
  Rute inspeksi foto dan kalibrasi Pitch & Yaw didefinisikan secara publik tanpa perlindungan:
  ```php
  Route::get('/admin/cek-foto', [PhotoInspectorController::class, 'index'])->name('admin.cek-foto');
  Route::post('/admin/cek-foto/kalibrasi', [PhotoInspectorController::class, 'saveCalibration'])->name('admin.cek-foto.saveCalibration');
  Route::get('/admin/cek-foto-p', [PhotoInspectorController::class, 'indexCylinder'])->name('admin.cek-foto-p');
  ```
  Method `saveCalibration` mengeksekusi SPARQL `DELETE/INSERT` langsung ke server Apache Jena Fuseki.
- **Dampak Keamanan:** Pengguna tidak terotentikasi dapat memanggil `POST /admin/cek-foto/kalibrasi` untuk memanipulasi koordinat orientasi panorama atau merusak integritas data pada triplestore Fuseki.
- **Rekomendasi Perbaikan:** Pindahkan ketiga rute ini ke dalam grup middleware `['auth', 'admin']` di dalam blok `Route::prefix('admin')`.

---

#### 🔴 TEMUAN 3: Kerentanan Manipulasi Harga (Price Tampering) pada Pemesanan Fotokopi Publik
- **Lokasi Kode:** `App\Http\Controllers\FotocopyController.php` (method `storeOrder`, baris 98–147).
- **Deskripsi Masalah:**
  Pada form pemesanan layanan fotokopi mahasiswa, harga satuan item diterima mentah dari input pengguna (`$request->validate(['items.*.price' => 'required|numeric|min:0'])`) dan langsung digunakan untuk menghitung total harga:
  ```php
  $totalPrice = 0;
  foreach ($validated['items'] as $item) {
      $totalPrice += ($item['price'] * $item['quantity']);
  }
  ```
- **Dampak Keamanan:** Penyerang dapat mencegat request HTTP (misal via Burp Suite atau Console DevTools) dan mengubah parameter `price` menjadi `0` atau `1`. Sistem akan mencatat pesanan resmi dengan total tagihan Rp 0 / Rp 1, yang kemudian masuk ke antrean kasir dan berpotensi dicetak secara cuma-cuma tanpa kasir menyadari manipulasi harga tersebut.
- **Rekomendasi Perbaikan:** Jangan pernah mempercayai harga dari sisi klien. Ambil harga resmi dari basis data master layanan berdasarkan `service_id`:
  ```php
  $totalPrice = 0;
  foreach ($validated['items'] as $item) {
      $service = FotocopyService::where('store_id', $store->id)->findOrFail($item['service_id']);
      $subtotal = $service->price * $item['quantity'];
      $totalPrice += $subtotal;
      // Gunakan $service->price dan $service->name untuk disimpan ke database
  }
  ```

---

#### 🔴 TEMUAN 4: Insecure Direct Object Reference (IDOR) pada Panel Kasir Fotokopi
- **Lokasi Kode:** `App\Http\Controllers\CashierFotokopi\OrderController.php` (method `updateStatus`, `downloadFile`, `printJob`).
- **Deskripsi Masalah:**
  Pada method `updateStatus(Request $request, FotocopyOrder $order)`, `downloadFile(FotocopyOrder $order)`, dan `printJob(FotocopyOrder $order)`, tidak ada pengecekan apakah pesanan (`$order`) tersebut benar-benar milik toko yang dikelola oleh kasir yang sedang login:
  ```php
  public function updateStatus(Request $request, FotocopyOrder $order)
  {
      // Tidak ada validasi: $order->store_id === $myStore->id
      $order->update($updateData);
  }
  ```
- **Dampak Keamanan:** Kasir dari Toko A dapat mengubah status pesanan milik Toko B, mengunduh file dokumen rahasia milik pelanggan Toko B, atau mencetak nota transaksi toko lain hanya dengan menebak/mengganti ID pesanan pada URL.
- **Rekomendasi Perbaikan:** Buat otorisasi menggunakan Policy atau validasi kepemilikan tenant:
  ```php
  $user = auth()->user();
  $myStore = FotocopyStore::where('user_id', $user->id)->first();
  if (!$user->isAdmin() && (!$myStore || $order->store_id !== $myStore->id)) {
      abort(403, 'Anda tidak memiliki hak akses terhadap pesanan toko ini.');
  }
  ```

---

### 6.2. Temuan Tingkat Prioritas SEDANG (Medium)

#### 🟡 TEMUAN 5: Desinkronisasi Triplestore Fuseki Akibat Ketiadaan Model Observer pada Master Akademik
- **Lokasi Kode:** `app/Providers/AppServiceProvider.php` (baris 54), `app/Models/{Building, Room, Course, Lecturer}.php`.
- **Deskripsi Masalah:** Hanya model `Schedule` yang didaftarkan pada `ScheduleObserver`. Perubahan nama gedung, pergeseran koordinat GPS gedung, pengubahan nama dosen, atau perubahan SKS mata kuliah tidak memicu event pembaharuan graf RDF ke Fuseki.
- **Rekomendasi Perbaikan:** Daftarkan Observer untuk `Building`, `Room`, `Course`, dan `Lecturer` yang memicu pembaruan jadwal terkait atau dispatch `RegenerateRdfJob`.

---

#### 🟡 TEMUAN 6: Potensi Queue Flooding / DoS saat Eksekusi Bulk Import Excel
- **Lokasi Kode:** `app/Imports/ScheduleImport.php` (baris 60), `app/Observers/ScheduleObserver.php`.
- **Deskripsi Masalah:** `ScheduleImport` menyimpan model `Schedule` satu per satu di setiap baris. Untuk file Excel berisi 500 baris jadwal, Eloquent akan memicu event `created` sebanyak 500 kali, men-dispatch 500 `RegenerateRdfJob` ke database queue. Setiap job akan membaca seluruh tabel jadwal dan meng-generate ulang serta meng-upload seluruh berkas RDF secara berulang-ulang 500 kali.
- **Rekomendasi Perbaikan:** Matikan observer sementara saat proses import berlangsung menggunakan `Schedule::withoutEvents(...)`, lalu dispatch `RegenerateRdfJob` tepat satu kali setelah seluruh file selesai diimpor:
  ```php
  Schedule::withoutEvents(function () use ($import, $file) {
      Excel::import($import, $file);
  });
  RegenerateRdfJob::dispatch();
  ```

---

#### 🟡 TEMUAN 7: Desinkronisasi Antara Fitur Ekstensi Browser Chrome dengan Database
- **Lokasi Kode:** `app/Http/Controllers/Api/ScheduleApiController.php` (method `sync`).
- **Deskripsi Masalah:** Endpoint `/api/schedule/sync` hanya mencatat log dan mengembalikan respons sukses HTTP 200 tanpa menyimpan data apa pun ke database. Pengguna ekstensi mengira data jadwal mereka telah tersimpan di akun mereka, padahal data tersebut hilang.
- **Rekomendasi Perbaikan:** Buat proses parsing array `schedules` dari payload, cocokkan mata kuliah dan ruangan, lalu simpan ke database dengan mengaitkannya ke pengguna yang sedang login (via personal access token atau session auth).

---

### 6.3. Temuan Tingkat Prioritas RENDAH & Kualitas Kode (Low / Code Quality)

#### 🟢 TEMUAN 8: File Sampah Crash Dump Windows di Direktori Aset Frontend
- **Lokasi Kode:** `resources/js/grep.exe.stackdump`.
- **Deskripsi:** Berkas crash dump aplikasi Cygwin/MSYS2 berukuran 998 byte tidak sengaja ter-commit ke dalam repositori.
- **Rekomendasi:** Hapus file tersebut dan tambahkan `*.stackdump` ke berkas `.gitignore`.

---

#### 🟢 TEMUAN 9: Inkonsistensi Pemuatan Aset Frontend (Inline Script & Eksternal CDN)
- **Lokasi Kode:** `resources/views/admin/map.blade.php`, `resources/views/admin/cek-foto.blade.php`.
- **Deskripsi:** Halaman `admin/map.blade.php` memiliki >1.300 baris script JavaScript langsung di dalam Blade, sementara `cek-foto.blade.php` memuat Tailwind CSS dan Pannellum dari CDN eksternal alih-alih memanfaatkan pipeline Vite terkompilasi.
- **Rekomendasi:** Pindahkan script Alpine.js ke berkas terpisah (misal `resources/js/admin-map.js`) dan daftarkan ke dalam `vite.config.js`.

---

#### 🟢 TEMUAN 10: Kode Mati (Dead Code) pada Lapisan Repositories
- **Lokasi Kode:** Direktori `app/Repositories/Contracts/` dan `app/Repositories/Eloquent/`.
- **Deskripsi:** Repository pattern yang telah dibuat dan didaftarkan di `AppServiceProvider` tidak pernah digunakan oleh controller mana pun.
- **Rekomendasi:** Lakukan refaktorisasi pada Controller Admin agar meng-inject Repository Interface sesuai prinsip *Dependency Inversion*, atau jika proyek memutuskan untuk menggunakan Eloquent langsung secara pragmatis, bersihkan folder repositories yang tidak terpakai agar tidak membingungkan pengembang baru.

---

### 6.4. Pelan Aksi Remediasi Bertahap (Action Plan)

| Fase | Target Perbaikan | Estimasi Tingkat Kesulitan | File Utama yang Dimodifikasi |
| :--- | :--- | :---: | :--- |
| **Fase 1: Kritis (Keamanan)** | 1. Lindungi rute `/admin-sementara-/map/*` dengan middleware `auth` & `admin`.<br>2. Lindungi rute `/admin/cek-foto/*` dengan middleware `auth` & `admin`.<br>3. Perbaiki validasi harga pada `FotocopyController@storeOrder`.<br>4. Tambahkan validasi kepemilikan tenant (IDOR prevention) pada `CashierFotokopi\OrderController`. | **Mudah - Sedang** | `routes/web.php`, `FotocopyController.php`, `OrderController.php` |
| **Fase 2: Integritas Data** | 1. Implementasikan `withoutEvents` pada `ImportController` saat bulk Excel.<br>2. Pasang Observer pada model `Building` dan `Room` untuk regenerasi RDF Fuseki.<br>3. Implementasikan penyimpanan data nyata pada `ScheduleApiController@sync`. | **Sedang** | `ImportController.php`, `AppServiceProvider.php`, `ScheduleApiController.php` |
| **Fase 3: Refaktorisasi & Kualitas** | 1. Hapus file `grep.exe.stackdump`.<br>2. Ekstrak script JS inline dari `admin/map.blade.php` ke bundle Vite.<br>3. Konsolidasikan model `PhotocopyPlace` dan `FotocopyStore`. | **Sedang - Lanjut** | `resources/js/`, `vite.config.js`, `resources/views/admin/map.blade.php` |

---
*Laporan audit ini disusun secara independen dan komprehensif berdasarkan penelusuran kode sumber aktual proyek USUStarterMap.*
