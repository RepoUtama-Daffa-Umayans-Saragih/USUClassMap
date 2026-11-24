<?php

namespace App\Services\Search;

use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use Illuminate\Support\Collection;

/**
 * Engine 2: SmartPhotocopySearchService
 *
 * Searches across all 32 photocopy stores and 2,689 catalog items using:
 * - Multi-field weighted matching (name, category, sub_category, brand, description, store)
 * - Honest relaxed-attribute fallback when exact color/variant is absent in the catalog
 * - Eceran vs bulk unit separation for "murah/cheapest" requests
 * - Multi-factor ranking (relevance, price, real Haversine distance + walk ETA, open status, rating)
 * - Strictly in-app /fotocopy/{id}/order & /map?route_to=fotocopy:{id} actions (zero Google Maps redirects)
 */
class SmartPhotocopySearchService
{
    public const USU_CENTER_LAT = 3.5612;
    public const USU_CENTER_LNG = 98.6565;

    public function __construct(
        protected QueryIntentParserService $intentParser
    ) {
    }

    /**
     * Execute smart photocopy & ATK search across all active stores and items.
     *
     * @param string $query
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function search(string $query, array $options = []): array
    {
        $parsed = $options['parsed'] ?? $this->intentParser->parse($query);

        $sort = $this->normalizeSort($options['sort'] ?? null);
        $openNowFilter = (bool) ($options['open_now'] ?? $parsed['open_now'] ?? false);
        $maxPrice = isset($options['max_price']) && is_numeric($options['max_price'])
            ? (int) $options['max_price']
            : ($parsed['max_price'] ?? null);
        $limit = max(1, min(40, (int) ($options['limit'] ?? 15)));

        $bulkRequested = (bool) ($parsed['bulk_requested'] ?? false);
        $pricePreference = $parsed['price_preference'] ?? null;
        $proximityPreference = $parsed['proximity_preference'] ?? null;
        $preferCheapest = ($sort === 'cheapest') || ($pricePreference === 'cheapest' && $sort === 'recommended');
        $preferNearest = ($sort === 'nearest') || ($proximityPreference === 'nearest');

        // 1. Determine location anchor (Explicit Landmark > User GPS > USU Campus Center)
        [$anchorLat, $anchorLng, $anchorSource, $anchorLabel] = $this->resolveAnchor($parsed, $options);

        $itemTerms = $parsed['item_terms'] ?? [];
        $attributes = $parsed['attributes'] ?? [];
        $allSearchTokens = array_values(array_unique(array_merge($itemTerms, $attributes, $parsed['tokens'] ?? [])));

        // Remove modifier/anchor/noise words from core lexical matching
        $noise = ['murah', 'termurah', 'hemat', 'terjangkau', 'terdekat', 'dekat', 'buka', 'sekarang', 'di', 'bawah', 'maks', 'maksimal', 'fotocopy', 'toko', 'atk', 'alat', 'tulis'];
        if (!empty($parsed['anchor_landmark'])) {
            $noise[] = strtolower((string) $parsed['anchor_landmark']['code']);
            foreach (explode('-', strtolower((string) $parsed['anchor_landmark']['code'])) as $part) {
                $noise[] = $part;
            }
        }
        $coreQueryTokens = array_values(array_diff($itemTerms, ['fotocopy', 'atk']));
        $lexicalTokens = array_values(array_diff($allSearchTokens, $noise));

        // 2. Load active catalog items & stores
        $items = PhotocopyItem::query()
            ->with('place')
            ->where('is_available', true)
            ->whereHas('place', fn ($q) => $q->where('is_active', true))
            ->get();

        // Cache store open status & distances once per store ID
        $placeMetrics = [];
        foreach ($items as $item) {
            $place = $item->place;
            if (!$place) {
                continue;
            }
            if (!isset($placeMetrics[$place->id])) {
                $placeMetrics[$place->id] = $this->computePlaceMetrics($place, $anchorLat, $anchorLng);
            }
        }

        // Also check active places without items just in case (or shop-only query)
        $allActivePlaces = PhotocopyPlace::query()->where('is_active', true)->get();
        foreach ($allActivePlaces as $p) {
            if (!isset($placeMetrics[$p->id])) {
                $placeMetrics[$p->id] = $this->computePlaceMetrics($p, $anchorLat, $anchorLng);
            }
        }

        // 3. Evaluate items
        $exactMatches = [];
        $relaxedMatches = [];

        $isStoreCentricQuery = empty($coreQueryTokens) && empty($attributes);

        if (!$isStoreCentricQuery) {
            $matchTokens = !empty($coreQueryTokens) ? $coreQueryTokens : $lexicalTokens;

            foreach ($items as $item) {
                $place = $item->place;
                if (!$place) {
                    continue;
                }
                $metrics = $placeMetrics[$place->id];
                if ($openNowFilter && !$metrics['is_open_now']) {
                    continue;
                }
                if ($maxPrice !== null && (int) $item->price > $maxPrice) {
                    continue;
                }

                $eval = $this->evaluateItemMatch($item, $place, $matchTokens, $attributes);
                if (!$eval['matched']) {
                    continue;
                }

                $entry = [
                    'item'                => $item,
                    'place'               => $place,
                    'metrics'             => $metrics,
                    'relevance_score'     => $eval['score'],
                    'exact_attribute'     => $eval['exact_attribute'],
                    'matched_attributes'  => $eval['matched_attributes'],
                    'missing_attributes'  => $eval['missing_attributes'],
                    'is_bulk'             => $this->isBulkItem($item),
                ];

                if ($eval['exact_attribute']) {
                    $exactMatches[] = $entry;
                } else {
                    $relaxedMatches[] = $entry;
                }
            }
        }

        // Decide exact vs honest relaxed fallback
        $relaxedFallbackUsed = false;
        $missingAttributes = [];
        $advisoryNote = null;

        if (!empty($exactMatches)) {
            $pool = $exactMatches;
        } elseif (!empty($relaxedMatches)) {
            $pool = $relaxedMatches;
            $relaxedFallbackUsed = !empty($attributes);
            $missingAttributes = $this->computeMissingAttributes($attributes, $relaxedMatches);

            if (!empty($missingAttributes)) {
                $itemLabel = !empty($coreQueryTokens) ? implode(' ', $coreQueryTokens) : 'produk ini';
                $attrLabel = implode(', ', $missingAttributes);
                $advisoryNote = "Belum ada {$itemLabel} dengan spesifikasi/warna \"{$attrLabel}\" tercatat di katalog 32 mitra fotokopi USU. Menampilkan alternatif {$itemLabel} terbaik, termurah & terdekat yang tersedia — kamu bisa konfirmasi ketersediaan {$attrLabel} langsung ke toko sebelum memesan.";
            }
        } else {
            // Store-centric search (e.g. "fotocopy terdekat", "fotocopy buka sekarang", "toko sekitar FIB", or shop name)
            $pool = $this->buildStoreRepresentativePool($allActivePlaces, $items, $placeMetrics, $query, $lexicalTokens, $openNowFilter, $maxPrice);
            if ($maxPrice !== null && empty($pool)) {
                $advisoryNote = "Tidak ditemukan produk atau jasa dengan harga maksimal Rp " . number_format($maxPrice, 0, ',', '.') . " untuk kata kunci tersebut.";
            } elseif (empty($pool) && $openNowFilter) {
                $advisoryNote = "Tidak ada toko yang sedang buka untuk kriteria tersebut saat ini.";
            }
        }

        // 4. Rank candidates using sort mode + price/distance/open/rating intelligence
        $ranked = $this->rankCandidates(
            $pool,
            $sort,
            $preferCheapest,
            $preferNearest,
            $bulkRequested,
            $this->isSpecificShopQuery($query, $allActivePlaces)
        );

        $slice = array_slice($ranked, 0, $limit);
        $formattedResults = array_map(
            fn (array $row): array => $this->formatResultItem($row, $anchorLabel),
            $slice
        );

        // Unique stores summary from ranked pool
        $storesSummary = $this->summarizeStores($ranked, $limit);

        return [
            'status'                => 'success',
            'query'                 => $query,
            'normalized_intent'     => $parsed,
            'anchor'                => [
                'lat'    => $anchorLat,
                'lng'    => $anchorLng,
                'source' => $anchorSource,
                'label'  => $anchorLabel,
            ],
            'sort'                  => $sort,
            'exact_match_count'     => count($exactMatches),
            'relaxed_fallback_used' => $relaxedFallbackUsed,
            'missing_attributes'    => $missingAttributes,
            'advisory_note'         => $advisoryNote,
            'total_candidates'      => count($ranked),
            'count'                 => count($formattedResults),
            'results'               => $formattedResults,
            'stores'                => $storesSummary,
        ];
    }

    /**
     * Resolve anchor coordinates: Explicit Landmark > GPS > USU Campus Center.
     *
     * @param array<string, mixed> $parsed
     * @param array<string, mixed> $options
     * @return array{0: float, 1: float, 2: string, 3: string}
     */
    protected function resolveAnchor(array $parsed, array $options): array
    {
        if (!empty($parsed['anchor_landmark'])) {
            $lm = $parsed['anchor_landmark'];
            $lat = (float) ($lm['entrance_lat'] ?? $lm['latitude']);
            $lng = (float) ($lm['entrance_lng'] ?? $lm['longitude']);
            if (is_finite($lat) && is_finite($lng) && abs($lat) > 0.1) {
                return [$lat, $lng, 'landmark', ($lm['name'] ?? $lm['code'])];
            }
        }

        $optLat = isset($options['lat']) && is_numeric($options['lat']) ? (float) $options['lat'] : null;
        $optLng = isset($options['lng']) && is_numeric($options['lng']) ? (float) $options['lng'] : null;

        if ($optLat !== null && $optLng !== null && $optLat >= -90 && $optLat <= 90 && $optLng >= -180 && $optLng <= 180) {
            return [$optLat, $optLng, 'gps', 'Lokasi GPS Anda'];
        }

        return [self::USU_CENTER_LAT, self::USU_CENTER_LNG, 'campus_center', 'Pusat Kampus USU'];
    }

