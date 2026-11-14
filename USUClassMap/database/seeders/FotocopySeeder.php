<?php

namespace Database\Seeders;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FotocopySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Akun Admin Fotokopi (Bisa login dengan 'adminfotokopi' atau 'adminfotokopi@usu.ac.id')
        $adminFotokopi = User::updateOrCreate(
            ['email' => 'adminfotokopi@usu.ac.id'],
            [
                'name' => 'Admin Fotokopi USU',
                'nim' => 'adminfotokopi',
                'semester' => 'Staff',
                'password' => Hash::make('adminusu123'),
                'role' => 'admin_fotokopi',
            ]
        );

        // 2. Seed Akun Cashier Fotokopi (Bisa login dengan 'cashierfotokopi' atau 'cashierfotokopi@usu.ac.id')
        $cashierFotokopi = User::updateOrCreate(
            ['email' => 'cashierfotokopi@usu.ac.id'],
            [
                'name' => 'Kasir Berkah Abadi',
                'nim' => 'cashierfotokopi',
                'semester' => 'Staff',
                'password' => Hash::make('adminusu123'),
                'role' => 'cashier_fotokopi',
            ]
        );

        // 3. Load all 31 places from usu_photocopy_data.json
        $jsonPath = database_path('seeders/data/usu_photocopy_data.json');
        if (!file_exists($jsonPath)) {
            return;
        }

        $storesData = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($storesData)) {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF;');

        // Temporarily randomize existing slugs to prevent unique constraint conflict during re-mapping
        if (DB::table('fotocopy_stores')->count() > 0) {
            DB::table('fotocopy_stores')->update([
                'slug' => DB::raw("'temp-' || id || '-' || hex(randomblob(4))")
            ]);
        }

        foreach ($storesData as $data) {
            $id = $data['id'] ?? null;
            $slug = $data['slug'] ?? Str::slug($data['name']);

            $startingPrice = 200;
            if (!empty($data['catalog_items']) && is_array($data['catalog_items'])) {
                $prices = array_column($data['catalog_items'], 'price');
                if (!empty($prices)) {
                    $startingPrice = min($prices);
                }
            }

            $openHour = $data['open_time'] ?? '08:00';
            $closeHour = $data['close_time'] ?? '21:00';
            $openingHoursStr = "{$openHour} - {$closeHour}";

            // Assign cashier to Store 5 (Berkah Abadi)
            $isBerkah = ($id == 5) || str_contains($data['name'], 'Berkah Abadi');
            $storeUserId = $isBerkah ? $cashierFotokopi->id : null;

            $attrs = [
                'id' => $id,
                'user_id' => $storeUserId,
                'name' => $data['name'],
                'slug' => $slug,
                'location_name' => $data['location_name'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'opening_hours' => $openingHoursStr,
                'operating_days' => $data['operating_days'] ?? 'Senin - Sabtu',
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'google_maps_url' => $data['google_maps_url'] ?? null,
                'google_place_id' => $data['google_place_id'] ?? null,
                'description' => $data['description'] ?? null,
                'rating' => $data['rating'] ?? 4.8,
                'user_rating_count' => $data['user_rating_count'] ?? 50,
                'is_open' => true,
                'starting_price' => $startingPrice,
                'updated_at' => now(),
            ];

            $existing = DB::table('fotocopy_stores')->where('id', $id)->first();
            if ($existing) {
                DB::table('fotocopy_stores')->where('id', $id)->update($attrs);
            } else {
                $attrs['created_at'] = now();
                DB::table('fotocopy_stores')->insert($attrs);
            }

            // Insert or update services
            if (!empty($data['catalog_items']) && is_array($data['catalog_items'])) {
                foreach ($data['catalog_items'] as $item) {
                    FotocopyService::updateOrCreate(
                        [
                            'store_id' => $id,
                            'name' => $item['name'],
                        ],
                        [
                            'category' => $item['category'] ?? 'Fotocopy',
                            'price' => $item['price'] ?? 200,
                            'unit' => $item['unit'] ?? 'lembar',
                            'description' => $item['description'] ?? null,
                            'is_available' => $item['is_available'] ?? true,
                        ]
                    );
                }
            }
        }

        DB::statement('PRAGMA foreign_keys = ON;');

        // 4. Seed sample orders on Berkah Abadi (Store ID 5) for cashier & reports testing
        $berkahStore = FotocopyStore::find(5) ?? FotocopyStore::where('name', 'like', '%Berkah Abadi%')->first();
        if ($berkahStore) {
            $order1 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0001'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Budi Santoso (NIM 211402011)',
                    'customer_phone' => '081234567890',
                    'notes' => 'Tolong jilid spiral warna biru ya mas, selesaikan sebelum jam 12:00.',
                    'file_name' => 'Makalah_Studi_Kasus_MKWU_Kelompok4.pdf',
                    'file_size' => '2.4 MB',
                    'file_type' => 'pdf',
                    'file_path' => 'fotocopy_docs/sample_makalah_mkwu.pdf',
                    'payment_method' => 'QRIS',
                    'payment_status' => 'paid',
                    'order_status' => 'Menunggu',
                    'total_price' => 35000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Print Tugas A4 B/W'],
                [
                    'category' => 'Print',
                    'price' => 200,
                    'quantity' => 50,
                    'subtotal' => 10000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Print Warna A4'],
                [
                    'category' => 'Print',
                    'price' => 800,
                    'quantity' => 20,
                    'subtotal' => 16000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Jilid Spiral Kawat A4'],
                [
                    'category' => 'Jilid',
                    'price' => 10000,
                    'quantity' => 1,
                    'subtotal' => 10000,
                ]
            );

            $order2 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0002'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Siti Nurhaliza (NIM 221401045)',
                    'customer_phone' => '082199887766',
                    'notes' => 'Fotocopy modul kuliah halaman 1-40, staples sudut kiri atas saja.',
                    'file_name' => 'Modul_Praktikum_Jaringan_Komputer.pdf',
                    'file_size' => '1.8 MB',
                    'file_type' => 'pdf',
                    'file_path' => 'fotocopy_docs/sample_modul_jaringan.pdf',
                    'payment_method' => 'Tunai',
                    'payment_status' => 'pending',
                    'order_status' => 'Diproses',
                    'total_price' => 7000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order2->id, 'service_name' => 'Fotocopy A4/F4'],
                [
                    'category' => 'Fotocopy',
                    'price' => 175,
                    'quantity' => 40,
                    'subtotal' => 7000,
                ]
            );

            $order3 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0003'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Rizky Pratama (NIM 201402099)',
                    'customer_phone' => '085311224455',
                    'notes' => 'Ambil jam 14:00 WIB, pulpen tinta hitam 2 pcs.',
                    'payment_method' => 'Transfer',
                    'payment_status' => 'paid',
                    'order_status' => 'Selesai',
                    'total_price' => 27000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order3->id, 'service_name' => 'Print Warna A4'],
                [
                    'category' => 'Print',
                    'price' => 800,
                    'quantity' => 25,
                    'subtotal' => 20000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order3->id, 'service_name' => 'Pulpen Gel Standard / Faster'],
                [
                    'category' => 'ATK',
                    'price' => 3500,
                    'quantity' => 2,
                    'subtotal' => 7000,
                ]
            );
        }

        // 5. Also seed PhotocopyPlace & PhotocopyItem to maintain 100% database parity
        (new PhotocopySeeder())->run();
    }
}
