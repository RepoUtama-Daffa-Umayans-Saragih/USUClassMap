<?php

namespace App\Services\Search;

use App\Models\Building;

/**
 * Engine 1: QueryIntentParserService
 *
 * Parses Indonesian conversational queries into structured multi-domain intents
 * (photocopy/ATK search, spatial landmark guessing, route navigation, academic schedule,
 * or general campus Q&A) with synonym normalization, typo tolerance, full 77-building
 * alias matching, price/proximity modifier extraction, and spatial constraint extraction.
 */
class QueryIntentParserService
{
    /**
     * Request-level cache of campus buildings to avoid repeated DB queries.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected static ?array $cachedBuildings = null;

    /**
     * Indonesian conversational filler words removed when building clean search tokens.
     *
     * @var array<int, string>
     */
    protected array $fillers = [
        'saya', 'aku', 'gue', 'gw', 'kami', 'kita', 'kamu', 'anda',
        'ingatnya', 'ingetnya', 'ingat', 'kayaknya', 'kayak', 'sepertinya', 'rasanya',
        'tolong', 'carikan', 'cari', 'cariin', 'nyari', 'belikan', 'beli', 'butuh', 'perlu',
        'dimana', 'mana', 'dimanakah', 'di', 'ke', 'dari', 'pada', 'untuk', 'buat', 'dengan', 'dan',
        'yang', 'yg', 'ada', 'gak', 'ga', 'nggak', 'ngga', 'tidak', 'gk', 'g', 'bukan',
        'mau', 'ingin', 'pengen', 'mohon', 'bantu', 'bantuin', 'tunjukkan', 'tunjukin',
        'lihat', 'tampilkan', 'cek', 'coba', 'kira', 'kirakira', 'nih', 'deh', 'dong', 'yah',
        'ya', 'sih', 'aja', 'saja', 'gitu', 'begitu', 'tuh', 'kok', 'kah', 'oh', 'wah', 'eh',
        'nya', 'itu', 'ini', 'adalah', 'yaitu', 'atau', 'atay', 'sama', 'tapi', 'cuma',
        'tempat', 'toko', 'kios', 'warung', 'gedung', 'gedungnya', 'fakultas', 'fakultasnya',
        'ruang', 'ruangan', 'kelas', 'kelasku', 'kelasnya', 'kasih', 'tau', 'tahu',
    ];

    /**
     * Canonical synonym mapping for Indonesian ATK, photocopy, and color terms.
     *
     * @var array<string, string>
     */
    protected array $synonyms = [
        'pena'         => 'pulpen',
        'bolpen'       => 'pulpen',
        'bulpen'       => 'pulpen',
        'balpoin'      => 'pulpen',
        'ballpoint'    => 'pulpen',
        'polpen'       => 'pulpen',
        'pen'          => 'pulpen',
        'pulpennya'    => 'pulpen',
        'fotokopi'     => 'fotocopy',
        'fotocopy'     => 'fotocopy',
        'photocopy'    => 'fotocopy',
        'potokopi'     => 'fotocopy',
        'fotocopi'     => 'fotocopy',
        'fotokop'      => 'fotocopy',
        'fc'           => 'fotocopy',
        'percetakan'   => 'print',
        'ngeprint'     => 'print',
        'nge-print'    => 'print',
        'cetak'        => 'print',
        'cetakin'      => 'print',
        'printing'     => 'print',
        'stabilo'      => 'highlighter',
        'hvs'          => 'hvs',
        'kertasnya'    => 'kertas',
        'stofmap'      => 'stopmap',
        'stopmap'      => 'stopmap',
        'snelhecter'   => 'stopmap',
        'spidol'       => 'spidol',
        'marker'       => 'spidol',
        'penggaris'    => 'penggaris',
        'mistar'       => 'penggaris',
        'pensil'       => 'pensil',
        'potlot'       => 'pensil',
        'penghapus'    => 'penghapus',
        'stip'         => 'penghapus',
        'tipex'        => 'correction',
        'tip-x'        => 'correction',
        'tipe-x'       => 'correction',
        'tipx'         => 'correction',
        'tipeks'       => 'correction',
        'stapler'      => 'stapler',
        'staples'      => 'stapler',
        'hekter'       => 'stapler',
        'hecter'       => 'stapler',
        'lakban'       => 'lakban',
        'isolasi'      => 'lakban',
        'selotip'      => 'lakban',
        'solatip'      => 'lakban',
        'materai'      => 'materai',
        'meterai'      => 'materai',
        'laminasi'     => 'laminating',
        'laminating'   => 'laminating',
        'jilid'        => 'jilid',
        'penjilidan'   => 'jilid',
        'binding'      => 'jilid',
        'jambu'        => 'pink',
        'rose'         => 'pink',
        'magenta'      => 'pink',
        'ijo'          => 'hijau',
        'item'         => 'hitam',
        'dongker'      => 'biru',
        'violet'       => 'ungu',
        'orange'       => 'oranye',
        'oren'         => 'oranye',
        'orenji'       => 'oranye',
        'deket'        => 'dekat',
        'sebrang'      => 'seberang',
        'sebelah'      => 'samping',
        'bersebelahan' => 'samping',
        'sebelahan'    => 'samping',
        'hadapan'      => 'depan',
        'berhadapan'   => 'depan',
        'dibawah'      => 'dibawah',
        'murmer'       => 'murah',
        'perpus'       => 'perpustakaan',
    ];

