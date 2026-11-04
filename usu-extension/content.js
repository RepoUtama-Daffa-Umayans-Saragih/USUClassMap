/**
 * USUClassMap Sync - Content Script (DOM Scraper)
 * Injected into portal satu.usu.ac.id to extract schedules and send them to Laravel.
 */

(function () {
  console.log("[USUClassMap Sync] Memulai proses ekstraksi jadwal dari halaman SATU USU...");

  /**
   * Helper: Show on-page Toast Notification
   */
  function showToast(message, type = "success") {
    let toast = document.getElementById("usuclassmap-sync-toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "usuclassmap-sync-toast";
      toast.style.position = "fixed";
      toast.style.bottom = "24px";
      toast.style.right = "24px";
      toast.style.zIndex = "999999";
      toast.style.padding = "14px 20px";
      toast.style.borderRadius = "10px";
      toast.style.color = "#FFFFFF";
      toast.style.fontSize = "13px";
      toast.style.fontWeight = "600";
      toast.style.boxShadow = "0 10px 15px -3px rgba(0, 0, 0, 0.2)";
      toast.style.transition = "all 0.3s ease";
      toast.style.fontFamily = "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif";
      document.body.appendChild(toast);
    }

    toast.style.backgroundColor = type === "success" ? "#10A760" : "#EF4444";
    toast.textContent = message;
    toast.style.opacity = "1";
    toast.style.transform = "translateY(0)";

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateY(10px)";
    }, 4500);
  }

  /**
   * Helper: Parse Time Range string e.g. "08.00 - 09.40" or "08:00 - 10:30"
   */
  function parseTimeRange(timeStr) {
    if (!timeStr) return { start: "08:00", end: "09:40" };
    const cleaned = timeStr.replace(/\./g, ":");
    const match = cleaned.match(/(\d{1,2}:\d{2})\s*[-–—]\s*(\d{1,2}:\d{2})/);
    if (match) {
      return {
        start: match[1].padStart(5, "0"),
        end: match[2].padStart(5, "0"),
      };
    }
    return { start: "08:00", end: "09:40" };
  }

  /**
   * Helper: Normalize Indonesian Day Name
   */
  function normalizeDay(text) {
    if (!text) return "Senin";
    const lower = text.toLowerCase();
    if (lower.includes("senin") || lower.includes("mon")) return "Senin";
    if (lower.includes("selasa") || lower.includes("tue")) return "Selasa";
    if (lower.includes("rabu") || lower.includes("wed")) return "Rabu";
    if (lower.includes("kamis") || lower.includes("thu")) return "Kamis";
    if (lower.includes("jumat") || lower.includes("jum'at") || lower.includes("fri")) return "Jumat";
    if (lower.includes("sabtu") || lower.includes("sat")) return "Sabtu";
    if (lower.includes("minggu") || lower.includes("sun")) return "Minggu";
    return "Senin";
  }

  /**
   * Main DOM Scraping Logic
   * Adapts flexibly to table layouts, card widgets, or list rows.
   */
  function extractSchedulesFromDOM() {
    const schedules = [];

    // =========================================================================
    // STRATEGI 1: PARSER TABEL JADWAL KULIAH / PRESENSI
    // Target: Tabel dengan baris data (tr)
    // =========================================================================
    const tableRows = document.querySelectorAll("table tbody tr, .table-responsive tr");
    if (tableRows && tableRows.length > 0) {
      tableRows.forEach((row) => {
        const cells = row.querySelectorAll("td");
        if (cells.length >= 4) {
          const rowText = row.innerText.trim();
          // Skip header rows
          if (rowText.toLowerCase().includes("mata kuliah") || rowText.toLowerCase().includes("waktu")) {
            return;
          }

          // Coba ekstrak berdasarkan posisi kolom tabel standar SATU USU
          const courseName = cells[1]?.innerText?.trim() || cells[0]?.innerText?.trim();
          const lecturer = cells[2]?.innerText?.trim() || "Dosen Pengampu";
          const rawDayTime = cells[3]?.innerText?.trim() || "";
          const room = cells[4]?.innerText?.trim() || cells[3]?.innerText?.trim() || "Ruang Kelas";

          if (courseName && courseName.length > 2) {
            const timeObj = parseTimeRange(rawDayTime);
            schedules.push({
              mata_kuliah: courseName,
              kode_mk: courseName.substring(0, 7).toUpperCase().replace(/\s/g, ""),
              hari: normalizeDay(rawDayTime || rowText),
              jam_mulai: timeObj.start,
              jam_selesai: timeObj.end,
              ruangan: room || "Ruang Kuliah",
              dosen: lecturer || "Dosen Pengampu",
              kelas: "A",
              semester: "Ganjil 2024/2025",
            });
          }
        }
      });
    }

    // =========================================================================
    // STRATEGI 2: PARSER KARTU JADWAL HARIAN (CARD / WIDGET)
    // Target: Elemen kartu hari/jadwal (misal: .card, .jadwal-item, .schedule-card)
    // =========================================================================
    if (schedules.length === 0) {
      const scheduleCards = document.querySelectorAll(
        ".card, .schedule-item, .jadwal-item, div[class*='jadwal'], div[class*='schedule'], .timeline-item"
      );

      scheduleCards.forEach((card) => {
        const cardText = card.innerText.trim();
        if (cardText.length < 5) return;

        // Ambil elemen judul / nama mata kuliah
        const titleEl = card.querySelector("h4, h5, h6, .title, .subject, strong, b, .font-bold");
        const courseName = titleEl ? titleEl.innerText.trim() : "";

        if (courseName && courseName.length > 2) {
          const timeObj = parseTimeRange(cardText);
          const roomMatch = cardText.match(/(?:ruang|gedung|lokasi|r\.)\s*[:]?\s*([A-Za-z0-9\s\-]+)/i);
          const room = roomMatch ? roomMatch[1].trim() : "Ruang Kelas";

          const lecturerMatch = cardText.match(/(?:dosen|pengajar)\s*[:]?\s*([A-Za-z\s,\.]+)/i);
          const lecturer = lecturerMatch ? lecturerMatch[1].trim() : "Dosen Pengampu";

          schedules.push({
            mata_kuliah: courseName,
            kode_mk: courseName.substring(0, 7).toUpperCase().replace(/\s/g, ""),
            hari: normalizeDay(cardText),
            jam_mulai: timeObj.start,
            jam_selesai: timeObj.end,
            ruangan: room,
            dosen: lecturer,
            kelas: "A",
            semester: "Ganjil 2024/2025",
          });
        }
      });
    }

    // =========================================================================
    // FALLBACK / SAMPLE DATA GENERATOR (Jika format DOM khusus atau belum login)
    // =========================================================================
    if (schedules.length === 0) {
      console.warn("[USUClassMap Sync] Elemen DOM tabel/kartu spesifik tidak terdeteksi. Menggunakan parser cerdas cadangan...");
      // Ambil teks seluruh halaman dan ekstrak pola baris
      const bodyLines = document.body.innerText.split("\n").filter((l) => l.trim().length > 0);
      for (let i = 0; i < bodyLines.length; i++) {
        const line = bodyLines[i];
        if (line.match(/(?:SKS|Smt|Semester|Kelas|Waktu)/i) && bodyLines[i + 1]) {
          const candidateName = bodyLines[i + 1].trim();
          if (candidateName.length > 3 && candidateName.length < 60) {
            schedules.push({
              mata_kuliah: candidateName,
              kode_mk: "MK-" + (schedules.length + 1),
              hari: "Senin",
              jam_mulai: "08:00",
              jam_selesai: "09:40",
              ruangan: "Gedung Kuliah Bersama (GKB)",
              dosen: "Dosen Pengampu USU",
              kelas: "A",
              semester: "Ganjil 2024/2025",
            });
          }
        }
      }
    }

    return schedules;
  }

  // Execute extraction
  const extractedSchedules = extractSchedulesFromDOM();
  console.log(`[USUClassMap Sync] Berhasil mengekstrak ${extractedSchedules.length} jadwal:`, extractedSchedules);

  if (extractedSchedules.length === 0) {
    showToast("⚠️ Tidak menemukan elemen jadwal di halaman ini. Pastikan Anda berada di menu Jadwal / Presensi.", "error");
    alert("USUClassMap Sync: Tidak menemukan elemen jadwal di halaman ini. Buka halaman Jadwal Kuliah di satu.usu.ac.id dan coba lagi.");
    return;
  }

  // Payload structure for Laravel API
  const payload = {
    source: "satu.usu.ac.id",
    synced_at: new Date().toISOString(),
    total_count: extractedSchedules.length,
    schedules: extractedSchedules,
  };

  const API_ENDPOINT = "http://127.0.0.1:8000/api/schedule/sync";

  showToast(`⏳ Mengirimkan ${extractedSchedules.length} data jadwal ke USUClassMap...`, "success");

  // Send POST to Laravel API
  fetch(API_ENDPOINT, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP Error status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("[USUClassMap Sync] Response sukses dari Laravel Backend:", data);
      showToast(`✅ Sinkronisasi Berhasil! ${extractedSchedules.length} jadwal masuk ke USUClassMap.`, "success");
      alert(`Sinkronisasi Berhasil!\n\nSebanyak ${extractedSchedules.length} jadwal berhasil dikirim ke server USUClassMap (Laravel).`);
    })
    .catch((error) => {
      console.error("[USUClassMap Sync] Gagal mengirim data ke backend:", error);
      showToast(`❌ Gagal terhubung ke Laravel (127.0.0.1:8000): ${error.message}`, "error");
      alert(`Sinkronisasi Gagal!\n\nTidak dapat menghubungi http://127.0.0.1:8000/api/schedule/sync.\nPastikan server Laravel Anda sedang berjalan (php artisan serve).`);
    });
})();
