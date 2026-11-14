<?php

namespace App\Http\Controllers;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FotocopyController extends Controller
{
    /**
     * Tampilan publik: Daftar Mitra Toko Fotocopy USU.
     */
    public function index(Request $request)
    {
        $query = FotocopyStore::with('services');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                    ->orWhere('location_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('services', function ($servQ) use ($q) {
                        $servQ->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->input('filter') === 'buka') {
            $query->where('is_open', true);
        }

        if ($request->filled('tag')) {
            $tag = strtolower($request->input('tag'));
            if ($tag === 'buka sekarang') {
                $query->where('is_open', true);
            } elseif ($tag === 'jilid skripsi') {
                $query->whereHas('services', function ($sq) {
                    $sq->where('name', 'like', '%skripsi%')->orWhere('category', 'Jilid');
                });
            } else {
                $searchTag = str_replace('dekat ', '', $tag);
                $query->where(function ($sq) use ($searchTag) {
                    $sq->where('location_name', 'like', "%{$searchTag}%")
                        ->orWhere('name', 'like', "%{$searchTag}%");
                });
            }
        }

        $allStores = (clone $query)->get();
        $openStores = $allStores->where('is_open', true);

        return view('fotocopy.index', compact('allStores', 'openStores'));
    }

    /**
     * Tampilan publik: Detail Toko & Katalog Harga.
     */
    public function show($id)
    {
        $store = is_numeric($id)
            ? FotocopyStore::with('services')->findOrFail($id)
            : FotocopyStore::with('services')->where('slug', $id)->firstOrFail();

        $servicesByCategory = $store->services->groupBy('category');

        return view('fotocopy.show', compact('store', 'servicesByCategory'));
    }

    /**
     * Tampilan publik: Halaman Pemesanan Interaktif.
     */
    public function order($id)
    {
        $store = is_numeric($id)
            ? FotocopyStore::with('services')->findOrFail($id)
            : FotocopyStore::with('services')->where('slug', $id)->firstOrFail();

        $servicesByCategory = $store->services->groupBy('category');

        return view('fotocopy.order', compact('store', 'servicesByCategory'));
    }

    /**
     * Simpan pemesanan layanan dari mahasiswa ke database.
     */
    public function storeOrder(Request $request, $id)
    {
        $store = is_numeric($id)
            ? FotocopyStore::findOrFail($id)
            : FotocopyStore::where('slug', $id)->firstOrFail();

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:QRIS,Tunai,Transfer',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'nullable|integer',
            'items.*.service_name' => 'required|string',
            'items.*.category' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $totalPrice = 0;
        foreach ($validated['items'] as $item) {
            $totalPrice += ($item['price'] * $item['quantity']);
        }

        $order = FotocopyOrder::create([
            'order_code' => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'store_id' => $store->id,
            'user_id' => auth()->id(),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_method'] === 'Tunai' ? 'pending' : 'paid',
            'order_status' => 'Menunggu',
            'total_price' => $totalPrice,
        ]);

        foreach ($validated['items'] as $item) {
            FotocopyOrderItem::create([
                'order_id' => $order->id,
                'service_id' => $item['service_id'] ?? null,
                'service_name' => $item['service_name'],
                'category' => $item['category'] ?? 'Print',
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity'],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
            ]);
        }

        return redirect()->route('fotocopy.show', $store->id)
            ->with('success', "Pesanan #{$order->order_code} berhasil dikirim! Kasir toko akan segera memproses.");
    }
}
