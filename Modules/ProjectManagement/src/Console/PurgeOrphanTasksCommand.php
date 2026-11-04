<?php

namespace Modules\ProjectManagement\Console;

use Illuminate\Console\Command;
use Modules\ProjectManagement\Models\Task;

class PurgeOrphanTasksCommand extends Command
{
    protected $signature = 'tasks:purge-orphans';

    protected $description = 'Purge tasks belonging to deleted or non-existent projects from the database';

    public function handle(): int
    {
        $orphans = Task::withTrashed()
            ->whereDoesntHave('project')
            ->get();

        $count = $orphans->count();

        $orphans->each(function (Task $task) {
            $task->attachments()->delete();
            $task->comments()->delete();
            if (method_exists($task, 'timers')) {
                $task->timers()->delete();
            }
            $task->forceDelete();
        });

        $this->info("Purged {$count} orphan tasks from the database.");

        return self::SUCCESS;
    }
}

