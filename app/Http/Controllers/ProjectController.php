<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Skill;
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

    public function show($id)
    {
        try {
            $project = Project::with(['skills', 'user', 'teams' => function($q) {
                $q->active()->withCount('activeMembers');
            }])->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            $totalTeams = $project->teams()->count();
            $activeTeams = $project->teams()->active()->count();
            $totalMembers = $project->teams()->withCount('activeMembers')->get()->sum('active_members_count');

            return response()->json([
                'success' => true,
                'message' => 'Project fetched successfully',
                'data' => [
                    'project' => $project,
                    'stats' => [
                        'total_teams' => $totalTeams,
                        'active_teams' => $activeTeams,
                        'total_members' => $totalMembers,
                        'available_teams_slots' => ($project->max_teams * $project->team_size) - $totalMembers
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing project: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to show project'
            ], 500);
        }
    }

    public function store(StoreProjectRequest $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $project = Project::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'difficulty' => $validated['difficulty'],
                'estimated_duration_days' => $validated['estimated_duration_days'],
                'max_teams' => $validated['max_teams'] ?? 5,
                'team_size' => $validated['team_size'] ?? 10,
                'user_id' => auth()->id()
            ]);

            if ($request->has('skills')) {
                $project->skills()->attach($validated['skills']);
            }

            Log::info('Project created', [
                'project_id' => $project->id,
                'created_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project->load('skills')
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

    public function getUserProjects($userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $projects = collect();

            if ($user->role === 'programmer' && $user->programmer) {
                $projects = Project::whereHas('teams.activeMembers', function($q) use ($user) {
                    $q->where('programmer_id', $user->programmer->id);
                })->with(['teams' => function($q) use ($user) {
                    $q->whereHas('activeMembers', function($subQ) use ($user) {
                        $subQ->where('programmer_id', $user->programmer->id);
                    });
                }])->get();
            }
            elseif ($user->role === 'company') {
                $projects = Project::where('created_by', $userId)->get();
            }
            elseif ($user->role === 'admin') {
                $projects = Project::with('creator')->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->user_name,
                        'role' => $user->role,
                    ],
                    'projects' => $projects,
                    'total_projects' => $projects->count(),
                ],
                'message' => 'User projects fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user projects: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user projects'
            ], 500);
        }
    }
}
