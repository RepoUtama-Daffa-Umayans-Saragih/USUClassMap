<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'photocopy_place_id',
        'google_review_name',
        'author_name',
        'author_photo_url',
        'rating',
        'text',
        'relative_publish_time',
        'publish_time',
    ];

    protected $casts = [
        'rating' => 'integer',
        'publish_time' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(PhotocopyPlace::class, 'photocopy_place_id');
    }
}

