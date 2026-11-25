<?php

namespace App\Services\Fotocopy;

use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Models\Service;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Support\Str;

class StoreMapSyncService
{
    public const DEFAULT_USU_LAT = 3.5622266;
    public const DEFAULT_USU_LNG = 98.6589566;

    /**
     * Sinkronkan 1 FotocopyStore (Admin) ke PhotocopyPlace (USU Maps & Direktori Publik).
     */
    public function syncStoreToPlace(
        FotocopyStore $store,
        ?string $oldName = null,
        ?string $oldSlug = null,
        bool $syncServices = false
    ): PhotocopyPlace {
        $existing = $this->resolvePlaceForStore($store, $oldName, $oldSlug);

        [$lat, $lng] = $this->sanitizeCoordinates(
            $store->latitude !== null ? (float) $store->latitude : null,
            $store->longitude !== null ? (float) $store->longitude : null,
            $existing?->latitude !== null ? (float) $existing->latitude : null,
            $existing?->longitude !== null ? (float) $existing->longitude : null,
        );

        if (
            $store->latitude === null
            || $store->longitude === null
            || abs((float) $store->latitude - $lat) > 0.000001
            || abs((float) $store->longitude - $lng) > 0.000001
        ) {
            $store->forceFill(['latitude' => $lat, 'longitude' => $lng])->saveQuietly();
        }

        $slug = trim((string) $store->slug);
        if ($slug === '') {
            $slug = Str::slug($store->name) . '-' . Str::random(5);
            $store->forceFill(['slug' => $slug])->saveQuietly();
        }

        [$openTime, $closeTime] = $this->parseOpeningHours($store->opening_hours);
        $waDigits = WhatsAppPhoneNormalizer::normalize($store->whatsapp ?: $store->phone);
        $phoneDisplay = $waDigits ? WhatsAppPhoneNormalizer::formatDisplay($waDigits) : ($store->phone ?: null);
        $phoneNational = $waDigits ? WhatsAppPhoneNormalizer::formatNational($waDigits) : ($store->phone ?: null);
        $mapsUrl = $store->google_maps_url ?: "https://maps.google.com/?q={$lat},{$lng}";

        $shouldResetEntrance = !$existing
            || $existing->entrance_lat === null
            || $existing->entrance_lng === null
            || $this->haversineMeters((float) $existing->latitude, (float) $existing->longitude, $lat, $lng) >= 20.0;

        $entranceLat = $shouldResetEntrance ? $lat : (float) $existing->entrance_lat;
        $entranceLng = $shouldResetEntrance ? $lng : (float) $existing->entrance_lng;

        $payload = [
            'name' => $store->name,
            'slug' => $slug,
            'description' => $store->description ?: $existing?->description,
            'location_name' => $store->location_name ?: ($existing?->location_name ?: 'Kampus USU Padang Bulan, Medan'),
            'latitude' => $lat,
            'longitude' => $lng,
            'entrance_lat' => $entranceLat,
            'entrance_lng' => $entranceLng,
            'google_maps_url' => $mapsUrl,
            'google_maps_uri' => $existing?->google_maps_uri ?: $mapsUrl,
            'google_place_id' => $store->google_place_id ?: $existing?->google_place_id,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'operating_days' => $store->operating_days ?: ($existing?->operating_days ?: 'Senin - Sabtu'),
            'phone' => $phoneDisplay,
            'phone_national' => $phoneNational,
            'phone_international' => $phoneDisplay,
            'whatsapp' => $waDigits ?? $store->whatsapp,
            'whatsapp_url' => $waDigits ? ('https://wa.me/' . $waDigits) : $existing?->whatsapp_url,
            'instagram' => $store->instagram ?? $existing?->instagram,
            'facebook' => $store->facebook ?? $existing?->facebook,
            'image_url' => $store->image_url,
            'rating' => $store->rating !== null ? (float) $store->rating : ($existing?->rating ?? 4.7),
            'user_rating_count' => $store->user_rating_count ?? ($existing?->user_rating_count ?? 1),
            'is_active' => (bool) $store->is_open,
            'business_status' => $store->is_open ? 'OPERATIONAL' : 'CLOSED_TEMPORARILY',
            'is_verified' => true,
            'last_synced_at' => now(),
        ];

        if ($existing) {
            $existing->update($payload);
            $place = $existing->fresh();
        } else {
            $place = PhotocopyPlace::create($payload);
        }

        if ($place->services()->count() === 0) {
            $coreIds = Service::whereIn('id', range(1, 10))->pluck('id')->all();
            foreach ($coreIds as $sid) {
                $place->services()->syncWithoutDetaching([
                    $sid => [
                        'starting_price' => max(100, (int) ($store->starting_price ?: 200)),
                        'unit' => 'lembar',
                        'notes' => 'Layanan standar mitra fotokopi USU',
                        'is_auto_detected' => true,
                    ],
                ]);
            }
        }

        if ($syncServices) {
            $this->syncServicesBetweenStoreAndPlace($store, $place);
        }

        return $place;
    }