    /**
     * Compute distance & schedule metrics once per PhotocopyPlace.
     *
     * @return array<string, mixed>
     */
    protected function computePlaceMetrics(PhotocopyPlace $place, float $anchorLat, float $anchorLng): array
    {
        $pLat = (float) ($place->entrance_lat ?: $place->latitude ?: self::USU_CENTER_LAT);
        $pLng = (float) ($place->entrance_lng ?: $place->longitude ?: self::USU_CENTER_LNG);

        $distanceMeters = (int) round($this->haversine($anchorLat, $anchorLng, $pLat, $pLng));
        $walkMinutes = max(1, (int) ceil($distanceMeters / 75));

        return [
            'lat'             => $pLat,
            'lng'             => $pLng,
            'distance_meters' => $distanceMeters,
            'walk_minutes'    => $walkMinutes,
            'is_open_now'     => $place->isOpenNow(),
            'schedule_status' => $place->current_schedule_status,
            'rating'          => (float) ($place->rating ?: 4.5),
            'rating_count'    => (int) ($place->user_rating_count ?: 0),
        ];
    }

    /**
     * Evaluate how well an item matches core product tokens & requested attributes.
     *
     * @param array<int, string> $coreTokens
     * @param array<int, string> $attributes
     * @return array{matched: bool, score: float, exact_attribute: bool, matched_attributes: array<int, string>, missing_attributes: array<int, string>}
     */
    protected function evaluateItemMatch(PhotocopyItem $item, PhotocopyPlace $place, array $coreTokens, array $attributes): array
    {
        $nameLower = mb_strtolower((string) $item->name, 'UTF-8');
        $catLower = mb_strtolower((string) $item->category, 'UTF-8');
        $subCatLower = mb_strtolower((string) ($item->sub_category ?? ''), 'UTF-8');
        $brandLower = mb_strtolower((string) ($item->brand ?? ''), 'UTF-8');
        $descLower = mb_strtolower((string) ($item->description ?? ''), 'UTF-8');
        $placeLower = mb_strtolower((string) ($place->name . ' ' . $place->location_name), 'UTF-8');

        $itemHaystack = "{$nameLower} {$catLower} {$subCatLower} {$brandLower} {$descLower}";
        $fullHaystack = "{$itemHaystack} {$placeLower}";

        $score = 0.0;
        $coreHits = 0;

        foreach ($coreTokens as $tok) {
            $hit = false;
            if (str_contains($nameLower, $tok)) {
                $score += 35.0;
                $hit = true;
            } elseif (str_contains($catLower, $tok) || str_contains($subCatLower, $tok) || str_contains($brandLower, $tok)) {
                $score += 24.0;
                $hit = true;
            } elseif (str_contains($descLower, $tok)) {
                $score += 14.0;
                $hit = true;
            } elseif (str_contains($placeLower, $tok)) {
                $score += 10.0;
                $hit = true;
            }

            if ($hit) {
                $coreHits++;
            }
        }

        // If user searched for a product (coreTokens non-empty) and ZERO core tokens hit, skip
        if (!empty($coreTokens) && $coreHits === 0) {
            return [
                'matched'            => false,
                'score'              => 0.0,
                'exact_attribute'    => false,
                'matched_attributes' => [],
                'missing_attributes' => $attributes,
            ];
        }

        // Check attributes (e.g. "pink", "hitam", "0.5mm", "hardcover", "a4", "warna")
        $matchedAttrs = [];
        $missingAttrs = [];

        foreach ($attributes as $attr) {
            if (str_contains($itemHaystack, $attr)) {
                $matchedAttrs[] = $attr;
                $score += 18.0;
            } else {
                $missingAttrs[] = $attr;
            }
        }

        // Special case: if user only provided attributes (no coreTokens, e.g. "hardcover" already became jilid+hardcover,
        // or "kertas a4"), require at least 1 matched attribute or fullHaystack hit
        if (empty($coreTokens) && empty($matchedAttrs)) {
            $anyPlaceAttr = false;
            foreach ($attributes as $attr) {
                if (str_contains($fullHaystack, $attr)) {
                    $anyPlaceAttr = true;
                    break;
                }
            }
            if (!$anyPlaceAttr) {
                return [
                    'matched'            => false,
                    'score'              => 0.0,
                    'exact_attribute'    => false,
                    'matched_attributes' => [],
                    'missing_attributes' => $attributes,
                ];
            }
        }

        $exactAttribute = empty($missingAttrs);

        return [
            'matched'            => true,
            'score'              => $score,
            'exact_attribute'    => $exactAttribute,
            'matched_attributes' => $matchedAttrs,
            'missing_attributes' => $missingAttrs,
        ];
    }

