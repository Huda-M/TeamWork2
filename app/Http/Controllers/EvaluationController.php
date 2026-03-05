<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Project;
use App\Models\Team;
use App\Models\Programmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EvaluationController extends Controller
{
    public function index($projectId)
    {
        try {
            $project = Project::findOrFail($projectId);

            $evaluations = Evaluation::where('project_id', $projectId)
                ->with(['evaluator.user', 'evaluated.user'])
                ->get()
                ->groupBy('evaluated_id')
                ->map(function ($evals) {
                    $first = $evals->first();
                    return [
                        'programmer' => [
                            'id' => $first->evaluated->id,
                            'name' => $first->evaluated->user->name,
                            'username' => $first->evaluated->user->user_name,
                        ],
                        'evaluations_count' => $evals->count(),
                        'average_scores' => [
                            'technical_skills' => round($evals->avg('technical_skills'), 2),
                            'communication' => round($evals->avg('communication'), 2),
                            'teamwork' => round($evals->avg('teamwork'), 2),
                            'problem_solving' => round($evals->avg('problem_solving'), 2),
                            'reliability' => round($evals->avg('reliability'), 2),
                            'code_quality' => round($evals->avg('code_quality'), 2),
                            'overall' => round($evals->avg('average_score'), 2),
                        ],
                        'feedbacks' => $evals->pluck('feedback')->filter(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $evaluations,
                'message' => 'Evaluations fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching evaluations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch evaluations'
            ], 500);
        }
    }

    public function startEvaluation($projectId, $teamId)
    {
        try {
            $project = Project::findOrFail($projectId);
            $team = Team::findOrFail($teamId);

            $user = Auth::user();
            $programmer = $user->programmer;

            if (!$team->isMember($programmer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            if ($project->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Project is not completed yet'
                ], 400);
            }

            $existingEvaluations = Evaluation::where('project_id', $projectId)
                ->where('team_id', $teamId)
                ->where('evaluator_id', $programmer->id)
                ->exists();

            if ($existingEvaluations) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted evaluations for this project'
                ], 400);
            }

            $membersToEvaluate = $team->activeMembers()
                ->where('programmer_id', '!=', $programmer->id)
                ->with('programmer.user')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Evaluation started',
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'title' => $project->title
                    ],
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name
                    ],
                    'members_to_evaluate' => $membersToEvaluate->map(function($member) {
                        return [
                            'id' => $member->programmer->id,
                            'name' => $member->programmer->user->name,
                            'username' => $member->programmer->user->user_name
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error starting evaluation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start evaluation'
            ], 500);
        }
    }

    public function store(Request $request, $projectId, $teamId)
    {
        $validator = Validator::make($request->all(), [
            'evaluated_id' => 'required|exists:programmers,id',
            'technical_skills' => 'required|integer|min:1|max:10',
            'communication' => 'required|integer|min:1|max:10',
            'teamwork' => 'required|integer|min:1|max:10',
            'problem_solving' => 'required|integer|min:1|max:10',
            'reliability' => 'required|integer|min:1|max:10',
            'code_quality' => 'required|integer|min:1|max:10',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'feedback' => 'nullable|string',
            'is_anonymous' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $project = Project::findOrFail($projectId);
            $team = Team::findOrFail($teamId);

            $user = Auth::user();
            $evaluator = $user->programmer;

            if (!$team->isMember($evaluator->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            if ($evaluator->id == $request->evaluated_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot evaluate yourself'
                ], 400);
            }

            if (!$team->isMember($request->evaluated_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The evaluated programmer is not in this team'
                ], 400);
            }

            $existing = Evaluation::where('project_id', $projectId)
                ->where('team_id', $teamId)
                ->where('evaluator_id', $evaluator->id)
                ->where('evaluated_id', $request->evaluated_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already evaluated this programmer'
                ], 400);
            }

            $average = (
                $request->technical_skills +
                $request->communication +
                $request->teamwork +
                $request->problem_solving +
                $request->reliability +
                $request->code_quality
            ) / 6;

            $evaluation = Evaluation::create([
                'project_id' => $projectId,
                'team_id' => $teamId,
                'evaluator_id' => $evaluator->id,
                'evaluated_id' => $request->evaluated_id,
                'technical_skills' => $request->technical_skills,
                'communication' => $request->communication,
                'teamwork' => $request->teamwork,
                'problem_solving' => $request->problem_solving,
                'reliability' => $request->reliability,
                'code_quality' => $request->code_quality,
                'average_score' => round($average, 2),
                'strengths' => $request->strengths,
                'areas_for_improvement' => $request->areas_for_improvement,
                'feedback' => $request->feedback,
                'is_anonymous' => $request->is_anonymous ?? true,
                'is_completed' => true,
                'submitted_at' => now()
            ]);

            $evaluated = Programmer::find($request->evaluated_id);
            $bonusPoints = $average * 10;
            $evaluated->addScore($bonusPoints, 'Received peer evaluation', [
                'project_id' => $projectId,
                'average_score' => $average
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evaluation submitted successfully',
                'data' => $evaluation
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error submitting evaluation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit evaluation'
            ], 500);
        }
    }

    public function myEvaluationsAsEvaluator()
    {
        try {
            $programmer = Auth::user()->programmer;

            $evaluations = Evaluation::where('evaluator_id', $programmer->id)
                ->with(['project', 'team', 'evaluated.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $evaluations
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching my evaluations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch evaluations'
            ], 500);
        }
    }

    public function myEvaluationsAsEvaluated()
    {
        try {
            $programmer = Auth::user()->programmer;

            $evaluations = Evaluation::where('evaluated_id', $programmer->id)
                ->with(['project', 'team', 'evaluator.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $evaluations
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching received evaluations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch evaluations'
            ], 500);
        }
    }

    public function programmerStats($programmerId)
    {
        try {
            $evaluations = Evaluation::where('evaluated_id', $programmerId)->get();

            if ($evaluations->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_evaluations' => 0,
                        'average_scores' => null
                    ]
                ]);
            }

            $stats = [
                'total_evaluations' => $evaluations->count(),
                'average_scores' => [
                    'technical_skills' => round($evaluations->avg('technical_skills'), 2),
                    'communication' => round($evaluations->avg('communication'), 2),
                    'teamwork' => round($evaluations->avg('teamwork'), 2),
                    'problem_solving' => round($evaluations->avg('problem_solving'), 2),
                    'reliability' => round($evaluations->avg('reliability'), 2),
                    'code_quality' => round($evaluations->avg('code_quality'), 2),
                    'overall' => round($evaluations->avg('average_score'), 2),
                ],
                'recent_feedback' => $evaluations->sortByDesc('created_at')
                    ->take(5)
                    ->pluck('feedback')
                    ->filter()
                    ->values()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching programmer stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}
