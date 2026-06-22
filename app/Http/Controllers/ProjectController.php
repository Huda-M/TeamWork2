<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Annotations as OA;


class ProjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Project::with(['skills', 'user']);

            if ($request->has('skill')) {
                $query->whereHas('skills', function($q) use ($request) {
                    $q->where('skills.id', $request->skill);
                });
            }

            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->difficulty);
            }

            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $projects = $query->paginate(15);

            // Transform projects to include full image URLs
            $projects->getCollection()->transform(function ($project) {
                return $this->transformProject($project);
            });

            return response()->json([
                'success' => true,
                'message' => 'Projects fetched successfully',
                'data' => $projects
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching projects: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch projects'
            ], 500);
        }
    }

    public function zeroProject($projectId)
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

            // جلب المشروع مع الفرق والأعضاء والمهام
            $project = Project::with([
                'teams.activeMembers.programmer.user',
                'teams.tasks'
            ])->find($projectId);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found'], 404);
            }

            // التحقق من أن المستخدم هو قائد الفريق في هذا المشروع
            // نفترض أن المشروع له فريق واحد (كما في نظامك)
            $team = $project->teams->first();
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
            }

            if (!$team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only the team leader can view zero project details'], 403);
            }

            // إزالة الشرط الذي يمنع العرض إذا كانت هناك مهام
            // جمع جميع الأعضاء من كل فريق في المشروع
            $members = collect();
            foreach ($project->teams as $team) {
                foreach ($team->activeMembers as $member) {
                    $prog = $member->programmer;
                    $programmerTasks = $team->tasks->where('programmer_id', $prog->id);
                    $doneCount = $programmerTasks->where('status', 'done')->count();
                    $pendingCount = $programmerTasks->whereNotIn('status', ['done', 'cancelled'])->count();

                    $members->push([
                        'name'          => $prog->user->full_name,
                        'avatar_url'    => $prog->avatar_url ?: null,
                        'track'         => $prog->track ?? 'general',
                        'tasks_summary' => "{$doneCount} done , {$pendingCount} pending",
                    ]);
                }
            }

            $members = $members->unique('name')->values();

            $responseData = [
                'project_id'    => $project->id,
                'project_title' => $project->title,
                'description'   => $project->description,
                'total_members' => $members->count(),
                'pending_tasks' => $project->teams->flatMap->tasks->whereNotIn('status', ['done', 'cancelled'])->count(),
                'members'       => $members,
            ];

            return response()->json([
                'success' => true,
                'data'    => $responseData,
                'message' => 'Zero project details fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching zero project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch zero project details'
            ], 500);
        }
    }

    public function store(StoreProjectRequest $request)
    {
        try {
            $user = $request->user();

            DB::beginTransaction();

            $validated = $request->validated();

            // Handle project image upload like avatar
            if ($request->hasFile('image')) {
                $validated['image_url'] = $request->file('image')->store('projects', 'public');
            }

            $project = Project::create($validated);

            if ($request->has('skills')) {
                $project->skills()->sync($validated['skills']);
            }

            Log::info('Project created', [
                'project_id' => $project->id,
                'created_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->transformProject($project->load('skills', 'user')),
                'message' => 'Project created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project'
            ], 500);
        }
    }

    public function markAsCompleted($projectId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $project = Project::with('teams')->find($projectId);
            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found'], 404);
            }

            $team = $project->teams->first();
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
            }

            $programmer = $user->programmer;
            if (!$programmer || !$team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only the team leader can mark the project as completed'], 403);
            }

            // تحديث حالة الفريق إلى 'completed' بدلاً من ذلك
            $team->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Project marked as completed (team status updated)',
                'project_status' => $project->status // هذا سيعيد 'completed' الآن تلقائياً
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking project completed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to mark project: ' . $e->getMessage()], 500);
        }
    }

    public function myProjectDetails($projectId, Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            }

            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            // جلب المشروع مع العلاقات المطلوبة
            $project = Project::with([
                'teams.activeMembers.programmer.user',
                'teams.activeMembers.programmer.tasks',
                'evaluations' // التقييمات الخاصة بالمشروع
            ])->find($projectId);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found'], 404);
            }

            // تحديد الفريق الذي ينتمي إليه المبرمج الحالي
            $myTeam = $project->teams->first(function ($team) use ($programmer) {
                return $team->activeMembers->contains('programmer_id', $programmer->id);
            });

            if (!$myTeam) {
                return response()->json(['success' => false, 'message' => 'You are not a member of any team in this project'], 403);
            }

            // التراك الخاص بي
            $myTrack = $programmer->track ?? 'general';

            // رابط GitHub (من المشروع أو من الفريق – نأخذ من المشروع لأنه موجود هناك في كودك)
            $githubLink = $project->github_url ?? null;

            // أعضاء الفريق (للمشاريع الجارية نحتاج أسمائهم وصورهم فقط)
            $teamMembers = $myTeam->activeMembers->map(function ($member) {
                $prog = $member->programmer;
                return [
                    'id' => $prog->id,
                    'name' => $prog->user->full_name,
                    'avatar_url' => $prog->avatar_url ?: null,
                    'role_in_team' => $member->role,
                ];
            });

            // البيانات المشتركة بين الحالتين (مكتمل / قيد التنفيذ)
            $responseData = [
                'project' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'status' => $project->status,
                    'my_track' => $myTrack,
                    'github_link' => $githubLink,
                    'team_members' => $teamMembers,
                    'image_url' => $project->image_url ? Storage::disk('public')->url($project->image_url) : null,
                ]
            ];

            // إذا كان المشروع مكتملاً (completed)
            if ($project->status === 'completed') {
                $responseData['project']['category'] = $project->category_name;
                // المدة المتوقعة للمشروع (بالأيام)
                $durationDays = $project->estimated_duration_days;
                // تاريخ الانتهاء الفعلي (آخر تحديث للمشروع أو آخر مهمة)
                $completionDate = $project->updated_at->toDateString();

                // جلب جميع التقييمات الخاصة بهذا المشروع (feedbacks)
                $feedbacks = $project->evaluations->map(function ($eval) {
                    return [
                        'evaluator_name' => $eval->evaluator->user->full_name,
                        'evaluated_name' => $eval->evaluated->user->full_name,
                        'average_score' => $eval->average_score,
                        'feedback' => $eval->feedback,
                        'strengths' => $eval->strengths,
                        'areas_for_improvement' => $eval->areas_for_improvement,
                    ];
                });

                // حساب النجوم (rating) للمبرمج الحالي بناءً على التقييمات التي استقبلها في هذا المشروع
                $myEvaluations = $project->evaluations->where('evaluated_id', $programmer->id);
                $averageRating = $myEvaluations->isNotEmpty() ? $myEvaluations->avg('average_score') : 0;
                // تحويل إلى نسبة مئوية من 5 (مثلاً 4.2 من 5)
                $starsPercentage = round(($averageRating / 5) * 100, 2);

                $responseData['project']['duration_days'] = $durationDays;
                $responseData['project']['completion_date'] = $completionDate;
                $responseData['project']['feedbacks'] = $feedbacks;
                $responseData['project']['my_rating'] = round($averageRating, 2); // من 5
                $responseData['project']['stars_percentage'] = $starsPercentage; // نسبة مئوية

            } else {
                // المشروع قيد التنفيذ (ongoing)
                $responseData['project']['team_members_count'] = $teamMembers->count();
                // يمكن إضافة مهام إن وجدت (اختياري)
                if ($request->boolean('include_team_tasks', false)) {
                    $teamTasks = $myTeam->tasks()->with('programmer.user')->get();
                    $responseData['team_tasks'] = $teamTasks;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error in myProjectDetails: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch project details'], 500);
        }
    }

    public function update(UpdateProjectRequest $request, $id)
    {
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            DB::beginTransaction();

            $validated = $request->validated();

            // Handle project image upload like avatar - delete old image if exists
            if ($request->hasFile('image')) {
                if ($project->image_url && Storage::disk('public')->exists($project->image_url)) {
                    Storage::disk('public')->delete($project->image_url);
                }
                $validated['image_url'] = $request->file('image')->store('projects', 'public');
            }

            $project->update($validated);

            if ($request->has('skills')) {
                $project->skills()->sync($validated['skills']);
            }

            Log::info('Project updated', [
                'project_id' => $project->id,
                'updated_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $this->transformProject($project->load('skills'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            $activeTeams = $project->teams()->active()->count();
            if ($activeTeams > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete project with active teams',
                    'active_teams' => $activeTeams
                ], 400);
            }

            DB::beginTransaction();

            // Delete project image if exists (like avatar deletion)
            if ($project->image_url && Storage::disk('public')->exists($project->image_url)) {
                Storage::disk('public')->delete($project->image_url);
            }

            $project->skills()->detach();
            $project->delete();

            Log::info('Project deleted', [
                'project_id' => $id,
                'deleted_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project'
            ], 500);
        }
    }

    public function teams($id)
    {
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            $teams = $project->teams()
                ->with(['leader.programmer.user', 'activeMembers.programmer.user'])
                ->withCount('activeMembers')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Project teams fetched successfully',
                'data' => $teams
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching project teams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch project teams'
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $project = Project::with([
                'skills',
                'user',
                'teams.activeMembers.programmer.user',
                'teams.activeMembers.programmer.tracks',
                'evaluations' => function($q) {
                    $q->with(['evaluator.user', 'evaluated.user']);
                }
            ])->findOrFail($id);

            // ----- 1. أعضاء الفريق مع تخصصهم -----
            $teamMembers = collect();
            foreach ($project->teams as $team) {
                foreach ($team->activeMembers as $member) {
                    $programmer = $member->programmer;
                    $specialization = $programmer->track ?? $this->extractSpecializationFromSkills($programmer);
                    $teamMembers->push([
                        'programmer_id' => $programmer->id,
                        'user_name' => $programmer->user_name,
                        'full_name' => $programmer->user->full_name,
                        'avatar_url' => $programmer->avatar_url ?: null,
                        'role_in_team' => $member->role,
                        'specialization' => $specialization,
                        'team_name' => $team->name,
                        'joined_at' => $member->joined_at,
                    ]);
                }
            }

            // ----- 2. التقييمات -----
            $feedbacks = [];
            foreach ($project->evaluations as $eval) {
                $feedbacks[] = [
                    'evaluator_name' => $eval->evaluator->user->full_name,
                    'evaluated_name' => $eval->evaluated->user->full_name,
                    'technical_skills' => $eval->technical_skills,
                    'communication' => $eval->communication,
                    'teamwork' => $eval->teamwork,
                    'problem_solving' => $eval->problem_solving,
                    'reliability' => $eval->reliability,
                    'code_quality' => $eval->code_quality,
                    'average_score' => $eval->average_score,
                    'strengths' => $eval->strengths,
                    'areas_for_improvement' => $eval->areas_for_improvement,
                    'feedback' => $eval->feedback,
                    'submitted_at' => $eval->submitted_at,
                ];
            }

            $totalAverageRating = $project->evaluations->avg('average_score');

            // ----- 3. تاريخ الانتهاء -----
            $completionDate = null;
            if ($project->status === 'completed') {
                $completionDate = $project->updated_at;
                $lastTask = $project->teams->flatMap->tasks->where('status', 'done')->sortByDesc('updated_at')->first();
                if ($lastTask) $completionDate = $lastTask->updated_at;
            }

            $durationDays = $project->estimated_duration_days;
            $completionPercentage = $project->completion_percentage;

            // ----- 4. دور المستخدم الحالي -----
            $currentUserRole = null;
            $authUser = Auth::user(); // الآن يعمل بشكل صحيح
            if ($authUser && $authUser->role === 'programmer' && $authUser->programmer) {
                $prog = $authUser->programmer;
                $isParticipant = $teamMembers->contains('programmer_id', $prog->id);
                $myTeamRole = null;
                if ($isParticipant) {
                    $teamMember = $teamMembers->firstWhere('programmer_id', $prog->id);
                    $myTeamRole = $teamMember['role_in_team'] ?? null;
                }
                $currentUserRole = [
                    'specialization' => $prog->track ?? $this->extractSpecializationFromSkills($prog),
                    'is_participant' => $isParticipant,
                    'team_role' => $myTeamRole,
                ];
            }

            $response = [
                'success' => true,
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'title' => $project->title,
                        'category' => $project->category_name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'duration_days' => $durationDays,
                        'expected_end_date' => $project->expected_end_date->toDateString(),
                        'completion_date' => $completionDate ? $completionDate->toDateString() : null,
                        'completion_percentage' => $completionPercentage,
                        'difficulty' => $project->difficulty,
                        'max_team_size' => $project->team_size,
                        'num_of_teams' => $project->teams->count(),
                        'image_url' => $project->image_url ? Storage::disk('public')->url($project->image_url) : null,
                        'owner' => [
                            'id' => $project->user->id,
                            'name' => $project->user->full_name,
                        ],
                        'skills_required' => $project->skills->pluck('name'),
                    ],
                    'team_members' => $teamMembers,
                    'feedbacks' => $feedbacks,
                    'total_average_rating' => round($totalAverageRating, 2),
                    'current_user_role' => $currentUserRole,
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error in show project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch project details'
            ], 500);
        }
    }

    public function getUserProjects($userId, Request $request)
    {
        try {
            $user = User::findOrFail($userId); // الآن يعمل

            $statusFilter = $request->query('status');
            $projects = collect();

            if ($user->role === 'programmer' && $user->programmer) {
                $programmer = $user->programmer;

                $query = Project::whereHas('teams.activeMembers', function($q) use ($programmer) {
                    $q->where('programmer_id', $programmer->id);
                })->with(['teams' => function($q) use ($programmer) {
                    $q->whereHas('activeMembers', function($sub) use ($programmer) {
                        $sub->where('programmer_id', $programmer->id);
                    });
                }]);

                if ($statusFilter === 'active') {
                    $query->where('status', 'active');
                } elseif ($statusFilter === 'completed') {
                    $query->where('status', 'completed');
                }

                $projects = $query->get()->map(function($project) use ($programmer) {
                    $myTasks = $project->teams->flatMap->tasks->where('programmer_id', $programmer->id);
                    $completedMyTasks = $myTasks->where('status', 'done')->count();
                    $myCompletion = $myTasks->isEmpty() ? 0 : round(($completedMyTasks / $myTasks->count()) * 100);

                    return [
                        'id' => $project->id,
                        'title' => $project->title,
                        'description' => $project->description,
                        'category' => $project->category_name,
                        'status' => $project->status,
                        'estimated_duration_days' => $project->estimated_duration_days,
                        'expected_end_date' => $project->expected_end_date->toDateString(),
                        'completion_date' => ($project->status === 'completed') ? $project->updated_at->toDateString() : null,
                        'project_completion_percentage' => $project->completion_percentage,
                        'my_completion_percentage' => $myCompletion,
                        'my_specialization' => $programmer->track ?? $this->extractSpecializationFromSkills($programmer),
                        'image_url' => $project->image_url ? Storage::disk('public')->url($project->image_url) : null,
                    ];
                });
            } elseif ($user->role === 'company') {
                $projects = Project::where('user_id', $userId)->get()->map(function($project) {
                    return $this->transformProject($project);
                });
            } elseif ($user->role === 'admin') {
                $projects = Project::with('user')->get()->map(function($project) {
                    return $this->transformProject($project);
                });
            }

            return response()->json([
                'success' => true,
                'data' => $projects,
                'message' => 'User projects fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getUserProjects: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user projects'
            ], 500);
        }
    }

    public function myProjects(Request $request)
{
    $user = Auth::user();
    if (!$user || $user->role !== 'programmer') {
        return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
    }

    $programmer = $user->programmer;
    if (!$programmer) {
        return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
    }

    $perPage = (int) $request->get('per_page', 10);
    $page = (int) $request->get('page', 1);
    $statusFilter = $request->query('status');

    // ─── جلب المشاريع مع الفرق والأعضاء والمهام ───
    $allProjects = Project::whereHas('teams', function($q) use ($programmer) {
            $q->whereHas('activeMembers', function($sub) use ($programmer) {
                $sub->where('programmer_id', $programmer->id);
            });
        })
        ->with([
            'teams' => function($q) {
                $q->with([
                    'activeMembers.programmer.user',
                    'tasks'
                ]);
            }
        ])
        ->get();

    $ongoingProjects = [];
    $completedProjects = [];

    foreach ($allProjects as $project) {
        // ─── حساب تقدم المبرمج ───
        $myTasks = $project->teams->flatMap->tasks->where('programmer_id', $programmer->id);
        $completedMyTasks = $myTasks->where('status', 'done')->count();
        $totalMyTasks = $myTasks->count();
        $myCompletion = $totalMyTasks > 0 ? round(($completedMyTasks / $totalMyTasks) * 100) : 0;

        // ─── Check Leader + Your Team ───
        $isLeader = false;
        $yourTeam = null;

        foreach ($project->teams as $team) {
            // ✅ Check 1: created_by (اللي عمل التيم)
            // ✅ Check 2: leader_id (اللي هو الليدر الحالي)
            // ✅ Check 3: isLeader() method لو موجود
            
            $teamCreatedBy = $team->created_by;
            $teamLeaderId = $team->leader_id ?? null;
            $progId = $programmer->id;

            $isTeamLeader = (
                $teamCreatedBy == $progId || 
                $teamLeaderId == $progId ||
                (method_exists($team, 'isLeader') && $team->isLeader($progId))
            );

            if ($isTeamLeader) {
                $isLeader = true;

                $yourTeam = [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'team_size' => $team->activeMembers->count(),
                    'members' => $team->activeMembers->map(function($member) {
                        $prog = $member->programmer;
                        return [
                            'programmer_id' => $prog->id,
                            'name' => $prog->user->full_name ?? 'Unknown',
                            'avatar_url' => $prog->avatar_url 
                                ? Storage::disk('public')->url($prog->avatar_url) 
                                : null,
                            'track' => $prog->track ?? 'general',
                            'role' => $member->role ?? 'member',
                        ];
                    }),
                    'github_url' => $team->github_url ?? null,
                    'tasks_count' => $team->tasks->count(),
                    'completed_tasks_count' => $team->tasks->where('status', 'done')->count(),
                ];

                // Exit loop — we found the leader's team
                break;
            }
        }

        $projectData = [
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'category' => $project->category_name,
            'status' => $project->status,
            'estimated_duration_days' => $project->estimated_duration_days,
            'expected_end_date' => $project->expected_end_date?->toDateString(),
            'project_completion_percentage' => $project->completion_percentage ?? 0,
            'my_completion_percentage' => $myCompletion,
            'my_specialization' => $programmer->track ?? 'general',
            'image_url' => $project->image_url 
                ? Storage::disk('public')->url($project->image_url) 
                : null,
            'is_leader' => $isLeader,
            'your_team' => $yourTeam,
        ];

        if ($project->status === 'ongoing' || $project->status === 'active') {
            $ongoingProjects[] = $projectData;
        } else {
            $projectData['completion_date'] = $project->updated_at?->toDateString();
            $completedProjects[] = $projectData;
        }
    }

    // ─── Pagination + Response ───
    $collection = $statusFilter === 'ongoing' || $statusFilter === 'active'
        ? collect($ongoingProjects)
        : ($statusFilter === 'completed' ? collect($completedProjects) : collect(array_merge($ongoingProjects, $completedProjects)));

    $paginated = $this->paginateCollection($collection, $perPage, $page);

    return response()->json([
        'success' => true,
        'data' => $paginated
    ]);
}

    private function extractSpecializationFromSkills($programmer)
    {
        if (method_exists($programmer, 'skills') && $programmer->skills()->exists()) {
            $skillNames = $programmer->skills->pluck('name')->toArray();
            if (in_array('Laravel', $skillNames) || in_array('PHP', $skillNames)) return 'backend';
            if (in_array('Vue.js', $skillNames) || in_array('React', $skillNames)) return 'frontend';
            if (in_array('UI/UX', $skillNames)) return 'ui';
            return $skillNames[0] ?? 'general';
        }
        if ($programmer->skills && is_array($programmer->skills)) {
            $firstSkill = $programmer->skills[0] ?? null;
            if ($firstSkill) return $firstSkill;
        }
        return 'general';
    }


    public function projectTasks($projectId, Request $request)
    {
        try {
            $project = Project::with(['teams.tasks' => function($q) use ($request) {
                if ($request->has('status')) {
                    $q->where('tasks.status', $request->status);
                }
                if ($request->has('programmer_id')) {
                    $q->where('tasks.programmer_id', $request->programmer_id);
                }
                $q->orderBy('priority', 'desc')->orderBy('deadline');
            }, 'teams.tasks.programmer.user'])->findOrFail($projectId);

            $allTasks = $project->teams->flatMap->tasks;

            return response()->json([
                'success' => true,
                'data' => $allTasks,
                'message' => 'Project tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching project tasks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch project tasks'
            ], 500);
        }
    }

    /**
     * Helper method to transform project with full image URL
     * Like ProfileController does with avatar_url
     */
    private function transformProject($project)
    {
        $project->image_url = $project->image_url ? Storage::disk('public')->url($project->image_url) : null;
        return $project;
    }

    private function paginateCollection($collection, $perPage, $page)
    {
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $collection->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
    }
}
