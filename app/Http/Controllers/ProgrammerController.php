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
                'experience'=>optional($programmer->programmerLevel)->current_level ?? 1,
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
                            'experience'=>optional($member->programmer->programmerLevel)->current_level ?? 1,
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
    /**
     * @OA\Get(
     *     path="/api/profile",
     *     operationId="getMyProfile",
     *     tags={"Profile"},
     *     summary="Get current programmer profile",
     *     description="Returns the profile data of the authenticated programmer.",
     *     security={{"Bearer": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_name", type="string"),
     *                 @OA\Property(property="full_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="bio", type="string"),
     *                 @OA\Property(property="track", type="string"),
     *                 @OA\Property(property="avatar_url", type="string"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - not a programmer"),
     *     @OA\Response(response=404, description="Profile not found")
     * )
     */

    /**
     * @OA\Put(
     *     path="/api/profile/update",
     *     operationId="updateMyProfile",
     *     tags={"Profile"},
     *     summary="Update current programmer profile",
     *     description="Allows a programmer to update their profile information (full name, bio, track, avatar_url, etc.).",
     *     security={{"Bearer": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="full_name", type="string", example="Ahmed Mohamed"),
     *             @OA\Property(property="bio", type="string", example="Senior Developer with 5 years experience"),
     *             @OA\Property(property="track", type="string", example="Web Development"),
     *             @OA\Property(property="avatar_url", type="string", format="url", example="https://example.com/avatar.jpg"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
public function myProfile()
{
    $user = Auth::user();
    if (!$user || $user->role !== 'programmer') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $programmer = $user->programmer;
    if (!$programmer) {
        return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id'         => $programmer->id,
            'user_name'  => $programmer->user_name,
            'full_name'  => $user->full_name,
            'email'      => $user->email,
            'bio'        => $programmer->bio,
            'track'      => $programmer->track,
            'avatar_url' => $programmer->avatar_url,
        ]
    ]);
}

public function updateProfile(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json(['success' => false, 'message' => 'Only programmers can update their profile'], 403);
        }

        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }

        // قواعد التحقق الأساسية
        $rules = [
            'full_name'    => 'sometimes|required|string|max:255',
            'bio'          => 'nullable|string|max:1000',
            'track'        => 'nullable|string|max:100',
            'avatar'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'avatar_url'   => 'nullable|url|max:255',
        ];

        // معالجة user_name فقط إذا ورد في الطلب وتغيرت قيمته
        if ($request->has('user_name')) {
            $newUserName = $request->input('user_name');
            if ($newUserName !== $programmer->user_name) {
                $rules['user_name'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('programmers', 'user_name')->ignore($programmer->id)
                ];
            } else {
                $rules['user_name'] = 'sometimes|required|string|max:255';
            }
        }

        // لا نضيف email في القواعد نهائياً

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // 1. تحديث جدول users (الحقول الموجودة فعلاً)
        if ($request->has('full_name')) {
            $user->full_name = $request->input('full_name');
            $user->save();
        }

        // 2. تحديث جدول programmers
        $programmerUpdated = false;
        if ($request->has('user_name')) {
            $programmer->user_name = $request->input('user_name');
            $programmerUpdated = true;
        }
        if ($request->has('bio')) {
            $programmer->bio = $request->input('bio');
            $programmerUpdated = true;
        }
        if ($request->has('track')) {
            $programmer->track = $request->input('track');
            $programmerUpdated = true;
        }

        // معالجة الصورة
        if ($request->hasFile('avatar')) {
            if ($programmer->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $programmer->avatar_url))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $programmer->avatar_url));
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $programmer->avatar_url = Storage::url($path);
            $programmerUpdated = true;
        } elseif ($request->has('avatar_url') && $request->filled('avatar_url')) {
            $programmer->avatar_url = $request->input('avatar_url');
            $programmerUpdated = true;
        }

        if ($programmerUpdated) {
            $programmer->save();
        }

        // إعادة تحميل البيانات لضمان الحداثة
        $programmer->refresh();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id'          => $programmer->id,
                'user_name'   => $programmer->user_name,
                'full_name'   => $user->full_name,
                'email'       => $user->email,
                'bio'         => $programmer->bio,
                'track'       => $programmer->track,
                'avatar_url'  => $programmer->avatar_url,
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Profile update error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating profile',
            'error'   => $e->getMessage()
        ], 500);
    }
}
}

}
