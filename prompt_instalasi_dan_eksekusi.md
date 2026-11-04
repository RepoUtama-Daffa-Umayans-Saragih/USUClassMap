Halo Claude, bertindaklah sebagai Senior DevOps Engineer dan Technical Writer. 

Saya akan memberikan/mengunggah file proyek aplikasi web saya (beserta dokumentasi atau source code-nya). Tolong pindai dan analisis seluruh file ini secara mendalam. 

Setelah Anda menganalisisnya, tugas Anda adalah menghasilkan **sebuah panduan (dalam format Markdown / .md yang rapi)** untuk developer lain agar bisa menginstal dan menjalankan proyek ini dari nol. 

Panduan yang Anda buat **wajib** mencakup 3 poin utama berikut secara berurutan:

### 1. Daftar Perangkat Lunak (Software) yang Dibutuhkan
Analisis file seperti `composer.json`, `package.json`, `.env.example`, atau `README` untuk mengidentifikasi semua software pendukung yang wajib terinstal di sistem operasi (misalnya PHP, Node.js, Composer, Git, Database seperti SQLite/MySQL, dan Apache Jena Fuseki jika ada).

### 2. Cara Install Software (Disesuaikan dengan Proyek)
Jangan hanya menyebutkan nama software-nya. Buatkan panduan cara menginstal/menyiapkan software tersebut **khusus untuk kebutuhan proyek ini**. 
* Contoh: Jika menggunakan SQLite, jelaskan perintah untuk membuat file `database.sqlite` yang kosong.
* Contoh: Jika menggunakan MySQL, jelaskan perlunya membuat database tertentu.
* Contoh: Jika menggunakan Apache Jena Fuseki, jelaskan cara menjalankannya dengan parameter spesifik (seperti `--update --mem /usuclassmap`) dan dataset apa yang harus di-upload.

### 3. Tahapan Menjalankan Proyek (Run Project)
Asumsikan semua software di atas sudah terinstal. Sekarang, buatkan urutan eksekusi *command line* secara step-by-step untuk membangun dan menyalakan proyek ini.
* Mulai dari *clone* (jika perlu), install dependencies backend (`composer install`) & frontend (`npm install`).
* Konfigurasi file `.env`.
* Eksekusi database (migrasi dan *seeder*).
* Eksekusi hal-hal spesifik framework (seperti `php artisan storage:link`).
* Dan terakhir, perintah untuk menjalankan server lokalnya (misalnya `php artisan serve`, `npm run dev`, atau eksekusi service worker/queue).

**Penting:** Tolong berikan output HANYA dalam bentuk raw teks Markdown (tanpa basa-basi) agar bisa langsung saya simpan sebagai file `SETUP.md` di proyek saya.