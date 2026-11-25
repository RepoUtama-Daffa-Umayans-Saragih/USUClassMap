<?php

namespace App\Services\VirtualWalk;

use Illuminate\Support\Facades\Storage;

/**
 * FibVirtualWalkGraphService
 *
 * Mengelola graf navigasi Street View 360° Fakultas Ilmu Budaya (FIB) USU
 * berbasis foto #428-#495 (67 scene aktif, #434 dikecualikan) dengan persistensi
 * sinkron ke runtime storage dan Git snapshot.
 */
class FibVirtualWalkGraphService
{
    public const START_SCENE_ID = 495;
    public const EXCLUDED_SCENE_IDS = [434];
    public const GRAPH_VERSION = 2;
    public const PANO_MAX_WIDTH = 4096;
    public const PANO_MAX_HEIGHT = 2048;

    public function getRuntimePath(): string
    {
        return storage_path('app/virtual-walk/fib_walk_graph.json');
    }

    public function getSnapshotPath(): string
    {
        return database_path('seeders/snapshots/fib_walk_graph.json');
    }

    /**
     * Ambil graf lengkap tur virtual FIB 360° yang sudah diperkaya URL file & statistik konektivitas.
     *
     * @param bool $includeExcluded
     * @return array<string, mixed>
     */
    public function getGraph(bool $includeExcluded = false): array
    {
        $raw = $this->loadRawGraph();
        $disk = Storage::disk('public');

        $rawScenes = $raw['scenes'] ?? [];
        $scenes = [];
        $zones = [];

        foreach ($rawScenes as $key => $scene) {
            $id = (int) ($scene['id'] ?? $key);
            $isExcluded = !empty($scene['excluded']) || in_array($id, self::EXCLUDED_SCENE_IDS, true);

            if (!$includeExcluded && $isExcluded) {
                continue;
            }

            $filename = (string) ($scene['filename'] ?? "{$id}.jpg");
            $storagePath = 'foto360/' . ltrim($filename, '/');
            $fileExists = $disk->exists($storagePath);

            // Normalize hotspots to only point to active targets unless includeExcluded
            $hotspots = [];
            foreach ($scene['hotspots'] ?? [] as $hs) {
                $targetId = (int) ($hs['target_id'] ?? 0);
                if ($targetId <= 0 || in_array($targetId, self::EXCLUDED_SCENE_IDS, true)) {
                    continue;
                }
                $hsKind = (string) ($hs['kind'] ?? 'walk');
                $hotspots[] = [
                    'target_id'    => $targetId,
                    'label'        => (string) ($hs['label'] ?? "Titik #{$targetId}"),
                    'kind'         => $hsKind,
                    'yaw'          => round((float) ($hs['yaw'] ?? 0.0), 1),
                    'pitch'        => round((float) ($hs['pitch'] ?? $this->defaultPitchForKind($hsKind)), 1),
                    'target_yaw'   => round((float) ($hs['target_yaw'] ?? 0.0), 1),
                    'target_pitch' => round((float) ($hs['target_pitch'] ?? 0.0), 1),
                ];
            }

            $zoneName = (string) ($scene['zone'] ?? 'FIB');
            if (!$isExcluded && !in_array($zoneName, $zones, true)) {
                $zones[] = $zoneName;
            }

            $scenes[(string) $id] = [
                'id'            => $id,
                'label'         => (string) ($scene['label'] ?? "Titik #{$id}"),
                'zone'          => $zoneName,
                'floor'         => (int) ($scene['floor'] ?? 1),
                'type'          => (string) ($scene['type'] ?? 'corridor'),
                'excluded'      => $isExcluded,
                'filename'      => $filename,
                'storage_path'  => $storagePath,
                'file_exists'   => $fileExists,
                'image_url'     => asset('storage/' . $storagePath),
                'pano_url'      => '/virtual-walk/fib/pano/' . $id,
                'default_pitch' => round((float) ($scene['default_pitch'] ?? 0.0), 1),
                'default_yaw'   => round((float) ($scene['default_yaw'] ?? 0.0), 1),
                'hotspots'      => $hotspots,
            ];
        }

        ksort($scenes, SORT_NUMERIC);

        $connectivity = $this->verifyConnectivity(['scenes' => $scenes]);

        return [
            'version'        => (int) ($raw['version'] ?? self::GRAPH_VERSION),
            'updated_at'     => (string) ($raw['updated_at'] ?? now()->toIso8601String()),
            'start_scene_id' => self::START_SCENE_ID,
            'excluded_ids'   => self::EXCLUDED_SCENE_IDS,
            'zones'          => $zones,
            'scenes'         => $scenes,
            'stats'          => [
                'total_nodes'     => 68,
                'active_scenes'   => count(array_filter($scenes, fn (array $s): bool => empty($s['excluded']))),
                'excluded_count'  => count(self::EXCLUDED_SCENE_IDS),
                'total_hotspots'  => array_sum(array_map(fn (array $s): int => count($s['hotspots']), $scenes)),
                'connectivity'    => $connectivity,
            ],
        ];
    }

