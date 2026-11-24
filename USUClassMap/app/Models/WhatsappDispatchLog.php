<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappDispatchLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_dispatch_logs';

    protected $fillable = [
        'fotocopy_order_id',
        'recipient_phone',
        'recipient_role',
        'message_type',
        'payload_json',
        'attachment_path',
        'driver',
        'external_message_id',
        'status',
        'attempts',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FotocopyOrder::class, 'fotocopy_order_id');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeForStore(Builder $query): Builder
    {
        return $query->where('recipient_role', 'store');
    }

    public function scopeForCustomer(Builder $query): Builder
    {
        return $query->where('recipient_role', 'customer');
    }
}