    /**
     * Build pool of store-representative items when user searches by store or general photocopy service.
     *
     * @param Collection<int, PhotocopyPlace> $places
     * @param Collection<int, PhotocopyItem> $items
     * @param array<int, array<string, mixed>> $placeMetrics
     * @param array<int, string> $lexicalTokens
     * @return array<int, array<string, mixed>>
     */
    protected function buildStoreRepresentativePool(
        Collection $places,
        Collection $items,
        array $placeMetrics,
        string $rawQuery,
        array $lexicalTokens,
        bool $openNowFilter,
        ?int $maxPrice
    ): array {
        $itemsByPlace = $items->groupBy('photocopy_place_id');
        $pool = [];

        $shopTokens = array_values(array_diff($lexicalTokens, ['fotocopy', 'percetakan', 'toko', 'terdekat', 'murah', 'termurah', 'cari', 'sekitar', 'dekat']));

        foreach ($places as $place) {
            $metrics = $placeMetrics[$place->id] ?? null;
            if (!$metrics) {
                continue;
            }
            if ($openNowFilter && !$metrics['is_open_now']) {
                continue;
            }

            $placeHaystack = mb_strtolower($place->name . ' ' . $place->location_name . ' ' . ($place->description ?? ''), 'UTF-8');
            $shopScore = 20.0;

            if (!empty($shopTokens)) {
                $hits = 0;
                foreach ($shopTokens as $st) {
                    if (str_contains($placeHaystack, $st)) {
                        $hits++;
                        $shopScore += 25.0;
                    }
                }
                if ($hits === 0 && !preg_match('/\b(fotocopy|percetakan|terdekat|dekat|toko|murah)\b/i', $rawQuery)) {
                    continue;
                }
            }

            $placeItems = ($itemsByPlace[$place->id] ?? collect())
                ->filter(fn (PhotocopyItem $it): bool => $maxPrice === null || (int) $it->price <= $maxPrice);

            if ($placeItems->isEmpty()) {
                continue;
            }

            // Pick affordable signature service/item from this store
            $repItem = $placeItems->sortBy(function (PhotocopyItem $it): array {
                $catPriority = match ($it->category) {
                    'Fotocopy' => 0,
                    'Print'    => 1,
                    'Jilid'    => 2,
                    default    => 3,
                };
                return [$catPriority, (int) $it->price];
            })->first();

            if ($repItem) {
                $pool[] = [
                    'item'               => $repItem,
                    'place'              => $place,
                    'metrics'            => $metrics,
                    'relevance_score'    => $shopScore,
                    'exact_attribute'    => true,
                    'matched_attributes' => [],
                    'missing_attributes' => [],
                    'is_bulk'            => $this->isBulkItem($repItem),
                ];
            }
        }

        return $pool;
    }

