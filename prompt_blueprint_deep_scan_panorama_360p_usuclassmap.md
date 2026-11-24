# Prompt & Blueprint Eksekusi: Deep Scan Tulisan Ruangan Panorama $360^\circ$ & Halaman Pembuktian `/admin/cek-foto/360p`

> **Tujuan Dokumen:** Blueprint dan prompt *self-contained* untuk dieksekusi di **Antigravity** (`Orchestrator -> Claude Opus 5.5` / `Gemini 4 Neon`, mode `Turbo`).
>
> **Target Proyek:** `USUClassMap` (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)
>
> **Sumber Foto:** `D:\Documents\TUBES WEB SEMANTIK\FOTO 360P` (`112` file JPG)
>
> **Output Daftar Ruangan:** `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\DAFTAR_RUANGAN_DEEP_SCAN_360P.md`
>
> **Halaman Pembuktian Terisolasi:** `http://127.0.0.1:8000/admin/cek-foto/360p`

---

## Ringkasan Arsitektur Kerja (4 Lapis)

1. **Rename Aman & Terisolasi (`1.jpg` – `112.jpg`):**
   - Seluruh `112` foto diurutkan secara deterministik lalu di-rename 2-tahap menjadi `1.jpg`, `2.jpg`, `3.jpg`, ..., `112.jpg` disertai `rename_mapping.json` sebagai arsip nama file asli.
   - Seluruh foto disalin ke `public/images/foto-360p/` agar terpisah 100% dari galeri `/admin/cek-foto` lama.
2. **Rectilinear Unwarping + AI Vision Deep Scan:**
   - Tiap gambar panorama *equirectangular* dipecah ke beberapa potongan sudut pandang datar (*perspective crops* keliling $360^\circ$ termasuk sudut atas $\text{pitch } +5^\circ / +15^\circ$ tempat papan nama pintu terpasang) untuk mengatasi distorsi lengkung dan kompresi WhatsApp.
   - AI Vision & OCR memindai tulisan ruangan apa pun dan merekam koordinat $(\text{yaw}, \text{pitch})$ tempat tulisan ditemukan.
3. **Daftar Ruangan Terstruktur (`DAFTAR_RUANGAN_DEEP_SCAN_360P.md`):**
   - Mencatat statistik total, indeks nama ruangan beserta nomor foto dan sudut kamera, serta tabel lengkap foto `1` s.d. `112` dengan status jujur (`detected`, `no_text`, atau `blurry`).
4. **Halaman Pembuktian Interaktif (`/admin/cek-foto/360p`):**
   - Terpisah penuh dari `/admin/cek-foto` eksisting. Admin dapat menyaring foto yang terdeteksi ruangan, mengklik pill nama ruangan agar kamera Pannellum otomatis berputar dan zoom ke papan tulisannya, serta melakukan verifikasi/export (`.md`, `.csv`, `.json`).

---

## Salin Prompt Berikut ke Antigravity

````markdown
<role>
Anda adalah Principal Computer Vision / AI Pipeline Engineer dan Senior Full-Stack Laravel Engineer.
Target Proyek: USUClassMap (`D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`).
Sumber Foto 360 Baru: `D:\Documents\TUBES WEB SEMANTIK\FOTO 360P` (112 file JPG).
Output Daftar Markdown: `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\DAFTAR_RUANGAN_DEEP_SCAN_360P.md`.
</role>

<mission_and_strict_rules>
1. ISOLASI MUTLAK ("BIAR GAK TERCAMPUR-CAMPUR"):
   - DILARANG mengubah atau mencampur data/tampilan halaman existing `/admin/cek-foto`, tabel `rooms`, tabel `room_media`, folder foto 360 lama, maupun graf Apache Jena Fuseki (`5.025` triples).
   - Seluruh aset, hasil scan, controller, route, dan halaman pembuktian untuk tugas ini WAJIB terisolasi pada:
     - Folder gambar publik: `USUClassMap/public/images/foto-360p/` (`1.jpg` .. `112.jpg`)
     - Data hasil scan: `USUClassMap/storage/app/foto-360p/scan_results.json` & `rename_mapping.json`
     - Route halaman pembuktian: `GET /admin/cek-foto/360p` (dilindungi middleware admin yang sama dengan `/admin/cek-foto`).
