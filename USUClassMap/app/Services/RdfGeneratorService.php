<?php

namespace App\Services;

use App\Models\Schedule;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RdfGeneratorService
{
    protected string $namespaceUri;
    protected string $resourceBaseUri;
    protected string $rdfFilePath;

    public function __construct()
    {
        $this->namespaceUri = config('sparql.namespaces.usu', 'http://usuclassmap.usu.ac.id/ontology#');
        $this->resourceBaseUri = 'http://usuclassmap.usu.ac.id/resource/';
        $this->rdfFilePath = config('sparql.rdf_storage_path', storage_path('app/rdf/schedules.rdf'));

        // Register custom namespace with EasyRdf
        RdfNamespace::set('usu', $this->namespaceUri);
    }

    /**
     * Generate RDF/XML file from MySQL schedules data.
     *
     * @return string Path to generated RDF file
     */
    public function generate(): string
    {
        $graph = new Graph();

        // Eager load all relational dependencies
        $schedules = Schedule::with(['course', 'lecturer', 'room.building'])->get();

        foreach ($schedules as $schedule) {
            $scheduleUri = $this->resourceBaseUri . 'jadwal/' . $schedule->id;
            $scheduleNode = $graph->resource($scheduleUri, 'usu:Jadwal');

            // Schedule Data Properties
            $scheduleNode->set('usu:hari', $schedule->day);
            $scheduleNode->set('usu:jamMulai', substr($schedule->time_start, 0, 5));
            $scheduleNode->set('usu:jamSelesai', substr($schedule->time_end, 0, 5));
            $scheduleNode->set('usu:semester', $schedule->semester);
            $scheduleNode->set('usu:kelompokKelas', $schedule->class_group);

            // Course relation & properties
            if ($schedule->course) {
                $course = $schedule->course;
                $courseUri = $this->resourceBaseUri . 'matakuliah/' . $course->id;
                $courseNode = $graph->resource($courseUri, 'usu:MataKuliah');
                $courseNode->set('usu:kodeMataKuliah', $course->code);
                $courseNode->set('usu:namaMataKuliah', $course->name);
                $courseNode->set('usu:sks', (int) $course->credits);
                $courseNode->set('usu:tipeMataKuliah', $course->type ?? 'MKWK');

                $scheduleNode->set('usu:memilikiMataKuliah', $courseNode);
            }

            // Lecturer relation & properties
            if ($schedule->lecturer) {
                $lecturer = $schedule->lecturer;
                $lecturerUri = $this->resourceBaseUri . 'dosen/' . $lecturer->id;
                $lecturerNode = $graph->resource($lecturerUri, 'usu:Dosen');
                $lecturerNode->set('usu:namaDosen', $lecturer->name);
                if ($lecturer->nip) {
                    $lecturerNode->set('usu:nipDosen', $lecturer->nip);
                }
                if ($lecturer->email) {
                    $lecturerNode->set('usu:emailDosen', $lecturer->email);
                }

                $scheduleNode->set('usu:diajarOleh', $lecturerNode);
            }

            // Room & Building relations & properties
            if ($schedule->room) {
                $room = $schedule->room;
                $roomUri = $this->resourceBaseUri . 'ruangan/' . $room->id;
                $roomNode = $graph->resource($roomUri, 'usu:Ruangan');
                $roomNode->set('usu:namaRuangan', $room->name);
                $roomNode->set('usu:lantaiRuangan', (int) $room->floor);
                $roomNode->set('usu:kapasitasRuangan', (int) $room->capacity);
                if ($room->photo_360_path) {
                    $roomNode->set('usu:pathFoto360', $room->photo_360_path);
                }

                if ($room->building) {
                    $building = $room->building;
                    $buildingUri = $this->resourceBaseUri . 'gedung/' . $building->id;
                    $buildingNode = $graph->resource($buildingUri, 'usu:Gedung');
                    $buildingNode->set('usu:namaGedung', $building->name);
                    $buildingNode->set('usu:kodeGedung', $building->code);
                    $buildingNode->set('usu:latitude', (string) $building->latitude);
                    $buildingNode->set('usu:longitude', (string) $building->longitude);

                    $roomNode->set('usu:beradaDiGedung', $buildingNode);
                }

                $scheduleNode->set('usu:berlokasiDi', $roomNode);
            }
        }

        // Serialize graph to RDF/XML format
        $rdfXml = $graph->serialise('rdfxml');

        // Ensure directory exists
        $dir = dirname($this->rdfFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->rdfFilePath, $rdfXml);

        Log::info('RDF Generator: Successfully generated schedules RDF file.', [
            'schedules_count' => $schedules->count(),
            'file' => $this->rdfFilePath,
        ]);

        return $this->rdfFilePath;
    }

    /**
     * Upload generated RDF file to Apache Jena Fuseki dataset.
     *
     * @return bool True if upload succeeded, false otherwise
     */
    public function uploadToFuseki(): bool
    {
        if (!file_exists($this->rdfFilePath)) {
            $this->generate();
        }

        $dataEndpoint = config('sparql.data_endpoint');
        $timeout = config('sparql.timeout', 5);

        try {
            $rdfContent = file_get_contents($this->rdfFilePath);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/rdf+xml; charset=utf-8',
                ])
                ->withBody($rdfContent, 'application/rdf+xml')
                ->put($dataEndpoint . '?default');

            if ($response->successful()) {
                Log::info('RDF Generator: Successfully synchronized RDF data to Fuseki dataset.');
                return true;
            }

            Log::warning('RDF Generator: Fuseki upload returned non-success status.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::warning('RDF Generator: Could not connect to Fuseki server (' . $e->getMessage() . '). Falling back gracefully.');
            return false;
        }
    }

    /**
     * Get the absolute path of the generated RDF file.
     */
    public function getRdfFilePath(): string
    {
        return $this->rdfFilePath;
    }
}
