<?php

namespace App\Models;

use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FotocopyOrder extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_orders';

    protected $fillable = [
        'order_code',
        'invoice_number',
        'pickup_code',
        'store_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_whatsapp',
        'notes',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'payment_method',
        'payment_channel',
        'payment_status',
        'payment_payload',
        'invoice_pdf_path',
        'order_status',
        'pickup_estimate',
        'wa_dispatch_status',
        'wa_dispatched_at',
        'total_price',
        'paid_at',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'payment_payload' => 'array',
        'paid_at' => 'datetime',
        'wa_dispatched_at' => 'datetime',
    ];

    public function isPaid(): bool
    {
        return strtolower($this->payment_status ?? '') === 'paid';
    }

    public function isPending(): bool
    {
        return strtolower($this->payment_status ?? '') === 'pending';
    }

    public function getFormattedTotal(): string
    {
        return 'Rp ' . number_format($this->total_price, 0, ',', '.');
    }

    public function getPaymentMethodLabel(): string
    {
        $m = strtolower($this->payment_method ?? '');
        return match ($m) {
            'qris' => 'QRIS Dinamis',
            'bank_transfer', 'transfer' => 'Transfer Bank',
            'cash', 'tunai', 'cod' => 'Tunai di Kasir',
            default => strtoupper($this->payment_method ?? 'TUNAI'),
        };
    }

    public function getPaymentChannelLabel(): string
    {
        if (!empty($this->payment_channel)) {
            return strtoupper($this->payment_channel);
        }

        $m = strtolower($this->payment_method ?? '');
        if ($m === 'qris') {
            return 'QRIS (Semua E-Wallet/Bank)';
        }
        if (in_array($m, ['cash', 'tunai', 'cod'])) {
            return 'Kasir Toko';
        }

        return '-';
    }

    public function getPaymentStatusBadge(): string
    {
        if ($this->isPaid()) {
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                <i class="fa-solid fa-circle-check text-xs"></i> Lunas
            </span>';
        }

        return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
            <i class="fa-solid fa-clock text-xs"></i> Menunggu Pembayaran
        </span>';
    }

    public function hasFile(): bool
    {
        return !empty($this->file_path) || !empty($this->file_name) || $this->hasMultiFiles();
    }

    public function hasMultiFiles(): bool
    {
        if ($this->relationLoaded('files')) {
            return $this->files->isNotEmpty();
        }

        return $this->files()->exists();
    }

    public function totalPagesToPrint(): int
    {
        $files = $this->relationLoaded('files') ? $this->files : $this->files()->get();

        return (int) $files->sum(fn (FotocopyOrderFile $f): int => max(1, (int) $f->page_count) * max(1, (int) $f->copies));
    }

    public function isWaDispatched(): bool
    {
        return in_array((string) $this->wa_dispatch_status, ['sent', 'sandbox'], true);
    }

    public function invoiceUrl(int $minutes = 1440): ?string
    {
        if (empty($this->invoice_pdf_path) && !$this->isPaid()) {
            return null;
        }

        if (!Route::has('fotocopy.orders.invoice')) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fotocopy.orders.invoice',
            now()->addMinutes($minutes),
            ['orderCode' => $this->order_code]
        );
    }

    public function invoicePreviewUrl(int $minutes = 1440): ?string
    {
        if (empty($this->invoice_pdf_path) && !$this->isPaid()) {
            return null;
        }

        if (!Route::has('fotocopy.orders.invoice.preview')) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fotocopy.orders.invoice.preview',
            now()->addMinutes($minutes),
            ['orderCode' => $this->order_code]
        );
    }

    public function signedZipUrl(int $minutes = 1440): ?string
    {
        if (!$this->hasMultiFiles() && !$this->isPaid()) {
            return null;
        }

        if (!Route::has('fotocopy.orders.download-zip')) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fotocopy.orders.download-zip',
            now()->addMinutes($minutes),
            ['orderCode' => $this->order_code]
        );
    }

    public function fileUrl(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (Storage::disk('local')->exists($this->file_path) && Route::has('cashier-fotokopi.orders.file')) {
            return route('cashier-fotokopi.orders.file', $this);
        }

        return asset('storage/' . $this->file_path);
    }

    public function fileIconType(): string
    {
        $ext = strtolower($this->file_type ?? pathinfo($this->file_name ?? '', PATHINFO_EXTENSION));

        if (in_array($ext, ['pdf'])) {
            return 'pdf';
        }

        if (in_array($ext, ['doc', 'docx', 'rtf', 'odt'])) {
            return 'doc';
        }

        if (in_array($ext, ['ppt', 'pptx'])) {
            return 'ppt';
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            return 'image';
        }

        return 'other';
    }

    /**
     * Resolve customer WhatsApp digits for notifications and cashier direct links.
     */
    public function getNormalizedCustomerWhatsappAttribute(): ?string
    {
        return WhatsAppPhoneNormalizer::normalize($this->customer_whatsapp ?: $this->customer_phone);
    }

    /**
     * Resolve paired PhotocopyPlace via FotocopyStore slug/name before raw store_id fallback.
     */
    public function getPhotocopyPlaceResolvedAttribute(): ?PhotocopyPlace
    {
        if ($this->relationLoaded('photocopyPlace') && $this->photocopyPlace) {
            return $this->photocopyPlace;
        }

        $store = $this->relationLoaded('store') ? $this->store : $this->store()->first();
        if ($store) {
            $bySlug = !empty($store->slug) ? PhotocopyPlace::where('slug', $store->slug)->first() : null;
            if ($bySlug) {
                return $bySlug;
            }

            $byName = PhotocopyPlace::where('name', $store->name)->first();
            if ($byName) {
                return $byName;
            }
        }

        return $this->store_id ? PhotocopyPlace::find($this->store_id) : null;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_code)) {
                $order->order_code = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(FotocopyStore::class, 'store_id');
    }

    public function photocopyPlace(): HasOneThrough
    {
        return $this->hasOneThrough(
            PhotocopyPlace::class,
            FotocopyStore::class,
            'id',
            'slug',
            'store_id',
            'slug'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FotocopyOrderItem::class, 'order_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FotocopyOrderFile::class, 'fotocopy_order_id')->orderBy('sort_order');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsappDispatchLog::class, 'fotocopy_order_id')->latest();
    }
}
