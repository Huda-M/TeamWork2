<?php

namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Evaluation;
use App\Http\Requests\UpdateProgrammerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ProfileController extends Controller
{
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
            'avatar_url' => $programmer->avatar_url ? Storage::disk('public')->url($programmer->avatar_url) : null,
        ]
    ]);
}

    // 2. عرض إحصائيات المبرمج
    public function myStats()
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer not found'], 404);
        }

        // عدد التيمات التي انضم إليها (نشطة فقط)
        $teamsCount = $programmer->teams()
            ->wherePivotNull('left_at')
            ->count();

        // عدد التاسكات المكتملة وغير المكتملة
        $completedTasks = $programmer->tasks()
            ->where('status', 'done')
            ->count();

        $incompleteTasks = $programmer->tasks()
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->count();

        // حساب الليفل النصي
        $levelText = $programmer->calculateLevel();

        return response()->json([
            'success' => true,
            'data' => [
                'programmer_id' => $programmer->id,
                'name' => $user->full_name,
                'user_name' => $programmer->user_name,
                'level' => $levelText,
                'track' => $programmer->track,
                'total_teams' => $teamsCount,
                'completed_tasks' => $completedTasks,
                'incomplete_tasks' => $incompleteTasks,
                'total_score' => $programmer->total_score,
                'stars' => $programmer->stars,
            ]
        ]);
    }

    // 3. عرض التقييمات التي تلقيتها
    public function myEvaluations()
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $evaluations = Evaluation::where('evaluated_id', $programmer->id)
            ->with(['evaluator.user', 'project'])
            ->get()
            ->map(function($eval) {
                return [
                    'project_name' => $eval->project->title,
                    'project_description' => $eval->project->description,
                    'evaluator_name' => $eval->evaluator->user->full_name,
                    'evaluator_track' => $eval->evaluator->track,
                    'technical_skills' => $eval->technical_skills,
                    'communication' => $eval->communication,
                    'teamwork' => $eval->teamwork,
                    'problem_solving' => $eval->problem_solving,
                    'reliability' => $eval->reliability,
                    'average_score' => $eval->average_score,
                    'feedback' => $eval->feedback,
                    'strengths' => $eval->strengths,
                    'areas_for_improvement' => $eval->areas_for_improvement,
                    'submitted_at' => $eval->submitted_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    // 4. عرض أعضاء الفريق لتقييمهم
    public function teamMembersToEvaluate($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        // جلب الفريق الذي ينتمي إليه المبرمج في هذا المشروع
        $team = Team::whereHas('project', function($q) use ($projectId) {
                $q->where('id', $projectId);
            })
            ->whereHas('activeMembers', function($q) use ($programmer) {
                $q->where('programmer_id', $programmer->id);
            })
            ->first();

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not in any team for this project'], 404);
        }

        $members = $team->activeMembers()
            ->with('programmer.user')
            ->where('programmer_id', '!=', $programmer->id)
            ->get()
            ->map(function($member) {
                return [
                    'programmer_id' => $member->programmer_id,
                    'name' => $member->programmer->user->full_name,
                    'track' => $member->programmer->track,
                    'avatar_url' => $member->programmer->avatar_url ? Storage::disk('public')->url($member->programmer->avatar_url) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $projectId,
                'project_title' => $team->project->title,
                'project_description' => $team->project->description,
                'members_to_evaluate' => $members,
            ]
        ]);
    }

    // 5. تقديم تقييم لعضو فريق
    public function submitEvaluation(Request $request, $projectId, $evaluatedId)
    {
        $user = Auth::user();
        $evaluator = $user->programmer;

        $validated = $request->validate([
            'technical_skills' => 'required|integer|min:1|max:5',
            'communication' => 'required|integer|min:1|max:5',
            'teamwork' => 'required|integer|min:1|max:5',
            'problem_solving' => 'required|integer|min:1|max:5',
            'reliability' => 'required|integer|min:1|max:5',
            'code_quality' => 'nullable|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'feedback' => 'nullable|string',
        ]);

        $project = Project::findOrFail($projectId);
        $evaluated = Programmer::findOrFail($evaluatedId);

        // التحقق من أن المقيم والمقيم في نفس الفريق
        $team = Team::where('project_id', $projectId)
            ->whereHas('activeMembers', function($q) use ($evaluator) {
                $q->where('programmer_id', $evaluator->id);
            })
            ->whereHas('activeMembers', function($q) use ($evaluated) {
                $q->where('programmer_id', $evaluated->id);
            })
            ->first();

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'Both programmers are not in the same team for this project'], 400);
        }

        // حساب متوسط التقييم
        $scores = [
            $validated['technical_skills'],
            $validated['communication'],
            $validated['teamwork'],
            $validated['problem_solving'],
            $validated['reliability'],
        ];
        if (isset($validated['code_quality'])) {
            $scores[] = $validated['code_quality'];
        }
        $average = round(array_sum($scores) / count($scores), 2);

        $evaluation = Evaluation::create([
            'project_id' => $projectId,
            'team_id' => $team->id,
            'evaluator_id' => $evaluator->id,
            'evaluated_id' => $evaluated->id,
            'technical_skills' => $validated['technical_skills'],
            'communication' => $validated['communication'],
            'teamwork' => $validated['teamwork'],
            'problem_solving' => $validated['problem_solving'],
            'reliability' => $validated['reliability'],
            'code_quality' => $validated['code_quality'] ?? null,
            'average_score' => $average,
            'strengths' => $validated['strengths'] ?? null,
            'areas_for_improvement' => $validated['areas_for_improvement'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
            'submitted_at' => now(),
        ]);

        // إضافة نجوم للمقيم
        $evaluated->addStars(5);

        return response()->json([
            'success' => true,
            'message' => 'Evaluation submitted successfully',
            'data' => $evaluation
        ]);
    }
