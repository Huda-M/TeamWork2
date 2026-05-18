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
 * @OA\Tag(
 *     name="Tasks",
 *     description="Task Management API endpoints"
 * )
 */
class TaskController
{
    /**
     * الإضافة الأولى: Response Models 
     */
    
    // قبل دالة getMyTasks، أضف هذا التعليق:
    /**
     * @OA\Response(
     *     response=200,
     *     description="تم جلب قائمة المهام بنجاح",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer", example=20),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "done", "cancelled"}),
     *                     @OA\Property(property="priority", type="integer", example=1),
     *                     @OA\Property(property="deadline", type="string", format="date-time"),
     *                     @OA\Property(property="estimated_hours", type="number"),
     *                     @OA\Property(property="actual_hours", type="number"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         ),
     *         @OA\Property(property="message", type="string", example="My tasks retrieved successfully")
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string")
     *     )
     * )
     */


    /**
     * الإضافة الثانية: Error Response Model
     */
    
    /**
     * @OA\Response(
     *     response=500,
     *     description="Server Error",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Failed to retrieve your tasks")
     *     )
     * )
     */


    /**
     * الإضافة الثالثة: Request Body Example للـ Store/Update
     */
    
    /**
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         required={"title", "description", "priority", "deadline", "estimated_hours"},
     *         @OA\Property(property="title", type="string", example="Fix login bug"),
     *         @OA\Property(property="description", type="string", example="Fix the authentication issue"),
     *         @OA\Property(property="priority", type="integer", example=1),
     *         @OA\Property(property="deadline", type="string", format="date-time", example="2024-12-31T23:59:59Z"),
     *         @OA\Property(property="estimated_hours", type="number", example=5.5),
     *         @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "done", "cancelled"}, example="todo")
     *     )
     * )
     */


    /**
     * الإضافة الرابعة: Security requirement على كل endpoint
     */
    
    /**
     * @OA\SecurityScheme(
     *     type="http",
     *     description="Login with username and password to get the authentication token",
     *     name="Token",
     *     in="header",
     *     scheme="bearer",
     *     bearerFormat="JWT",
     *     securityScheme="Bearer",
     * )
     */

    /**
     * الإضافة الخامسة: Parameter examples
     */
    
    /**
     * @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Filter by task status",
     *     required=false,
     *     @OA\Schema(
     *         type="string",
     *         enum={"todo", "in_progress", "review", "done", "cancelled"},
     *         example="in_progress"
     *     )
     * )
     * @OA\Parameter(
     *     name="team_id",
     *     in="query",
     *     description="Filter by team ID",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         example=1
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number for pagination",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         default=1,
     *         example=1
     *     )
     * )
     * @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Items per page",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         default=20,
     *         example=20
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

    public function show(Task $task)
    {
        try {
            $user = auth()->user();
            $programmer = $user->programmer;

            if (!$task->team->isMember($programmer->id) && $user->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $task->load([
                'programmer.user',
                'creator.user',
                'team.project'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'deadline' => $task->deadline,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'created_at' => $task->created_at,
                    'completed_at' => $task->completed_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing task: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch task'], 500);
        }
    }
}
