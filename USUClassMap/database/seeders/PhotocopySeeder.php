<?php

namespace Database\Seeders;

use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PhotocopySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/usu_photocopy_data.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("File {$jsonPath} tidak ditemukan!");
            return;
        }

        $placesData = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($placesData)) {
            $this->command->error("Format JSON pada {$jsonPath} tidak valid!");
            return;
        }

        $allServices = Service::all()->keyBy('name');

        foreach ($placesData as $data) {
            $slug = Str::slug($data['name']);

            // Parse clean digits for phone formats
            $cleanDigits = preg_replace('/[^\d]/', '', $data['phone'] ?? '');
            $phoneNat = $data['phone'] ?? '';
            $phoneInt = $cleanDigits;
            if (str_starts_with($phoneInt, '0')) {
                $phoneInt = '+62 ' . substr($phoneInt, 1);
            } elseif (str_starts_with($phoneInt, '62')) {
                $phoneInt = '+' . $phoneInt;
            }

            $waDigits = preg_replace('/[^\d]/', '', $data['whatsapp'] ?? $cleanDigits);
            if (str_starts_with($waDigits, '08')) {
                $waDigits = '62' . substr($waDigits, 1);
            }
            $whatsappUrl = 'https://wa.me/' . $waDigits;

            $place = PhotocopyPlace::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? 'Layanan fotokopi & percetakan di sekitar kampus Universitas Sumatera Utara.',
                    'location_name' => $data['location_name'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'open_time' => $data['open_time'] ?? '08:00',
                    'close_time' => $data['close_time'] ?? '21:00',
                    'operating_days' => $data['operating_days'] ?? 'Senin - Sabtu',
                    'phone' => $data['phone'],
                    'phone_national' => $phoneNat,
                    'phone_international' => $phoneInt,
                    'whatsapp' => $waDigits,
                    'whatsapp_url' => $whatsappUrl,
                    'image_url' => $data['image_url'] ?? null,
                    'rating' => $data['rating'] ?? 4.8,
                    'user_rating_count' => $data['user_rating_count'] ?? 50,
                    'google_maps_url' => $data['google_maps_url'] ?? "https://maps.google.com/?q=" . urlencode($data['name'] . " USU Medan"),
                    'google_maps_uri' => $data['google_maps_url'] ?? "https://maps.google.com/?q=" . urlencode($data['name'] . " USU Medan"),
                    'business_status' => 'OPERATIONAL',
                    'is_verified' => true,
                    'is_manually_verified' => true,
                    'is_active' => true,
                ]
            );

            // Seed tailored catalog items
            if (!empty($data['catalog_items']) && is_array($data['catalog_items'])) {
                // Hapus item lama agar sesuai dengan katalog kustom terbaru
                PhotocopyItem::where('photocopy_place_id', $place->id)->delete();

                foreach ($data['catalog_items'] as $item) {
                    PhotocopyItem::create([
                        'photocopy_place_id' => $place->id,
                        'name' => $item['name'],
                        'category' => $item['category'] ?? 'Lainnya',
                        'price' => $item['price'] ?? 1000,
                        'unit' => $item['unit'] ?? 'lembar',
                        'description' => $item['description'] ?? null,
                        'is_available' => true,
                    ]);
                }
            }

            // Hubungkan dengan master services jika ada
            if (!empty($data['services']) && is_array($data['services'])) {
                $serviceSync = [];
                foreach ($data['services'] as $srvName) {
                    // Cari service yang cocok atau create baru
                    $matchedService = $allServices->first(function ($s) use ($srvName) {
                        return str_contains(strtolower($srvName), strtolower($s->name))
                            || str_contains(strtolower($s->name), strtolower($srvName));
                    });

                    if (!$matchedService) {
                        $matchedService = Service::firstOrCreate(
                            ['name' => $srvName],
                            [
                                'slug' => Str::slug($srvName),
                                'category' => 'Percetakan & Layanan',
                                'icon' => 'fa-solid fa-print',
                                'description' => 'Layanan ' . $srvName . ' di mitra percetakan USU.'
                            ]
                        );
                        $allServices->put($srvName, $matchedService);
                    }

                    $serviceSync[$matchedService->id] = [
                        'starting_price' => 500,
                        'unit' => 'lembar',
                        'is_auto_detected' => false,
                        'notes' => 'Tersedia di ' . $data['name'],
                    ];
                }

                if (!empty($serviceSync)) {
                    $place->services()->syncWithoutDetaching($serviceSync);
                }
            }
        }
    }
}
