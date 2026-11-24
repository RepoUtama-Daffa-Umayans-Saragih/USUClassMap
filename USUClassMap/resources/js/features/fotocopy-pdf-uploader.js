import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const MAX_PDF_BYTES = 25 * 1024 * 1024; // 25 MB per file

/**
 * Inspect an uploaded PDF File object in the browser:
 * - Verifies .pdf extension, file size (<= 25MB), and "%PDF-" header bytes.
 * - Extracts exact numPages using pdfjs-dist with a local Vite-bundled worker.
 * - Handles PasswordException gracefully so encrypted PDFs fall back to manual page count.
 */
window.usuInspectPdfFile = async function usuInspectPdfFile(file) {
    if (!(file instanceof File)) {
        return { valid: false, error: 'Objek file tidak valid.' };
    }

    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (ext !== 'pdf') {
        return { valid: false, error: 'Hanya file berformat .pdf yang diperbolehkan.' };
    }

    if (file.size <= 0) {
        return { valid: false, error: 'File PDF kosong (0 byte).' };
    }

    if (file.size > MAX_PDF_BYTES) {
        return { valid: false, error: 'Ukuran file melebihi batas maksimal 25 MB.' };
    }

    let buffer;
    try {
        buffer = await file.arrayBuffer();
    } catch (e) {
        return { valid: false, error: 'Gagal membaca isi file dari perangkat Anda.' };
    }

    const headerBytes = new Uint8Array(buffer.slice(0, 5));
    const headerStr = String.fromCharCode(...headerBytes);
    if (headerStr !== '%PDF-') {
        return {
            valid: false,
            error: 'Struktur file bukan PDF valid (signature %PDF- tidak ditemukan).',
        };
    }

    try {
        const loadingTask = pdfjsLib.getDocument({
            data: new Uint8Array(buffer),
            verbosity: 0,
        });
        const pdf = await loadingTask.promise;
        const pageCount = Math.max(1, Number(pdf?.numPages || 1));

        if (typeof pdf?.destroy === 'function') {
            pdf.destroy();
        }

        return {
            valid: true,
            pageCount,
            isEncrypted: false,
            warning: null,
        };
    } catch (err) {
        if (err && err.name === 'PasswordException') {
            return {
                valid: true,
                pageCount: 1,
                isEncrypted: true,
                warning: 'PDF terkunci password. Masukkan jumlah halaman manual & cantumkan password pada kolom Note jika perlu dicetak.',
            };
        }

        return {
            valid: false,
            error: 'File PDF rusak atau tidak dapat diproses oleh pembaca dokumen.',
        };
    }
};
