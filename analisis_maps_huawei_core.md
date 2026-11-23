# 🛰️ ANALISIS ARSITEKTUR MAPS 3D HUAWEI C.O.R.E. & RENCANA ADAPTASI KE USUCLASSMAP
**Dokumen:** Analisis Teknis & Peta Padanan Adopsi Engine Spasial 3D  
**Sumber Referensi:** Proyek `Huawei C.O.R.E.` (`d:\Documents\HUAWEI-CORE`)  
**Target Proyek:** Proyek `USUClassMap` (`d:\Documents\TUBES WEB SEMANTIK\Kelompok 3 Web Semantik\USUClassMap`)  
**Single Source of Truth:** `audit_fitur_maps.md`  
**Auditor & Arsitek:** Senior Full-Stack & Geospatial Software Architect

---

## 1. PENDAHULUAN & LANDASAN ARSITEKTUR
Proyek **Huawei C.O.R.E. (Clearance Optimization & Routing Engine)** mengimplementasikan sistem Digital Twin Spasial berbasis **Mapbox GL JS v3.35+** dengan kemampuan rendering WebGL 3D yang sangat kaya (gedung 3D *fill-extrusion*, *camera pitch & bearing*, *dynamic geofence radar*, dan *HUD telemetry*).

Tujuan dokumen ini adalah membedah arsitektur teknis visualisasi peta Huawei C.O.R.E. dan menyusun cetak biru (*blueprint*) adaptasi bersih ke dalam **USUClassMap**. Sesuai prinsip **KISS** dan instruksi arsitektural:
* **NO React / Next.js Bloat:** Seluruh keunggulan visual C.O.R.E. diadaptasi ke dalam **Vanilla JS (`mapbox-gl`) + Tailwind CSS + Alpine.js** yang sudah terpasang di USUClassMap.
* **100% Zero-Regression:** Seluruh 11 method pada `window.USUClassMap`, 7 custom DOM event, 5 custom marker, smart focus target index 0, dan integrasi Rexa Copilot (`copilot.js`) yang tercatat di `audit_fitur_maps.md` dipertahankan secara utuh.

---

## 2. BEDAH ENGINE & KONFIGURASI MAPBOX 3D HUAWEI C.O.R.E.

Berdasarkan inspeksi langsung pada `d:\Documents\HUAWEI-CORE\src\components\MockMap.tsx`, `LiveSimulation.tsx`, dan `DriverMockMap.tsx`:

### 2.1. Versi Library & Sumber Daya
* **Library:** `mapbox-gl` v3.35.0 (dengan wrapper `react-map-gl/mapbox` v8.1.3 di C.O.R.E.).
* **Mapbox Style:** `mapbox://styles/mapbox/streets-v12` (menghasilkan peta modern, jernih, dan memiliki vektor layer bangunan `composite -> building` yang sangat detail).
* **Token Konfigurasi:** Terdaftar di `.env.local` sebagai `NEXT_PUBLIC_MAPBOX_TOKEN` (nilai default token publik aktif: `YOUR_MAPBOX_TOKEN`).

### 2.2. Injeksi Layer 3D Building Extrusion
C.O.R.E. menginjeksi layer bangunan 3D dinamis saat peta selesai dimuat (`onLoad` / `handleMapLoad`):
```javascript
// Menempatkan layer 3D di bawah layer label teks agar nama jalan/gedung tetap terbaca jelas
const layers = map.getStyle()?.layers;
let labelLayerId;
if (layers) {
    for (const layer of layers) {
        if (layer.type === 'symbol' && layer.layout?.['text-field']) {
            labelLayerId = layer.id;
            break;
        }
    }
}

if (!map.getLayer('3d-buildings') && map.getSource('composite')) {
    map.addLayer({
        id: '3d-buildings',
        source: 'composite',
        'source-layer': 'building',
        filter: ['==', 'extrude', 'true'],
        type: 'fill-extrusion',
        minzoom: 14,
        paint: {
            'fill-extrusion-color': '#e2e8f0', // Slate modern yang bersih
            'fill-extrusion-height': ['get', 'height'],
            'fill-extrusion-base': ['get', 'min_height'],
            'fill-extrusion-opacity': 0.75
        }
    }, labelLayerId);
}
```

### 2.3. Parameter Kamera 3D (Pitch, Bearing, & Zoom)
* **Default Viewport C.O.R.E.:**
  * `pitch: 45` s/d `50` (kemiringan sudut kamera untuk memperlihatkan elevasi 3D gedung).
  * `bearing: -15` (rotasi azimut lembut yang memberikan kesan isometrik sinematik).
  * `zoom: 15` – `16`.
* **Animasi Kamera (*Camera Maneuver*):**
  * Transisi halus menggunakan `map.flyTo({ center, zoom, pitch, bearing, essential: true, duration: 1200 })` atau `map.easeTo({ ... })`.

---

## 3. VISUALISASI, HUD & EFEK SPASIAL (HUAWEI C.O.R.E.)

