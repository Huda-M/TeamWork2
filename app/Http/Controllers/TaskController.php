<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\Programmer;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\AssignTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

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
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="team_id",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
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

    /**
 * @OA\Get(
 *     path="/api/tasks/completed",
 *     tags={"Tasks"},
 *     summary="Get completed tasks",
 *     security={{"bearerAuth":{}}},
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
            if (!$user || $user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            }

            $programmer = $user->programmer;
            if (!$programmer) {
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

            $result = $tasks->map(function($task) {
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
                'message' => 'Completed tasks fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching completed tasks: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch completed tasks'], 500);
        }
    }
/**
 * @OA\Get(
 *     path="/api/tasks/in-progress",
 *     tags={"Tasks"},
 *     summary="Get in-progress tasks",
 *     security={{"bearerAuth":{}}},
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
            if (!$user || $user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            }

            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            $query = Task::where('programmer_id', $programmer->id)
                ->whereIn('status', ['in_progress', 'review'])
                ->with(['team.project'])
                ->orderBy('deadline', 'asc');

            $tasks = $query->paginate(20);

            $result = $tasks->map(function($task) {
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
                    'in_progress_tasks' => $result,
                    'total' => $tasks->total(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                ],
                'message' => 'In-progress tasks fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching in-progress tasks: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch in-progress tasks'], 500);
        }
    }
/**
 * @OA\Get(
 *     path="/api/teams/{team}/tasks",
 *     tags={"Tasks"},
 *     summary="Get team tasks",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="team",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
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
                      ->orWhere('description', 'like', '%' . $request->description . '%');
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
 * @OA\Get(
 *     path="/api/tasks/{task}",
 *     tags={"Tasks"},
 *     summary="Get single task",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
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

        if (!$task->team->isMember($programmer->id) && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $task->load([
            'programmer.user',      // المبرمج المسند إليه
            'creator.user',         // منشئ المهمة (يجب أن يكون لديك علاقة creator في نموذج Task)
            'team.project'
        ]);

        // تحويل priority من رقم إلى نص
        $priorityText = 'low';
        if ($task->priority >= 7) {
            $priorityText = 'high';
        } elseif ($task->priority >= 4) {
            $priorityText = 'medium';
        }

        $responseData = [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $priorityText,   // نص بدلاً من رقم
            'deadline' => $task->deadline,
            'project_name' => $task->team->project->title ?? null,
            'creator' => $task->creator ? [
                'name' => $task->creator->user->full_name ?? null,
                'avatar_url' => $task->creator->avatar_url ?? null,
            ] : null,
            'attachments' => $task->attachments, // لو موجود
        ];

        // إذا كان المبرمج الحالي هو المسند إليه، يمكن إضافة حقل إضافي
        if ($task->programmer_id == $programmer->id) {
            $responseData['assigned_to_me'] = true;
        }

        return response()->json([
            'success' => true,
            'data' => $responseData
        ]);
    } catch (\Exception $e) {
        Log::error('Error showing task: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch task'], 500);
    }
}
public function store(StoreTaskRequest $request, Team $team)
{
    try {
        $user = $request->user();
        $programmer = $user->programmer;

        if (!$team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader can create tasks'
            ], 403);
        }

        if ($request->has('programmer_id') && !$team->isMember($request->programmer_id)) {
            return response()->json([
                'success' => false,
                'message' => 'The assigned programmer is not a member of this team'
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
            'message' => 'Failed to create task: ' . $e->getMessage()
        ], 500);
    }
}
    /**
 * تحديث حالة المهمة إلى "done" (مكتملة)
 */
public function markAsCompleted(Task $task)
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        // التحقق من أن المستخدم هو المسند إليه المهمة أو قائد الفريق
        if ($task->programmer_id != $programmer->id && !$task->team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned programmer or team leader can mark task as completed'
            ], 403);
        }

        if ($task->status === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Task is already completed'
            ], 400);
        }

        $task->status = 'done';
        $task->completed_at = now();
        $task->save();

        // تحديث إحصائيات المبرمج (اختياري)
        // يمكنك إضافة نقاط للمبرمج هنا

        return response()->json([
            'success' => true,
            'message' => 'Task marked as completed',
            'data' => [
                'task_id' => $task->id,
                'status' => $task->status,
                'completed_at' => $task->completed_at
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error completing task: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to complete task'], 500);
    }
}
}
