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
    $url = url('/api/invitations/' . $this->invitation->id);
    
    // استخدام قالب مخصص مع تمرير المتغيرات
    return (new MailMessage)
        ->subject('Join Team ' . $this->team->name . ' on TeamWork Platform')
        ->markdown('emails.invitation', [
            'notifiable' => $notifiable,
            'team' => $this->team,
            'url' => $url,
        ]);
}
}
