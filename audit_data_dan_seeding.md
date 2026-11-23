# 📋 LAPORAN AUDIT DATA, SEEDER & VISIBILITAS TEMPAT DI PETA (USUClassMap)
**Tanggal Audit:** 22 November 2026  
**Auditor:** Senior Laravel Full-Stack & Spatial Database Engineer  
**Target Aplikasi:** USUClassMap (Laravel 11, Vite, Alpine.js, Tailwind CSS v4, Mapbox GL JS 3D, Apache Jena Fuseki)  
**Metodologi:** Static Code Analysis, SQLite Master Inspection, API Sandbox Testing & Semantic Triplestore Probing (Read-Only)

---

## 1. RINGKASAN EKSEKUTIF & JAWABAN INTI MASALAH

### ❓ Mengapa Titik Lokasi Fotokopi (Mitra Percetakan) Tidak Terlihat di Peta?
Berdasarkan audit end-to-end dari database, API resource, hingga JavaScript rendering, ditemukan **3 lapis penyebab**:

1. **Inkompatibilitas Kontrak Payload API (`CopyShopResource` vs `map.js`) — [FATAL]:**
   - Endpoint `/api/copy-shops` mentransformasi model `PhotocopyPlace` menggunakan `CopyShopResource`.
   - Di dalam `CopyShopResource.php`, koordinat dibungkus di dalam sub-objek:
     ```php
     'location' => [
         'name' => $this->location_name,
         'latitude' => $this->latitude,
         'longitude' => $this->longitude,
     ]
     ```
   - Namun, fungsi JavaScript `renderPhotocopyMarkers(places)` di `resources/js/features/map.js` (baris 1290–1294) membaca koordinat langsung dari root:
     ```javascript
     places.forEach(place => {
         if (!place.latitude || !place.longitude) return; // <-- place.latitude bernilai UNDEFINED!
         const lng = parseFloat(place.longitude);
         const lat = parseFloat(place.latitude);
     ```
   - **Dampaknya:** Karena `place.latitude` dan `place.longitude` bernilai `undefined`, pengecekan `if (!place.latitude || !place.longitude) return;` mengevaluasi `true` dan membatalkan pembuatan marker untuk **seluruh 31 data fotokopi**. Akibatnya, `photocopyMarkers` tetap kosong (`{}`) dan tidak ada satu pun pin yang digambar di kanvas peta.
2. **Behavior "Hidden by Default" (Sesuai Desain UX):**
   - Variabel `isPhotocopyLayerActive = false` secara default agar peta fokus pada gedung perkuliahan saat awal dibuka. Pengguna harus mengklik tombol "Mitra Fotokopi" di sidebar atau floating HUD untuk memunculkannya.
   - Ketika tombol diklik, request API sukses berjalan dan mengembalikan 31 data, namun gagal dirender akibat bug poin #1 di atas.
3. **Kerusakan Tampilan Popup Fotokopi (Secondary Bug):**
   - Di dalam template popup marker, `place.rating` dibaca sebagai nilai skalar (`${place.rating || '4.8'}`), padahal `CopyShopResource` mengirimkannya sebagai objek (`{ score: 4.8, user_rating_count: 50 }`). Ini akan merender teks `[object Object]` pada antarmuka.
   - Status buka/tutup `place.is_open_now` berada di `place.status.is_open_now`.
   - Nomor WhatsApp `place.whatsapp_url` berada di `place.contacts.whatsapp_url`.

---

## 2. INVENTARISASI TABEL, MODEL, SEEDER & DATASET MENTAH

Tabel berikut memetakan relasi lengkap antara migration, model Eloquent, seeder, file data mentah, pendaftaran di `DatabaseSeeder.php`, dan status baris aktual di database SQLite aktif (`database/database.sqlite`):

