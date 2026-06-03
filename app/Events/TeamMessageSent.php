<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class TeamMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->load('user:id,full_name');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('team-chat.' . $this->message->room->team_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'team_id' => $this->message->room->team_id,
            'team_name'=> $this->message->room->team->name,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->full_name,
            ],
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}