    /**
     * Canonical photocopy/ATK item keywords.
     *
     * @var array<int, string>
     */
    protected array $itemKeywords = [
        'pulpen', 'pensil', 'kertas', 'hvs', 'print', 'fotocopy', 'jilid',
        'spidol', 'highlighter', 'binder', 'buku', 'stopmap', 'materai',
        'laminating', 'scan', 'stapler', 'penggaris', 'penghapus', 'lakban',
        'skripsi', 'foto', 'pasfoto', 'amplop', 'klip', 'paperclip', 'folio',
        'notebook', 'note', 'sticky', 'correction', 'atk', 'rautan', 'lem',
        'gunting', 'cutter', 'kalkulator', 'tinta', 'spanduk', 'banner',
        'stiker', 'kartu', 'poster', 'brosur', 'kuarto', 'duplex', 'hardcover',
        'softcover', 'spiral', 'flashdisk', 'cd', 'dvd', 'idcard', 'lanyard',
        'mika', 'bufalo', 'buffalo', 'karton', 'doubletape', 'notes', 'postit',
    ];

    /**
     * Attribute/color/specification keywords for ATK & photocopy services.
     *
     * @var array<int, string>
     */
    protected array $attributeKeywords = [
        'pink', 'merah', 'biru', 'hitam', 'hijau', 'kuning', 'ungu', 'oranye',
        'putih', 'coklat', 'abu', 'pastel', 'transparan', 'bening', 'emas',
        'warna', 'gel', '0.5mm', '0.7mm', '0.38mm', 'a4', 'f4', 'a3', 'a5',
        'b5', '70gsm', '75gsm', '80gsm', '100gsm', 'hardcover', 'softcover',
        'spiral', 'lakban', 'glossy', 'matte', '2b', 'hb', 'tebal',
        'bolak-balik', 'duplex', 'polos', 'garis', 'kancing', 'resleting',
        'standard', 'joyko', 'kenko', 'snowman', 'faster', 'zebra', 'sarasa',
        'faber-castell', 'pilot', 'deli', 'paperone', 'sidu', 'sinar dunia',
        'bindex', 'epson', 'canon', '10000',
    ];

    /**
     * Price preference tokens.
     *
     * @var array<int, string>
     */
    protected array $cheapTokens = [
        'murah', 'termurah', 'hemat', 'terjangkau', 'ekonomis', 'budget', 'miring',
    ];

    /**
     * Proximity preference tokens.
     *
     * @var array<int, string>
     */
    protected array $proximityTokens = [
        'terdekat', 'dekat', 'deket', 'sekitar', 'sekitaran',
    ];

