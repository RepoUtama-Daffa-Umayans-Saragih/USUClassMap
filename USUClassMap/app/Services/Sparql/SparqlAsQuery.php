<?php

namespace App\Services\Sparql;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SparqlAsQuery
{
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $this->endpoint = config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query');
        $this->timeout = config('sparql.timeout', 10);
    }

    /**
     * Konsep AS #1 (Agregasi): Menghitung total jadwal perkuliahan per gedung dengan COUNT(...) AS ?totalJadwal.
     *
     * @return array
     */
    public function getScheduleCountPerBuilding(): array
    {
        $sparql = <<<SPARQL
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

SELECT ?kodeGedung ?namaGedung (COUNT(?jadwal) AS ?totalJadwal)
WHERE {
    ?jadwal a usu:Jadwal ;
            usu:berlokasiDi ?ruangan .
    ?ruangan usu:beradaDiGedung ?gedung .
    ?gedung usu:kodeGedung ?kodeGedung ;
            usu:namaGedung ?namaGedung .
}
GROUP BY ?kodeGedung ?namaGedung
ORDER BY DESC(?totalJadwal)
SPARQL;

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->accept('application/sparql-results+json')
                ->post($this->endpoint, ['query' => $sparql]);

            if ($response->successful()) {
                $bindings = $response->json('results.bindings') ?? [];
                return array_map(function ($b) {
                    return [
                        'kode_gedung'  => $b['kodeGedung']['value'] ?? '',
                        'nama_gedung'  => $b['namaGedung']['value'] ?? '',
                        'total_jadwal' => (int) ($b['totalJadwal']['value'] ?? 0),
                    ];
                }, $bindings);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('SparqlAsQuery Aggregation Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Konsep AS #2 (BIND Assignment): Membentuk kolom turunan rentang waktu dengan BIND(CONCAT(...) AS ?rentangWaktu).
     *
     * @param string|null $day Filter hari opsional (misal: 'Senin')
     * @param int $limit
     * @return array
     */
    public function getSchedulesWithFormattedDuration(?string $day = null, int $limit = 20): array
    {
        $dayFilter = '';
        if (!empty($day)) {
            $sanitizedDay = addcslashes(trim($day), '"\\');
            $dayFilter = "FILTER(?hari = \"{$sanitizedDay}\")\n    ";
        }

        $sparql = <<<SPARQL
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

SELECT ?namaMk ?hari ?jamMulai ?jamSelesai ?rentangWaktu ?namaRuangan ?namaGedung
WHERE {
    ?jadwal a usu:Jadwal ;
            usu:hari ?hari ;
            usu:jamMulai ?jamMulai ;
            usu:jamSelesai ?jamSelesai ;
            usu:memilikiMataKuliah ?mk ;
            usu:berlokasiDi ?ruangan .
    ?mk usu:namaMataKuliah ?namaMk .
    ?ruangan usu:namaRuangan ?namaRuangan ;
             usu:beradaDiGedung ?gedung .
    ?gedung usu:namaGedung ?namaGedung .

    {$dayFilter}BIND(CONCAT(STR(?jamMulai), " - ", STR(?jamSelesai)) AS ?rentangWaktu)
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
                return array_map(function ($b) {
                    return [
                        'nama_mk'      => $b['namaMk']['value'] ?? '',
                        'hari'         => $b['hari']['value'] ?? '',
                        'jam_mulai'    => $b['jamMulai']['value'] ?? '',
                        'jam_selesai'  => $b['jamSelesai']['value'] ?? '',
                        'rentang_waktu'=> $b['rentangWaktu']['value'] ?? '',
                        'nama_ruangan' => $b['namaRuangan']['value'] ?? '',
                        'nama_gedung'  => $b['namaGedung']['value'] ?? '',
                    ];
                }, $bindings);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('SparqlAsQuery Duration Error: ' . $e->getMessage());
            return [];
        }
    }
}
