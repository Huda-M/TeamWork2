<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskCreatedNotification extends Notification
{
    use Queueable;


    private $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }
   
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_created',
            'message' => 'You have been assigned a new task',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
        ];
    }
}
