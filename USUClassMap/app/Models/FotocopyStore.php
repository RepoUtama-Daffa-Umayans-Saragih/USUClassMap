<?php

namespace App\Models;

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
        'user_id',
        'name',
        'slug',
        'location_name',
        'latitude',
        'longitude',
        'opening_hours',
        'operating_days',
        'whatsapp',
        'instagram',
        'facebook',
        'description',
        'rating',
        'is_open',
        'starting_price',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'rating' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'starting_price' => 'integer',
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
}
