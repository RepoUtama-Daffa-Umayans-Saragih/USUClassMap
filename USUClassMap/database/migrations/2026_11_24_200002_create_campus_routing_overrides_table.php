<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campus_routing_overrides')) {
            Schema::create('campus_routing_overrides', function (Blueprint $table) {
                $table->id();
                $table->string('type', 30);
                $table->string('name', 150);
                $table->json('coordinates_json');
                $table->json('metadata_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['type', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_routing_overrides');
    }
};
