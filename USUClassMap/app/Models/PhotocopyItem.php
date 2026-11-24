<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotocopyItem extends Model
{
    use HasFactory;
    use HasCatalogAttributes;

    protected $fillable = [
        'photocopy_place_id',
        'name',
        'category',
        'item_type',
        'sub_category',
        'brand',
        'pack_qty',
        'price',
        'unit',
        'description',
        'image_path',
        'metadata',
        'stock',
        'is_available',
    ];

    protected $casts = [
        'price' => 'integer',
        'pack_qty' => 'integer',
        'stock' => 'integer',
        'metadata' => 'array',
        'is_available' => 'boolean',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(PhotocopyPlace::class, 'photocopy_place_id');
    }
}

