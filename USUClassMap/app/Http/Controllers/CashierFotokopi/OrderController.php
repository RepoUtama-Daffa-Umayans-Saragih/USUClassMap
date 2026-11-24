<?php

namespace App\Http\Controllers\CashierFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Tampilan Antarmuka Kasir: Antrean Pesanan Masuk & Operasional Harian.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Cari toko yang dikelola kasir ini, atau ambil toko default pertama jika belum di-assign
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();

        $query = FotocopyOrder::with(['items', 'store']);

        if ($myStore && !$user->isAdmin()) {
            $query->where('store_id', $myStore->id);
        }

        // Filter status pesanan
        if ($request->filled('status') && $request->input('status') !== 'semua') {
            $query->where('order_status', $request->input('status'));
        }

        // Pencarian cepat
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('order_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        // Hitung badge status
        $baseStoreQuery = FotocopyOrder::query();
        if ($myStore && !$user->isAdmin()) {
            $baseStoreQuery->where('store_id', $myStore->id);
        }

        $countMenunggu = (clone $baseStoreQuery)->where('order_status', 'Menunggu')->count();
        $countDiproses = (clone $baseStoreQuery)->where('order_status', 'Diproses')->count();
        $countSelesai = (clone $baseStoreQuery)->where('order_status', 'Selesai')->count();
        $totalOrdersCount = (clone $baseStoreQuery)->count();

        $orders = $query->latest()->paginate(12)->withQueryString();

        return view('features.fotocopy.cashier.orders.index', compact(
            'orders',
            'myStore',
            'countMenunggu',
            'countDiproses',
            'countSelesai',
            'totalOrdersCount'
        ));
    }

    /**
     * Update status pesanan oleh kasir (Menunggu -> Diproses -> Selesai / Dibatalkan).
     */
    public function updateStatus(Request $request, FotocopyOrder $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:Menunggu,Diproses,Selesai,Dibatalkan',
            'payment_status' => 'nullable|in:pending,paid',
        ]);

        $updateData = ['order_status' => $validated['order_status']];

        // Jika selesai, otomatis tandai lunas (paid) jika belum
        if ($validated['order_status'] === 'Selesai') {
            $updateData['payment_status'] = 'paid';
        } elseif (isset($validated['payment_status'])) {
            $updateData['payment_status'] = $validated['payment_status'];
        }

        $order->update($updateData);

        return redirect()->back()->with('success', "Status pesanan #{$order->order_code} berhasil diperbarui menjadi '{$validated['order_status']}'!");
    }

    /**
     * Form Point-of-Sale (POS) Kasir untuk Pesanan Walk-in / Langsung di Counter.
     */
    public function createPos()
    {
        $user = auth()->user();
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();
        $services = FotocopyService::where('store_id', $myStore->id)
            ->where('is_available', true)
            ->orderByRaw("CASE WHEN item_type = 'jasa' THEN 0 ELSE 1 END")
            ->orderBy('category')
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get();

        $catalog = $services->map->toCatalogArray()->values()->all();

        return view('features.fotocopy.cashier.orders.pos', compact('myStore', 'services', 'catalog'));
    }

    /**
     * Simpan pesanan walk-in dari POS Kasir.
     */
    public function storePos(Request $request)
    {
        $user = auth()->user();
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'file' => 'nullable|file|max:25600',
            'payment_method' => 'required|in:QRIS,Tunai,Transfer',
            'order_status' => 'required|in:Diproses,Selesai',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:fotocopy_services,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $fileType = null;

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $uploaded = $request->file('file');
            $fileName = $uploaded->getClientOriginalName();
            $fileType = strtolower($uploaded->getClientOriginalExtension());
            $bytes = $uploaded->getSize();
            if ($bytes >= 1048576) {
                $fileSize = round($bytes / 1048576, 1) . ' MB';
            } else {
                $fileSize = round($bytes / 1024, 0) . ' KB';
            }
            $filePath = $uploaded->store('fotocopy_docs', 'public');
        }

        $totalPrice = 0;
        $orderItemsData = [];

        foreach ($validated['items'] as $item) {
            $service = FotocopyService::find($item['service_id']);
            if ($service) {
                $subtotal = $service->price * $item['quantity'];
                $totalPrice += $subtotal;
                $orderItemsData[] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'category' => $service->category,
                    'price' => $service->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }
        }

        $order = FotocopyOrder::create([
            'order_code' => 'POS-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'store_id' => $myStore->id,
            'user_id' => $user->id,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'order_status' => $validated['order_status'],
            'total_price' => $totalPrice,
        ]);

        foreach ($orderItemsData as $itemData) {
            $order->items()->create($itemData);
        }

        return redirect()->route('cashier-fotokopi.orders.index')
            ->with('success', "Pesanan kasir #{$order->order_code} berhasil dibuat dan tercatat!");
    }

    /**
     * Download atau unduh file lampiran dokumen pesanan.
     */
    public function downloadFile(FotocopyOrder $order)
    {
        if ($order->file_path && Storage::disk('public')->exists($order->file_path)) {
            return Storage::disk('public')->download($order->file_path, $order->file_name ?? 'dokumen.pdf');
        }

        // Jika demo data atau file fisik belum di-upload namun ada nama file
        return redirect()->back()->with('error', "File fisik dokumen belum tersedia di server storage.");
    }

    /**
     * Halaman / Dialog Cetak Khusus: Surat Perintah Kerja (SPK) Cetak & Nota Transaksi Kasir.
     */
    public function printJob(FotocopyOrder $order)
    {
        $order->load(['items', 'store']);
        return view('features.fotocopy.cashier.orders.print', compact('order'));
    }
}
