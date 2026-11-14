<?php

namespace App\Http\Controllers;

use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use Illuminate\Http\Request;

class PhotocopyController extends Controller
{
    /**
     * Display a listing of photocopy places with search and sideways cards.
     */
    public function index(Request $request)
    {
        $search = $request->input('q');
        $filter = $request->input('status'); // 'all', 'open'

        $query = PhotocopyPlace::with('items')->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('items', function ($itemQ) use ($search) {
                      $itemQ->where('name', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                  });
            });
        }

        $allPlaces = $query->orderBy('name')->get();

        // Separate into currently open places for horizontal scrolling section
        $openPlaces = $allPlaces->filter(function ($place) {
            return $place->isOpenNow();
        });

        // If user specifically asked for only open places
        $places = ($filter === 'open') ? $openPlaces : $allPlaces;

        $openCount = $allPlaces->filter(fn($p) => $p->isOpenNow())->count();
        $totalCount = $allPlaces->count();

        return view('public.fotocopy.index', [
            'places' => $places,
            'openPlaces' => $openPlaces,
            'search' => $search,
            'filter' => $filter,
            'openCount' => $openCount,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Display the specified photocopy place details.
     */
    public function show($id)
    {
        $place = PhotocopyPlace::with(['items', 'services', 'reviews'])->findOrFail($id);

        if ($place->items->isEmpty() || $place->services->isEmpty()) {
            $place->seedDefaultItemsAndServices();
            $place->load(['items', 'services']);
        }

        $isOpen = $place->isOpenNow();
        $itemsByCategory = $place->items->where('is_available', true)->groupBy('category');

        // Other nearby / recommended places
        $nearbyPlaces = PhotocopyPlace::where('id', '!=', $place->id)
            ->where('is_active', true)
            ->limit(3)
            ->get();

        return view('public.fotocopy.show', [
            'place' => $place,
            'isOpen' => $isOpen,
            'itemsByCategory' => $itemsByCategory,
            'nearbyPlaces' => $nearbyPlaces,
        ]);
    }

    /**
     * Display the order page for a specific photocopy place.
     */
    public function order($id)
    {
        $place = PhotocopyPlace::with('items')->findOrFail($id);

        if ($place->items->isEmpty()) {
            $place->seedDefaultItemsAndServices();
            $place->load('items');
        }

        $isOpen = $place->isOpenNow();
        $itemsByCategory = $place->items->where('is_available', true)->groupBy('category');

        return view('public.fotocopy.order', [
            'place' => $place,
            'isOpen' => $isOpen,
            'itemsByCategory' => $itemsByCategory,
        ]);
    }
}

