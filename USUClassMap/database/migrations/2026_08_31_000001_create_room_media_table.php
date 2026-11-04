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
        Schema::create('room_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->enum('type', ['photo', '360', 'video'])->default('photo');
            $table->string('file_path');
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable()->comment('Stores 360 pitch, yaw, hotspots, linked scenes');
            $table->timestamps();

            $table->index(['room_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_media');
    }
};
