<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SwapLeaderNotification extends Notification
{
    use Queueable;

    public $newleader;
    
    public function __construct($user)
    {
        $this->newleader = $user;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Swap Leader',
            'message' => 'The new leader of your team is '.$this->newleader->full_name,
        ];
    }
}
