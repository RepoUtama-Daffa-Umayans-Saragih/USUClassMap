<?php

namespace App\Models;

use App\Services\OperationalStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotocopyPlace extends Model
{
    use HasFactory;

    protected $table = 'photocopy_places';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location_name',
        'latitude',
        'longitude',
        'entrance_lat',
        'entrance_lng',
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
        // Google Places API (New) Sync & Rich Attributes
        'google_place_id',
        'business_status',
        'phone_national',
        'phone_international',
        'whatsapp_url',
        'website_url',
        'email',
        'user_rating_count',
        'review_summary',
        'google_maps_uri',
        'opening_hours',
        'is_verified',
        'is_manually_verified',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'is_manually_verified' => 'boolean',
        'rating' => 'float',
        'user_rating_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'entrance_lat' => 'float',
        'entrance_lng' => 'float',
        'opening_hours' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $appends = [
        'is_open_now',
        'current_schedule_status',
        'effective_google_maps_url',
    ];

    /**
     * Relationship: Catalog items (Print, Jilid, ATK, etc.)
     */
    public function items(): HasMany
    {
        return $this->hasMany(PhotocopyItem::class, 'photocopy_place_id');
    }

    /**
     * Relationship: Linked Master Services
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'photocopy_place_service')
            ->withPivot(['starting_price', 'unit', 'notes', 'is_auto_detected'])
            ->withTimestamps();
    }

    /**
     * Relationship: Google Maps / Platform Reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ShopReview::class, 'photocopy_place_id')->latest();
    }

    /**
     * Automatically ensure dummy items and services exist for this shop if empty.
     */
    public function seedDefaultItemsAndServices(): void
    {
        if ($this->items()->count() === 0) {
            $defaultItems = [
                ['name' => 'Fotocopy Hitam Putih A4 / F4 (Bolak-Balik)', 'category' => 'Fotocopy', 'price' => 200, 'unit' => 'lembar', 'description' => 'HVS 75gr hasil jernih dan teks tajam'],
                ['name' => 'Fotocopy Bahan Ajar & Modul Kuliah A4', 'category' => 'Fotocopy', 'price' => 180, 'unit' => 'lembar', 'description' => 'Tarif mahasiswa khusus rangkuman materi kuliah'],
                ['name' => 'Print Dokumen Skripsi / Makalah A4', 'category' => 'Print', 'price' => 250, 'unit' => 'lembar', 'description' => 'Hasil cetak laser berkecepatan tinggi'],
                ['name' => 'Print Warna Dokumen & Laporan Praktikum A4', 'category' => 'Print', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Warna presisi dan kontras tajam'],
                ['name' => 'Jilid Lakban Hitam + Mika Bening', 'category' => 'Jilid', 'price' => 3500, 'unit' => 'buku', 'description' => 'Standar tugas mingguan praktikum dan kuliah'],
                ['name' => 'Jilid Spiral Kawat Ring Besi', 'category' => 'Jilid', 'price' => 12000, 'unit' => 'buku', 'description' => 'Modul dan diktat kuliah rapi hingga 150 lembar'],
                ['name' => 'Jilid Hard Cover Skripsi Emas Standar USU', 'category' => 'Jilid', 'price' => 35000, 'unit' => 'buku', 'description' => 'Sesuai buku pedoman penulisan skripsi USU'],
                ['name' => 'Scan Berkas Dokumen ke PDF (300 DPI)', 'category' => 'Finishing', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Arsip digital langsung dikirim ke email atau WA'],
                ['name' => 'Laminating Panas KTP / Kartu Mahasiswa', 'category' => 'Finishing', 'price' => 3500, 'unit' => 'lembar', 'description' => 'Plastik tebal pelindung anti air'],
                ['name' => 'Kertas HVS A4 75gr PaperOne (1 Rim)', 'category' => 'ATK', 'price' => 52000, 'unit' => 'rim', 'description' => '500 lembar standar skripsi dan tugas resmi'],
            ];

            foreach ($defaultItems as $item) {
                $this->items()->create(array_merge($item, ['is_available' => true]));
            }
        }

        if ($this->services()->count() === 0) {
            $coreServiceIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
            $services = Service::whereIn('id', $coreServiceIds)->get();
            foreach ($services as $srv) {
                $this->services()->attach($srv->id, [
                    'starting_price' => 200,
                    'unit' => 'lembar',
                    'notes' => 'Layanan standar mitra fotokopi USU',
                    'is_auto_detected' => true,
                ]);
            }
        }
    }

    /**
     * Calculate whether the shop is open right now via OperationalStatusService (WIB).
     */
    public function getIsOpenNowAttribute(): bool
    {
        $status = app(OperationalStatusService::class)->evaluatePhotocopyStatus($this);
        return (bool) ($status['is_open_now'] ?? false);
    }

    /**
     * Method alias for isOpenNow() for backward compatibility.
     */
    public function isOpenNow(): bool
    {
        return $this->is_open_now;
    }

    /**
     * Generate human-readable schedule status via OperationalStatusService (WIB).
     */
    public function getCurrentScheduleStatusAttribute(): string
    {
        $status = app(OperationalStatusService::class)->evaluatePhotocopyStatus($this);
        return ($status['is_open_now'] ?? false)
            ? "Buka ⋅ {$status['detail_text']}"
            : "Tutup ⋅ {$status['detail_text']}";
    }

    /**
     * Retrieve day schedule string from SerpApi operating_hours dictionary.
     */
    protected function getDayScheduleFromSerpApi(array $hours, int $dayOfWeek): ?string
    {
        $idDays = [0 => 'minggu', 1 => 'senin', 2 => 'selasa', 3 => 'rabu', 4 => 'kamis', 5 => 'jumat', 6 => 'sabtu'];
        $enDays = [0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday'];

        $idKey = $idDays[$dayOfWeek] ?? null;
        $enKey = $enDays[$dayOfWeek] ?? null;

        foreach ($hours as $key => $val) {
            $cleanKey = strtolower(trim((string)$key));
            if ($cleanKey === $idKey || $cleanKey === $enKey) {
                return (string)$val;
            }
        }

        return null;
    }

    /**
     * Parse human readable time string like "07.30–23.00", "Buka 24 jam", or "Tutup".
     */
    protected function parseTimeString(?string $val): ?array
    {
        if (!$val) {
            return null;
        }

        $valLower = strtolower(trim($val));
        if (str_contains($valLower, '24 jam') || str_contains($valLower, '24 hours') || str_contains($valLower, 'open 24')) {
            return ['is_24h' => true];
        }
        if (str_contains($valLower, 'tutup') || str_contains($valLower, 'closed')) {
            return ['is_closed' => true];
        }

        // Match time range: 07.30–23.00 or 08:00 - 21:00 or 08.00-20.00
        if (preg_match('/(\d{1,2})[\.:](\d{2})\s*[\x{2013}\x{2014}\-\to]+\s*(\d{1,2})[\.:](\d{2})/u', $val, $matches)) {
            return [
                'open_hour' => sprintf('%02d%02d', (int)$matches[1], (int)$matches[2]),
                'close_hour' => sprintf('%02d%02d', (int)$matches[3], (int)$matches[4]),
                'open_formatted' => sprintf('%02d.%02d', (int)$matches[1], (int)$matches[2]),
                'close_formatted' => sprintf('%02d.%02d', (int)$matches[3], (int)$matches[4]),
            ];
        }

        return null;
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

        $message = $customMessage ?? "Halo *{$this->name}*, saya mahasiswa USU ingin bertanya mengenai layanan fotokopi dan cetak dokumen melalui USUStarterMap.";

        return "https://wa.me/{$phone}?text=" . rawurlencode($message);
    }

    /**
     * Get preferred Google Maps URI for directions & pin viewing.
     */
    public function getEffectiveGoogleMapsUrlAttribute(): string
    {
        if (!empty($this->google_maps_uri)) {
            return $this->google_maps_uri;
        }

        if (!empty($this->google_maps_url)) {
            return $this->google_maps_url;
        }

        if ($this->latitude && $this->longitude) {
            return "https://maps.google.com/?q={$this->latitude},{$this->longitude}";
        }

        return "https://maps.google.com/?q=" . urlencode("{$this->name} USU Medan");
    }

    /**
     * In-app navigation URL directly inside USUClassMap interactive map.
     */
    public function getDirectionUrl(): string
    {
        return route('map', ['route_to' => 'fotocopy:' . $this->id]);
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

        if (str_starts_with($this->facebook, 'http')) {
            return $this->facebook;
        }

        return "https://facebook.com/" . trim($this->facebook);
    }
}
