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

        // Search by keyword in name, location, or description
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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

        $shops = $query->paginate((int)$request->query('per_page', 15));

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

