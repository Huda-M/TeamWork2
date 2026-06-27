<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Programmer;
use App\Http\Requests\StoreProgrammerRequest;
use App\Http\Requests\UpdateProgrammerRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class ProgrammerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/programmers",
     *     operationId="getProgrammers",

     *     tags={"Programmers"},
     *     summary="جلب قائمة المبرمجين",
     *     description="الحصول على قائمة بجميع المبرمجين المسجلين",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="رقم الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="عدد النتائج في الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="قائمة المبرمجين",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="user_name", type="string"),
     *                     @OA\Property(property="track", type="string"),
     *                     @OA\Property(property="total_score", type="integer"),
     *                     @OA\Property(property="experience_level", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في السيرفر")
     * )
     */
    public function index()
    {
        $programmers = Programmer::with('user')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Programmer list fetched successfully',
            'data' => $programmers
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/programmers/{id}",
     *     tags={"Programmers"},
     *     summary="جلب بيانات مبرمج محدد",
     *     description="الحصول على تفاصيل مبرمج واحد",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="معرف المبرمج",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="بيانات المبرمج",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="المبرمج غير موجود")
     * )
     */
    public function show($id)
    {
        $programmer = Programmer::with('user')->find($id);

        if (!$programmer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Programmer not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Programmer fetched successfully',
            'data' => [
                'programmer' => $programmer
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/my/statistics",
     *     tags={"Statistics"},
     *     summary="جلب إحصائياتي",
     *     description="الحصول على إحصائيات شاملة عن المبرمج الحالي",
     *     security={{"Bearer": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="الإحصائيات",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="programmer_id", type="integer"),
     *                 @OA\Property(property="programmer_name", type="string"),
     *                 @OA\Property(property="total_tasks_all_projects", type="integer"),
     *                 @OA\Property(property="completed_tasks_all_projects", type="integer"),
     *                 @OA\Property(property="overall_completion_rate", type="number"),
     *                 @OA\Property(property="total_projects_participated", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=403, description="ممنوع"),
     *     @OA\Response(response=404, description="ملف تعريفي غير موجود")
     * )
     */
    public function myStatistics()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can access'
            ], 403);
        }

        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }

        $totalTasks = $programmer->tasks()->count();
        $completedTasks = $programmer->tasks()->where('status', 'done')->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        $projects = $programmer->teams()
            ->whereNull('team_members.left_at')
            ->with('project')
            ->get()
            ->pluck('project')
            ->unique('id');

        $totalProjects = $projects->count();

        $projectsStats = [];
        foreach ($projects as $project) {
            $tasksInProject = $programmer->tasks()
                ->whereHas('team', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })->get();

            $total = $tasksInProject->count();
            $completed = $tasksInProject->where('status', 'done')->count();
            $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            $projectsStats[] = [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'completion_percentage' => $percentage,
                'image_url' => $project->image_url ? Storage::disk('public')->url($project->image_url) : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'programmer_id' => $programmer->id,
                'programmer_name' => $programmer->user->full_name,
                'total_tasks_all_projects' => $totalTasks,
                'completed_tasks_all_projects' => $completedTasks,
                'overall_completion_rate' => $completionRate,
                'total_projects_participated' => $totalProjects,
                'projects_details' => $projectsStats,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/programmers/{id}/statistics",
     *     tags={"Statistics"},
     *     summary="جلب إحصائيات مبرمج محدد",
     *     description="الحصول على إحصائيات مبرمج آخر",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="معرف المبرمج",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="الإحصائيات",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=404, description="المبرمج غير موجود")
     * )
     */
    public function programmerStatistics($id)
    {
        $programmer = Programmer::with('user')->find($id);
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer not found'], 404);
        }

        $totalTasks = $programmer->tasks()->count();
        $completedTasks = $programmer->tasks()->where('status', 'done')->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        $projects = $programmer->teams()
            ->whereNull('team_members.left_at')
            ->with('project')
            ->get()
            ->pluck('project')
            ->unique('id');

        $totalProjects = $projects->count();
        $projectsStats = [];

        foreach ($projects as $project) {
            $tasksInProject = $programmer->tasks()
                ->whereHas('team', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })->get();

            $total = $tasksInProject->count();
            $completed = $tasksInProject->where('status', 'done')->count();
            $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            $projectsStats[] = [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'category' => $project->category_name,
                'status' => $project->status,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'completion_percentage' => $percentage,
                'image_url' => $project->image_url ? Storage::disk('public')->url($project->image_url) : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'programmer_id' => $programmer->id,
                'programmer_name' => $programmer->user->full_name,
                'total_tasks_all_projects' => $totalTasks,
                'completed_tasks_all_projects' => $completedTasks,
                'overall_completion_rate' => $completionRate,
                'total_projects_participated' => $totalProjects,
                'projects_details' => $projectsStats,
            ]
        ]);
    }

    public function store(StoreProgrammerRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('avatar_url')) {
            $validated['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
        }

        $programmer = Programmer::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Programmer created successfully',
            'data' => $programmer
        ]);
    }

    /**
     * عرض لوحة المعلومات الخاصة بالمبرمج (ملخص كامل)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard()
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

            // ---- 1. بيانات الملف الشخصي (بدون user_name) ----
            $profileData = [
                'name'       => $user->full_name,
                'track'      => $programmer->track ?? 'general',
                'avatar_url' => $programmer->avatar_url 
    ? Storage::disk('public')->url($programmer->avatar_url) 
    : null,
                'level'      => $this->getProgrammerLevel($programmer),
            ];

            // ---- 2. إحصائيات المهام ----
            $completedTasks = $programmer->tasks()->where('status', 'done')->count();
            $inProgressTasks = $programmer->tasks()->whereIn('status', ['todo', 'in_progress', 'review'])->count();

            // ---- 3. عدد الفرق النشطة ----
            $teamsCount = $programmer->teams()
                ->wherePivotNull('left_at')
                ->count();

            return response()->json([
                'success' => true,
                'data'    => [
                    'profile'          => $profileData,
                    'tasks_statistics' => [
                        'completed_tasks'  => $completedTasks,
                        'in_progress_tasks' => $inProgressTasks,
                    ],
                    'teams_count' => $teamsCount,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard'
            ], 500);
        }
    }

   public function levelProgression()
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

        $score = $programmer->total_score ?? 0;

        // ✅ استخدم experience_level من الـ programmer model
        $baseLevel = strtolower(trim($programmer->experience_level ?? ''));

        // ✅ لو فاضي أو غلط، احسب من الـ score
        $validLevels = ['beginner', 'junior', 'intermediate', 'senior', 'advanced', 'expert'];
        if (empty($baseLevel) || !in_array($baseLevel, $validLevels)) {
            $baseLevel = $this->getBaseLevelFromScore($score);
        }

        // تعريف المستويات الفرعية
        $subLevels = [
            'beginner'     => ['bronze' => 0,   'silver' => 50,  'gold' => 100],
            'junior'       => ['bronze' => 200,  'silver' => 250, 'gold' => 300],
            'intermediate' => ['bronze' => 400,  'silver' => 460, 'gold' => 520],
            'senior'       => ['bronze' => 600,  'silver' => 680, 'gold' => 760],
            'advanced'     => ['bronze' => 800,  'silver' => 890, 'gold' => 980],
            'expert'       => ['bronze' => 1000, 'silver' => 1100, 'gold' => 1200],
        ];

        $subs = $subLevels[$baseLevel] ?? $subLevels['beginner'];

        // ✅ تحديد الـ sub level بناءً على score
        $currentSubLevel = 'bronze';
        $currentThreshold = $subs['bronze'];
        $nextSubLevel = null;
        $nextThreshold = null;

        foreach ($subs as $sub => $threshold) {
            if ($score >= $threshold) {
                $currentSubLevel = $sub;
                $currentThreshold = $threshold;
            } else {
                $nextSubLevel = $sub;
                $nextThreshold = $threshold;
                break;
            }
        }

        // ✅ لو وصل لـ gold، الـ next يبقى المستوى اللي بعده
        if ($nextSubLevel === null && $baseLevel !== 'expert') {
            $nextBaseLevel = $this->getNextBaseLevel($baseLevel);
            if ($nextBaseLevel) {
                $nextSubLevel = 'bronze';
                $nextThreshold = $subLevels[$nextBaseLevel]['bronze'];
            }
        }

        $isMaxLevel = ($baseLevel === 'expert' && $currentSubLevel === 'gold');

        // ✅ حساب نسبة التقدم
        $progressPercentage = 0;
        if (!$isMaxLevel && $nextThreshold !== null && $nextThreshold > $currentThreshold) {
            $range = $nextThreshold - $currentThreshold;
            $progress = $score - $currentThreshold;
            $progressPercentage = round(($progress / $range) * 100, 2);
            $progressPercentage = min(max($progressPercentage, 0), 99.99);
        } elseif ($isMaxLevel) {
            $progressPercentage = 100;
        }

        $fullLevelName = ucfirst($baseLevel) . ' ' . ucfirst($currentSubLevel);
        
        $nextFullLevelName = null;
        if (!$isMaxLevel && $nextSubLevel) {
            $nextBase = ($currentSubLevel === 'gold') 
                ? $this->getNextBaseLevel($baseLevel) 
                : $baseLevel;
            $nextFullLevelName = ucfirst($nextBase) . ' ' . ucfirst($nextSubLevel);
        }

        $totalTasks = $programmer->tasks()->count();
        $averageRating = \App\Models\Evaluation::where('evaluated_id', $programmer->id)
            ->avg('average_score') ?? 0;
        $averageRating = round($averageRating, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'current_level_full'    => $fullLevelName,
                'base_level'            => $baseLevel,
                'sub_level'             => $currentSubLevel,
                'progress_percentage'   => $progressPercentage,
                'next_level_full'       => $nextFullLevelName,
                'total_tasks'           => $totalTasks,
                'average_rating'        => $averageRating,
                'total_score'           => $score,
                'experience_level'      => $programmer->experience_level,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Level progression error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to load level progression'
        ], 500);
    }
}

private function getBaseLevelFromScore($score)
{
    if ($score >= 1000) return 'expert';
    if ($score >= 700)  return 'advanced';
    if ($score >= 500)  return 'senior';
    if ($score >= 200)  return 'intermediate';
    if ($score >= 50)   return 'junior';
    return 'beginner';
}

private function getNextBaseLevel($currentBaseLevel)
{
    $levels = ['beginner', 'junior', 'intermediate', 'senior', 'advanced', 'expert'];
    $currentIndex = array_search($currentBaseLevel, $levels);
    
    if ($currentIndex === false || $currentIndex >= count($levels) - 1) {
        return null;
    }
    
    return $levels[$currentIndex + 1];
}

    
    public function searchByUsername(Request $request)
    {
        $query = $request->get('query', '');

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $programmers = Programmer::with('user')
            ->where('user_name', 'LIKE', "%{$query}%")  
            ->limit(10)
            ->get()
            ->map(function($programmer) {
                return [
                    'id' => $programmer->id,
                    'user_name' => $programmer->user_name,
                    'full_name' => $programmer->user->full_name,
                    'avatar_url' => $programmer->avatar_url 
    ? Storage::disk('public')->url($programmer->avatar_url) 
    : null,
                    'track' => $programmer->track,
                ];
            });

        return response()->json(['data' => $programmers]);
    }
}
