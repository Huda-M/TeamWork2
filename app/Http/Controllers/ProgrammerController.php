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
                'category' => $project->category_name,
                'status' => $project->status,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'completion_percentage' => $percentage,
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
        $programmer = Programmer::create($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'Programmer created successfully',
            'data' => $programmer
        ]);
    }

    public function joinTeam(){
        $user = auth()->user();
        $programmer = Programmer::query()->where('user_id', $user->id)->firstOrFail();
        $teams = Team::query()->where('status','forming')->get();
        $payload = [
            'user_profile'=>[
                'user_id'=>$user->id,
                'full_name'=>$user->full_name,
                'skills'=>$programmer->skills,
                'experience'=>$programmer->total_score,
            ],
            'teams'=>$teams->map(function($team){
                return [
                    'id'=>$team->id,
                    'name'=>$team->name,
                    'status'=>$team->status,
                    'req_skills'=>$team->required_skills,
                    'req_exp_level'=>$team->experience_level,
                    'composition'=>$team->teamMembers->map(function($member){
                        return [
                            'user_id'=>$member->programmer->user_id,
                            'full_name'=>$member->programmer->user->full_name,
                            'experience'=>$member->programmer->experience_level,
                        ];
                    })
                ];
            })
        ];

        Http::post('https://arabicsoft-ai-team-matcher.hf.space/api/match-teams', $payload);

        return response()->json([
            'success' => true,
            'data' => $payload
        ]);
    }
}
