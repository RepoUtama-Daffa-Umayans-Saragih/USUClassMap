<?php

namespace Tests\Feature;

use App\Jobs\RegenerateRdfJob;
use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\RdfGeneratorService;
use App\Services\SparqlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase4SemanticWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Building $building;
    protected Room $room;
    protected Lecturer $lecturer;
    protected Course $course;
    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@usu.ac.id',
            'password' => bcrypt('Admin123!'),
            'role' => 'superadmin',
        ]);

        $this->building = Building::create([
            'name' => 'Gedung Kuliah Bersama 1',
            'code' => 'GK1',
            'latitude' => 3.56524500,
            'longitude' => 98.65652000,
            'description' => 'Gedung utama perkuliahan MKWK & MKWU',
            'floor_count' => 3,
        ]);

        $this->room = Room::create([
            'building_id' => $this->building->id,
            'name' => 'GK1-101',
            'floor' => 1,
            'capacity' => 50,
            'photo_360_path' => null,
        ]);

        $this->lecturer = Lecturer::create([
            'name' => 'Prof. Dr. H. M. Arif Nasution, M.A.',
            'nip' => '196504151990031002',
            'email' => 'arif.nasution@usu.ac.id',
            'phone' => '081260112233',
        ]);

        $this->course = Course::create([
            'code' => 'UNI101',
            'name' => 'Pendidikan Agama Islam',
            'credits' => 2,
            'type' => 'MKWU',
        ]);

        $this->schedule = Schedule::create([
            'course_id' => $this->course->id,
            'lecturer_id' => $this->lecturer->id,
            'room_id' => $this->room->id,
            'day' => 'Senin',
            'time_start' => '08:00',
            'time_end' => '09:40',
            'semester' => 'Ganjil 2024/2025',
            'class_group' => 'A',
        ]);
    }

    /**
     * Test 1: Verify OWL Ontology file exists and is valid XML
     */
    public function test_owl_ontology_file_exists_and_is_valid_xml(): void
    {
        $ontologyPath = config('sparql.ontology_storage_path', storage_path('app/ontology/usuclassmap.owl'));
        $this->assertFileExists($ontologyPath);

        $content = file_get_contents($ontologyPath);
        $this->assertStringContainsString('http://usuclassmap.usu.ac.id/ontology#', $content);
        $this->assertStringContainsString('ontology#Jadwal', $content);
        $this->assertStringContainsString('ontology#MataKuliah', $content);
        $this->assertStringContainsString('ontology#Dosen', $content);
        $this->assertStringContainsString('ontology#Ruangan', $content);
        $this->assertStringContainsString('ontology#Gedung', $content);
        $this->assertStringContainsString('ontology#hari', $content);
        $this->assertStringContainsString('ontology#jamMulai', $content);

        // Verify valid XML parsing
        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml);
    }

    /**
     * Test 2: Verify RDF Generator Service generates valid RDF/XML
     */
    public function test_rdf_generator_creates_valid_rdf_file(): void
    {
        $service = new RdfGeneratorService();
        $filePath = $service->generate();

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('http://usuclassmap.usu.ac.id/ontology#', $content);
        $this->assertStringContainsString('Pendidikan Agama Islam', $content);
        $this->assertStringContainsString('Prof. Dr. H. M. Arif Nasution', $content);
        $this->assertStringContainsString('GK1-101', $content);
        $this->assertStringContainsString('Gedung Kuliah Bersama 1', $content);
    }

    /**
     * Test 3: Verify Observer dispatches RegenerateRdfJob on model changes
     */
    public function test_schedule_model_changes_dispatch_regenerate_job(): void
    {
        Queue::fake();

        Schedule::create([
            'course_id' => $this->course->id,
            'lecturer_id' => $this->lecturer->id,
            'room_id' => $this->room->id,
            'day' => 'Selasa',
            'time_start' => '10:00',
            'time_end' => '11:40',
            'semester' => 'Ganjil 2024/2025',
            'class_group' => 'B',
        ]);

        Queue::assertPushed(RegenerateRdfJob::class);
    }

    /**
     * Test 4: Verify SPARQL service fallback when Fuseki is offline
     */
    public function test_sparql_service_fallback_to_mysql(): void
    {
        // Fake connection failure to Fuseki
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $sparqlService = new SparqlService();
        $results = $sparqlService->searchSchedules('Pendidikan Agama Islam', 'Senin');

        $this->assertEquals('success', $results['status']);
        $this->assertGreaterThan(0, $results['count']);
        $this->assertEquals('Pendidikan Agama Islam', $results['data'][0]['mata_kuliah']['nama']);
        $this->assertEquals('Senin', $results['data'][0]['hari']);
    }

    /**
     * Test 5: Verify SPARQL service when Fuseki responds with standard SPARQL JSON
     */
    public function test_sparql_service_with_mocked_fuseki_response(): void
    {
        $mockSparqlJson = [
            'head' => ['vars' => ['jadwal', 'hari', 'jamMulai', 'jamSelesai', 'semester', 'kelompokKelas', 'kodeMk', 'namaMk', 'sks', 'tipeMk', 'namaDosen', 'nipDosen', 'emailDosen', 'namaRuangan', 'lantaiRuangan', 'kapasitasRuangan', 'pathFoto360', 'namaGedung', 'kodeGedung', 'latitude', 'longitude']],
            'results' => [
                'bindings' => [
                    [
                        'jadwal' => ['type' => 'uri', 'value' => 'http://usuclassmap.usu.ac.id/resource/jadwal/1'],
                        'hari' => ['type' => 'literal', 'value' => 'Senin'],
                        'jamMulai' => ['type' => 'literal', 'value' => '08:00'],
                        'jamSelesai' => ['type' => 'literal', 'value' => '09:40'],
                        'semester' => ['type' => 'literal', 'value' => 'Ganjil 2024/2025'],
                        'kelompokKelas' => ['type' => 'literal', 'value' => 'A'],
                        'kodeMk' => ['type' => 'literal', 'value' => 'UNI101'],
                        'namaMk' => ['type' => 'literal', 'value' => 'Pendidikan Agama Islam'],
                        'sks' => ['type' => 'typed-literal', 'datatype' => 'http://www.w3.org/2001/XMLSchema#integer', 'value' => '2'],
                        'tipeMk' => ['type' => 'literal', 'value' => 'MKWU'],
                        'namaDosen' => ['type' => 'literal', 'value' => 'Prof. Dr. H. M. Arif Nasution, M.A.'],
                        'namaRuangan' => ['type' => 'literal', 'value' => 'GK1-101'],
                        'lantaiRuangan' => ['type' => 'typed-literal', 'datatype' => 'http://www.w3.org/2001/XMLSchema#integer', 'value' => '1'],
                        'kapasitasRuangan' => ['type' => 'typed-literal', 'datatype' => 'http://www.w3.org/2001/XMLSchema#integer', 'value' => '50'],
                        'namaGedung' => ['type' => 'literal', 'value' => 'Gedung Kuliah Bersama 1'],
                        'kodeGedung' => ['type' => 'literal', 'value' => 'GK1'],
                        'latitude' => ['type' => 'typed-literal', 'datatype' => 'http://www.w3.org/2001/XMLSchema#decimal', 'value' => '3.56524500'],
                        'longitude' => ['type' => 'typed-literal', 'datatype' => 'http://www.w3.org/2001/XMLSchema#decimal', 'value' => '98.65652000'],
                    ]
                ]
            ]
        ];

        Http::fake([
            '*/query' => Http::response($mockSparqlJson, 200),
        ]);

        $sparqlService = new SparqlService();
        $results = $sparqlService->searchSchedules('Agama', 'Senin');

        $this->assertEquals('success', $results['status']);
        $this->assertStringContainsString('SPARQL', $results['source']);
        $this->assertEquals(1, $results['count']);
        $this->assertEquals('UNI101', $results['data'][0]['mata_kuliah']['kode']);
        $this->assertEquals('GK1-101', $results['data'][0]['ruangan']['nama']);
        $this->assertEquals(3.56524500, $results['data'][0]['gedung']['latitude']);
    }

    /**
     * Test 6: Verify API /api/schedules/search returns JSON structure
     */
    public function test_api_schedules_search_endpoint(): void
    {
        $response = $this->getJson('/api/schedules/search?q=Islam&day=Senin');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'source',
            'count',
            'data' => [
                '*' => [
                    'uri',
                    'id',
                    'hari',
                    'jam_mulai',
                    'jam_selesai',
                    'semester',
                    'kelompok_kelas',
                    'mata_kuliah' => ['kode', 'nama', 'sks', 'tipe'],
                    'dosen' => ['nama', 'nip', 'email'],
                    'ruangan' => ['nama', 'lantai', 'kapasitas', 'foto_360'],
                    'gedung' => ['nama', 'kode', 'latitude', 'longitude'],
                ]
            ]
        ]);
    }

    /**
     * Test 7: Verify API /api/buildings returns JSON with GPS coordinates
     */
    public function test_api_buildings_endpoint(): void
    {
        $response = $this->getJson('/api/buildings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'code',
                    'latitude',
                    'longitude',
                    'description',
                    'floor_count',
                    'rooms_count',
                    'rooms' => [
                        '*' => ['id', 'name', 'floor', 'capacity', 'photo_360']
                    ]
                ]
            ]
        ]);

        $this->assertEquals(3.56524500, $response->json('data.0.latitude'));
        $this->assertEquals(98.65652000, $response->json('data.0.longitude'));
    }

    /**
     * Test 8: Verify artisan command rdf:generate runs smoothly
     */
    public function test_artisan_rdf_generate_command(): void
    {
        $this->artisan('rdf:generate')
            ->expectsOutputToContain('Generating RDF graph from MySQL database')
            ->expectsOutputToContain('RDF successfully generated')
            ->assertExitCode(0);
    }
}
