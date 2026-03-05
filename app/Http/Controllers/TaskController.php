<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\Programmer;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\AssignTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get tasks for a specific team
     */
    public function getTeamTasks(Team $team, Request $request)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            if (!$team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            $query = $team->tasks()->with('programmer.user');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('programmer_id')) {
                $query->where('programmer_id', $request->programmer_id);
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $tasks = $query->orderBy('priority', 'desc')
                          ->orderBy('deadline')
                          ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'Tasks retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting team tasks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks'
            ], 500);
        }
    }

    /**
     * Create a new task
     */
    public function store(StoreTaskRequest $request, Team $team)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            if (!$team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only team members can create tasks'
                ], 403);
            }

            DB::beginTransaction();

            $task = $team->tasks()->create([
                'programmer_id' => $request->programmer_id ?? $programmer->id,
                'project_id' => $team->project_id,
                'title' => $request->title,
                'description' => $request->description,
                'status' => $request->status ?? 'todo',
                'estimated_hours' => $request->estimated_hours,
                'deadline' => $request->deadline,
                'priority' => $request->priority ?? 5,
                'complexity' => $request->complexity ?? 'medium',
            ]);

            if ($request->has('required_skills')) {
                $task->update(['required_skills' => $request->required_skills]);
            }

            Log::info('Task created', [
                'task_id' => $task->id,
                'team_id' => $team->id,
                'created_by' => $programmer->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $task->load('programmer.user'),
                'message' => 'Task created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task'
            ], 500);
        }
    }

    /**
     * Assign task to a programmer
     */
    public function assignTask(AssignTaskRequest $request, Task $task)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            if (!$task->team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only team members can assign tasks'
                ], 403);
            }

            if (!$task->team->isMember($request->programmer_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only assign tasks to team members'
                ], 400);
            }

            $oldProgrammerId = $task->programmer_id;

            $task->update([
                'programmer_id' => $request->programmer_id,
                'assigned_by' => $programmer->id,
                'assigned_at' => now(),
                'status' => 'todo',
            ]);

            Log::info('Task assigned', [
                'task_id' => $task->id,
                'old_programmer' => $oldProgrammerId,
                'new_programmer' => $request->programmer_id,
                'assigned_by' => $programmer->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task assigned successfully',
                'data' => $task->fresh()->load('programmer.user')
            ]);

        } catch (\Exception $e) {
            Log::error('Error assigning task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign task'
            ], 500);
        }
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, Task $task)
    {
        try {
            $request->validate([
                'status' => 'required|in:todo,in_progress,review,done,cancelled',
                'actual_hours' => 'nullable|integer|min:0|max:1000',
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            $user = $request->user();
            $programmer = $user->programmer;

            $canUpdate = $task->programmer_id === $programmer->id ||
                        $task->team->isLeader($programmer->id);

            if (!$canUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update your own tasks'
                ], 403);
            }

            $oldStatus = $task->status;
            $updates = ['status' => $request->status];

            if ($request->status === 'in_progress' && !$task->started_at) {
                $updates['started_at'] = now();
            }

            if ($request->status === 'done' && !$task->completed_at) {
                $updates['completed_at'] = now();
                $updates['progress_percentage'] = 100;

                if ($request->has('actual_hours')) {
                    $updates['actual_hours'] = $request->actual_hours;
                }

                $programmer->addScore(50, 'Task completed', [
                    'task_id' => $task->id,
                    'title' => $task->title
                ]);
            }

            if ($request->status === 'cancelled') {
                $updates['completed_at'] = now();
                $updates['progress_percentage'] = 0;
            }

            if ($request->has('completion_notes')) {
                $updates['completion_notes'] = $request->completion_notes;
            }

            $task->update($updates);

            DB::table('task_history')->insert([
                'task_id' => $task->id,
                'changed_by' => $programmer->id,
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => $request->status]),
                'change_type' => 'status_update',
                'change_description' => "Status changed from {$oldStatus} to {$request->status}",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'data' => [
                    'old_status' => $oldStatus,
                    'new_status' => $task->status,
                    'task' => $task->fresh()->load('programmer.user')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating task status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task status'
            ], 500);
        }
    }

    /**
     * Get team task statistics
     */
    public function getTeamTaskStats(Team $team)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (!$team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            $stats = [
                'total_tasks' => $team->tasks()->count(),
                'tasks_by_status' => $team->tasks()
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
                'tasks_by_priority' => $team->tasks()
                    ->select('priority', DB::raw('count(*) as count'))
                    ->groupBy('priority')
                    ->orderBy('priority', 'desc')
                    ->get(),
                'tasks_by_programmer' => $team->tasks()
                    ->join('programmers', 'tasks.programmer_id', '=', 'programmers.id')
                    ->join('users', 'programmers.user_id', '=', 'users.id')
                    ->select('users.name', 'programmers.id', DB::raw('count(*) as task_count'))
                    ->groupBy('programmers.id', 'users.name')
                    ->get(),
                'total_estimated_hours' => $team->tasks()->sum('estimated_hours'),
                'total_actual_hours' => $team->tasks()->sum('actual_hours'),
                'completion_rate' => $this->calculateCompletionRate($team),
                'average_completion_time' => $this->calculateAverageCompletionTime($team),
                'overdue_tasks' => $team->tasks()
                    ->where('deadline', '<', now())
                    ->where('status', '!=', 'done')
                    ->count(),
                'upcoming_deadlines' => $team->tasks()
                    ->where('deadline', '>', now())
                    ->where('deadline', '<=', now()->addDays(7))
                    ->where('status', '!=', 'done')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Task statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting team task stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task statistics'
            ], 500);
        }
    }

    /**
     * Calculate completion rate for a team
     */
    private function calculateCompletionRate(Team $team): float
    {
        $totalTasks = $team->tasks()->count();
        if ($totalTasks === 0) return 0;

        $completedTasks = $team->tasks()->where('status', 'done')->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Calculate average completion time for a team
     */
    private function calculateAverageCompletionTime(Team $team): float
    {
        $completedTasks = $team->tasks()
            ->where('status', 'done')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedTasks->isEmpty()) return 0;

        $totalDays = 0;
        foreach ($completedTasks as $task) {
            $days = $task->started_at->diffInDays($task->completed_at);
            $totalDays += $days;
        }

        return round($totalDays / $completedTasks->count(), 2);
    }

    /**
     * Update task details
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            $canUpdate = $task->programmer_id === $programmer->id ||
                        $task->team->isLeader($programmer->id);

            if (!$canUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update this task'
                ], 403);
            }

            DB::beginTransaction();

            $oldValues = $task->toArray();
            $task->update($request->validated());

            $changes = [];
            foreach ($request->validated() as $key => $value) {
                if (isset($oldValues[$key]) && $oldValues[$key] != $value) {
                    $changes[$key] = [
                        'old' => $oldValues[$key],
                        'new' => $value
                    ];
                }
            }

            if (!empty($changes)) {
                DB::table('task_history')->insert([
                    'task_id' => $task->id,
                    'changed_by' => $programmer->id,
                    'old_values' => json_encode($changes),
                    'change_type' => 'task_update',
                    'change_description' => 'Task details updated',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $task->fresh()->load('programmer.user'),
                'message' => 'Task updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task'
            ], 500);
        }
    }

    /**
     * Delete a task
     */
    public function destroy(Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (!$task->team->isLeader($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only team leader can delete tasks'
                ], 403);
            }

            DB::beginTransaction();

            DB::table('task_history')->insert([
                'task_id' => $task->id,
                'changed_by' => $programmer->id,
                'change_type' => 'task_deleted',
                'change_description' => "Task '{$task->title}' deleted",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $task->delete();

            Log::info('Task deleted', [
                'task_id' => $task->id,
                'team_id' => $task->team_id,
                'deleted_by' => $programmer->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task'
            ], 500);
        }
    }

    /**
     * Get task history
     */
    public function getTaskHistory(Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (!$task->team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            $history = DB::table('task_history')
                ->where('task_id', $task->id)
                ->join('programmers', 'task_history.changed_by', '=', 'programmers.id')
                ->join('users', 'programmers.user_id', '=', 'users.id')
                ->select(
                    'task_history.*',
                    'users.name as changed_by_name',
                    'users.user_name as changed_by_username'
                )
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $task->load('programmer.user'),
                    'history' => $history
                ],
                'message' => 'Task history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting task history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task history'
            ], 500);
        }
    }

    public function getMyTasks(Request $request)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            $query = Task::where('programmer_id', $programmer->id)
                ->with(['team.project', 'team.leader.programmer.user']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('team_id')) {
                $query->where('team_id', $request->team_id);
            }

            $tasks = $query->orderBy('priority', 'desc')
                          ->orderBy('deadline')
                          ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'My tasks retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting my tasks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your tasks'
            ], 500);
        }
    }
}
