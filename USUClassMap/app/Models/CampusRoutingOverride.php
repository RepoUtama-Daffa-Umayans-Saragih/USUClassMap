<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampusRoutingOverride extends Model
{
    use HasFactory;

    protected $table = 'campus_routing_overrides';

    protected $fillable = [
        'type',
        'name',
        'coordinates_json',
        'metadata_json',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'coordinates_json' => 'array',
            'metadata_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
