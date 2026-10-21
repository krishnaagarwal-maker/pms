<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ProjectManagement\Models\Task;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assignedBy,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->task->project;

        return [
            'title' => 'Task assigned',
            'message' => 'Task assigned: ' . $this->task->title,
            'project_name' => $project?->name ?? 'N/A',
            'assigned_by' => $this->assignedBy->name,
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'link' => $project ? url('/projects/' . $project->id . '/tasks/' . $this->task->id) : null,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->task->project;

        return (new MailMessage)
            ->subject('You have been assigned a task')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have been assigned a new task by ' . $this->assignedBy->name . '.')
            ->line('Task: ' . $this->task->title)
            ->line('Project: ' . ($project?->name ?? 'N/A'))
            ->line('Due date: ' . ($this->task->due_date ? $this->task->due_date->format('M d, Y') : 'Not set'))
            ->line('Priority: ' . ucfirst($this->task->priority))
            ->action('View task', url('/projects/' . $project?->id . '/tasks/' . $this->task->id));
    }
}
