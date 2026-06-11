<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
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
            'type' => 'task_completed',
            'message' => 'The task has been completed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
        ];
    }
}
