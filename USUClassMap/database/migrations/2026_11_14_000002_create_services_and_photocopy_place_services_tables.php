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
        // 1. Master Services Table
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->default('fa-solid fa-print'); // FontAwesome icon class
            $table->string('category')->default('Percetakan'); // Percetakan, Penjilidan, Dokumen, ATK
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Pivot Table: photocopy_place_service
        Schema::create('photocopy_place_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photocopy_place_id')->constrained('photocopy_places')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('starting_price')->nullable();
            $table->string('unit')->nullable()->default('lembar'); // lembar, buku, rim, pcs
            $table->text('notes')->nullable();
            $table->boolean('is_auto_detected')->default(false); // true if detected by NLP keyword parser from reviews
            $table->timestamps();

            $table->unique(['photocopy_place_id', 'service_id'], 'place_service_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photocopy_place_service');
        Schema::dropIfExists('services');
    }
};

