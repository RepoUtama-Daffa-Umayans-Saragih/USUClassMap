<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'category',
        'description',
    ];

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(PhotocopyPlace::class, 'photocopy_place_service')
            ->withPivot(['starting_price', 'unit', 'notes', 'is_auto_detected'])
            ->withTimestamps();
    }
}

