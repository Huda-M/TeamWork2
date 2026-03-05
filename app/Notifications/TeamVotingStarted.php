<?php

namespace App\Notifications;

use App\Models\Team;
use App\Models\Programmer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class TeamVotingStarted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $team;
    protected $startedBy;

    public function __construct(Team $team, Programmer $startedBy)
    {
        $this->team = $team;
        $this->startedBy = $startedBy;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $url = url('/teams/' . $this->team->id . '/voting');

        return (new MailMessage)
            ->subject(' Voting Started for Team: ' . $this->team->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Voting has started for your team **' . $this->team->name . '**')
            ->line('It\'s time to choose your team leader!')
            ->line('Team Members: ' . $this->team->activeMembers()->count())
            ->line('Voting Deadline: ' . now()->addDays(2)->format('Y-m-d H:i'))
            ->action('Cast Your Vote', $url)
            ->line('Please cast your vote as soon as possible.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'voting_started',
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'started_by' => [
                'id' => $this->startedBy->id,
                'name' => $this->startedBy->user->name,
                'username' => $this->startedBy->user->user_name,
            ],
            'message' => 'Voting has started for team: ' . $this->team->name,
            'members_count' => $this->team->activeMembers()->count(),
            'voting_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'action_url' => '/teams/' . $this->team->id . '/voting',
            'action_text' => 'Vote Now',
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'started_by' => $this->startedBy->user->name,
            'message' => 'Voting has started for team: ' . $this->team->name,
        ];
    }
}
