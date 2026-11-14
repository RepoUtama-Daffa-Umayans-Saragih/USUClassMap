<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FotocopyStore extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_stores';

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'slug',
        'location_name',
        'latitude',
        'longitude',
        'opening_hours',
        'operating_days',
        'phone',
        'whatsapp',
        'image_url',
        'google_maps_url',
        'google_place_id',
        'instagram',
        'facebook',
        'description',
        'rating',
        'user_rating_count',
        'is_open',
        'starting_price',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'rating' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'starting_price' => 'integer',
        'user_rating_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name) . '-' . Str::random(5);
            }
        });
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(FotocopyService::class, 'store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FotocopyOrder::class, 'store_id');
    }

    /**
     * Fallback to default high-res image if empty.
     */
    public function getEffectiveImageUrlAttribute(): string
    {
        if (!empty($this->image_url)) {
            return $this->image_url;
        }

        return 'https://images.unsplash.com/photo-1568667256549-094345857637?auto=format&fit=crop&w=800&q=80';
    }

    /**
     * Normalize WhatsApp URL.
     */
    public function getWhatsappUrlAttribute(): string
    {
        $phone = preg_replace('/[^\d]/', '', $this->whatsapp ?? $this->phone ?? '');
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return 'https://wa.me/' . ($phone ?: '6285769737410');
    }

    /**
     * Check if currently open.
     */
    public function isOpenNow(): bool
    {
        return (bool) $this->is_open;
    }

    /**
     * Human-readable schedule status.
     */
    public function getCurrentScheduleStatusAttribute(): string
    {
        return $this->is_open
            ? "Buka ⋅ {$this->opening_hours}"
            : "Tutup ⋅ Buka besok ({$this->opening_hours})";
    }
}