    /**
     * Rank candidates using weights, eceran priority, and store diversity cap.
     *
     * @param array<int, array<string, mixed>> $pool
     * @return array<int, array<string, mixed>>
     */
    protected function rankCandidates(
        array $pool,
        string $sort,
        bool $preferCheapest,
        bool $preferNearest,
        bool $bulkRequested,
        bool $isSpecificShop
    ): array {
        if (empty($pool)) {
            return [];
        }

        $prices = array_map(fn ($r) => (int) $r['item']->price, $pool);
        $dists = array_map(fn ($r) => (int) $r['metrics']['distance_meters'], $pool);

        $minPrice = min($prices);
        $maxPrice = max($prices);
        $priceSpan = max(1, $maxPrice - $minPrice);

        $minDist = min($dists);
        $maxDist = max($dists);
        $distSpan = max(1, $maxDist - $minDist);

        foreach ($pool as &$row) {
            $priceNorm = 1.0 - (((int) $row['item']->price - $minPrice) / $priceSpan);
            $distNorm = 1.0 - (((int) $row['metrics']['distance_meters'] - $minDist) / $distSpan);
            $openBonus = $row['metrics']['is_open_now'] ? 12.0 : 0.0;
            $ratingScore = ($row['metrics']['rating'] / 5.0) * 10.0;

            $eceranBonus = 0.0;
            if (!$bulkRequested && $preferCheapest) {
                $eceranBonus = $row['is_bulk'] ? -28.0 : 25.0;
            } elseif (!$bulkRequested && $row['is_bulk']) {
                $eceranBonus = -8.0;
            }

            $relWeight = 0.45 * min(60.0, (float) $row['relevance_score']);
            $priceWeight = $preferCheapest ? 38.0 : 18.0;
            $distWeight = $preferNearest ? 38.0 : 20.0;

            $row['composite_score'] = round(
                $relWeight
                + ($priceNorm * $priceWeight)
                + ($distNorm * $distWeight)
                + $openBonus
                + $ratingScore
                + $eceranBonus,
                2
            );
        }
        unset($row);

        usort($pool, function (array $a, array $b) use ($sort, $preferCheapest, $bulkRequested): int {
            if ($sort === 'nearest') {
                $c = $a['metrics']['distance_meters'] <=> $b['metrics']['distance_meters'];
                if ($c !== 0) {
                    return $c;
                }
                return ((int) $a['item']->price) <=> ((int) $b['item']->price);
            }

            if ($sort === 'cheapest') {
                if (!$bulkRequested && $a['is_bulk'] !== $b['is_bulk']) {
                    return $a['is_bulk'] ? 1 : -1;
                }
                $c = ((int) $a['item']->price) <=> ((int) $b['item']->price);
                if ($c !== 0) {
                    return $c;
                }
                return $a['metrics']['distance_meters'] <=> $b['metrics']['distance_meters'];
            }

            if ($sort === 'rating') {
                $c = $b['metrics']['rating'] <=> $a['metrics']['rating'];
                if ($c !== 0) {
                    return $c;
                }
                return $a['metrics']['distance_meters'] <=> $b['metrics']['distance_meters'];
            }

            // Recommended smart sort: still ensure non-bulk precedes bulk if user asked for "murah"
            if ($preferCheapest && !$bulkRequested && $a['is_bulk'] !== $b['is_bulk']) {
                return $a['is_bulk'] ? 1 : -1;
            }

            $c = $b['composite_score'] <=> $a['composite_score'];
            if ($c !== 0) {
                return $c;
            }

            return $a['metrics']['distance_meters'] <=> $b['metrics']['distance_meters'];
        });

        // Apply Store Diversity Cap (max 2 items per store unless user searched specific shop name)
        if ($isSpecificShop) {
            return $pool;
        }

        $diversified = [];
        $deferred = [];
        $perStoreCount = [];

        foreach ($pool as $row) {
            $pid = $row['place']->id;
            $perStoreCount[$pid] = ($perStoreCount[$pid] ?? 0) + 1;
            if ($perStoreCount[$pid] <= 2) {
                $diversified[] = $row;
            } else {
                $deferred[] = $row;
            }
        }

        return array_merge($diversified, $deferred);
    }