| Nama Entitas / Tabel | Model Eloquent | File Seeder Terkait | Status di `DatabaseSeeder.php` | File Dataset Mentah | Jumlah Baris Aktual di DB | Status Tampil di Peta |
|---|---|---|---|---|---|---|
| **`buildings`** | `Building.php` | `BuildingSeeder.php` | ✅ **Ya** (Dipanggil) | `raw_osm_buildings.json` (46) & `raw_usu_buildings.json` (52) | **47 baris** | ✅ **Tampil** (Marker Hijau / Oranye) |
| **`rooms`** | `Room.php` | `RoomSeeder.php`, `FibDummySeeder.php` | ✅ **Ya** (Dipanggil) | Ter-hardcode di seeder | **70 baris** | ✅ **Tampil** (Di dalam Popup Gedung) |
| **`room_media`** | `RoomMedia.php` | *Tidak ada seeder* | ❌ **Tidak Ada** | File foto di `storage/app/public/` | **0 baris** | ⚠️ Fallback Mock Unsplash/Video |
| **`courses`** | `Course.php` | `CourseSeeder.php`, `FibDummySeeder.php`, `MkwuFasilkomSeeder.php` | ✅ **Ya** (`CourseSeeder` & `FibDummySeeder`) | `data/mkwu.csv` (208 matkul) | **90 baris** | ✅ **Tampil** (Daftar Jadwal Ruangan) |
| **`lecturers`** | `Lecturer.php` | `LecturerSeeder.php`, `FibDummySeeder.php` | ✅ **Ya** (Dipanggil) | Ter-hardcode di seeder | **79 baris** | ✅ **Tampil** (Info Dosen di Jadwal) |
| **`schedules`** | `Schedule.php` | `ScheduleSeeder.php`, `FibDummySeeder.php`, `MkwuFasilkomSeeder.php` | ⚠️ **Parsial** (`MkwuFasilkomSeeder` Tertinggal) | `data/mkwu.csv` (208 baris jadwal) | **143 baris** | ✅ **Tampil** (Sidebar & Popup Ruangan) |
| **`photocopy_places`** | `PhotocopyPlace.php` (alias `CopyShop.php`) | `PhotocopySeeder.php` | ✅ **Ya** (Dipanggil) | `data/usu_photocopy_data.json` (31 mitra) | **31 baris** | ❌ **Gagal Tampil** (Bug Deserialisasi JSON API) |
| **`photocopy_items`** | `PhotocopyItem.php` | `PhotocopySeeder.php` | ✅ **Ya** (Dipanggil) | `data/usu_photocopy_data.json` | **204 baris** | ✅ Tampil di Detail Toko Fotokopi |
| **`services`** | `Service.php` | `ServiceMasterSeeder.php` | ✅ **Ya** (Dipanggil) | Ter-hardcode di seeder | **112 baris** | ✅ Master Kategori Layanan |
| **`photocopy_place_service`** | Pivot Table | `PhotocopySeeder.php` | ✅ **Ya** (Dipanggil) | `data/usu_photocopy_data.json` | **312 baris** | ✅ Relasi Many-to-Many |
| **`fotocopy_stores`** | `FotocopyStore.php` | `FotocopySeeder.php` | ✅ **Ya** (Dipanggil) | `data/usu_photocopy_data.json` | **31 baris** | Digunakan pada Modul Order & Kasir |
| **`fotocopy_services`** | `FotocopyService.php` | `FotocopySeeder.php` | ✅ **Ya** (Dipanggil) | `data/usu_photocopy_data.json` | **204 baris** | Digunakan pada Modul Kasir |
| **`fotocopy_orders`** | `FotocopyOrder.php` | `FotocopySeeder.php` | ✅ **Ya** (Dipanggil) | Ter-hardcode di seeder | **3 baris** | Riwayat Order Toko Berkah Abadi |
| **`fotocopy_order_items`** | `FotocopyOrderItem.php` | `FotocopySeeder.php` | ✅ **Ya** (Dipanggil) | Ter-hardcode di seeder | **6 baris** | Item Pesanan Berkah Abadi |
| **`shop_reviews`** | `ShopReview.php` | *Tidak ada seeder* | ❌ **Tidak Ada** | *Belum ada dataset mentah* | **0 baris** | Ulasan Toko Masih Kosong |
| **`users`** | `User.php` | `AdminUserSeeder.php`, `FotocopySeeder.php` | ✅ **Ya** (Dipanggil) | Akun superadmin, kasir, & tester | **6 baris** | Otentikasi & Akun Login |

---

## 3. TEMUAN DATA MENTAH YANG TERCECER / BELUM DI-SEED

### A. Dataset Jadwal MKWU Riil (`database/seeders/data/mkwu.csv`) — **MENGANGGUR**
- **Status:** File `database/seeders/data/mkwu.csv` berisi **208 data jadwal kuliah MKWU riil** lintas fakultas (Bahasa Inggris/IELTS, Bahasa Jepang, Olahraga/Badminton/Futsal, Kearifan Lokal, Etika, Agama, dsb.) lengkap dengan dosen pengampu, ruangan, jam, dan SKS.
- **Masalah:** Sama sekali **belum ada class Seeder yang membaca file CSV ini**. Class `MkwuFasilkomSeeder.php` yang ada di codebase hanya menampung 8 jadwal hardcoded khusus Fasilkom-TI dan **bahkan belum didaftarkan di `DatabaseSeeder.php`**.
- **Dampak:** Sebanyak 200+ jadwal perkuliahan mahasiswa umum tidak tersedia di database maupun pencarian peta.

