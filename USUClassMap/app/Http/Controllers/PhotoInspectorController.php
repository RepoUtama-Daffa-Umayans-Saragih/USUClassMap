<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PhotoInspectorController extends Controller
{
    /**
     * Endpoint SPARQL Fuseki
     */
    protected string $queryEndpoint;
    protected string $updateEndpoint;

    public function __construct()
    {
        $this->queryEndpoint  = env('FUSEKI_QUERY_URL', 'http://localhost:3030/usuclassmap/query');
        $this->updateEndpoint = env('FUSEKI_UPDATE_URL', 'http://localhost:3030/usuclassmap/update');
    }

    /**
     * Display the 360 Photo Inspector utility page.
     * Scans storage/app/public/foto360 and merges with Pitch & Yaw data from Apache Jena Fuseki.
     */
    public function index()
    {
        $disk = Storage::disk('public');

        // 1. Ambil data Pitch & Yaw yang tersimpan di Apache Jena Fuseki via SPARQL SELECT
        $calibrations = [];

        try {
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

            $response = Http::timeout(3)
                ->accept('application/sparql-results+json')
                ->get($this->queryEndpoint, [
                    'query' => $sparqlSelect,
                ]);

            if ($response->successful()) {
                $bindings = $response->json('results.bindings') ?? [];
                foreach ($bindings as $b) {
                    $fn = $b['filename']['value'] ?? null;
                    if ($fn) {
                        $calibrations[$fn] = [
                            'pitch' => isset($b['pitch']['value']) ? (float) $b['pitch']['value'] : 0.0,
                            'yaw'   => isset($b['yaw']['value'])   ? (float) $b['yaw']['value']   : 0.0,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback anggun jika Fuseki server sedang offline/belum berjalan
            logger()->warning('Apache Jena Fuseki SPARQL Query tidak dapat dihubungi: ' . $e->getMessage());
        }

        // 2. Pindai berkas fisik dan gabungkan dengan koordinat Pitch & Yaw dari Fuseki
        $files = collect($disk->files('foto360'))
            ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg']))
            ->map(function (string $path) use ($disk, $calibrations): array {
                $bytes    = $disk->exists($path) ? $disk->size($path) : 0;
                $modified = $disk->exists($path) ? $disk->lastModified($path) : time();
                $filename = basename($path);

                $savedPitch = $calibrations[$filename]['pitch'] ?? 0.0;
                $savedYaw   = $calibrations[$filename]['yaw']   ?? 0.0;

                return [
                    'filename'      => $filename,
                    'url'           => asset('storage/' . $path),
                    'size'          => $this->humanFilesize($bytes),
                    'size_bytes'    => $bytes,
                    'last_modified' => date('d M Y, H:i', $modified),
                    'path'          => $path,
                    'pitch'         => $savedPitch,
                    'yaw'           => $savedYaw,
                ];
            })
            ->sortBy('filename')
            ->values();

        return view('admin.cek-foto', [
            'photos' => $files,
        ]);
    }

    /**
     * Display the 360 Photo Inspector (Cylinder mode) utility page.
     * Scans storage/app/public/foto360p and merges with Pitch & Yaw data from Apache Jena Fuseki.
     */
    public function indexCylinder()
    {
        $disk = Storage::disk('public');

        // 1. Ambil data Pitch & Yaw yang tersimpan di Apache Jena Fuseki via SPARQL SELECT
        $calibrations = [];

        try {
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

            $response = Http::timeout(3)
                ->accept('application/sparql-results+json')
                ->get($this->queryEndpoint, [
                    'query' => $sparqlSelect,
                ]);

            if ($response->successful()) {
                $bindings = $response->json('results.bindings') ?? [];
                foreach ($bindings as $b) {
                    $fn = $b['filename']['value'] ?? null;
                    if ($fn) {
                        $calibrations[$fn] = [
                            'pitch' => isset($b['pitch']['value']) ? (float) $b['pitch']['value'] : 0.0,
                            'yaw'   => isset($b['yaw']['value'])   ? (float) $b['yaw']['value']   : 0.0,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback anggun jika Fuseki server sedang offline/belum berjalan
            logger()->warning('Apache Jena Fuseki SPARQL Query tidak dapat dihubungi: ' . $e->getMessage());
        }

        // 2. Pindai berkas fisik dari folder foto360p dan gabungkan dengan koordinat dari Fuseki
        $files = collect(Storage::disk('public')->files('foto360p'))
            ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg']))
            ->map(function (string $path) use ($disk, $calibrations): array {
                $bytes    = $disk->exists($path) ? $disk->size($path) : 0;
                $modified = $disk->exists($path) ? $disk->lastModified($path) : time();
                $filename = basename($path);

                $savedPitch = $calibrations[$filename]['pitch'] ?? 0.0;
                $savedYaw   = $calibrations[$filename]['yaw']   ?? 0.0;

                return [
                    'filename'      => $filename,
                    'url'           => asset('storage/' . $path),
                    'size'          => $this->humanFilesize($bytes),
                    'size_bytes'    => $bytes,
                    'last_modified' => date('d M Y, H:i', $modified),
                    'path'          => $path,
                    'pitch'         => $savedPitch,
                    'yaw'           => $savedYaw,
                ];
            })
            ->sortBy('filename')
            ->values();

        return view('admin.cek-foto-p', [
            'photos' => $files,
        ]);
    }

    /**
     * Simpan koordinat Pitch & Yaw ke Apache Jena Fuseki via SPARQL Update.
     */
    public function saveCalibration(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'pitch'    => 'required|numeric',
            'yaw'      => 'required|numeric',
        ]);

        $filename = $validated['filename'];
        $pitch    = (float) $validated['pitch'];
        $yaw      = (float) $validated['yaw'];

        // Sanitasi ID untuk URI resource RDF (contoh: 424.jpg -> Photo_424)
        $cleanId  = preg_replace('/[^a-zA-Z0-9_]/', '_', pathinfo($filename, PATHINFO_FILENAME));
        $photoUri = "ucm:Photo_{$cleanId}";

        // Query SPARQL DELETE & INSERT (UPSERT)
        $sparqlUpdate = <<<SPARQL
PREFIX ucm: <http://example.org/usuclassmap/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

DELETE {
    {$photoUri} ucm:hasFileName ?oldFilename ;
                ucm:hasPitch ?oldPitch ;
                ucm:hasYaw ?oldYaw .
}
INSERT {
    {$photoUri} ucm:hasFileName "{$filename}" ;
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
            $response = Http::asForm()
                ->timeout(5)
                ->post($this->updateEndpoint, [
                    'update' => $sparqlUpdate,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Koordinat Pitch & Yaw untuk {$filename} berhasil disimpan ke Apache Jena Fuseki.",
                    'data'    => [
                        'filename'  => $filename,
                        'pitch'     => $pitch,
                        'yaw'       => $yaw,
                        'photo_uri' => "http://example.org/usuclassmap/Photo_{$cleanId}",
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Apache Jena Fuseki merespons error (' . $response->status() . '): ' . $response->body(),
            ], 500);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghubungi Apache Jena Fuseki di ' . $this->updateEndpoint . ' (' . $e->getMessage() . ')',
            ], 503);
        }
    }

    /**
     * Format raw bytes into human-readable size string (B, KB, MB, GB).
     */
    private function humanFilesize(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return number_format($bytes / 1_073_741_824, 2) . ' GB';
        }

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2) . ' MB';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}