<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Team;
use App\Services\FCM\PushNotify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="TeamWork API",
 *     description="API Documentation"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
class TaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tasks/my",
     *     tags={"Tasks"},
     *     summary="Get my tasks",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="team_id",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/tasks/completed",
     *     tags={"Tasks"},
     *     summary="Get completed tasks",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/tasks/in-progress",
     *     tags={"Tasks"},
     *     summary="Get in-progress tasks",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     )
     * )
     */
    public function inProgressTasks(Request $request)
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

            $query = Task::where('programmer_id', $programmer->id)
                ->whereIn('status', ['in_progress', 'review'])
                ->with(['team.project'])
                ->orderBy('deadline', 'asc');

            $tasks = $query->paginate(20);

            $result = $tasks->map(function ($task) {
                $createdAt = $task->created_at;
                $deadline = $task->deadline;
                $totalDays = $createdAt->diffInDays($deadline);
                $passedDays = $createdAt->diffInDays(now());
                $percentageTimePassed = ($totalDays > 0) ? round(($passedDays / $totalDays) * 100) : 0;
                if ($percentageTimePassed > 100) {
                    $percentageTimePassed = 100;
                }

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
                    'in_progress_tasks' => $result,
                    'total' => $tasks->total(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                ],
                'message' => 'In-progress tasks fetched successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching in-progress tasks: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to fetch in-progress tasks'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/teams/{team}/tasks",
     *     tags={"Tasks"},
     *     summary="Get team tasks",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function getTeamTasks(Team $team, Request $request)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            if (! $team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team',
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
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%'.$request->search.'%')
                        ->orWhere('description', 'like', '%'.$request->description.'%');
                });
            }

            $tasks = $query->orderBy('priority', 'desc')
                ->orderBy('deadline')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'Tasks retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting team tasks: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{task}",
     *     tags={"Tasks"},
     *     summary="Get single task",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="task",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function show(Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (! $task->team->isMember($programmer->id) && $user->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $task->load([
                'programmer.user',      // المبرمج المسند إليه المهمة
                'creator.user',         // منشئ المهمة (created_by)
                'team.project',         // الفريق والمشروع
                'attachments',           // المرفقات
            ]);

            // تحويل priority إلى نص (إذا كان العمود لا يزال integer)
            $priorityMap = [1 => 'low', 2 => 'medium', 3 => 'high'];
            $priorityName = $priorityMap[$task->priority] ?? 'medium';

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $priorityName,  // low, medium, high
                    'deadline' => $task->deadline?->toDateString(),
                    'project_name' => $task->team->project->title ?? null,
                    'created_by' => [
                        'id' => $task->creator?->id,
                        'name' => $task->creator?->user?->full_name,
                        'avatar_url' => $task->creator?->avatar_url,
                    ],
                    'assigned_to' => [
                        'id' => $task->programmer?->id,
                        'name' => $task->programmer?->user?->full_name,
                        'avatar_url' => $task->programmer?->avatar_url,
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
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'todo',
                'estimated_hours' => 0,  // 👈 أضف هذا السطر (قيمة افتراضية)
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

            $task->load('programmer.user');
            $assignedUser = $task->programmer?->user;
            if ($assignedUser && $assignedUser->fcm_token) {
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

    /**
     * تحديث حالة المهمة إلى "done" (مكتملة)
     */
    /**
     * وضع علامة "مكتمل" على المهمة
     */
    public function markAsDone(Request $request, Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            // فقط المبرمج المسند إليه المهمة أو قائد الفريق يمكنه إنهاء المهمة
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
            if ($assigner && $assigner->user && $assigner->user->fcm_token) {
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

    /**
     * رفع مرفق لمهمة معينة
     */
    public function uploadAttachment(Request $request, Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            // التحقق من أن المستخدم عضو في الفريق
            if (! $task->team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team',
                ], 403);
            }

            $request->validate([
                'attachment' => 'required|file|max:10240', // max 10MB
            ]);

            $file = $request->file('attachment');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = $file->getMimeType();

            // تخزين الملف
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

    /**
     * تحديث مهمة موجودة
     * - القائد/الأدمن: كل الحقول
     * - المبرمج المُسند إليه: الحالة (status) فقط
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            // التحقق من الصلاحية الأساسية: قائد الفريق أو أدمن أو المبرمج المسند إليه
            $isLeader = $task->team->isLeader($programmer->id);
            $isAssigned = ($task->programmer_id === $programmer->id);

            if (! $isLeader && ! $isAssigned && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only team leader, assigned programmer, or admin can update this task',
                ], 403);
            }

            $validated = $request->validated();

            // حالة خاصة: إذا كان المبرمج المسند إليه (وليس قائداً ولا أدمن)
            if ($isAssigned && ! $isLeader && $user->role !== 'admin') {
                // يسمح له فقط بتعديل حقل 'status'
                $allowedFields = ['status'];
                $data = array_intersect_key($validated, array_flip($allowedFields));
                if (empty($data) && $request->has('status') === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assigned programmer can only update the task status',
                    ], 403);
                }
                $task->update($data);
            } else {
                // القائد أو الأدمن: يمكنه تعديل كل الحقول (بما فيها إعادة التعيين programmer_id)
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
            if ($assigner && $assigner->user && $assigner->user->fcm_token) {
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
}