    /**
     * Academic days map.
     *
     * @var array<string, string>
     */
    protected array $daysMap = [
        'senin'  => 'Senin',
        'selasa' => 'Selasa',
        'rabu'   => 'Rabu',
        'kamis'  => 'Kamis',
        'jumat'  => 'Jumat',
        "jum'at" => 'Jumat',
        'sabtu'  => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    /**
     * Parse raw Indonesian natural-language query into structured intent & entities.
     *
     * @param string $rawQuery
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function parse(string $rawQuery, array $context = []): array
    {
        $rawQuery = trim($rawQuery);
        $normalized = $this->normalizeText($rawQuery);
        $rawTokens = $this->tokenize($normalized);
        $canonicalTokens = $this->canonicalizeTokens($rawTokens);
        $canonicalQuery = implode(' ', $canonicalTokens);

        $searchTokens = array_values(array_filter(
            $canonicalTokens,
            fn (string $t): bool => $t !== '' && !in_array($t, $this->fillers, true)
        ));

        // 1. Price, Max Price, Proximity, Open-Now, Bulk extraction
        $maxPrice = $this->extractMaxPrice($normalized);
        $pricePreference = $this->extractPricePreference($canonicalTokens, $normalized, $maxPrice);
        $proximityPreference = $this->extractProximityPreference($canonicalTokens, $normalized);
        $openNow = (bool) preg_match('/\b(buka\s+sekarang|yang\s+buka|masih\s+buka|buka\s+24\s*jam|sedang\s+buka|open\s+now)\b/i', $normalized);
        $bulkRequested = (bool) preg_match('/\b(box|kotak|lusin|lusinan|rim|pak|pack|dus|grosir|karton|kodi|isi\s*\d+)\b/i', $normalized);

        // 2. Day extraction
        $day = $this->extractDay($canonicalTokens, $context['day'] ?? null);

        // 3. Item terms & Attributes for Photocopy/ATK
        [$itemTerms, $attributes] = $this->extractItemTermsAndAttributes($canonicalTokens, $normalized);

        // 4. Spatial constraints & Mentioned buildings across all 77 buildings
        $buildings = $this->getBuildingsCatalog();
        $spatialConstraints = $this->extractSpatialConstraints($normalized, $canonicalQuery, $buildings);
        $mentionedBuildings = $this->extractMentionedBuildings($normalized, $canonicalQuery, $buildings);

        // If user asked for photocopy near/depan/samping a landmark, capture anchor_landmark
        $anchorLandmark = null;
        if (!empty($itemTerms) || preg_match('/\b(fotocopy|percetakan|toko|atk|alat\s+tulis)\b/i', $canonicalQuery)) {
            if (!empty($spatialConstraints)) {
                $firstConstraint = $spatialConstraints[0];
                $anchorBuildings = $firstConstraint['buildings'] ?? ($firstConstraint['buildings_a'] ?? []);
                if (!empty($anchorBuildings)) {
                    $anchorLandmark = $anchorBuildings[0];
                }
            } elseif (!empty($mentionedBuildings)) {
                $anchorLandmark = $mentionedBuildings[0];
            }
        }

        // 5. Course/academic terms (stripped of spatial phrases, landmarks, days, fillers)
        $courseTerms = $this->extractCourseTerms($searchTokens, $itemTerms, $attributes, $mentionedBuildings, $day);

        // 6. Intent Classification
        [$primaryIntent, $secondaryIntents] = $this->classifyIntents(
            $normalized,
            $canonicalQuery,
            $itemTerms,
            $attributes,
            $spatialConstraints,
            $mentionedBuildings,
            $day,
            $courseTerms,
            $pricePreference,
            $proximityPreference
        );

        return [
            'raw_query'            => $rawQuery,
            'normalized_query'     => $normalized,
            'canonical_query'      => $canonicalQuery,
            'tokens'               => $searchTokens,
            'canonical_tokens'     => $canonicalTokens,
            'primary_intent'       => $primaryIntent,
            'secondary_intents'    => $secondaryIntents,
            'item_terms'           => $itemTerms,
            'attributes'           => $attributes,
            'price_preference'     => $pricePreference,
            'max_price'            => $maxPrice,
            'proximity_preference' => $proximityPreference,
            'open_now'             => $openNow,
            'bulk_requested'       => $bulkRequested,
            'anchor_landmark'      => $anchorLandmark,
            'spatial_constraints'  => $spatialConstraints,
            'mentioned_buildings'  => $mentionedBuildings,
            'day'                  => $day,
            'course_terms'         => $courseTerms,
        ];
    }

    /**
     * Return all 77 buildings formatted for fast matching & spatial reasoning.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBuildingsCatalog(): array
    {
        if (self::$cachedBuildings !== null && !app()->runningUnitTests()) {
            return self::$cachedBuildings;
        }

        $rows = Building::query()
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'faculty', 'category', 'latitude', 'longitude', 'entrance_lat', 'entrance_lng']);

        $catalog = $rows->map(fn (Building $b): array => [
            'id'           => (int) $b->id,
            'name'         => (string) $b->name,
            'code'         => (string) $b->code,
            'faculty'      => (string) ($b->faculty ?? ''),
            'category'     => (string) ($b->category ?? ''),
            'latitude'     => (float) $b->latitude,
            'longitude'    => (float) $b->longitude,
            'entrance_lat' => $b->entrance_lat !== null ? (float) $b->entrance_lat : null,
            'entrance_lng' => $b->entrance_lng !== null ? (float) $b->entrance_lng : null,
        ])->all();

        self::$cachedBuildings = $catalog;

        return $catalog;
    }

    /**
     * Clear static cache (useful across unit/feature test database resets).
     */
    public static function flushCache(): void
    {
        self::$cachedBuildings = null;
    }

    protected function normalizeText(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');

        // Expand common Indonesian shorthand & multi-word expressions
        $replacements = [
            '/\bd\s+bawah\b/u'      => 'di bawah',
            '/\bdibawah\b/u'        => 'di bawah',
            '/\bdi\s*bawah\b/u'     => 'di bawah',
            '/\bmerah\s+muda\b/u'   => 'pink',
            '/\bsinar\s+dunia\b/u'  => 'sidu',
            '/\bpaper\s+one\b/u'    => 'paperone',
            '/\bbolak\s+balik\b/u'  => 'bolak-balik',
            '/\btip[\s\-]?x\b/u'    => 'tipex',
            '/\bhard\s+cover\b/u'   => 'hardcover',
            '/\bsoft\s+cover\b/u'   => 'softcover',
            '/\bfoto\s+kopi\b/u'    => 'fotocopy',
            '/\bfoto\s+copy\b/u'    => 'fotocopy',
            '/\bpaling\s+murah\b/u' => 'termurah',
            '/\bpaling\s+dekat\b/u' => 'terdekat',
            '/\balat\s+tulis\b/u'   => 'atk',
            '/\b0[,.]5\s*mm\b/u'    => '0.5mm',
            '/\b0[,.]7\s*mm\b/u'    => '0.7mm',
            '/\bpas\s+foto\b/u'     => 'pasfoto',
            '/\bsticky\s+note\b/u'  => 'sticky',
            '/\bfasilkom\s*[- ]\s*ti\b/u' => 'fasilkom-ti',
            '/\bperpus\s+usu\b/u'   => 'perpustakaan usu',
            '/\bgedung\s+pancasila\b/u' => 'gedung pancasila',
        ];

        $text = preg_replace(array_keys($replacements), array_values($replacements), $lower) ?? $lower;

        // Clean punctuation except dots inside decimals, hyphens, and <
        $text = preg_replace('/[^\p{L}\p{N}\s.\-<]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(fn (string $t): string => trim($t, '.'), $parts));
    }

    /**
     * Apply synonym map and safe Levenshtein typo correction to tokens.
     *
     * @param array<int, string> $tokens
     * @return array<int, string>
     */
    protected function canonicalizeTokens(array $tokens): array
    {
        $protected = array_merge($this->fillers, [
            'fib', 'feb', 'ft', 'fh', 'fk', 'fp', 'ff', 'fkg', 'fkm', 'fmipa', 'mipa',
            'fisip', 'fpsi', 'fkep', 'fhut', 'gkb', 'gdp', 'dlcb', 'vokasi', 'fasilkom',
            'fasilkom-ti', 'ilkom', 'rektorat', 'auditorium', 'perpustakaan', 'pancasila',
            'depan', 'samping', 'belakang', 'antara', 'seberang', 'dekat', 'bawah',
            'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu', 'mkwu', 'mkwk',
        ]);

        $canonical = [];
        foreach ($tokens as $tok) {
            if ($tok === '') {
                continue;
            }

            if (isset($this->synonyms[$tok])) {
                $canonical[] = $this->synonyms[$tok];
                continue;
            }

            // Do not fuzzy-alter protected campus abbreviations, digits, or short tokens <= 3
            if (in_array($tok, $protected, true) || strlen($tok) <= 3 || preg_match('/^\d/', $tok)) {
                $canonical[] = $tok;
                continue;
            }

            $matched = $this->fuzzyMatchToken($tok);
            $canonical[] = $matched ?? $tok;
        }

        return $canonical;
    }

    protected function fuzzyMatchToken(string $token): ?string
    {
        // Only fuzzy-match against ATK/item/attribute canonical dictionaries when safely unambiguous
        $candidates = array_merge($this->itemKeywords, ['murah', 'termurah', 'terdekat', 'buka', 'samping', 'depan', 'belakang']);
        $len = strlen($token);
        $maxDist = $len >= 6 ? 2 : 1;

        $bestMatch = null;
        $bestDist = $maxDist + 1;

        foreach ($candidates as $cand) {
            if (abs(strlen($cand) - $len) > $maxDist) {
                continue;
            }
            $dist = levenshtein($token, $cand);
            if ($dist <= $maxDist && $dist < $bestDist) {
                $bestDist = $dist;
                $bestMatch = $cand;
            }
        }

        return $bestMatch;
    }

    protected function extractMaxPrice(string $normalized): ?int
    {
        if (preg_match('/(?:di\s+bawah|maks|maksimal|max|under|kurang\s+dari|<)\s*(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)?\b/i', $normalized, $m)) {
            $numStr = $m[1];
            $suffix = strtolower($m[2] ?? '');

            if ($suffix !== '') {
                $numStr = str_replace(',', '.', $numStr);
                return (int) round(((float) $numStr) * 1000);
            }

            $cleanInt = (int) str_replace(['.', ','], '', $numStr);
            return $cleanInt > 0 ? $cleanInt : null;
        }

        return null;
    }

    /**
     * @param array<int, string> $canonicalTokens
     */
    protected function extractPricePreference(array $canonicalTokens, string $normalized, ?int $maxPrice): ?string
    {
        foreach ($canonicalTokens as $tok) {
            if (in_array($tok, $this->cheapTokens, true)) {
                return 'cheapest';
            }
        }
        if ($maxPrice !== null) {
            return 'cheapest';
        }
        if (preg_match('/\b(termahal|premium|terbaik|paling\s+bagus)\b/i', $normalized)) {
            return 'premium';
        }

        return null;
    }

    /**
     * @param array<int, string> $canonicalTokens
     */
    protected function extractProximityPreference(array $canonicalTokens, string $normalized): ?string
    {
        foreach ($canonicalTokens as $tok) {
            if (in_array($tok, $this->proximityTokens, true)) {
                return 'nearest';
            }
        }
        if (preg_match('/\b(paling\s+dekat|dekat\s+sini|dari\s+sini|sekitar\s+sini)\b/i', $normalized)) {
            return 'nearest';
        }

        return null;
    }

    /**
     * @param array<int, string> $canonicalTokens
     */
    protected function extractDay(array $canonicalTokens, mixed $overrideDay = null): ?string
    {
        if (is_string($overrideDay) && trim($overrideDay) !== '') {
            $lower = mb_strtolower(trim($overrideDay), 'UTF-8');
            if (isset($this->daysMap[$lower])) {
                return $this->daysMap[$lower];
            }
        }

        foreach ($canonicalTokens as $tok) {
            if (isset($this->daysMap[$tok])) {
                return $this->daysMap[$tok];
            }
        }

        return null;
    }

    /**
     * Extract canonical ATK/photocopy item terms and descriptive attributes.
     *
     * @param array<int, string> $canonicalTokens
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function extractItemTermsAndAttributes(array $canonicalTokens, string $normalized): array
    {
        $items = [];
        $attrs = [];

        // Special guard for "map": only treat as ATK stopmap if ATK cues accompany it
        $hasAtkContext = preg_match('/\b(beli|harga|toko|fotocopy|print|kertas|plastik|kancing|coklat|folio|a4|snelhecter|murah|pulpen|spidol)\b/i', $normalized);

        foreach ($canonicalTokens as $tok) {
            if (in_array($tok, $this->itemKeywords, true)) {
                $items[] = $tok;
                continue;
            }

            if ($tok === 'map' && $hasAtkContext) {
                $items[] = 'stopmap';
                continue;
            }

            if (in_array($tok, $this->attributeKeywords, true)) {
                $attrs[] = $tok;
            }
        }

        // Handle "print warna" / "kertas warna" vs colored item: keep both item & attribute cleanly
        // Special phrase checks
        if (preg_match('/\bmap\s+(plastik|kancing|coklat|kertas|folio|snelhecter)\b/i', $normalized)) {
            $items[] = 'stopmap';
        }
        if (preg_match('/\bhard\s*cover\b|\bhardcover\b/i', $normalized)) {
            $items[] = 'jilid';
            $attrs[] = 'hardcover';
        }
        if (preg_match('/\bsoft\s*cover\b|\bsoftcover\b/i', $normalized)) {
            $items[] = 'jilid';
            $attrs[] = 'softcover';
        }

        $items = array_values(array_unique($items));
        $attrs = array_values(array_unique($attrs));

        // Do not double-count terms that appear in $items as missing $attrs
        $attrs = array_values(array_diff($attrs, $items));

        return [$items, $attrs];
    }

    /**
     * Extract spatial relation constraints (between, front, side, back, near)
     * plus resolved USU campus landmark building groups.
     *
     * @param array<int, array<string, mixed>> $buildings
     * @return array<int, array<string, mixed>>
     */
    protected function extractSpatialConstraints(string $normalized, string $canonicalQuery, array $buildings): array
    {
        $constraints = [];

        // Build landmark pattern helper regex
        // First handle "antara <A> dan <B>"
        if (preg_match('/\b(?:di\s+)?(?:antara|tengah(?:-tengah)?)\s+([a-z0-9\-\s]{2,45}?)\s+(?:dan|sama|&|dengan)\s+([a-z0-9\-\s]{2,45}?)(?=$|[,.]|\s+(?:di\s+|dekat|samping|sebelah|depan|belakang|hari|jam|kelas|matkul|yang|untuk|ya|dong|nih))/i', $normalized, $bm)) {
            $rawA = trim($bm[1]);
            $rawB = trim($bm[2]);
            $resolvedA = $this->resolveLandmarkPhrase($rawA, $buildings);
            $resolvedB = $this->resolveLandmarkPhrase($rawB, $buildings);

            if (!empty($resolvedA['buildings']) && !empty($resolvedB['buildings'])) {
                $constraints[] = [
                    'relation'    => 'between',
                    'raw_phrase'  => trim($bm[0]),
                    'raw_a'       => $rawA,
                    'raw_b'       => $rawB,
                    'buildings_a' => $resolvedA['buildings'],
                    'buildings_b' => $resolvedB['buildings'],
                    'buildings'   => array_values(array_merge($resolvedA['buildings'], $resolvedB['buildings'])),
                ];
            }
        }

        // Next extract ordered directional clauses: depan, seberang, samping, sebelah, belakang, dekat, sekitar
        $relationRegex = '/\b(?:di\s+)?(depan|seberang|sebrang|hadapan|berhadapan\s+dengan|menghadap|samping|sebelah|bersebelahan\s+dengan|sebelahan|belakang|balik|dekat|deket|sekitar|sekitaran|tidak\s+jauh\s+dari|tak\s+jauh\s+dari)\s+([a-z0-9\-\s]{1,45}?)(?=\s+(?:di\s+)?(?:depan|seberang|sebrang|hadapan|samping|sebelah|belakang|balik|dekat|deket|sekitar|sekitaran|antara|yang|hari|jam|matkul|mata\s+kuliah|kelas|atau|atay)\b|$)/iu';

        if (preg_match_all($relationRegex, $normalized, $matches, PREG_SET_ORDER)) {
            $preliminary = [];
            foreach ($matches as $m) {
                $relWord = trim($m[1]);
                $rawLandmark = trim($m[2]);

                // Trim trailing filler/day tokens from rawLandmark
                $rawLandmark = preg_replace('/\s+(?:ya|dong|nih|deh|sih|aja|gitu|tuh|senin|selasa|rabu|kamis|jumat|sabtu|minggu)$/i', '', $rawLandmark) ?? $rawLandmark;
                $rawLandmark = trim($rawLandmark);
                if ($rawLandmark === '') {
                    continue;
                }

                $relType = $this->mapRelationWord($relWord);
                $preliminary[] = [
                    'relation'     => $relType,
                    'relation_raw' => $relWord,
                    'raw_landmark' => $rawLandmark,
                ];
            }

            // Collect clear primary landmarks first so ambiguous wings like "fakultas B" can disambiguate
            $contextLandmarks = [];
            foreach ($preliminary as $item) {
                if (!$this->isAmbiguousWingPhrase($item['raw_landmark'])) {
                    $res = $this->resolveLandmarkPhrase($item['raw_landmark'], $buildings);
                    if (!empty($res['buildings'])) {
                        $contextLandmarks = array_merge($contextLandmarks, $res['buildings']);
                    }
                }
            }

            foreach ($preliminary as $item) {
                $res = $this->resolveLandmarkPhrase($item['raw_landmark'], $buildings, $contextLandmarks);
                if (!empty($res['buildings'])) {
                    $constraints[] = [
                        'relation'     => $item['relation'],
                        'relation_raw' => $item['relation_raw'],
                        'raw_landmark' => $item['raw_landmark'],
                        'label'        => $res['label'],
                        'is_ambiguous' => $res['is_ambiguous'],
                        'buildings'    => $res['buildings'],
                    ];
                }
            }
        }

        return $constraints;
    }

    protected function mapRelationWord(string $word): string
    {
        if (str_contains($word, 'depan') || str_contains($word, 'seberang') || str_contains($word, 'sebrang') || str_contains($word, 'hadapan') || str_contains($word, 'menghadap')) {
            return 'front';
        }
        if (str_contains($word, 'samping') || str_contains($word, 'sebelah')) {
            return 'side';
        }
        if (str_contains($word, 'belakang') || str_contains($word, 'balik')) {
            return 'back';
        }
        return 'near';
    }

    protected function isAmbiguousWingPhrase(string $phrase): bool
    {
        return (bool) preg_match('/^(?:fakultas|gedung|blok|sayap|unit)\s+[a-z0-9]{1,2}$/i', trim($phrase));
    }

    /**
     * Resolve a landmark phrase ("FIB", "FMIPA", "fakultas B", "perpus", "pancasila")
     * into exact Building rows from the 77-building database.
     *
     * @param array<int, array<string, mixed>> $buildings
     * @param array<int, array<string, mixed>> $contextLandmarks
     * @return array{label: string, is_ambiguous: bool, buildings: array<int, array<string, mixed>>}
     */
    public function resolveLandmarkPhrase(string $phrase, array $buildings, array $contextLandmarks = []): array
    {
        $clean = trim(mb_strtolower($phrase, 'UTF-8'));
        $clean = preg_replace('/\b(gedung|fakultas|universitas\s+sumatera\s+utara|usu)\b/i', ' ', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        // 1. Handle wing / block phrases like "fakultas B", "gedung B", "blok C"
        if (preg_match('/^(?:fakultas|gedung|blok|sayap|unit)\s+([a-z0-9]{1,2})$/i', trim($phrase), $wm)) {
            $wing = strtoupper($wm[1]);
            $wingMatches = array_values(array_filter($buildings, function (array $b) use ($wing): bool {
                $code = strtoupper($b['code']);
                return preg_match('/(?:^|\-|\s)' . preg_quote($wing, '/') . '$/i', $code) === 1;
            }));

            if (!empty($wingMatches)) {
                // If context landmark exists (e.g., FIB in "depan FIB di samping fakultas B"),
                // prefer same-faculty wing (FIB-B) or nearest wing to context landmark.
                if (!empty($contextLandmarks)) {
                    $ctx = $contextLandmarks[0];
                    $ctxCode = strtoupper(explode('-', (string) $ctx['code'])[0]);

                    $sameFaculty = array_values(array_filter($wingMatches, function (array $b) use ($ctxCode, $ctx): bool {
                        $bCode = strtoupper((string) $b['code']);
                        $bFac = strtoupper((string) $b['faculty']);
                        return str_starts_with($bCode, $ctxCode) || ($bFac !== '' && $bFac === strtoupper((string) $ctx['faculty'])) || ($bFac !== '' && str_contains($bFac, $ctxCode));
                    }));

                    if (!empty($sameFaculty)) {
                        return [
                            'label'        => "Gedung/Sayap {$wing} ({$sameFaculty[0]['code']})",
                            'is_ambiguous' => true,
                            'buildings'    => $sameFaculty,
                        ];
                    }

                    usort($wingMatches, function (array $a, array $b) use ($ctx): int {
                        $da = $this->haversine($ctx['latitude'], $ctx['longitude'], $a['latitude'], $a['longitude']);
                        $db = $this->haversine($ctx['latitude'], $ctx['longitude'], $b['latitude'], $b['longitude']);
                        return $da <=> $db;
                    });

                    return [
                        'label'        => "Gedung {$wing} ({$wingMatches[0]['code']})",
                        'is_ambiguous' => true,
                        'buildings'    => [$wingMatches[0]],
                    ];
                }

                return [
                    'label'        => "Gedung/Sayap {$wing}",
                    'is_ambiguous' => true,
                    'buildings'    => $wingMatches,
                ];
            }
        }

        // 2. Faculty & major landmark canonical mapping across all 77 buildings
        $facultyAnchorCodes = [
            'fib' => [
                'patterns' => ['fib', 'ilmu budaya', 'budaya', 'sastra'],
                'codes'    => ['FIB'],
                'label'    => 'FIB (Fakultas Ilmu Budaya)',
            ],
            'fmipa' => [
                'patterns' => ['fmipa', 'mipa', 'matematika dan ilmu pengetahuan alam', 'matematika'],
                'codes'    => ['FMIPA-U4', 'FMIPA-U5', 'PERPUS-MIPA'],
                'label'    => 'FMIPA (Fakultas MIPA USU)',
            ],
            'fasilkom' => [
                'patterns' => ['fasilkom-ti', 'fasilkom ti', 'fasilkom', 'fasilkomti', 'ilmu komputer', 'ilkom', 'ti', 'teknologi informasi'],
                'codes'    => ['FASILKOM-TI', 'GD-FASILKOM', 'FASILKOM-B'],
                'label'    => 'FASILKOM-TI',
            ],
            'feb' => [
                'patterns' => ['feb', 'ekonomi', 'ekonomi dan bisnis', 'bisnis', 'mm-feb', 'magister manajemen'],
                'codes'    => ['FEB', 'MM-FEB', 'FEB-GL', 'FEB-GB'],
                'label'    => 'FEB (Fakultas Ekonomi dan Bisnis)',
            ],
            'ft' => [
                'patterns' => ['ft', 'teknik', 'teknik mesin', 'teknik kimia', 'teknik elektro', 'teknik sipil', 'teknik industri', 'teknik lingkungan'],
                'codes'    => ['FT-MESIN', 'FT-KIMIA', 'FT-LINGKUNGAN', 'FT-ELEKTRO_K', 'FT-INDUSTRI_J', 'FT-SIPIL_SC'],
                'label'    => 'FT (Fakultas Teknik)',
            ],
            'fh' => [
                'patterns' => ['fh', 'hukum', 'fh-gb'],
                'codes'    => ['FH', 'FH-GB'],
                'label'    => 'FH (Fakultas Hukum)',
            ],
            'fk' => [
                'patterns' => ['fk', 'kedokteran', 'kedokteran umum'],
                'codes'    => ['FK'],
                'label'    => 'FK (Fakultas Kedokteran)',
            ],
            'fkg' => [
                'patterns' => ['fkg', 'kedokteran gigi'],
                'codes'    => ['FKG'],
                'label'    => 'FKG (Fakultas Kedokteran Gigi)',
            ],
            'fkm' => [
                'patterns' => ['fkm', 'kesehatan masyarakat'],
                'codes'    => ['FKM', 'FKM-B', 'FKM-E'],
                'label'    => 'FKM (Fakultas Kesehatan Masyarakat)',
            ],
            'ff' => [
                'patterns' => ['ff', 'farmasi'],
                'codes'    => ['FF', 'FF-LT.2'],
                'label'    => 'FF (Fakultas Farmasi)',
            ],
            'fisip' => [
                'patterns' => ['fisip', 'ilmu sosial', 'sosial dan ilmu politik', 'msp', 'tax center'],
                'codes'    => ['FISIP', 'TAX-CENTER', 'MSP-FISIP', 'FISIP-MKWU'],
                'label'    => 'FISIP',
            ],
            'fp' => [
                'patterns' => ['fp', 'pertanian', 'keteknikan pertanian', 'agro'],
                'codes'    => ['FP-TEKNIK', 'FP-KOMPOS', 'FP-MKWU'],
                'label'    => 'FP (Fakultas Pertanian)',
            ],
            'fpsi' => [
                'patterns' => ['fpsi', 'psikologi', 'psi usu'],
                'codes'    => ['FPSI', 'FPSI-C'],
                'label'    => 'FPSI (Fakultas Psikologi)',
            ],
            'fkep' => [
                'patterns' => ['fkep', 'keperawatan'],
                'codes'    => ['FKEP'],
                'label'    => 'FKEP (Fakultas Keperawatan)',
            ],
            'vokasi' => [
                'patterns' => ['vokasi', 'fv'],
                'codes'    => ['VOKASI'],
                'label'    => 'Fakultas Vokasi',
            ],
            'perpus' => [
                'patterns' => ['perpus', 'perpustakaan', 'perpus-usu'],
                'codes'    => ['PERPUS-USU'],
                'label'    => 'Perpustakaan USU',
            ],
            'gkb' => [
                'patterns' => ['gkb', 'kuliah bersama'],
                'codes'    => ['GKB'],
                'label'    => 'GKB (Gedung Kuliah Bersama)',
            ],
            'gdp' => [
                'patterns' => ['gdp', 'pancasila'],
                'codes'    => ['GDP'],
                'label'    => 'GDP (Gedung Pancasila)',
            ],
            'rektorat' => [
                'patterns' => ['rektorat', 'biro rektor'],
                'codes'    => ['REKTORAT'],
                'label'    => 'Gedung Rektorat USU',
            ],
            'auditorium' => [
                'patterns' => ['auditorium', 'audit'],
                'codes'    => ['AUDITORIUM'],
                'label'    => 'Auditorium USU',
            ],
            'lppm' => [
                'patterns' => ['lppm', 'lppm-usu', 'lembaga penelitian'],
                'codes'    => ['LPPM-USU'],
                'label'    => 'LPPM USU',
            ],
            'dlcb' => [
                'patterns' => ['dlcb'],
                'codes'    => ['DLCB'],
                'label'    => 'DLCB USU',
            ],
            'psi' => [
                'patterns' => ['psi-usu', 'pusat sistem informasi'],
                'codes'    => ['PSI-USU'],
                'label'    => 'PSI USU',
            ],
            'masjid' => [
                'patterns' => ['masjid', 'assyifa', 'as-syifa', 'alikhlash', 'al-ikhlas'],
                'codes'    => ['MSJ-ASSYIFA', 'MSJ-ALIKHLAS'],
                'label'    => 'Masjid Kampus USU',
            ],
        ];

        $phraseUpper = strtoupper(trim($phrase));

        // Direct exact code match first (e.g., "FIB-B", "FMIPA-U4", "FT-MESIN")
        $directCode = array_values(array_filter($buildings, function (array $b) use ($phraseUpper, $clean): bool {
            $bCode = strtoupper((string) $b['code']);
            $bName = mb_strtolower((string) $b['name'], 'UTF-8');
            return $bCode === $phraseUpper || ($clean !== '' && str_contains($bName, $clean) && strlen($clean) >= 4);
        }));
        if (!empty($directCode)) {
            return [
                'label'        => $directCode[0]['name'] . ' (' . $directCode[0]['code'] . ')',
                'is_ambiguous' => false,
                'buildings'    => $directCode,
            ];
        }

        // Check faculty / landmark pattern dictionary
        foreach ($facultyAnchorCodes as $spec) {
            foreach ($spec['patterns'] as $pat) {
                if ($clean === $pat || mb_strtolower(trim($phrase), 'UTF-8') === $pat || preg_match('/\b' . preg_quote($pat, '/') . '\b/i', $phrase)) {
                    $matched = array_values(array_filter($buildings, function (array $b) use ($spec): bool {
                        return in_array(strtoupper((string) $b['code']), $spec['codes'], true);
                    }));
                    if (!empty($matched)) {
                        return [
                            'label'        => $spec['label'],
                            'is_ambiguous' => false,
                            'buildings'    => $matched,
                        ];
                    }
                }
            }
        }

        // Fuzzy code/name match against all 77 buildings
        $phraseTokens = $this->tokenize($clean);
        foreach ($phraseTokens as $pt) {
            if (strlen($pt) < 2) {
                continue;
            }
            $ptUpper = strtoupper($pt);
            foreach ($buildings as $b) {
                $bCode = strtoupper((string) $b['code']);
                if ($bCode === $ptUpper) {
                    return [
                        'label'        => $b['name'] . ' (' . $b['code'] . ')',
                        'is_ambiguous' => false,
                        'buildings'    => [$b],
                    ];
                }
            }
            foreach ($facultyAnchorCodes as $spec) {
                foreach ($spec['patterns'] as $pat) {
                    if ($pt === $pat || (strlen($pt) >= 4 && strlen($pat) >= 4 && levenshtein($pt, $pat) <= 1)) {
                        $matched = array_values(array_filter($buildings, function (array $b) use ($spec): bool {
                            return in_array(strtoupper((string) $b['code']), $spec['codes'], true);
                        }));
                        if (!empty($matched)) {
                            return [
                                'label'        => $spec['label'],
                                'is_ambiguous' => false,
                                'buildings'    => $matched,
                            ];
                        }
                    }
                }
            }
        }

        return [
            'label'        => $phrase,
            'is_ambiguous' => false,
            'buildings'    => [],
        ];
    }

    /**
     * Collect all campus buildings explicitly mentioned anywhere in the query.
     *
     * @param array<int, array<string, mixed>> $buildings
     * @return array<int, array<string, mixed>>
     */
    protected function extractMentionedBuildings(string $normalized, string $canonicalQuery, array $buildings): array
    {
        $found = [];
        $seenIds = [];

        // Check multi-word building codes or faculty names directly
        $tokens = $this->tokenize($normalized);
        $windows = $tokens;
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $windows[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }

        foreach ($windows as $chunk) {
            if (in_array($chunk, $this->fillers, true) || strlen($chunk) < 2) {
                continue;
            }
            $res = $this->resolveLandmarkPhrase($chunk, $buildings);
            foreach ($res['buildings'] as $b) {
                if (!isset($seenIds[$b['id']])) {
                    $seenIds[$b['id']] = true;
                    $found[] = $b;
                }
            }
        }

        return $found;
    }

    /**
     * Extract remaining meaningful academic course/lecturer terms.
     *
     * @param array<int, string> $searchTokens
     * @param array<int, string> $itemTerms
     * @param array<int, string> $attributes
     * @param array<int, array<string, mixed>> $mentionedBuildings
     * @return array<int, string>
     */
    protected function extractCourseTerms(
        array $searchTokens,
        array $itemTerms,
        array $attributes,
        array $mentionedBuildings,
        ?string $day
    ): array {
        $exclude = array_merge(
            $itemTerms,
            $attributes,
            $this->cheapTokens,
            $this->proximityTokens,
            ['depan', 'seberang', 'samping', 'sebelah', 'belakang', 'balik', 'antara', 'rute', 'arah', 'arahkan', 'navigasi', 'jalan', 'cara', 'buka', 'sekarang', 'matkul', 'mata', 'kuliah', 'jadwal', 'jadwalnya', 'hari', 'bawah', 'maks', 'maksimal', 'box', 'kotak', 'lusin', 'rim', 'pack', 'pak', 'grosir']
        );

        if ($day !== null) {
            $exclude[] = strtolower($day);
        }

        foreach ($mentionedBuildings as $b) {
            $exclude[] = strtolower((string) $b['code']);
            foreach (explode('-', strtolower((string) $b['code'])) as $part) {
                $exclude[] = $part;
            }
        }

        // Also exclude faculty shortcodes commonly attached
        $facultyWords = ['fib', 'fmipa', 'mipa', 'feb', 'fasilkom', 'fasilkom-ti', 'ft', 'fh', 'fk', 'fkg', 'fkm', 'fp', 'ff', 'fisip', 'fpsi', 'fkep', 'fhut', 'gkb', 'gdp', 'dlcb', 'vokasi', 'rektorat', 'auditorium', 'perpustakaan', 'b'];
        $exclude = array_unique(array_merge($exclude, $facultyWords));

        $terms = [];
        foreach ($searchTokens as $tok) {
            if (in_array($tok, $exclude, true)) {
                continue;
            }
            if (preg_match('/^\d+(?:rb|ribu|k)?$/i', $tok)) {
                continue;
            }
            if (strlen($tok) >= 3) {
                $terms[] = $tok;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * Classify primary intent and ordered secondary intents.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function classifyIntents(
        string $normalized,
        string $canonicalQuery,
        array $itemTerms,
        array $attributes,
        array $spatialConstraints,
        array $mentionedBuildings,
        ?string $day,
        array $courseTerms,
        ?string $pricePreference,
        ?string $proximityPreference
    ): array {
        $intents = [];

        $hasExplicitRouteVerb = (bool) preg_match('/\b(rute\s+ke|arahkan\s+(?:rute\s+)?ke|navigasi\s+ke|jalan\s+ke|cara\s+ke|arah\s+ke|antar\s+ke)\b/i', $normalized);
        $hasFotocopyCue = !empty($itemTerms)
            || (bool) preg_match('/\b(fotocopy|percetakan|toko|atk|alat\s+tulis)\b/i', $canonicalQuery)
            || (($pricePreference !== null) && !empty($itemTerms));

        $hasDirectionalSpatial = false;
        foreach ($spatialConstraints as $c) {
            if (in_array($c['relation'], ['front', 'side', 'back', 'between'], true)) {
                $hasDirectionalSpatial = true;
                break;
            }
        }
        $hasSpatialMemoryPhrase = (bool) preg_match('/\b(ingatnya|ingetnya|lupa\s+nama|gedung\s+(?:mana|apa|yg|yang)|sebelah\s+mana|di\s+mana\s+ya|patokan)\b/i', $normalized);

        if ($hasExplicitRouteVerb && (!empty($mentionedBuildings) || $hasFotocopyCue)) {
            $intents[] = 'route_navigation';
        }
        if ($hasFotocopyCue) {
            $intents[] = 'smart_fotocopy_search';
        }

        $hasAcademicCue = $day !== null
            || !empty($courseTerms)
            || (bool) preg_match('/\b(jadwal|kuliah|kelas|dosen|sks|mkwu|mkwk|semester|ruang|ruangan|matkul)\b/i', $normalized);

        if ($hasDirectionalSpatial || ($hasSpatialMemoryPhrase && !empty($spatialConstraints))) {
            $intents[] = 'spatial_building_finder';
        } elseif (!empty($spatialConstraints) && empty($courseTerms)) {
            $intents[] = 'spatial_building_finder';
        }

        if ($hasAcademicCue && empty($itemTerms) && !$hasDirectionalSpatial) {
            $intents[] = 'schedule_search';
        } elseif ($hasAcademicCue && $hasDirectionalSpatial) {
            $intents[] = 'schedule_search';
        }

        if (empty($intents)) {
            if ($proximityPreference !== null && empty($courseTerms) && empty($mentionedBuildings)) {
                $intents[] = 'smart_fotocopy_search';
            } elseif (!empty($mentionedBuildings) && empty($courseTerms)) {
                $intents[] = 'spatial_building_finder';
            } else {
                $intents[] = 'general_campus_qa';
            }
        }

        $intents = array_values(array_unique($intents));
        $primary = $intents[0];
        $secondary = array_slice($intents, 1);

        return [$primary, $secondary];
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
