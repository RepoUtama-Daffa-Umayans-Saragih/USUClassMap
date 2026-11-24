<?php

namespace App\Services\Foto360p;

class Photo360pMarkdownBuilder
{
    /**
     * Compute aggregate scan statistics and unique room index.
     *
     * @param array<string, mixed> $data
     * @return array{
     *     total: int,
     *     detected: int,
     *     no_text: int,
     *     blurry: int,
     *     pending: int,
     *     verified: int,
     *     unique_rooms: int,
     *     room_index: array<string, array<int, array<string, mixed>>>
     * }
     */
    public function stats(array $data): array
    {
        $items = $data['items'] ?? [];
        $detected = 0;
        $noText = 0;
        $blurry = 0;
        $pending = 0;
        $verified = 0;
        $roomIndex = [];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'no_text');
            if ($status === 'detected') {
                $detected++;
            } elseif ($status === 'blurry') {
                $blurry++;
            } elseif ($status === 'pending') {
                $pending++;
            } else {
                $noText++;
            }

            if (!empty($item['verified_by_admin'])) {
                $verified++;
            }

            foreach ($item['detected_rooms'] ?? [] as $room) {
                $name = trim((string) ($room['room_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $normKey = mb_strtoupper(preg_replace('/\s+/', ' ', $name) ?? $name, 'UTF-8');
                if (!isset($roomIndex[$normKey])) {
                    $roomIndex[$normKey] = [
                        'display_name' => $name,
                        'occurrences'  => [],
                    ];
                }
                $roomIndex[$normKey]['occurrences'][] = [
                    'id'                => (int) ($item['id'] ?? 0),
                    'filename'          => (string) ($item['filename'] ?? ''),
                    'raw_text'          => (string) ($room['raw_text'] ?? $name),
                    'verified_read'     => (string) ($room['verified_read'] ?? ($room['raw_text'] ?? $name)),
                    'category'          => (string) ($room['category'] ?? 'lainnya'),
                    'confidence'        => (string) ($room['confidence'] ?? 'medium'),
                    'yaw'               => (float) ($room['yaw'] ?? 0.0),
                    'pitch'             => (float) ($room['pitch'] ?? 0.0),
                    'evidence_note'     => (string) ($room['evidence_note'] ?? ''),
                    'verified_by_admin' => !empty($item['verified_by_admin']),
                ];
            }
        }

        ksort($roomIndex, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'total'        => count($items),
            'detected'     => $detected,
            'no_text'      => $noText,
            'blurry'       => $blurry,
            'pending'      => $pending,
            'verified'     => $verified,
            'unique_rooms' => count($roomIndex),
            'room_index'   => $roomIndex,
        ];
    }

    /**
     * Build the full structured Markdown report (statistics, room index, 1-112 table).
     *
     * @param array<string, mixed> $data
     */
    public function build(array $data): string
    {
        $stats = $this->stats($data);
        $items = $data['items'] ?? [];
        $generatedAt = (string) ($data['generated_at'] ?? '-');
        $scanEngine = (string) ($data['scan_engine'] ?? '-');

        $lines = [];
        $lines[] = '# DAFTAR RUANGAN DEEP SCAN PANORAMA 360P (FOTO `1.jpg` – `112.jpg`)';
        $lines[] = '';
        $lines[] = '> **Kebijakan Zero Hallucination:** Setiap tulisan ruangan di bawah ini dipindai langsung secara visual dari potongan rectilinear multi-sudut panorama `FOTO 360P` dan diverifikasi melalui *zoom verification pass*. Foto yang tidak memuat papan tulisan ruangan atau terlalu buram dicatat secara jujur sebagai `no_text` atau `blurry`.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 1. Ringkasan Statistik Deep Scan';
        $lines[] = '';
        $lines[] = '| Metrik | Nilai |';
        $lines[] = '| :--- | :--- |';
        $lines[] = "| Total Foto Panorama (`1.jpg` – `{$stats['total']}.jpg`) | **{$stats['total']}** |";
        $lines[] = "| Foto Terdeteksi Tulisan Ruangan (`detected`) | **{$stats['detected']}** |";
        $lines[] = "| Foto Tanpa Tulisan Ruangan (`no_text`) | **{$stats['no_text']}** |";
        $lines[] = "| Foto Tulisan Tidak Terbaca / Buram (`blurry`) | **{$stats['blurry']}** |";
        $lines[] = "| Terverifikasi Manual Admin | **{$stats['verified']}** |";
        $lines[] = "| Total Nama Ruangan Unik Terdeteksi | **{$stats['unique_rooms']}** |";
        $lines[] = '| Mesin & Metode Scan | `' . $this->escapeMd($scanEngine) . '` |';
        $lines[] = '| Terakhir Diperbarui | `' . $this->escapeMd($generatedAt) . '` |';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 2. Daftar Indeks Nama Ruangan Terdeteksi';
        $lines[] = '';

        if (empty($stats['room_index'])) {
            $lines[] = '_Belum ada papan tulisan nama ruangan yang terbaca secara fisik dan lolos verifikasi zoom pada dataset ini._';
            $lines[] = '';
        } else {
            $lines[] = '| No | Nama Ruangan | Foto Terkait | Teks Asli di Papan | Sudut Kamera `(yaw, pitch)` | Keyakinan | Catatan Bukti | Verifikasi |';
            $lines[] = '| ---: | :--- | :--- | :--- | :--- | :--- | :--- | :---: |';

            $no = 0;
            foreach ($stats['room_index'] as $group) {
                $no++;
                $displayName = $this->escapeMd($group['display_name']);
                $occs = $group['occurrences'];

                foreach ($occs as $idx => $occ) {
                    $rowNo = $idx === 0 ? (string) $no : '';
                    $rowRoom = $idx === 0 ? "**{$displayName}**" : '↳ *(foto lain)*';
                    $photoRef = "`#{$occ['id']}` ({$occ['filename']})";
                    $rawText = '`' . $this->escapeMd($occ['raw_text']) . '`';
                    $coords = sprintf('`(%.1f°, %.1f°)`', $occ['yaw'], $occ['pitch']);
                    $conf = $this->escapeMd($occ['confidence']);
                    $evidence = $this->escapeMd($occ['evidence_note'] ?: '-');
                    $verFlag = $occ['verified_by_admin'] ? '✓' : '—';

                    $lines[] = "| {$rowNo} | {$rowRoom} | {$photoRef} | {$rawText} | {$coords} | {$conf} | {$evidence} | {$verFlag} |";
                }
            }
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = "## 3. Tabel Lengkap Hasil Scan Foto 1 – {$stats['total']}";
        $lines[] = '';
        $lines[] = '| No | File Baru | Nama File Asli | Dimensi | Status | Tulisan Ruangan Terdeteksi | Ringkasan Lokasi / Catatan | Verifikasi |';
        $lines[] = '| ---: | :--- | :--- | :--- | :--- | :--- | :--- | :---: |';

        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            $fn = $this->escapeMd((string) ($item['filename'] ?? ''));
            $orig = $this->escapeMd((string) ($item['original_filename'] ?? ''));
            $dims = ((int) ($item['width'] ?? 0)) . '×' . ((int) ($item['height'] ?? 0));
            $status = $this->escapeMd((string) ($item['status'] ?? 'no_text'));

            $roomTexts = [];
            foreach ($item['detected_rooms'] ?? [] as $r) {
                $rName = $this->escapeMd((string) ($r['room_name'] ?? ''));
                $rRaw = $this->escapeMd((string) ($r['raw_text'] ?? ''));
                $rCoords = sprintf('(%.1f°, %.1f°)', (float) ($r['yaw'] ?? 0), (float) ($r['pitch'] ?? 0));
                $roomTexts[] = "**{$rName}** `{$rRaw}` {$rCoords}";
            }
            $roomsCell = !empty($roomTexts) ? implode('<br>', $roomTexts) : '—';

            $summary = $this->escapeMd((string) ($item['scene_summary'] ?? ''));
            if (!empty($item['admin_note'])) {
                $summary .= ($summary !== '' ? '<br>' : '') . '_Admin:_ ' . $this->escapeMd((string) $item['admin_note']);
            }
            if ($summary === '') {
                $summary = '—';
            }

            $ver = !empty($item['verified_by_admin']) ? '✓' : '—';
            $lines[] = "| {$id} | `{$fn}` | `{$orig}` | {$dims} | `{$status}` | {$roomsCell} | {$summary} | {$ver} |";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Write the generated Markdown report to config('foto360p.markdown_path').
     *
     * @param array<string, mixed> $data
     */
    public function write(array $data): string
    {
        $target = (string) config('foto360p.markdown_path');
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($target, $this->build($data));

        return $target;
    }

    /**
     * Build UTF-8 BOM CSV export with one row per detected room (or one empty-room row per photo).
     *
     * @param array<string, mixed> $data
     */
    public function csv(array $data): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");

        fputcsv($fh, [
            'id',
            'filename',
            'original_filename',
            'status',
            'room_name',
            'raw_text',
            'category',
            'confidence',
            'yaw',
            'pitch',
            'x_pct',
            'y_pct',
            'verified_by_admin',
            'admin_note',
        ]);

        foreach ($data['items'] ?? [] as $item) {
            $rooms = $item['detected_rooms'] ?? [];
            if (empty($rooms)) {
                fputcsv($fh, [
                    (int) ($item['id'] ?? 0),
                    (string) ($item['filename'] ?? ''),
                    (string) ($item['original_filename'] ?? ''),
                    (string) ($item['status'] ?? ''),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    !empty($item['verified_by_admin']) ? '1' : '0',
                    (string) ($item['admin_note'] ?? ''),
                ]);
                continue;
            }

            foreach ($rooms as $r) {
                fputcsv($fh, [
                    (int) ($item['id'] ?? 0),
                    (string) ($item['filename'] ?? ''),
                    (string) ($item['original_filename'] ?? ''),
                    (string) ($item['status'] ?? ''),
                    (string) ($r['room_name'] ?? ''),
                    (string) ($r['raw_text'] ?? ''),
                    (string) ($r['category'] ?? ''),
                    (string) ($r['confidence'] ?? ''),
                    (string) ($r['yaw'] ?? ''),
                    (string) ($r['pitch'] ?? ''),
                    (string) ($r['x_pct'] ?? ''),
                    (string) ($r['y_pct'] ?? ''),
                    !empty($item['verified_by_admin']) ? '1' : '0',
                    (string) ($item['admin_note'] ?? ''),
                ]);
            }
        }

        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    protected function escapeMd(string $value): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return str_replace('|', '\|', $clean);
    }
}
