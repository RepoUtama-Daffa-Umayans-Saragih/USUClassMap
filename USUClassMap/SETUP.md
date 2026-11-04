# 🛠️ SETUP.md — Panduan Instalasi & Menjalankan USUClassMap

> Panduan ini ditujukan untuk developer baru yang ingin menginstal dan menjalankan proyek **USUClassMap** dari nol di lingkungan lokal (Windows / Linux / macOS).

---

## 1. Daftar Perangkat Lunak yang Dibutuhkan

Pastikan semua software berikut sudah terinstal di sistem Anda sebelum memulai:

| No | Software | Versi Minimum | Fungsi dalam Proyek |
|----|---|---|---|
| 1 | **PHP** | 8.2+ | Runtime backend Laravel |
| 2 | **Composer** | 2.x | Package manager PHP (install Laravel, EasyRdf, dll) |
| 3 | **Node.js** | 18+ | Runtime untuk build frontend (Vite, Tailwind CSS) |
| 4 | **npm** | 9+ | Package manager JS (install Alpine.js, Tailwind, Axios) |
| 5 | **Git** | Terbaru | Clone repository proyek |
| 6 | **SQLite** | 3.x | Database default proyek (umumnya sudah bawaan PHP) |
| 7 | **Apache Jena Fuseki** | 4.x+ | *(Opsional)* Triplestore untuk fitur SPARQL Semantic Web |

> **Catatan SQLite:** Pastikan ekstensi `php_sqlite3` dan `php_pdo_sqlite` aktif di `php.ini`. Cek dengan: `php -m | grep -i sqlite`
>
> **Catatan Fuseki:** Jika tidak diinstal, aplikasi tetap berjalan normal menggunakan **fallback otomatis ke MySQL/SQLite**. Fuseki hanya diperlukan untuk mengaktifkan fitur SPARQL penuh.

---

## 2. Cara Install Software (Disesuaikan dengan Proyek)

### 2.1 PHP 8.2+

**Windows:**
- Download dari https://windows.php.net/download/ (pilih versi **Thread Safe**, arsitektur sesuai sistem)
- Ekstrak ke `C:\php`
- Tambahkan `C:\php` ke **System Environment Variable → PATH**
- Salin `php.ini-development` menjadi `php.ini`
- Aktifkan ekstensi berikut di `php.ini` (hapus tanda `;` di depannya):
  ```ini
  extension=curl
  extension=fileinfo
  extension=mbstring
  extension=openssl
  extension=pdo_sqlite
  extension=sqlite3
  extension=zip
  extension=gd
  ```
- Verifikasi: `php -v`

**Linux (Ubuntu/Debian):**
```bash
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-curl php8.2-mbstring \
     php8.2-xml php8.2-zip php8.2-sqlite3 php8.2-gd php8.2-pdo
```

### 2.2 Composer

**Windows & Linux:**
```bash
# Download installer Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Pindahkan agar bisa diakses global (Linux)
sudo mv composer.phar /usr/local/bin/composer

# Windows: jalankan installer .exe dari https://getcomposer.org/download/
```
- Verifikasi: `composer -V`

### 2.3 Node.js & npm

- Download installer dari https://nodejs.org/ (pilih versi **LTS**)
- Ikuti wizard instalasi (npm sudah termasuk)
- Verifikasi:
  ```bash
  node -v   # Harus >= 18.x
  npm -v    # Harus >= 9.x
  ```

### 2.4 SQLite (Database Default Proyek)

Proyek ini menggunakan SQLite sebagai database default. Tidak perlu instalasi server database terpisah — cukup buat file kosong:

**Windows:**
```cmd
# Jalankan di dalam folder USUClassMap
type nul > database\database.sqlite
```

**Linux / macOS:**
```bash
touch database/database.sqlite
```

> File ini yang akan menjadi database proyek. Semua tabel dan data akan disimpan di sini setelah migrasi dijalankan.

### 2.5 Apache Jena Fuseki *(Opsional — Hanya untuk Fitur SPARQL)*

Jika Anda ingin mengaktifkan fitur pencarian berbasis **SPARQL** (bukan hanya MySQL fallback):

1. Download Apache Jena Fuseki dari:
   https://jena.apache.org/download/

2. Ekstrak arsip, lalu jalankan Fuseki dengan dataset in-memory untuk proyek ini:
   ```bash
   # Masuk ke folder hasil ekstrak Fuseki
   # Linux / macOS:
   ./fuseki-server --update --mem /usuclassmap

   # Windows (di Command Prompt):
   fuseki-server.bat --update --mem /usuclassmap
   ```

3. Fuseki akan berjalan di: **http://localhost:3030**

4. Buka http://localhost:3030, masuk ke UI, pilih dataset `usuclassmap`, lalu upload file RDF yang sudah di-generate:
   ```
   USUClassMap/storage/app/rdf/schedules.rdf
   ```

5. Setelah dataset ter-upload, tambahkan baris berikut ke file `.env` proyek:
   ```env
   SPARQL_ENDPOINT=http://localhost:3030/usuclassmap/query
   SPARQL_TIMEOUT=5
   ```

> **Jika tidak menginstal Fuseki:** Hapus atau biarkan variabel `SPARQL_ENDPOINT` tidak ada di `.env`. Aplikasi akan otomatis menggunakan query MySQL sebagai fallback tanpa error apapun.

---

## 3. Tahapan Menjalankan Proyek (Run Project)

