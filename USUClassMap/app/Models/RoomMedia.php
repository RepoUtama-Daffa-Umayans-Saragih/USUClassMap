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
        if (empty($this->file_path)) {
            return '';
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return asset('storage/' . ltrim($this->file_path, '/'));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
