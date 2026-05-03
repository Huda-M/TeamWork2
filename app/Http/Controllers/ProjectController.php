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

    public function store(StoreTaskRequest $request, Team $team)
{
    try {
        $user = $request->user();
        $programmer = $user->programmer;

        // التحقق من أن المستخدم قائد الفريق
        if (!$team->isLeader($programmer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader can create tasks'
            ], 403);
        }

        // التحقق من أن المبرمج المعين موجود في الفريق
        if ($request->has('programmer_id') && !$team->isMember($request->programmer_id)) {
            return response()->json([
                'success' => false,
                'message' => 'The assigned programmer is not a member of this team'
            ], 400);
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
            'git_link' => $request->git_link,
            'tags' => $request->tags, // json array
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
            'message' => 'Failed to create task'
        ], 500);
    }
}

public function markAsCompleted($projectId)
{
    try {
        $user = Auth::user();
        // السماح فقط للأدمن العام
        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Only admin can mark project as completed'], 403);
        }

        $project = Project::findOrFail($projectId);
        $project->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Project marked as completed'
        ]);
    } catch (\Exception $e) {
        Log::error('Error marking project completed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to mark project'], 500);
    }
}

/**
 * عرض تفاصيل المشروع للمبرمج الحالي (دوره، الأعضاء، مهامه، مهام الفريق)
 *
 * @param int $projectId
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
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
            'teams.activeMembers.programmer.tasks' => function($q) use ($projectId) {
                $q->where('project_id', $projectId);
            },
            'teams.tasks' => function($q) use ($projectId) {
                $q->where('project_id', $projectId);
            }
        ])->find($projectId);

        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        // تحديد الفريق الذي ينتمي إليه المبرمج الحالي
        $myTeam = $project->teams->first(function ($team) use ($programmer) {
            return $team->isMember($programmer->id);
        });

        if (!$myTeam) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of any team in this project'
            ], 403);
        }

        // دور المبرمج في فريقه
        $myTeamMember = $myTeam->activeMembers->firstWhere('programmer_id', $programmer->id);
        $myRole = $myTeamMember ? $myTeamMember->role : null;

        // أعضاء الفريق بأدوارهم
        $teamMembers = $myTeam->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            return [
                'id' => $prog->id,
                'name' => $prog->user->full_name,
                'username' => $prog->user_name,
                'avatar_url' => $prog->avatar_url,
                'role_in_team' => $member->role,
                'specialization' => $prog->track ?? 'general',
                'joined_at' => $member->joined_at,
            ];
        });

        // مهام المبرمج الشخصية في هذا المشروع
        $myTasks = $myTeam->tasks()
            ->where('programmer_id', $programmer->id)
            ->with('programmer.user')
            ->orderBy('priority', 'desc')
            ->orderBy('deadline')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'complexity' => $task->complexity,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'deadline' => $task->deadline,
                    'git_link' => $task->git_link,
                    'tags' => $task->tags,
                    'progress_percentage' => $task->progress_percentage,
                    'created_at' => $task->created_at,
                ];
            });

        // (اختياري) مهام جميع أعضاء الفريق إذا أرسل ?include_team_tasks=true
        $includeTeamTasks = $request->boolean('include_team_tasks', false);
        $teamTasks = null;

        if ($includeTeamTasks) {
            $teamTasks = $myTeam->tasks()
                ->with('programmer.user')
                ->orderBy('priority', 'desc')
                ->orderBy('deadline')
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'programmer' => [
                            'id' => $task->programmer->id,
                            'name' => $task->programmer->user->full_name,
                            'username' => $task->programmer->user_name,
                        ],
                        'deadline' => $task->deadline,
                        'git_link' => $task->git_link,
                        'tags' => $task->tags,
                        'progress_percentage' => $task->progress_percentage,
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'category' => $project->category_name,
                    'difficulty' => $project->difficulty,
                    'status' => $project->status,
                    'expected_end_date' => $project->expected_end_date->toDateString(),
                    'completion_percentage' => $project->completion_percentage,
                ],
                'my_role_in_team' => $myRole,
                'team' => [
                    'id' => $myTeam->id,
                    'name' => $myTeam->name,
                    'description' => $myTeam->description,
                    'members_count' => $teamMembers->count(),
                    'members' => $teamMembers,
                ],
                'my_tasks' => $myTasks,
                'team_tasks' => $teamTasks, // null unless ?include_team_tasks=true
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error in myProjectDetails: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch project details'
        ], 500);
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
                'data' => $project->load('skills')
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
                        'avatar_url' => $programmer->avatar_url,
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
                    ];
                });
            } elseif ($user->role === 'company') {
                $projects = Project::where('user_id', $userId)->get();
            } elseif ($user->role === 'admin') {
                $projects = Project::with('user')->get();
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

    public function myProjects(Request $request) // تأكد من إضافة Request
{
    $user = Auth::user();
    if (!$user || $user->role !== 'programmer') {
        return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
    }

    $programmer = $user->programmer;
    if (!$programmer) {
        return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
    }

    $statusFilter = $request->query('status'); // 'ongoing' أو 'completed'

    // جلب كل مشاريع المبرمج عبر الفرق
    $allProjects = Project::whereHas('teams.activeMembers', function($q) use ($programmer) {
        $q->where('programmer_id', $programmer->id);
    })->with(['teams.tasks'])->get();

    $ongoingProjects = [];
    $completedProjects = [];

    foreach ($allProjects as $project) {
        // حساب تقدم المبرمج في هذا المشروع
        $myTasks = $project->teams->flatMap->tasks->where('programmer_id', $programmer->id);
        $completedMyTasks = $myTasks->where('status', 'done')->count();
        $myCompletion = $myTasks->isEmpty() ? 0 : round(($completedMyTasks / $myTasks->count()) * 100);

        $projectData = [
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'category' => $project->category_name,
            'estimated_duration_days' => $project->estimated_duration_days,
            'expected_end_date' => $project->expected_end_date->toDateString(),
            'project_completion_percentage' => $project->completion_percentage,
            'my_completion_percentage' => $myCompletion,
            'my_specialization' => $programmer->track ?? 'general',
        ];

        if ($project->status === 'ongoing') {   // المقارنة مع 'ongoing'
            $ongoingProjects[] = $projectData;
        } else {
            $projectData['completion_date'] = $project->updated_at->toDateString();
            $completedProjects[] = $projectData;
        }
    }

    // تطبيق الفلتر بناءً على الـ status المطلوب
    if ($statusFilter === 'ongoing') {
        return response()->json([
            'success' => true,
            'data' => $ongoingProjects,
        ]);
    } elseif ($statusFilter === 'completed') {
        return response()->json([
            'success' => true,
            'data' => $completedProjects,
        ]);
    } else {
        return response()->json([
            'success' => true,
            'data' => [
                'ongoing_projects' => $ongoingProjects,
                'completed_projects' => $completedProjects,
            ],
        ]);
    }
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
}