    /**
     * Buat atau perbarui FotocopyStore dari PhotocopyPlace yang belum punya pasangan Admin.
     */
    public function syncPlaceToStore(PhotocopyPlace $place, bool $syncItems = false): FotocopyStore
    {
        $store = FotocopyStore::where('slug', $place->slug)->first()
            ?? FotocopyStore::where('name', $place->name)->first();

        [$lat, $lng] = $this->sanitizeCoordinates(
            $place->latitude !== null ? (float) $place->latitude : null,
            $place->longitude !== null ? (float) $place->longitude : null,
        );

        $slug = trim((string) $place->slug) ?: (Str::slug($place->name) . '-' . Str::random(5));
        $openTime = $this->normalizeSingleTime($place->open_time, '07:30');
        $closeTime = $this->normalizeSingleTime($place->close_time, '20:30');
        $waDigits = WhatsAppPhoneNormalizer::normalize($place->whatsapp ?: $place->phone);
        $minPrice = (int) (PhotocopyItem::where('photocopy_place_id', $place->id)
            ->where('is_available', true)
            ->min('price') ?? 500);

        $payload = [
            'name' => $place->name,
            'slug' => $slug,
            'location_name' => $place->location_name ?: 'Kampus USU Padang Bulan, Medan',
            'latitude' => $lat,
            'longitude' => $lng,
            'opening_hours' => "{$openTime} - {$closeTime}",
            'operating_days' => $place->operating_days ?: 'Senin - Sabtu',
            'phone' => $waDigits ? WhatsAppPhoneNormalizer::formatDisplay($waDigits) : $place->phone,
            'whatsapp' => $waDigits ?? $place->whatsapp,
            'image_url' => $place->image_url,
            'google_maps_url' => $place->google_maps_url ?: "https://maps.google.com/?q={$lat},{$lng}",
            'google_place_id' => $place->google_place_id,
            'instagram' => $place->instagram,
            'facebook' => $place->facebook,
            'description' => $place->description,
            'rating' => $place->rating !== null ? (float) $place->rating : 4.7,
            'user_rating_count' => $place->user_rating_count ?? 1,
            'is_open' => (bool) $place->is_active,
            'starting_price' => max(100, $minPrice),
        ];

        if ($store) {
            $store->update($payload);
            $store = $store->fresh();
        } else {
            $store = FotocopyStore::create($payload);
        }

        if ($syncItems) {
            $this->syncServicesBetweenStoreAndPlace($store, $place);
        }

        return $store;
    }

    /**
     * Sinkronkan katalog layanan dua arah antara FotocopyService (Admin) dan PhotocopyItem (Maps/Publik).
     *
     * @return array{items_synced: int, services_created: int}
     */
    public function syncServicesBetweenStoreAndPlace(FotocopyStore $store, PhotocopyPlace $place): array
    {
        $servicesByName = FotocopyService::where('store_id', $store->id)->get()->keyBy('name');
        $itemsByName = PhotocopyItem::where('photocopy_place_id', $place->id)->get()->keyBy('name');

        $itemsSynced = 0;
        $servicesCreated = 0;

        foreach ($servicesByName as $name => $srv) {
            $item = $itemsByName->get($name);
            $payload = [
                'photocopy_place_id' => $place->id,
                'name' => $srv->name,
                'category' => $srv->category,
                'item_type' => $srv->item_type ?? 'jasa',
                'sub_category' => $srv->sub_category,
                'brand' => $srv->brand,
                'pack_qty' => (int) ($srv->pack_qty ?? 1),
                'price' => (int) $srv->price,
                'unit' => $srv->unit,
                'description' => $srv->description,
                'image_path' => $srv->image_path,
                'metadata' => $srv->metadata,
                'stock' => $srv->stock,
                'is_available' => (bool) $srv->is_available,
            ];

            if ($item) {
                $item->update($payload);
            } else {
                PhotocopyItem::create($payload);
            }
            $itemsSynced++;
        }

        foreach ($itemsByName as $name => $itm) {
            if ($servicesByName->has($name)) {
                continue;
            }
            FotocopyService::create([
                'store_id' => $store->id,
                'name' => $itm->name,
                'category' => $itm->category ?: 'Print',
                'item_type' => $itm->item_type ?? 'jasa',
                'sub_category' => $itm->sub_category,
                'brand' => $itm->brand,
                'pack_qty' => (int) ($itm->pack_qty ?? 1),
                'price' => (int) $itm->price,
                'unit' => $itm->unit ?: 'pcs',
                'description' => $itm->description,
                'image_path' => $itm->image_path,
                'metadata' => $itm->metadata,
                'stock' => $itm->stock,
                'is_available' => (bool) $itm->is_available,
            ]);
            $servicesCreated++;
        }

        $minAvailable = FotocopyService::where('store_id', $store->id)
            ->where('is_available', true)
            ->min('price');
        if ($minAvailable !== null && (int) $store->starting_price !== (int) $minAvailable) {
            $store->forceFill(['starting_price' => (int) $minAvailable])->saveQuietly();
        }

        return [
            'items_synced' => $itemsSynced,
            'services_created' => $servicesCreated,
        ];
    }

