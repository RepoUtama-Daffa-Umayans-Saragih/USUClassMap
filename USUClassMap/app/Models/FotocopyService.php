<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotocopyService extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_services';

    protected $fillable = [
        'store_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(FotocopyStore::class, 'store_id');
    }
}
