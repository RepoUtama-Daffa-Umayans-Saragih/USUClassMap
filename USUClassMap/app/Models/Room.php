<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'name',
        'floor',
        'capacity',
        'photo_360_path',
        'door_photo_path',
        'photo_360_meta',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'capacity' => 'integer',
            'photo_360_meta' => 'array',
        ];
    }

    public function getPhoto360UrlAttribute(): ?string
    {
        return \App\Support\CampusMediaUrl::resolve($this->photo_360_path);
    }

    public function getDoorPhotoUrlAttribute(): ?string
    {
        return \App\Support\CampusMediaUrl::resolve($this->door_photo_path);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(RoomMedia::class);
    }
}
