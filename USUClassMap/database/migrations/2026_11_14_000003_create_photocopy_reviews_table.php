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
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photocopy_place_id')->constrained('photocopy_places')->cascadeOnDelete();
            $table->string('google_review_name')->nullable(); // Unique identifier from Google if provided
            $table->string('author_name');
            $table->text('author_photo_url')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('text')->nullable();
            $table->string('relative_publish_time')->nullable(); // e.g. "2 minggu lalu"
            $table->timestamp('publish_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};