2. ZERO HALLUCINATION / KEJUJURAN DEEP SCAN:
   - Setiap nama ruangan yang dicatat WAJIB benar-benar terlihat/terbaca di dalam foto panorama tersebut.
   - Jika sebuah foto tidak memuat papan/tulisan ruangan atau terlalu buram untuk dibaca, tandai secara jujur sebagai `no_text` atau `blurry` (DILARANG mengarang nama ruangan).
3. ZERO FEATURE LOSS:
   - Seluruh fitur existing (`vite.config.js` termasuk `copilot.js`, `php artisan test` 107+ tests, Fuseki 5.025 triples, dan `/admin/cek-foto` lama) harus tetap bekerja 100%.
   - Seluruh logika Alpine.js/JS kompleks pada view baru wajib ditempatkan di dalam blok `<script>` (0 kebocoran kode JS di atribut HTML `x-data`).
</mission_and_strict_rules>

---

## FASE 1: VERIFIKASI FOLDER & RENAME SEKUENSIAL (`1.jpg`, `2.jpg`, `3.jpg`, ...)

1. Verifikasi keberadaan seluruh file JPG di `D:\Documents\TUBES WEB SEMANTIK\FOTO 360P` (112 file `IMG-...-WA....jpg`). Jika path sedikit berbeda di direktori `Documents`, deteksi path aktualnya secara otomatis.
2. Urutkan seluruh file gambar secara deterministik berdasarkan nama file asli (ascending).
3. Lakukan **2-Pass Safe Rename** di folder `FOTO 360P` (pass 1 ke `__tmp_001.jpg` dst., pass 2 ke `1.jpg`, `2.jpg`, `3.jpg`, ..., `112.jpg` agar tidak pernah terjadi tabrakan nama):
   - Simpan peta nama asli <-> nomor baru ke `USUClassMap/storage/app/foto-360p/rename_mapping.json` (contoh: `{"id": 1, "filename": "1.jpg", "original_filename": "IMG-20260908-WA0003.jpg", "size_bytes": ...}`).
4. Salin seluruh file `1.jpg` .. `112.jpg` ke `USUClassMap/public/images/foto-360p/` sehingga langsung dapat dimuat oleh browser & Pannellum 360 viewer di `/admin/cek-foto/360p`.

---

## FASE 2: DEEP SCAN AI PANORAMA 360 (UNWARPING + DETEKSI TULISAN RUANGAN)

Bangun pipeline Python `USUClassMap/scripts/deep_scan_360p.py` dan jalankan terhadap **seluruh 112 foto (`1.jpg` sampai `112.jpg`)**:

1. **Equirectangular-to-Perspective (Rectilinear) Unwarping:**
   - Karena foto 360 mengalami distorsi lengkung di tepi & atas, konversi setiap gambar equirectangular menjadi beberapa potongan sudut pandang datar (*rectilinear tiles* menggunakan `Pillow` + `numpy`), misalnya:
     - 6-8 arah horizontal (`yaw`: `0, 60, 120, 180, -120, -60` derajat atau per `45` derajat) pada ketinggian `pitch = +5` dan/atau `+15` derajat (FOV `80-90` derajat), plus peningkatan ketajaman/kontras ringan (`CLAHE`/sharpen + upscale 2x) agar tulisan kecil di atas pintu/dinding tetap terbaca meski gambar terkompresi WhatsApp.
   - Simpan pemetaan koordinat `(yaw, pitch)` setiap potongan agar posisi tulisan yang ditemukan bisa diarahkan langsung di Pannellum 360 viewer.

