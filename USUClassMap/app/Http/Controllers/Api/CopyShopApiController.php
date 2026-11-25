<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CopyShopResource;
use App\Models\PhotocopyPlace;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CopyShopApiController extends Controller
{
    /**
     * Display a listing of copy shops with geospatial/keyword filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PhotocopyPlace::with(['services', 'items', 'reviews'])
            ->where('is_active', true);

        // Search by keyword in name, location, or description (supports compact & multi-token store names)
        if ($search = $request->query('q')) {
            $trimSearch = trim((string) $search);
            $compactSearch = preg_replace('/[^a-z0-9]/i', '', mb_strtolower($trimSearch, 'UTF-8')) ?? '';
            $tokens = array_values(array_filter(
                preg_split('/[\s\-\(\)&\/.,]+/u', mb_strtolower($trimSearch, 'UTF-8')) ?: [],
                fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 2
            ));

            $query->where(function ($q) use ($trimSearch, $compactSearch, $tokens) {
                $q->where('name', 'like', "%{$trimSearch}%")
                    ->orWhere('location_name', 'like', "%{$trimSearch}%")
                    ->orWhere('description', 'like', "%{$trimSearch}%")
                    ->orWhere('slug', 'like', "%{$trimSearch}%");

                if (strlen($compactSearch) >= 4) {
                    $q->orWhereRaw(
                        "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '(', ''), ')', ''), '&', '')) LIKE ?",
                        ["%{$compactSearch}%"]
                    )->orWhereRaw(
                        "LOWER(REPLACE(slug, '-', '')) LIKE ?",
                        ["%{$compactSearch}%"]
                    );
                }

                if (count($tokens) >= 2) {
                    $q->orWhere(function ($tokenGroup) use ($tokens) {
                        foreach ($tokens as $token) {
                            $tokenGroup->where(function ($sub) use ($token) {
                                $sub->where('name', 'like', "%{$token}%")
                                    ->orWhere('location_name', 'like', "%{$token}%")
                                    ->orWhere('description', 'like', "%{$token}%")
                                    ->orWhere('slug', 'like', "%{$token}%");
                            });
                        }
                    });
                }
            });
        }

        // Filter by category or service slug
        if ($serviceSlug = $request->query('service')) {
            $query->whereHas('services', function ($q) use ($serviceSlug) {
                $q->where('slug', $serviceSlug);
            });
        }

        // Sorting
        $sortBy = $request->query('sort', 'rating');
        if ($sortBy === 'rating') {
            $query->orderByDesc('rating')->orderByDesc('user_rating_count');
        } elseif ($sortBy === 'name') {
            $query->orderBy('name');
        } else {
            $query->latest();
        }

        if ($request->boolean('all')) {
            return CopyShopResource::collection($query->get());
        }

        $perPage = min(500, max(1, (int) $request->query('per_page', 100)));
        $shops = $query->paginate($perPage);

        return CopyShopResource::collection($shops);
    }

    /**
     * Display detailed data for a single copy shop.
     */
    public function show($id): CopyShopResource
    {
        $shop = PhotocopyPlace::with(['services', 'items', 'reviews'])->findOrFail($id);

        if ($shop->items->isEmpty() || $shop->services->isEmpty()) {
            $shop->seedDefaultItemsAndServices();
            $shop->load(['services', 'items']);
        }

        return new CopyShopResource($shop);
    }

    /**
     * Return list of master services.
     */
    public function services(): JsonResponse
    {
        $services = Service::withCount('places')->get();

        return response()->json([
            'status' => 'success',
            'data' => $services,
        ]);
    }
}

