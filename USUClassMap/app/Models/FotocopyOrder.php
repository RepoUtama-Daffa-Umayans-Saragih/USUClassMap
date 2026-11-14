<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FotocopyOrder extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_orders';

    protected $fillable = [
        'order_code',
        'store_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'notes',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'payment_method',
        'payment_channel',
        'payment_status',
        'payment_payload',
        'order_status',
        'total_price',
        'paid_at',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'payment_payload' => 'array',
        'paid_at' => 'datetime',
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
        return !empty($this->file_path) || !empty($this->file_name);
    }

    public function fileUrl(): ?string
    {
        if (empty($this->file_path)) {
            return null;
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

    public function photocopyPlace(): BelongsTo
    {
        return $this->belongsTo(PhotocopyPlace::class, 'store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FotocopyOrderItem::class, 'order_id');
    }
}
