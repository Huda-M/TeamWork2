<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Programmer;
use App\Http\Requests\StoreProgrammerRequest;
use App\Http\Requests\UpdateProgrammerRequest;

class ProgrammerController extends Controller
{
    public function index()
    {
        $programmers = Programmer::with('user')->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Programmer list fetched successfully',
            'data' => $programmers
        ]);
    }


// ...

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

    // 1. جميع المهام الموكلة للمبرمج
    $totalTasks = $programmer->tasks()->count();
    $completedTasks = $programmer->tasks()->where('status', 'done')->count();
    $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

    // 2. المشاريع التي شارك فيها المبرمج (عبر الفرق)
    $projects = $programmer->teams()
        ->whereNull('team_members.left_at')
        ->with('project')
        ->get()
        ->pluck('project')
        ->unique('id');

    $totalProjects = $projects->count();

    // 3. تفاصيل كل مشروع: عدد المهام، المهام المكتملة، النسبة
    $projectsStats = [];
    foreach ($projects as $project) {
        // المهام الخاصة بهذا المبرمج داخل هذا المشروع فقط
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

public function programmerStatistics($id)
{
    $programmer = Programmer::with('user')->find($id);
    if (!$programmer) {
        return response()->json(['success' => false, 'message' => 'Programmer not found'], 404);
    }

    // نفس الكود أعلاه لكن باستخدام $programmer بدلاً من Auth::user()->programmer
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
}
