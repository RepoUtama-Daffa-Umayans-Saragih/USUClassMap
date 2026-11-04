<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SparqlService
{
    protected string $queryEndpoint;
    protected int $timeout;
    protected array $namespaces;

    public function __construct()
    {
        $this->queryEndpoint = config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query');
        $this->timeout = config('sparql.timeout', 5);
        $this->namespaces = config('sparql.namespaces', [
            'usu' => 'http://usuclassmap.usu.ac.id/ontology#',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        ]);
    }

    /**
     * Search schedules using SPARQL with text filter & day filter.
     * Includes automatic fallback to MySQL if Fuseki is offline.
     *
     * @param string|null $keyword Search by course name, code, or lecturer name
     * @param string|null $day Filter by day (Senin - Sabtu)
     * @return array Formatted array of schedule records
     */
    public function searchSchedules(?string $keyword = null, ?string $day = null): array
    {
        try {
            $sparql = $this->buildSearchQuery($keyword, $day);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/sparql-results+json',
                ])
                ->asForm()
                ->post($this->queryEndpoint, [
                    'query' => $sparql,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $this->formatSparqlResults($data);

                // If results found via SPARQL, return them
                return [
                    'status' => 'success',
                    'source' => 'SPARQL (Apache Jena Fuseki)',
                    'count' => count($results),
                    'data' => $results,
                ];
            }

            Log::warning('SparqlService: Fuseki returned non-success response code: ' . $response->status());
            return $this->fallbackToMysql($keyword, $day);
        } catch (\Exception $e) {
            Log::info('SparqlService: Fuseki query unavailable (' . $e->getMessage() . '). Using MySQL fallback.');
            return $this->fallbackToMysql($keyword, $day);
        }
    }

    /**
     * Build SPARQL SELECT query with dynamic filters.
     */
    protected function buildSearchQuery(?string $keyword = null, ?string $day = null): string
    {
        $prefixHeader = '';
        foreach ($this->namespaces as $prefix => $uri) {
            $prefixHeader .= "PREFIX {$prefix}: <{$uri}>\n";
        }

        $filters = [];

        if (!empty($keyword)) {
            $sanitizedKeyword = addslashes(trim($keyword));
            $filters[] = "(CONTAINS(LCASE(?namaMk), LCASE(\"{$sanitizedKeyword}\")) || " .
                         "CONTAINS(LCASE(?kodeMk), LCASE(\"{$sanitizedKeyword}\")) || " .
                         "CONTAINS(LCASE(?namaDosen), LCASE(\"{$sanitizedKeyword}\")) || " .
                         "CONTAINS(LCASE(?namaRuangan), LCASE(\"{$sanitizedKeyword}\")) || " .
                         "CONTAINS(LCASE(?namaGedung), LCASE(\"{$sanitizedKeyword}\")))";
        }

        if (!empty($day)) {
            $sanitizedDay = addslashes(trim($day));
            $filters[] = "(?hari = \"{$sanitizedDay}\")";
        }

        $filterClause = !empty($filters) ? 'FILTER(' . implode(' && ', $filters) . ')' : '';

        return <<<SPARQL
{$prefixHeader}
SELECT ?jadwal ?hari ?jamMulai ?jamSelesai ?semester ?kelompokKelas
       ?kodeMk ?namaMk ?sks ?tipeMk
       ?namaDosen ?nipDosen ?emailDosen
       ?namaRuangan ?lantaiRuangan ?kapasitasRuangan ?pathFoto360
       ?namaGedung ?kodeGedung ?latitude ?longitude
WHERE {
    ?jadwal a usu:Jadwal ;
            usu:hari ?hari ;
            usu:jamMulai ?jamMulai ;
            usu:jamSelesai ?jamSelesai ;
            usu:semester ?semester ;
            usu:kelompokKelas ?kelompokKelas .

    OPTIONAL {
        ?jadwal usu:memilikiMataKuliah ?mk .
        ?mk usu:kodeMataKuliah ?kodeMk ;
            usu:namaMataKuliah ?namaMk ;
            usu:sks ?sks ;
            usu:tipeMataKuliah ?tipeMk .
    }

    OPTIONAL {
        ?jadwal usu:diajarOleh ?dosen .
        ?dosen usu:namaDosen ?namaDosen .
        OPTIONAL { ?dosen usu:nipDosen ?nipDosen . }
        OPTIONAL { ?dosen usu:emailDosen ?emailDosen . }
    }

    OPTIONAL {
        ?jadwal usu:berlokasiDi ?ruangan .
        ?ruangan usu:namaRuangan ?namaRuangan ;
                 usu:lantaiRuangan ?lantaiRuangan ;
                 usu:kapasitasRuangan ?kapasitasRuangan .
        OPTIONAL { ?ruangan usu:pathFoto360 ?pathFoto360 . }

        OPTIONAL {
            ?ruangan usu:beradaDiGedung ?gedung .
            ?gedung usu:namaGedung ?namaGedung ;
                    usu:kodeGedung ?kodeGedung ;
                    usu:latitude ?latitude ;
                    usu:longitude ?longitude .
        }
    }

    {$filterClause}
}
ORDER BY ?hari ?jamMulai ?namaMk
SPARQL;
    }

    /**
     * Format SPARQL JSON bindings to standardized application array structure.
     */
    protected function formatSparqlResults(array $jsonResponse): array
    {
        $bindings = $jsonResponse['results']['bindings'] ?? [];
        $formatted = [];

        foreach ($bindings as $b) {
            $uri = $b['jadwal']['value'] ?? '';
            $id = (int) basename($uri);

            $photoPath = $b['pathFoto360']['value'] ?? null;
            $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;

            $formatted[] = [
                'uri' => $uri,
                'id' => $id,
                'hari' => $b['hari']['value'] ?? '',
                'jam_mulai' => $b['jamMulai']['value'] ?? '',
                'jam_selesai' => $b['jamSelesai']['value'] ?? '',
                'semester' => $b['semester']['value'] ?? '',
                'kelompok_kelas' => $b['kelompokKelas']['value'] ?? '',
                'mata_kuliah' => [
                    'kode' => $b['kodeMk']['value'] ?? '',
                    'nama' => $b['namaMk']['value'] ?? '',
                    'sks' => isset($b['sks']['value']) ? (int) $b['sks']['value'] : 0,
                    'tipe' => $b['tipeMk']['value'] ?? 'MKWK',
                ],
                'dosen' => [
                    'nama' => $b['namaDosen']['value'] ?? '',
                    'nip' => $b['nipDosen']['value'] ?? null,
                    'email' => $b['emailDosen']['value'] ?? null,
                ],
                'ruangan' => [
                    'nama' => $b['namaRuangan']['value'] ?? '',
                    'lantai' => isset($b['lantaiRuangan']['value']) ? (int) $b['lantaiRuangan']['value'] : 1,
                    'kapasitas' => isset($b['kapasitasRuangan']['value']) ? (int) $b['kapasitasRuangan']['value'] : 0,
                    'foto_360' => $photoUrl,
                    'foto_360_raw' => $photoPath,
                ],
                'gedung' => [
                    'nama' => $b['namaGedung']['value'] ?? '',
                    'kode' => $b['kodeGedung']['value'] ?? '',
                    'latitude' => isset($b['latitude']['value']) ? (float) $b['latitude']['value'] : null,
                    'longitude' => isset($b['longitude']['value']) ? (float) $b['longitude']['value'] : null,
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Fallback to direct MySQL Eloquent query with the same output format.
     */
    public function fallbackToMysql(?string $keyword = null, ?string $day = null): array
    {
        $query = Schedule::with(['course', 'lecturer', 'room.building']);

        if (!empty($keyword)) {
            $search = trim($keyword);
            $query->where(function ($q) use ($search) {
                $q->whereHas('course', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('lecturer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('room', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('building', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            });
        }

        if (!empty($day)) {
            $query->where('day', trim($day));
        }

        $schedules = $query->orderBy('day')->orderBy('time_start')->get();

        $formatted = $schedules->map(function ($s) {
            $photoPath = $s->room?->photo_360_path;
            $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;

            return [
                'uri' => 'http://usuclassmap.usu.ac.id/resource/jadwal/' . $s->id,
                'id' => $s->id,
                'hari' => $s->day,
                'jam_mulai' => substr($s->time_start, 0, 5),
                'jam_selesai' => substr($s->time_end, 0, 5),
                'semester' => $s->semester,
                'kelompok_kelas' => $s->class_group,
                'mata_kuliah' => [
                    'kode' => $s->course?->code ?? '',
                    'nama' => $s->course?->name ?? '',
                    'sks' => (int) ($s->course?->credits ?? 0),
                    'tipe' => $s->course?->type ?? 'MKWK',
                ],
                'dosen' => [
                    'nama' => $s->lecturer?->name ?? '',
                    'nip' => $s->lecturer?->nip,
                    'email' => $s->lecturer?->email,
                ],
                'ruangan' => [
                    'nama' => $s->room?->name ?? '',
                    'lantai' => (int) ($s->room?->floor ?? 1),
                    'kapasitas' => (int) ($s->room?->capacity ?? 0),
                    'foto_360' => $photoUrl,
                    'foto_360_raw' => $photoPath,
                ],
                'gedung' => [
                    'nama' => $s->room?->building?->name ?? '',
                    'kode' => $s->room?->building?->code ?? '',
                    'latitude' => (float) ($s->room?->building?->latitude ?? 0),
                    'longitude' => (float) ($s->room?->building?->longitude ?? 0),
                ],
            ];
        })->toArray();

        return [
            'status' => 'success',
            'source' => 'MySQL (Fallback)',
            'count' => count($formatted),
            'data' => $formatted,
        ];
    }
}
