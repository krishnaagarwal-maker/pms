<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?string $link = null,
        public string $type = 'system',
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'link' => $this->link,
        ];
    }
}
