<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photocopy_places', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('photocopy_places')->whereRaw('CHAR_LENGTH(image_url) > 255')->exists()) {
            throw new RuntimeException('Cannot shorten photocopy image URLs to 255 characters without data loss.');
        }

        Schema::table('photocopy_places', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }
};
