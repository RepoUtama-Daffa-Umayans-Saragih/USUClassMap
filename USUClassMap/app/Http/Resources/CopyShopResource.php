<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CopyShopResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'google_place_id' => $this->google_place_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => [
                'name' => $this->location_name,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'status' => [
                'business_status' => $this->business_status,
                'is_open_now' => $this->is_open_now,
                'schedule_status_text' => $this->current_schedule_status,
                'operating_days' => $this->operating_days,
                'open_time' => $this->open_time,
                'close_time' => $this->close_time,
                'opening_hours_raw' => $this->opening_hours,
            ],
            'rating' => [
                'score' => $this->rating,
                'user_rating_count' => $this->user_rating_count,
                'review_summary' => $this->review_summary,
            ],
            'contacts' => [
                'phone' => $this->phone,
                'phone_national' => $this->phone_national,
                'phone_international' => $this->phone_international,
                'whatsapp' => $this->whatsapp,
                'whatsapp_url' => $this->getWhatsappUrl(),
                'website_url' => $this->website_url,
                'instagram_handle' => $this->instagram,
                'instagram_url' => $this->getInstagramUrl(),
                'facebook_url' => $this->getFacebookUrl(),
                'email' => $this->email,
            ],
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'slug' => $s->slug,
                        'category' => $s->category,
                        'icon' => $s->icon,
                        'starting_price' => $s->pivot->starting_price,
                        'unit' => $s->pivot->unit,
                        'notes' => $s->pivot->notes,
                        'is_auto_detected' => (bool)$s->pivot->is_auto_detected,
                    ];
                });
            }),
            'catalog_items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($it) {
                    return [
                        'id' => $it->id,
                        'name' => $it->name,
                        'category' => $it->category,
                        'price' => $it->price,
                        'formatted_price' => $it->formatted_price,
                        'unit' => $it->unit,
                        'is_available' => (bool)$it->is_available,
                    ];
                });
            }),
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'author_name' => $r->author_name,
                        'author_photo_url' => $r->author_photo_url,
                        'rating' => $r->rating,
                        'text' => $r->text,
                        'relative_publish_time' => $r->relative_publish_time,
                        'publish_time' => $r->publish_time?->toIso8601String(),
                    ];
                });
            }),
            'sharing' => [
                'web_url' => route('fotocopy.show', $this->id),
                'order_url' => route('fotocopy.order', $this->id),
                'google_maps_uri' => $this->effective_google_maps_url,
                'share_text' => "{$this->name} - Layanan Fotokopi & Print Kampus USU ({$this->current_schedule_status}). Info & Pesan: " . route('fotocopy.show', $this->id),
            ],
            'flags' => [
                'is_verified' => (bool)$this->is_verified,
                'is_manually_verified' => (bool)$this->is_manually_verified,
                'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            ],
        ];
    }
}

