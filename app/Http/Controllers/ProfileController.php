<?php
namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Evaluation;
use App\Models\Skill;
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
    public function myStats()
    {
        $user = Auth::user();
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer not found'], 404);
        }
        $teamsCount = $programmer->teams()
            ->wherePivotNull('left_at')
            ->count();
        $completedTasks = $programmer->tasks()
            ->where('status', 'done')
            ->count();
        $incompleteTasks = $programmer->tasks()
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->count();
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
    public function teamMembersToEvaluate($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;
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
    try {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    } catch (\Exception $e) {
        Log::warning('Token deletion failed: ' . $e->getMessage());
    }
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
    public function zeroProject($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;
        $project = Project::with(['teams.activeMembers.programmer', 'teams.tasks'])
            ->findOrFail($projectId);
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
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can update their profile'
            ], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }
        $fullName = $request->input('full_name');
        $email = $request->input('email');
        $userName = $request->input('user_name');
        $bio = $request->input('bio');
        $track = $request->input('track');
        Log::info('Profile update - form-data/JSON', [
            'full_name' => $fullName,
            'email' => $email,
            'user_name' => $userName,
            'bio' => $bio,
            'track' => $track,
            'has_file' => $request->hasFile('avatar'),
            'content_type' => $request->header('Content-Type'),
        ]);
        $rules = [
            'full_name' => 'sometimes|required|string|max:255',
            'bio'       => 'nullable|string|max:1000',
            'track'     => 'nullable|string|max:100',
            'avatar'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
        if ($userName !== null && $userName !== $programmer->user_name) {
            $rules['user_name'] = [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('programmers', 'user_name')
                    ->ignore($programmer->id)
            ];
        } elseif ($userName !== null) {
            $rules['user_name'] = 'sometimes|string|max:255';
        }
        if ($email !== null && $email !== $user->email) {
            $rules['email'] = [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('users', 'email')
                    ->ignore($user->id)
            ];
        } elseif ($email !== null) {
            $rules['email'] = 'sometimes|email';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $userUpdated = false;
        if ($fullName !== null && $fullName !== $user->full_name) {
            $user->full_name = $fullName;
            $userUpdated = true;
        }
        if ($email !== null && $email !== $user->email) {
            $user->email = $email;
            $userUpdated = true;
        }
        if ($userUpdated) {
            $user->save();
            Log::info('User updated successfully');
        }
        $programmerUpdated = false;
        if ($userName !== null && $userName !== $programmer->user_name) {
            $programmer->user_name = $userName;
            $programmerUpdated = true;
        }
        if ($bio !== null && $bio !== $programmer->bio) {
            $programmer->bio = $bio;
            $programmerUpdated = true;
        }
        if ($track !== null && $track !== $programmer->track) {
            $programmer->track = $track;
            $programmerUpdated = true;
        }
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file->isValid()) {
                if ($programmer->avatar_url && str_contains($programmer->avatar_url, '/storage/')) {
                    $oldPath = str_replace('/storage/', '', $programmer->avatar_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $fileName = 'avatar_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('avatars', $fileName, 'public');
                $programmer->avatar_url = $path;
                $programmerUpdated = true;
                Log::info('Avatar uploaded successfully: ' . $path);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image file'
                ], 400);
            }
        }
        if (!$programmerUpdated && !$userUpdated && !$request->hasFile('avatar')) {
            Log::info('No changes detected');
            return response()->json([
                'success' => true,
                'message' => 'No changes detected',
                'data' => [
                    'id' => $programmer->id,
                    'user_name' => $programmer->user_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'bio' => $programmer->bio,
                    'track' => $programmer->track,
                    'avatar_url' => $programmer->avatar_url ? Storage::disk('public')->url($programmer->avatar_url) : null,
                ]
            ], 200);
        }
        if ($programmerUpdated) {
            $programmer->save();
            Log::info('Programmer updated successfully');
        }
        $programmer->refresh();
        $user->refresh();
        $avatarUrl = $programmer->avatar_url
            ? Storage::disk('public')->url($programmer->avatar_url)
            : null;
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id'         => $programmer->id,
                'user_name'  => $programmer->user_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'bio'        => $programmer->bio,
                'track'      => $programmer->track,
                'avatar_url' => $avatarUrl,
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Profile update error: ' . $e->getMessage(), [
            'user_id' => Auth::id(),
            'request_data' => $request->all(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating profile',
            'error'   => $e->getMessage()
        ], 500);
    }
}
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
public function getSkillsAndExperience()
{
    try {
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
        $skills = $programmer->skills()->pluck('name')->toArray();
        $experience = $programmer->bio; 
        return response()->json([
            'success' => true,
            'data' => [
                'experience_level' => $programmer->experience_level,  
                'skills' => $skills,  
                'experience' => $experience,  
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Get skills/experience error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch skills and experience'
        ], 500);
    }
}
public function updateSkillsAndExperience(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can update'
            ], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }
        $validated = $request->validate([
            'skills' => 'nullable|array',           
            'skills.*' => 'string|max:50',
            'experience' => 'nullable|string|max:5000',
        ]);
        if (isset($validated['skills'])) {
            $skillIds = [];
            foreach ($validated['skills'] as $skillName) {
                $skill = Skill::firstOrCreate(['name' => trim($skillName)]);
                $skillIds[] = $skill->id;
            }
            $programmer->skills()->sync($skillIds);
        }
        if (isset($validated['experience'])) {
            $programmer->bio = $validated['experience']; 
            $programmer->save();
        }
        return response()->json([
            'success' => true,
            'message' => 'Skills and experience updated successfully',
            'data' => [
                'skills' => $programmer->skills()->pluck('name')->toArray(),
                'experience' => $programmer->bio, 
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Update skills/experience error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update skills and experience'
        ], 500);
    }
}
}
