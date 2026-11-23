<?php

namespace App\Services;

use App\Observers\GitSnapshotObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GitSnapshotSyncService
{
    /**
     * Daftar tabel CRUD yang disinkronkan via Git Snapshot (urutan Parent -> Child untuk Upsert).
     * Urutan terbalik (Child -> Parent) digunakan saat Prune.
     */
    public const TABLES = [
        'buildings',
        'rooms',
        'room_media',
        'courses',
        'lecturers',
        'schedules',
        'fotocopy_stores',
        'photocopy_places',
        'fotocopy_services',
        'photocopy_items',
    ];

    /**
     * Kolom ber-constraint UNIQUE selain primary key `id`, untuk mencegah bentrok saat swap nilai.
     */
    protected const UNIQUE_COLUMNS = [
        'buildings' => ['code'],
        'courses' => ['code'],
        'fotocopy_stores' => ['slug'],
        'photocopy_places' => ['slug'],
    ];

    /**
     * Guard anti re-entrancy agar operasi export/import tidak saling memicu.
     */
    protected static bool $isSyncing = false;

    public function getSnapshotDir(): string
    {
        return database_path('seeders/snapshots');
    }

    public function getManifestPath(): string
    {
        return $this->getSnapshotDir() . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    public function getCalibrationsPath(): string
    {
        return $this->getSnapshotDir() . DIRECTORY_SEPARATOR . 'photo_360_calibrations.json';
    }

    public function getRdfSnapshotPath(): string
    {
        return $this->getSnapshotDir() . DIRECTORY_SEPARATOR . 'schedules.rdf';
    }

    public function getLocalStatePath(): string
    {
        return storage_path('app/git_snapshot_state.json');
    }

    /**
     * Cek cepat (< 1ms) apakah manifest.json dari Git memiliki hash baru yang belum diimpor ke DB lokal.
     */
    public function hasPendingImport(): bool
    {
        $manifestPath = $this->getManifestPath();
        if (!is_file($manifestPath)) {
            return false;
        }

        $manifest = $this->readJsonFile($manifestPath);
        $manifestHash = is_array($manifest) ? (string) ($manifest['content_hash'] ?? '') : '';
        if ($manifestHash === '') {
            return false;
        }

        $localState = $this->readLocalState();
        $localHash = (string) ($localState['last_synced_hash'] ?? '');

        return $manifestHash !== $localHash;
    }

    /**
     * Ekspor seluruh data tabel CRUD + kalibrasi 360 + schedules.rdf ke database/seeders/snapshots/.
     */
    public function exportAll(bool $regenerateRdf = false): array
    {
        if (self::$isSyncing) {
            return ['exported' => false, 'reason' => 'reentrant'];
        }

        self::$isSyncing = true;

        try {
            $dir = $this->getSnapshotDir();
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $counts = [];
            $files = [];

            foreach (self::TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(static fn ($row): array => (array) $row)
                    ->values()
                    ->all();

                $fileName = "{$table}.json";
                $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;

                $this->writeJsonIfChanged($filePath, $rows);

                $counts[$table] = count($rows);
                $files[] = $fileName;
            }

            // Pastikan photo_360_calibrations.json tersedia
            $calibrationsPath = $this->getCalibrationsPath();
            if (!is_file($calibrationsPath)) {
                $initialCalibrations = $this->fetchPhotoCalibrationsFromFuseki();
                $this->writeJsonIfChanged($calibrationsPath, $initialCalibrations);
                $counts['photo_360_calibrations'] = count($initialCalibrations);
            } else {
                $existingCalibrations = $this->readJsonFile($calibrationsPath);
                $counts['photo_360_calibrations'] = is_array($existingCalibrations) ? count($existingCalibrations) : 0;
            }
            $files[] = 'photo_360_calibrations.json';

            // Salin atau regenerasi schedules.rdf ke snapshot Git
            $rdfExported = $this->exportRdfSnapshot($regenerateRdf);
            if ($rdfExported) {
                $files[] = 'schedules.rdf';
            }

            // Hitung hash deterministik atas seluruh berkas JSON snapshot
            $contentHash = $this->computeContentHash();

            $manifestPath = $this->getManifestPath();
            $existingManifest = $this->readJsonFile($manifestPath);
            $previousHash = is_array($existingManifest) ? (string) ($existingManifest['content_hash'] ?? '') : '';

            $manifestData = [
                'version' => 1,
                'generated_at' => ($previousHash === $contentHash && !empty($existingManifest['generated_at']))
                    ? $existingManifest['generated_at']
                    : now()->toIso8601String(),
                'content_hash' => $contentHash,
                'counts' => $counts,
                'files' => $files,
            ];

            $this->writeJsonIfChanged($manifestPath, $manifestData);

            // Perbarui state lokal agar laptop pengekspor tidak mengimpor ulang hasil ekspornya sendiri
            $this->writeLocalState($contentHash, 'export');

            return [
                'exported' => true,
                'content_hash' => $contentHash,
                'counts' => $counts,
                'rdf_exported' => $rdfExported,
            ];
        } finally {
            self::$isSyncing = false;
        }
    }

    /**
     * Simpan satu entri kalibrasi Pitch & Yaw foto 360 ke photo_360_calibrations.json dan perbarui manifest.
     */
    public function savePhotoCalibration(string $filename, float $pitch, float $yaw): array
    {
        if (self::$isSyncing) {
            return ['saved' => false, 'reason' => 'reentrant'];
        }

        self::$isSyncing = true;

        try {
            $dir = $this->getSnapshotDir();
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $calibrationsPath = $this->getCalibrationsPath();
            $existing = $this->readJsonFile($calibrationsPath);
            $map = [];

            if (is_array($existing)) {
                foreach ($existing as $item) {
                    if (is_array($item) && !empty($item['filename'])) {
                        $map[(string) $item['filename']] = [
                            'filename' => (string) $item['filename'],
                            'pitch' => (float) ($item['pitch'] ?? 0.0),
                            'yaw' => (float) ($item['yaw'] ?? 0.0),
                        ];
                    }
                }
            }

            $map[$filename] = [
                'filename' => $filename,
                'pitch' => $pitch,
                'yaw' => $yaw,
            ];

            ksort($map);
            $sorted = array_values($map);

            $this->writeJsonIfChanged($calibrationsPath, $sorted);

            // Perbarui manifest.json jika sudah ada, atau lakukan export penuh jika belum ada
            $manifestPath = $this->getManifestPath();
            if (!is_file($manifestPath)) {
                self::$isSyncing = false;
                return $this->exportAll(false);
            }

            $manifest = $this->readJsonFile($manifestPath);
            if (!is_array($manifest)) {
                $manifest = ['version' => 1, 'counts' => [], 'files' => []];
            }

            $contentHash = $this->computeContentHash();
            $manifest['generated_at'] = now()->toIso8601String();
            $manifest['content_hash'] = $contentHash;
            $manifest['counts']['photo_360_calibrations'] = count($sorted);
            if (!in_array('photo_360_calibrations.json', $manifest['files'] ?? [], true)) {
                $manifest['files'][] = 'photo_360_calibrations.json';
            }

            $this->writeJsonIfChanged($manifestPath, $manifest);
            $this->writeLocalState($contentHash, 'export');

            return [
                'saved' => true,
                'content_hash' => $contentHash,
                'count' => count($sorted),
            ];
        } finally {
            self::$isSyncing = false;
        }
    }

    /**
     * Impor seluruh snapshot JSON dari Git ke database lokal secara atomik,
     * kemudian sinkronkan RDF & kalibrasi 360 ke Apache Jena Fuseki.
     */
    public function importAll(bool $force = false): array
    {
        if (self::$isSyncing) {
            return ['synced' => false, 'reason' => 'reentrant'];
        }

        $manifestPath = $this->getManifestPath();
        if (!is_file($manifestPath)) {
            return ['synced' => false, 'reason' => 'no_manifest'];
        }

        if (!$force && !$this->hasPendingImport()) {
            return ['synced' => false, 'reason' => 'up_to_date'];
        }

        $executeImport = function () use ($force, $manifestPath): array {
            // Double-checked locking setelah lock diperoleh
            if (!$force && !$this->hasPendingImport()) {
                return ['synced' => false, 'reason' => 'up_to_date'];
            }

            $manifest = $this->readJsonFile($manifestPath);
            $manifestHash = is_array($manifest) ? (string) ($manifest['content_hash'] ?? '') : '';
            if ($manifestHash === '') {
                return ['synced' => false, 'reason' => 'invalid_manifest'];
            }

            self::$isSyncing = true;
            $previousMuted = GitSnapshotObserver::$isMuted;
            GitSnapshotObserver::$isMuted = true;

            $importedCounts = [];
            $prunedCounts = [];

            try {
                $dir = $this->getSnapshotDir();
                $validUserIds = Schema::hasTable('users')
                    ? array_flip(DB::table('users')->pluck('id')->all())
                    : [];

                // Muat semua payload JSON sebelum membuka transaksi
                $payloads = [];
                foreach (self::TABLES as $table) {
                    if (!Schema::hasTable($table)) {
                        continue;
                    }
                    $filePath = $dir . DIRECTORY_SEPARATOR . "{$table}.json";
                    if (!is_file($filePath)) {
                        continue;
                    }
                    $decoded = $this->readJsonFile($filePath);
                    if (is_array($decoded)) {
                        $payloads[$table] = $decoded;
                    }
                }

                Schema::withoutForeignKeyConstraints(function () use ($payloads, $validUserIds, &$importedCounts, &$prunedCounts): void {
                    DB::transaction(function () use ($payloads, $validUserIds, &$importedCounts, &$prunedCounts): void {
                        // FASE 1: PRUNE (Child -> Parent) - hapus baris lokal yang tidak ada lagi di snapshot Git
                        foreach (array_reverse(self::TABLES) as $table) {
                            if (!array_key_exists($table, $payloads)) {
                                continue;
                            }

                            $rows = $payloads[$table];
                            $snapshotIds = [];
                            foreach ($rows as $row) {
                                if (is_array($row) && array_key_exists('id', $row) && $row['id'] !== null) {
                                    $snapshotIds[] = $row['id'];
                                }
                            }

                            if (empty($snapshotIds)) {
                                $prunedCounts[$table] = DB::table($table)->delete();
                            } else {
                                $localIds = DB::table($table)->pluck('id')->all();
                                $idsToDelete = array_values(array_diff($localIds, $snapshotIds));
                                $deletedTotal = 0;

                                foreach (array_chunk($idsToDelete, 500) as $chunk) {
                                    $deletedTotal += DB::table($table)->whereIn('id', $chunk)->delete();
                                }

                                $prunedCounts[$table] = $deletedTotal;
                            }
                        }

                        // FASE 2: UPSERT (Parent -> Child) - sinkronkan seluruh data & kolom
                        foreach (self::TABLES as $table) {
                            if (!array_key_exists($table, $payloads)) {
                                continue;
                            }

                            $rows = $payloads[$table];
                            if (empty($rows)) {
                                $importedCounts[$table] = 0;
                                continue;
                            }

                            $tableColumns = Schema::getColumnListing($table);
                            if (empty($tableColumns)) {
                                continue;
                            }
                            $columnSet = array_flip($tableColumns);

                            // Sanitasi setiap baris terhadap skema kolom aktual di database lokal
                            $sanitizedRows = [];
                            foreach ($rows as $row) {
                                if (!is_array($row)) {
                                    continue;
                                }

                                $filtered = [];
                                foreach ($tableColumns as $col) {
                                    if (array_key_exists($col, $row)) {
                                        $value = $row[$col];
                                        if (is_array($value) || is_object($value)) {
                                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                        }
                                        $filtered[$col] = $value;
                                    }
                                }

                                // Pastikan FK opsional fotocopy_stores.user_id aman di laptop tujuan
                                if ($table === 'fotocopy_stores' && array_key_exists('user_id', $filtered) && $filtered['user_id'] !== null) {
                                    if (!isset($validUserIds[$filtered['user_id']])) {
                                        $filtered['user_id'] = null;
                                    }
                                }

                                if (!empty($filtered)) {
                                    $sanitizedRows[] = $filtered;
                                }
                            }

                            if (empty($sanitizedRows)) {
                                $importedCounts[$table] = 0;
                                continue;
                            }

                            // Resolusi bentrok kolom UNIQUE (mis. jika 2 baris bertukar code/slug)
                            if (isset(self::UNIQUE_COLUMNS[$table])) {
                                foreach (self::UNIQUE_COLUMNS[$table] as $uniqueCol) {
                                    if (!isset($columnSet[$uniqueCol])) {
                                        continue;
                                    }
                                    $this->resolveUniqueCollisions($table, $uniqueCol, $sanitizedRows);
                                }
                            }

                            // Pastikan seluruh baris punya set key yang seragam untuk upsert batch
                            $upsertColumns = array_keys($sanitizedRows[0]);
                            $updateColumns = array_values(array_diff($upsertColumns, ['id']));

                            foreach (array_chunk($sanitizedRows, 50) as $chunk) {
                                DB::table($table)->upsert($chunk, ['id'], $updateColumns);
                            }

                            $importedCounts[$table] = count($sanitizedRows);
                        }
                    });
                });

                // FASE 3: Regenerasi RDF & Sinkronisasi ke Apache Jena Fuseki (tidak menggagalkan import DB jika Fuseki mati)
                $fusekiSynced = false;
                $calibrationsSynced = 0;

                try {
                    /** @var RdfGeneratorService $rdfService */
                    $rdfService = app(RdfGeneratorService::class);
                    $rdfService->generate();
                    $fusekiSynced = $rdfService->uploadToFuseki();
                    $calibrationsSynced = $this->syncPhotoCalibrationsToFuseki();
                } catch (\Throwable $e) {
                    Log::warning('GitSnapshotSync: Gagal menyinkronkan RDF/Fuseki setelah impor DB: ' . $e->getMessage());
                }

                // FASE 4: Catat state lokal agar tidak ter-impor ulang pada request berikutnya
                $this->writeLocalState($manifestHash, 'import');

                Log::info('GitSnapshotSync: Impor snapshot Git selesai.', [
                    'content_hash' => $manifestHash,
                    'imported_counts' => $importedCounts,
                    'pruned_counts' => $prunedCounts,
                    'fuseki_synced' => $fusekiSynced,
                    'calibrations_synced' => $calibrationsSynced,
                ]);

                return [
                    'synced' => true,
                    'content_hash' => $manifestHash,
                    'imported_counts' => $importedCounts,
                    'pruned_counts' => $prunedCounts,
                    'fuseki_synced' => $fusekiSynced,
                    'calibrations_synced' => $calibrationsSynced,
                ];
            } finally {
                GitSnapshotObserver::$isMuted = $previousMuted;
                self::$isSyncing = false;
            }
        };

        try {
            $lock = Cache::lock('git_snapshot_sync_lock', 30);

            return $lock->block(5, $executeImport);
        } catch (\Throwable $e) {
            Log::warning('GitSnapshotSync: Cache lock tidak tersedia atau timeout, menjalankan impor langsung: ' . $e->getMessage());

            return $executeImport();
        }
    }

    /**
     * Dorong seluruh kalibrasi foto 360 dari photo_360_calibrations.json ke Apache Jena Fuseki.
     */
    public function syncPhotoCalibrationsToFuseki(): int
    {
        $calibrationsPath = $this->getCalibrationsPath();
        if (!is_file($calibrationsPath)) {
            return 0;
        }

        $calibrations = $this->readJsonFile($calibrationsPath);
        if (!is_array($calibrations) || empty($calibrations)) {
            return 0;
        }

        $updateEndpoint = env(
            'FUSEKI_UPDATE_URL',
            rtrim((string) config('sparql.fuseki_url', 'http://localhost:3030'), '/')
                . '/' . config('sparql.dataset', 'usuclassmap') . '/update'
        );
        $username = config('sparql.username', env('FUSEKI_USERNAME', 'admin'));
        $password = config('sparql.password', env('FUSEKI_PASSWORD', 'admin'));

        $synced = 0;

        foreach ($calibrations as $item) {
            if (!is_array($item) || empty($item['filename'])) {
                continue;
            }

            $filename = (string) $item['filename'];
            $pitch = (float) ($item['pitch'] ?? 0.0);
            $yaw = (float) ($item['yaw'] ?? 0.0);
            $cleanId = preg_replace('/[^a-zA-Z0-9_]/', '_', pathinfo($filename, PATHINFO_FILENAME));
            $photoUri = "ucm:Photo_{$cleanId}";
            $escapedFilename = str_replace(['\\', '"'], ['\\\\', '\\"'], $filename);

            $sparqlUpdate = <<<SPARQL
PREFIX ucm: <http://example.org/usuclassmap/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

DELETE {
    {$photoUri} ucm:hasFileName ?oldFilename ;
                ucm:hasPitch ?oldPitch ;
                ucm:hasYaw ?oldYaw .
}
INSERT {
    {$photoUri} ucm:hasFileName "{$escapedFilename}" ;
                ucm:hasPitch "{$pitch}"^^xsd:float ;
                ucm:hasYaw "{$yaw}"^^xsd:float .
}
WHERE {
    OPTIONAL { {$photoUri} ucm:hasFileName ?oldFilename . }
    OPTIONAL { {$photoUri} ucm:hasPitch ?oldPitch . }
    OPTIONAL { {$photoUri} ucm:hasYaw ?oldYaw . }
}
SPARQL;

            try {
                $http = Http::asForm()->timeout(3);
                if (!empty($username) && !empty($password)) {
                    $http = $http->withBasicAuth($username, $password);
                }

                $response = $http->post($updateEndpoint, ['update' => $sparqlUpdate]);
                if ($response->successful()) {
                    $synced++;
                }
            } catch (\Throwable $e) {
                Log::warning("GitSnapshotSync: Gagal mengirim kalibrasi {$filename} ke Fuseki: " . $e->getMessage());
                break;
            }
        }

        return $synced;
    }

    /**
     * Dapatkan ringkasan status sinkronisasi saat ini antara DB lokal, manifest Git, dan state lokal.
     */
    public function getStatus(): array
    {
        $manifestPath = $this->getManifestPath();
        $snapshotExists = is_file($manifestPath);
        $manifest = $snapshotExists ? $this->readJsonFile($manifestPath) : null;
        $localState = $this->readLocalState();

        $manifestHash = is_array($manifest) ? (string) ($manifest['content_hash'] ?? '') : null;
        $localHash = (string) ($localState['last_synced_hash'] ?? '');

        $tables = [];
        foreach (self::TABLES as $table) {
            $dbCount = Schema::hasTable($table) ? DB::table($table)->count() : null;
            $snapCount = is_array($manifest) && isset($manifest['counts'][$table])
                ? (int) $manifest['counts'][$table]
                : null;

            $tables[$table] = [
                'db_count' => $dbCount,
                'snapshot_count' => $snapCount,
                'matches' => ($dbCount !== null && $snapCount !== null && $dbCount === $snapCount),
            ];
        }

        $calibrationsCount = is_array($manifest) ? (int) ($manifest['counts']['photo_360_calibrations'] ?? 0) : 0;

        return [
            'snapshot_exists' => $snapshotExists,
            'manifest_hash' => $manifestHash,
            'manifest_generated_at' => is_array($manifest) ? ($manifest['generated_at'] ?? null) : null,
            'local_hash' => $localHash !== '' ? $localHash : null,
            'local_synced_at' => $localState['last_synced_at'] ?? null,
            'local_direction' => $localState['direction'] ?? null,
            'is_in_sync' => $snapshotExists && $manifestHash !== '' && $manifestHash === $localHash,
            'has_pending_import' => $this->hasPendingImport(),
            'rdf_snapshot_exists' => is_file($this->getRdfSnapshotPath()),
            'photo_360_calibrations_count' => $calibrationsCount,
            'tables' => $tables,
        ];
    }

    /**
     * Ekspor atau salin schedules.rdf ke database/seeders/snapshots/schedules.rdf.
     */
    protected function exportRdfSnapshot(bool $regenerateRdf): bool
    {
        try {
            /** @var RdfGeneratorService $rdfService */
            $rdfService = app(RdfGeneratorService::class);
            $rdfPath = $rdfService->getRdfFilePath();

            if ($regenerateRdf || !is_file($rdfPath)) {
                $rdfPath = $rdfService->generate();
            }

            if (is_file($rdfPath)) {
                $content = file_get_contents($rdfPath);
                if ($content !== false) {
                    $targetPath = $this->getRdfSnapshotPath();
                    if (!is_file($targetPath) || file_get_contents($targetPath) !== $content) {
                        file_put_contents($targetPath, $content, LOCK_EX);
                    }

                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GitSnapshotSync: Gagal mengekspor snapshot schedules.rdf: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Ambil daftar kalibrasi foto 360 yang sudah tersimpan di Fuseki (jika server menyala).
     */
    protected function fetchPhotoCalibrationsFromFuseki(): array
    {
        $queryEndpoint = env(
            'FUSEKI_QUERY_URL',
            config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query')
        );

        $sparqlSelect = <<<'SPARQL'
PREFIX ucm: <http://example.org/usuclassmap/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT ?filename ?pitch ?yaw
WHERE {
    ?photo ucm:hasFileName ?filename .
    OPTIONAL { ?photo ucm:hasPitch ?pitch . }
    OPTIONAL { ?photo ucm:hasYaw ?yaw . }
}
SPARQL;

        try {
            $response = Http::timeout(2)
                ->accept('application/sparql-results+json')
                ->get($queryEndpoint, ['query' => $sparqlSelect]);

            if (!$response->successful()) {
                return [];
            }

            $bindings = $response->json('results.bindings') ?? [];
            $map = [];
            foreach ($bindings as $b) {
                $fn = $b['filename']['value'] ?? null;
                if ($fn) {
                    $map[(string) $fn] = [
                        'filename' => (string) $fn,
                        'pitch' => isset($b['pitch']['value']) ? (float) $b['pitch']['value'] : 0.0,
                        'yaw' => isset($b['yaw']['value']) ? (float) $b['yaw']['value'] : 0.0,
                    ];
                }
            }

            ksort($map);

            return array_values($map);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Hindari kegagalan UNIQUE constraint ketika dua baris lokal dan snapshot saling bertukar code/slug.
     */
    protected function resolveUniqueCollisions(string $table, string $uniqueCol, array $rows): void
    {
        $existing = DB::table($table)->pluck($uniqueCol, 'id')->all();
        if (empty($existing)) {
            return;
        }

        $valueToLocalId = [];
        foreach ($existing as $localId => $val) {
            if ($val !== null && $val !== '') {
                $valueToLocalId[(string) $val] = $localId;
            }
        }

        foreach ($rows as $row) {
            $rowId = $row['id'] ?? null;
            $val = $row[$uniqueCol] ?? null;

            if ($rowId === null || $val === null || $val === '') {
                continue;
            }

            $key = (string) $val;
            if (isset($valueToLocalId[$key]) && (string) $valueToLocalId[$key] !== (string) $rowId) {
                $conflictingId = $valueToLocalId[$key];
                $tempVal = '__tmp_sync_' . $conflictingId . '_' . substr(md5($key . microtime()), 0, 8);
                DB::table($table)->where('id', $conflictingId)->update([$uniqueCol => $tempVal]);
                unset($valueToLocalId[$key]);
            }
        }
    }

    /**
     * Hitung SHA-256 gabungan dari semua berkas JSON snapshot (diurutkan berdasarkan nama berkas).
     */
    protected function computeContentHash(): string
    {
        $dir = $this->getSnapshotDir();
        $jsonFiles = [];

        foreach (self::TABLES as $table) {
            $jsonFiles[] = "{$table}.json";
        }
        $jsonFiles[] = 'photo_360_calibrations.json';
        sort($jsonFiles);

        $parts = [];
        foreach ($jsonFiles as $fileName) {
            $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($fullPath)) {
                $parts[] = $fileName . ':' . hash_file('sha256', $fullPath);
            }
        }

        return hash('sha256', implode("\n", $parts));
    }

    protected function writeJsonIfChanged(string $path, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (!is_file($path) || file_get_contents($path) !== $json) {
            file_put_contents($path, $json, LOCK_EX);
        }
    }

    protected function readJsonFile(string $path): mixed
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        return json_decode($raw, true);
    }

    protected function readLocalState(): array
    {
        $state = $this->readJsonFile($this->getLocalStatePath());

        return is_array($state) ? $state : [];
    }

    protected function writeLocalState(string $contentHash, string $direction): void
    {
        $statePath = $this->getLocalStatePath();
        $dir = dirname($statePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = [
            'last_synced_hash' => $contentHash,
            'last_synced_at' => now()->toIso8601String(),
            'direction' => $direction,
        ];

        file_put_contents(
            $statePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        );
    }
}
