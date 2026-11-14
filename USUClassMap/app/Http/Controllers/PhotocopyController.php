<?php

namespace App\Http\Controllers;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use App\Services\Payment\PaymentManager;
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
     * Process order submission, save to database, and initialize payment strategy.
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
            'payment_method' => 'nullable|string|max:50',
            'payment_channel' => 'nullable|string|max:50',
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

        // Normalize payment method to strategy keys: 'qris', 'bank_transfer', 'cash'
        $rawMethod = strtolower(trim((string) ($validated['payment_method'] ?? 'qris')));
        $paymentMethod = match ($rawMethod) {
            'cash', 'tunai', 'cod' => 'cash',
            'bank_transfer', 'bank', 'transfer', 'va' => 'bank_transfer',
            default => 'qris',
        };

        $order = FotocopyOrder::create([
            'order_code' => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'store_id' => $storeId,
            'user_id' => auth()->id(),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $paymentMethod,
            'payment_channel' => $validated['payment_channel'] ?? null,
            'payment_status' => 'pending',
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

        // Execute payment strategy
        $paymentManager = app(PaymentManager::class);
        $paymentResult = $paymentManager->process($order, $paymentMethod, [
            'payment_channel' => $validated['payment_channel'] ?? null,
        ]);

        // Format WhatsApp order message
        $waNumber = preg_replace('/[^\d]/', '', $place->whatsapp ?: $place->phone ?: '6285769737410');
        if (str_starts_with($waNumber, '08')) {
            $waNumber = '62' . substr($waNumber, 1);
        } elseif (str_starts_with($waNumber, '0')) {
            $waNumber = '62' . substr($waNumber, 1);
        }

        $waText = "Halo {$place->name}, saya telah membuat pesanan di USUClassMap:\n";
        $waText .= "Kode Pesanan: #{$order->order_code}\n";
        $waText .= "Nama Pemesan: {$order->customer_name}\n";
        if ($order->customer_phone) {
            $waText .= "No. HP: {$order->customer_phone}\n";
        }
        $waText .= "\nRincian Pesanan:\n";
        foreach ($validated['items'] as $item) {
            $itemName = $item['name'] ?? $item['service_name'] ?? 'Item';
            $sub = number_format($item['price'] * $item['quantity'], 0, ',', '.');
            $waText .= "- {$itemName} (x{$item['quantity']}): Rp {$sub}\n";
        }
        $waText .= "\nTotal: Rp " . number_format($totalPrice, 0, ',', '.') . "\n";
        $waText .= "Metode Bayar: " . $order->getPaymentMethodLabel();
        if ($order->payment_channel) {
            $waText .= " (" . $order->getPaymentChannelLabel() . ")";
        }
        $waText .= "\nStatus Pembayaran: " . ($order->isPaid() ? 'Lunas' : 'Menunggu Konfirmasi') . "\n";
        if ($order->notes) {
            $waText .= "Catatan: {$order->notes}\n";
        }
        $waText .= "\nMohon diproses pesanan saya. Terima kasih!";

        $whatsappUrl = 'https://wa.me/' . $waNumber . '?text=' . urlencode($waText);
        $paymentUrl = route('fotocopy.orders.payment', $order->order_code);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
                'payment_url' => $paymentUrl,
                'whatsapp_url' => $whatsappUrl,
                'payment' => $paymentResult->toArray(),
            ]);
        }

        return redirect()->route('fotocopy.orders.payment', $order->order_code)
            ->with('success', "Pesanan #{$order->order_code} berhasil dibuat! Silakan selesaikan pembayaran.")
            ->with('whatsapp_url', $whatsappUrl);
    }

    /**
     * Tampilkan halaman instruksi pembayaran dan rincian pesanan.
     */
    public function paymentInstructions($orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)
            ->with(['store', 'photocopyPlace', 'items'])
            ->firstOrFail();

        $place = $order->photocopyPlace ?? PhotocopyPlace::find($order->store_id) ?? new PhotocopyPlace([
            'name' => $order->store?->name ?? 'Mitra Fotokopi USU',
            'address' => $order->store?->address ?? 'Area Kampus USU Medan',
            'whatsapp' => $order->store?->whatsapp ?? '6285769737410',
        ]);

        return view('public.fotocopy.payment', compact('order', 'place'));
    }

    /**
     * Endpoint simulasi pembayaran (PoC) untuk menandai pesanan menjadi Lunas.
     */
    public function simulatePayment(Request $request, $orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $paymentManager = app(PaymentManager::class);
        $success = $paymentManager->simulateSuccess($order);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => 'Status pembayaran berhasil diperbarui menjadi Lunas (Simulasi PoC)!',
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'paid_at' => $order->paid_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i'),
            ]);
        }

        return redirect()->route('fotocopy.orders.payment', $order->order_code)
            ->with('success', 'Status pembayaran berhasil diperbarui menjadi Lunas!');
    }
}
