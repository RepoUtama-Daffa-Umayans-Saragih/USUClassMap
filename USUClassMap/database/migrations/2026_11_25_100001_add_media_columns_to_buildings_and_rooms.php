<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table): void {
            if (!Schema::hasColumn('buildings', 'photo_360_path')) {
                $table->string('photo_360_path')->nullable()->after('description');
            }
            if (!Schema::hasColumn('buildings', 'door_photo_path')) {
                $table->string('door_photo_path')->nullable()->after('photo_360_path');
            }
            if (!Schema::hasColumn('buildings', 'photo_360_meta')) {
                $table->json('photo_360_meta')->nullable()->after('door_photo_path');
            }
        });

        Schema::table('rooms', function (Blueprint $table): void {
            if (!Schema::hasColumn('rooms', 'door_photo_path')) {
                $table->string('door_photo_path')->nullable()->after('photo_360_path');
            }
            if (!Schema::hasColumn('rooms', 'photo_360_meta')) {
                $table->json('photo_360_meta')->nullable()->after('door_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table): void {
            $drop = array_values(array_filter(
                ['photo_360_path', 'door_photo_path', 'photo_360_meta'],
                fn (string $col): bool => Schema::hasColumn('buildings', $col)
            ));
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $drop = array_values(array_filter(
                ['door_photo_path', 'photo_360_meta'],
                fn (string $col): bool => Schema::hasColumn('rooms', $col)
            ));
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
