<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendInvitationNotification extends Notification
{
    use Queueable;

    protected $invitation;
    protected $team;

    /**
     * Create a new notification instance.
     */
    public function __construct(TeamInvitation $invitation)
    {
        $this->invitation = $invitation;
        $this->team = $invitation->team;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Join Team ' . $this->team->name . ' on TeamWork Platform')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been invited to join the team "' . $this->team->name . '".')
            ->line('The team is working on the project: ' . $this->team->project->title)
            ->action('View Invitation', url('/api/invitations/' . $this->invitation->id))
            ->line('Please log in to TeamWork Platform to accept the invitation.');
    }
}
