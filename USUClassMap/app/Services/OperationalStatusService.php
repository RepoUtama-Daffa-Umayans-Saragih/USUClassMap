<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * OperationalStatusService
 *
 * Layanan terpusat untuk mengevaluasi status Buka/Tutup real-time berbasis zona waktu
 * WIB (Asia/Jakarta) pada seluruh 77 Gedung Perkuliahan USU (07:00 - 16:00 WIB, Senin-Sabtu)
 * serta seluruh 32 Mitra Fotokopi berdasarkan jam operasional aktual masing-masing toko.
 */
class OperationalStatusService
{
    public const TIMEZONE = 'Asia/Jakarta';
    public const BUILDING_OPEN_TIME = '07:00';
    public const BUILDING_CLOSE_TIME = '16:00';
    public const COLOR_BUILDING_OPEN = '#10B981';
    public const COLOR_PHOTOCOPY_OPEN = '#0D9488';
    public const COLOR_CLOSED = '#DC2626';

    /**
     * Evaluasi status operasional real-time gedung/ruang kelas USU (07:00 - 16:00 WIB, Senin-Sabtu).
     *
     * @param mixed $building
     * @return array<string, mixed>
     */
    public function evaluateBuildingStatus($building = null, ?Carbon $now = null): array
    {
        $wib = $this->toWib($now);
        $minutesNow = ($wib->hour * 60) + $wib->minute;
        $dayOfWeek = (int) $wib->dayOfWeek; // 0 = Sunday .. 6 = Saturday

        $openMin = $this->timeToMinutes(self::BUILDING_OPEN_TIME);   // 420 (07:00)
        $closeMin = $this->timeToMinutes(self::BUILDING_CLOSE_TIME); // 960 (16:00)

        $isSunday = $dayOfWeek === Carbon::SUNDAY;
        $isOpenNow = !$isSunday && $minutesNow >= $openMin && $minutesNow < $closeMin;

        if ($isOpenNow) {
            $detailText = 'Buka sampai ' . self::BUILDING_CLOSE_TIME . ' WIB';
        } elseif ($isSunday) {
            $detailText = 'Tutup (Hari Minggu) • Buka Senin pukul ' . self::BUILDING_OPEN_TIME . ' WIB';
        } elseif ($minutesNow < $openMin) {
            $detailText = 'Belum buka • Buka pukul ' . self::BUILDING_OPEN_TIME . ' WIB';
        } elseif ($dayOfWeek === Carbon::SATURDAY) {
            $detailText = 'Sudah tutup • Buka Senin pukul ' . self::BUILDING_OPEN_TIME . ' WIB';
        } else {
            $detailText = 'Sudah tutup • Buka besok pukul ' . self::BUILDING_OPEN_TIME . ' WIB';
        }

        return [
            'is_open_now'      => $isOpenNow,
            'status_code'      => $isOpenNow ? 'open' : 'closed',
            'status_label'     => $isOpenNow ? 'Buka' : 'Tutup',
            'hours_label'      => self::BUILDING_OPEN_TIME . ' - ' . self::BUILDING_CLOSE_TIME . ' WIB',
            'detail_text'      => $detailText,
            'marker_color'     => $isOpenNow ? self::COLOR_BUILDING_OPEN : self::COLOR_CLOSED,
            'open_time'        => self::BUILDING_OPEN_TIME,
            'close_time'       => self::BUILDING_CLOSE_TIME,
            'operating_days'   => 'Senin - Sabtu',
            'evaluated_at_wib' => $wib->format('H:i'),
        ];
    }

