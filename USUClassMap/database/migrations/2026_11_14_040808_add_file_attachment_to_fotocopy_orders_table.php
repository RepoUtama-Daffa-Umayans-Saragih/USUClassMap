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
        Schema::table('fotocopy_orders', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('notes');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_size')->nullable()->after('file_name');
            $table->string('file_type')->nullable()->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fotocopy_orders', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_size', 'file_type']);
        });
    }
};
