<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Models\Programmer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendInvitationNotification extends Notification
{
    use Queueable;

    protected $invitation;
    protected $team;
    protected $inviterName;
    protected $projectDescription;

    public function __construct(TeamInvitation $invitation)
    {
        $this->invitation = $invitation;
        $this->team = $invitation->team;

        // جلب اسم المرسل (اللي بعت الدعوة)
        $inviter = Programmer::with('user')->find($invitation->invited_by);
        $this->inviterName = $inviter?->user?->full_name ?? 'A team member';

        // جلب وصف المشروع
        $this->projectDescription = $this->team->project->description ?? '';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/api/invitations/' . $this->invitation->id);

        return (new MailMessage)
            ->subject('Join Team ' . $this->team->name . ' on bridgeX')
            ->markdown('emails.invitation', [
                'notifiable'         => $notifiable,
                'team'               => $this->team,
                'inviterName'        => $this->inviterName,
                'projectDescription' => $this->projectDescription,
                'url'                => $url,
            ]);
    }


    public function toDatabase(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'message' => 'You have been invited to join team '.$this->team->name .', check your email for more information.',
            'action_url' => '/api/my/invitations',
        ];
    }
}
