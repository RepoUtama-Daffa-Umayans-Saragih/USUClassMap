<?php

namespace App\Services\Foto360p;

use Illuminate\Support\Carbon;
use RuntimeException;

class Photo360pScanRepository
{
    public function storageDir(): string
    {
        return rtrim((string) config('foto360p.storage_path', storage_path('app/foto-360p')), '/\\');
    }

    public function path(string $file): string
    {
        return $this->storageDir() . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
    }

    public function publicDirPath(): string
    {
        $publicDir = trim((string) config('foto360p.public_dir', 'images/foto-360p'), '/\\');

        return public_path(str_replace('/', DIRECTORY_SEPARATOR, $publicDir));
    }

    public function publicImageUrl(string $filename): string
    {
        $publicDir = trim((string) config('foto360p.public_dir', 'images/foto-360p'), '/');

        return '/' . $publicDir . '/' . ltrim($filename, '/');
    }

    /**
     * Load scan_results.json or synthesize a fallback payload from rename_mapping.json / public images.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $scanPath = $this->path('scan_results.json');
        if (is_file($scanPath)) {
            $raw = file_get_contents($scanPath);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])) {
                return $this->normalizePayload($decoded);
            }
        }

        return $this->normalizePayload($this->buildFallbackPayload());
    }

    /**
     * Atomically write scan_results.json guarded by an exclusive flock.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $dir = $this->storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $lockPath = $this->path('.lock');
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException("Unable to open lock file at {$lockPath}");
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException("Unable to acquire flock on {$lockPath}");
            }

            $normalized = $this->normalizePayload($data);
            $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Failed to encode scan_results.json');
            }

            $targetPath = $this->path('scan_results.json');
            $tmpPath = $this->path('scan_results.json.tmp.' . uniqid('', true));

            if (file_put_contents($tmpPath, $json) === false) {
                throw new RuntimeException("Failed to write temp scan file {$tmpPath}");
            }

            if (!@rename($tmpPath, $targetPath)) {
                if (@copy($tmpPath, $targetPath)) {
                    @unlink($tmpPath);
                } else {
                    @unlink($tmpPath);
                    throw new RuntimeException("Failed to move {$tmpPath} to {$targetPath}");
                }
            }
        } finally {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findItem(int $id): ?array
    {
        $payload = $this->load();
        foreach ($payload['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Update a single item atomically and return the updated item, or null if id not found.
     *
     * @param array<string, mixed> $patch
     * @return array<string, mixed>|null
     */
    public function updateItem(int $id, array $patch): ?array
    {
        $payload = $this->load();
        $foundIndex = null;

        foreach ($payload['items'] as $idx => $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return null;
        }

        $updated = array_merge($payload['items'][$foundIndex], $patch);
        $payload['items'][$foundIndex] = $this->normalizeItem($updated);
        $this->save($payload);

        return $payload['items'][$foundIndex];
    }

    /**
     * Compute Pannellum equirectangular projection bounds matching the Python unwarper.
     *
     * @return array{type: string, haov: float, vaov: float, is_panorama: bool}
     */
    public static function projectionFor(int $w, int $h): array
    {
        if ($w <= 0 || $h <= 0) {
            return [
                'type'        => 'equirectangular',
                'haov'        => 360.0,
                'vaov'        => 60.0,
                'is_panorama' => true,
            ];
        }

        $ratio = $w / $h;
        if ($ratio >= 2.2) {
            return [
                'type'        => 'equirectangular',
                'haov'        => 360.0,
                'vaov'        => round(360.0 * $h / $w, 2),
                'is_panorama' => true,
            ];
        }

        if ($ratio >= 1.8) {
            return [
                'type'        => 'equirectangular',
                'haov'        => 360.0,
                'vaov'        => 180.0,
                'is_panorama' => true,
            ];
        }

        return [
            'type'        => 'equirectangular',
            'haov'        => 90.0,
            'vaov'        => round(90.0 * $h / $w, 2),
            'is_panorama' => false,
        ];
    }

