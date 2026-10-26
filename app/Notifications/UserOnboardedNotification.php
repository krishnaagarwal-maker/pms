<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserOnboardedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public User $onboardedBy,
        public string $roleName,
        public ?string $temporaryPassword = null
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

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to the team',
            'message' => 'Your account has been onboarded by ' . $this->onboardedBy->name,
            'role' => $this->roleName,
            'onboarded_by' => $this->onboardedBy->name,
            'user_id' => $this->user->id,
            'link' => route('dashboard'),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to the Team - Account Details')
            ->view('emails.user-onboarded', [
                'user' => $this->user,
                'onboardedBy' => $this->onboardedBy,
                'roleName' => $this->roleName,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('login'),
            ]);
    }
}
