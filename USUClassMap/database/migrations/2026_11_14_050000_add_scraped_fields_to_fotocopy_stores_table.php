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
        Schema::table('fotocopy_stores', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('operating_days');
            $table->text('image_url')->nullable()->after('whatsapp');
            $table->text('google_maps_url')->nullable()->after('image_url');
            $table->string('google_place_id')->nullable()->index()->after('google_maps_url');
            $table->unsignedInteger('user_rating_count')->default(0)->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fotocopy_stores', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'image_url',
                'google_maps_url',
                'google_place_id',
                'user_rating_count',
            ]);
        });
    }
};
