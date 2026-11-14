<?php

namespace App\Models;

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
        'payment_status',
        'order_status',
        'total_price',
    ];

    protected $casts = [
        'total_price' => 'integer',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FotocopyOrderItem::class, 'order_id');
    }
}
