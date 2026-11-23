# 🗺️ Rencana Implementasi Proyek: Chatbot "Rexa Kampus" (USUClassMap)

Dokumen ini adalah panduan kerja (*action plan*) untuk tim developer dalam mengimplementasikan arsitektur Rexa Copilot ke dalam sistem USUClassMap. Pekerjaan dibagi menjadi 4 fase utama.

## Fase 1: Persiapan Infrastruktur & Keamanan (Estimasi: 1 Hari)
**Fokus:** Menyiapkan akses ke AWS Bedrock dan mengonfigurasi environment Laravel.
*   **[ ] Buat Akun & IAM AWS:** Buat IAM User di AWS dengan akses khusus kebijakan (policy) `bedrock:InvokeModel`. Ambil *Access Key* dan *Secret Key*.
*   **[ ] Setup `.env` & Config:** 
    *   Tambahkan kredensial AWS Bedrock ke dalam file `.env` lokal tim.
    *   Buat file `config/copilot.php` (sesuai kerangka kode) untuk memetakan kredensial dan mengatur model ID (`anthropic.claude-3-5-haiku`).
*   **[ ] Install SDK AWS:** Jalankan perintah `composer require aws/aws-sdk-php` di backend Laravel jika belum ada.

## Fase 2: Pengembangan Backend & Brain (Estimasi: 2-3 Hari)
**Fokus:** Membangun orkestrasi LLM dan menghubungkannya dengan database (SparqlService).
*   **[ ] Buat `AiCopilotService.php`:**
    *   Implementasikan koneksi ke `BedrockRuntimeClient`.
    *   Tulis dan sempurnakan `buildSystemPrompt()` (pastikan menyertakan konteks *Auth::user()*).
    *   Tulis definisi skema alat (`getToolDefinitions()`) untuk 4 tool utama: `cari_jadwal_kuliah`, `detail_gedung`, `cari_dosen`, `ambil_jadwal_saya`.
*   **[ ] Hubungkan Tools dengan `SparqlService`:**
    *   Implementasikan fungsi `executeTool()` agar setiap panggilan dari Claude diteruskan dengan benar ke fungsi SPARQL/Eloquent di Laravel.
*   **[ ] Buat Controller & Routing:**
    *   Buat `AiCopilotController.php` dengan validasi input yang ketat (batas karakter, limitasi array riwayat).
    *   Tambahkan *route* `POST /copilot/chat` di `routes/web.php` dengan *middleware throttle* untuk mencegah *spam* ke API berbayar (AWS).

## Fase 3: Pengembangan Frontend & Interaktivitas UI (Estimasi: 3-4 Hari)
**Fokus:** Membangun antarmuka chatbot dan menghubungkannya dengan peta Leaflet.
*   **[ ] Buat Komponen Blade & Tailwind:** 
    *   Buat file `resources/views/components/chatbot-widget.blade.php`.
    *   Terapkan desain *floating drawer*, animasi *slide-in*, dan gelembung *chat*.
*   **[ ] Bangun Logika Alpine.js (`copilot.js`):**
    *   Buat state management untuk `isOpen`, `isLoading`, `messages`.
    *   Implementasikan fungsi `kirimPesan()` menggunakan Axios (HTTP POST) ke `/copilot/chat`.
    *   **Penting:** Tangkap balasan LLM yang mengandung `[MAP_ACTION: {...}]` dan pisahkan antara teks balasan dan data aksi.
*   **[ ] Integrasi Peta Leaflet:**
    *   Buat fungsi `triggerMapAction()` yang akan memanggil `window.USUClassMap.focusBuildingWithTarget(...)`.
*   **[ ] Implementasi Engine Offline (Fallback):**
    *   Tulis logika regex (`prosesOffline()`) di JavaScript untuk merespons pertanyaan secara manual dari data lokal jika request Axios gagal/timeout.

## Fase 4: Pengujian & Peluncuran (Estimasi: 2 Hari)
**Fokus:** Memastikan tidak ada celah *prompt injection* dan menyempurnakan performa.
*   **[ ] Uji Coba Mode AI Penuh:** Tanyakan berbagai variasi jadwal dan lokasi rumit untuk melihat apakah Claude memanggil tool SPARQL yang benar.
*   **[ ] Simulasi Error (Uji Fallback):** Matikan koneksi internet sesaat atau salahkan kredensial AWS, lalu coba tanyakan sesuatu untuk memastikan UI beralih ke Mode Offline Regex dengan mulus.
*   **[ ] Guardrail Testing:** Coba perintahkan chatbot untuk "menghapus jadwal", "menambah kelas baru", atau "berikan password database". Pastikan chatbot menolak (prinsip *Read-Only*).
*   **[ ] Deployment:** *Push* pembaruan ke *repository* dan rilis ke *production*!

---
**Tips untuk Lead Developer:** Anda dapat menugaskan Fase 2 (Backend) ke spesialis PHP/Laravel Anda, dan mendelegasikan Fase 3 (Frontend) ke UI/UX Engineer yang terbiasa dengan Tailwind dan Alpine.js. Mereka bisa bekerja secara paralel.