    /**
     * Perbarui metadata dan daftar hotspot milik satu scene secara atomik.
     *
     * @param int $sceneId
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateScene(int $sceneId, array $payload): array
    {
        $raw = $this->loadRawGraph();
        $key = (string) $sceneId;

        if (!isset($raw['scenes'][$key]) || !empty($raw['scenes'][$key]['excluded'])) {
            throw new \InvalidArgumentException("Scene #{$sceneId} tidak ditemukan atau dikecualikan.");
        }

        $scene = $raw['scenes'][$key];

        if (isset($payload['label']) && is_string($payload['label']) && trim($payload['label']) !== '') {
            $scene['label'] = trim($payload['label']);
        }
        if (isset($payload['zone']) && is_string($payload['zone']) && trim($payload['zone']) !== '') {
            $scene['zone'] = trim($payload['zone']);
        }
        if (isset($payload['floor']) && is_numeric($payload['floor'])) {
            $scene['floor'] = max(1, min(5, (int) $payload['floor']));
        }
        if (isset($payload['type']) && in_array($payload['type'], ['room', 'corridor', 'stairs', 'junction', 'outdoor'], true)) {
            $scene['type'] = $payload['type'];
        }
        if (isset($payload['default_pitch']) && is_numeric($payload['default_pitch'])) {
            $scene['default_pitch'] = round(max(-85.0, min(85.0, (float) $payload['default_pitch'])), 1);
        }
        if (isset($payload['default_yaw']) && is_numeric($payload['default_yaw'])) {
            $scene['default_yaw'] = round($this->wrapYaw((float) $payload['default_yaw']), 1);
        }

        if (isset($payload['hotspots']) && is_array($payload['hotspots'])) {
            $sanitizedHotspots = [];
            $seenTargets = [];
            foreach ($payload['hotspots'] as $hs) {
                $targetId = (int) ($hs['target_id'] ?? 0);
                if ($targetId <= 0 || $targetId === $sceneId || in_array($targetId, self::EXCLUDED_SCENE_IDS, true)) {
                    continue;
                }
                if (!isset($raw['scenes'][(string) $targetId]) || isset($seenTargets[$targetId])) {
                    continue;
                }
                $seenTargets[$targetId] = true;

                $targetScene = $raw['scenes'][(string) $targetId];
                $kind = in_array($hs['kind'] ?? '', ['walk', 'room', 'stairs_up', 'stairs_down'], true)
                    ? (string) $hs['kind']
                    : $this->inferHotspotKind($scene, $targetScene);

                $sanitizedHotspots[] = [
                    'target_id'    => $targetId,
                    'label'        => trim((string) ($hs['label'] ?? $this->defaultHotspotLabel($targetScene, $kind))),
                    'kind'         => $kind,
                    'yaw'          => round($this->wrapYaw((float) ($hs['yaw'] ?? 0.0)), 1),
                    'pitch'        => round(max(-85.0, min(85.0, (float) ($hs['pitch'] ?? $this->defaultPitchForKind($kind)))), 1),
                    'target_yaw'   => round($this->wrapYaw((float) ($hs['target_yaw'] ?? ($targetScene['default_yaw'] ?? 0.0))), 1),
                    'target_pitch' => round(max(-85.0, min(85.0, (float) ($hs['target_pitch'] ?? ($targetScene['default_pitch'] ?? 0.0)))), 1),
                ];
            }
            $scene['hotspots'] = $sanitizedHotspots;
        }

        $raw['scenes'][$key] = $scene;
        $raw['updated_at'] = now()->toIso8601String();
        $this->saveRawGraph($raw);

        $enriched = $this->getGraph(false);

        return [
            'scene' => $enriched['scenes'][$key] ?? null,
            'stats' => $enriched['stats'],
        ];
    }

    /**
     * Tambahkan koneksi jalan dua arah antara dua scene.
     *
     * @param int $fromId
     * @param int $toId
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function addBidirectionalLink(int $fromId, int $toId, array $options = []): array
    {
        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Scene asal dan tujuan tidak boleh sama.');
        }

        $raw = $this->loadRawGraph();
        $fromKey = (string) $fromId;
        $toKey = (string) $toId;

        if (!isset($raw['scenes'][$fromKey]) || !empty($raw['scenes'][$fromKey]['excluded'])) {
            throw new \InvalidArgumentException("Scene #{$fromId} tidak valid.");
        }
        if (!isset($raw['scenes'][$toKey]) || !empty($raw['scenes'][$toKey]['excluded'])) {
            throw new \InvalidArgumentException("Scene #{$toId} tidak valid.");
        }

        $fromScene = $raw['scenes'][$fromKey];
        $toScene = $raw['scenes'][$toKey];

        $forwardKind = isset($options['kind']) && in_array($options['kind'], ['walk', 'room', 'stairs_up', 'stairs_down'], true)
            ? (string) $options['kind']
            : $this->inferHotspotKind($fromScene, $toScene);

        $forwardYaw = isset($options['yaw']) && is_numeric($options['yaw'])
            ? $this->wrapYaw((float) $options['yaw'])
            : $this->wrapYaw((float) ($fromScene['default_yaw'] ?? 0.0));

        $forwardPitch = isset($options['pitch']) && is_numeric($options['pitch'])
            ? max(-85.0, min(85.0, (float) $options['pitch']))
            : $this->defaultPitchForKind($forwardKind);

        $forwardLabel = isset($options['label']) && trim((string) $options['label']) !== ''
            ? trim((string) $options['label'])
            : $this->defaultHotspotLabel($toScene, $forwardKind);

        $forwardHs = [
            'target_id'    => $toId,
            'label'        => $forwardLabel,
            'kind'         => $forwardKind,
            'yaw'          => round($forwardYaw, 1),
            'pitch'        => round($forwardPitch, 1),
            'target_yaw'   => round((float) ($toScene['default_yaw'] ?? 0.0), 1),
            'target_pitch' => round((float) ($toScene['default_pitch'] ?? 0.0), 1),
        ];

        $reverseKind = $this->inferHotspotKind($toScene, $fromScene);
        $reverseYaw = $this->wrapYaw((float) ($toScene['default_yaw'] ?? 0.0) + 180.0);
        $reverseHs = [
            'target_id'    => $fromId,
            'label'        => $this->defaultHotspotLabel($fromScene, $reverseKind),
            'kind'         => $reverseKind,
            'yaw'          => round($reverseYaw, 1),
            'pitch'        => round($this->defaultPitchForKind($reverseKind), 1),
            'target_yaw'   => round((float) ($fromScene['default_yaw'] ?? 0.0), 1),
            'target_pitch' => round((float) ($fromScene['default_pitch'] ?? 0.0), 1),
        ];

        $fromScene['hotspots'] = $this->upsertHotspot($fromScene['hotspots'] ?? [], $forwardHs);
        $toScene['hotspots'] = $this->upsertHotspot($toScene['hotspots'] ?? [], $reverseHs);

        $raw['scenes'][$fromKey] = $fromScene;
        $raw['scenes'][$toKey] = $toScene;
        $raw['updated_at'] = now()->toIso8601String();
        $this->saveRawGraph($raw);

        $enriched = $this->getGraph(false);

        return [
            'from_scene' => $enriched['scenes'][$fromKey] ?? null,
            'to_scene'   => $enriched['scenes'][$toKey] ?? null,
            'stats'      => $enriched['stats'],
        ];
    }

    /**
     * Hapus koneksi jalan dua arah antara dua scene.
     *
     * @return array<string, mixed>
     */
    public function removeBidirectionalLink(int $fromId, int $toId): array
    {
        $raw = $this->loadRawGraph();
        $fromKey = (string) $fromId;
        $toKey = (string) $toId;

        if (isset($raw['scenes'][$fromKey])) {
            $raw['scenes'][$fromKey]['hotspots'] = array_values(array_filter(
                $raw['scenes'][$fromKey]['hotspots'] ?? [],
                fn (array $hs): bool => (int) ($hs['target_id'] ?? 0) !== $toId
            ));
        }

        if (isset($raw['scenes'][$toKey])) {
            $raw['scenes'][$toKey]['hotspots'] = array_values(array_filter(
                $raw['scenes'][$toKey]['hotspots'] ?? [],
                fn (array $hs): bool => (int) ($hs['target_id'] ?? 0) !== $fromId
            ));
        }

        $raw['updated_at'] = now()->toIso8601String();
        $this->saveRawGraph($raw);

        $enriched = $this->getGraph(false);

        return [
            'from_scene' => $enriched['scenes'][$fromKey] ?? null,
            'stats'      => $enriched['stats'],
        ];
    }