2. **Mesin Deep Scan AI Vision + OCR (Eksekusi Nyata untuk Seluruh 112 Foto):**
   - Periksa kredensial AI Vision yang tersedia di `.env` (`GEMINI_API_KEY`, `CLASSAI_API_KEY`, `OPENAI_API_KEY`) atau kemampuan multimodal/vision yang tersedia di lingkungan Anda, serta engine OCR lokal (`rapidocr-onnxruntime` / `easyocr` / `pytesseract` / Windows WinRT OCR) sebagai pendukung atau pelengkap.
   - **Strategi Terbaik:** Gunakan **AI Vision Multimodal** (memindai *contact sheet* berlabel sudut `yaw/pitch` atau potongan tile per foto secara sistematis untuk semua 112 foto) dan/atau kombinasi OCR lokal + verifikasi Vision untuk menemukan **segala bentuk tulisan ruangan/lokasi**:
     - Kode/nomor ruang kuliah (mis. `R. 101`, `Ruang 2.5`, `Kelas B`, `Lab Komputer`, `Studio`, `Aula`, `Ruang Sidang`, `Ruang Dosen`, `Tata Usaha`, `Perpustakaan`, `Musholla`, `Toilet`, papan nama fakultas/departemen/lantai).
   - Catat untuk **SETIAP** foto (`1` s.d. `112`) dalam `USUClassMap/storage/app/foto-360p/scan_results.json`:
     ```json
     {
       "generated_at": "...",
       "total_images": 112,
       "scan_engine": "...",
       "items": [
         {
           "id": 1,
           "filename": "1.jpg",
           "original_filename": "IMG-20260908-WA0003.jpg",
           "image_url": "/images/foto-360p/1.jpg",
           "width": 0,
           "height": 0,
           "size_kb": 83.5,
           "status": "detected",
           "detected_rooms": [
             {
               "room_name": "Ruang 104",
               "raw_text": "RUANG 104 / R. KULIAH",
               "category": "ruang_kuliah",
               "confidence": "high",
               "yaw": 120,
               "pitch": 10,
               "evidence_note": "Papan nama putih di atas pintu kayu sebelah kanan"
             }
           ],
           "all_readable_texts": ["..."],
           "scene_summary": "Koridor lantai 1 dengan pintu ruang kelas di sisi kanan",
           "verified_by_admin": false,
           "admin_note": ""
         }
       ]
     }
     ```
   - Hapus folder sementara tile crop setelah scan selesai agar penyimpanan tetap bersih.

3. **Generate File Daftar Markdown Lengkap:**
   - Buat file: `D:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\DAFTAR_RUANGAN_DEEP_SCAN_360P.md` berisi:
     1. Ringkasan Statistik (Total 112 foto, jumlah foto terdeteksi tulisan ruangan, jumlah foto tanpa tulisan/buram, total nama ruangan unik, metode scan yang dipakai).
     2. **Daftar Indeks Nama Ruangan Terdeteksi** (dikelompokkan/diurutkan rapi: Nama Ruangan -> Nomor Foto (`#1`, `#14`, dst.) -> Teks Asli di Papan -> Sudut Kamera `(yaw, pitch)` -> Catatan Bukti).
     3. **Tabel Lengkap 1-112** (Nomor Foto `1.jpg`..`112.jpg`, Nama Asli, Status, Tulisan Ruangan Terdeteksi, Ringkasan Lokasi).

---

## FASE 3: HALAMAN PEMBUKTIAN TERISOLASI `/admin/cek-foto/360p`

Bangun antarmuka pembuktian khusus di Laravel yang **100% terpisah** dari `/admin/cek-foto`:

1. **Routes (`routes/web.php`) & Controller (`App\Http\Controllers\Admin\Photo360pInspectorController`):**
   - `GET /admin/cek-foto/360p` (`admin.cek-foto.360p.index`) - halaman pembuktian utama (middleware sama dengan `/admin/cek-foto`).
   - `POST /admin/cek-foto/360p/{id}/verify` (`admin.cek-foto.360p.verify`) - simpan koreksi/verifikasi manual admin pada `scan_results.json` dan sinkronkan ulang `DAFTAR_RUANGAN_DEEP_SCAN_360P.md`.
   - `GET /admin/cek-foto/360p/export/{format}` (`md`, `csv`, `json`) - unduh file daftar nama ruangan langsung dari browser.
   - *(Opsional)* Tambahkan 1 tombol tautan ringan di header `/admin/cek-foto` menuju `/admin/cek-foto/360p` (tanpa mengubah fungsi `/admin/cek-foto` sedikit pun).

