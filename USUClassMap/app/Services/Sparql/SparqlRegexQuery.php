<?php

namespace App\Services\Sparql;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SparqlRegexQuery
{
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $this->endpoint = config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query');
        $this->timeout = config('sparql.timeout', 10);
    }

    /**
     * Pencarian jadwal kuliah dengan pencocokan pola Regular Expression (SPARQL REGEX).
     *
     * @param string $pattern Pola regex (misal: '^SAE', 'literacy', 'dr\\.')
     * @param int $limit Maksimum hasil yang dikembalikan
     * @return array
     */
    public function searchByPattern(string $pattern, int $limit = 30): array
    {
        $sanitizedPattern = addcslashes(trim($pattern), '"\\');

        $sparql = <<<SPARQL
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

SELECT ?jadwal ?kodeMk ?namaMk ?namaDosen ?namaRuangan ?namaGedung ?hari ?jamMulai ?jamSelesai
WHERE {
    ?jadwal a usu:Jadwal ;
            usu:hari ?hari ;
            usu:jamMulai ?jamMulai ;
            usu:jamSelesai ?jamSelesai ;
            usu:memilikiMataKuliah ?mk ;
            usu:diajarOleh ?dosen ;
            usu:berlokasiDi ?ruangan .
    ?mk usu:kodeMataKuliah ?kodeMk ;
        usu:namaMataKuliah ?namaMk .
    ?dosen usu:namaDosen ?namaDosen .
    ?ruangan usu:namaRuangan ?namaRuangan ;
             usu:beradaDiGedung ?gedung .
    ?gedung usu:namaGedung ?namaGedung .

    FILTER(
        REGEX(STR(?namaMk), "{$sanitizedPattern}", "i") ||
        REGEX(STR(?kodeMk), "{$sanitizedPattern}", "i") ||
        REGEX(STR(?namaDosen), "{$sanitizedPattern}", "i")
    )
}
ORDER BY ?hari ?jamMulai ?namaMk
LIMIT {$limit}
SPARQL;

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->accept('application/sparql-results+json')
                ->post($this->endpoint, ['query' => $sparql]);

            if ($response->successful()) {
                $bindings = $response->json('results.bindings') ?? [];
                $data = array_map(function ($b) {
                    return [
                        'jadwal_uri'  => $b['jadwal']['value'] ?? '',
                        'kode_mk'     => $b['kodeMk']['value'] ?? '',
                        'nama_mk'     => $b['namaMk']['value'] ?? '',
                        'dosen'       => $b['namaDosen']['value'] ?? '',
                        'ruangan'     => $b['namaRuangan']['value'] ?? '',
                        'gedung'      => $b['namaGedung']['value'] ?? '',
                        'hari'        => $b['hari']['value'] ?? '',
                        'jam_mulai'   => $b['jamMulai']['value'] ?? '',
                        'jam_selesai' => $b['jamSelesai']['value'] ?? '',
                    ];
                }, $bindings);

                return [
                    'status'  => 'success',
                    'concept' => 'SPARQL REGEX',
                    'pattern' => $pattern,
                    'count'   => count($data),
                    'data'    => $data,
                ];
            }

            return ['status' => 'error', 'message' => 'Fuseki error: ' . $response->status(), 'data' => []];
        } catch (\Throwable $e) {
            Log::error('SparqlRegexQuery Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage(), 'data' => []];
        }
    }
}
