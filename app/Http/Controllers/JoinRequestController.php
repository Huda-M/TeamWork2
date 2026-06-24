<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\JoinRequest;
use App\Models\Programmer;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Notifications\JoinRequestAcceptedNotification;
use App\Notifications\JoinRequestRejectedNotification;
use App\Notifications\NewJoinRequestNotification;
use App\Services\FCM\PushNotify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JoinRequestController extends Controller
{
    /**
     * ✅ NEW: إرسال طلب انضمام باستخدام project_id
     */
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

        // جلب المشروع والتيم المرتبط
        $project = Project::with('teams')->find($projectId);
        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        $team = $project->teams->first();
        if (!$team) {
            return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
        }

        // ✅ FIXED: Check status بأمان
        if (isset($team->status) && $team->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Team is not active'], 400);
        }

        // ✅ FIXED: Check is_public بأمان
        if (isset($team->is_public) && !$team->is_public) {
            return response()->json(['success' => false, 'message' => 'This team is private'], 400);
        }

        // ✅ FIXED: Check vacancy يدوياً
        $currentMembers = TeamMember::where('team_id', $team->id)
            ->whereNull('left_at')
            ->count();
        
        if (isset($team->max_members) && $currentMembers >= $team->max_members) {
            return response()->json(['success' => false, 'message' => 'Team is full'], 400);
        }

        // ✅ FIXED: Check membership يدوياً
        $isMember = TeamMember::where('team_id', $team->id)
            ->where('programmer_id', $programmer->id)
            ->whereNull('left_at')
            ->exists();
            
        if ($isMember) {
            return response()->json(['success' => false, 'message' => 'You are already a member of this team'], 400);
        }

        // التحقق من وجود request سابق
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
            'message' => $request->message ?? null,
        ]);

        // ✅ FIXED: إشعار لليدر بأمان (بدون $team->leader)
        $leaderMember = TeamMember::where('team_id', $team->id)
            ->where('role', 'leader')
            ->whereNull('left_at')
            ->with('programmer.user')
            ->first();
            
        if ($leaderMember && $leaderMember->programmer && $leaderMember->programmer->user) {
            $leader = $leaderMember->programmer;
            $leader->user->notify(new NewJoinRequestNotification($joinRequest, $programmer));
            
            if ($leader->user->fcm_token) {
                $pushNotify = new PushNotify;
                $pushNotify->sendPushNotification(
                    $leader->user->fcm_token,
                    'New Join Request',
                    "{$programmer->user->full_name} wants to join your team '{$team->name}'",
                    ['join_request_id' => $joinRequest->id, 'project_id' => $projectId]
                );
            }
        }

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

        // جلب التيمات اللي المبرمج هو ليدر فيها
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

        $status = $request->query('status', 'pending');

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

                // حساب متوسط النجوم فقط
                $avgStars = $this->calculateAverageStars($prog->id);

                return [
                    'join_request_id' => $joinRequest->id,
                    'status' => $joinRequest->status,
                    'created_at' => $joinRequest->created_at,
                    'responded_at' => $joinRequest->responded_at,
                    
                    // ✅ بيانات المبرمج (بدون total_evaluations و stars_percentage)
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
                    
                    // ✅ بيانات المشروع
                    'project' => [
                        'project_id' => $project?->id,
                        'name' => $project?->title ?? 'Unknown Project',
                        'description' => $project?->description ?? null,
                    ],
                    
                    // ✅ بيانات التيم
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

    /**
     * ✅ NEW: الليدر يقبل أو يرفض join request
     */
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

            // التحقق إن المبرمج هو ليدر التيم
            if (!$joinRequest->team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only team leader can respond'], 403);
            }

            if ($joinRequest->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'This request has already been ' . $joinRequest->status], 400);
            }

            $validated = $request->validate([
                'status' => 'required|in:accepted,rejected',
            ]);

            $status = $validated['status'];

            if ($status === 'accepted') {
                // التحقق من المساحة
                if (!$joinRequest->team->hasVacancy()) {
                    return response()->json(['success' => false, 'message' => 'Team is now full'], 400);
                }

                // إضافة العضو
                TeamMember::create([
                    'team_id' => $joinRequest->team_id,
                    'programmer_id' => $joinRequest->programmer_id,
                    'role' => 'member',
                    'joined_at' => now(),
                    'joined_by' => $programmer->id,
                ]);

                // إشعار للمبرمج
                $joinRequest->programmer->user?->notify(new JoinRequestAcceptedNotification($joinRequest->team));
                
                if ($joinRequest->programmer->user?->fcm_token) {
                    $pushNotify = new PushNotify;
                    $pushNotify->sendPushNotification(
                        $joinRequest->programmer->user->fcm_token,
                        'Join Request Accepted',
                        "You have been accepted to join '{$joinRequest->team->name}'",
                        ['team_id' => $joinRequest->team_id]
                    );
                }

            } else {
                // rejected
                $joinRequest->programmer->user?->notify(new JoinRequestRejectedNotification($joinRequest->team));
                
                if ($joinRequest->programmer->user?->fcm_token) {
                    $pushNotify = new PushNotify;
                    $pushNotify->sendPushNotification(
                        $joinRequest->programmer->user->fcm_token,
                        'Join Request Rejected',
                        "Your request to join '{$joinRequest->team->name}' was rejected",
                        ['team_id' => $joinRequest->team_id]
                    );
                }
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

    // average_score من 10، نحول لـ 5
    $avgScore = $evaluations->avg('average_score');
    $starsOutOf5 = round($avgScore / 2, 1);

    return [
        'stars' => $starsOutOf5,
    ];
}

    /**
     * ⬇️ OLD: إرسال طلب انضمام إلى فريق (by team_id) - سيبه زي ما هو
     */
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

    /**
     * ⬇️ OLD: عرض طلبات الانضمام التي أرسلها المبرمج الحالي
     */
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

    /**
     * ⬇️ OLD: عرض طلبات الانضمام لفريق معين (للقائد فقط)
     */
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

    /**
     * ⬇️ OLD: قبول أو رفض طلب الانضمام (للقائد فقط)
     */
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
                'status' => 'required|in:approved,rejected',
                'rejection_reason' => 'nullable|string|max:255',
            ]);

            if ($joinRequest->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'This request has already been processed'], 400);
            }

            DB::beginTransaction();

            if ($validated['status'] === 'approved') {
                $team->members()->attach($joinRequest->programmer_id, [
                    'role' => 'member',
                    'joined_at' => now(),
                    'joined_by' => $programmer->id,
                ]);
                $joinRequest->update(['status' => 'approved']);
                $message = 'Join request approved. You are now a member of the team.';
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
