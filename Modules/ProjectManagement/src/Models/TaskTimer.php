<?php

namespace Modules\ProjectManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimer extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'started_by',
        'stopped_by',
        'started_at',
        'stopped_at',
        'duration_seconds',
        'note',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'stopped_at'       => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function stopper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by');
    }

    /**
     * Format seconds into human-readable duration, e.g. "2h 15m" or "45m".
     */
    public static function formatDuration(?int $seconds): string
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds === 0) {
            return '0m';
        }

        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);
        $s = (int) ($seconds % 60);

        $parts = [];
        if ($h > 0) {
            $parts[] = "{$h}h";
        }
        if ($m > 0) {
            $parts[] = "{$m}m";
        }

        return ! empty($parts) ? implode(' ', $parts) : "{$s}s";
    }

    /**
     * Structured array sent to the Timesheet frontend.
     */
    public function toTimerArray(?User $viewer = null): array
    {
        $viewerId = $viewer?->id ?? auth()->id();
        $isManager = $viewer ? $viewer->can('tasks.edit') : (auth()->check() && auth()->user()->can('tasks.edit'));
        $isOwner = $viewerId && ((int) $this->started_by === (int) $viewerId);

        $started = $this->started_at;
        $stopped = $this->stopped_at;

        return [
            'id'                 => $this->id,
            'running'            => $stopped === null,
            'started_by'         => $this->starter?->name ?? 'Deleted user',
            'started_by_id'      => $this->started_by,
            'started_at'         => $started?->toIso8601String(),
            'started_label'      => $started ? $started->format('M d, Y · g:i A') : '',
            'date_key'           => $started ? $started->format('Y-m-d') : '',
            'date_label'         => $started ? ($started->isToday() ? 'Today' : ($started->isYesterday() ? 'Yesterday' : $started->format('M d, Y'))) : '',
            'date_full'          => $started ? $started->format('D, M d, Y') : '',
            'day_number'         => $started ? $started->format('j') : '',
            'day_name'           => $started ? $started->format('D') : '',
            'month_name'         => $started ? $started->format('M') : '',
            'is_today'           => $started ? $started->isToday() : false,
            'time_range_label'   => $started
                ? ($stopped
                    ? $started->format('g:i A') . ' – ' . $stopped->format('g:i A')
                    : $started->format('g:i A') . ' – Active')
                : '',
            'stopped_by'         => $stopped ? ($this->stopper?->name ?? 'Deleted user') : null,
            'stopped_at'         => $stopped?->toIso8601String(),
            'stopped_label'      => $stopped ? $stopped->format('M d, Y · g:i A') : null,
            'duration_seconds'   => (int) ($this->duration_seconds ?? 0),
            'duration_formatted' => self::formatDuration($this->duration_seconds),
            'can_delete'         => (bool) ($isManager || $isOwner),
        ];
    }
}