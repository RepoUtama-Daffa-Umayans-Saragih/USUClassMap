<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photocopy_places', function (Blueprint $table) {
            // Google Places API (New) Identifier & Status
            $table->string('google_place_id')->nullable()->unique()->index()->after('id');
            $table->string('business_status')->default('OPERATIONAL')->after('rating'); // OPERATIONAL, CLOSED_TEMPORARILY, CLOSED_PERMANENTLY

            // Additional Detailed Contact Fields
            $table->string('phone_national')->nullable()->after('phone');
            $table->string('phone_international')->nullable()->after('phone_national');
            $table->text('whatsapp_url')->nullable()->after('whatsapp');
            $table->text('website_url')->nullable()->after('whatsapp_url');
            $table->string('email')->nullable()->after('facebook');

            // Rich Metrics & Google Maps Links
            $table->unsignedInteger('user_rating_count')->default(0)->after('rating');
            $table->text('review_summary')->nullable()->after('user_rating_count');
            $table->text('google_maps_uri')->nullable()->after('google_maps_url');

            // Structured Real-time Schedule
            $table->json('opening_hours')->nullable()->after('operating_days'); // stores regularOpeningHours (periods & weekdayDescriptions)

            // Flags & Audit Timestamps
            $table->boolean('is_verified')->default(false)->after('is_active');
            $table->boolean('is_manually_verified')->default(false)->after('is_verified'); // Protects manual edits from being overwritten by API sync
            $table->timestamp('last_synced_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photocopy_places', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};

