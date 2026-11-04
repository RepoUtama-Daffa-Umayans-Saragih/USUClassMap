<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'lecturer_id',
        'room_id',
        'day',
        'time_start',
        'time_end',
        'semester',
        'class_group',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getFullScheduleLabelAttribute(): string
    {
        return sprintf(
            '%s - %s %s-%s - %s',
            $this->course->name ?? '-',
            $this->day,
            $this->time_start ? substr($this->time_start, 0, 5) : '--:--',
            $this->time_end ? substr($this->time_end, 0, 5) : '--:--',
            $this->room->name ?? '-'
        );
    }
}