    /**
     * Evaluasi status operasional real-time mitra fotokopi berdasarkan jam buka-tutup aktual WIB.
     *
     * @param mixed $place
     * @return array<string, mixed>
     */
    public function evaluatePhotocopyStatus($place, ?Carbon $now = null): array
    {
        $wib = $this->toWib($now);
        $minutesNow = ($wib->hour * 60) + $wib->minute;
        $dayOfWeek = (int) $wib->dayOfWeek;

        [$openTime, $closeTime, $operatingDays] = $this->extractStoreSchedule($place, $wib);
        $hoursLabel = "{$openTime} - {$closeTime} WIB";

        $isActive = $this->readField($place, 'is_active');
        $isManualOpen = $this->readField($place, 'is_open');
        $businessStatus = strtoupper((string) $this->readField($place, 'business_status', ''));

        if ($businessStatus === 'CLOSED_PERMANENTLY') {
            return $this->closedPayload($hoursLabel, 'Tutup permanen', $openTime, $closeTime, $operatingDays, $wib);
        }

        if (
            $businessStatus === 'CLOSED_TEMPORARILY'
            || ($isActive !== null && !(bool) $isActive)
            || ($isManualOpen !== null && !(bool) $isManualOpen)
        ) {
            return $this->closedPayload($hoursLabel, 'Tutup sementara', $openTime, $closeTime, $operatingDays, $wib);
        }

        // Check structured 24/7 or day-closed flags when Google/SerpApi schedule exists
        $structured = $this->evaluateStructuredOpeningHours($place, $wib);
        if ($structured !== null) {
            return $structured;
        }

        $isOperatingDay = $this->isOperatingDay($operatingDays, $dayOfWeek);
        $openMin = $this->timeToMinutes($openTime);
        $closeMin = $this->timeToMinutes($closeTime);

        $isOpenNow = false;
        if ($isOperatingDay) {
            if ($closeMin < $openMin) {
                $isOpenNow = ($minutesNow >= $openMin || $minutesNow < $closeMin);
            } else {
                $isOpenNow = ($minutesNow >= $openMin && $minutesNow < $closeMin);
            }
        }

        if ($isOpenNow) {
            $detailText = "Buka sampai {$closeTime} WIB";
        } elseif (!$isOperatingDay) {
            $nextOpenLabel = $this->nextOperatingDayLabel($operatingDays, $dayOfWeek);
            $detailText = "Tutup hari ini • Buka {$nextOpenLabel} pukul {$openTime} WIB";
        } elseif ($minutesNow < $openMin) {
            $detailText = "Belum buka • Buka pukul {$openTime} WIB";
        } else {
            $nextOpenLabel = $this->isOperatingDay($operatingDays, ($dayOfWeek + 1) % 7)
                ? 'besok'
                : $this->nextOperatingDayLabel($operatingDays, $dayOfWeek);
            $detailText = "Sudah tutup • Buka {$nextOpenLabel} pukul {$openTime} WIB";
        }

        return [
            'is_open_now'      => $isOpenNow,
            'status_code'      => $isOpenNow ? 'open' : 'closed',
            'status_label'     => $isOpenNow ? 'Buka' : 'Tutup',
            'hours_label'      => $hoursLabel,
            'detail_text'      => $detailText,
            'marker_color'     => $isOpenNow ? self::COLOR_PHOTOCOPY_OPEN : self::COLOR_CLOSED,
            'open_time'        => $openTime,
            'close_time'       => $closeTime,
            'operating_days'   => $operatingDays,
            'evaluated_at_wib' => $wib->format('H:i'),
        ];
    }

    /**
     * @param mixed $target
     * @param mixed $default
     * @return mixed
     */
    protected function readField($target, string $key, $default = null)
    {
        if (is_array($target)) {
            return $target[$key] ?? $default;
        }
        if (is_object($target)) {
            if (method_exists($target, 'getRawOriginal') && in_array($key, ['is_open', 'is_active', 'open_time', 'close_time', 'operating_days', 'opening_hours', 'business_status'], true)) {
                $attrs = method_exists($target, 'getAttributes') ? $target->getAttributes() : [];
                if (array_key_exists($key, $attrs)) {
                    return $target->{$key};
                }
                return $default;
            }
            return $target->{$key} ?? $default;
        }

        return $default;
    }

    protected function toWib(?Carbon $now): Carbon
    {
        return ($now ? $now->copy() : Carbon::now(self::TIMEZONE))->timezone(self::TIMEZONE);
    }

    /**
     * @param mixed $place
     * @return array{0: string, 1: string, 2: string}
     */
    protected function extractStoreSchedule($place, Carbon $wib): array
    {
        $openRaw = $this->readField($place, 'open_time');
        $closeRaw = $this->readField($place, 'close_time');
        $operatingDays = trim((string) $this->readField($place, 'operating_days', 'Senin - Sabtu')) ?: 'Senin - Sabtu';

        if ((!is_string($openRaw) || trim($openRaw) === '') || (!is_string($closeRaw) || trim($closeRaw) === '')) {
            $ohRaw = $this->readField($place, 'opening_hours');
            if (is_string($ohRaw) && preg_match('/(\d{1,2}[:.]\d{2})\s*[-–]\s*(\d{1,2}[:.]\d{2})/u', $ohRaw, $m)) {
                $openRaw = $openRaw ?: $m[1];
                $closeRaw = $closeRaw ?: $m[2];
            }
        }

        $openTime = $this->normalizeTime((string) ($openRaw ?: '08:00'), '08:00');
        $closeTime = $this->normalizeTime((string) ($closeRaw ?: '21:00'), '21:00');

        return [$openTime, $closeTime, $operatingDays];
    }

