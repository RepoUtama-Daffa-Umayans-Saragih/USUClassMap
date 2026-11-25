<?php

namespace App\Services\Search;

use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Services\OperationalStatusService;
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
        $openNowFilter = (bool) (($options['open_now'] ?? false) || ($parsed['open_now'] ?? false));
        $maxPrice = isset($options['max_price']) && is_numeric($options['max_price'])
            ? (int) $options['max_price']
            : ($parsed['max_price'] ?? null);
        $limit = max(1, min(40, (int) ($options['limit'] ?? 15)));

        $bulkRequested = (bool) ($parsed['bulk_requested'] ?? false);
        $pricePreference = $parsed['price_preference'] ?? null;
        $proximityPreference = $parsed['proximity_preference'] ?? null;
        $preferCheapest = ($sort === 'cheapest') || ($pricePreference === 'cheapest' && $sort === 'recommended');
        $preferNearest = ($sort === 'nearest') || ($proximityPreference === 'nearest');

        // Load active places upfront so specific store queries don't misroute into landmark anchors
        $allActivePlaces = PhotocopyPlace::query()->where('is_active', true)->get();
        $matchedSpecificStores = $this->findMatchedSpecificStores($query, $allActivePlaces);
        $matchedPlaceIds = $matchedSpecificStores->pluck('id')->all();
        $isSpecificShop = !empty($matchedPlaceIds);

        // 1. Determine location anchor (Explicit Landmark > Spatial Landmark > User GPS > USU Campus Center)
        [$anchorLat, $anchorLng, $anchorSource, $anchorLabel, $anchorBuilding] = $this->resolveAnchor($parsed, $options, $isSpecificShop);
        $landmarkKeywords = $anchorSource === 'landmark'
            ? $this->extractLandmarkAffinityKeywords($anchorBuilding, $anchorLabel)
            : [];

        $itemTerms = $parsed['item_terms'] ?? [];
        $attributes = $parsed['attributes'] ?? [];
        $allSearchTokens = array_values(array_unique(array_merge($itemTerms, $attributes, $parsed['tokens'] ?? [])));

        // Remove modifier/anchor/noise words from core lexical matching
        $noise = array_merge([
            'murah', 'termurah', 'hemat', 'terjangkau', 'terdekat', 'dekat', 'deket',
            'sekitar', 'sekitaran', 'buka', 'sekarang', 'masih', 'sedang', 'di', 'bawah',
            'maks', 'maksimal', 'fotocopy', 'fotokopi', 'toko', 'tempat', 'atk', 'alat', 'tulis',
        ], $landmarkKeywords);
        if (!empty($anchorBuilding['code'])) {
            $noise[] = strtolower((string) $anchorBuilding['code']);
            foreach (explode('-', strtolower((string) $anchorBuilding['code'])) as $part) {
                $noise[] = $part;
            }
        }
        $noise = array_values(array_unique($noise));
        $coreQueryTokens = array_values(array_diff($itemTerms, ['fotocopy', 'atk']));
        $lexicalTokens = array_values(array_diff($allSearchTokens, $noise));

        // 2. Load active catalog items & cache store open status + distance metrics once per store
        $items = PhotocopyItem::query()
            ->with('place')
            ->where('is_available', true)
            ->whereHas('place', fn ($q) => $q->where('is_active', true))
            ->get();

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

        foreach ($allActivePlaces as $p) {
            if (!isset($placeMetrics[$p->id])) {
                $placeMetrics[$p->id] = $this->computePlaceMetrics($p, $anchorLat, $anchorLng);
            }
        }

        // 3. Determine 3-mode search intent & evaluate matching items/stores
        $exactMatches = [];
        $relaxedMatches = [];
        $itemsByPlace = $items->groupBy('photocopy_place_id');
        $isEmptyQuery = trim($query) === '';
        $hasSpecificProduct = (bool) ($parsed['has_specific_product_intent'] ?? (!empty($coreQueryTokens) || !empty($attributes)));
        $hasSpecificLocOrStore = ($anchorSource === 'landmark')
            || $isSpecificShop
            || (bool) ($parsed['has_specific_location_intent'] ?? false);

        if ($isEmptyQuery) {
            $searchMode = ($maxPrice !== null && $maxPrice > 0) ? 'product' : 'store';
        } elseif ($hasSpecificProduct && $hasSpecificLocOrStore) {
            $searchMode = 'mixed';
        } elseif ($hasSpecificProduct) {
            $searchMode = 'product';
        } elseif ($maxPrice !== null && $maxPrice > 0) {
            $searchMode = $hasSpecificLocOrStore ? 'mixed' : 'product';
        } else {
            $searchMode = 'store';
        }

        $evaluateItemsDirect = $hasSpecificProduct || (!$isEmptyQuery && (!empty($coreQueryTokens) || !empty($attributes))) || ($maxPrice !== null && $maxPrice > 0 && $searchMode !== 'store');

        if ($evaluateItemsDirect) {
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

                if ($isEmptyQuery && $maxPrice !== null) {
                    $eval = [
                        'matched'            => true,
                        'score'              => ($item->item_type === 'jasa' ? 30.0 : 24.0),
                        'exact_attribute'    => true,
                        'matched_attributes' => [],
                        'missing_attributes' => [],
                    ];
                } else {
                    $eval = $this->evaluateItemMatch($item, $place, $matchTokens, $attributes);
                }

                if (!$eval['matched']) {
                    continue;
                }

                $landmarkDirectMatch = $anchorSource === 'landmark'
                    ? $this->placeMatchesLandmarkKeywords($place, $landmarkKeywords)
                    : false;

                $boostedScore = in_array($place->id, $matchedPlaceIds, true)
                    ? ($eval['score'] + 80.0)
                    : $eval['score'];

                $entry = [
                    'item'                  => $item,
                    'place'                 => $place,
                    'metrics'               => $metrics,
                    'relevance_score'       => $boostedScore,
                    'exact_attribute'       => $eval['exact_attribute'],
                    'matched_attributes'    => $eval['matched_attributes'],
                    'missing_attributes'    => $eval['missing_attributes'],
                    'is_bulk'               => $this->isBulkItem($item),
                    'landmark_direct_match' => $landmarkDirectMatch,
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
                $activeStoreCount = $allActivePlaces->count();
                $advisoryNote = "Belum ada {$itemLabel} dengan spesifikasi/warna \"{$attrLabel}\" tercatat di katalog {$activeStoreCount} mitra fotokopi USU. Menampilkan alternatif {$itemLabel} terbaik, termurah & terdekat yang tersedia — kamu bisa konfirmasi ketersediaan {$attrLabel} langsung ke toko sebelum memesan.";
            }
        } elseif ($isSpecificShop) {
            // Direct store-name query (e.g. "USUpress Penerbit", "USU Press", "Fotocopy Trial Admin")
            $pool = $this->buildSpecificStoreCatalogPool($matchedSpecificStores, $items, $placeMetrics, $openNowFilter, $maxPrice, min(max($limit, 12), 18));
            if ($maxPrice !== null && empty($pool)) {
                $advisoryNote = "Tidak ditemukan produk atau jasa dengan harga maksimal Rp " . number_format($maxPrice, 0, ',', '.') . " di toko tersebut.";
            } elseif (empty($pool) && $openNowFilter) {
                $advisoryNote = "Toko yang Anda cari sedang tutup pada jam WIB saat ini.";
            }
        } else {
            // Store-centric search (e.g. "", "deket teknik", "fotocopy buka sekarang", "toko sekitar FIB")
            $pool = $this->buildStoreRepresentativePool(
                $allActivePlaces,
                $items,
                $placeMetrics,
                $query,
                $lexicalTokens,
                $openNowFilter,
                $maxPrice,
                $anchorSource,
                $landmarkKeywords
            );
            if ($maxPrice !== null && empty($pool)) {
                $advisoryNote = "Tidak ditemukan produk atau jasa dengan harga maksimal Rp " . number_format($maxPrice, 0, ',', '.') . " untuk kata kunci tersebut.";
            } elseif (empty($pool) && $openNowFilter) {
                $advisoryNote = "Tidak ada toko yang sedang buka untuk kriteria tersebut saat ini.";
            }
        }

        if ($isSpecificShop && !empty($pool)) {
            $storeScopedPool = array_values(array_filter(
                $pool,
                fn (array $row): bool => in_array($row['place']->id, $matchedPlaceIds, true)
            ));
            if (!empty($storeScopedPool)) {
                $pool = $storeScopedPool;
            } elseif ($hasSpecificProduct) {
                // Specific store didn't match item at all (e.g., max_price excluded everything at this shop)
                $pool = [];
                $advisoryNote = $advisoryNote ?? 'Produk atau jasa yang diminta belum tersedia sesuai filter di toko tersebut.';
            }
        }

        // 4. Rank candidates using sort mode + price/distance/open/rating intelligence
        $ranked = $this->rankCandidates(
            $pool,
            $sort,
            $preferCheapest,
            $preferNearest,
            $bulkRequested,
            $isSpecificShop
        );

        // When mode is 'mixed' with a landmark, scope mixed_groups & store directory to nearby/matching campus stores
        $mixedGroups = $searchMode === 'mixed'
            ? $this->buildMixedGroups(
                $ranked,
                $itemsByPlace,
                $anchorLabel,
                $anchorSource,
                $isSpecificShop
            )
            : [];

        $slice = array_slice($ranked, 0, $limit);
        $formattedResults = $this->assignHighlightBadges(array_map(
            fn (array $row): array => $this->formatResultItem($row, $anchorLabel),
            $slice
        ));

        // Ensure store mode returns all matching stores (not truncated at 18); mixed aligns store summary with qualifying stores
        if ($searchMode === 'mixed' && !empty($mixedGroups)) {
            $storesSummary = array_map(fn (array $g): array => $g['store'], $mixedGroups);
        } else {
            $storeLimit = ($searchMode === 'store' || $isEmptyQuery)
                ? max($allActivePlaces->count(), $limit, 50)
                : max($limit, 35);
            $storesSummary = $this->summarizeStores($ranked, $storeLimit, $itemsByPlace, $anchorLabel);
        }

        $matchedStoreIds = array_values(array_map(
            fn (array $st): int => (int) $st['id'],
            $storesSummary
        ));

        $highlightTerms = $this->extractHighlightTerms(
            $query,
            $coreQueryTokens,
            $attributes,
            $isSpecificShop,
            $matchedSpecificStores
        );

        $intentSummary = $this->buildIntentSummary(
            $searchMode,
            $query,
            $parsed,
            $coreQueryTokens,
            $attributes,
            $anchorSource,
            $anchorLabel,
            $isSpecificShop,
            $matchedSpecificStores,
            $openNowFilter,
            $maxPrice,
            count($storesSummary),
            count($formattedResults),
            count($mixedGroups),
            $highlightTerms
        );

        return [
            'status'                => 'success',
            'query'                 => $query,
            'search_mode'           => $searchMode,
            'intent_summary'        => $intentSummary,
            'normalized_intent'     => $parsed,
            'anchor_type'           => $anchorSource,
            'anchor'                => [
                'lat'    => $anchorLat,
                'lng'    => $anchorLng,
                'source' => $anchorSource,
                'type'   => $anchorSource,
                'label'  => $anchorLabel,
            ],
            'sort'                  => $sort,
            'exact_match_count'     => count($exactMatches),
            'relaxed_fallback_used' => $relaxedFallbackUsed,
            'missing_attributes'    => $missingAttributes,
            'advisory_note'         => $advisoryNote,
            'total_candidates'      => count($ranked),
            'count'                 => count($formattedResults),
            'matched_store_ids'     => $matchedStoreIds,
            'matched_stores_count'  => count($matchedStoreIds),
            'results'               => $formattedResults,
            'items'                 => $formattedResults,
            'stores'                => $storesSummary,
            'mixed_groups'          => $mixedGroups,
        ];
    }

    /**
     * Resolve anchor coordinates: Explicit Landmark > Spatial Constraint > Mentioned Building > GPS > USU Campus Center.
     *
     * @param array<string, mixed> $parsed
     * @param array<string, mixed> $options
     * @return array{0: float, 1: float, 2: string, 3: string, 4: array<string, mixed>|null}
     */
    protected function resolveAnchor(array $parsed, array $options, bool $isSpecificShop = false): array
    {
        if (!empty($parsed['anchor_landmark'])) {
            $lm = $parsed['anchor_landmark'];
            $isSelfStoreBuilding = $isSpecificShop && strtoupper((string) ($lm['code'] ?? '')) === 'USUPRESS';
            if (!$isSelfStoreBuilding) {
                $lat = (float) ($lm['entrance_lat'] ?? $lm['latitude']);
                $lng = (float) ($lm['entrance_lng'] ?? $lm['longitude']);
                if (is_finite($lat) && is_finite($lng) && abs($lat) > 0.1) {
                    return [$lat, $lng, 'landmark', ($lm['name'] ?? $lm['code']), $lm];
                }
            }
        }

        if (!$isSpecificShop && !empty($parsed['spatial_constraints'])) {
            $sc = $parsed['spatial_constraints'][0];
            $candidateBuildings = $sc['buildings'] ?? ($sc['buildings_a'] ?? []);
            if (!empty($candidateBuildings)) {
                $lm = $candidateBuildings[0];
                $lat = (float) ($lm['entrance_lat'] ?? $lm['latitude']);
                $lng = (float) ($lm['entrance_lng'] ?? $lm['longitude']);
                if (is_finite($lat) && is_finite($lng) && abs($lat) > 0.1) {
                    return [$lat, $lng, 'landmark', (string) ($sc['label'] ?? $lm['name'] ?? $lm['code']), $lm];
                }
            }
        }

        if (
            !$isSpecificShop
            && empty($parsed['item_terms'])
            && empty($parsed['attributes'])
            && !empty($parsed['mentioned_buildings'])
        ) {
            $lm = $parsed['mentioned_buildings'][0];
            $lat = (float) ($lm['entrance_lat'] ?? $lm['latitude']);
            $lng = (float) ($lm['entrance_lng'] ?? $lm['longitude']);
            if (is_finite($lat) && is_finite($lng) && abs($lat) > 0.1) {
                return [$lat, $lng, 'landmark', (string) ($lm['name'] ?? $lm['code']), $lm];
            }
        }

        $optLat = isset($options['lat']) && is_numeric($options['lat']) ? (float) $options['lat'] : null;
        $optLng = isset($options['lng']) && is_numeric($options['lng']) ? (float) $options['lng'] : null;

        if ($optLat !== null && $optLng !== null && $optLat >= -90 && $optLat <= 90 && $optLng >= -180 && $optLng <= 180) {
            return [$optLat, $optLng, 'gps', 'Lokasi GPS Anda', null];
        }

        return [self::USU_CENTER_LAT, self::USU_CENTER_LNG, 'campus_center', 'Pusat Kampus USU', null];
    }

    /**
     * Extract store-text affinity keywords associated with a landmark building/faculty.
     *
     * @param array<string, mixed>|null $landmarkBuilding
     * @return array<int, string>
     */
    protected function extractLandmarkAffinityKeywords(?array $landmarkBuilding, string $anchorLabel): array
    {
        if (!$landmarkBuilding) {
            return [];
        }

        $code = strtoupper((string) ($landmarkBuilding['code'] ?? ''));
        $prefix = strtoupper(explode('-', $code)[0]);

        $map = [
            'FT'          => ['ft', 'teknik', 'fakultas teknik'],
            'FISIP'       => ['fisip', 'ilmu sosial'],
            'TAX'         => ['fisip', 'tax center'],
            'MSP'         => ['fisip'],
            'FK'          => ['fk', 'kedokteran'],
            'FIB'         => ['fib', 'ilmu budaya', 'sastra'],
            'PERPUS'      => ['perpus', 'perpustakaan'],
            'FASILKOM'    => ['fasilkom', 'fasilkom-ti', 'fasilkomti', 'ilmu komputer'],
            'GD'          => ['fasilkom', 'fasilkom-ti', 'ilmu komputer'],
            'FH'          => ['fh', 'hukum'],
            'FP'          => ['fp', 'pertanian'],
            'FEB'         => ['feb', 'ekonomi'],
            'MM'          => ['feb', 'ekonomi'],
            'FMIPA'       => ['fmipa', 'mipa'],
            'FKG'         => ['fkg', 'kedokteran gigi'],
            'FKM'         => ['fkm', 'kesehatan masyarakat'],
            'FF'          => ['ff', 'farmasi'],
            'FPSI'        => ['fpsi', 'psikologi'],
            'FKEP'        => ['fkep', 'keperawatan'],
            'VOKASI'      => ['vokasi'],
        ];

        $keywords = $map[$prefix] ?? [];
        if ($code === 'GD-FASILKOM') {
            $keywords = $map['FASILKOM'];
        } elseif ($code === 'PERPUS-MIPA') {
            $keywords = ['fmipa', 'mipa', 'perpus', 'perpustakaan'];
        }

        if ($code !== '') {
            $keywords[] = strtolower($code);
        }
        if ($prefix !== '' && strlen($prefix) >= 2 && $prefix !== 'GD') {
            $keywords[] = strtolower($prefix);
        }

        return array_values(array_unique(array_filter($keywords)));
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
        $opStatus = app(OperationalStatusService::class)->evaluatePhotocopyStatus($place);

        return [
            'lat'                => $pLat,
            'lng'                => $pLng,
            'distance_meters'    => $distanceMeters,
            'walk_minutes'       => $walkMinutes,
            'is_open_now'        => (bool) ($opStatus['is_open_now'] ?? false),
            'schedule_status'    => ($opStatus['is_open_now'] ?? false)
                ? "Buka ⋅ {$opStatus['detail_text']}"
                : "Tutup ⋅ {$opStatus['detail_text']}",
            'operational_status' => $opStatus,
            'marker_color'       => $opStatus['marker_color'],
            'open_time'          => $opStatus['open_time'],
            'close_time'         => $opStatus['close_time'],
            'operating_days'     => $opStatus['operating_days'],
            'rating'             => (float) ($place->rating ?: 4.5),
            'rating_count'       => (int) ($place->user_rating_count ?: 0),
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

        $tokenExpansions = [
            'correction'  => ['correction', 'tipe-x', 'tip-x', 'tipex', 'stipo'],
            'highlighter' => ['highlighter', 'stabilo'],
            'stopmap'     => ['stopmap', 'stofmap', 'map ', 'snelhecter'],
            'sticky'      => ['sticky', 'post-it', 'postit'],
        ];

        $score = 0.0;
        $coreHits = 0;

        foreach ($coreTokens as $tok) {
            $candidates = $tokenExpansions[$tok] ?? [$tok];
            $hit = false;
            foreach ($candidates as $cand) {
                if (str_contains($nameLower, $cand)) {
                    $score += 35.0;
                    $hit = true;
                    break;
                }
                if (str_contains($catLower, $cand) || str_contains($subCatLower, $cand) || str_contains($brandLower, $cand)) {
                    $score += 24.0;
                    $hit = true;
                    break;
                }
                if (str_contains($descLower, $cand)) {
                    $score += 14.0;
                    $hit = true;
                    break;
                }
                if (str_contains($placeLower, $cand)) {
                    $score += 10.0;
                    $hit = true;
                    break;
                }
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
            $attrPattern = '/(?<![a-z0-9])' . preg_quote($attr, '/') . '(?![a-z0-9])/iu';
            if (preg_match($attrPattern, $itemHaystack) === 1) {
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
     * @param array<int, string> $landmarkKeywords
     * @return array<int, array<string, mixed>>
     */
    protected function buildStoreRepresentativePool(
        Collection $places,
        Collection $items,
        array $placeMetrics,
        string $rawQuery,
        array $lexicalTokens,
        bool $openNowFilter,
        ?int $maxPrice,
        string $anchorSource = 'campus_center',
        array $landmarkKeywords = []
    ): array {
        $itemsByPlace = $items->groupBy('photocopy_place_id');
        $pool = [];

        $shopStopwords = [
            'fotocopy', 'fotokopi', 'percetakan', 'toko', 'terdekat', 'murah', 'termurah',
            'cari', 'sekitar', 'sekitaran', 'dekat', 'deket', 'buka', 'sekarang', 'masih',
            'sedang', 'yang', 'tempat', 'jasa', 'kampus', 'usu', 'medan', 'terbaik', 'paling',
            'saja', 'aja', '24', 'jam',
        ];
        $shopTokens = array_values(array_diff($lexicalTokens, $shopStopwords));

        foreach ($places as $place) {
            $metrics = $placeMetrics[$place->id] ?? null;
            if (!$metrics) {
                continue;
            }
            if ($openNowFilter && !$metrics['is_open_now']) {
                continue;
            }

            $placeHaystack = mb_strtolower($place->name . ' ' . $place->location_name . ' ' . ($place->description ?? '') . ' ' . ($place->slug ?? ''), 'UTF-8');
            $placeCompact = $this->normalizeCompact($place->name . ' ' . ($place->slug ?? ''));
            $rawCompact = $this->normalizeCompact($rawQuery);
            $shopScore = 20.0;
            $landmarkDirectMatch = false;

            if ($anchorSource === 'landmark' && !empty($landmarkKeywords)) {
                if ($this->placeMatchesLandmarkKeywords($place, $landmarkKeywords)) {
                    $landmarkDirectMatch = true;
                    $shopScore += 65.0;
                }
            }

            if ($rawCompact !== '' && strlen($rawCompact) >= 5 && str_contains($placeCompact, $rawCompact)) {
                $shopScore += 65.0;
            } elseif (!empty($shopTokens) && $anchorSource !== 'landmark') {
                $hits = 0;
                foreach ($shopTokens as $st) {
                    $stCompact = $this->normalizeCompact($st);
                    if (str_contains($placeHaystack, $st) || ($stCompact !== '' && str_contains($placeCompact, $stCompact))) {
                        $hits++;
                        $shopScore += 25.0;
                    }
                }
                if ($hits === 0) {
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
                    'item'                  => $repItem,
                    'place'                 => $place,
                    'metrics'               => $metrics,
                    'relevance_score'       => $shopScore,
                    'exact_attribute'       => true,
                    'matched_attributes'    => [],
                    'missing_attributes'    => [],
                    'is_bulk'               => $this->isBulkItem($repItem),
                    'landmark_direct_match' => $landmarkDirectMatch,
                ];
            }
        }

        if ($anchorSource === 'landmark' && !empty($pool)) {
            $nearbyOrMentioned = array_values(array_filter(
                $pool,
                fn (array $r): bool => !empty($r['landmark_direct_match']) || (int) $r['metrics']['distance_meters'] <= 500
            ));

            if (count($nearbyOrMentioned) >= 5) {
                return $nearbyOrMentioned;
            }

            usort($pool, function (array $a, array $b): int {
                $mA = !empty($a['landmark_direct_match']) ? 0 : 1;
                $mB = !empty($b['landmark_direct_match']) ? 0 : 1;
                if ($mA !== $mB) {
                    return $mA <=> $mB;
                }
                return ((int) $a['metrics']['distance_meters']) <=> ((int) $b['metrics']['distance_meters']);
            });

            return array_slice($pool, 0, min(8, count($pool)));
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
            $landmarkBonus = !empty($row['landmark_direct_match']) ? 40.0 : 0.0;

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
                + $eceranBonus
                + $landmarkBonus,
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
            'id'                  => (int) $item->id,
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
            'highlight_badge'     => null,
            'matched_attributes'  => $row['matched_attributes'],
            'missing_attributes'  => $row['missing_attributes'],
            'composite_score'     => $row['composite_score'] ?? 0,
            'ranking_reason'      => implode(' • ', $reasonParts),
            'store'               => [
                'id'                 => (int) $place->id,
                'name'               => (string) $place->name,
                'slug'               => (string) $place->slug,
                'location_name'      => (string) $place->location_name,
                'address'            => (string) $place->location_name,
                'latitude'           => $metrics['lat'],
                'longitude'          => $metrics['lng'],
                'is_open_now'        => $metrics['is_open_now'],
                'status_label'       => $metrics['is_open_now'] ? 'Buka Sekarang' : 'Tutup',
                'schedule_status'    => $metrics['schedule_status'],
                'operational_status' => $metrics['operational_status'],
                'marker_color'       => $metrics['marker_color'],
                'open_time'          => $metrics['open_time'],
                'close_time'         => $metrics['close_time'],
                'operating_days'     => $metrics['operating_days'],
                'rating'             => $metrics['rating'],
                'rating_count'       => $metrics['rating_count'],
                'distance_meters'    => $dist,
                'distance_m'         => $dist,
                'walk_minutes'       => $walk,
                'walking_minutes'    => $walk,
                'image_url'          => $place->image_url,
                'photo_url'          => $place->image_url,
                'whatsapp_url'       => $place->getWhatsappUrl("Halo {$place->name}, apakah {$item->name} tersedia?"),
            ],
            'actions' => [
                'order_url'  => route('fotocopy.order', ['id' => $place->id]),
                'detail_url' => route('fotocopy.show', ['id' => $place->id]),
                'route_url'  => url('/map?route_to=fotocopy:' . $place->id),
                'map_route_url' => url('/map?route_to=fotocopy:' . $place->id),
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
     * Check whether a place's name/address/description matches any landmark affinity keyword.
     *
     * @param array<int, string> $landmarkKeywords
     */
    protected function placeMatchesLandmarkKeywords(PhotocopyPlace $place, array $landmarkKeywords): bool
    {
        if (empty($landmarkKeywords)) {
            return false;
        }
        $haystack = mb_strtolower(
            $place->name . ' ' . $place->location_name . ' ' . ($place->description ?? '') . ' ' . ($place->slug ?? ''),
            'UTF-8'
        );
        foreach ($landmarkKeywords as $kw) {
            $kw = trim($kw);
            if ($kw !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($kw, '/') . '(?![a-z0-9])/iu', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a unified Store Card object shared by `stores` and `mixed_groups[*].store`.
     *
     * @param array<string, mixed> $metrics
     * @param Collection<int, PhotocopyItem> $placeItems
     * @return array<string, mixed>
     */
    protected function formatStoreCardPayload(
        PhotocopyPlace $place,
        array $metrics,
        Collection $placeItems,
        string $anchorLabel,
        int $matchingItemsCount,
        int $topItemPrice
    ): array {
        $startingPrice = $placeItems->isNotEmpty()
            ? (int) $placeItems->min('price')
            : ($topItemPrice > 0 ? $topItemPrice : 200);

        $availableCategories = $placeItems
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $preferredOrder = ['Print', 'Fotocopy', 'Jilid', 'ATK', 'Digital', 'Finishing'];
        $serviceTags = [];
        foreach ($preferredOrder as $cat) {
            if (in_array($cat, $availableCategories, true)) {
                $serviceTags[] = $cat === 'Fotocopy' ? 'Fotokopi' : $cat;
            }
            if (count($serviceTags) >= 4) {
                break;
            }
        }
        if (empty($serviceTags)) {
            $serviceTags = ['Fotokopi', 'Print', 'Jilid', 'ATK'];
        }

        $openTime = (string) ($metrics['open_time'] ?? '08:00');
        $closeTime = (string) ($metrics['close_time'] ?? '21:00');
        $orderUrl = route('fotocopy.order', ['id' => $place->id]);
        $detailUrl = route('fotocopy.show', ['id' => $place->id]);
        $mapRouteUrl = url('/map?route_to=fotocopy:' . $place->id);

        return [
            'id'                      => (int) $place->id,
            'name'                    => (string) $place->name,
            'slug'                    => (string) $place->slug,
            'location_name'           => (string) $place->location_name,
            'address'                 => (string) $place->location_name,
            'description'             => (string) ($place->description ?? ''),
            'latitude'                => $metrics['lat'],
            'longitude'               => $metrics['lng'],
            'distance_meters'         => $metrics['distance_meters'],
            'distance_m'              => $metrics['distance_meters'],
            'walk_minutes'            => $metrics['walk_minutes'],
            'walking_minutes'         => $metrics['walk_minutes'],
            'anchor_label'            => $anchorLabel,
            'is_active'               => (bool) $place->is_active,
            'is_open_now'             => $metrics['is_open_now'],
            'status_label'            => $metrics['is_open_now'] ? 'Buka Sekarang' : 'Tutup',
            'schedule_status'         => $metrics['schedule_status'],
            'operational_status'      => $metrics['operational_status'],
            'marker_color'            => $metrics['marker_color'],
            'open_time'               => $openTime,
            'close_time'              => $closeTime,
            'operating_days'          => $metrics['operating_days'],
            'hours_label'             => "{$openTime} - {$closeTime} WIB",
            'rating'                  => $metrics['rating'],
            'rating_count'            => $metrics['rating_count'],
            'image_url'               => $place->image_url,
            'photo_url'               => $place->image_url,
            'starting_price'          => $startingPrice,
            'formatted_starting_price' => 'Rp ' . number_format($startingPrice, 0, ',', '.'),
            'service_tags'            => $serviceTags,
            'matching_items_count'    => $matchingItemsCount,
            'top_item_price'          => $topItemPrice,
            'order_url'               => $orderUrl,
            'detail_url'              => $detailUrl,
            'route_url'               => $mapRouteUrl,
            'map_route_url'           => $mapRouteUrl,
            'whatsapp_url'            => $place->getWhatsappUrl("Halo {$place->name}, saya ingin bertanya layanan fotokopi."),
        ];
    }

    /**
     * Assign up to 1 distinctive `highlight_badge` per item card ('Termurah', 'Terdekat', 'Paling Cocok').
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function assignHighlightBadges(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $nonBulk = array_values(array_filter($items, fn (array $r): bool => empty($r['is_bulk'])));
        $pricePool = !empty($nonBulk) ? $nonBulk : $items;
        $minPrice = min(array_map(fn (array $r): int => (int) $r['price'], $pricePool));
        $minDist = min(array_map(fn (array $r): int => (int) ($r['store']['distance_meters'] ?? 999999), $items));

        $cheapestIdx = null;
        foreach ($items as $idx => $row) {
            if (!empty($nonBulk) && !empty($row['is_bulk'])) {
                continue;
            }
            if ((int) $row['price'] === $minPrice) {
                $cheapestIdx = $idx;
                break;
            }
        }

        $nearestIdx = null;
        foreach ($items as $idx => $row) {
            if ($idx === $cheapestIdx) {
                continue;
            }
            if ((int) ($row['store']['distance_meters'] ?? 999999) === $minDist) {
                $nearestIdx = $idx;
                break;
            }
        }
        if ($nearestIdx === null && count($items) > 1) {
            // Find second closest unbadged store within close radius if closest already owns Termurah
            $bestD = PHP_INT_MAX;
            foreach ($items as $idx => $row) {
                if ($idx === $cheapestIdx) {
                    continue;
                }
                $d = (int) ($row['store']['distance_meters'] ?? 999999);
                if ($d < $bestD) {
                    $bestD = $d;
                    $nearestIdx = $idx;
                }
            }
        }

        if ($cheapestIdx !== null) {
            $items[$cheapestIdx]['highlight_badge'] = 'Termurah';
        }
        if ($nearestIdx !== null && $items[$nearestIdx]['highlight_badge'] === null) {
            $items[$nearestIdx]['highlight_badge'] = 'Terdekat';
        }
        if ($items[0]['highlight_badge'] === null) {
            $items[0]['highlight_badge'] = 'Paling Cocok';
        } elseif (!empty($items[0]['exact_attribute_match']) && $cheapestIdx !== 0 && $nearestIdx !== 0) {
            $items[0]['highlight_badge'] = 'Paling Cocok';
        } else {
            foreach ($items as $idx => $row) {
                if ($row['highlight_badge'] === null && !empty($row['exact_attribute_match'])) {
                    $items[$idx]['highlight_badge'] = 'Paling Cocok';
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Build unique store list ordered by best candidate rank.
     *
     * @param array<int, array<string, mixed>> $ranked
     * @param Collection<int, Collection<int, PhotocopyItem>>|null $itemsByPlace
     * @return array<int, array<string, mixed>>
     */
    protected function summarizeStores(array $ranked, int $limit, ?Collection $itemsByPlace = null, string $anchorLabel = 'Pusat Kampus USU'): array
    {
        $stores = [];
        $groupedCounts = [];
        foreach ($ranked as $row) {
            $pid = (int) $row['place']->id;
            $groupedCounts[$pid] = ($groupedCounts[$pid] ?? 0) + 1;
        }

        foreach ($ranked as $row) {
            /** @var PhotocopyPlace $place */
            $place = $row['place'];
            if (isset($stores[$place->id])) {
                continue;
            }
            $placeItems = $itemsByPlace ? ($itemsByPlace[$place->id] ?? collect()) : collect();
            $stores[$place->id] = $this->formatStoreCardPayload(
                $place,
                $row['metrics'],
                $placeItems,
                $anchorLabel,
                $groupedCounts[(int) $place->id] ?? 1,
                (int) $row['item']->price
            );
            if (count($stores) >= $limit) {
                break;
            }
        }

        return array_values($stores);
    }

    /**
     * Build per-store grouped product cards for `mixed` queries (`pulpen pink deket teknik`, `print warna di USU Press`).
     *
     * @param array<int, array<string, mixed>> $ranked
     * @param Collection<int, Collection<int, PhotocopyItem>> $itemsByPlace
     * @return array<int, array<string, mixed>>
     */
    protected function buildMixedGroups(
        array $ranked,
        Collection $itemsByPlace,
        string $anchorLabel,
        string $anchorSource,
        bool $isSpecificShop
    ): array {
        if (empty($ranked)) {
            return [];
        }

        $groupsByStore = [];
        foreach ($ranked as $row) {
            /** @var PhotocopyPlace $place */
            $place = $row['place'];
            $pid = (int) $place->id;
            if (!isset($groupsByStore[$pid])) {
                $groupsByStore[$pid] = [
                    'place'                 => $place,
                    'metrics'               => $row['metrics'],
                    'landmark_direct_match' => !empty($row['landmark_direct_match']),
                    'rows'                  => [],
                ];
            } else {
                $groupsByStore[$pid]['landmark_direct_match'] = $groupsByStore[$pid]['landmark_direct_match'] || !empty($row['landmark_direct_match']);
            }
            $groupsByStore[$pid]['rows'][] = $row;
        }

        $groups = array_values($groupsByStore);

        if (!$isSpecificShop && $anchorSource === 'landmark' && count($groups) > 1) {
            usort($groups, function (array $a, array $b): int {
                $dA = (int) $a['metrics']['distance_meters'] - ($a['landmark_direct_match'] ? 120 : 0);
                $dB = (int) $b['metrics']['distance_meters'] - ($b['landmark_direct_match'] ? 120 : 0);
                if ($dA !== $dB) {
                    return $dA <=> $dB;
                }
                return ((float) $b['metrics']['rating']) <=> ((float) $a['metrics']['rating']);
            });

            $nearby = array_values(array_filter(
                $groups,
                fn (array $g): bool => $g['landmark_direct_match'] || (int) $g['metrics']['distance_meters'] <= 650
            ));

            $groups = count($nearby) >= 3
                ? array_slice($nearby, 0, 12)
                : array_slice($groups, 0, min(8, count($groups)));
        } elseif (!$isSpecificShop) {
            $groups = array_slice($groups, 0, 12);
        }

        $result = [];
        foreach ($groups as $g) {
            $rows = $g['rows'];
            usort($rows, function (array $a, array $b): int {
                if (!empty($a['exact_attribute']) !== !empty($b['exact_attribute'])) {
                    return !empty($a['exact_attribute']) ? -1 : 1;
                }
                if (!empty($a['is_bulk']) !== !empty($b['is_bulk'])) {
                    return !empty($a['is_bulk']) ? 1 : -1;
                }
                $c = ((int) $a['item']->price) <=> ((int) $b['item']->price);
                if ($c !== 0) {
                    return $c;
                }
                return ((float) ($b['relevance_score'] ?? 0)) <=> ((float) ($a['relevance_score'] ?? 0));
            });

            $matchedItems = [];
            $seenItemIds = [];
            foreach ($rows as $r) {
                /** @var PhotocopyItem $it */
                $it = $r['item'];
                if (isset($seenItemIds[(int) $it->id])) {
                    continue;
                }
                $seenItemIds[(int) $it->id] = true;
                $priceInt = (int) $it->price;
                $matchedItems[] = [
                    'id'              => (int) $it->id,
                    'item_id'         => (int) $it->id,
                    'name'            => (string) $it->name,
                    'category'        => (string) $it->category,
                    'price'           => $priceInt,
                    'formatted_price' => 'Rp ' . number_format($priceInt, 0, ',', '.'),
                    'unit'            => (string) ($it->unit ?: 'pcs'),
                    'image_url'       => $this->resolveItemImageUrl($it, $g['place']),
                    'is_exact_match'  => (bool) $r['exact_attribute'],
                ];
            }

            if (empty($matchedItems)) {
                continue;
            }

            $lowestPrice = min(array_map(fn (array $m): int => (int) $m['price'], $matchedItems));
            $storePayload = $this->formatStoreCardPayload(
                $g['place'],
                $g['metrics'],
                $itemsByPlace[$g['place']->id] ?? collect(),
                $anchorLabel,
                count($matchedItems),
                $lowestPrice
            );

            $result[] = [
                'store'                  => $storePayload,
                'matched_items'          => $matchedItems,
                'total_matched_items'    => count($matchedItems),
                'lowest_matched_price'   => $lowestPrice,
                'formatted_lowest_price' => 'Rp ' . number_format($lowestPrice, 0, ',', '.'),
            ];
        }

        return $result;
    }

    /**
     * Extract clean highlight terms (including safe morphological/synonym forms) for `<mark>` rendering.
     *
     * @param array<int, string> $coreQueryTokens
     * @param array<int, string> $attributes
     * @param Collection<int, PhotocopyPlace> $matchedSpecificStores
     * @return array<int, string>
     */
    protected function extractHighlightTerms(
        string $rawQuery,
        array $coreQueryTokens,
        array $attributes,
        bool $isSpecificShop,
        Collection $matchedSpecificStores
    ): array {
        $terms = [];

        $synonymHighlight = [
            'correction'  => ['correction', 'tipe-x', 'tipex'],
            'highlighter' => ['highlighter', 'stabilo'],
            'stopmap'     => ['stopmap', 'stofmap', 'map', 'snelhecter'],
            'sticky'      => ['sticky', 'post-it'],
            'pasfoto'     => ['pas foto', 'pasfoto'],
        ];

        foreach (array_merge($coreQueryTokens, $attributes) as $tok) {
            $t = trim(mb_strtolower((string) $tok, 'UTF-8'));
            if ($t === '') {
                continue;
            }
            if (isset($synonymHighlight[$t])) {
                foreach ($synonymHighlight[$t] as $alt) {
                    $terms[] = $alt;
                }
            } else {
                $terms[] = $t;
            }
        }

        // Also preserve distinctive raw product tokens (e.g. 'tipex', 'bolpen') if user typed them
        $noiseForHighlight = [
            'fotocopy', 'fotokopi', 'toko', 'gerai', 'tempat', 'cari', 'carikan', 'murah', 'termurah',
            'terdekat', 'dekat', 'deket', 'sekitar', 'buka', 'sekarang', 'di', 'yang', 'untuk', 'bawah',
            'maks', 'maksimal', 'dan', 'atau', 'kampus', 'usu',
        ];
        if ($isSpecificShop) {
            foreach ($matchedSpecificStores as $st) {
                foreach (preg_split('/[\s\-\(\)&\/.,]+/u', mb_strtolower((string) $st->name, 'UTF-8')) ?: [] as $wt) {
                    if (mb_strlen($wt, 'UTF-8') >= 3 && !in_array($wt, ['fotocopy', 'fotokopi', 'dan', 'penerbit', 'medan'], true)) {
                        $terms[] = $wt;
                    }
                }
            }
        } else {
            foreach (preg_split('/[\s,]+/u', mb_strtolower(trim($rawQuery), 'UTF-8')) ?: [] as $rt) {
                $clean = trim($rt, '."\'()');
                if (mb_strlen($clean, 'UTF-8') >= 3 && !in_array($clean, $noiseForHighlight, true) && in_array($clean, ['tipec', 'tipex', 'tipe-x', 'stabilo'], true)) {
                    $terms[] = $clean;
                }
            }
        }

        $terms = array_values(array_unique(array_filter($terms, fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 2)));
        usort($terms, fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return array_slice($terms, 0, 8);
    }

    /**
     * Build Smart Intent Bar summary metadata (`mode_label`, `headline`, `pills`, `highlight_terms`).
     *
     * @param array<string, mixed> $parsed
     * @param array<int, string> $coreQueryTokens
     * @param array<int, string> $attributes
     * @param Collection<int, PhotocopyPlace> $matchedSpecificStores
     * @param array<int, string> $highlightTerms
     * @return array<string, mixed>
     */
    protected function buildIntentSummary(
        string $searchMode,
        string $rawQuery,
        array $parsed,
        array $coreQueryTokens,
        array $attributes,
        string $anchorSource,
        string $anchorLabel,
        bool $isSpecificShop,
        Collection $matchedSpecificStores,
        bool $openNowFilter,
        ?int $maxPrice,
        int $storesCount,
        int $itemsCount,
        int $mixedCount,
        array $highlightTerms
    ): array {
        $modeLabel = match ($searchMode) {
            'product' => 'Produk & Jasa',
            'mixed'   => 'Produk + Lokasi',
            default   => 'Tempat Fotokopi',
        };

        $prodPhrase = trim(implode(' ', array_slice(array_merge($coreQueryTokens, $attributes), 0, 4)));
        if ($prodPhrase === '' && trim($rawQuery) !== '') {
            $prodPhrase = trim($rawQuery);
        }

        if ($searchMode === 'mixed') {
            $locTarget = $isSpecificShop
                ? (string) ($matchedSpecificStores->first()?->name ?? $anchorLabel)
                : $anchorLabel;
            $headline = $isSpecificShop
                ? "Katalog \"{$prodPhrase}\" di {$locTarget} ({$mixedCount} gerai cocok)"
                : "Toko dekat {$locTarget} yang menyediakan \"{$prodPhrase}\" ({$mixedCount} gerai)";
        } elseif ($searchMode === 'product') {
            if (trim($rawQuery) === '' && $maxPrice !== null) {
                $headline = 'Produk & jasa cetak dengan harga maksimal Rp ' . number_format($maxPrice, 0, ',', '.');
            } else {
                $headline = "Produk & jasa \"{$prodPhrase}\" ({$itemsCount} rekomendasi di {$storesCount} gerai)";
            }
        } else {
            if (trim($rawQuery) === '') {
                $headline = $openNowFilter
                    ? "Tempat fotokopi yang sedang buka saat ini ({$storesCount} gerai)"
                    : "Daftar lengkap {$storesCount} mitra fotokopi & percetakan Kampus USU";
            } elseif ($isSpecificShop) {
                $headline = 'Profil gerai ' . (string) ($matchedSpecificStores->first()?->name ?? $rawQuery);
            } elseif ($anchorSource === 'landmark') {
                $headline = "Tempat fotokopi terdekat dari {$anchorLabel} ({$storesCount} gerai)";
            } elseif ($openNowFilter) {
                $headline = "Tempat fotokopi buka sekarang ({$storesCount} gerai aktif)";
            } else {
                $headline = "Tempat fotokopi terbaik sekitar {$anchorLabel} ({$storesCount} gerai)";
            }
        }

        $pills = [
            ['type' => 'mode', 'label' => $modeLabel],
        ];

        if ($anchorSource === 'landmark') {
            $pills[] = ['type' => 'location', 'label' => 'Area: ' . $anchorLabel];
        } elseif ($isSpecificShop && $matchedSpecificStores->isNotEmpty()) {
            $pills[] = ['type' => 'location', 'label' => 'Toko: ' . (string) $matchedSpecificStores->first()->name];
        } elseif ($anchorSource === 'gps') {
            $pills[] = ['type' => 'location', 'label' => 'GPS Anda'];
        }

        if (($searchMode === 'product' || $searchMode === 'mixed') && $prodPhrase !== '') {
            $pills[] = ['type' => 'product', 'label' => 'Item: ' . $prodPhrase];
        }

        if ($openNowFilter) {
            $pills[] = ['type' => 'filter', 'label' => 'Buka Sekarang'];
        }

        if ($maxPrice !== null) {
            $pills[] = ['type' => 'filter', 'label' => 'Maks Rp ' . number_format($maxPrice, 0, ',', '.')];
        }

        return [
            'mode'            => $searchMode,
            'mode_label'      => $modeLabel,
            'headline'        => $headline,
            'pills'           => $pills,
            'highlight_terms' => $highlightTerms,
        ];
    }

    /**
     * Build diverse featured catalog items across categories when user directly searches by store name.
     *
     * @param Collection<int, PhotocopyPlace> $matchedStores
     * @param Collection<int, PhotocopyItem> $items
     * @param array<int, array<string, mixed>> $placeMetrics
     * @return array<int, array<string, mixed>>
     */
    protected function buildSpecificStoreCatalogPool(
        Collection $matchedStores,
        Collection $items,
        array $placeMetrics,
        bool $openNowFilter,
        ?int $maxPrice,
        int $targetCount = 12
    ): array {
        $itemsByPlace = $items->groupBy('photocopy_place_id');
        $pool = [];

        foreach ($matchedStores as $place) {
            $metrics = $placeMetrics[$place->id] ?? null;
            if (!$metrics) {
                continue;
            }
            if ($openNowFilter && !$metrics['is_open_now']) {
                continue;
            }

            $placeItems = ($itemsByPlace[$place->id] ?? collect())
                ->filter(fn (PhotocopyItem $it): bool => $maxPrice === null || (int) $it->price <= $maxPrice);

            if ($placeItems->isEmpty()) {
                continue;
            }

            $categoryOrder = ['Print' => 0, 'Jilid' => 1, 'Digital' => 2, 'Fotocopy' => 3, 'Finishing' => 4, 'ATK' => 5];

            // Prioritaskan item unggulan brand toko (mis. USU Press) lalu keragaman kategori & jasa utama
            $sorted = $placeItems->sort(function (PhotocopyItem $a, PhotocopyItem $b) use ($categoryOrder): int {
                $sigA = stripos((string) ($a->brand ?? '') . ' ' . $a->name, 'usu press') !== false ? 0 : 1;
                $sigB = stripos((string) ($b->brand ?? '') . ' ' . $b->name, 'usu press') !== false ? 0 : 1;
                if ($sigA !== $sigB) {
                    return $sigA <=> $sigB;
                }

                $typeA = ($a->item_type === 'jasa') ? 0 : 1;
                $typeB = ($b->item_type === 'jasa') ? 0 : 1;
                if ($typeA !== $typeB) {
                    return $typeA <=> $typeB;
                }

                $catA = $categoryOrder[(string) $a->category] ?? 9;
                $catB = $categoryOrder[(string) $b->category] ?? 9;
                if ($catA !== $catB) {
                    return $catA <=> $catB;
                }

                return ((int) $a->price) <=> ((int) $b->price);
            })->values();

            // Ambil representatif merata lintas kategori dulu, lalu lengkapi hingga $targetCount
            $chosen = collect();
            $seenIds = [];

            foreach (array_keys($categoryOrder) as $cat) {
                foreach ($sorted->where('category', $cat)->take(2) as $it) {
                    if ($chosen->count() >= $targetCount) {
                        break 2;
                    }
                    $seenIds[$it->id] = true;
                    $chosen->push($it);
                }
            }

            foreach ($sorted as $it) {
                if ($chosen->count() >= $targetCount) {
                    break;
                }
                if (!isset($seenIds[$it->id])) {
                    $seenIds[$it->id] = true;
                    $chosen->push($it);
                }
            }

            foreach ($chosen as $idx => $item) {
                $pool[] = [
                    'item'               => $item,
                    'place'              => $place,
                    'metrics'            => $metrics,
                    'relevance_score'    => max(85.0, 98.0 - ($idx * 0.5)),
                    'exact_attribute'    => true,
                    'matched_attributes' => [],
                    'missing_attributes' => [],
                    'is_bulk'            => $this->isBulkItem($item),
                ];
            }
        }

        return $pool;
    }

    protected function normalizeCompact(string $text): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower($text, 'UTF-8')) ?? '';
    }

    /**
     * Find specific stores directly referenced by name or slug (flexible whitespace/punctuation matching).
     *
     * @param Collection<int, PhotocopyPlace> $places
     * @return Collection<int, PhotocopyPlace>
     */
    protected function findMatchedSpecificStores(string $rawQuery, Collection $places): Collection
    {
        $compactQuery = $this->normalizeCompact($rawQuery);
        if (strlen($compactQuery) < 5) {
            return collect();
        }

        $facultyStopwords = [
            'fib', 'feb', 'ft', 'fh', 'fk', 'fp', 'ff', 'fkg', 'fkm', 'fmipa', 'mipa',
            'fisip', 'fpsi', 'fkep', 'fhut', 'gkb', 'gdp', 'dlcb', 'vokasi', 'fasilkom',
            'fasilkomti', 'rektorat', 'perpustakaan', 'perpus', 'auditorium', 'usu', 'medan',
        ];
        if (in_array($compactQuery, $facultyStopwords, true)) {
            return collect();
        }

        $genericWords = [
            'fotocopy', 'fotokopi', 'foto', 'copy', 'percetakan', 'printing', 'print', 'cetak',
            'toko', 'gerai', 'atk', 'center', 'corner', 'custom', 'stiker', 'poster', 'banner',
            'medan', 'usu', 'perpustakaan', 'fakultas', 'hukum', 'fisip', 'fasilkom', 'ft', 'fk',
            'fib', 'fp', 'pintu', 'dan', 'large', 'format', 'digital', 'expedisi', 'jt', 'multi', 'penerbit',
        ];

        $isUsuPressMention = (bool) preg_match('/\b(usupress|usu\s*press)\b/i', $rawQuery)
            || str_contains($compactQuery, 'usupress');

        return $places->filter(function (PhotocopyPlace $place) use ($compactQuery, $genericWords, $isUsuPressMention): bool {
            $placeCompactName = $this->normalizeCompact((string) $place->name);
            $placeCompactSlug = $this->normalizeCompact((string) $place->slug);

            if ($isUsuPressMention && (str_contains($placeCompactName, 'usupress') || str_contains($placeCompactSlug, 'usupress'))) {
                return true;
            }

            $baseName = trim((string) preg_replace('/\([^)]*\)/', '', (string) $place->name));
            $placeCompactBase = $this->normalizeCompact($baseName);

            $distinctiveBaseTokens = array_values(array_filter(
                preg_split('/[\s\-\(\)&\/.,]+/u', mb_strtolower($baseName, 'UTF-8')) ?: [],
                fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 2 && !in_array($t, $genericWords, true)
            ));
            $distinctiveCompact = $this->normalizeCompact(implode('', $distinctiveBaseTokens));

            if ($distinctiveCompact !== '' && strlen($distinctiveCompact) >= 6 && str_contains($compactQuery, $distinctiveCompact)) {
                return true;
            }

            $nameTokens = array_values(array_filter(
                preg_split('/[\s\-\(\)&\/.,]+/u', mb_strtolower((string) $place->name, 'UTF-8')) ?: [],
                fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 4 && !in_array($t, $genericWords, true)
            ));

            if (empty($nameTokens)) {
                return false;
            }

            if ($placeCompactBase !== '' && strlen($placeCompactBase) >= 6 && str_contains($compactQuery, $placeCompactBase)) {
                return true;
            }

            if (strlen($compactQuery) >= 6 && (str_contains($placeCompactName, $compactQuery) || str_contains($placeCompactSlug, $compactQuery))) {
                foreach ($nameTokens as $tok) {
                    if (str_contains($compactQuery, $tok)) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    /**
     * @param Collection<int, PhotocopyPlace> $places
     */
    protected function isSpecificShopQuery(string $rawQuery, Collection $places): bool
    {
        return $this->findMatchedSpecificStores($rawQuery, $places)->isNotEmpty();
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
