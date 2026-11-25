<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RoomMedia extends Model
{
    use HasFactory;

    protected $table = 'room_media';

    protected $fillable = [
        'room_id',
        'type',
        'file_path',
        'is_primary',
        'metadata',
    ];

    protected $appends = [
        'file_url',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getFileUrlAttribute(): string
    {
        return \App\Support\CampusMediaUrl::resolve($this->file_path) ?? '';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
