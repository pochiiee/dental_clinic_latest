<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Schedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'schedule_date',
        'start_time',
        'end_time',
        'is_available',
        'max_patients',
        'booked_count',
        'unavailable_reason',
        'lock_until',
        'locked_by',
    ];

    protected $casts = [
        'schedule_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_available' => 'boolean',
        'max_patients' => 'integer',
        'booked_count' => 'integer',
        'lock_until' => 'datetime',
    ];

    protected $appends = [
        'start_time_formatted',
        'end_time_formatted',
        'time_slot',
        'is_available_status',
        'remaining_slots',
        'is_fully_booked',
    ];

    /**
     * Define fixed time slots 
     */
    public static function getFixedTimeSlots(): array
    {
        return [
            'morning'    => ['start' => '10:00:00', 'end' => '12:00:00'],
            'afternoon1' => ['start' => '13:00:00', 'end' => '15:00:00'],
            'afternoon2' => ['start' => '15:00:00', 'end' => '17:00:00'],
        ];
    }

    /** Accessors */
    public function getStartTimeFormattedAttribute(): string
    {
        return $this->start_time ? $this->start_time->format('g:i A') : '';
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return $this->end_time ? $this->end_time->format('g:i A') : '';
    }

    public function getTimeSlotAttribute(): string
    {
        return "{$this->start_time_formatted} - {$this->end_time_formatted}";
    }

    public function getIsAvailableStatusAttribute(): bool
    {
        return $this->isAvailable();
    }

    public function getRemainingSlotsAttribute(): int
    {
        return max(0, $this->max_patients - $this->booked_count);
    }

    public function getIsFullyBookedAttribute(): bool
    {
        return $this->booked_count >= $this->max_patients;
    }

    /**
     * Check if this schedule slot is available
     */
    public function isAvailable(): bool
    {
        $isLocked = $this->lock_until && $this->lock_until->isFuture();

        return $this->is_available &&
            $this->booked_count < $this->max_patients &&
            !$isLocked;
    }

    /**
     * Mark slot as booked 
     */
    public function markAsBooked($userId = null): bool
    {
        return DB::transaction(function () use ($userId) {
            $fresh = self::lockForUpdate()->find($this->schedule_id);

            if (!$fresh || !$fresh->isAvailable()) {
                return false;
            }

            $fresh->increment('booked_count');

            if ($fresh->booked_count >= $fresh->max_patients) {
                $fresh->update(['is_available' => false]);
            }

            $fresh->update(['lock_until' => null, 'locked_by' => null]);
            return true;
        });
    }

    /**
     * Release a slot when an appointment is cancelled
     */
    public function releaseSlot(): bool
    {
        return DB::transaction(function () {
            $fresh = self::lockForUpdate()->find($this->schedule_id);

            if ($fresh->booked_count > 0) {
                $fresh->decrement('booked_count');
            }

            if ($fresh->booked_count < $fresh->max_patients) {
                $fresh->update([
                    'is_available' => true,
                    'lock_until' => null,
                    'locked_by' => null,
                ]);
            }

            return true;
        });
    }

    /**
     * Generate 3 time slots for a given date
     */
    public static function getOrCreateSchedulesForDate($date)
    {
        $existing = self::where('schedule_date', $date)->get();

        if ($existing->isEmpty()) {
            self::generateSchedulesForDate($date);
            $existing = self::where('schedule_date', $date)->get();
        }

        return $existing;
    }

    /**
     * Generate fixed time slots for date
     */
    public static function generateSchedulesForDate($date): void
    {
        foreach (self::getFixedTimeSlots() as $slot) {
            self::create([
                'schedule_date' => $date,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'is_available' => true,
                'max_patients' => 1,
                'booked_count' => 0,
            ]);
        }
    }

    /**
     * Relationships
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'schedule_id');
    }
}