public function softDeleteAccount()
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // إلغاء التوكنات
    try {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    } catch (\Exception $e) {
        Log::warning('Token deletion failed: ' . $e->getMessage());
    }

    // Soft delete
    try {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account soft deleted successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Soft delete failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete account: ' . $e->getMessage()
        ], 500);
    }
}
    // 7. Zero Project - عرض تفاصيل المشروع مع إحصائيات الأعضاء
    public function zeroProject($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $project = Project::with(['teams.activeMembers.programmer', 'teams.tasks'])
            ->findOrFail($projectId);

        // جلب الفريق الخاص بالمبرمج
        $team = $project->teams->first(function($t) use ($programmer) {
            return $t->activeMembers->contains('programmer_id', $programmer->id);
        });

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this project'], 403);
        }

        $members = $team->activeMembers->map(function($member) {
            $prog = $member->programmer;
            $tasksCount = $prog->tasks()->where('team_id', $member->team_id)->count();
            $completedTasks = $prog->tasks()
                ->where('team_id', $member->team_id)
                ->where('status', 'done')
                ->count();
            $completionRate = $tasksCount > 0 ? round(($completedTasks / $tasksCount) * 100) : 0;

            return [
                'programmer_id' => $prog->id,
                'name' => $prog->user->full_name,
                'track' => $prog->track,
                'tasks_assigned' => $tasksCount,
                'tasks_completed' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        });

        $totalTasks = $team->tasks()->count();
        $totalCompleted = $team->tasks()->where('status', 'done')->count();
        $remainingTasks = $totalTasks - $totalCompleted;

        return response()->json([
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'project_description' => $project->description,
                'team_size' => $members->count(),
                'total_tasks' => $totalTasks,
                'completed_tasks' => $totalCompleted,
                'remaining_tasks' => $remainingTasks,
                'members' => $members,
            ]
        ]);
    }

