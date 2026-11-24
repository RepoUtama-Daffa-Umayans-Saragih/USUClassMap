# Blueprint & Master Tracking: Sistem Rute Pejalan Kaki In-App Anti-Tembus Tembok & Anti-Loncat Paret (`USUClassMap`)

> **Status Dokumen:** Tahap 0 (Analisis Masalah, Desain Arsitektur 3-Lapis & Master Tracking) **SELESAI** (`[x]`). Implementasi Kode (Tahap 1–4) **SIAP DIEKSEKUSI** (`[ ]`).
>
> **Target Proyek:** `USUClassMap` (`D:\Documents\TUBES` WEB SEMANTIK\\Kelompok 3` Web Semantik\USUClassMap`)
>
> **Cakupan Entitas Kampus:** **77 Gedung Akademik**, **133 Ruangan**, dan **32 Mitra Fotokopi** (termasuk `Fotocopy Trial Admin`)
>
> **Tanggal Pembuatan:** 24 November 2026

## Daftar Isi

1. [Bab 1: Analisis Masalah & Keputusan Arsitektur Final](#bab-1-analisis-masalah--keputusan-arsitektur-final)

2. [Bab 2: Arsitektur Routing 3-Lapis (Anti-Ngawang, Anti-Tembus, Anti-Loncat Paret)](#bab-2-arsitektur-routing-3-lapis-anti-ngawang-anti-tembus-anti-loncat-paret)

3. [Bab 3: Model Matematis, Obstacle Guard & Logika Last-Mile Pejalan Kaki](#bab-3-model-matematis-obstacle-guard--logika-last-mile-pejalan-kaki)

4. [Bab 4: Desain Data Graf, Skema Override, Kontrak API & Git Snapshot](#bab-4-desain-data-graf-skema-override-kontrak-api--git-snapshot)

5. [Bab 5: Spesifikasi UX Navigasi In-App (`/map`) & Editor Spasial Admin (`/admin-sementara-/map`)](#bab-5-spesifikasi-ux-navigasi-in-app-map--editor-spasial-admin-admin-sementara-map)

6. [Bab 6: Master Tracking Checklist Implementasi (Tahap 1–4) & Verification Gates](#bab-6-master-tracking-checklist-implementasi-tahap-14--verification-gates)

## Bab 1: Analisis Masalah & Keputusan Arsitektur Final

### 1.1 Masalah pada Mekanisme Rute Saat Ini

1. **Melempar Pengguna ke Web Eksternal:** Tombol arah/rute di popup peta, direktori fotokopi, halaman detail toko, halaman order, dan halaman pembayaran masih membuka tab baru ke `https://www.google.com/maps/...`. Pengguna kehilangan konteks peta interaktif `USUClassMap`, hasil pencarian jadwal SPARQL, dan tombol pesan fotokopi.

2. Kelemahan API **Rute Umum di Lingkungan Kampus USU:** Di kawasan Kampus USU (sekitar `Jl. Almamater`, `Jl. Politeknik`, `Jl. Perpustakaan`, `Jl. Alumni`, kompleks FT, FMIPA, dan Fasilkom-TI), terdapat banyak selasar sempit, halaman fakultas, parkiran (`Parkiran Teknik`, `Parkir AULA`), pagar pemisah, serta parit pinggir jalan. Layanan rute generik sering menarik garis lurus (*ngawang*) pada 20–50 meter terakhir dari tepi jalan raya langsung menuju titik tengah atap gedung—sehingga secara visual maupun fisik menembus tembok bangunan atau melompati parit.

3. **Koordinat Gedung Berada di Tengah Atap (*Centroid*), Bukan di Pintu Masuk:** Mayoritas pin gedung dan fotokopi terletak di bagian tengah bangunan, bukan di sisi jalan atau gerbang akses pejalan kaki.

### 1.2 Keputusan Arsitektur Final yang Disetujui

* **Keputusan 1 (100% In-App Navigation — ACC):** Seluruh tombol arah/rute di seluruh halaman **USUClassMap** dialihkan 100% ke sistem navigasi di dalam `/map` (`/map?route_to=...`). **Tidak ada lagi pengalihan (*redirect*) ke situs eksternal Google Maps.** Untuk pengguna yang titik awalnya berada di luar area Kampus USU, rute menuju gerbang kampus tetap digambar langsung di kanvas `/map` menggunakan *hybrid fallback* Mapbox Directions.

* Keputusan 2 (Penyertaan Editor Jalur, Pintu Masuk & Hambatan di Admin Map **— ACC):** Tahap 4 dimasukkan langsung dalam ruang lingkup implementasi. Admin mendapatkan alat interaktif di `/admin-sementara-/map` untuk menambahkan jalur pejalan kaki/jembatan penyeberangan parit lokal, menggambar garis hambatan (*paret/pagar*), serta memindahkan koordinat pintu masuk (*entrance*) gedung dan mitra fotokopi. Seluruh data kustomisasi tersebut terintegrasi penuh dengan **Auto-Sync Git Snapshot** (`app:git-sync`).

## Bab 2: Arsitektur Routing 3-Lapis (Anti-Ngawang, Anti-Tembus, Anti-Loncat Paret)

### 2.1 Ringkasan 3 Lapisan Routing

| Lapisan | Komponen Teknis | Tugas & Jaminan Aturan Fisik | 
| ----- | ----- | ----- | 
| **Lapisan 1: Graf Pejalan Kaki Lokal Kampus USU** | `usu_campus_graph.json` (data jaringan jalan & trotoar OSM Kampus USU + kustomisasi admin) + mesin pencarian jalur **A-Star** lokal di backend Laravel (`CampusRoutingService`). | Menjamin rute **100% menapak pada jaringan jalan, trotoar, selasar, dan jalur pejalan kaki yang ada**. Cepat (`< 15 ms` di memori), bebas kuota API, serta mendukung jalan dua arah untuk pejalan kaki (mengabaikan larangan satu arah kendaraan bermotor). | 
| **Lapisan 2: Obstacle Guard & Entrance Snapping** | Poligon bangunan (`building=*`), garis hambatan (`waterway=ditch/drain`, `barrier=wall/fence`), serta tabel override admin (`campus_routing_overrides`). | **Anti-tembus tembok & anti-loncat paret:** Setiap koneksi *snapping* (dari titik asal/tujuan ke node jalan) dan segmen *last-mile* wajib lolos uji bebas potong (*Line-of-Sight Check*) terhadap seluruh poligon bangunan dan garis hambatan. Jika terhalang, sistem otomatis memutar lewat gerbang, jembatan, atau sudut halaman bebas hambatan terdekat. | 
| **Lapisan 3: Hybrid Fallback Luar Kampus & Mode Kendaraan** | Mapbox Directions API (`mapbox/walking` & `mapbox/driving`) dipanggil melalui proxy backend Laravel lalu disambungkan ke Graf Kampus di gerbang masuk USU. | Jika pengguna memesan rute dari luar batas Kampus USU (misalnya dari kos di Padang Bulan / Dr. Mansyur) atau memilih mode **Motor/Mobil**, rute luar kampus tetap dirender **100% di dalam peta `/map`** tanpa berpindah ke Google Maps. | 

### 2.2 Diagram Alur Keputusan Engine Routing (`CampusRoutingService`)

```
flowchart TD
    A["Request /api/route (Origin, Destination, Mode: walking/driving)"] --> B{"Origin di Dalam Bounding Box Kampus USU?"}
    B -->|"Tidak (Luar Kampus)"| C["Panggil Mapbox Directions API ke Gerbang/Entry Node USU Terdekat"]
    C --> D["Sambungkan ke Graf Kampus USU (In-App)"]
    B -->|"Ya (Dalam Kampus)"| D
    D --> E["Resolve Titik Pintu Masuk (Entrance) Tujuan jika Tersedia"]
    E --> F["Cari Kandidat Node Jalan Terdekat (Radius Bertahap 15m -> 40m -> 100m)"]
    F --> G{"Segmen Snapping Bebas Tembok & Bebas Paret?"}
    G -->|"Terhalang Poligon/Paret"| H["Tolak Kandidat! Uji Kandidat Node Berikutnya / Hitung Jalur Sudut Halaman (Local Visibility Connector)"]
    H --> G
    G -->|"Bebas Hambatan"| I["Jalankan Algoritma A-Star pada Graf Kampus (Sesuai Mode: Walking / Driving)"]
    I --> J{"Seluruh Segmen Hasil A-Star Lolos Validasi Obstacle Guard?"}
    J -->|"Ya"| K["Pisahkan Geometri: main_route (Solid) + access_segments (Dashed)"]
    J -->|"Ditemukan Hambatan Baru"| L["Blokir Edge Terhalang Sementara & Re-run A-Star"]
    L --> I
    K --> M["Generate Instruksi Turn-by-Turn Bahasa Indonesia + Jarak (m) + Estimasi Waktu"]
    M --> N["Render Double-Stroke Route di Kanvas /map (Kompatibel 2D, 3D & Satelit)"]

```

## Bab 3: Model Matematis, Obstacle Guard & Logika Last-Mile Pejalan Kaki

### 3.1 Algoritma A-Star Berbobot Pejalan Kaki

Mesin routing lokal mencari jalur optimal meminimalkan fungsi biaya:

$$
f(n) = g(n) + h(n)
$$

dengan:

* $g(n)$ adalah akumulasi jarak berbobot dari node awal ke node $n$:
  

  $$
  g(n) = \sum_{e \in P(s, n)} d(e) \cdot w_{\text{mode}}(e)
  $$

* $h(n)$ adalah heuristik jarak geografis Haversine (dalam meter) dari node $n$ menuju node tujuan $t$, sehingga menjamin sifat *admissible* ($h(n) \le d_{\text{aktual}}(n, t)$) dan selalu menemukan jalur terpendek dengan kompleksitas $O(E \log V)$.

#### Matriks Bobot Jenis Jalan ($w_{\text{mode}}$)

| Tipe Jalur (`highway` / Kustom Admin) | Bobot Mode **Jalan Kaki (`walking`)** | Bobot Mode **Motor/Mobil (`driving`)** | Keterangan Operasional | 
| ----- | ----- | ----- | ----- | 
| `footway`, `pedestrian`, `corridor`, `campus_walkway` | `$1.00$` | Diblokir | Prioritas utama pejalan kaki, trotoar, dan selasar gedung | 
| `bridge_foot` (Jembatan penyeberangan parit) | `$1.00$` | Diblokir | Jalur legal melintasi garis parit (dikecualikan dari blokir parit) | 
| `path`, `steps`, `living_street` | `$1.05$` | Diblokir | Jalan setapak dan tangga pejalan kaki | 
| `service`, `parking_aisle`, `unclassified` | `$1.12$` | `$1.05$` | Jalan akses fakultas dan area parkiran (`Parkiran Teknik`, `Parkir AULA`) | 
| `residential`, `tertiary`, `secondary` | `$1.18$` | `$1.00$` | Jalan raya dalam kampus (`Jl. Almamater`, `Jl. Politeknik`, `Jl. Perpustakaan`, `Jl. Alumni`) | 
| `primary` / jalan raya besar tanpa trotoar | `$1.45$` | `$1.00$` | Dihindari pejalan kaki jika tersedia jalur dalam kampus yang lebih aman | 

### 3.2 Obstacle Guard (Uji Interseksi 2D Anti-Tembus & Anti-Loncat Paret)

Untuk setiap segmen kandidat $\overline{AB}$ (baik *snapping* ke jaringan jalan maupun jalur *last-mile* halaman), sistem mengambil daftar poligon bangunan dan garis hambatan (*paret/pagar*) di dalam *bounding box* segmen tersebut (ditambah *buffer* $0.0005$ derajat $\approx 55\text{ m}$), lalu menjalankan uji interseksi segmen 2D berbasis orientasi (*counter-clockwise*):

* Segmen $\overline{AB}$ dan segmen dinding/parit $\overline{CD}$ dinyatakan **berpotongan (dilarang)** apabila:
  

  $$
  \text{orient}(A, B, C) \cdot \text{orient}(A, B, D) < 0 \quad \text{dan} \quad \text{orient}(C, D, A) \cdot \text{orient}(C, D, B) < 0
  $$

  
  *(ditambah pengecekan titik kolinear pada segmen serta uji Point-in-Polygon untuk memastikan segmen tidak berada sepenuhnya di dalam bangunan).*

* **Pengecualian Jembatan / Gerbang Legal:** Apabila sebuah *edge* bertipe `bridge_foot` atau `gate_passage` (yang didefinisikan di OSM atau ditambahkan oleh admin lewat Editor Tahap 4), maka *edge* tersebut diizinkan menyeberangi parit/pagar tepat di koordinat jembatan/gerbang tersebut.

### 3.3 Logika Last-Mile untuk Area Tanpa Jalan Terpetakan

Ketika sebuah gedung atau titik asal berada di halaman/pelataran yang belum memiliki garis jalan:

1. **Resolusi Pintu Masuk (*Entrance Point*):**

   * **Prioritas 1:** Memakai koordinat `entrance_lat`, `entrance_lng` yang disetel manual di database (melalui Admin Map Tahap 4).

   * **Prioritas 2:** Jika kosong, sistem memproyeksikan titik *centroid* gedung keluar ke tepi poligon bangunan (*building footprint* + *offset* $2\text{ m}$ ke arah luar) yang memiliki jarak terpendek sekaligus **bebas halangan tembok & parit** ke node jalan kaki terdekat.

2. **Penghindaran Poligon (*Local Visibility Connector*):**

   * Jika garis lurus dari node jalan ke pintu masuk terhalang sudut bangunan lain atau pagar, sistem membangun *visibility graph* lokal menggunakan titik-titik sudut terluar (*buffered vertices* $+1.5\text{ m}$) dari poligon penghalang tersebut, lalu mencari lintasan memutar paling pendek yang 100% bebas potong.

3. **Penandaan Visual Transparan:**

   * Rute di atas jaringan jalan utama dirender sebagai **garis solid**.

   * Segmen penghubung *last-mile* melintasi halaman/parkiran menuju pintu masuk gedung dirender sebagai **garis putus-putus pendek** dengan instruksi langkah terakhir: *"Masuk area halaman/pelataran menuju pintu masuk \[Nama Gedung/Toko\]"*.

### 3.4 Estimasi Kecepatan & Penanganan Edge Cases

* **Kecepatan Referensi:** Jalan kaki `$1.25\text{ m/s}$` (`$4.5\text{ km/jam}$` atau `$75\text{ m/menit}$`); Motor/Mobil kampus `$6.94\text{ m/s}$` (`$25\text{ km/jam}$` sesuai batas aman dalam kampus).

* **Edge Case 1 — GPS User Melenceng ke Dalam Gedung:** Jika titik asal GPS berada di dalam poligon bangunan (akibat *drift* sinyal indoor), sistem secara otomatis memproyeksikan titik asal ke tepi luar poligon yang menghadap node jalan bebas parit terdekat sebelum memulai A-Star.

* **Edge Case 2 — Asal dan Tujuan Sangat Dekat (`$< 25\text{ m}$`):** Jika bebas tembok dan bebas parit, kembalikan langsung jalur akses pendek tanpa memaksa memutar jauh ke jalan raya.

* **Edge Case 3 — Komponen Graf Terisolasi (*Disconnected Subgraph*):** Pada proses `routing:sync-campus-graph`, sistem otomatis menjalankan analisis *Connected Components* untuk menjamin komponen utama graf terhubung penuh dan mendeteksi secara dini apabila ada gedung/fotokopi yang belum tersambung.

## Bab 4: Desain Data Graf, Skema Override, Kontrak API & Git Snapshot

### 4.1 Arsitektur Penyimpanan Hybrid (Statis OSM + Override Dinamis DB + Git Snapshot)

Agar data graf cepat diakses sekaligus mudah dikoreksi oleh admin tanpa menimpa data OSM saat sinkronisasi ulang:

1. Base Graph OSM **(`storage/app/routing/usu_campus_base_graph.json` & backup `database/seeders/snapshots/usu_campus_base_graph.json`):**

   * Dihasilkan oleh command `php artisan routing:sync-campus-graph` (mengambil data via Overpass API untuk bounding box Kampus USU Padang Bulan `3.5515, 98.6470` hingga `3.5720, 98.6695`, dengan fallback ke snapshot lokal jika offline).

   * Memuat: `nodes[]`, `edges[]`, `building_polygons[]`, `waterway_barriers[]`, `fence_barriers[]`, dan metadata versi/jumlah elemen.

2. **Tabel Override Admin & Pintu Masuk (SQLite + Masuk Git Snapshot):**

   * Penambahan Kolom **Pintu Masuk:**

     * Pada tabel `buildings`: `entrance_lat` (`decimal(10,7)`, nullable), `entrance_lng` (`decimal(10,7)`, nullable), `entrance_note` (`string(150)`, nullable).

     * Pada tabel `photocopy_places` & `fotocopy_stores`: `entrance_lat`, `entrance_lng`, `entrance_note`.

   * **Tabel Baru `campus_routing_features`:** Menyimpan koreksi lokal mahasiswa/admin agar tidak hilang saat base graph diperbarui:

     * `id`, `feature_type` (`walkway`, `bridge_foot`, `barrier_ditch`, `barrier_fence`, `blocked_edge`), `name`, `geometry_json` (GeoJSON LineString/Point koordinat), `is_walkable` (boolean), `is_drivable` (boolean), `is_active` (boolean), `notes`, `timestamps()`.

   * **Compiled Runtime Graph (`storage/app/routing/usu_campus_compiled_graph.json`):** Hasil gabungan `Base Graph + campus_routing_features` yang di-cache di memori/file dan otomatis dikompilasi ulang setiap kali admin menyimpan perubahan di Editor Map.

   * **Sinkronisasi Git Snapshot (`GitSnapshotSyncService`):** Menambahkan `campus_routing_features`, kolom `entrance_*`, dan `usu_campus_base_graph.json` ke manifest `php artisan app:git-sync --export/--import` agar seluruh anggota tim mendapatkan graf dan koreksi jembatan/paret yang sama persis setelah `git pull`.

### 4.2 Kontrak API Routing & Admin Editor

#### A. Endpoint Publik Navigasi (`routes/api.php`)

* **`POST /api/route`** *(mendukung juga `GET /api/route` untuk pengujian cepat)*

  * **Parameter Input:**

    ```
    {
      "origin_lat": 3.56085,
      "origin_lng": 98.65520,
      "destination_type": "fotocopy",
      "destination_id": 32,
      "destination_lat": null,
      "destination_lng": null,
      "mode": "walking"
    }
    
    ```

  * **Respons Sukses (`HTTP 200`):**

    ```
    {
      "status": "ok",
      "engine": "usu_campus_astar",
      "mode": "walking",
      "origin": { "lat": 3.56085, "lng": 98.65520, "label": "FT-MESIN" },
      "destination": { "lat": 3.56268, "lng": 98.65912, "entrance_lat": 3.56271, "entrance_lng": 98.65908, "label": "Fotocopy Trial Admin" },
      "summary": {
        "distance_meters": 540,
        "duration_minutes": 7,
        "duration_text": "7 menit jalan kaki",
        "distance_text": "540 m"
      },
      "validation": {
        "building_intersections": 0,
        "barrier_intersections": 0,
        "uses_last_mile_connector": true
      },
      "geojson": {
        "main_route": { "type": "Feature", "geometry": { "type": "LineString", "coordinates": [] } },
        "access_segments": { "type": "FeatureCollection", "features": [] }
      },
      "steps": [
        { "index": 1, "maneuver": "depart", "instruction": "Mulai berjalan ke
    
    ```