    /**
     * Detect whether an item is sold in bulk (box/lusin/rim/pak).
     */
    protected function isBulkItem(PhotocopyItem $item): bool
    {
        if ((int) ($item->pack_qty ?? 1) > 1) {
            return true;
        }

        $unitLower = mb_strtolower((string) ($item->unit ?? ''), 'UTF-8');
        if (preg_match('/\b(box|kotak|lusin|rim|pak|pack|dus|karton|kodi|pcs\s*\)|12\s*pcs|10\s*set|50\s*lbr|100\s*lbr|500\s*lbr)\b/i', $unitLower)) {
            return true;
        }

        $nameLower = mb_strtolower((string) $item->name, 'UTF-8');
        if (preg_match('/\b(1\s*box|1\s*lusin|1\s*rim|1\s*pak|isi\s*(?:10|12|20|30|50|60|100))\b/i', $nameLower)) {
            return true;
        }

        return false;
    }

    /**
     * Collect attributes absent across ALL relaxed matches.
     *
     * @param array<int, string> $attributes
     * @param array<int, array<string, mixed>> $relaxedMatches
     * @return array<int, string>
     */
    protected function computeMissingAttributes(array $attributes, array $relaxedMatches): array
    {
        $missingCounts = [];
        foreach ($relaxedMatches as $m) {
            foreach ($m['missing_attributes'] as $attr) {
                $missingCounts[$attr] = ($missingCounts[$attr] ?? 0) + 1;
            }
        }

        // Include every requested attribute missing from top matches
        $missing = [];
        foreach ($attributes as $attr) {
            if (($missingCounts[$attr] ?? 0) > 0) {
                $missing[] = $attr;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * Format a single ranked candidate into public API / Copilot schema.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function formatResultItem(array $row, string $anchorLabel): array
    {
        /** @var PhotocopyItem $item */
        $item = $row['item'];
        /** @var PhotocopyPlace $place */
        $place = $row['place'];
        $metrics = $row['metrics'];

        $priceInt = (int) $item->price;
        $formattedPrice = 'Rp ' . number_format($priceInt, 0, ',', '.');
        $dist = $metrics['distance_meters'];
        $walk = $metrics['walk_minutes'];

        $reasonParts = [
            "{$formattedPrice}/" . ($item->unit ?: 'pcs'),
            "~{$dist} m ({$walk} mnt jalan) dari {$anchorLabel}",
            $metrics['is_open_now'] ? 'Sedang buka' : 'Saat ini tutup',
        ];
        if (!empty($item->brand)) {
            $reasonParts[] = 'Merk ' . $item->brand;
        }

        return [
            'item_id'             => (int) $item->id,
            'name'                => (string) $item->name,
            'category'            => (string) $item->category,
            'item_type'           => (string) ($item->item_type ?: 'barang'),
            'sub_category'        => $item->sub_category,
            'brand'               => $item->brand,
            'price'               => $priceInt,
            'formatted_price'     => $formattedPrice,
            'unit'                => (string) ($item->unit ?: 'pcs'),
            'is_bulk'             => (bool) $row['is_bulk'],
            'description'         => $item->description,
            'image_url'           => $this->resolveItemImageUrl($item, $place),
            'exact_attribute_match' => (bool) $row['exact_attribute'],
            'matched_attributes'  => $row['matched_attributes'],
            'missing_attributes'  => $row['missing_attributes'],
            'composite_score'     => $row['composite_score'] ?? 0,
            'ranking_reason'      => implode(' • ', $reasonParts),
            'store'               => [
                'id'              => (int) $place->id,
                'name'            => (string) $place->name,
                'slug'            => (string) $place->slug,
                'location_name'   => (string) $place->location_name,
                'latitude'        => $metrics['lat'],
                'longitude'       => $metrics['lng'],
                'is_open_now'     => $metrics['is_open_now'],
                'schedule_status' => $metrics['schedule_status'],
                'rating'          => $metrics['rating'],
                'rating_count'    => $metrics['rating_count'],
                'distance_meters' => $dist,
                'walk_minutes'    => $walk,
                'image_url'       => $place->image_url,
                'whatsapp_url'    => $place->getWhatsappUrl("Halo {$place->name}, apakah {$item->name} tersedia?"),
            ],
            'actions' => [
                'order_url'  => route('fotocopy.order', ['id' => $place->id]),
                'detail_url' => route('fotocopy.show', ['id' => $place->id]),
                'route_url'  => url('/map?route_to=fotocopy:' . $place->id),
                'route_key'  => 'fotocopy:' . $place->id,
            ],
        ];
    }

    protected function resolveItemImageUrl(PhotocopyItem $item, PhotocopyPlace $place): ?string
    {
        $path = trim((string) ($item->image_path ?? ''));
        if ($path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            $clean = ltrim($path, '/');
            if (file_exists(public_path($clean))) {
                return asset($clean);
            }
            if (file_exists(public_path('storage/' . $clean))) {
                return asset('storage/' . $clean);
            }
            return asset('storage/' . $clean);
        }

        return $place->image_url;
    }

    /**
     * Build unique store list ordered by best candidate rank.
     *
     * @param array<int, array<string, mixed>> $ranked
     * @return array<int, array<string, mixed>>
     */
    protected function summarizeStores(array $ranked, int $limit): array
    {
        $stores = [];
        foreach ($ranked as $row) {
            /** @var PhotocopyPlace $place */
            $place = $row['place'];
            if (isset($stores[$place->id])) {
                $stores[$place->id]['matching_items_count']++;
                continue;
            }
            $m = $row['metrics'];
            $stores[$place->id] = [
                'id'                   => (int) $place->id,
                'name'                 => (string) $place->name,
                'location_name'        => (string) $place->location_name,
                'distance_meters'      => $m['distance_meters'],
                'walk_minutes'         => $m['walk_minutes'],
                'is_open_now'          => $m['is_open_now'],
                'schedule_status'      => $m['schedule_status'],
                'rating'               => $m['rating'],
                'image_url'            => $place->image_url,
                'matching_items_count' => 1,
                'top_item_price'       => (int) $row['item']->price,
                'order_url'            => route('fotocopy.order', ['id' => $place->id]),
                'detail_url'           => route('fotocopy.show', ['id' => $place->id]),
                'route_url'            => url('/map?route_to=fotocopy:' . $place->id),
            ];
            if (count($stores) >= $limit) {
                break;
            }
        }

        return array_values($stores);
    }

    /**
     * @param Collection<int, PhotocopyPlace> $places
     */
    protected function isSpecificShopQuery(string $rawQuery, Collection $places): bool
    {
        $qLower = mb_strtolower($rawQuery, 'UTF-8');
        foreach ($places as $p) {
            $name = mb_strtolower((string) $p->name, 'UTF-8');
            if ($name !== '' && strlen($name) >= 5 && str_contains($qLower, $name)) {
                return true;
            }
        }
        return false;
    }

    protected function normalizeSort(?string $sort): string
    {
        return in_array($sort, ['recommended', 'cheapest', 'nearest', 'rating'], true)
            ? $sort
            : 'recommended';
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
