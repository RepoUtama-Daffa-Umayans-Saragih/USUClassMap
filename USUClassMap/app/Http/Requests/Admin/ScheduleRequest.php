<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'lecturer_id' => 'required|exists:lecturers,id',
            'room_id' => 'required|exists:rooms,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'semester' => 'required|string|max:50',
            'class_group' => 'required|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Mata kuliah wajib dipilih.',
            'course_id.exists' => 'Mata kuliah tidak valid.',
            'lecturer_id.required' => 'Dosen wajib dipilih.',
            'lecturer_id.exists' => 'Dosen tidak valid.',
            'room_id.required' => 'Ruangan wajib dipilih.',
            'room_id.exists' => 'Ruangan tidak valid.',
            'day.required' => 'Hari wajib dipilih.',
            'day.in' => 'Hari tidak valid.',
            'time_start.required' => 'Jam mulai wajib diisi.',
            'time_start.date_format' => 'Format jam mulai harus HH:MM.',
            'time_end.required' => 'Jam selesai wajib diisi.',
            'time_end.date_format' => 'Format jam selesai harus HH:MM.',
            'time_end.after' => 'Jam selesai harus setelah jam mulai.',
            'semester.required' => 'Semester wajib diisi.',
            'class_group.required' => 'Kelompok kelas wajib diisi.',
        ];
    }
}
