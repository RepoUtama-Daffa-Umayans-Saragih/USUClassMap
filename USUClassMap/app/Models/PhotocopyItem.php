<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotocopyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'photocopy_place_id',
        'name',
        'category',
        'price',
        'unit',
        'description',
        'is_available',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_available' => 'boolean',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(PhotocopyPlace::class, 'photocopy_place_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}

