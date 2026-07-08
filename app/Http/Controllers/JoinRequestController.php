<?php
namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\JoinRequest;
use App\Models\Programmer;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\FCM\PushNotify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JoinRequestController extends Controller
{
public function show($joinRequestId)
{
    try {
        $user = Auth::user();
        if ($user->role !== 'programmer') {
            return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }
        $joinRequest = JoinRequest::with(['programmer.user', 'programmer.skills', 'team.project'])
            ->find($joinRequestId);
        if (!$joinRequest) {
            return response()->json(['success' => false, 'message' => 'Join request not found'], 404);
        }
        $isLeader = TeamMember::where('team_id', $joinRequest->team_id)
            ->where('programmer_id', $programmer->id)
            ->where('role', 'leader')
            ->whereNull('left_at')
            ->exists();
        if (!$isLeader) {
            return response()->json(['success' => false, 'message' => 'Only team leader can view this request'], 403);
        }
        $prog = $joinRequest->programmer;
        $project = $joinRequest->team?->project;
        $avgStars = $this->calculateAverageStars($prog->id);
        return response()->json([
            'success' => true,
            'data' => [
                'join_request_id' => $joinRequest->id,
                'status' => $joinRequest->status,
                'created_at' => $joinRequest->created_at,
                'responded_at' => $joinRequest->responded_at,
                'programmer' => [
                    'programmer_id' => $prog->id,
                    'name' => $prog->user?->full_name ?? 'Unknown',
                    'username' => $prog->user?->user_name ?? 'unknown',
                    'avatar_url' => $prog->avatar_url 
                        ? asset('storage/' . $prog->avatar_url) 
                        : null,
                    'track' => $prog->track ?? 'general',
                    'bio' => $prog->bio ?? null,
                    'skills' => $prog->skills?->pluck('name') ?? [],
                    'average_stars' => $avgStars['stars'],
                ],
                'project' => [
                    'project_id' => $project?->id,
                    'name' => $project?->title ?? 'Unknown Project',
                    'description' => $project?->description ?? null,
                ],
                'team' => [
                    'team_id' => $joinRequest->team?->id,
                    'name' => $joinRequest->team?->name,
                ],
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching join request: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch join request: ' . $e->getMessage()], 500);
    }
} 
    public function storeByProject(Request $request, $projectId)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if ($user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can send join requests'], 403);
            }
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }
            $project = Project::with('teams')->find($projectId);
            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found'], 404);
            }
            $team = $project->teams->first();
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
            }
            if (isset($team->status) && $team->status !== 'active') {
                return response()->json(['success' => false, 'message' => 'Team is not active'], 400);
            }
            if (isset($team->is_public) && !$team->is_public) {
                return response()->json(['success' => false, 'message' => 'This team is private'], 400);
            }
            $currentMembers = TeamMember::where('team_id', $team->id)
                ->whereNull('left_at')
                ->count();
            if (isset($team->max_members) && $currentMembers >= $team->max_members) {
                return response()->json(['success' => false, 'message' => 'Team is full'], 400);
            }
            $isMember = TeamMember::where('team_id', $team->id)
                ->where('programmer_id', $programmer->id)
                ->whereNull('left_at')
                ->exists(); 
            if ($isMember) {
                return response()->json(['success' => false, 'message' => 'You are already a member of this team'], 400);
            }
            $existing = JoinRequest::where('team_id', $team->id)
                ->where('programmer_id', $programmer->id)
                ->where('status', 'pending')
                ->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'You already have a pending join request'], 400);
            }
            $joinRequest = JoinRequest::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'status' => 'pending',
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Join request sent successfully',
                'data' => [
                    'join_request_id' => $joinRequest->id,
                    'team_id' => $team->id,
                    'project_id' => (int) $projectId,
                    'project_name' => $project->title,
                    'status' => 'pending',
                    'created_at' => $joinRequest->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending join request: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to send join request: ' . $e->getMessage()
            ], 500);
        }
    }
    public function myJoinRequests(Request $request)
{
    try {
        $user = Auth::user();
        if ($user->role !== 'programmer') {
            return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }
        $leaderTeamIds = TeamMember::where('programmer_id', $programmer->id)
            ->where('role', 'leader')
            ->whereNull('left_at')
            ->pluck('team_id');
        if ($leaderTeamIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'You are not a leader of any team'
            ]);
        }
        $status = $request->query('status', 'all');  
        $joinRequests = JoinRequest::with(['programmer.user', 'programmer.skills', 'team.project'])
            ->whereIn('team_id', $leaderTeamIds)
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($joinRequest) {
                $prog = $joinRequest->programmer;
                $project = $joinRequest->team?->project;
                return [
                    'join_request_id' => $joinRequest->id,
                    'status' => $joinRequest->status,
                    'responded_at' => $joinRequest->responded_at,                    
                    'programmer' => [
                        'programmer_id' => $prog->id,
                        'name' => $prog->user?->full_name ?? 'Unknown',
                        'username' => $prog->user?->user_name ?? 'unknown',
                        'avatar_url' => $prog->avatar_url 
                            ? asset('storage/' . $prog->avatar_url) 
                            : null,
                        'track' => $prog->track ?? 'general',
                        'bio' => $prog->bio ?? null,
                        'skills' => $prog->skills?->pluck('name') ?? [],
                    ],                    
                    'project' => [
                        'project_id' => $project?->id,
                        'name' => $project?->title ?? 'Unknown Project',
                        'description' => $project?->description ?? null,
                    ],                    
                    'team' => [
                        'team_id' => $joinRequest->team?->id,
                        'name' => $joinRequest->team?->name,
                    ],
                ];
            });
        return response()->json([
            'success' => true,
            'data' => $joinRequests,
            'count' => $joinRequests->count(),
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching join requests: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch join requests: ' . $e->getMessage()], 500);
    }
}
    public function respond(Request $request, $joinRequestId)
{
    DB::beginTransaction();
    try {
        $user = Auth::user();
        if ($user->role !== 'programmer') {
            return response()->json(['success' => false, 'message' => 'Only programmers can respond'], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }
        $joinRequest = JoinRequest::with(['team', 'programmer.user'])->find($joinRequestId);
        if (!$joinRequest) {
            return response()->json(['success' => false, 'message' => 'Join request not found'], 404);
        }
        if (!$joinRequest->team->isLeader($programmer->id)) {
            return response()->json(['success' => false, 'message' => 'Only team leader can respond'], 403);
        }
        if ($joinRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'This request has already been ' . $joinRequest->status], 400);
        }
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',  
        ]);
        $status = $validated['status'];
        if ($status === 'approved') {  
            if (!$joinRequest->team->hasVacancy()) {
                return response()->json(['success' => false, 'message' => 'Team is now full'], 400);
            }
            TeamMember::create([
                'team_id' => $joinRequest->team_id,
                'programmer_id' => $joinRequest->programmer_id,
                'role' => 'member',
                'joined_at' => now(),
                'joined_by' => $programmer->id,
            ]);
        } else {
        }
        $joinRequest->update([
            'status' => $status,
            'responded_at' => now(),
            'responded_by' => $programmer->id,
        ]);
        DB::commit();
        return response()->json([
            'success' => true,
            'message' => "Join request {$status} successfully",
            'data' => [
                'join_request_id' => $joinRequest->id,
                'status' => $status,
                'programmer_name' => $joinRequest->programmer->user?->full_name,
                'team_name' => $joinRequest->team->name,
            ]
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error responding to join request: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to respond: ' . $e->getMessage()], 500);
    }
}
    private function calculateAverageStars($programmerId)
    {
        $evaluations = Evaluation::where('evaluated_id', $programmerId)
            ->whereNotNull('average_score')
            ->get();
        $total = $evaluations->count();
        if ($total === 0) {
            return [
                'stars' => 0,
            ];
        }
        $avgScore = $evaluations->avg('average_score');
        $starsOutOf5 = round($avgScore / 2, 1);
        return [
            'stars' => $starsOutOf5,
        ];
    }
    public function store(Request $request, Team $team)
    {
        try {
            $user = Auth::user();
            if ($user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can send join requests'], 403);
            }
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }
            if ($team->isMember($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'You are already a member of this team'], 400);
            }
            $existing = JoinRequest::where('team_id', $team->id)
                ->where('programmer_id', $programmer->id)
                ->where('status', 'pending')
                ->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'You already have a pending request for this team'], 400);
            }
            DB::beginTransaction();
            $joinRequest = JoinRequest::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'status' => 'pending',
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Join request sent successfully',
                'data' => $joinRequest
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending join request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send join request'], 500);
        }
    }
    public function index()
    {
        try {
            $programmer = Auth::user()->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }
            $joinRequests = $programmer->joinRequests()->with('team')->get();
            return response()->json(['success' => true, 'data' => $joinRequests]);
        } catch (\Exception $e) {
            Log::error('Error fetching join requests: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch join requests'], 500);
        }
    }
    public function teamJoinRequests(Team $team)
    {
        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }
            if (!$team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only the team leader can view join requests'], 403);
            }
            $joinRequests = $team->joinRequests()
                ->with('programmer.user')
                ->where('status', 'pending')
                ->get();
            return response()->json(['success' => true, 'data' => $joinRequests]);
        } catch (\Exception $e) {
            Log::error('Error fetching team join requests: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team join requests'], 500);
        }
    }
    public function update(Request $request, JoinRequest $joinRequest)
{
    try {
        $user = Auth::user();
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }
        $team = $joinRequest->team;
        if (!$team->isLeader($programmer->id)) {
            return response()->json(['success' => false, 'message' => 'Only the team leader can approve or reject join requests'], 403);
        }
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',  
            'rejection_reason' => 'nullable|string|max:255',
        ]);
        if ($joinRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'This request has already been processed'], 400);
        }
        DB::beginTransaction();
        if ($validated['status'] === 'accepted') {  
            $team->members()->attach($joinRequest->programmer_id, [
                'role' => 'member',
                'joined_at' => now(),
                'joined_by' => $programmer->id,
            ]);
            $joinRequest->update(['status' => 'accepted']);  
            $message = 'Join request accepted. You are now a member of the team.';  
        } else {
            $joinRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'] ?? null
            ]);
            $message = 'Join request rejected.';
        }
        DB::commit();
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $joinRequest->fresh()
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error updating join request: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to update join request'], 500);
    }
}
}
