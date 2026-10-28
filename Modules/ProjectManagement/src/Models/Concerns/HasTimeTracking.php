<?php

namespace Modules\ProjectManagement\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ProjectManagement\Models\TaskTimer;

trait HasTimeTracking
{
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimer::class);
    }

    public function timers(): HasMany
    {
        return $this->hasMany(TaskTimer::class);
    }

    /**
     * Get total time spent on the task in seconds (completed sessions + running session if any).
     */
    public function getTotalTimeAttribute(): int
    {
        $total = (int) $this->timeEntries()->whereNotNull('stopped_at')->sum('duration_seconds');
        $running = $this->timeEntries()->whereNull('stopped_at')->first();
        if ($running && $running->started_at) {
            $total += max(0, now()->getTimestamp() - $running->started_at->getTimestamp());
        }

        return $total;
    }

    /**
     * Returns structured state for the Time Tracker & Timesheet card and API responses.
     */

    public function timerState(): array
    {
        $running = $this->timeEntries()->whereNull('stopped_at')->with('starter:id,name')->first();

        $completedTotal = (int) $this->timeEntries()->whereNotNull('stopped_at')->sum('duration_seconds');
        $total = $completedTotal;
        if ($running && $running->started_at) {
            $total += max(0, now()->getTimestamp() - $running->started_at->getTimestamp());
        }

        $allEntries = $this->timeEntries()
            ->with(['starter:id,name', 'stopper:id,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        // Calculate breakdown by user
        $byUser = [];
        foreach ($allEntries as $entry) {
            if ($entry->stopped_at !== null) {
                $uid = $entry->started_by ?: 0;
                $uname = $entry->starter?->name ?? 'Unassigned';
                if (!isset($byUser[$uid])) {
                    $byUser[$uid] = [
                        'user_id'    => $uid,
                        'name'       => $uname,
                        'seconds'    => 0,
                        'formatted'  => '0m',
                        'percentage' => 0,
                    ];
                }
                
                $byUser[$uid]['seconds'] += (int) $entry->duration_seconds;
            }
        }

        foreach ($byUser as $uid => $data) {
            $byUser[$uid]['formatted'] = TaskTimer::formatDuration($data['seconds']);
            $byUser[$uid]['percentage'] = $total > 0 ? (int) round(($data['seconds'] / $total) * 100) : 0;
        }

        usort($byUser, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        $entries = $allEntries->take(100)->map(fn (TaskTimer $e) => $e->toTimerArray())->values()->all();

        return [
            'running'         => (bool) $running,
            'started_at'      => $running?->started_at?->toIso8601String(),
            'started_by'      => $running ? ($running->starter?->name ?? 'Deleted user') : null,
            'started_by_id'   => $running?->started_by,
            'active_note'     => $running?->note ?? '',
            'total_seconds'   => $total,
            'total_formatted' => TaskTimer::formatDuration($total),
            'by_user'         => array_values($byUser),
            'entries'         => $entries,
            'server_now'      => (int) round(microtime(true) * 1000),

        ];
    }
}