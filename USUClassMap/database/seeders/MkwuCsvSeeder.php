<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MkwuCsvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mengimpor 208 data jadwal MKWU riil dari data/mkwu.csv secara aman dan idempoten.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/mkwu.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("File {$csvPath} tidak ditemukan!");
            return;
        }

        $this->command->info("🚀 Memulai Import Data Jadwal MKWU dari CSV ({$csvPath})...");

        // Fallback default buildings
        $gkb = Building::where('code', 'GKB')->orWhere('name', 'like', '%Kuliah Bersama%')->first()
            ?? Building::first();
        $fib = Building::where('code', 'FIB')->orWhere('name', 'like', '%Ilmu Budaya%')->first()
            ?? $gkb;
        $fasilkom = Building::where('code', 'FASILKOM-TI')->orWhere('code', 'GD-FASILKOM')->orWhere('name', 'like', '%Komputer%')->first()
            ?? $gkb;
        $fk = Building::where('code', 'FK')->orWhere('name', 'like', '%Kedokteran%')->first()
            ?? $gkb;
        $fmipa = Building::where('code', 'FMIPA')->orWhere('name', 'like', '%MIPA%')->first()
            ?? $gkb;
        $fp = Building::where('code', 'FP')->orWhere('name', 'like', '%Pertanian%')->first()
            ?? $gkb;
        $fh = Building::where('code', 'FH')->orWhere('name', 'like', '%Hukum%')->first()
            ?? $gkb;
        $feb = Building::where('code', 'FEB')->orWhere('name', 'like', '%Ekonomi%')->first()
            ?? $gkb;
        $fisip = Building::where('code', 'FISIP')->orWhere('name', 'like', '%Sosial%')->first()
            ?? $gkb;

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command->error("Gagal membuka file {$csvPath}!");
            return;
        }

        // Header: No,Kode,Mata Kuliah,Semester,Kelas,Tipe Kelas,Tipe,SKS,Ruangan,Jadwal,Dosen
        $header = fgetcsv($handle, 1000, ',');
        $importedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (empty($row) || count($row) < 10) {
                    continue;
                }

                $code       = trim($row[1] ?? '');
                $courseName = trim($row[2] ?? '');
                $classGroup = trim($row[4] ?? 'MKWU');
                $sks        = (int)($row[7] ?? 2);
                $roomName   = trim($row[8] ?? 'GKB');
                $scheduleRaw= trim($row[9] ?? '');
                $lecturerRaw= trim($row[10] ?? 'Dosen Pengampu');

                if (empty($code) || empty($courseName)) {
                    $skippedCount++;
                    continue;
                }

                // 1. Parsing Dosen (jika multiple dosen via newline, ambil dosen pertama)
                $lecturerClean = explode("\n", $lecturerRaw)[0];
                $lecturerClean = trim(preg_replace('/\s+/', ' ', $lecturerClean));
                if (empty($lecturerClean)) {
                    $lecturerClean = 'Dosen Pengampu MKWU';
                }

                $lecturer = Lecturer::firstOrCreate(
                    ['name' => $lecturerClean],
                    [
                        'nip' => null,
                        'email' => null,
                        'phone' => null,
                    ]
                );

                // 2. Parsing Course
                $course = Course::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $courseName,
                        'credits' => $sks > 0 ? $sks : 2,
                        'type' => 'MKWU',
                    ]
                );

                // 3. Menentukan Gedung untuk Ruangan
                $targetBuilding = $gkb;
                $upperRoom = strtoupper($roomName);
                if (str_contains($upperRoom, 'FIB') || str_contains($upperRoom, 'K.301') || str_contains($upperRoom, 'K.302')) {
                    $targetBuilding = $fib;
                } elseif (str_contains($upperRoom, 'FIKTI') || str_contains($upperRoom, 'C-10') || str_contains($upperRoom, 'D-10')) {
                    $targetBuilding = $fasilkom;
                } elseif (str_contains($upperRoom, 'FK') || str_contains($upperRoom, 'PATOLOGI') || str_contains($upperRoom, 'KEDOKTERAN')) {
                    $targetBuilding = $fk;
                } elseif (str_contains($upperRoom, 'FMIPA') || str_contains($upperRoom, 'MIPA')) {
                    $targetBuilding = $fmipa;
                } elseif (str_contains($upperRoom, 'FP') || str_contains($upperRoom, 'PERTANIAN')) {
                    $targetBuilding = $fp;
                } elseif (str_contains($upperRoom, 'FH') || str_contains($upperRoom, 'HUKUM')) {
                    $targetBuilding = $fh;
                } elseif (str_contains($upperRoom, 'FEB') || str_contains($upperRoom, 'EKONOMI')) {
                    $targetBuilding = $feb;
                } elseif (str_contains($upperRoom, 'FISIP') || str_contains($upperRoom, 'SOSIAL')) {
                    $targetBuilding = $fisip;
                }

                // 4. Parsing Lantai
                $floor = 1;
                if (preg_match('/(?:Lt\.?|Lantai)\s*(\d+)/i', $roomName, $matches)) {
                    $floor = (int)$matches[1];
                } elseif (preg_match('/^[A-Z]?[-.]?([1-4])\d{2}$/i', $roomName, $matches)) {
                    $floor = (int)$matches[1];
                }

                $room = Room::firstOrCreate(
                    [
                        'building_id' => $targetBuilding->id,
                        'name' => $roomName,
                    ],
                    [
                        'floor' => $floor,
                        'capacity' => 40,
                    ]
                );

                // 5. Parsing Jadwal (Contoh: "Selasa, 09:50 - 11:30")
                $day = 'Senin';
                $timeStart = '08:00';
                $timeEnd = '09:40';

                if (preg_match('/^(Senin|Selasa|Rabu|Kamis|Jumat|Sabtu)[\s,]+(\d{1,2}[:.]\d{2})\s*[-–]\s*(\d{1,2}[:.]\d{2})/i', $scheduleRaw, $matches)) {
                    $day = ucfirst(strtolower($matches[1]));
                    $timeStart = str_replace('.', ':', $matches[2]);
                    $timeEnd = str_replace('.', ':', $matches[3]);
                    if (strlen($timeStart) === 4) $timeStart = '0' . $timeStart;
                    if (strlen($timeEnd) === 4) $timeEnd = '0' . $timeEnd;
                }

                // 6. Simpan Jadwal Idempoten
                Schedule::firstOrCreate(
                    [
                        'course_id'   => $course->id,
                        'lecturer_id' => $lecturer->id,
                        'room_id'     => $room->id,
                        'day'         => $day,
                        'time_start'  => $timeStart,
                        'time_end'    => $timeEnd,
                        'class_group' => $classGroup,
                        'semester'    => 'Ganjil 2026/2027',
                    ]
                );

                $importedCount++;
            }

            fclose($handle);
            DB::commit();

            $this->command->newLine();
            $this->command->info("=======================================================");
            $this->command->info("🎉 IMPORT CSV MKWU SELESAI DENGAN SUKSES");
            $this->command->info("=======================================================");
            $this->command->line("📊 Total Jadwal Berhasil Di-Seed : <info>{$importedCount}</info>");
            if ($skippedCount > 0) {
                $this->command->line("⚠️  Total Baris Di-Skip           : <comment>{$skippedCount}</comment>");
            }
            $this->command->info("=======================================================");

        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();
            $this->command->error("Terjadi error saat import CSV: " . $e->getMessage());
        }
    }
}
