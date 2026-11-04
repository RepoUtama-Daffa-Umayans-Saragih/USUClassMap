<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class ScheduleImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    private int $successCount = 0;
    private int $failCount = 0;
    private array $customErrorMessages = [];

    /**
     * Map each Excel row to a Schedule model.
     * Auto-creates Course and Lecturer if they don't exist.
     */
    public function model(array $row)
    {
        try {
            // Auto-create or find the course
            $course = Course::firstOrCreate(
                ['code' => $row['kode_mk']],
                [
                    'name' => $row['nama_mk'],
                    'credits' => $row['sks'] ?? 2,
                    'type' => $row['tipe_mk'] ?? 'MKWK',
                ]
            );

            // Auto-create or find the lecturer
            $lecturer = Lecturer::firstOrCreate(
                ['name' => $row['nama_dosen']],
                ['name' => $row['nama_dosen']]
            );

            // Find room by name
            $room = Room::where('name', $row['nama_ruangan'])->first();
            if (!$room) {
                $this->failCount++;
                $this->customErrorMessages[] = "Baris: Ruangan '{$row['nama_ruangan']}' tidak ditemukan.";
                return null;
            }

            $this->successCount++;

            return new Schedule([
                'course_id' => $course->id,
                'lecturer_id' => $lecturer->id,
                'room_id' => $room->id,
                'day' => $row['hari'],
                'time_start' => $row['jam_mulai'],
                'time_end' => $row['jam_selesai'],
                'semester' => $row['semester'] ?? 'Ganjil 2024/2025',
                'class_group' => $row['kelompok_kelas'] ?? 'A',
            ]);
        } catch (\Exception $e) {
            $this->failCount++;
            $this->customErrorMessages[] = "Error: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Validation rules for each row.
     */
    public function rules(): array
    {
        return [
            'kode_mk' => 'required|string',
            'nama_mk' => 'required|string',
            'nama_dosen' => 'required|string',
            'nama_ruangan' => 'required|string',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function customValidationMessages(): array
    {
        return [
            'kode_mk.required' => 'Kode mata kuliah wajib diisi.',
            'nama_mk.required' => 'Nama mata kuliah wajib diisi.',
            'nama_dosen.required' => 'Nama dosen wajib diisi.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
            'hari.required' => 'Hari wajib diisi.',
            'hari.in' => 'Hari harus salah satu dari: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu.',
            'jam_mulai.required' => 'Jam mulai wajib diisi.',
            'jam_selesai.required' => 'Jam selesai wajib diisi.',
        ];
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getFailCount(): int
    {
        return $this->failCount;
    }

    public function getErrors(): array
    {
        return $this->customErrorMessages;
    }
}
