/**
 * USUClassMap Sync - Popup Script
 * Handles popup UI state, validates active tab is satu.usu.ac.id, and injects content scraper.
 */

document.addEventListener("DOMContentLoaded", async () => {
  const btnSync = document.getElementById("btn-sync");
  const statusDot = document.getElementById("status-dot");
  const statusText = document.getElementById("status-text");
  const logBox = document.getElementById("log-box");

  function setStatus(message, type = "idle") {
    statusText.textContent = message;
    statusDot.className = "status-dot";
    if (type === "active") statusDot.classList.add("active");
    if (type === "error") statusDot.classList.add("error");
  }

  function appendLog(msg) {
    logBox.style.display = "block";
    const p = document.createElement("div");
    p.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    logBox.appendChild(p);
    logBox.scrollTop = logBox.scrollHeight;
  }

  // 1. Check active tab
  try {
    const [activeTab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!activeTab || !activeTab.url) {
      setStatus("Gagal membaca tab aktif.", "error");
      return;
    }

    const isSatuUsu = activeTab.url.includes("satu.usu.ac.id");

    if (isSatuUsu) {
      setStatus("Terhubung: portal satu.usu.ac.id", "active");
      btnSync.disabled = false;
      appendLog("Siap melakukan ekstraksi jadwal.");
    } else {
      setStatus("Bukan halaman satu.usu.ac.id", "error");
      btnSync.disabled = true;
      appendLog("Silakan buka halaman portal SATU USU terlebih dahulu.");
    }
  } catch (err) {
    setStatus("Error perizinan ekstensi.", "error");
    appendLog(err.message);
  }

  // 2. Button Sync Click Handler
  btnSync.addEventListener("click", async () => {
    btnSync.disabled = true;
    btnSync.innerHTML = "Sedang Menarik Data...";
    appendLog("Menyuntikkan skrip scraper...");

    try {
      const [activeTab] = await chrome.tabs.query({ active: true, currentWindow: true });

      // Execute content.js into the active tab
      await chrome.scripting.executeScript({
        target: { tabId: activeTab.id },
        files: ["content.js"],
      });

      appendLog("Skrip ekstraksi berhasil dijalankan di halaman.");
      setStatus("Sinkronisasi sedang diproses...", "active");

      setTimeout(() => {
        btnSync.disabled = false;
        btnSync.innerHTML = `
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          Tarik Jadwal Sekarang
        `;
      }, 3000);
    } catch (err) {
      setStatus("Gagal mengeksekusi scraper.", "error");
      appendLog(`Error: ${err.message}`);
      btnSync.disabled = false;
      btnSync.textContent = "Tarik Jadwal Sekarang";
    }
  });
});
