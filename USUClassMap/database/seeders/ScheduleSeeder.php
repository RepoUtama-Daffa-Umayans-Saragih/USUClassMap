<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all()->keyBy('code');
        $lecturers = Lecturer::all()->keyBy('nip');
        $rooms = Room::all()->keyBy('name');

        if ($courses->isEmpty() || $lecturers->isEmpty() || $rooms->isEmpty()) {
            return;
        }

        $schedules = [
            // ================= SENIN =================
            [
                'course_code' => 'UNI101',
                'lecturer_nip' => '196504151990031002', // Prof. Arif
                'room_name' => 'GK1-101',
                'day' => 'Senin',
                'time_start' => '08:00',
                'time_end' => '09:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],
            [
                'course_code' => 'UNI104',
                'lecturer_nip' => '197008201995122001', // Dr. Siti Rahmah
                'room_name' => 'GK1-102',
                'day' => 'Senin',
                'time_start' => '08:00',
                'time_end' => '09:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'B',
            ],
            [
                'course_code' => 'KOM201',
                'lecturer_nip' => '198806122015041001', // Rahmat Hidayat
                'room_name' => 'Lab Software Engineering',
                'day' => 'Senin',
                'time_start' => '10:00',
                'time_end' => '12:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-A',
            ],
            [
                'course_code' => 'UNI106',
                'lecturer_nip' => '197511102001121003', // Dr. Budi Santoso
                'room_name' => 'GK2-101',
                'day' => 'Senin',
                'time_start' => '13:00',
                'time_end' => '14:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'C',
            ],

            // ================= SELASA =================
            [
                'course_code' => 'UNI105',
                'lecturer_nip' => '197008201995122001', // Dr. Siti Rahmah
                'room_name' => 'GK1-201',
                'day' => 'Selasa',
                'time_start' => '08:00',
                'time_end' => '09:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],
            [
                'course_code' => 'KOM202',
                'lecturer_nip' => '199002182019031008', // Faisal Reza
                'room_name' => 'Lab Artificial Intelligence',
                'day' => 'Selasa',
                'time_start' => '10:00',
                'time_end' => '12:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-B',
            ],
            [
                'course_code' => 'UNI107',
                'lecturer_nip' => '197803252003122002', // Dra. Nur Asiah
                'room_name' => 'GK2-201',
                'day' => 'Selasa',
                'time_start' => '10:00',
                'time_end' => '11:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],
            [
                'course_code' => 'UNI108',
                'lecturer_nip' => '197209141998022001', // Dra. Marlina Siregar
                'room_name' => 'Ruang Kuliah FEB-101',
                'day' => 'Selasa',
                'time_start' => '13:00',
                'time_end' => '14:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'B',
            ],

            // ================= RABU =================
            [
                'course_code' => 'KOM203',
                'lecturer_nip' => '196903221994032001', // Dr. Erna Nababan
                'room_name' => 'Ruang Kuliah TI-201',
                'day' => 'Rabu',
                'time_start' => '08:00',
                'time_end' => '10:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-A',
            ],
            [
                'course_code' => 'UNI109',
                'lecturer_nip' => '196812041994031001', // Dr. Henry Sitompul
                'room_name' => 'GK1-301',
                'day' => 'Rabu',
                'time_start' => '08:00',
                'time_end' => '09:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],
            [
                'course_code' => 'UNI101',
                'lecturer_nip' => '196504151990031002', // Prof. Arif
                'room_name' => 'GK1-101',
                'day' => 'Rabu',
                'time_start' => '10:00',
                'time_end' => '11:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'B',
            ],
            [
                'course_code' => 'UNI110',
                'lecturer_nip' => '198806122015041001', // Rahmat Hidayat
                'room_name' => 'Ruang Diskusi Lantai 2',
                'day' => 'Rabu',
                'time_start' => '13:00',
                'time_end' => '14:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'C',
            ],

            // ================= KAMIS =================
            [
                'course_code' => 'KOM204',
                'lecturer_nip' => '196208101988031002', // Prof. Opim Salim
                'room_name' => 'Ruang Seminar TI-301',
                'day' => 'Kamis',
                'time_start' => '08:00',
                'time_end' => '10:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-A',
            ],
            [
                'course_code' => 'UNI102',
                'lecturer_nip' => '196812041994031001', // Dr. Henry Sitompul
                'room_name' => 'GK1-102',
                'day' => 'Kamis',
                'time_start' => '10:00',
                'time_end' => '11:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],
            [
                'course_code' => 'UNI105',
                'lecturer_nip' => '197008201995122001', // Dr. Siti Rahmah
                'room_name' => 'GK1-202',
                'day' => 'Kamis',
                'time_start' => '10:00',
                'time_end' => '11:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'B',
            ],
            [
                'course_code' => 'UNI106',
                'lecturer_nip' => '197511102001121003', // Dr. Budi Santoso
                'room_name' => 'Ruang Kuliah FEB-201',
                'day' => 'Kamis',
                'time_start' => '13:00',
                'time_end' => '14:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'D',
            ],

            // ================= JUMAT =================
            [
                'course_code' => 'KOM205',
                'lecturer_nip' => '199002182019031008', // Faisal Reza
                'room_name' => 'Lab Software Engineering',
                'day' => 'Jumat',
                'time_start' => '08:00',
                'time_end' => '10:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-A',
            ],
            [
                'course_code' => 'UNI104',
                'lecturer_nip' => '197008201995122001', // Dr. Siti Rahmah
                'room_name' => 'GK2-301',
                'day' => 'Jumat',
                'time_start' => '08:00',
                'time_end' => '09:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'C',
            ],
            [
                'course_code' => 'UNI108',
                'lecturer_nip' => '197209141998022001', // Dra. Marlina Siregar
                'room_name' => 'Digital Learning Theater',
                'day' => 'Jumat',
                'time_start' => '14:00',
                'time_end' => '15:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'A',
            ],

            // ================= SABTU =================
            [
                'course_code' => 'UNI109',
                'lecturer_nip' => '196812041994031001', // Dr. Henry Sitompul
                'room_name' => 'Auditorium Utama',
                'day' => 'Sabtu',
                'time_start' => '08:30',
                'time_end' => '11:00',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'Paralel-1',
            ],
            [
                'course_code' => 'KOM201',
                'lecturer_nip' => '198806122015041001', // Rahmat Hidayat
                'room_name' => 'Lab Artificial Intelligence',
                'day' => 'Sabtu',
                'time_start' => '09:00',
                'time_end' => '11:30',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'KOM-B',
            ],
            [
                'course_code' => 'UNI107',
                'lecturer_nip' => '197803252003122002', // Dra. Nur Asiah
                'room_name' => 'Lab Bahasa GK2',
                'day' => 'Sabtu',
                'time_start' => '11:00',
                'time_end' => '12:40',
                'semester' => 'Ganjil 2024/2025',
                'class_group' => 'R-1',
            ],
        ];

        foreach ($schedules as $s) {
            $course = $courses[$s['course_code']] ?? null;
            $lecturer = $lecturers[$s['lecturer_nip']] ?? null;
            $room = $rooms[$s['room_name']] ?? null;

            if ($course && $lecturer && $room) {
                Schedule::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'day' => $s['day'],
                        'time_start' => $s['time_start'],
                        'class_group' => $s['class_group'],
                    ],
                    [
                        'course_id' => $course->id,
                        'lecturer_id' => $lecturer->id,
                        'room_id' => $room->id,
                        'day' => $s['day'],
                        'time_start' => $s['time_start'],
                        'time_end' => $s['time_end'],
                        'semester' => $s['semester'],
                        'class_group' => $s['class_group'],
                    ]
                );
            }
        }
    }
}
