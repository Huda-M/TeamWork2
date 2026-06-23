<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Team;
use App\Models\Project;
use App\Notifications\TaskCompletedNotification;
use App\Notifications\TaskCreatedNotification;
use App\Notifications\TaskUpdatedNotification;
use App\Services\FCM\PushNotify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Annotations as OA;


class TaskController extends Controller
{
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
                'message' => 'My tasks retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting my tasks: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your tasks',
            ], 500);
        }
    }

    public function completedTasks(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user || $user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            }

            $programmer = $user->programmer;
            if (! $programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            $tasksThisWeek = Task::where('programmer_id', $programmer->id)
                ->where('status', 'done')
                ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            $query = Task::where('programmer_id', $programmer->id)
                ->where('status', 'done')
                ->with(['team.project'])
                ->orderBy('completed_at', 'desc');

            if ($request->has('from_date')) {
                $query->whereDate('completed_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('completed_at', '<=', $request->to_date);
            }

            $tasks = $query->paginate(20);

            $result = $tasks->map(function ($task) {
                return [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'completion_date' => $task->completed_at ? $task->completed_at->toDateString() : $task->updated_at->toDateString(),
                    'project_name' => $task->team->project->title ?? null,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'num_of_tasks_done' => $tasks->total(),
                    'num_of_tasks_done_this_week' => $tasksThisWeek,
                    'completed_tasks' => $result,
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                ],
                'message' => 'Completed tasks fetched successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching completed tasks: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to fetch completed tasks'], 500);
        }
    }

    public function inProgressTasks(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
        }

        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }

        $query = Task::where('programmer_id', $programmer->id)
            ->where('status', 'active') 
            ->with(['team.project'])
            ->orderBy('deadline', 'asc');

        $tasks = $query->paginate(20);

        $result = $tasks->map(function ($task) {
            $createdAt = $task->created_at;
            $deadline = $task->deadline;
            $totalDays = $createdAt->diffInDays($deadline);
            $passedDays = $createdAt->diffInDays(now());
            $percentageTimePassed = ($totalDays > 0) ? round(($passedDays / $totalDays) * 100) : 0;
            if ($percentageTimePassed > 100) $percentageTimePassed = 100;

            return [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'project_name' => $task->team->project->title ?? null,
                'due_date' => $task->deadline->toDateString(),
                'priority' => $task->priority,
                'status' => $task->status,
                'days_remaining' => now()->diffInDays($task->deadline, false),
                'is_overdue' => $task->deadline->isPast(),
                'percentage_time_passed' => $percentageTimePassed,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'active_tasks' => $result, 
                'total' => $tasks->total(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
            ],
            'message' => 'Active tasks fetched successfully', 
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching active tasks: ' . $e->getMessage());

        return response()->json(['success' => false, 'message' => 'Failed to fetch active tasks'], 500);
    }
}
public function show(Task $task)
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        if (!$task->team) {
            return response()->json(['success' => false, 'message' => 'Task has no associated team'], 404);
        }

        if (! $task->team->isMember($programmer->id) && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // ✅ CHANGED: Load creator relation with user
        $task->load([
            'programmer.user',      
            'creator.user',         
            'team.project',         
            'attachments',           
        ]);

        // ✅ NEW: Debug - check if creator exists
        $creator = $task->creator;
        if (!$creator) {
            Log::warning('Task creator not found', [
                'task_id' => $task->id,
                'created_by' => $task->created_by ?? 'null',
                'programmer_id' => $task->programmer_id,
            ]);
        }

        $priorityMap = [1 => 'low', 2 => 'medium', 3 => 'high'];
        $priorityName = $priorityMap[$task->priority] ?? 'medium';

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $priorityName,  
                'deadline' => $task->deadline?->toDateString(),
                'project_name' => $task->team->project->title ?? null,
                'created_by' => [
                    'id' => $task->creator?->id,
                    'name' => $task->creator?->user?->full_name,
                    'avatar_url' => $task->creator?->avatar_url 
                        ? Storage::disk('public')->url($task->creator->avatar_url) 
                        : null,
                ],
                'assigned_to' => [
                    'id' => $task->programmer?->id,
                    'name' => $task->programmer?->user?->full_name,
                    'avatar_url' => $task->programmer?->avatar_url 
                        ? Storage::disk('public')->url($task->programmer->avatar_url) 
                        : null,
                ],
                'attachments' => $task->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_size' => $attachment->file_size,
                        'uploaded_by' => $attachment->uploadedBy?->user?->full_name,
                        'uploaded_at' => $attachment->created_at,
                    ];
                }),
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Error showing task: '.$e->getMessage());

        return response()->json(['success' => false, 'message' => 'Failed to fetch task'], 500);
    }
}
    public function store(StoreTaskRequest $request, Team $team)
{
    try {
        $user = $request->user();
        $programmer = $user->programmer;

        if (! $team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader can create tasks',
            ], 403);
        }

        // ✅ NEW: منع إنشاء task لو المشروع completed
        if ($team->project && $team->project->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create tasks for a completed project',
            ], 403);
        }

        if ($request->has('programmer_id') && ! $team->isMember($request->programmer_id)) {
            return response()->json([
                'success' => false,
                'message' => 'The assigned programmer is not a member of this team',
            ], 400);
        }

        DB::beginTransaction();

        $validated = $request->validated();

        $task = $team->tasks()->create([
            'programmer_id' => $validated['programmer_id'] ?? $programmer->id,
             'created_by' => $programmer->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'todo',
            'estimated_hours' => $validated['estimated_hours'] ?? 72,  // ✅ CHANGED: من 0 لـ 72
            'deadline' => $validated['deadline'] ?? null,
            'priority' => $validated['priority'] ?? 5,
            'git_link' => $validated['git_link'] ?? null,
            'tags' => $validated['tags'] ?? null,
        ]);

            Log::info('Task created', [
                'task_id' => $task->id,
                'team_id' => $team->id,
                'created_by' => $programmer->id,
            ]);

            DB::commit();

            $task->load(['programmer.user', 'team']);
            $assignedUser = $task->programmer?->user;
            if ($assignedUser) {

                if ($assignedUser->fcm_token) {
                    try {
                        $pushNotify = new PushNotify;
                        $pushNotify->sendPushNotification(
                            $assignedUser->fcm_token,
                            'New Task Assigned',
                            "You have been assigned a new task: {$task->title}",
                            [
                                'task_id' => (string) $task->id,
                                'type' => 'new_task_assigned',
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::error('FCM notification failed: '.$e->getMessage());
                    }
                }

                $assignedUser->notify(new TaskCreatedNotification($task));
            }

            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => 'Task created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating task: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create task: '.$e->getMessage(),
            ], 500);
        }
    }

    public function markAsDone(Request $request, Task $task)
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        if ($task->programmer_id !== $programmer->id && ! $task->team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned programmer or team leader can mark task as done',
            ], 403);
        }

        if ($task->status === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Task is already completed',
            ], 400);
        }

        if ($task->deadline && $task->deadline->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark task as completed. Deadline has passed.',
            ], 400);
        }

        DB::beginTransaction();

        $task->update([
            'status' => 'done',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

            Log::info('Task marked as done', [
                'task_id' => $task->id,
                'marked_by' => $programmer->id,
            ]);

            $assigner = $task->assignedBy;
            if ($assigner && $assigner->user) {
                if ($assigner->user->fcm_token) {
                    $pushNotify = new PushNotify;
                    $pushNotify->sendPushNotification(
                        $assigner->user->fcm_token,
                        'Task Completed',
                        "Task '{$task->title}' you assigned has been completed.",
                        [
                            'task_id' => (string) $task->id,
                            'type' => 'task_completed',
                        ]
                    );
                }
                $assigner->user->notify(new TaskCompletedNotification($task));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task marked as completed successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking task as done: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark task as done',
            ], 500);
        }
    }

    public function uploadAttachment(Request $request, Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (! $task->team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team',
                ], 403);
            }

            $request->validate([
                'attachment' => 'required|file|max:10240', 
            ]);

            $file = $request->file('attachment');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = $file->getMimeType();

            $path = $file->store('task_attachments/'.$task->id, 'public');

            $attachment = $task->attachments()->create([
                'file_name' => $fileName,
                'file_path' => $path,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'uploaded_by' => $programmer->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_path' => Storage::url($attachment->file_path),
                    'file_size' => $attachment->file_size,
                    'uploaded_at' => $attachment->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error uploading attachment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
            ], 500);
        }
    }

    public function update(UpdateTaskRequest $request, Task $task)
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        $isLeader = $task->team->isLeader($programmer->id);
        $isAssigned = ($task->programmer_id === $programmer->id);

        if (! $isLeader && ! $isAssigned && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader, assigned programmer, or admin can update this task',
            ], 403);
        }

        if ($task->team->project && $task->team->project->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update tasks in a completed project',
            ], 403);
        }

        $validated = $request->validated();

        if ($isAssigned && ! $isLeader && $user->role !== 'admin') {
            $allowedFields = ['status'];
            $data = array_intersect_key($validated, array_flip($allowedFields));
            
            if (empty($data) && $request->has('status') === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned programmer can only update the task status',
                ], 403);
            }

            if (isset($data['status']) && $data['status'] === 'done') {
                if ($task->deadline && $task->deadline->isPast()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot mark task as completed. Deadline has passed.',
                    ], 400);
                }
            }

            $task->update($data);
        } else {
            if (isset($validated['status']) && $validated['status'] === 'done') {
                if ($task->deadline && $task->deadline->isPast()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot mark task as completed. Deadline has passed.',
                    ], 400);
                }
            }

            if ($request->has('programmer_id') && ! $task->team->isMember($request->programmer_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The new assigned programmer is not a member of this team',
                ], 400);
            }
            $task->update($validated);
        }

            Log::info('Task updated', [
                'task_id' => $task->id,
                'updated_by' => $programmer->id,
                'updated_fields' => array_keys($validated),
            ]);

            $assigner = $task->assignedBy;
            if ($assigner && $assigner->user) {
                if ($assigner->user->fcm_token) {
                    $pushNotify = new PushNotify;
                    $pushNotify->sendPushNotification(
                        $assigner->user->fcm_token,
                        'Task Updated',
                        "Task '{$task->title}' you assigned has been Updated.",
                        [
                            'task_id' => (string) $task->id,
                            'type' => 'task_updated',
                        ]
                    );
                }
                $assigner->user->notify(new TaskUpdatedNotification($task));
            }

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task->fresh(['programmer.user', 'creator.user', 'team.project']),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating task: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update task',
            ], 500);
        }
    }
