<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Programmer;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamMatchingService;
use App\Services\AITeamRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    protected $teamMatchingService;
    protected $aiRecommendationService;

    public function __construct(TeamMatchingService $teamMatchingService,
        AITeamRecommendationService $aiRecommendationService)
    {
        $this->teamMatchingService = $teamMatchingService;
        $this->aiRecommendationService = $aiRecommendationService;
    }

    // ================== دوال عامة (بدون تصويت) ==================

    public function index(Request $request)
    {
        try {
            $query = Team::query()->with(['project', 'leader.programmer.user']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('project_id')) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->has('experience_level')) {
                $query->where('experience_level', $request->experience_level);
            }

            if ($request->has('is_public')) {
                $query->where('is_public', $request->boolean('is_public'));
            }

            $teams = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'Teams fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching teams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teams'
            ], 500);
        }
    }

    public function showTeam($id)
    {
        try {
            $team = Team::with([
                'project',
                'activeMembers.programmer.user',
                'leader.programmer.user',
                'invitations' => function($q) {
                    $q->where('status', 'pending');
                }
            ])->find($id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'team' => $team,
                    'members_count' => $team->activeMembers()->count(),
                    'available_slots' => $team->max_members - $team->activeMembers()->count(),
                    'is_full' => !$team->hasVacancy(),
                    'stage' => $team->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing team: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to show team'
            ], 500);
        }
    }

    public function getTeamDetails($id)
{
    try {
        $team = Team::with(['project', 'activeMembers.programmer.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'project_title' => $team->project->title,
                'project_description' => $team->project->description,
                'members' => $team->activeMembers->map(function($member) {
                    return [
                        'programmer_id' => $member->programmer_id,
                        'name' => $member->programmer->user->full_name,
                        'role' => $member->role, // 'leader' or 'member'
                    ];
                })
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching team details: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
    }
}

public function swapLeader(Request $request, $teamId, $programmerId)
{
    try {
        $user = Auth::user();
        $currentLeader = $user->programmer;

        $team = Team::findOrFail($teamId);

        // التحقق من أن المستخدم الحالي هو قائد الفريق أو أدمن عام
        if (!$team->isLeader($currentLeader->id) && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $newLeader = Programmer::findOrFail($programmerId);

        // التأكد أن المبرمج الجديد عضو في الفريق
        if (!$team->isMember($newLeader->id)) {
            return response()->json(['success' => false, 'message' => 'Programmer is not a member of this team'], 400);
        }

        DB::transaction(function () use ($team, $currentLeader, $newLeader) {
            // جعل القائد الحالي member
            TeamMember::where('team_id', $team->id)
                      ->where('programmer_id', $currentLeader->id)
                      ->update(['role' => 'member']);

            // جعل العضو الجديد leader
            TeamMember::where('team_id', $team->id)
                      ->where('programmer_id', $newLeader->id)
                      ->update(['role' => 'leader']);
        });

        return response()->json([
            'success' => true,
            'message' => "Leader role transferred from {$currentLeader->user->full_name} to {$newLeader->user->full_name}"
        ]);
    } catch (\Exception $e) {
        Log::error('Error swapping leader: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to swap leader'], 500);
    }
}

public function softDeleteTeam($id)
{
    try {
        $user = Auth::user();
        $team = Team::findOrFail($id);

        // التحقق من الصلاحية: إما قائد الفريق أو أدمن عام
        $isLeader = $team->isLeader($user->programmer->id);
        if (!$isLeader && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $team->delete(); // soft delete

        // إخراج الأعضاء من الفريق (اختياري، إذا أردنا تفعيل left_at)
        $team->activeMembers()->update(['left_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Team soft deleted successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Error soft deleting team: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to delete team'], 500);
    }
}

// app/Http/Controllers/TeamController.php

public function store(Request $request)
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'is_public' => ['boolean'],
        'github_url' => ['nullable', 'url'],
        'category' => ['nullable', 'array'],
        'required_role' => ['nullable', 'array'],
        'invitations' => ['nullable', 'array'],
        'invitations.*' => ['integer', 'exists:programmers,id'],
    ]);

    $team = Team::create($validated);

    // إرسال دعوات للمبرمجين
    if (!empty($validated['invitations'])) {
        foreach ($validated['invitations'] as $programmerId) {
            TeamInvitation::create([
                'team_id' => $team->id,
                'programmer_id' => $programmerId,
            ]);
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Team created successfully',
        'data' => $team->fresh()
    ], 201);
}

    public function inviteByUsername(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $team = Team::find($id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                    'requested_id' => $id
                ], 404);
            }

            // السماح بالدعوات فقط إذا كان الفريق active (تم تعديل الشرط)
            if ($team->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot invite now. Team is in ' . $team->status . ' stage. Only active teams can accept new members.'
                ], 400);
            }

            $user = Auth::user();

            if ($user->role !== 'programmer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only programmers can send invitations',
                ], 403);
            }

            if (!$user->programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found',
                ], 404);
            }

            $inviter = $user->programmer;

            // فقط القائد (أو من له صلاحية) يمكنه إرسال الدعوات (حسب需求)
            if (!$team->isLeader($inviter->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the team leader can send invitations'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'username' => 'required|string|exists:users,user_name',
                'message' => 'nullable|string|max:500',
                'expires_at' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invitedUser = User::where('user_name', $request->username)->first();

            if (!$invitedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found with username: ' . $request->username
                ], 404);
            }

            if ($invitedUser->role !== 'programmer') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a programmer',
                ], 400);
            }

            if (!$invitedUser->programmer) {
                $invitedProgrammer = Programmer::create([
                    'user_id' => $invitedUser->id,
                    'specialty' => 'Not specified',
                    'total_score' => 0,
                    'github_username' => '',
                    'is_available' => true,
                ]);
            } else {
                $invitedProgrammer = $invitedUser->programmer;
            }

            if (!$invitedUser->profile_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile is not completed',
                ], 400);
            }

            $currentMembers = $team->activeMembers()->count();
            $maxMembers = $team->max_members;
            $availableSlots = $maxMembers - $currentMembers;

            if ($availableSlots <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team has reached maximum capacity',
                ], 400);
            }

            if ($team->isMember($invitedProgrammer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer is already a team member',
                ], 400);
            }

            if ($invitedProgrammer->is_in_team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer is already in another team',
                ], 400);
            }

            $existingInvitation = TeamInvitation::where('team_id', $team->id)
                ->where('programmer_id', $invitedProgrammer->id)
                ->where('status', 'pending')
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'An invitation is already pending for this programmer',
                ], 400);
            }

            $invitation = TeamInvitation::create([
                'team_id' => $team->id,
                'programmer_id' => $invitedProgrammer->id,
                'invited_by' => $inviter->id,
                'message' => $request->message ?? "You've been invited to join team '{$team->name}' by @{$user->user_name}",
                'status' => 'pending',
                'expires_at' => $request->expires_at ?: now()->addDays(7),
            ]);

            Log::info('Team invitation sent by username', [
                'team_id' => $team->id,
                'inviter_id' => $inviter->id,
                'invited_username' => $request->username,
                'invitation_id' => $invitation->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully to @' . $request->username,
                'data' => [
                    'invitation' => $invitation,
                    'invited_programmer' => [
                        'id' => $invitedProgrammer->id,
                        'name' => $invitedUser->name,
                        'username' => $invitedUser->user_name,
                    ],
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'current_members' => $currentMembers,
                        'max_members' => $maxMembers,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send invitation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation',
            ], 500);
        }
    }

    public function acceptInvitationById(Request $request, $invitationId)
    {
        DB::beginTransaction();

        try {
            $invitation = TeamInvitation::find($invitationId);

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation not found'
                ], 404);
            }

            $user = Auth::user();
            $programmer = $user->programmer;

            if ($invitation->programmer_id !== $programmer->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invitation is not for you'
                ], 403);
            }

            if ($invitation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "This invitation is already {$invitation->status}."
                ], 400);
            }

            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has expired'
                ], 400);
            }

            $team = $invitation->team;

            if ($team->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'This team is not active.'
                ], 400);
            }

            if (!$team->hasVacancy()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is full',
                ], 400);
            }

            if ($programmer->is_in_team) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already in another team'
                ], 400);
            }

            $teamMember = TeamMember::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'role' => 'member',   // الأعضاء الجدد يصبحون أعضاء عاديين (القائد موجود مسبقاً)
                'joined_at' => now(),
                'joined_by' => $invitation->invited_by,
                'invitation_id' => $invitation->id,
            ]);

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            Log::info('Invitation accepted', [
                'invitation_id' => $invitation->id,
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully',
                'data' => [
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'current_members' => $team->activeMembers()->count(),
                        'max_members' => $team->max_members,
                    ],
                    'member' => [
                        'id' => $teamMember->id,
                        'role' => $teamMember->role,
                        'joined_at' => $teamMember->joined_at,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept invitation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation',
            ], 500);
        }
    }

    public function declineInvitationById(Request $request, $invitationId)
    {
        DB::beginTransaction();
        try {
            $invitation = TeamInvitation::find($invitationId);
            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation not found'
                ], 404);
            }
            $user = Auth::user();
            $programmer = $user->programmer;
            if ($invitation->programmer_id !== $programmer->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invitation is not for you'
                ], 403);
            }
            if ($invitation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "This invitation is already {$invitation->status}."
                ], 400);
            }
            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has expired'
                ], 400);
            }
            $invitation->update([
                'status' => 'declined',
                'declined_at' => now(),
            ]);
            Log::info('Invitation declined', [
                'invitation_id' => $invitation->id,
                'programmer_id' => $programmer->id,
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invitation declined successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error declining invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline invitation',
            ], 500);
        }
    }

    public function getMyInvitations(Request $request)
    {
        try {
            $user = Auth::user();
            $programmer = $user->programmer;

            if (!$programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found.'
                ], 404);
            }

            $sentInvitations = TeamInvitation::where('invited_by', $programmer->id)
                ->with(['team', 'programmer.user'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'id' => $invitation->id,
                        'type' => 'sent',
                        'status' => $invitation->status,
                        'team' => $invitation->team ? ['id' => $invitation->team->id, 'name' => $invitation->team->name] : null,
                        'to_programmer' => $invitation->programmer ? [
                            'id' => $invitation->programmer->id,
                            'name' => $invitation->programmer->user->name ?? 'N/A',
                            'username' => $invitation->programmer->user->user_name ?? 'N/A',
                        ] : null,
                        'message' => $invitation->message,
                        'created_at' => $invitation->created_at,
                        'expires_at' => $invitation->expires_at,
                    ];
                });

            $receivedInvitations = TeamInvitation::where('programmer_id', $programmer->id)
                ->with(['team', 'inviter.user'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'id' => $invitation->id,
                        'type' => 'received',
                        'status' => $invitation->status,
                        'team' => $invitation->team ? ['id' => $invitation->team->id, 'name' => $invitation->team->name] : null,
                        'from' => $invitation->inviter ? [
                            'name' => $invitation->inviter->user->name ?? 'N/A',
                            'username' => $invitation->inviter->user->user_name ?? 'N/A',
                        ] : null,
                        'message' => $invitation->message,
                        'created_at' => $invitation->created_at,
                        'expires_at' => $invitation->expires_at,
                    ];
                });

            $allInvitations = $sentInvitations->concat($receivedInvitations);

            return response()->json([
                'success' => true,
                'message' => 'Invitations fetched successfully.',
                'data' => $allInvitations,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMyInvitations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invitations.'
            ], 500);
        }
    }

    public function teamMembers($id)
    {
        try {
            $team = Team::find($id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $members = $team->activeMembers()
                ->with(['programmer.user', 'inviter.user', 'invitation'])
                ->orderByRaw("FIELD(role, 'leader', 'member')")
                ->orderBy('joined_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'status' => $team->status,
                        'current_members' => $members->count(),
                        'max_members' => $team->max_members,
                    ],
                    'members' => $members->map(function($member) {
                        return [
                            'id' => $member->id,
                            'role' => $member->role,
                            'joined_at' => $member->joined_at,
                            'programmer' => $member->programmer ? [
                                'id' => $member->programmer->id,
                                'name' => $member->programmer->user->name,
                                'username' => $member->programmer->user->user_name,
                                'specialty' => $member->programmer->specialty,
                                'total_score' => $member->programmer->total_score,
                            ] : null,
                            'invited_by' => $member->inviter ? [
                                'name' => $member->inviter->user->name,
                                'username' => $member->inviter->user->user_name,
                            ] : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch team members', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch team members',
            ], 500);
        }
    }

    public function getAIRandomRecommendations(Request $request)
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'programmer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only programmers can get AI recommendations'
                ], 403);
            }

            $programmer = $user->programmer;

            if (!$programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found'
                ], 404);
            }

            $recommendations = $this->aiRecommendationService->getRecommendations($programmer, 10);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'message' => 'AI recommendations fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting AI recommendations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations'
            ], 500);
        }
    }

    public function joinViaAIRecommendation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            $team = Team::find($request->team_id);

            if ($team->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'This team is not active.'
                ], 400);
            }

            if (!$team->is_public) {
                return response()->json([
                    'success' => false,
                    'message' => 'This team is private. Use join code instead.'
                ], 400);
            }

            if (!$team->hasVacancy()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is full'
                ], 400);
            }

            if ($programmer->is_in_team) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already in a team'
                ], 400);
            }

            TeamMember::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'role' => 'member',
                'joined_at' => now(),
                'joined_by' => $programmer->id,
            ]);

            Log::info('Programmer joined via AI recommendation', [
                'programmer_id' => $programmer->id,
                'team_id' => $team->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully joined team via AI recommendation',
                'data' => [
                    'team' => $team->fresh(['activeMembers']),
                    'joined_via' => 'ai_recommendation'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error joining via AI: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to join team'
            ], 500);
        }
    }

    // ================== دوال التصويت (معطلة بالكامل) ==================

    public function startVoting($teamId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Voting system is disabled. The team creator is automatically the leader.'
        ], 400);
    }

    public function vote(Request $request, $teamId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Voting system is disabled. No votes are required.'
        ], 400);
    }

    public function votingStatus($teamId)
    {
        return response()->json([
            'success' => true,
            'message' => 'Voting is disabled. The team leader is assigned at creation.',
            'data' => [
                'voting_enabled' => false,
                'leader_assigned' => true
            ]
        ]);
    }

    // إزالة الدوال المساعدة: checkTeamCompletion, sendVotingStartedNotifications, handleTieVote, sendRunoffVotingNotifications, processVotingResults, announceWinner, calculatePercentage
}