> **Semua perintah berikut dijalankan dari dalam folder proyek:**
> ```
> d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
> ```
> Buka terminal / Command Prompt, lalu:
> ```cmd
> cd "d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap"
> ```

---

### Langkah 1 — Clone Repository (jika belum ada)

```bash
git clone <URL_REPOSITORY> USUClassMap
cd "d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap"
```

---

### Langkah 2 — Install Dependensi PHP (Backend)

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
composer install
```

Proses ini menginstal semua library PHP termasuk Laravel 11, EasyRdf, Maatwebsite Excel, dll.

---

### Langkah 3 — Install Dependensi JavaScript (Frontend)

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
npm install
```

Proses ini menginstal Tailwind CSS v4, Alpine.js, Vite, dan Axios.

---

### Langkah 4 — Buat File Environment

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\

# Windows
copy .env.example .env

# Linux / macOS
cp .env.example .env
```

Lalu generate Application Key:
```bash
php artisan key:generate
```

---

### Langkah 5 — Konfigurasi File `.env`

Buka file `.env` dengan teks editor, pastikan konfigurasi berikut sudah benar:

```env
APP_NAME="USUClassMap"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

# --- Database (SQLite Default) ---
DB_CONNECTION=sqlite
# File sqlite sudah dibuat di Langkah 2.4

# --- Queue (untuk job regenerasi RDF) ---
QUEUE_CONNECTION=database

# --- SPARQL Fuseki (opsional, hapus jika tidak pakai Fuseki) ---
# SPARQL_ENDPOINT=http://localhost:3030/usuclassmap/query
# SPARQL_TIMEOUT=5
```

---

### Langkah 6 — Buat File Database SQLite

```bash
# Windows (CMD) — Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
type nul > database\database.sqlite

# Linux / macOS
touch database/database.sqlite
```

---

### Langkah 7 — Jalankan Migrasi Database

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan migrate
```

Perintah ini membuat semua tabel di database: `users`, `buildings`, `rooms`, `lecturers`, `courses`, `schedules`, `room_media`, `jobs`, `cache`, dll.

---

### Langkah 8 — Jalankan Seeder (Data Awal)

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan db:seed
```

Proses ini mengisi database dengan data awal:
- ✅ Akun admin default
- ✅ Data gedung-gedung USU (dengan koordinat latitude/longitude)
- ✅ Data ruangan per gedung
- ✅ Data dosen & mata kuliah
- ✅ Jadwal perkuliahan
- ✅ 100 data dummy MKWU FIB (hardcoded, tidak perlu file CSV)

---

### Langkah 9 — Buat Storage Symlink *(WAJIB)*

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan storage:link
```

> ⚠️ **Langkah ini wajib!** Tanpanya, semua foto ruangan (termasuk foto 360°) tidak akan bisa diakses via URL. Jalankan sekali saja, tidak perlu diulang.

---

### Langkah 10 — Generate File RDF *(Opsional, untuk Semantic Web)*

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan rdf:generate
```

Perintah ini membuat file `storage/app/rdf/schedules.rdf` dari data di database. File ini dibutuhkan jika Anda menggunakan Apache Jena Fuseki.

---

### Langkah 11 — Build Aset Frontend

**Untuk Development (dengan Hot Module Replacement):**
```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
npm run dev
```

**Untuk Production (sekali build, file statis):**
```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
npm run build
```

---

### Langkah 12 — Jalankan Server Lokal

Buka **terminal baru** (jangan tutup terminal `npm run dev` jika sedang development):

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan serve
```

Aplikasi akan berjalan di: **http://localhost:8000**

---

### Langkah 13 — Jalankan Queue Worker *(Wajib untuk Regenerasi RDF Otomatis)*

Buka **terminal ketiga**, jalankan:

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan queue:listen --tries=1
```

Queue worker ini memproses job `RegenerateRdfJob` yang dipicu otomatis setiap kali data jadwal ditambah, diubah, atau dihapus.

---

### Cara Cepat: Jalankan Semua Sekaligus (Rekomendasi Development)

Sebagai alternatif langkah 11–13, jalankan satu perintah berikut yang menjalankan semua service secara paralel:

```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
composer dev
```

Perintah ini secara bersamaan menjalankan:
| Service | Perintah |
|---|---|
| Web Server | `php artisan serve` |
| Queue Worker | `php artisan queue:listen --tries=1` |
| Log Viewer | `php artisan pail --timeout=0` |
| Vite Dev Server | `npm run dev` |

---

## ✅ Verifikasi Instalasi

Setelah semua langkah selesai, buka browser dan akses:

| URL | Yang Diharapkan |
|---|---|
| http://localhost:8000 | Landing page USUClassMap |
| http://localhost:8000/map | Peta interaktif dengan marker gedung |
| http://localhost:8000/login | Halaman login |
| http://localhost:8000/admin/dashboard | Dashboard admin (login dulu) |
| http://localhost:8000/admin-sementara-/map | Admin Spatial Map Dashboard |

Jalankan test suite untuk memastikan tidak ada yang rusak:
```bash
# Jalankan dari: d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap\
php artisan test
```
**Hasil yang diharapkan: 49 tests, 219 assertions — semua PASS ✅**

---

## 🔑 Akun Default (Setelah Seeder)

| Role | Email | Password |
|---|---|---|
| Admin | Lihat `database/seeders/AdminUserSeeder.php` | Lihat file seeder |

---

*SETUP.md ini di-generate berdasarkan analisis source code proyek USUClassMap — November 2026.*
