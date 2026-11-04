<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('day'); // Senin, Selasa, Rabu, Kamis, Jumat, Sabtu
            $table->time('time_start');
            $table->time('time_end');
            $table->string('semester');
            $table->string('class_group');
            $table->timestamps();

            $table->index('day');
            $table->index('semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
