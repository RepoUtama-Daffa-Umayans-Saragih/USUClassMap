<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotocopyOrderItem extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_order_items';

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name',
        'category',
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FotocopyOrder::class, 'order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(FotocopyService::class, 'service_id');
    }
}