    /**
     * Verifikasi konektivitas graf dengan BFS dari scene awal (#495).
     *
     * @param array<string, mixed>|null $graph
     * @return array{start_scene_id: int, active_count: int, reachable_count: int, unreachable_ids: array<int, int>, is_fully_connected: bool}
     */
    public function verifyConnectivity(?array $graph = null): array
    {
        $scenes = $graph['scenes'] ?? $this->loadRawGraph()['scenes'] ?? [];
        $activeIds = [];
        $adj = [];

        foreach ($scenes as $k => $sc) {
            $id = (int) ($sc['id'] ?? $k);
            if (!empty($sc['excluded']) || in_array($id, self::EXCLUDED_SCENE_IDS, true)) {
                continue;
            }
            $activeIds[] = $id;
            $adj[$id] = [];
            foreach ($sc['hotspots'] ?? [] as $hs) {
                $tId = (int) ($hs['target_id'] ?? 0);
                if ($tId > 0 && !in_array($tId, self::EXCLUDED_SCENE_IDS, true)) {
                    $adj[$id][] = $tId;
                }
            }
        }

        $startId = in_array(self::START_SCENE_ID, $activeIds, true)
            ? self::START_SCENE_ID
            : ($activeIds[0] ?? 0);

        $visited = [];
        if ($startId > 0) {
            $queue = [$startId];
            $visited[$startId] = true;
            while (!empty($queue)) {
                $curr = array_shift($queue);
                foreach ($adj[$curr] ?? [] as $next) {
                    if (isset($adj[$next]) && !isset($visited[$next])) {
                        $visited[$next] = true;
                        $queue[] = $next;
                    }
                }
            }
        }

        $unreachable = array_values(array_filter(
            $activeIds,
            fn (int $id): bool => !isset($visited[$id])
        ));

        return [
            'start_scene_id'     => $startId,
            'active_count'       => count($activeIds),
            'reachable_count'    => count($visited),
            'unreachable_ids'    => $unreachable,
            'is_fully_connected' => empty($unreachable) && count($activeIds) > 0,
        ];
    }

