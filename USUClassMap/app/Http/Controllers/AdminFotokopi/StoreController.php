<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Models\WhatsappDispatchLog;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    /**
     * Sinkronkan data FotocopyStore ke PhotocopyPlace berdasarkan slug/nama (bukan raw ID)
     * agar Peta User (/map) & Direktori Publik (/fotocopy) selalu akurat.
     */
    protected function syncToPhotocopyPlace(FotocopyStore $store, ?string $oldName = null, ?string $oldSlug = null): void
    {
        try {
            $hoursParts = array_map('trim', explode('-', $store->opening_hours ?? '07:30 - 20:30'));
            $openTime = $hoursParts[0] ?? '07:30';
            $closeTime = $hoursParts[1] ?? '20:30';

            $place = PhotocopyPlace::where('slug', $store->slug)->first();
            if (!$place && $oldSlug) {
                $place = PhotocopyPlace::where('slug', $oldSlug)->first();
            }
            if (!$place && $oldName) {
                $place = PhotocopyPlace::where('name', $oldName)->first();
            }
            if (!$place) {
                $place = PhotocopyPlace::where('name', $store->name)->first();
            }

            $waDigits = WhatsAppPhoneNormalizer::normalize($store->whatsapp ?: $store->phone);
            $phoneDisplay = $waDigits ? WhatsAppPhoneNormalizer::formatDisplay($waDigits) : $store->phone;
            $phoneNational = $waDigits ? WhatsAppPhoneNormalizer::formatNational($waDigits) : $store->phone;

            $payload = [
                'name' => $store->name,
                'slug' => $store->slug ?: (Str::slug($store->name) . '-' . Str::random(5)),
                'location_name' => $store->location_name,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'open_time' => $openTime ?: '07:30',
                'close_time' => $closeTime ?: '20:30',
                'operating_days' => $store->operating_days,
                'whatsapp' => $waDigits ?? $store->whatsapp,
                'whatsapp_url' => $waDigits ? ('https://wa.me/' . $waDigits) : null,
                'phone' => $phoneDisplay,
                'phone_national' => $phoneNational,
                'phone_international' => $phoneDisplay,
                'instagram' => $store->instagram,
                'facebook' => $store->facebook,
                'rating' => $store->rating,
                'is_active' => (bool) $store->is_open,
                'business_status' => $store->is_open ? 'OPERATIONAL' : 'CLOSED_TEMPORARILY',
                'description' => $store->description,
            ];

            if ($place) {
                $place->update($payload);
            } else {
                PhotocopyPlace::create($payload);
            }
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menyinkronkan FotocopyStore ke PhotocopyPlace: ' . $e->getMessage());
        }
    }
    public function index(Request $request, WhatsAppManager $waManager)
    {
        $query = FotocopyStore::withCount(['services', 'orders']);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('location_name', 'like', "%{$q}%")
                ->orWhere('whatsapp', 'like', "%{$q}%");
        }

        $stores = $query->latest()->paginate(10)->withQueryString();
        $waStatus = $waManager->statusSummary();

        return view('features.fotocopy.admin.stores.index', compact('stores', 'waStatus'));
    }

    /**
     * Kirim pesan uji coba (Tes Ping) ke WhatsApp aktif toko mitra via driver Gateway terpilih.
     */
    public function testWhatsApp(FotocopyStore $store, WhatsAppManager $waManager)
    {
        $targetDigits = WhatsAppPhoneNormalizer::normalize($store->whatsapp ?: $store->phone);
        if (!$targetDigits) {
            return redirect()->back()->with('error', "Toko '{$store->name}' belum memiliki nomor WhatsApp valid.");
        }

        $driver = $waManager->driver();
        $message = implode("\n", [
            '*TES KONEKSI WHATSAPP - USUCLASSMAP*',
            "Mitra Toko : {$store->name}",
            'No. Tujuan : ' . WhatsAppPhoneNormalizer::formatDisplay($targetDigits),
            'Driver     : ' . strtoupper($driver->driverName()),
            'Waktu      : ' . now('Asia/Jakarta')->format('d M Y, H:i:s') . ' WIB',
            'Status     : Koneksi gateway pengiriman otomatis siap menerima order Multi-PDF & Invoice.',
        ]);

        $res = $driver->sendText($targetDigits, $message);

        if ($res['success']) {
            $modeNote = ($res['simulated'] ?? false)
                ? ' (dicatat via Sandbox Simulator — aktifkan WHATSAPP_DRIVER=waha untuk kirim ke HP nyata)'
                : '';

            return redirect()->back()->with(
                'success',
                "Tes pesan WhatsApp ke {$store->name} (" . WhatsAppPhoneNormalizer::formatDisplay($targetDigits) . ") berhasil{$modeNote}!"
            );
        }

        return redirect()->back()->with('error', 'Tes kirim WhatsApp gagal: ' . ($res['error'] ?? 'Unknown gateway error'));
    }

    /**
     * Ambil daftar log pesan WhatsApp terbaru untuk modal "Simulasi Chat WA (Sandbox Viewer)".
     */
    public function whatsappSandboxLogs()
    {
        $logs = WhatsappDispatchLog::with(['order.store'])
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(function (WhatsappDispatchLog $log): array {
                return [
                    'id' => $log->id,
                    'order_code' => $log->order?->order_code ?? '-',
                    'store_name' => $log->order?->store?->name ?? 'Mitra Fotokopi USU',
                    'customer_name' => $log->order?->customer_name ?? '-',
                    'recipient_phone' => WhatsAppPhoneNormalizer::formatDisplay($log->recipient_phone),
                    'recipient_role' => $log->recipient_role,
                    'message_type' => $log->message_type,
                    'driver' => $log->driver,
                    'status' => $log->status,
                    'text' => $log->payload_json['text'] ?? $log->payload_json['caption'] ?? '',
                    'file_name' => $log->payload_json['file_name'] ?? ($log->attachment_path ? basename($log->attachment_path) : null),
                    'error_message' => $log->error_message,
                    'sent_at' => ($log->sent_at ?? $log->created_at)?->timezone('Asia/Jakarta')->format('d M Y, H:i:s'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }

    public function create()
    {
        $cashiers = User::where('role', 'cashier_fotokopi')->get();
        return view('features.fotocopy.admin.stores.create', compact('cashiers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'location_name' => 'required|string|max:200',
            'user_id' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'opening_hours' => 'required|string|max:50',
            'operating_days' => 'required|string|max:100',
            'whatsapp' => 'nullable|string|max:30',
            'instagram' => 'nullable|string|max:50',
            'facebook' => 'nullable|string|max:50',
            'starting_price' => 'required|integer|min:0',
            'rating' => 'required|numeric|between:1,5',
            'is_open' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        if (!empty($validated['whatsapp'])) {
            $normalizedWa = WhatsAppPhoneNormalizer::normalize($validated['whatsapp']);
            if ($normalizedWa) {
                $validated['whatsapp'] = $normalizedWa;
                $validated['phone'] = WhatsAppPhoneNormalizer::formatDisplay($normalizedWa);
            }
        }

        $store = FotocopyStore::create($validated);
        $this->syncToPhotocopyPlace($store);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra Toko '{$validated['name']}' berhasil ditambahkan!");
    }

    public function edit(FotocopyStore $store)
    {
        $cashiers = User::where('role', 'cashier_fotokopi')->get();
        return view('features.fotocopy.admin.stores.edit', compact('store', 'cashiers'));
    }

    public function update(Request $request, FotocopyStore $store)
    {
        $oldName = $store->name;
        $oldSlug = $store->slug;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'location_name' => 'required|string|max:200',
            'user_id' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'opening_hours' => 'required|string|max:50',
            'operating_days' => 'required|string|max:100',
            'whatsapp' => 'nullable|string|max:30',
            'instagram' => 'nullable|string|max:50',
            'facebook' => 'nullable|string|max:50',
            'starting_price' => 'required|integer|min:0',
            'rating' => 'required|numeric|between:1,5',
            'is_open' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['whatsapp'])) {
            $normalizedWa = WhatsAppPhoneNormalizer::normalize($validated['whatsapp']);
            if ($normalizedWa) {
                $validated['whatsapp'] = $normalizedWa;
                $validated['phone'] = WhatsAppPhoneNormalizer::formatDisplay($normalizedWa);
            }
        }

        $store->update($validated);
        $this->syncToPhotocopyPlace($store, $oldName, $oldSlug);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Data mitra toko '{$store->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyStore $store)
    {
        $slug = $store->slug;
        $name = $store->name;
        $store->delete();

        try {
            $place = ($slug ? PhotocopyPlace::where('slug', $slug)->first() : null)
                ?? PhotocopyPlace::where('name', $name)->first();
            if ($place) {
                $place->delete();
            }
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menghapus PhotocopyPlace: ' . $e->getMessage());
        }

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra toko '{$name}' berhasil dihapus.");
    }
}