public function getProjectTasks(Request $request, $projectId)
{
    try {
        $user = Auth::user();
        $programmer = $user->programmer;

        $userTeam = Team::where('project_id', $projectId)
            ->whereHas('members', function ($q) use ($programmer) {
                $q->where('programmer_id', $programmer->id);
            })
            ->first();

        if (!$userTeam) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project'
            ], 403);
        }

        $projectTeamIds = Team::where('project_id', $projectId)
            ->pluck('id');

        $query = Task::whereIn('team_id', $projectTeamIds)
            ->with(['programmer.user', 'team', 'creator.user']);

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
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->has('my_tasks') && $request->boolean('my_tasks')) {
            $query->where('programmer_id', $programmer->id);
        }

        $tasks = $query->orderBy('priority', 'desc')
            ->orderBy('deadline')
            ->paginate(20);

        $result = $tasks->map(function ($task) {
            $priorityMap = [1 => 'low', 2 => 'medium', 3 => 'high'];
            $priorityName = $priorityMap[$task->priority] ?? 'medium';

            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $priorityName,
                'priority_value' => $task->priority,
                'deadline' => $task->deadline?->toDateString(),
                'created_at' => $task->created_at?->toDateString(),
                
                'team' => [
                    'id' => $task->team?->id,
                    'name' => $task->team?->name,
                ],
                
                'assigned_to' => [
                    'id' => $task->programmer?->id,
                    'name' => $task->programmer?->user?->full_name,
                    'avatar_url' => $task->programmer?->avatar_url ?: null,
                ],
                
                'created_by' => [
                    'id' => $task->creator?->id,
                    'name' => $task->creator?->user?->full_name,
                ],
                
                'is_overdue' => $task->deadline?->isPast() ?? false,
                'days_remaining' => $task->deadline ? now()->diffInDays($task->deadline, false) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $result,
                'total' => $tasks->total(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
            ],
            'meta' => [
                'project_id' => $projectId,
                'user_team_id' => $userTeam->id,
                'available_teams' => $projectTeamIds,
                'filters_applied' => [
                    'status' => $request->status,
                    'programmer_id' => $request->programmer_id,
                    'priority' => $request->priority,
                    'search' => $request->search,
                    'team_id' => $request->team_id,
                    'my_tasks' => $request->boolean('my_tasks'),
                ],
            ],
            'message' => 'Project tasks retrieved successfully',
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching project tasks: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch project tasks',
        ], 500);
    }
}
   