### 3.1. Floating Controls & HUD (Heads-Up Display)
C.O.R.E. menempatkan kontrol di kanvas peta secara elegan menggunakan Tailwind CSS dengan `backdrop-blur` dan border transparan:
1. **Toggle Sudut Pandang 2D / 3D:**
   * Mode 3D: `pitch: 50, bearing: -15`.
   * Mode 2D (Top-Down): `pitch: 0, bearing: 0`.
2. **Reset Kamera Kampus:** Tombol satu-klik untuk mengembalikan peta ke titik fokus pusat kampus USU.
3. **Kompas Interaktif & Zoom Widget:** Menggunakan `mapboxgl.NavigationControl({ visualizePitch: true })` di kanan atas (`top-right`).

### 3.2. Custom HTML Marker & Glow Effects
Marker di C.O.R.E. dibangun menggunakan elemen DOM kustom dengan CSS animation:
* **Radar Pulse Ring:** Animasi `@keyframes ping` ganda di sekeliling marker utama untuk menandai objek prioritas.
* **Status Badge Dinamis:** Chip status mini di atas pin yang memuat informasi live.
* **SDF WebGL Icons & Tooltip:** Penggunaan tooltip elegan dengan border menyala saat objek di-hover atau di-drag.

---

## 4. MANAJEMEN SIKLUS HIDUP & PERFORMA WEBGL

1. **Resize Canvas:** Saat container peta berubah ukuran (misalnya saat sidebar mobile dibuka/tutup), C.O.R.E. memanggil `map.resize()` untuk mencegah kanvas terdistorsi atau terpotong.
2. **Pencegahan WebGL Context Loss:** Instance peta dibersihkan secara eksplisit menggunakan `map.remove()` jika komponen dilepas (*unmounted*).
3. **Graceful Fallback Token:** C.O.R.E. memiliki penanganan visual jika token Mapbox kosong atau salah, menampilkan kartu peringatan modern di atas kanvas daripada membiarkan layar menjadi *blank white screen*.

---

## 5. PETA PADANAN (MAPPING PLAN): C.O.R.E. KE USUCLASSMAP

Berikut adalah pemetaan langsung dari fitur visual C.O.R.E. ke seluruh kontrak wajib `audit_fitur_maps.md`:

| Fitur / Komponen | Implementasi di Huawei C.O.R.E. | Rencana Adaptasi di USUClassMap (Vanilla JS + Alpine) | Status Kontrak |
|:---|:---|:---|:---:|
| **Engine Spasial** | `mapbox-gl` (React / Next.js) | `mapbox-gl` v3.x (NPM / Vite bundle + Vanilla JS) | Wajib |
| **Format Koordinat** | `[lng, lat]` (GeoJSON standard) | Helper konversi `toMapboxCoord(lat, lng)` dan `toLeafletCoord(lng, lat)` | **KRITIS** |
| **Titik Pusat Peta** | Jakarta (`106.8520, -6.2010`) | **Kampus USU Padang Bulan:** `[98.656520, 3.565245]` | Wajib |
| **Gedung 3D** | `fill-extrusion` layer di `streets-v12` | Injeksi layer `3d-buildings` di bawah label teks | Baru (Upgrade) |
| **HUD Controls** | Floating React buttons | Floating Tailwind buttons: **Toggle 2D/3D**, **Reset Center**, **Layer Fotokopi** | Baru (Upgrade) |
| **Custom Markers (5 Tipe)** | React `<Marker>` components | `new mapboxgl.Marker({ element: el })` murni DOM HTML (Pin Hijau `#10A760`, Pin Emas `#D4A017`, Pin Oranye Kelas Saya `#EA580C`, Pin Fotokopi Zamrud `#059669`, Pin Admin Draggable) | Wajib 100% |
| **Popup 340px & Smart Focus** | Next.js popup drawer | `new mapboxgl.Popup({ maxWidth: '340px', className: 'custom-popup usu-custom-popup' })` dengan logika sorting index 0 untuk target ruangan & jadwal | Wajib 100% |
| **Admin Draggable Marker** | React Marker drag | `marker.setDraggable(true)` + event `marker.on('dragend', ...)` memancarkan `coordinate-updated` | Wajib 100% |
| **Global JS Contract** | Tidak ada | **Adapter Pattern:** Mengekspos seluruh 11 method `window.USUClassMap` secara identik | **KRITIS (Chatbot)** |
| **Multi-Mode Media Modal** | Tidak ada | Pertahankan event `open-room-detail-modal` ke Alpine.js modal (Foto, 360° Pannellum, Video POV) | Wajib 100% |

---

## 6. KESIMPULAN & LANGKAH EKSEKUSI TAHAP 3

Arsitektur Mapbox GL JS 3D dari Huawei C.O.R.E. dapat diintegrasikan ke dalam USUClassMap secara **bersih, elegan, dan tanpa ketergantungan React**. Dengan mengimplementasikan pola **Adapter Pattern**, seluruh sistem lain (Chatbot Rexa, Landing Page, Halaman Bookmark, dan Admin Spatial Hub) akan langsung berfungsi normal tanpa perlu diubah sama sekali.

Dokumen ini menjadi dasar langsung untuk mengeksekusi **Tahap 3 (Implementasi Teknis)**.
