<?php

namespace App\Notifications;

use App\Models\Team;
use App\Models\Programmer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class TeamRunoffVotingStarted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $team;
    protected $candidates;
    protected $round;

    public function __construct(Team $team, $candidates, $round)
    {
        $this->team = $team;
        $this->candidates = $candidates;
        $this->round = $round;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $candidatesList = '';
        foreach ($this->candidates as $candidate) {
            $candidatesList .= "- {$candidate['name']} (@{$candidate['username']})\n";
        }

        $url = url('/teams/' . $this->team->id . '/voting');

        return (new MailMessage)
            ->subject(' Runoff Round ' . $this->round . ' for Team: ' . $this->team->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A tie has been detected! A runoff round (#' . $this->round . ') has started.')
            ->line('Please vote again, this time only between the following candidates:')
            ->line($candidatesList)
            ->action('Cast Your Vote in Runoff', $url)
            ->line('Your vote is important to break the tie!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'runoff_voting_started',
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'runoff_round' => $this->round,
            'candidates' => $this->candidates,
            'message' => 'Runoff round #' . $this->round . ' started for team: ' . $this->team->name,
            'candidates_count' => count($this->candidates),
            'voting_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'action_url' => '/teams/' . $this->team->id . '/voting',
            'action_text' => 'Vote in Runoff',
        ];
    }
}