    /**
     * Evaluasi format opening_hours array (Google Places / SerpApi) bila tersedia dan tidak kosong.
     *
     * @param mixed $place
     * @return array<string, mixed>|null
     */
    protected function evaluateStructuredOpeningHours($place, Carbon $wib): ?array
    {
        $oh = $this->readField($place, 'opening_hours');
        if (!is_array($oh) || empty($oh)) {
            return null;
        }

        $operatingDays = trim((string) $this->readField($place, 'operating_days', 'Senin - Sabtu')) ?: 'Senin - Sabtu';
        $minutesNow = ($wib->hour * 60) + $wib->minute;
        $dayOfWeek = (int) $wib->dayOfWeek;

        if (!empty($oh['periods']) && is_array($oh['periods'])) {
            $periods = $oh['periods'];
            if (count($periods) === 1 && isset($periods[0]['open']) && !isset($periods[0]['close'])) {
                return [
                    'is_open_now'      => true,
                    'status_code'      => 'open',
                    'status_label'     => 'Buka',
                    'hours_label'      => '24 Jam WIB',
                    'detail_text'      => 'Buka 24 Jam',
                    'marker_color'     => self::COLOR_PHOTOCOPY_OPEN,
                    'open_time'        => '00:00',
                    'close_time'       => '23:59',
                    'operating_days'   => $operatingDays,
                    'evaluated_at_wib' => $wib->format('H:i'),
                ];
            }

            foreach ($periods as $period) {
                if ((int) ($period['open']['day'] ?? -1) !== $dayOfWeek) {
                    continue;
                }
                $openTime = sprintf('%02d:%02d', (int) ($period['open']['hour'] ?? 8), (int) ($period['open']['minute'] ?? 0));
                if (!isset($period['close'])) {
                    return [
                        'is_open_now'      => true,
                        'status_code'      => 'open',
                        'status_label'     => 'Buka',
                        'hours_label'      => '24 Jam WIB',
                        'detail_text'      => 'Buka 24 Jam',
                        'marker_color'     => self::COLOR_PHOTOCOPY_OPEN,
                        'open_time'        => $openTime,
                        'close_time'       => '23:59',
                        'operating_days'   => $operatingDays,
                        'evaluated_at_wib' => $wib->format('H:i'),
                    ];
                }
                $closeTime = sprintf('%02d:%02d', (int) ($period['close']['hour'] ?? 21), (int) ($period['close']['minute'] ?? 0));
                $openMin = $this->timeToMinutes($openTime);
                $closeMin = $this->timeToMinutes($closeTime);
                $isOpenNow = $closeMin < $openMin
                    ? ($minutesNow >= $openMin || $minutesNow < $closeMin)
                    : ($minutesNow >= $openMin && $minutesNow < $closeMin);

                return [
                    'is_open_now'      => $isOpenNow,
                    'status_code'      => $isOpenNow ? 'open' : 'closed',
                    'status_label'     => $isOpenNow ? 'Buka' : 'Tutup',
                    'hours_label'      => "{$openTime} - {$closeTime} WIB",
                    'detail_text'      => $isOpenNow ? "Buka sampai {$closeTime} WIB" : ($minutesNow < $openMin ? "Belum buka • Buka pukul {$openTime} WIB" : "Sudah tutup • Buka besok pukul {$openTime} WIB"),
                    'marker_color'     => $isOpenNow ? self::COLOR_PHOTOCOPY_OPEN : self::COLOR_CLOSED,
                    'open_time'        => $openTime,
                    'close_time'       => $closeTime,
                    'operating_days'   => $operatingDays,
                    'evaluated_at_wib' => $wib->format('H:i'),
                ];
            }

            return $this->closedPayload('Jadwal Khusus', 'Tutup hari ini', '08:00', '21:00', $operatingDays, $wib);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function closedPayload(
        string $hoursLabel,
        string $detailText,
        string $openTime,
        string $closeTime,
        string $operatingDays,
        Carbon $wib
    ): array {
        return [
            'is_open_now'      => false,
            'status_code'      => 'closed',
            'status_label'     => 'Tutup',
            'hours_label'      => $hoursLabel,
            'detail_text'      => $detailText,
            'marker_color'     => self::COLOR_CLOSED,
            'open_time'        => $openTime,
            'close_time'       => $closeTime,
            'operating_days'   => $operatingDays,
            'evaluated_at_wib' => $wib->format('H:i'),
        ];
    }

    protected function normalizeTime(string $raw, string $fallback): string
    {
        $clean = str_replace('.', ':', trim($raw));
        if (preg_match('/^(\d{1,2}):(\d{2})/', $clean, $m)) {
            $h = max(0, min(23, (int) $m[1]));
            $min = max(0, min(59, (int) $m[2]));
            return sprintf('%02d:%02d', $h, $min);
        }

        return $fallback;
    }

    protected function timeToMinutes(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return ($h * 60) + $m;
    }

    protected function isOperatingDay(string $operatingDays, int $dayOfWeek): bool
    {
        $lower = strtolower(trim($operatingDays));
        if ($lower === '' || str_contains($lower, 'setiap hari') || str_contains($lower, 'senin - minggu') || str_contains($lower, 'senin-minggu') || str_contains($lower, '24 jam')) {
            return true;
        }
        if (str_contains($lower, 'senin - jumat') || str_contains($lower, 'senin-jumat')) {
            return $dayOfWeek >= Carbon::MONDAY && $dayOfWeek <= Carbon::FRIDAY;
        }
        if (str_contains($lower, 'senin - sabtu') || str_contains($lower, 'senin-sabtu')) {
            return $dayOfWeek !== Carbon::SUNDAY;
        }

        return $dayOfWeek !== Carbon::SUNDAY;
    }

    protected function nextOperatingDayLabel(string $operatingDays, int $currentDay): string
    {
        $names = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        for ($offset = 1; $offset <= 7; $offset++) {
            $cand = ($currentDay + $offset) % 7;
            if ($this->isOperatingDay($operatingDays, $cand)) {
                return $offset === 1 ? 'besok' : $names[$cand];
            }
        }

        return 'Senin';
    }
}
