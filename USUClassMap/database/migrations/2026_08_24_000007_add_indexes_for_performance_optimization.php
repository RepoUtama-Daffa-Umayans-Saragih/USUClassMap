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
        Schema::table('courses', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['day', 'time_start']);
        });

        Schema::table('lecturers', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['day', 'time_start']);
        });

        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