public function storeProjectTask(StoreTaskRequest $request, $projectId)
{
    try {
        $user = $request->user();
        $programmer = $user->programmer;

        $project = Project::with('teams')->findOrFail($projectId);
        
        $team = $project->teams->first(function ($t) use ($programmer) {
            return $t->isMember($programmer->id);
        });

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project',
            ], 403);
        }

        if (!$team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader can create tasks',
            ], 403);
        }

        if ($request->has('programmer_id') && !$team->isMember($request->programmer_id)) {
            return response()->json([
                'success' => false,
                'message' => 'The assigned programmer is not a member of this team',
            ], 400);
        }

        DB::beginTransaction();

        $validated = $request->validated();

        $task = $team->tasks()->create([
            'programmer_id' => $validated['programmer_id'] ?? $programmer->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'todo',
            'estimated_hours' => 0,
            'deadline' => $validated['deadline'] ?? null,
            'priority' => $validated['priority'] ?? 5,
            'git_link' => $validated['git_link'] ?? null,
            'tags' => $validated['tags'] ?? null,
        ]);

        Log::info('Task created', [
            'task_id' => $task->id,
            'project_id' => $projectId,
            'team_id' => $team->id,
            'created_by' => $programmer->id,
        ]);

        DB::commit();

        $task->load(['programmer.user', 'team']);
        $assignedUser = $task->programmer?->user;
        
        if ($assignedUser) {
            if ($assignedUser->fcm_token) {
                try {
                    $pushNotify = new PushNotify;
                    $pushNotify->sendPushNotification(
                        $assignedUser->fcm_token,
                        'New Task Assigned',
                        "You have been assigned a new task: {$task->title}",
                        [
                            'task_id' => (string) $task->id,
                            'project_id' => (string) $projectId,
                            'type' => 'new_task_assigned',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('FCM notification failed: ' . $e->getMessage());
                }
            }

            $assignedUser->notify(new TaskCreatedNotification($task));
        }

        return response()->json([
            'success' => true,
            'data' => $task,
            'message' => 'Task created successfully',
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error creating task: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to create task: ' . $e->getMessage(),
        ], 500);
    }
}
}
