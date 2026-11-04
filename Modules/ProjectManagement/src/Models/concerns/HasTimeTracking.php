<?php

namespace Modules\ProjectManagement\Models\concerns;

use Modules\ProjectManagement\Models\TaskTimer;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Add to the Task model:  use \App\Models\Concerns\HasTimeTracking;
 */
trait HasTimeTracking
{
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimer::class);
    }

    /** Everything the Time Tracker card needs, in one place (used by the view and the controller). */
    public function timerState(): array
    {
        $running = $this->timeEntries()->whereNull('stopped_at')->with('starter:id,name')->first();

        $total = (int) $this->timeEntries()->whereNotNull('stopped_at')->sum('duration_seconds');

        $entries = $this->timeEntries()
            ->with(['starter:id,name', 'stopper:id,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (TaskTimer $e) => $e->toTimerArray())
            ->all();    

        return [
            'running'       => (bool) $running,
            'started_at'    => $running?->started_at->toIso8601String(),
            'started_by'    => $running ? ($running->starter?->name ?? 'Deleted user') : null,
            'total_seconds' => $total,
            'entries'       => $entries,
            // lets the browser correct for a wrong device clock
            'server_now'    => (int) round(microtime(true) * 1000),
        ];
    }
}