    /**
     * Hitung jalur terpendek (BFS) antar dua scene.
     *
     * @return array<int, int>
     */
    public function shortestPath(int $fromId, int $toId): array
    {
        if ($fromId === $toId) {
            return [$fromId];
        }

        $graph = $this->getGraph(false);
        $scenes = $graph['scenes'] ?? [];
        if (!isset($scenes[(string) $fromId]) || !isset($scenes[(string) $toId])) {
            return [];
        }

        $queue = [[$fromId]];
        $visited = [$fromId => true];

        while (!empty($queue)) {
            $path = array_shift($queue);
            $curr = end($path);

            foreach ($scenes[(string) $curr]['hotspots'] ?? [] as $hs) {
                $next = (int) ($hs['target_id'] ?? 0);
                if ($next <= 0 || isset($visited[$next]) || !isset($scenes[(string) $next])) {
                    continue;
                }
                $newPath = array_merge($path, [$next]);
                if ($next === $toId) {
                    return $newPath;
                }
                $visited[$next] = true;
                $queue[] = $newPath;
            }
        }

        return [];
    }

    /**
     * Ambil atau bangkitkan berkas panorama terkompresi (4096x2048) agar WebGL
     * dan preloading berjalan cepat di seluruh perangkat tanpa menyentuh foto asli.
     */
    public function getCachedPanoramaPath(int $sceneId): ?string
    {
        if ($sceneId < 428 || $sceneId > 495 || in_array($sceneId, self::EXCLUDED_SCENE_IDS, true)) {
            return null;
        }

        $srcPath = storage_path("app/public/foto360/{$sceneId}.jpg");
        if (!is_file($srcPath)) {
            return null;
        }

        $cacheDir = storage_path('app/virtual-walk/pano-cache');
        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . "{$sceneId}.jpg";

        if (is_file($cachePath) && filemtime($cachePath) >= filemtime($srcPath) && filesize($cachePath) > 1024) {
            return $cachePath;
        }

        if (function_exists('imagecreatefromjpeg') && function_exists('imagecreatetruecolor')) {
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }

            @ini_set('memory_limit', '512M');
            $src = @imagecreatefromjpeg($srcPath);
            if ($src) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);

                if ($srcW <= self::PANO_MAX_WIDTH) {
                    imagedestroy($src);
                    return $srcPath;
                }

