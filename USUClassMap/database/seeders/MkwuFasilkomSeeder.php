<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Building;
use App\Models\Room;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Schedule;

class MkwuFasilkomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("🚀 Memulai Seeding Jadwal MKWU FASILKOM-TI (Hardcoded Dataset)...");

        // 1. DATASET HARDCODE JADWAL MKWU FASILKOM-TI
        $mkwuData = [
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'FIKTI',
                'room_name'     => 'C-103',
                'schedule_text' => 'Selasa, 09:50 - 11:30',
                'lecturer_name' => 'Fikry Prastya Syahputra S.S., M.Si.',
            ],
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'EIKTI',
                'room_name'     => 'C-102',
                'schedule_text' => 'Senin, 13:30 - 15:10',
                'lecturer_name' => 'Dr. Alemina Br. Perangin-angin S.S., M.Hum.',
            ],
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'DIKTI',
                'room_name'     => 'C-102',
                'schedule_text' => 'Selasa, 09:50 - 11:30',
                'lecturer_name' => 'Prof. Dr. Rusdi Noor Rosa S.S., M.Hum',
            ],
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'CIKTI',
                'room_name'     => 'D-106',
                'schedule_text' => 'Selasa, 08:00 - 09:40',
                'lecturer_name' => 'Prof. Dr. Rusdi Noor Rosa S.S., M.Hum',
            ],
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'BIKTI',
                'room_name'     => 'D-105',
                'schedule_text' => 'Senin, 13:30 - 15:10',
                'lecturer_name' => 'Prof. Dr. Rudy Sofyan SS., M.Hum.',
            ],
            [
                'course_code'   => 'SAE3108',
                'course_name'   => 'IELTS Preparation Internasionalisasi',
                'credits'       => 2,
                'class_group'   => 'AIKTI',
                'room_name'     => 'D-104',
                'schedule_text' => 'Senin, 09:50 - 11:30',
                'lecturer_name' => 'Prof. Dr. Rudy Sofyan SS., M.Hum.',
            ],
            [
                'course_code'   => 'AKT1206',
                'course_name'   => 'Komputerisasi Ekonomi dan Bisnis Literacy Skills',
                'credits'       => 2,
                'class_group'   => 'KWU-A',
                'room_name'     => 'GL 1',
                'schedule_text' => 'Senin, 13:00 - 14:40',
                'lecturer_name' => 'Taufik Akbar Parluhutan SE, M.Si',
            ],
        ];

        DB::beginTransaction();

        try {
            // 2. ELOQUENT firstOrCreate: Building (Gedung FASILKOM-TI)
            $building = Building::firstOrCreate(
                ['code' => 'GD-FASILKOM'],
                [
                    'name'        => 'Fakultas Ilmu Komputer dan Teknologi Informasi',
                    'faculty'     => 'Fakultas Ilmu Komputer dan Teknologi Informasi',
                    'latitude'    => 3.565245,
                    'longitude'   => 98.656520,
                    'description' => 'Gedung Perkuliahan FASILKOM-TI USU',
                    'floor_count' => 3,
                ]
            );

            $seededCount = 0;

            // 3. RELATIONAL MAPPING ITERATION
            foreach ($mkwuData as $item) {
                // Parsing Hari, Jam Mulai, dan Jam Selesai
                $parsedJadwal = $this->parseScheduleText($item['schedule_text']);

                // A. Mata Kuliah (courses)
                $course = Course::firstOrCreate(
                    ['code' => $item['course_code']],
                    [
                        'name'    => $item['course_name'],
                        'credits' => $item['credits'],
                        'type'    => 'MKWU',
                    ]
                );

                // B. Dosen (lecturers)
                $lecturer = Lecturer::firstOrCreate(
                    ['name' => $item['lecturer_name']],
                    [
                        'nip'   => null,
                        'email' => null,
                        'phone' => null,
                    ]
                );

                // C. Ruangan (rooms)
                $floor = $this->extractFloorNumber($item['room_name']);
                $room = Room::firstOrCreate(
                    [
                        'building_id' => $building->id,
                        'name'        => $item['room_name'],
                    ],
                    [
                        'floor'    => $floor,
                        'capacity' => 40,
                    ]
                );

                // D. Jadwal (schedules)
                Schedule::firstOrCreate(
                    [
                        'course_id'   => $course->id,
                        'lecturer_id' => $lecturer->id,
                        'room_id'     => $room->id,
                        'day'         => $parsedJadwal['day'],
                        'time_start'  => $parsedJadwal['time_start'],
                        'time_end'    => $parsedJadwal['time_end'],
                        'class_group' => $item['class_group'],
                        'semester'    => 'Ganjil 2026/2027',
                    ]
                );

                $seededCount++;
            }

            DB::commit();

            $this->command->newLine();
            $this->command->info("=======================================================");
            $this->command->info("🎉 SEEDING SELESAI DENGAN SUKSES (DB COMMIT)");
            $this->command->info("=======================================================");
            $this->command->line("📊 Total Jadwal Fasilkom-TI Berhasil di-Seed : <info>{$seededCount}</info>");
            $this->command->info("=======================================================");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error("❌ Terjadi kesalahan saat seeding: " . $e->getMessage());
            $this->command->error("   Database telah di-rollback secara aman.");
        }
    }

    /**
     * Memecah string format: "Hari, JamMulai - JamSelesai"
     * Contoh: "Selasa, 09:50 - 11:30" => ['day' => 'Selasa', 'time_start' => '09:50', 'time_end' => '11:30']
     */
    private function parseScheduleText(string $text): array
    {
        $pattern = '/^([A-Za-z]+)\s*,\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/u';

        if (preg_match($pattern, trim($text), $matches)) {
            return [
                'day'        => ucfirst(strtolower(trim($matches[1]))),
                'time_start' => sprintf('%02d:%02d', ...explode(':', trim($matches[2]))),
                'time_end'   => sprintf('%02d:%02d', ...explode(':', trim($matches[3]))),
            ];
        }

        // Fallback default jika teks tidak berpola standar
        return [
            'day'        => 'Senin',
            'time_start' => '08:00',
            'time_end'   => '09:40',
        ];
    }

    /**
     * Ekstrak lantai dari nama ruangan (Contoh: C-103 => 1, D-106 => 1, GL 1 => 1)
     */
    private function extractFloorNumber(string $roomName): int
    {
        if (preg_match('/(?:[A-Za-z\.\-\s]+)?([1-9])\d{2}/', $roomName, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(?:GL|Lt|Lantai)\s*([1-9])\b/i', $roomName, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }
}
