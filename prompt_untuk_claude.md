Bertindaklah sebagai Senior Software Engineer dan Technical Writer. 

Saya akan memberikan/mengunggah seluruh file dan struktur folder dari sebuah proyek aplikasi web kepada Anda. Tugas Anda adalah melakukan *scanning* dan analisis mendalam terhadap seluruh kode sumber (source code) tersebut, lalu membuatkan dokumentasi teknis yang sangat komprehensif.

Tujuan dari dokumentasi ini adalah agar tim developer lain (atau teman saya) dapat dengan mudah memahami, menginstal, dan melanjutkan pengembangan proyek ini dari nol.

Berdasarkan file yang saya unggah, tolong ekstrak dan hasilkan informasi berikut:

1. **Tech Stack (Teknologi yang Dipakai):** Analisis `package.json`, `composer.json`, atau file konfigurasi lainnya. Sebutkan bahasa pemrograman, framework utama, database, library penting (terutama yang berkaitan dengan fitur utama seperti 360 viewer jika ada), dan tools pendukung lainnya.
2. **Arsitektur Sistem:** Pelajari struktur foldernya. Jelaskan arsitektur yang digunakan (misalnya MVC, API-based, dll), alur kerja (flow) aplikasi dari *routing* hingga *view*, dan di mana letak logika utama atau fitur-fitur krusial berada.
3. **Persiapan & Instalasi (Setup Guide):** Buatkan daftar prasyarat aplikasi apa saja yang harus terinstal di komputer (*prerequisites*). Lalu, tuliskan panduan instalasi langkah demi langkah (step-by-step) dari proses *clone*, instalasi dependensi, konfigurasi environment (`.env`), migrasi database, hingga perintah untuk menjalankan server lokal.
4. **Catatan Tambahan untuk Developer (Opsional tapi penting):** Jika Anda menemukan konfigurasi khusus, titik rawan (*bottleneck*), atau hal-hal krusial (seperti *symlink* folder storage, mode kompresi gambar, dll) yang perlu diketahui oleh penerus proyek ini, tolong cantumkan.

**ATURAN OUTPUT (SANGAT PENTING):**
Format SELURUH jawaban Anda ke dalam bentuk raw Markdown (`.md`) yang siap pakai. Saya ingin langsung menyalin hasil jawaban Anda dan menyimpannya sebagai file `README.md` di repositori proyek ini. Gunakan struktur heading, list, bold, dan code block yang rapi dan profesional.