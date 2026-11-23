<?php

namespace App\Services\Sparql;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SparqlAskQuery
{
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $this->endpoint = config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query');
        $this->timeout = config('sparql.timeout', 10);
    }

    /**
     * Konsep YES/NO Query: Mengecek apakah suatu ruangan terpakai pada hari dan jam tertentu.
     *
     * @param string $roomName Nama ruangan (misal: 'Lab Artificial Intelligence')
     * @param string $day Hari perkuliahan (misal: 'Selasa')
     * @param string|null $startTime Jam mulai perkuliahan opsional (misal: '10:00')
     * @return bool
     */
    public function hasScheduleInRoom(string $roomName, string $day, ?string $startTime = null): bool
    {
        $sanitizedRoom = addcslashes(trim($roomName), '"\\');
        $sanitizedDay  = addcslashes(trim($day), '"\\');

        $timeClause = '';
        if (!empty($startTime)) {
            $sanitizedTime = addcslashes(trim($startTime), '"\\');
            $timeClause = "usu:jamMulai \"{$sanitizedTime}\" ;\n            ";
        }

        $sparql = <<<SPARQL
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

ASK {
    ?jadwal a usu:Jadwal ;
            usu:hari "{$sanitizedDay}" ;
            {$timeClause}usu:berlokasiDi ?ruangan .
    ?ruangan usu:namaRuangan "{$sanitizedRoom}" .
}
SPARQL;

        return $this->executeAsk($sparql);
    }

    /**
     * Konsep YES/NO Query: Mengecek apakah suatu gedung memiliki jadwal perkuliahan terdaftar.
     *
     * @param string $buildingCode Kode gedung (misal: 'GKB', 'FIB', 'FASILKOM-B')
     * @return bool
     */
    public function hasBuildingSchedules(string $buildingCode): bool
    {
        $sanitizedCode = addcslashes(trim($buildingCode), '"\\');

        $sparql = <<<SPARQL
PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>

ASK {
    ?jadwal a usu:Jadwal ;
            usu:berlokasiDi ?ruangan .
    ?ruangan usu:beradaDiGedung ?gedung .
    ?gedung usu:kodeGedung "{$sanitizedCode}" .
}
SPARQL;

        return $this->executeAsk($sparql);
    }

    /**
     * Eksekusi kueri SPARQL ASK dan ambil boolean langsung dari payload JSON Fuseki.
     */
    protected function executeAsk(string $sparql): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->accept('application/sparql-results+json')
                ->post($this->endpoint, ['query' => $sparql]);

            return $response->successful() && $response->json('boolean') === true;
        } catch (\Throwable $e) {
            Log::error('SparqlAskQuery Error: ' . $e->getMessage());
            return false;
        }
    }
}