    /**
     * Sinkronkan 1 item FotocopyService ke PhotocopyItem milik PhotocopyPlace terkait.
     */
    public function syncServiceToItem(FotocopyService $service, ?int $oldStoreId = null, ?string $oldName = null): ?PhotocopyItem
    {
        $store = $service->relationLoaded('store')
            ? $service->store
            : FotocopyStore::find($service->store_id);

        if (!$store) {
            return null;
        }

        $place = $this->syncStoreToPlace($store);
        $oldPlace = null;
        if ($oldStoreId && $oldStoreId !== (int) $store->id) {
            $oldStore = FotocopyStore::find($oldStoreId);
            if ($oldStore) {
                $oldPlace = $this->resolvePlaceForStore($oldStore);
            }
        }

        $item = null;
        if ($oldName) {
            $item = PhotocopyItem::where('photocopy_place_id', ($oldPlace ?? $place)->id)
                ->where('name', $oldName)
                ->first();
        }
        if (!$item) {
            $item = PhotocopyItem::where('photocopy_place_id', $place->id)
                ->where('name', $service->name)
                ->first();
        }

        $payload = [
            'photocopy_place_id' => $place->id,
            'name' => $service->name,
            'category' => $service->category,
            'item_type' => $service->item_type ?? 'jasa',
            'sub_category' => $service->sub_category,
            'brand' => $service->brand,
            'pack_qty' => (int) ($service->pack_qty ?? 1),
            'price' => (int) $service->price,
            'unit' => $service->unit,
            'description' => $service->description,
            'image_path' => $service->image_path,
            'metadata' => $service->metadata,
            'stock' => $service->stock,
            'is_available' => (bool) $service->is_available,
        ];

        if ($item) {
            $item->update($payload);
        } else {
            $item = PhotocopyItem::create($payload);
        }

        $minAvailable = FotocopyService::where('store_id', $store->id)
            ->where('is_available', true)
            ->min('price');
        if ($minAvailable !== null && (int) $store->starting_price !== (int) $minAvailable) {
            $store->forceFill(['starting_price' => (int) $minAvailable])->saveQuietly();
        }

        return $item;
    }

    public function deletePlaceForStore(FotocopyStore $store): bool
    {
        $place = $this->resolvePlaceForStore($store);
        if (!$place) {
            return false;
        }
        return (bool) $place->delete();
    }

    public function deleteItemForService(FotocopyService $service): bool
    {
        $store = FotocopyStore::find($service->store_id);
        $place = $store ? $this->resolvePlaceForStore($store) : null;
        if (!$place) {
            return false;
        }
        return PhotocopyItem::where('photocopy_place_id', $place->id)
            ->where('name', $service->name)
            ->delete() > 0;
    }

