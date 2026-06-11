<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\TeamInvitation;
use App\Models\User;

class InvitationDeclinedNotification extends Notification
{
    use Queueable;
    private TeamInvitation $invitation;
    private User $user;
    public function __construct(TeamInvitation $invitation,User $user)
    {
        $this->invitation = $invitation;
        $this->user = $user;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invitation_declined',
            'message' => "{$this->user->full_name} declined your invitation to join your team.",
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
        ];
    }
}