2. **UI Pembuktian (`resources/views/features/admin/cek-foto-360p.blade.php`):**
   - **Header & Statistik:** Total `112` foto, Terdeteksi Ruangan, Tidak Ada Tulisan/Buram, Sudah Diverifikasi, plus tombol **Export Daftar (.md / .csv / .json)**.
   - **Filter & Pencarian Cepat:** Filter tab (`Semua (112)`, `Terdeteksi Ruangan`, `Tidak Terdeteksi / Buram`, `Sudah Diverifikasi`) + search bar (cari berdasarkan nomor foto `1`-`112` atau teks ruangan).
   - **Split-View Pembuktian (Daftar Kartu Kiri + Viewer 360 Kanan / Modal Interaktif):**
     - Setiap kartu menampilkan nomor besar **`#1` .. `#112`**, thumbnail foto, nama file baru (`1.jpg`), nama file asli (kecil di bawah), status badge, dan daftar pill nama ruangan yang dideteksi AI.
     - **Interactive 360 Pannellum Proof Viewer:**
       - Memuat panorama `1.jpg`..`112.jpg` secara interaktif.
       - Memasang **Hotspot Bukti** pada setiap titik `(yaw, pitch)` tulisan ruangan yang ditemukan AI.
       - Ketika admin mengklik pill nama ruangan yang terdeteksi, kamera 360 **langsung berputar & zoom (`lookAt(pitch, yaw, hfov)`) mengarah tepat ke papan tulisan ruangan tersebut** sebagai bukti visual nyata.
       - Sediakan toggle **Mode 360 Interaktif** vs **Mode Gambar Datar (Flat)** + tombol **Sebelumnya / Berikutnya** untuk navigasi cepat `1` -> `112`.
     - **Panel Verifikasi / Koreksi Admin:** Admin bisa menandai *"Valid / Terverifikasi"*, menambah/mengedit nama ruangan beserta menangkap koordinat `(yaw, pitch)` kamera aktif saat ini dengan 1 klik, lalu klik Simpan.
   - Pastikan seluruh kode JavaScript/Alpine berada bersih di dalam `<script>` (0 kebocoran `x-data`).

---

## FASE 4: VERIFIKASI & QUALITY GATES WAJIB

1. Buat feature test `USUClassMap/tests/Feature/Photo360pInspectorTest.php`:
   - Memastikan non-admin ditolak (`302`/`403`), admin/superadmin mendapat HTTP `200` pada `/admin/cek-foto/360p`.
   - Memastikan endpoint export (`md`, `csv`, `json`) mengembalikan HTTP `200` dan konten valid.
   - Memastikan endpoint `POST /admin/cek-foto/360p/1/verify` menyimpan verifikasi dengan benar (menggunakan data terisolasi/restore agar tidak merusak hasil scan asli).
   - Memastikan halaman `/admin/cek-foto` lama tetap HTTP `200` tanpa regresi.
2. Jalankan Quality Gates di folder `USUClassMap`:
   - `php -l` pada seluruh file PHP baru/diubah (0 error).
   - `php artisan config:clear && php artisan view:cache && php artisan view:clear`.
   - `node --check` pada blok `<script>` hasil render `/admin/cek-foto/360p` (0 syntax error).
   - `npm run build` (pastikan `copilot.js` tetap termasuk, exit code `0`).
   - `php artisan test` penuh (wajib 100% PASS, 0 failed).
   - **Pulihkan Apache Jena Fuseki:** jalankan `php artisan rdf:generate --upload` setelah `php artisan test` dan verifikasi kembali **5.025 triples** aktif di `http://localhost:3030/usuclassmap`.
   - Verifikasi via Playwright Edge headless (jika tersedia) bahwa `/admin/cek-foto/360p` menampilkan `112` foto, Pannellum 360 terbuka normal, tombol klik ruangan memutar kamera, dan **0 uncaught JS error**.
3. Tampilkan ringkasan akhir di chat: total file di-rename (`1.jpg`-`112.jpg`), ringkasan seluruh nama ruangan yang berhasil dideteksi AI, lokasi file `DAFTAR_RUANGAN_DEEP_SCAN_360P.md`, dan URL bukti `http://127.0.0.1:8000/admin/cek-foto/360p`.
````