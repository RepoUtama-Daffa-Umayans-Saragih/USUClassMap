<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Foto360p\Photo360pScanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Photo360pInspectorTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmpDir;
    protected string $tmpMarkdown;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = storage_path('framework/testing/foto360p_' . uniqid());
        $this->tmpMarkdown = $this->tmpDir . DIRECTORY_SEPARATOR . 'DAFTAR.md';
        File::ensureDirectoryExists($this->tmpDir);

        config([
            'foto360p.storage_path'  => $this->tmpDir,
            'foto360p.markdown_path' => $this->tmpMarkdown,
        ]);

        $fixture = [
            'generated_at' => '2026-11-25T00:00:00+07:00',
            'total_images' => 3,
            'scan_engine'  => 'fixture_test_engine',
            'items'        => [
                [
                    'id'                => 1,
                    'filename'          => '1.jpg',
                    'original_filename' => 'IMG-TEST-0001.jpg',
                    'image_url'         => '/images/foto-360p/1.jpg',
                    'width'             => 1600,
                    'height'            => 269,
                    'size_kb'           => 80.5,
                    'projection'        => Photo360pScanRepository::projectionFor(1600, 269),
                    'status'            => 'detected',
                    'detected_rooms'    => [
                        [
                            'room_name'     => 'Ruang 104',
                            'raw_text'      => 'RUANG 104',
                            'verified_read' => 'RUANG104',
                            'category'      => 'ruang_kuliah',
                            'confidence'    => 'high',
                            'yaw'           => 120.0,
                            'pitch'         => 5.0,
                            'evidence_note' => 'Papan nama putih di atas pintu',
                            'source'        => 'ai',
                        ],
                    ],
                    'all_readable_texts' => ['RUANG 104'],
                    'scene_summary'      => 'Koridor ruang kuliah.',
                    'verified_by_admin'  => false,
                    'admin_note'         => '',
                ],
                [
                    'id'                => 2,
                    'filename'          => '2.jpg',
                    'original_filename' => 'IMG-TEST-0002.jpg',
                    'image_url'         => '/images/foto-360p/2.jpg',
                    'width'             => 1600,
                    'height'            => 240,
                    'size_kb'           => 70.0,
                    'projection'        => Photo360pScanRepository::projectionFor(1600, 240),
                    'status'            => 'no_text',
                    'detected_rooms'    => [],
                    'all_readable_texts' => [],
                    'scene_summary'      => 'Koridor kosong tanpa papan tulisan.',
                    'verified_by_admin'  => false,
                    'admin_note'         => '',
                ],
                [
                    'id'                => 3,
                    'filename'          => '3.jpg',
                    'original_filename' => 'IMG-TEST-0003.jpg',
                    'image_url'         => '/images/foto-360p/3.jpg',
                    'width'             => 900,
                    'height'            => 1600,
                    'size_kb'           => 92.0,
                    'projection'        => Photo360pScanRepository::projectionFor(900, 1600),
                    'status'            => 'blurry',
                    'detected_rooms'    => [],
                    'all_readable_texts' => [],
                    'scene_summary'      => 'Tulisan di dinding jauh buram.',
                    'verified_by_admin'  => false,
                    'admin_note'         => '',
                ],
            ],
        ];

        File::put(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'scan_results.json',
            json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        Http::fake();
    }

    protected function tearDown(): void
    {
        if (isset($this->tmpDir) && File::isDirectory($this->tmpDir)) {
            File::deleteDirectory($this->tmpDir);
        }

        parent::tearDown();
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    public function test_guest_and_non_admin_are_rejected_from_360p_inspector(): void
    {
        $guestResp = $this->get('/admin/cek-foto/360p');
        $guestResp->assertRedirect('/login');

        $regularUser = $this->makeUser('user');
        $userResp = $this->actingAs($regularUser)->get('/admin/cek-foto/360p');
        $this->assertTrue(in_array($userResp->getStatusCode(), [302, 403], true));

        $userPostResp = $this->actingAs($regularUser)->postJson('/admin/cek-foto/360p/1/verify', [
            'verified_by_admin' => true,
        ]);
        $userPostResp->assertStatus(403);
    }

    public function test_admin_and_superadmin_can_access_360p_inspector_without_inline_js_leak(): void
    {
        foreach (['admin_usuclassmap', 'superadmin'] as $role) {
            $admin = $this->makeUser($role);
            $resp = $this->actingAs($admin)->get('/admin/cek-foto/360p');
            $resp->assertStatus(200);

            $html = $resp->getContent();
            $this->assertStringContainsString('photo360pInspector', $html);
            $this->assertStringContainsString('Ruang 104', $html);
            $this->assertStringContainsString('pannellum', $html);
            $this->assertStringNotContainsString('x-data="{', $html);
        }
    }

    public function test_exports_return_valid_markdown_csv_and_json_and_reject_invalid_format(): void
    {
        $admin = $this->makeUser('admin_usuclassmap');

        $mdResp = $this->actingAs($admin)->get('/admin/cek-foto/360p/export/md');
        $mdResp->assertStatus(200);
        $this->assertStringContainsString('Ruang 104', $mdResp->streamedContent());

        $csvResp = $this->actingAs($admin)->get('/admin/cek-foto/360p/export/csv');
        $csvResp->assertStatus(200);
        $csvBody = $csvResp->streamedContent();
        $this->assertStringContainsString('room_name', $csvBody);
        $this->assertStringContainsString('Ruang 104', $csvBody);

        $jsonResp = $this->actingAs($admin)->get('/admin/cek-foto/360p/export/json');
        $jsonResp->assertStatus(200);
        $decoded = json_decode($jsonResp->streamedContent(), true);
        $this->assertIsArray($decoded);
        $this->assertSame(3, $decoded['total_images']);

        $badResp = $this->actingAs($admin)->get('/admin/cek-foto/360p/export/pdf');
        $badResp->assertStatus(404);
    }

    public function test_admin_can_verify_and_update_rooms_and_sync_isolated_markdown(): void
    {
        $admin = $this->makeUser('admin_usuclassmap');

        $resp = $this->actingAs($admin)->postJson('/admin/cek-foto/360p/1/verify', [
            'verified_by_admin' => true,
            'admin_note'        => 'Terverifikasi papan ruang kuliah dan lab',
            'status'            => 'detected',
            'rooms'             => [
                [
                    'room_name' => 'Ruang 104',
                    'raw_text'  => 'RUANG 104',
                    'category'  => 'ruang_kuliah',
                    'yaw'       => 120.0,
                    'pitch'     => 5.0,
                    'source'    => 'ai',
                ],
                [
                    'room_name' => 'Lab Komputer',
                    'raw_text'  => 'LAB KOMPUTER',
                    'category'  => 'laboratorium',
                    'yaw'       => -30.0,
                    'pitch'     => 2.0,
                    'source'    => 'admin',
                ],
            ],
        ]);

        $resp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('item.verified_by_admin', true);

        $saved = json_decode(File::get($this->tmpDir . DIRECTORY_SEPARATOR . 'scan_results.json'), true);
        $item1 = collect($saved['items'])->firstWhere('id', 1);
        $this->assertTrue($item1['verified_by_admin']);
        $this->assertCount(2, $item1['detected_rooms']);
        $this->assertArrayHasKey('x_pct', $item1['detected_rooms'][1]);
        $this->assertNotNull($item1['detected_rooms'][1]['x_pct']);

        $this->assertFileExists($this->tmpMarkdown);
        $this->assertStringContainsString('Lab Komputer', File::get($this->tmpMarkdown));
    }

    public function test_verify_returns_404_for_missing_id_and_422_for_invalid_payload(): void
    {
        $admin = $this->makeUser('admin_usuclassmap');

        $notFound = $this->actingAs($admin)->postJson('/admin/cek-foto/360p/999/verify', [
            'verified_by_admin' => true,
        ]);
        $notFound->assertStatus(404);

        $invalidPitch = $this->actingAs($admin)->postJson('/admin/cek-foto/360p/1/verify', [
            'verified_by_admin' => true,
            'rooms'             => [
                [
                    'room_name' => 'Ruang Bad',
                    'yaw'       => 10.0,
                    'pitch'     => 200.0,
                ],
            ],
        ]);
        $invalidPitch->assertStatus(422);
    }

    public function test_legacy_public_cek_foto_remains_accessible_and_isolated(): void
    {
        $legacyResp = $this->get('/admin/cek-foto');
        $legacyResp->assertStatus(200);
        $this->assertStringNotContainsString('/images/foto-360p/', $legacyResp->getContent());
    }
}