public function updateProfile(Request $request)
{
    $user = auth()->user();
    $programmer = $user->programmer;

    // Log all request data for debugging
    Log::info('Profile update request', [
        'all_data' => $request->all(),
        'has_user_name' => $request->has('user_name'),
        'has_bio' => $request->has('bio'),
        'has_track' => $request->has('track'),
        'has_full_name' => $request->has('full_name'),
        'has_avatar' => $request->hasFile('avatar'),
        'content_type' => $request->header('Content-Type'),
    ]);

    // Validate ALL fields that were sent
    $rules = [
        'full_name' => 'sometimes|string|max:255',
        'bio' => 'sometimes|string|max:1000',
        'track' => 'sometimes|string|max:100',
        'user_name' => 'sometimes|string|max:255|unique:programmers,user_name,' . ($programmer ? $programmer->id : 'NULL'),
        'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
    ];

    $validated = $request->validate($rules);

    // Log validated data
    Log::info('Profile update validated', ['validated' => $validated]);

    // Update user
    if ($request->has('full_name')) {
        $user->update(['full_name' => $validated['full_name']]);
    }

    if (!$programmer) {
        $programmer = Programmer::create(['user_id' => $user->id]);
    }

    // Update programmer - ONLY fields that were sent
    $updateData = [];
    if ($request->has('user_name')) $updateData['user_name'] = $validated['user_name'];
    if ($request->has('bio')) $updateData['bio'] = $validated['bio'];
    if ($request->has('track')) $updateData['track'] = $validated['track'];
    
    // Log update data
    Log::info('Profile update data', ['updateData' => $updateData]);

    if (!empty($updateData)) {
        $result = $programmer->update($updateData);
        Log::info('Profile update result', ['result' => $result]);
    }

    // Handle avatar
    if ($request->hasFile('avatar')) {
        if ($programmer->avatar_url && Storage::disk('public')->exists($programmer->avatar_url)) {
            Storage::disk('public')->delete($programmer->avatar_url);
        }
        $path = $request->file('avatar')->store('avatars', 'public');
        $programmer->update(['avatar_url' => $path]);
    }

    // Refresh
    $programmer->refresh();
    $user->refresh();

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $programmer->id,
            'user_name' => $programmer->user_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'bio' => $programmer->bio,
            'track' => $programmer->track,
            'avatar_url' => $programmer->avatar_url ? Storage::disk('public')->url($programmer->avatar_url) : null,
        ]
    ]);
}

    // 8. تفاصيل المشروع (لو لسه شغال أو خلص)
    public function projectDetails($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $project = Project::with([
            'teams.activeMembers.programmer.user',
            'teams.activeMembers.programmer.tasks',
            'evaluations' => function($q) use ($programmer) {
                $q->where('evaluated_id', $programmer->id);
            }
        ])->findOrFail($projectId);

        $team = $project->teams->first(function($t) use ($programmer) {
            return $t->activeMembers->contains('programmer_id', $programmer->id);
        });

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this project'], 403);
        }

        $memberRole = $team->activeMembers->firstWhere('programmer_id', $programmer->id)->role;

        $members = $team->activeMembers->map(function($member) {
            $prog = $member->programmer;
            return [
                'id' => $prog->id,
                'name' => $prog->user->full_name,
                'track' => $prog->track,
                'avatar_url' => $prog->avatar_url ? Storage::disk('public')->url($prog->avatar_url) : null,
                'role' => $member->role,
            ];
        });

        $isCompleted = $project->status === 'completed';

        $response = [
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'project_status' => $project->status,
                'project_description' => $project->description,
                'my_role' => $memberRole,
                'github_url' => $team->github_url ?? null,
                'members' => $members,
            ]
        ];

        if ($isCompleted) {
            // إذا كان المشروع مكتملاً، أضف التقييمات وتاريخ الانتهاء
            $evaluations = $project->evaluations->map(function($eval) {
                return [
                    'evaluator_name' => $eval->evaluator->user->full_name,
                    'average_score' => $eval->average_score,
                    'feedback' => $eval->feedback,
                    'strengths' => $eval->strengths,
                    'areas_for_improvement' => $eval->areas_for_improvement,
                ];
            });

            $response['data']['evaluations'] = $evaluations;
            $response['data']['completion_date'] = $project->updated_at->toDateString();
            $response['data']['duration_days'] = $project->estimated_duration_days;
        } else {
            // إذا كان المشروع قيد التنفيذ، أضف حالة المهام
            $tasks = $team->tasks()->with('programmer.user')->get();
            $tasksStats = [
                'total' => $tasks->count(),
                'todo' => $tasks->where('status', 'todo')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'review' => $tasks->where('status', 'review')->count(),
                'done' => $tasks->where('status', 'done')->count(),
            ];
            $response['data']['tasks_status'] = $tasksStats;
            $response['data']['personal_tasks'] = $tasks->where('programmer_id', $programmer->id)->values();
        }

        return response()->json($response);
    }
}