    /**
     * Sinkronisasi massal idempoten 1:1 seluruh fotocopy_stores <-> photocopy_places beserta layanan.
     *
     * @return array<string, int>
     */
    public function syncAll(): array
    {
        $createdPlaces = 0;
        $updatedPlaces = 0;
        $createdStores = 0;
        $itemsSynced = 0;
        $servicesCreated = 0;

        $stores = FotocopyStore::orderBy('id')->get();
        foreach ($stores as $store) {
            $existedBefore = $this->resolvePlaceForStore($store) !== null;
            $place = $this->syncStoreToPlace($store, null, null, false);
            if ($existedBefore) {
                $updatedPlaces++;
            } else {
                $createdPlaces++;
            }
            $svcRes = $this->syncServicesBetweenStoreAndPlace($store, $place);
            $itemsSynced += $svcRes['items_synced'];
            $servicesCreated += $svcRes['services_created'];
        }

        $storeSlugs = FotocopyStore::pluck('slug')->filter()->all();
        $storeNames = FotocopyStore::pluck('name')->filter()->all();
        $orphanPlaces = PhotocopyPlace::whereNotIn('slug', $storeSlugs)
            ->whereNotIn('name', $storeNames)
            ->orderBy('id')
            ->get();

        foreach ($orphanPlaces as $place) {
            $store = $this->syncPlaceToStore($place, true);
            $createdStores++;
            $svcRes = $this->syncServicesBetweenStoreAndPlace($store, $place);
            $itemsSynced += $svcRes['items_synced'];
            $servicesCreated += $svcRes['services_created'];
        }

        return [
            'stores_count' => FotocopyStore::count(),
            'places_count' => PhotocopyPlace::count(),
            'services_count' => FotocopyService::count(),
            'items_count' => PhotocopyItem::count(),
            'created_places' => $createdPlaces,
            'updated_places' => $updatedPlaces,
            'created_stores' => $createdStores,
            'items_synced' => $itemsSynced,
            'services_created' => $servicesCreated,
        ];
    }

    public function resolvePlaceForStore(
        FotocopyStore $store,
        ?string $oldName = null,
        ?string $oldSlug = null
    ): ?PhotocopyPlace {
        if (!empty($store->slug)) {
            $bySlug = PhotocopyPlace::where('slug', $store->slug)->first();
            if ($bySlug) {
                return $bySlug;
            }
        }
        if ($oldSlug) {
            $byOldSlug = PhotocopyPlace::where('slug', $oldSlug)->first();
            if ($byOldSlug) {
                return $byOldSlug;
            }
        }
        if (!empty($store->name)) {
            $byName = PhotocopyPlace::where('name', $store->name)->first();
            if ($byName) {
                return $byName;
            }
        }
        if ($oldName) {
            $byOldName = PhotocopyPlace::where('name', $oldName)->first();
            if ($byOldName) {
                return $byOldName;
            }
        }
        if (!empty($store->google_place_id)) {
            return PhotocopyPlace::where('google_place_id', $store->google_place_id)->first();
        }

        return null;
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function sanitizeCoordinates(
        ?float $lat,
        ?float $lng,
        ?float $fallbackLat = null,
        ?float $fallbackLng = null
    ): array {
        if ($this->isValidCoordPair($lat, $lng)) {
            return [(float) $lat, (float) $lng];
        }
        if ($this->isValidCoordPair($fallbackLat, $fallbackLng)) {
            return [(float) $fallbackLat, (float) $fallbackLng];
        }

        return [self::DEFAULT_USU_LAT, self::DEFAULT_USU_LNG];
    }

    protected function isValidCoordPair(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
            return false;
        }
        if (abs($lat) < 0.001 && abs($lng) < 0.001) {
            return false;
        }
        return $lat >= -12.0 && $lat <= 8.0 && $lng >= 94.0 && $lng <= 142.0;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseOpeningHours(?string $openingHours): array
    {
        $raw = str_replace('.', ':', trim((string) ($openingHours ?? '')));
        if (preg_match('/(\d{1,2}:\d{2})\s*[\-\x{2013}\x{2014}]\s*(\d{1,2}:\d{2})/u', $raw, $m)) {
            return [
                $this->normalizeSingleTime($m[1], '07:30'),
                $this->normalizeSingleTime($m[2], '20:30'),
            ];
        }

        return ['07:30', '20:30'];
    }

    protected function normalizeSingleTime(?string $raw, string $fallback): string
    {
        $clean = str_replace('.', ':', trim((string) $raw));
        if (preg_match('/^(\d{1,2}):(\d{2})/', $clean, $m)) {
            $h = max(0, min(23, (int) $m[1]));
            $min = max(0, min(59, (int) $m[2]));
            return sprintf('%02d:%02d', $h, $min);
        }

        return $fallback;
    }

    protected function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