### B. Dataset Alternatif Gedung USU (`database/seeders/raw_usu_buildings.json`) — **MENGANGGUR**
- **Status:** File ini memiliki **52 gedung dan fasilitas USU** dengan nama deskriptif lengkap (misal: *Gedung Biro Pusat Administrasi (Rektorat USU)*, *Pusat Inkubator Bisnis CIE*, *Poliklinik USU*, *Asrama Mahasiswa*, *RSGM*, *Gelanggang Mahasiswa*, dsb.).
- **Perbandingan:** Database saat ini memuat **47 gedung** yang bersumber dari `BuildingSeeder.php` (OpenStreetMap Overpass API). Sekitar 37 nama gedung/fasilitas spesifik di `raw_usu_buildings.json` belum terserap ke tabel `buildings`.

### C. Triples RDF Apache Jena Fuseki Kosong (0 Triples) — **KRITIS**
- **Status Koneksi:** Apache Jena Fuseki aktif berjalan di `http://localhost:3030` (Port 3030 `TcpTestSucceeded: True`).
- **Status Triples:** Query `SELECT (COUNT(*) as ?count) WHERE { ?s ?p ?o }` mengembalikan nilai **0 (KOSONG)**.
- **Akar Masalah:** File RDF hasil generasi [schedules.rdf](file:///d:/Documents/TUBES%20WEB%20SEMANTIK/Kelompok%203%20Web%20Semantik/USUClassMap/storage/app/rdf/schedules.rdf) (~160 KB, 3191 baris) sudah ada di penyimpanan lokal, namun perintah `php artisan rdf:generate --upload` belum pernah dijalankan ke Fuseki.
- **Dampak Fatal ke Fitur Pencarian:**
  - `SparqlService.php` melakukan POST query ke Fuseki. Karena Fuseki online, server mengembalikan status HTTP 200 (Success) dengan 0 hasil.
  - Akibatnya, `SparqlService.php` **TIDAK PERNAH memicu fallback ke database SQLite**, dan menganggap bahwa jadwal memang tidak ditemukan di kampus USU.

---

## 4. AUDIT KOORDINAT & KUALITAS SPASIAL

Pemeriksaan sanitasi koordinat terhadap tabel berkolom spasial (`latitude`, `longitude`):

1. **Tabel `buildings` (47 baris):**
   - Nilai `NULL` atau `0`: **0 baris (Nihil)**.
   - Seluruh koordinat berada dalam rentang valid USU Padang Bulan:
     - `Latitude`: `3.557000` s/d `3.568500` (Positif, Hemisfer Utara).
     - `Longitude`: `98.652000` s/d `98.662000` (Positif, Hemisfer Timur).
   - Urutan sumbu: Sesuai standar database spasial (`lat`, `lng`).
2. **Tabel `photocopy_places` (31 baris):**
   - Nilai `NULL` atau `0`: **0 baris (Nihil)**.
   - Status `is_active`: 31 dari 31 berstatus `1` (Aktif).
   - Seluruh koordinat valid di sekitar radius kampus USU (Jalan Dr. Mansyur, Jalan Jamin Ginting, Jalan Setia Budi):
     - Contoh ID 1 (FT): Lat `3.563812`, Lng `98.654210`
     - Contoh ID 6 (Pintu 1): Lat `3.566200`, Lng `98.658100`
   - **Kesimpulan:** Data spasial di database 100% sehat. Masalah murni berada pada lapisan penyerahan payload (*presentation layer*).

---

## 5. AUDIT KONTROLLER & ENDPOINT API

1. **`CopyShopApiController.php` (`/api/copy-shops`):**
   - Filter `where('is_active', true)`: Aman, semua 31 data berstatus `is_active = 1`.
   - Paginasi: `$request->query('per_page', 15)`. Frontend peta memanggil `/api/copy-shops?per_page=50`, sehingga semua 31 record terkirim dalam 1 halaman.
   - Resource: Menggunakan `CopyShopResource`. Inilah sumber ketidakcocokan skema dengan frontend.
2. **`BuildingApiController.php` (`/api/buildings`):**
   - Mengembalikan array langsung `['status' => 'success', 'data' => $buildings]` dengan relasi `rooms` dan `schedules`.
   - Kolom `latitude` dan `longitude` berada langsung di root objek gedung. Inilah alasan mengapa marker gedung dapat muncul normal sementara fotokopi tidak.
3. **`ScheduleApiController.php` / `ScheduleController.php` (`/api/schedules/search`):**
   - Terhubung ke `SparqlService.php`.
   - Berfungsi normal jika SPARQL mengembalikan data atau jika Fuseki mati total (fallback bekerja). Namun saat Fuseki hidup tapi kosong, endpoint mengembalikan 0 jadwal.

---

## 6. AUDIT KODE FRONTEND (MENGAPA FOTOKOPI TIDAK MUNCUL)

Perbandingan langsung antara output API dan ekspektasi JavaScript:

```
[Respons Aktual API: /api/copy-shops via CopyShopResource]
{
  "data": [
    {
      "id": 1,
      "name": "Fotocopy Pak Budi FT",
      "location": {                         <--- BERSARANG DI "location"
        "name": "Fakultas Teknik USU",
        "latitude": 3.563812,
        "longitude": 98.65421
      },
      "status": {                           <--- BERSARANG DI "status"
        "is_open_now": true
      },
      "rating": {                           <--- BERSARANG DI "rating"
        "score": 4.8,
        "user_rating_count": 50
      },
      "contacts": {                         <--- BERSARANG DI "contacts"
        "whatsapp_url": "https://wa.me/..."
      }
    }
  ]
}

[Kode Konsumen: resources/js/features/map.js]
places.forEach(place => {
    // BUG 1: place.latitude bernilai undefined!
    if (!place.latitude || !place.longitude) return; 

    // BUG 2: place.image_url aman jika ada, tapi rating jadi [object Object]
    // BUG 3: place.is_open_now undefined -> selalu dianggap Tutup
    const statusBadge = place.is_open_now ? 'Buka' : 'Tutup';

    // BUG 4: place.whatsapp_url undefined -> fallback ke default
});
```

---

## 7. RENCANA PERBAIKAN AMAN & TERUKUR (NON-DESTRUKTIF)

Semua langkah perbaikan berikut **tidak menghapus data** dan **tidak menggunakan migrate:fresh**:

### Langkah 1: Perbaikan Skema Konsumsi Data di `resources/js/features/map.js` (dan `resources/js/map.js`)
Normalisasi ekstraksi data agar mendukung kedua skema (baik dari `location.latitude` maupun `place.latitude` langsung):
```javascript
places.forEach(place => {
    const lat = place.location?.latitude ?? place.latitude;
    const lng = place.location?.longitude ?? place.longitude;
    if (!lat || !lng) return;

    const isOpen = place.status?.is_open_now ?? place.is_open_now;
    const ratingScore = typeof place.rating === 'object' ? (place.rating?.score ?? '4.8') : (place.rating ?? '4.8');
    const reviewCount = typeof place.rating === 'object' ? (place.rating?.user_rating_count ?? 50) : (place.user_rating_count ?? 50);
    const waUrl = place.contacts?.whatsapp_url ?? place.whatsapp_url ?? `https://wa.me/${place.whatsapp || ''}`;
    const locName = place.location?.name ?? place.location_name ?? '';
    // Buat marker dan popup menggunakan variabel hasil normalisasi
});
```

### Langkah 2: Daftarkan Seeder yang Tertinggal ke `DatabaseSeeder.php`
- Menambahkan `MkwuFasilkomSeeder::class` ke array `$this->call([...])` di `DatabaseSeeder.php`.
- Menjalankan seeder secara spesifik:
  ```bash
  php artisan db:seed --class=MkwuFasilkomSeeder
  ```

### Langkah 3: Sinkronisasi Dataset Fuseki SPARQL
- Mengunggah file RDF `schedules.rdf` ke dataset Fuseki lokal agar pencarian semantik aktif:
  ```bash
  php artisan rdf:generate --upload
  ```

### Langkah 4 (Opsional / Terjadwal): Pembuatan Seeder Pembaca `mkwu.csv`
- Membuat `MkwuCsvSeeder.php` yang membaca `database/seeders/data/mkwu.csv` untuk memasukkan 208 jadwal perkuliahan riil lintas fakultas ke tabel `schedules`, `courses`, dan `lecturers`.

---

*Laporan ini disusun secara objektif berdasarkan kondisi faktual repositori pada commit terkini.*