    /**
     * Compute normalized 0..1 image percentage coordinates (x_pct, y_pct) from (yaw, pitch).
     *
     * @param array{haov?: float, vaov?: float} $projection
     * @return array{x_pct: float, y_pct: float}
     */
    public static function coordsToPercentages(float $yaw, float $pitch, array $projection): array
    {
        $haov = max(1.0, (float) ($projection['haov'] ?? 360.0));
        $vaov = max(1.0, (float) ($projection['vaov'] ?? 60.0));

        if ($haov >= 359.9) {
            $wrappedYaw = fmod(($yaw + 180.0), 360.0);
            if ($wrappedYaw < 0.0) {
                $wrappedYaw += 360.0;
            }
            $xPct = $wrappedYaw / 360.0;
        } else {
            $xPct = ($yaw + ($haov / 2.0)) / $haov;
        }

        $yPct = (($vaov / 2.0) - $pitch) / $vaov;

        return [
            'x_pct' => round(max(0.0, min(1.0, $xPct)), 4),
            'y_pct' => round(max(0.0, min(1.0, $yPct)), 4),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildFallbackPayload(): array
    {
        $items = [];
        $mappingPath = $this->path('rename_mapping.json');

        if (is_file($mappingPath)) {
            $raw = file_get_contents($mappingPath);
            $mapping = is_string($raw) ? json_decode($raw, true) : null;
            $entries = is_array($mapping) ? ($mapping['items'] ?? $mapping) : [];
            if (is_array($entries)) {
                foreach ($entries as $row) {
                    if (!is_array($row) || empty($row['filename'])) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    $w = (int) ($row['width'] ?? 0);
                    $h = (int) ($row['height'] ?? 0);
                    $items[] = [
                        'id'                 => $id,
                        'filename'           => (string) $row['filename'],
                        'original_filename'  => (string) ($row['original_filename'] ?? $row['filename']),
                        'image_url'          => $this->publicImageUrl((string) $row['filename']),
                        'width'              => $w,
                        'height'             => $h,
                        'size_kb'            => round(((int) ($row['size_bytes'] ?? 0)) / 1024, 1),
                        'projection'         => self::projectionFor($w, $h),
                        'status'             => 'pending',
                        'detected_rooms'     => [],
                        'all_readable_texts' => [],
                        'scene_summary'      => 'Menunggu hasil deep scan AI.',
                        'verified_by_admin'  => false,
                        'admin_note'         => '',
                    ];
                }
            }
        }

        if (empty($items) && is_dir($this->publicDirPath())) {
            $files = glob($this->publicDirPath() . DIRECTORY_SEPARATOR . '*.jpg') ?: [];
            natsort($files);
            foreach ($files as $filePath) {
                $basename = basename($filePath);
                $id = (int) pathinfo($basename, PATHINFO_FILENAME);
                $info = @getimagesize($filePath);
                $w = is_array($info) ? (int) $info[0] : 0;
                $h = is_array($info) ? (int) $info[1] : 0;
                $sizeBytes = (int) (@filesize($filePath) ?: 0);

                $items[] = [
                    'id'                 => $id > 0 ? $id : (count($items) + 1),
                    'filename'           => $basename,
                    'original_filename'  => $basename,
                    'image_url'          => $this->publicImageUrl($basename),
                    'width'              => $w,
                    'height'             => $h,
                    'size_kb'            => round($sizeBytes / 1024, 1),
                    'projection'         => self::projectionFor($w, $h),
                    'status'             => 'pending',
                    'detected_rooms'     => [],
                    'all_readable_texts' => [],
                    'scene_summary'      => 'Menunggu hasil deep scan AI.',
                    'verified_by_admin'  => false,
                    'admin_note'         => '',
                ];
            }
        }

        return [
            'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'total_images' => count($items),
            'scan_engine'  => 'fallback_inventory',
            'items'        => $items,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        $items = array_map(
            fn (array $item): array => $this->normalizeItem($item),
            array_values(array_filter($payload['items'] ?? [], 'is_array'))
        );

        usort($items, fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));

        return [
            'generated_at' => (string) ($payload['generated_at'] ?? Carbon::now('Asia/Jakarta')->toIso8601String()),
            'total_images' => count($items),
            'scan_engine'  => (string) ($payload['scan_engine'] ?? 'unknown'),
            'items'        => $items,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function normalizeItem(array $item): array
    {
        $filename = (string) ($item['filename'] ?? (($item['id'] ?? '0') . '.jpg'));
        $w = (int) ($item['width'] ?? 0);
        $h = (int) ($item['height'] ?? 0);

        $projection = isset($item['projection']) && is_array($item['projection']) && isset($item['projection']['haov'], $item['projection']['vaov'])
            ? [
                'type'        => (string) ($item['projection']['type'] ?? 'equirectangular'),
                'haov'        => (float) $item['projection']['haov'],
                'vaov'        => (float) $item['projection']['vaov'],
                'is_panorama' => (bool) ($item['projection']['is_panorama'] ?? true),
            ]
            : self::projectionFor($w, $h);

        $rooms = [];
        foreach ($item['detected_rooms'] ?? [] as $room) {
            if (!is_array($room) || trim((string) ($room['room_name'] ?? '')) === '') {
                continue;
            }
            $yaw = (float) ($room['yaw'] ?? 0.0);
            $pitch = (float) ($room['pitch'] ?? 0.0);
            $pct = (isset($room['x_pct'], $room['y_pct']))
                ? ['x_pct' => (float) $room['x_pct'], 'y_pct' => (float) $room['y_pct']]
                : self::coordsToPercentages($yaw, $pitch, $projection);

            $rooms[] = [
                'room_name'     => trim((string) $room['room_name']),
                'raw_text'      => (string) ($room['raw_text'] ?? $room['room_name']),
                'verified_read' => (string) ($room['verified_read'] ?? ($room['raw_text'] ?? $room['room_name'])),
                'category'      => (string) ($room['category'] ?? 'lainnya'),
                'confidence'    => (string) ($room['confidence'] ?? 'medium'),
                'yaw'           => round($yaw, 1),
                'pitch'         => round($pitch, 1),
                'x_pct'         => $pct['x_pct'],
                'y_pct'         => $pct['y_pct'],
                'evidence_note' => (string) ($room['evidence_note'] ?? ''),
                'source'        => (string) ($room['source'] ?? 'ai'),
            ];
        }

        $item['id'] = (int) ($item['id'] ?? 0);
        $item['filename'] = $filename;
        $item['original_filename'] = (string) ($item['original_filename'] ?? $filename);
        $item['image_url'] = $this->publicImageUrl($filename);
        $item['width'] = $w;
        $item['height'] = $h;
        $item['size_kb'] = (float) ($item['size_kb'] ?? 0.0);
        $item['projection'] = $projection;
        $item['status'] = (string) ($item['status'] ?? (!empty($rooms) ? 'detected' : 'no_text'));
        $item['detected_rooms'] = $rooms;
        $item['all_readable_texts'] = array_values(array_map('strval', $item['all_readable_texts'] ?? []));
        $item['scene_summary'] = (string) ($item['scene_summary'] ?? '');
        $item['verified_by_admin'] = (bool) ($item['verified_by_admin'] ?? false);
        $item['admin_note'] = (string) ($item['admin_note'] ?? '');

        return $item;
    }
}
