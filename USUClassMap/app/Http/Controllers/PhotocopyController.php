<?php

namespace App\Http\Controllers;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        // Ensure default dummy items for any place without items
        foreach ($allPlaces as $place) {
            if ($place->items->isEmpty()) {
                $place->seedDefaultItemsAndServices();
                $place->load('items');
            }
        }

        // Separate into currently open places for horizontal scrolling section
        $openPlaces = $allPlaces->filter(function ($place) {
            return $place->isOpenNow();
        });

        // If user specifically asked for only open places
        $places = ($filter === 'open') ? $openPlaces : $allPlaces;

        $openCount = $openPlaces->count();
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
        $place = is_numeric($id)
            ? PhotocopyPlace::with(['items', 'services', 'reviews'])->findOrFail($id)
            : PhotocopyPlace::with(['items', 'services', 'reviews'])->where('slug', $id)->firstOrFail();

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
        $place = is_numeric($id)
            ? PhotocopyPlace::with('items')->findOrFail($id)
            : PhotocopyPlace::with('items')->where('slug', $id)->firstOrFail();

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

    /**
     * Process order submission, save to database, and return WhatsApp checkout link.
     */
    public function storeOrder(Request $request, $id)
    {
        $place = is_numeric($id)
            ? PhotocopyPlace::findOrFail($id)
            : PhotocopyPlace::where('slug', $id)->firstOrFail();

        // Match with FotocopyStore to preserve admin/cashier panel integration
        $store = FotocopyStore::find($place->id) ?? FotocopyStore::where('name', $place->name)->first();
        $storeId = $store ? $store->id : $place->id;

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|in:QRIS,Tunai,Transfer',
            'items' => 'required|array|min:1',
            'items.*.name' => 'nullable|string',
            'items.*.service_name' => 'nullable|string',
            'items.*.category' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.service_id' => 'nullable|integer',
        ]);

        $totalPrice = 0;
        foreach ($validated['items'] as $item) {
            $totalPrice += ($item['price'] * $item['quantity']);
        }

        $paymentMethod = $validated['payment_method'] ?? 'QRIS';
        $order = FotocopyOrder::create([
            'order_code' => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'store_id' => $storeId,
            'user_id' => auth()->id(),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentMethod === 'Tunai' ? 'pending' : 'paid',
            'order_status' => 'Menunggu',
            'total_price' => $totalPrice,
        ]);

        foreach ($validated['items'] as $item) {
            $itemName = $item['name'] ?? $item['service_name'] ?? 'Item Cetak';
            FotocopyOrderItem::create([
                'order_id' => $order->id,
                'service_id' => $item['service_id'] ?? null,
                'service_name' => $itemName,
                'category' => $item['category'] ?? 'Fotocopy',
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity'],
            ]);
        }

        // Format WhatsApp order message
        $waNumber = preg_replace('/[^\d]/', '', $place->whatsapp ?: $place->phone ?: '6285769737410');
        if (str_starts_with($waNumber, '08')) {
            $waNumber = '62' . substr($waNumber, 1);
        } elseif (str_starts_with($waNumber, '0')) {
            $waNumber = '62' . substr($waNumber, 1);
        }

        $waText = "Halo {$place->name}, saya ingin memesan:\n";
        $waText .= "Kode Pesanan: #{$order->order_code}\n";
        $waText .= "Nama Pemesan: {$order->customer_name}\n";
        if ($order->customer_phone) {
            $waText .= "No. HP: {$order->customer_phone}\n";
        }
        $waText .= "\nRincian Item:\n";
        foreach ($validated['items'] as $item) {
            $itemName = $item['name'] ?? $item['service_name'] ?? 'Item';
            $sub = number_format($item['price'] * $item['quantity'], 0, ',', '.');
            $waText .= "- {$itemName} (x{$item['quantity']}): Rp {$sub}\n";
        }
        $waText .= "\nTotal: Rp " . number_format($totalPrice, 0, ',', '.') . "\n";
        $waText .= "Metode Bayar: {$order->payment_method}\n";
        if ($order->notes) {
            $waText .= "Catatan: {$order->notes}\n";
        }
        $waText .= "\nMohon konfirmasi ketersediaan dan proses pesanan saya. Terima kasih!";

        $whatsappUrl = 'https://wa.me/' . $waNumber . '?text=' . urlencode($waText);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat dan tercatat di sistem!',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
                'whatsapp_url' => $whatsappUrl,
            ]);
        }

        return redirect()->route('fotocopy.show', $place->id)
            ->with('success', "Pesanan #{$order->order_code} berhasil dibuat! Silakan hubungi kasir atau kirim konfirmasi via WhatsApp.")
            ->with('whatsapp_url', $whatsappUrl);
    }
}
