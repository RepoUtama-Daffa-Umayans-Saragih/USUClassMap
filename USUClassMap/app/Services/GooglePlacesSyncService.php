<?php

namespace App\Services;

use App\Models\PhotocopyPlace;
use App\Models\Service;
use App\Models\ShopReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GooglePlacesSyncService
{
    protected string $apiKey;
    protected float $centerLat;
    protected float $centerLng;
    protected float $searchRadius;

    /**
     * Service keywords dictionary mapped to Master Service slugs.
     */
    protected array $serviceKeywords = [
        'jilid-hard-cover-skripsi' => ['jilid skripsi', 'hardcover', 'hard cover', 'tinta emas', 'embos', 'skripsi', 'tesis', 'disertasi', 'penjilid buku'],
        'jilid-lakban-mika' => ['jilid lakban', 'lakban', 'mika', 'jilid biasa', 'jilid tugas', 'jilid makalah', 'penjilid'],
        'jilid-spiral-kawat-plastik' => ['spiral', 'spiral kawat', 'spiral plastik', 'ring kawat'],
        'print-dokumen-full-color' => ['print warna', 'cetak warna', 'full color', 'warna', 'laserjet warna', 'inkjet', 'printer digital'],
        'print-hitam-putih-a4f4' => ['print hitam putih', 'print b/w', 'print b&w', 'print teks', 'print skripsi', 'jasa ketik'],
        'fotokopi-hitam-putih' => ['fotokopi', 'foto copy', 'photocopy', 'fc', 'copy buku', 'modul', 'diktat', 'bolak balik', 'toko fotokopi'],
        'cetak-banner-poster-stiker' => ['banner', 'spanduk', 'poster', 'a3', 'a3+', 'albatros', 'stiker', 'sticker', 'vinyl', 'pabrik stiker', 'toko sablon', 'large format'],
        'scan-dokumen-ke-pdf' => ['scan', 'scanner', 'pdf', 'scan ijazah', 'scan transkrip'],
        'laminating-panas' => ['laminating', 'laminasi', 'press'],
        'perlengkapan-atk-mahasiswa' => ['atk', 'alat tulis', 'kertas', 'hvs', 'pulpen', 'binder', 'map', 'meterai', 'materai', 'amplop', 'toko alat tulis kantor', 'grosir alat-alat tulis'],
    ];

    public function __construct(
        ?string $apiKey = null,
        float $centerLat = 3.5629,
        float $centerLng = 98.6562,
        float $searchRadius = 2500.0
    ) {
        $this->apiKey = $apiKey ?: (string)config('services.serpapi.key', env('SERPAPI_KEY', ''));
        $this->centerLat = $centerLat;
        $this->centerLng = $centerLng;
        $this->searchRadius = $searchRadius;
    }

    /**
     * Execute sync using SerpApi Google Maps Engine.
     */
    public function sync(string $query = 'fotokopi percetakan sekitar USU Medan', bool $dryRun = false): array
    {
        $stats = [
            'query' => $query,
            'api_called' => false,
            'source' => 'serpapi_google_maps',
            'total_fetched' => 0,
            'matched_by_id' => 0,
            'matched_by_haversine' => 0,
            'created_new' => 0,
            'services_tagged' => 0,
            'reviews_saved' => 0,
            'dry_run' => $dryRun,
            'items' => [],
        ];

        // 1. Fetch places from SerpApi Google Maps Engine
        $rawPlaces = [];
        if (!empty($this->apiKey)) {
            $rawPlaces = $this->fetchFromSerpApi($query);
            $stats['api_called'] = true;
        } else {
            Log::warning("SerpApi Key is missing, falling back to local dataset.");
            $rawPlaces = $this->loadFallbackScrapedData();
        }

        $stats['total_fetched'] = count($rawPlaces);

        // Preload master services for quick NLP lookup
        $masterServices = Service::all()->keyBy('slug');

        foreach ($rawPlaces as $placePayload) {
            $result = $this->processSinglePlace($placePayload, $masterServices, $dryRun);

            $stats['items'][] = $result;
            if ($result['action'] === 'matched_id') {
                $stats['matched_by_id']++;
            } elseif ($result['action'] === 'matched_haversine') {
                $stats['matched_by_haversine']++;
            } elseif ($result['action'] === 'created') {
                $stats['created_new']++;
            }
            $stats['services_tagged'] += count($result['detected_services']);
            $stats['reviews_saved'] += $result['reviews_count'];
        }

        return $stats;
    }

    /**
     * Fetch places using SerpApi Google Maps Engine (GET https://serpapi.com/search.json).
     */
    protected function fetchFromSerpApi(string $query): array
    {
        $params = [
            'engine' => 'google_maps',
            'q' => $query,
            'll' => "@{$this->centerLat},{$this->centerLng},15z",
            'hl' => 'id',
            'gl' => 'id',
            'type' => 'search',
            'api_key' => $this->apiKey,
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(25)
                ->retry(3, 1000)
                ->get('https://serpapi.com/search.json', $params);

            if (!$response->successful()) {
                Log::error("SerpApi Google Maps request failed: HTTP {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return $this->loadFallbackScrapedData();
            }

            $json = $response->json();
            $localResults = $json['local_results'] ?? [];

            Log::info("SerpApi Google Maps Engine successfully retrieved " . count($localResults) . " local results.");

            return $localResults;
        } catch (\Throwable $e) {
            Log::error("Exception while calling SerpApi Google Maps Engine: " . $e->getMessage());
            return $this->loadFallbackScrapedData();
        }
    }

    /**
     * Process a single place record with safe-merge (Non-Destructive Upsert).
     */
    protected function processSinglePlace(array $p, $masterServices, bool $dryRun): array
    {
        // -------------------------------------------------------------
        // Step 1: Normalize fields from SerpApi Google Maps payload
        // -------------------------------------------------------------
        $googlePlaceId = $p['place_id'] ?? ($p['id'] ?? null);
        $displayName = $p['title'] ?? ($p['name'] ?? 'Tempat Fotokopi USU');
        $address = $p['address'] ?? ($p['formattedAddress'] ?? ($p['location_name'] ?? 'Sekitar Kampus USU, Medan'));
        
        $lat = isset($p['gps_coordinates']['latitude']) 
            ? (float)$p['gps_coordinates']['latitude'] 
            : (float)($p['location']['latitude'] ?? ($p['latitude'] ?? $this->centerLat));
        $lng = isset($p['gps_coordinates']['longitude']) 
            ? (float)$p['gps_coordinates']['longitude'] 
            : (float)($p['location']['longitude'] ?? ($p['longitude'] ?? $this->centerLng));

        $rating = isset($p['rating']) ? (float)$p['rating'] : 4.8;
        $userRatingCount = (int)($p['reviews'] ?? ($p['userRatingCount'] ?? 0));
        $businessStatus = $p['business_status'] ?? 'OPERATIONAL';

        // Directions link or fallback generator
        $googleMapsUri = $p['links']['directions'] 
            ?? $p['googleMapsUri'] 
            ?? (!empty($googlePlaceId) ? "https://www.google.com/maps/search/?api=1&query=Google&query_place_id={$googlePlaceId}" : null);

        // Contact info
        $phone = $p['phone'] ?? ($p['nationalPhoneNumber'] ?? null);
        $phoneNational = $phone;
        $phoneIntl = null;
        $whatsappUrl = null;
        $cleanWa = null;

        if (!empty($phone)) {
            $digits = preg_replace('/[^\d]/', '', (string)$phone);
            if (str_starts_with($digits, '08')) {
                $cleanWa = '62' . substr($digits, 1);
                $whatsappUrl = 'https://wa.me/' . $cleanWa;
                $phoneIntl = '+62 ' . substr($digits, 1);
            } elseif (str_starts_with($digits, '628')) {
                $cleanWa = $digits;
                $whatsappUrl = 'https://wa.me/' . $cleanWa;
                $phoneIntl = '+' . $digits;
            } else {
                $cleanWa = $digits;
                $phoneIntl = '+62 ' . $digits;
            }
        }

        // Website and Social Media auto-detection
        $websiteUri = $p['website'] ?? ($p['websiteUri'] ?? null);
        $instagramHandle = null;
        $facebookUrl = null;

        if (!empty($websiteUri)) {
            $lowerWeb = strtolower($websiteUri);
            if (str_contains($lowerWeb, 'instagram.com')) {
                $path = parse_url($websiteUri, PHP_URL_PATH);
                $cleanPath = trim((string)$path, '/');
                if (!empty($cleanPath)) {
                    $parts = explode('/', $cleanPath);
                    $instagramHandle = $parts[0] ?? $cleanPath;
                }
            } elseif (str_contains($lowerWeb, 'facebook.com')) {
                $facebookUrl = $websiteUri;
            }
        }

        // Operating Hours
        $operatingHours = $p['operating_hours'] ?? ($p['regularOpeningHours'] ?? null);

        // Review summary & keyword corpus compilation
        $snippet = $p['snippet'] ?? null;
        $description = $p['description'] ?? null;
        $type = $p['type'] ?? null;
        $types = (array)($p['types'] ?? []);
        $serviceOptions = (array)($p['service_options'] ?? []);

        $summaryParts = [];
        if (!empty($type)) $summaryParts[] = $type;
        if (!empty($types)) $summaryParts = array_merge($summaryParts, $types);
        if (!empty($serviceOptions)) $summaryParts[] = 'Layanan: ' . implode(', ', $serviceOptions);
        if (!empty($snippet)) $summaryParts[] = $snippet;
        if (!empty($description)) $summaryParts[] = $description;

        $reviewSummary = !empty($summaryParts) 
            ? implode(' • ', array_unique($summaryParts)) 
            : "Layanan fotokopi, percetakan dokumen, dan perlengkapan kuliah di area Universitas Sumatera Utara (USU).";

        $thumbnail = $p['thumbnail'] ?? null;

        // -------------------------------------------------------------
        // Step 2: Matching logic (google_place_id -> Exact Name -> Haversine)
        // -------------------------------------------------------------
        $targetPlace = null;
        $matchAction = 'created';
        $matchDistance = null;

        // 1. Try exact match by google_place_id
        if ($googlePlaceId) {
            $targetPlace = PhotocopyPlace::where('google_place_id', $googlePlaceId)->first();
            if ($targetPlace) {
                $matchAction = 'matched_id';
            }
        }

        // 2. Try matching by slug or exact name
        if (!$targetPlace) {
            $slug = Str::slug($displayName);
            $targetPlace = PhotocopyPlace::where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower(trim($displayName))])
                ->first();
            if ($targetPlace) {
                $matchAction = 'matched_name';
            }
        }

        // 3. If not matched, try Name Similarity (>75%) or Haversine distance (<30m)
        if (!$targetPlace) {
            $allExisting = PhotocopyPlace::all();
            $closest = null;
            $minDistance = 999999;
            $bestSimilarity = 0;
            $matchedBySimilarity = null;

            foreach ($allExisting as $existing) {
                similar_text(strtolower($displayName), strtolower($existing->name), $percent);
                if ($percent > 75 && $percent > $bestSimilarity) {
                    $bestSimilarity = $percent;
                    $matchedBySimilarity = $existing;
                }

                if ($existing->latitude && $existing->longitude) {
                    $dist = $this->calculateHaversineDistance($lat, $lng, (float)$existing->latitude, (float)$existing->longitude);
                    if ($dist < $minDistance) {
                        $minDistance = $dist;
                        $closest = $existing;
                    }
                }
            }

            if ($matchedBySimilarity) {
                $targetPlace = $matchedBySimilarity;
                $matchAction = 'matched_name_fuzzy';
            } elseif ($closest && $minDistance <= 30.0) {
                $targetPlace = $closest;
                $matchAction = 'matched_haversine';
                $matchDistance = round($minDistance, 1);
            }
        }

        // -------------------------------------------------------------
        // Step 3: NLP Parser for auto-detecting services
        // -------------------------------------------------------------
        $textCorpus = $displayName . ' ' . $address . ' ' . $reviewSummary;
        if (!empty($types)) $textCorpus .= ' ' . implode(' ', $types);
        if (!empty($serviceOptions)) $textCorpus .= ' ' . implode(' ', $serviceOptions);

        $detectedServices = $this->detectServicesFromText($textCorpus, $masterServices);

        // -------------------------------------------------------------
        // Step 4: Non-Destructive Upsert (Safe-Merge)
        // -------------------------------------------------------------
        if (!$dryRun) {
            if (!$targetPlace) {
                // Create new place if no match exists
                $slug = Str::slug($displayName);
                $uniqueSlug = $slug;
                $counter = 1;
                while (PhotocopyPlace::where('slug', $uniqueSlug)->exists()) {
                    $uniqueSlug = $slug . '-' . $counter++;
                }

                $targetPlace = new PhotocopyPlace();
                $targetPlace->name = $displayName;
                $targetPlace->slug = $uniqueSlug;
                $targetPlace->location_name = $address;
                $targetPlace->latitude = $lat;
                $targetPlace->longitude = $lng;
                $targetPlace->description = $reviewSummary;
                $targetPlace->image_url = $thumbnail ?: 'https://lh3.googleusercontent.com/grass-cs/AABkmLexafbYg6fhQG8T8dRkF5U07SmjGs7XIBgqEHBOfbjPtgXjnbPefvjjqYR6MS6VNm_1bKvBnh-T3SgoltyNxqREkphmBIQ59Q-mbPatxKmyuXsLZxMekQFkpWwsukHTJfqVTH0=w800-h600-k-no';
                $targetPlace->open_time = '08:00';
                $targetPlace->close_time = '21:00';
                $targetPlace->operating_days = 'Senin - Sabtu';
                $targetPlace->is_active = true;
                $targetPlace->phone = $phoneNational ?: '0812-6474-2121';
                $targetPlace->whatsapp = $cleanWa ?: '6281264742121';
                $targetPlace->whatsapp_url = $whatsappUrl ?: 'https://wa.me/6281264742121';
            }

            // Always assign google_place_id
            if ($googlePlaceId) {
                $targetPlace->google_place_id = $googlePlaceId;
            }

            // Dynamic fields from Google/SerpApi: ALWAYS update
            $targetPlace->rating = $rating;
            $targetPlace->user_rating_count = $userRatingCount ?: $targetPlace->user_rating_count;
            $targetPlace->business_status = $businessStatus;
            if ($googleMapsUri) {
                $targetPlace->google_maps_uri = $googleMapsUri;
                $targetPlace->google_maps_url = $googleMapsUri;
            }
            if ($reviewSummary) {
                $targetPlace->review_summary = $reviewSummary;
            }
            if ($operatingHours) {
                $targetPlace->opening_hours = $operatingHours;
            }
            $targetPlace->last_synced_at = Carbon::now('Asia/Jakarta');
            $targetPlace->is_verified = true;

            // SAFE-MERGE: Only fill contact info if currently EMPTY (protects manual edits)
            if (!$targetPlace->is_manually_verified) {
                if (empty($targetPlace->phone_national) && !empty($phoneNational)) {
                    $targetPlace->phone_national = $phoneNational;
                }
                if (empty($targetPlace->phone_international) && !empty($phoneIntl)) {
                    $targetPlace->phone_international = $phoneIntl;
                }
                if (empty($targetPlace->phone) && !empty($phoneNational)) {
                    $targetPlace->phone = $phoneNational;
                }
                if (empty($targetPlace->whatsapp_url) && !empty($whatsappUrl)) {
                    $targetPlace->whatsapp_url = $whatsappUrl;
                }
                if (empty($targetPlace->whatsapp) && !empty($cleanWa)) {
                    $targetPlace->whatsapp = $cleanWa;
                }
                if (empty($targetPlace->website_url) && !empty($websiteUri)) {
                    $targetPlace->website_url = $websiteUri;
                }
                if (empty($targetPlace->instagram) && !empty($instagramHandle)) {
                    $targetPlace->instagram = $instagramHandle;
                }
                if (empty($targetPlace->facebook) && !empty($facebookUrl)) {
                    $targetPlace->facebook = $facebookUrl;
                }
                if (empty($targetPlace->image_url) && !empty($thumbnail)) {
                    $targetPlace->image_url = $thumbnail;
                }
            }

            $targetPlace->save();

            // Attach auto-detected services with is_auto_detected = true without removing manual ones
            foreach ($detectedServices as $service) {
                $existingPivot = $targetPlace->services()->where('service_id', $service->id)->first();
                if (!$existingPivot) {
                    $targetPlace->services()->attach($service->id, [
                        'starting_price' => $this->getDefaultPriceForService($service->slug),
                        'unit' => 'lembar',
                        'notes' => 'Terdeteksi otomatis via SerpApi Google Maps Engine',
                        'is_auto_detected' => true,
                    ]);
                }
            }

            // Ensure dummy catalog items & default services if shop is empty
            if ($targetPlace->items()->count() === 0 || $targetPlace->services()->count() === 0) {
                $targetPlace->seedDefaultItemsAndServices();
            }
        }

        return [
            'place_id' => $googlePlaceId,
            'name' => $displayName,
            'address' => Str::limit($address, 40),
            'rating' => $rating,
            'user_rating_count' => $userRatingCount,
            'action' => $matchAction,
            'haversine_distance_m' => $matchDistance,
            'target_place_id' => $targetPlace?->id,
            'detected_services' => $detectedServices->pluck('name')->toArray(),
            'reviews_count' => 0,
        ];
    }

    /**
     * Light NLP keyword parser to detect services from place text corpus.
     */
    protected function detectServicesFromText(string $text, $masterServices)
    {
        $normalizedText = strtolower($text);
        $matchedServices = collect();

        foreach ($this->serviceKeywords as $serviceSlug => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($normalizedText, strtolower($kw))) {
                    if (isset($masterServices[$serviceSlug])) {
                        $matchedServices->push($masterServices[$serviceSlug]);
                    }
                    break;
                }
            }
        }

        return $matchedServices->unique('id');
    }

    /**
     * Haversine formula to calculate distance between two coordinates in meters.
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Estimated baseline starting price for auto-detected services.
     */
    protected function getDefaultPriceForService(string $slug): int
    {
        return match ($slug) {
            'print-hitam-putih-a4f4' => 250,
            'fotokopi-hitam-putih' => 200,
            'print-dokumen-full-color' => 1000,
            'jilid-lakban-mika' => 3500,
            'jilid-spiral-kawat-plastik' => 12000,
            'jilid-hard-cover-skripsi' => 35000,
            'cetak-banner-poster-stiker' => 15000,
            'scan-dokumen-ke-pdf' => 500,
            'laminating-panas' => 4000,
            'perlengkapan-atk-mahasiswa' => 3000,
            default => 500,
        };
    }

    /**
     * Fallback dataset in case network or API is offline.
     */
    protected function loadFallbackScrapedData(): array
    {
        $path = database_path('seeders/data/usu_photocopy_data.json');
        if (!file_exists($path)) {
            return [];
        }

        $items = json_decode(file_get_contents($path), true);
        if (!is_array($items)) {
            return [];
        }

        $converted = [];
        foreach ($items as $item) {
            $converted[] = [
                'place_id' => 'usu_place_' . Str::slug($item['name']),
                'title' => $item['name'],
                'address' => $item['location_name'],
                'gps_coordinates' => [
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                ],
                'rating' => $item['rating'] ?? 4.8,
                'reviews' => $item['user_rating_count'] ?? 50,
                'phone' => $item['phone'] ?? null,
                'thumbnail' => $item['image_url'] ?? null,
                'links' => [
                    'directions' => $item['google_maps_url'] ?? null,
                ],
            ];
        }

        return $converted;
    }
}
