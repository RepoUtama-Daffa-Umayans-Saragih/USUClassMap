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
        Schema::create('photocopy_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photocopy_place_id')->constrained('photocopy_places')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Print'); // Print, Fotocopy, Jilid, Finishing, ATK
            $table->integer('price')->default(0);
            $table->string('unit')->default('lembar'); // lembar, buku, rim, pcs
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photocopy_items');
    }
};

