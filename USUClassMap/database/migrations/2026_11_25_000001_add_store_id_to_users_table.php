<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'store_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('fotocopy_stores')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'store_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_id');
            });
        }
    }
};
