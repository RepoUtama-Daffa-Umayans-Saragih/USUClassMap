<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FotocopyOrderFile extends Model
{
    use HasFactory;

    protected $table = 'fotocopy_order_files';

    protected $fillable = [
        'fotocopy_order_id',
        'fotocopy_order_item_id',
        'original_name',
        'stored_path',
        'storage_disk',
        'mime_type',
        'file_size',
        'page_count',
        'copies',
        'print_mode',
        'paper_size',
        'finishing_option',
        'note',
        'subtotal_estimated',
        'sort_order',
        'is_encrypted',
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_count' => 'integer',
        'copies' => 'integer',
        'subtotal_estimated' => 'integer',
        'sort_order' => 'integer',
        'is_encrypted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FotocopyOrder::class, 'fotocopy_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(FotocopyOrderItem::class, 'fotocopy_order_item_id');
    }

    /**
     * Format byte size into human-readable MB or KB string.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /**
     * Return concise print specification summary for UI, SPK, and WhatsApp captions.
     */
    public function getDisplaySpecSummary(): string
    {
        $parts = [
            "{$this->page_count} hal",
            "{$this->copies} rangkap",
            $this->print_mode,
            $this->paper_size,
        ];

        if (!empty($this->finishing_option)) {
            $parts[] = $this->finishing_option;
        }

        return implode(' • ', array_filter($parts));
    }

    /**
     * Resolve absolute filesystem path on configured storage disk when present.
     */
    public function getAbsoluteLocalPath(): ?string
    {
        if ($this->deleted_at !== null || empty($this->stored_path)) {
            return null;
        }

        $disk = $this->storage_disk ?: 'local';
        if (!Storage::disk($disk)->exists($this->stored_path)) {
            return null;
        }

        return Storage::disk($disk)->path($this->stored_path);
    }

    /**
     * Determine whether the physical document still exists on disk and has not been cleaned up.
     */
    public function isPhysicallyAvailable(): bool
    {
        return $this->getAbsoluteLocalPath() !== null;
    }

    /**
     * Generate temporary signed download URL for protected private access.
     */
    public function signedDownloadUrl(int $minutes = 30): string
    {
        $orderCode = $this->order?->order_code ?? $this->fotocopy_order_id;

        return URL::temporarySignedRoute(
            'fotocopy.orders.files.download',
            now()->addMinutes($minutes),
            ['orderCode' => $orderCode, 'file' => $this->id]
        );
    }
}