                $targetW = self::PANO_MAX_WIDTH;
                $targetH = (int) round(($srcH * $targetW) / max(1, $srcW));
                $dst = imagecreatetruecolor($targetW, $targetH);
                if ($dst) {
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
                    $tmpPath = $cachePath . '.tmp.' . getmypid();
                    $ok = @imagejpeg($dst, $tmpPath, 80);
                    imagedestroy($dst);
                    imagedestroy($src);

                    if ($ok && is_file($tmpPath) && filesize($tmpPath) > 1024) {
                        @rename($tmpPath, $cachePath);
                        if (is_file($cachePath)) {
                            return $cachePath;
                        }
                    }
                    @unlink($tmpPath);
                } else {
                    imagedestroy($src);
                }
            }
        }

        return $srcPath;
    }

    /**
     * Muat raw JSON graf dari runtime, atau pulihkan dari snapshot Git, atau generate default.
     *
     * @return array<string, mixed>
     */
    protected function loadRawGraph(): array
    {
        $runtime = $this->getRuntimePath();
        if (is_file($runtime)) {
            $decoded = json_decode((string) file_get_contents($runtime), true);
            if (
                is_array($decoded)
                && !empty($decoded['scenes'])
                && (int) ($decoded['version'] ?? 1) >= self::GRAPH_VERSION
            ) {
                return $decoded;
            }
        }

        $snapshot = $this->getSnapshotPath();
        if (is_file($snapshot)) {
            $decoded = json_decode((string) file_get_contents($snapshot), true);
            if (
                is_array($decoded)
                && !empty($decoded['scenes'])
                && (int) ($decoded['version'] ?? 1) >= self::GRAPH_VERSION
            ) {
                $this->writeJsonAtomically($runtime, $decoded);
                return $decoded;
            }
        }

        $default = $this->buildDefaultGraph();
        $this->saveRawGraph($default);

        return $default;
    }

    /**
     * Simpan raw JSON ke kedua lokasi (runtime storage & Git snapshot).
     *
     * @param array<string, mixed> $raw
     */
    public function saveRawGraph(array $raw): void
    {
        $this->writeJsonAtomically($this->getRuntimePath(), $raw);
        $this->writeJsonAtomically($this->getSnapshotPath(), $raw);
    }

    /**
     * Bangun definisi lengkap 68 node (428-495) dan graf jalan dua arah resmi FIB USU.
     *
     * @return array<string, mixed>
     */
    public function buildDefaultGraph(): array
    {
        $calibrations = $this->loadPhotoCalibrations();

        $nodes = [
            428 => ['label' => 'Ruang Kelas Pintar Internasional / O.102 & O.104', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'room'],
            429 => ['label' => 'Ruang Kelas Pintar Internasional / O.101 & O.103', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'room'],
            430 => ['label' => 'Gedung O (Titik 1)', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'corridor'],
            431 => ['label' => 'Gedung O (Titik 2)', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'corridor'],
            432 => ['label' => 'Lorong Depan Gedung O (Titik 1)', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'corridor'],
            433 => ['label' => 'Lorong Depan Gedung O (Titik 2)', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'corridor'],
            434 => ['label' => 'Random (Dikecualikan)', 'zone' => 'Dikecualikan', 'floor' => 1, 'type' => 'excluded', 'excluded' => true],
            435 => ['label' => 'Percabangan Gedung O dan Gedung A', 'zone' => 'Persimpangan O & A', 'floor' => 1, 'type' => 'junction'],
            436 => ['label' => 'Gedung A', 'zone' => 'Gedung A', 'floor' => 1, 'type' => 'corridor'],
            437 => ['label' => 'Ruang Kelas A-102', 'zone' => 'Gedung A', 'floor' => 1, 'type' => 'room'],
            438 => ['label' => 'Ruang Kelas A-101', 'zone' => 'Gedung A', 'floor' => 1, 'type' => 'room'],
            439 => ['label' => 'Ruang Kelas B-101', 'zone' => 'Gedung B', 'floor' => 1, 'type' => 'room'],
            440 => ['label' => 'Ruang Kelas B-102', 'zone' => 'Gedung B', 'floor' => 1, 'type' => 'room'],
            441 => ['label' => 'Ruang Kelas B-103', 'zone' => 'Gedung B', 'floor' => 1, 'type' => 'room'],

            442 => ['label' => 'Tangga Lantai 1 Gedung F', 'zone' => 'Gedung F', 'floor' => 1, 'type' => 'stairs'],
            443 => ['label' => 'Gedung F Dalam Tengah antara Lt. 1 & 2', 'zone' => 'Gedung F', 'floor' => 2, 'type' => 'stairs'],
            444 => ['label' => 'Ruang Kelas F-202', 'zone' => 'Gedung F', 'floor' => 3, 'type' => 'room'],
            445 => ['label' => 'Depan Tangga Lantai 3 Gedung F', 'zone' => 'Gedung F', 'floor' => 3, 'type' => 'stairs'],
            446 => ['label' => 'Ruang Kelas F-201', 'zone' => 'Gedung F', 'floor' => 3, 'type' => 'room'],
            447 => ['label' => 'Ruang Kelas F-203', 'zone' => 'Gedung F', 'floor' => 3, 'type' => 'room'],
            448 => ['label' => 'Lantai 3 di Tengah F-201–203', 'zone' => 'Gedung F', 'floor' => 3, 'type' => 'corridor'],

            449 => ['label' => 'Percabangan Gedung O & A', 'zone' => 'Persimpangan O & A', 'floor' => 1, 'type' => 'junction'],
            450 => ['label' => 'Gedung O (Arah Jalan ke H)', 'zone' => 'Gedung O', 'floor' => 1, 'type' => 'outdoor'],
            451 => ['label' => 'Jalan ke Gedung H (Titik 1)', 'zone' => 'Jalan ke Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            452 => ['label' => 'Jalan ke Gedung H (Titik 2)', 'zone' => 'Jalan ke Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            453 => ['label' => 'Jalan ke Gedung H (Titik 3)', 'zone' => 'Jalan ke Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            454 => ['label' => 'Jalan ke Gedung H (Titik 4)', 'zone' => 'Jalan ke Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            455 => ['label' => 'Jalan ke Gedung H (Titik 5)', 'zone' => 'Jalan ke Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            456 => ['label' => 'Depan Gedung H (Titik 1)', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            457 => ['label' => 'Depan Gedung H (Titik 2)', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'outdoor'],
            458 => ['label' => 'Ruang Kelas H-102 & H-103', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'room'],
            459 => ['label' => 'Teras Menuju Belakang Gedung H (Titik 1)', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'corridor'],
            460 => ['label' => 'Teras Menuju Belakang Gedung H (Titik 2)', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'corridor'],
            461 => ['label' => 'Menuju Ruang Kelas H-104 & H-105', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'corridor'],
            462 => ['label' => 'Ruang Kelas H-104 & H-105', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'room'],
            463 => ['label' => 'Lantai 1 Gedung H', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'junction'],
            464 => ['label' => 'Tangga Gedung H', 'zone' => 'Gedung H', 'floor' => 1, 'type' => 'stairs'],
            465 => ['label' => 'Ruang Kelas H-208', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],
            466 => ['label' => 'Ruang Kelas H-207', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],
            467 => ['label' => 'Ruang Kelas H-206', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],
            468 => ['label' => 'Ruang Kelas H-202', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],
            469 => ['label' => 'Ruang Kelas H-204', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],
            470 => ['label' => 'Ruang Kelas H-203', 'zone' => 'Gedung H', 'floor' => 2, 'type' => 'room'],

            471 => ['label' => 'Lorong Sebelah Gedung Teater (Titik 1)', 'zone' => 'Lorong Teater', 'floor' => 1, 'type' => 'corridor'],
            472 => ['label' => 'Lorong Sebelah Gedung Teater (Titik 2)', 'zone' => 'Lorong Teater', 'floor' => 1, 'type' => 'corridor'],
            473 => ['label' => 'Lorong Sebelah Gedung Teater (Titik 3)', 'zone' => 'Lorong Teater', 'floor' => 1, 'type' => 'corridor'],
            474 => ['label' => 'Gedung K', 'zone' => 'Gedung K', 'floor' => 1, 'type' => 'corridor'],
            475 => ['label' => 'Tangga Lantai 1 Gedung K', 'zone' => 'Gedung K', 'floor' => 1, 'type' => 'stairs'],
            476 => ['label' => 'Tangga Lantai 2 Gedung K (Titik 1)', 'zone' => 'Gedung K', 'floor' => 2, 'type' => 'stairs'],
            477 => ['label' => 'Tangga Lantai 2 Gedung K (Titik 2)', 'zone' => 'Gedung K', 'floor' => 2, 'type' => 'stairs'],
            478 => ['label' => 'Tangga Lantai 3 Gedung K (Titik 1)', 'zone' => 'Gedung K', 'floor' => 3, 'type' => 'stairs'],
            479 => ['label' => 'Tangga Lantai 3 Gedung K (Titik 2)', 'zone' => 'Gedung K', 'floor' => 3, 'type' => 'stairs'],
            480 => ['label' => 'Ruang Kelas K-302', 'zone' => 'Gedung K', 'floor' => 3, 'type' => 'room'],
            481 => ['label' => 'Ruang Kelas K-301', 'zone' => 'Gedung K', 'floor' => 3, 'type' => 'room'],

            482 => ['label' => 'Pendopo Depan Gedung F (Titik 1)', 'zone' => 'Pendopo & Gedung F', 'floor' => 1, 'type' => 'junction'],
            483 => ['label' => 'Pendopo Depan Gedung F (Titik 2)', 'zone' => 'Pendopo & Gedung F', 'floor' => 1, 'type' => 'junction'],
            484 => ['label' => 'Lorong Gedung I (Titik 1)', 'zone' => 'Gedung I', 'floor' => 1, 'type' => 'corridor'],
            485 => ['label' => 'Lorong Gedung I (Titik 2)', 'zone' => 'Gedung I', 'floor' => 1, 'type' => 'corridor'],
            486 => ['label' => 'Ruang Kelas I-104', 'zone' => 'Gedung I', 'floor' => 1, 'type' => 'room'],
            487 => ['label' => 'Ruang Kelas I-103', 'zone' => 'Gedung I', 'floor' => 1, 'type' => 'room'],
            488 => ['label' => 'Lorong Depan Gedung M', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'corridor'],
            489 => ['label' => 'Gedung M (Titik 1)', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'corridor'],
            490 => ['label' => 'Gedung M (Titik 2)', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'corridor'],
            491 => ['label' => 'Gedung M (Titik 3)', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'corridor'],
            492 => ['label' => 'Ruang Kelas M-101 & M-102', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'room'],
            493 => ['label' => 'Ruang Kelas M-104', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'room'],
            494 => ['label' => 'Ruang Kelas M-108', 'zone' => 'Gedung M', 'floor' => 1, 'type' => 'room'],
            495 => ['label' => 'Jalan Terusan Pintu Masuk FIB', 'zone' => 'Pintu Masuk FIB', 'floor' => 1, 'type' => 'junction'],
        ];

        $edges = [
            // 1. Gedung O <-> A <-> B
            [428, 430], [429, 430],
            [430, 431], [431, 432], [432, 433], [433, 435],
            [435, 436], [436, 437], [436, 438],
            [436, 439], [439, 440], [440, 441],
            [435, 449], [449, 450], [450, 451], [451, 452], [452, 453], [453, 454], [454, 455], [455, 456], [456, 457],

            // 2. Gedung H (Lt. 1 & Lt. 2)
            [457, 463], [463, 458],
            [463, 459], [459, 460], [460, 461], [461, 462],
            [463, 464], [464, 468], [468, 470], [470, 469], [469, 467], [467, 466], [466, 465],

            // 3. Gedung F (3 Lantai)
            [482, 483], [483, 442], [442, 443], [443, 445], [445, 448],
            [448, 446], [448, 444], [448, 447],

            // 4. Lorong Teater & Gedung K (3 Lantai)
            [471, 472], [472, 473], [473, 474], [474, 475], [475, 476], [476, 477], [477, 478], [478, 479],
            [479, 481], [479, 480],

            // 5. Gedung I, Gedung M & Pintu Masuk
            [483, 484], [484, 485], [485, 487], [487, 486],
            [485, 488], [488, 489], [489, 490], [490, 491],
            [491, 492], [492, 493], [493, 494],
            [491, 495],

            // 6. Penghubung Antar-Zona
            [495, 435], [495, 482],
            [482, 471], [457, 471],
        ];

        // Build adjacency + edge orientation lists
        $adj = [];
        $forwardEdges = [];
        $backwardEdges = [];
        foreach ($edges as [$u, $v]) {
            $adj[$u][] = $v;
            $adj[$v][] = $u;
            $forwardEdges[$u][] = $v;
            $backwardEdges[$v][] = $u;
        }

        $roomOffsets = [-48.0, 48.0, -105.0, 105.0, -28.0, 28.0];
        $otherWalkOffsets = [-75.0, 75.0, -132.0, 132.0, -40.0, 40.0];

        $scenes = [];
        foreach ($nodes as $id => $meta) {
            $filename = "{$id}.jpg";
            $cal = $calibrations[$filename] ?? ['pitch' => 0.0, 'yaw' => 0.0];

            $defaultPitch = round((float) $cal['pitch'], 1);
            $defaultYaw = round($this->wrapYaw((float) $cal['yaw']), 1);
            $isExcluded = !empty($meta['excluded']);

            $hotspots = [];
            if (!$isExcluded) {
                $neighbors = array_values(array_unique($adj[$id] ?? []));
                $placed = [];
                $usedYaws = [];

                $placeHotspot = function (int $targetId, float $rawYaw) use (
                    $id,
                    $meta,
                    $nodes,
                    $calibrations,
                    &$placed,
                    &$usedYaws,
                    &$hotspots
                ): void {
                    if (isset($placed[$targetId])) {
                        return;
                    }
                    $placed[$targetId] = true;

                    $targetMeta = $nodes[$targetId];
                    $targetCal = $calibrations["{$targetId}.jpg"] ?? ['pitch' => 0.0, 'yaw' => 0.0];
                    $kind = $this->inferHotspotKind($meta, $targetMeta);

                    $yaw = $this->wrapYaw($rawYaw);
                    for ($step = 0; $step < 15; $step++) {
                        $collision = false;
                        foreach ($usedYaws as $existingYaw) {
                            if ($this->angularDiff($yaw, $existingYaw) < 24.0) {
                                $collision = true;
                                break;
                            }
                        }
                        if (!$collision) {
                            break;
                        }
                        $yaw = $this->wrapYaw($yaw + 26.0);
                    }
                    $usedYaws[] = $yaw;

                    $hotspots[] = [
                        'target_id'    => $targetId,
                        'label'        => $this->defaultHotspotLabel($targetMeta, $kind),
                        'kind'         => $kind,
                        'yaw'          => round($yaw, 1),
                        'pitch'        => round($this->defaultPitchForKind($kind), 1),
                        'target_yaw'   => round($this->wrapYaw((float) $targetCal['yaw']), 1),
                        'target_pitch' => round((float) $targetCal['pitch'], 1),
                    ];
                };

                // 1. Primary forward non-room corridor step (0°)
                $forwardWalk = null;
                foreach ($forwardEdges[$id] ?? [] as $candId) {
                    if (!in_array($candId, $neighbors, true) || !empty($nodes[$candId]['excluded'])) {
                        continue;
                    }
                    if ($this->inferHotspotKind($meta, $nodes[$candId]) !== 'room') {
                        $forwardWalk = $candId;
                        break;
                    }
                }
                if ($forwardWalk !== null) {
                    $placeHotspot($forwardWalk, $defaultYaw);
                }

                // 2. Primary backward non-room corridor step (180°, or 0° if terminal corridor with no forward walk)
                $backWalk = null;
                foreach ($backwardEdges[$id] ?? [] as $candId) {
                    if ($candId === $forwardWalk || !in_array($candId, $neighbors, true) || !empty($nodes[$candId]['excluded'])) {
                        continue;
                    }
                    if ($this->inferHotspotKind($meta, $nodes[$candId]) !== 'room') {
                        $backWalk = $candId;
                        break;
                    }
                }

                // 3. Branch walks (-75°, +75°, ...)
                $otherWalkIdx = 0;
                foreach ($neighbors as $candId) {
                    if ($candId === $forwardWalk || $candId === $backWalk) {
                        continue;
                    }
                    if ($this->inferHotspotKind($meta, $nodes[$candId]) !== 'room') {
                        $off = $otherWalkOffsets[$otherWalkIdx] ?? (($otherWalkIdx + 1) * 42.0);
                        $placeHotspot($candId, $defaultYaw + $off);
                        $otherWalkIdx++;
                    }
                }

                // Place back_walk now cleanly (if corridor has no forward & no other walks and isn't a room, face it forward so chevron is immediately visible)
                if ($backWalk !== null) {
                    $backOffset = ($forwardWalk === null && $otherWalkIdx === 0 && ($meta['type'] ?? '') !== 'room') ? 0.0 : 180.0;
                    $placeHotspot($backWalk, $defaultYaw + $backOffset);
                }

                // 4. Classrooms around corridor (-48°, +48°, -105°, +105°, ...)
                $roomIdx = 0;
                foreach ($neighbors as $candId) {
                    if (isset($placed[$candId])) {
                        continue;
                    }
                    $off = $roomOffsets[$roomIdx] ?? (($roomIdx % 2 === 0 ? -1 : 1) * (35.0 + $roomIdx * 18.0));
                    $placeHotspot($candId, $defaultYaw + $off);
                    $roomIdx++;
                }
            }

            $scenes[(string) $id] = [
                'id'            => $id,
                'label'         => $meta['label'],
                'zone'          => $meta['zone'],
                'floor'         => (int) $meta['floor'],
                'type'          => $meta['type'],
                'excluded'      => $isExcluded,
                'filename'      => $filename,
                'default_pitch' => $defaultPitch,
                'default_yaw'   => $defaultYaw,
                'hotspots'      => $hotspots,
            ];
        }

        // Pass 2: Heading continuity so stepping along corridors always faces onward, never back toward origin
        foreach ($scenes as $fromKey => &$fromScene) {
            if (!empty($fromScene['excluded'])) {
                continue;
            }
            $fromId = (int) $fromScene['id'];

            foreach ($fromScene['hotspots'] as &$hs) {
                $toKey = (string) $hs['target_id'];
                if (!isset($scenes[$toKey]) || !empty($scenes[$toKey]['excluded'])) {
                    continue;
                }

                $targetScene = $scenes[$toKey];
                $revYaw = null;
                $walkContinuations = [];
                $roomContinuations = [];

                foreach ($targetScene['hotspots'] as $candHs) {
                    if ((int) $candHs['target_id'] === $fromId) {
                        $revYaw = (float) $candHs['yaw'];
                        continue;
                    }
                    if (($candHs['kind'] ?? 'walk') !== 'room') {
                        $walkContinuations[] = $candHs;
                    } else {
                        $roomContinuations[] = $candHs;
                    }
                }

                $desiredForwardYaw = $revYaw !== null
                    ? $this->wrapYaw($revYaw + 180.0)
                    : (float) ($targetScene['default_yaw'] ?? 0.0);

                if (!empty($walkContinuations)) {
                    $best = $walkContinuations[0];
                    $bestDiff = $this->angularDiff((float) $best['yaw'], $desiredForwardYaw);
                    foreach ($walkContinuations as $cand) {
                        $diff = $this->angularDiff((float) $cand['yaw'], $desiredForwardYaw);
                        if ($diff < $bestDiff) {
                            $bestDiff = $diff;
                            $best = $cand;
                        }
                    }
                    $hs['target_yaw'] = round((float) $best['yaw'], 1);
                } elseif (!empty($roomContinuations)) {
                    $hs['target_yaw'] = round((float) $roomContinuations[0]['yaw'], 1);
                } else {
                    $hs['target_yaw'] = round($desiredForwardYaw, 1);
                }
            }
            unset($hs);
        }
        unset($fromScene);

        return [
            'version'        => self::GRAPH_VERSION,
            'updated_at'     => now()->toIso8601String(),
            'start_scene_id' => self::START_SCENE_ID,
            'scenes'         => $scenes,
        ];
    }

    /**
     * Muat kalibrasi awal dari database/seeders/snapshots/photo_360_calibrations.json.
     *
     * @return array<string, array{pitch: float, yaw: float}>
     */
    protected function loadPhotoCalibrations(): array
    {
        $path = database_path('seeders/snapshots/photo_360_calibrations.json');
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $row) {
            if (!is_array($row) || empty($row['filename'])) {
                continue;
            }
            $map[(string) $row['filename']] = [
                'pitch' => (float) ($row['pitch'] ?? 0.0),
                'yaw'   => (float) ($row['yaw'] ?? 0.0),
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    protected function inferHotspotKind(array $from, array $to): string
    {
        $fFloor = (int) ($from['floor'] ?? 1);
        $tFloor = (int) ($to['floor'] ?? 1);

        if ($tFloor > $fFloor) {
            return 'stairs_up';
        }
        if ($tFloor < $fFloor) {
            return 'stairs_down';
        }
        if (($to['type'] ?? '') === 'room') {
            return 'room';
        }

        return 'walk';
    }

    protected function defaultPitchForKind(string $kind): float
    {
        return match ($kind) {
            'stairs_up'   => 6.0,
            'stairs_down' => -14.0,
            'room'        => -2.0,
            default       => -20.0,
        };
    }

    /**
     * @param array<string, mixed> $target
     */
    protected function defaultHotspotLabel(array $target, string $kind): string
    {
        $label = (string) ($target['label'] ?? 'Titik Berikutnya');

        return match ($kind) {
            'stairs_up'   => "Naik ke Lantai {$target['floor']}: {$label}",
            'stairs_down' => "Turun ke Lantai {$target['floor']}: {$label}",
            'room'        => "Masuk {$label}",
            default       => "Menuju {$label}",
        };
    }

    /**
     * @param array<int, array<string, mixed>> $hotspots
     * @param array<string, mixed> $newHotspot
     * @return array<int, array<string, mixed>>
     */
    protected function upsertHotspot(array $hotspots, array $newHotspot): array
    {
        $targetId = (int) $newHotspot['target_id'];
        $replaced = false;

        foreach ($hotspots as $i => $hs) {
            if ((int) ($hs['target_id'] ?? 0) === $targetId) {
                $hotspots[$i] = $newHotspot;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $hotspots[] = $newHotspot;
        }

        return array_values($hotspots);
    }

    protected function wrapYaw(float $deg): float
    {
        $wrapped = fmod($deg + 180.0, 360.0);
        if ($wrapped < 0) {
            $wrapped += 360.0;
        }

        return $wrapped - 180.0;
    }

    protected function angularDiff(float $a, float $b): float
    {
        return abs($this->wrapYaw($a - $b));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeJsonAtomically(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (!is_file($path) || file_get_contents($path) !== $json) {
            file_put_contents($path, $json, LOCK_EX);
        }
    }
}
