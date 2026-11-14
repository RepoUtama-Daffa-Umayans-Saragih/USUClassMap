<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotocopyPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location_name',
        'latitude',
        'longitude',
        'google_maps_url',
        'open_time',
        'close_time',
        'operating_days',
        'phone',
        'whatsapp',
        'instagram',
        'facebook',
        'image_url',
        'rating',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PhotocopyItem::class, 'photocopy_place_id');
    }

    /**
     * Determine if the photocopy place is currently open.
     */
    public function isOpenNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now('Asia/Jakarta');
        $currentTime = $now->format('H:i');
        $dayOfWeek = $now->isoFormat('dddd'); // e.g. Senin, Selasa, Minggu

        // Check if operating days include today
        $days = strtolower($this->operating_days);
        if (str_contains($days, 'senin - jumat') && in_array($dayOfWeek, ['Sabtu', 'Minggu'])) {
            return false;
        }
        if (str_contains($days, 'senin - sabtu') && $dayOfWeek === 'Minggu') {
            return false;
        }

        return $currentTime >= $this->open_time && $currentTime <= $this->close_time;
    }

    /**
     * Generate WhatsApp Direct Link with pre-filled automatic message.
     */
    public function getWhatsappUrl(?string $customMessage = null): string
    {
        $raw = !empty($this->whatsapp) ? $this->whatsapp : '6285769737410';
        $phone = preg_replace('/[^0-9]/', '', (string)$raw);
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }

        $message = $customMessage ?? "Halo *{$this->name}*, saya mahasiswa USU ingin bertanya mengenai layanan fotokopi dan cetak dokumen melalui USUClassMap.";

        return "https://wa.me/{$phone}?text=" . rawurlencode($message);
    }

    /**
     * Generate Instagram Profile Link.
     */
    public function getInstagramUrl(): ?string
    {
        if (empty($this->instagram)) {
            return null;
        }

        $username = ltrim(trim($this->instagram), '@');
        return "https://instagram.com/{$username}";
    }

    /**
     * Generate Facebook Page / Profile Link.
     */
    public function getFacebookUrl(): ?string
    {
        if (empty($this->facebook)) {
            return null;
        }

        return "https://facebook.com/" . trim($this->facebook);
    }

    /**
     * Generate Google Maps directions / view link.
     */
    public function getDirectionUrl(): string
    {
        if (!empty($this->google_maps_url)) {
            return $this->google_maps_url;
        }

        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps/dir/?api=1&destination={$this->latitude},{$this->longitude}";
        }

        return "https://www.google.com/maps/search/?api=1&query=" . rawurlencode($this->name . ' USU Medan');
    }
}

