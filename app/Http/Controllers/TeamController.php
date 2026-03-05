<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Programmer;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\TeamMember;
use App\Models\TeamVote;
use App\Models\User;
use App\Models\AiTeamRequest;
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

    private function checkTeamCompletion(Team $team)
    {
        $currentMembers = $team->activeMembers()->count();

        if ($currentMembers >= $team->max_members && $team->status === 'forming') {
            $team->update([
                'status' => 'voting',
                'voting_started_at' => now()
            ]);

            Log::info('Team completed, voting started automatically', [
                'team_id' => $team->id,
                'members_count' => $currentMembers
            ]);

            $this->sendVotingStartedNotifications($team);

            return true;
        }

        return $team->status === 'voting';
    }

    private function sendVotingStartedNotifications(Team $team)
    {
        try {
            $members = $team->activeMembers()->with('programmer.user')->get();
            $startedBy = Programmer::find($team->created_by);

            $users = $members->map(function($member) {
                return $member->programmer->user;
            })->filter();

            if ($users->isNotEmpty()) {
                Notification::send($users, new TeamVotingStarted($team, $startedBy));

                Log::info('Voting started notifications sent', [
                    'team_id' => $team->id,
                    'recipients_count' => $users->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send voting started notifications', [
                'team_id' => $team->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function startVoting($teamId)
    {
        try {
            $team = Team::find($teamId);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $user = Auth::user();
            $programmer = $user->programmer;

            if ($team->status !== 'forming') {
                return response()->json([
                    'success' => false,
                    'message' => 'Voting has already started or the team is in an advanced stage'
                ], 400);
            }

            if ($team->created_by !== $programmer->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the team creator can start the voting'
                ], 403);
            }

            $currentMembers = $team->activeMembers()->count();
            if ($currentMembers < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'The team needs at least 3 members to start voting',
                    'current' => $currentMembers
                ], 400);
            }

            $team->update([
                'status' => 'voting',
                'voting_started_at' => now()
            ]);

            $this->sendVotingStartedNotifications($team);

            return response()->json([
                'success' => true,
                'message' => 'Voting started successfully. Notifications sent to all team members.',
                'data' => [
                    'team_id' => $team->id,
                    'members' => $team->activeMembers()->with('programmer.user')->get(),
                    'notifications_sent' => $team->activeMembers()->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error starting voting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while starting the voting',
            ], 500);
        }
    }

    private function handleTieVote(Team $team, $topCandidates, $totalMembers)
    {
        DB::transaction(function () use ($team, $topCandidates) {
            foreach ($topCandidates as $candidate) {
                $candidate->update(['votes_count' => 0]);
            }

            TeamVote::where('team_id', $team->id)->delete();

            $runoffRound = ($team->runoff_round ?? 0) + 1;

            $team->update([
                'status' => 'voting',
                'voting_started_at' => now(),
                'is_runoff' => true,
                'runoff_round' => $runoffRound,
                'runoff_candidates' => json_encode($topCandidates->pluck('programmer_id')->toArray())
            ]);
        });

        $candidatesData = $topCandidates->map(function($candidate) {
            return [
                'id' => $candidate->programmer_id,
                'name' => $candidate->programmer->user->name,
                'username' => $candidate->programmer->user->user_name,
                'previous_votes' => $candidate->votes_count,
            ];
        })->toArray();

        $this->sendRunoffVotingNotifications($team, $candidatesData);

        return response()->json([
            'success' => true,
            'message' => 'A tie has been detected! A runoff round is starting between the top candidates only',
            'data' => [
                'team_name' => $team->name,
                'runoff_round' => $team->fresh()->runoff_round,
                'candidates' => $candidatesData,
                'total_members' => $totalMembers,
                'instructions' => 'Please vote again, this time only among the candidates listed above',
                'notifications_sent' => $team->activeMembers()->count()
            ]
        ]);
    }

    private function sendRunoffVotingNotifications(Team $team, array $candidatesData)
    {
        try {
            $members = $team->activeMembers()->with('programmer.user')->get();

            $users = $members->map(function($member) {
                return $member->programmer->user;
            })->filter();

            if ($users->isNotEmpty()) {
                Notification::send($users, new TeamRunoffVotingStarted(
                    $team,
                    $candidatesData,
                    $team->runoff_round
                ));

                Log::info('Runoff voting notifications sent', [
                    'team_id' => $team->id,
                    'runoff_round' => $team->runoff_round,
                    'recipients_count' => $users->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send runoff voting notifications', [
                'team_id' => $team->id,
                'error' => $e->getMessage()
            ]);
        }
    }


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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            if ($user->role !== 'programmer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only programmers can create teams'
                ], 403);
            }

            if (!$user->programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found'
                ], 404);
            }

            $programmer = $user->programmer;

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'formation_type' => 'required|in:random,manual,mixed',
                'max_members' => 'required|integer|min:3|max:20',
                'min_members' => 'required|integer|min:1|max:10',
                'is_public' => 'required|boolean',
                'experience_level' => 'nullable|in:beginner,intermediate,advanced,expert',
                'required_skills' => 'nullable|array',
                'required_skills.*' => 'string',
                'preferred_skills' => 'nullable|array',
                'preferred_skills.*' => 'string',
                'project_id' => 'nullable|exists:projects,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($programmer->is_in_team) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already in a team'
                ], 400);
            }

            $team = Team::create([
                'name' => $request->name,
                'description' => $request->description,
                'formation_type' => $request->formation_type,
                'max_members' => $request->max_members,
                'min_members' => $request->min_members,
                'is_public' => $request->is_public,
                'experience_level' => $request->experience_level ?? 'beginner',
                'required_skills' => $request->required_skills,
                'preferred_skills' => $request->preferred_skills,
                'project_id' => $request->project_id,
                'status' => 'forming',
                'created_by' => $programmer->id,
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'role' => 'member',
                'joined_at' => now(),
                'joined_by' => $programmer->id,
            ]);

            if (!$team->is_public) {
                $team->update(['join_code' => strtoupper(substr(md5(uniqid()), 0, 8))]);
            }

            Log::info('Team created', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'creator_id' => $programmer->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team created successfully. You can now invite members.',
                'data' => [
                    'team' => $team->fresh(),
                    'join_code' => $team->join_code,
                    'current_members' => 1,
                    'max_members' => $team->max_members,
                    'your_role' => 'member (creator)',
                    'can_invite' => true,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create team', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create team',
                'error' => $e->getMessage()
            ], 500);
        }
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

            if ($team->status !== 'forming') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot invite now. Team is in ' . $team->status . ' stage. Invitations only allowed during team formation.'
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

            if ($team->created_by !== $inviter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the team creator can send invitations during team formation'
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
                    'user_info' => [
                        'name' => $invitedUser->name,
                        'username' => $invitedUser->user_name,
                        'role' => $invitedUser->role
                    ]
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
                    'user_info' => [
                        'name' => $invitedUser->name,
                        'username' => $invitedUser->user_name,
                        'profile_completed' => false
                    ]
                ], 400);
            }

            $currentMembers = $team->activeMembers()->count();
            $maxMembers = $team->max_members;
            $availableSlots = $maxMembers - $currentMembers;

            if ($availableSlots <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team has reached maximum capacity',
                    'team_info' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'current_members' => $currentMembers,
                        'max_members' => $maxMembers,
                        'available_slots' => 0
                    ]
                ], 400);
            }

            if ($team->isMember($invitedProgrammer->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer is already a team member',
                    'programmer_info' => [
                        'id' => $invitedProgrammer->id,
                        'name' => $invitedUser->name
                    ]
                ], 400);
            }

            if ($invitedProgrammer->is_in_team) {
                $currentTeam = $invitedProgrammer->active_team;
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer is already in another team',
                    'programmer_info' => [
                        'id' => $invitedProgrammer->id,
                        'name' => $invitedUser->name,
                        'current_team' => $currentTeam ? [
                            'id' => $currentTeam->id,
                            'name' => $currentTeam->name
                        ] : null
                    ]
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
                    'existing_invitation' => [
                        'id' => $existingInvitation->id,
                        'created_at' => $existingInvitation->created_at,
                        'expires_at' => $existingInvitation->expires_at
                    ]
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
                'invited_programmer_id' => $invitedProgrammer->id,
                'invitation_id' => $invitation->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully to @' . $request->username,
                'data' => [
                    'invitation' => [
                        'id' => $invitation->id,
                        'message' => $invitation->message,
                        'status' => $invitation->status,
                        'expires_at' => $invitation->expires_at,
                        'created_at' => $invitation->created_at,
                    ],
                    'invited_programmer' => [
                        'id' => $invitedProgrammer->id,
                        'name' => $invitedUser->name,
                        'username' => $invitedUser->user_name,
                        'specialty' => $invitedProgrammer->specialty,
                        'total_score' => $invitedProgrammer->total_score,
                        'is_available' => $invitedProgrammer->is_available,
                    ],
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'description' => $team->description,
                        'current_members' => $currentMembers,
                        'max_members' => $maxMembers,
                        'available_slots' => $availableSlots,
                    ],
                    'inviter' => [
                        'id' => $inviter->id,
                        'name' => $user->name,
                        'username' => $user->user_name,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send invitation by username', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation',
                'error' => $e->getMessage()
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

            if ($team->status !== 'forming') {
                return response()->json([
                    'success' => false,
                    'message' => 'This team is not accepting new members at this stage.'
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

            $this->checkTeamCompletion($team);

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
            $status = $invitation->status;
            return response()->json([
                'success' => false,
                'message' => "This invitation is already {$status}. You cannot accept it now."
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

        if ($team->status !== 'forming') {
            return response()->json([
                'success' => false,
                'message' => 'This team is no longer accepting members.'
            ], 400);
        }

        if (!$team->hasVacancy()) {
            return response()->json([
                'success' => false,
                'message' => 'Team has reached maximum capacity',
            ], 400);
        }

        if ($programmer->is_in_team) {
            return response()->json([
                'success' => false,
                'message' => 'You are already in another team'
            ], 400);
        }

        TeamMember::create([
            'team_id' => $team->id,
            'programmer_id' => $programmer->id,
            'role' => 'member',
            'joined_at' => now(),
            'joined_by' => $invitation->invited_by,
            'invitation_id' => $invitation->id,
        ]);

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->checkTeamCompletion($team);

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
                ->orderBy('role', 'desc')
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
                'error' => $e->getMessage()
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
                ->with([
                    'team',
                    'inviter.user'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            $allInvitations = $sentInvitations->concat($receivedInvitations);
                return response()->json([
                'success' => true,
                'message' => 'Invitations fetched successfully.',
                'data' => $allInvitations,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMyInvitations: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invitations. An internal error has been logged.'
            ], 500);
        }
    }

    public function declineInvitationById(Request $request, $invitationId)
    {
        DB::beginTransaction();
        try
        {
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
                $status = $invitation->status;
                return response()->json([
                    'success' => false,
                    'message' => "This invitation is already {$status}. You cannot decline it now."
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
        }catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error declining invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline invitation',
            ], 500);
        }
    }


    private function calculatePercentage($votes, $total)
    {
        if ($total == 0) return 0;
        return round(($votes / $total) * 100, 2);
    }

    public function vote(Request $request, $teamId)
    {
        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:programmers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            $team = Team::find($teamId);

            if ($team->status !== 'voting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Voting is currently not available for this team',
                ], 400);
            }

            $member = $team->activeMembers()->where('programmer_id', $programmer->id)->first();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this team'
                ], 403);
            }

            $candidate = $team->activeMembers()->where('programmer_id', $request->candidate_id)->first();
            if (!$candidate) {
                return response()->json([
                    'success' => false,
                    'message' => 'The candidate does not exist in the team'
                ], 400);
            }

            $existingVote = TeamVote::where('team_id', $teamId)
                ->where('voter_id', $programmer->id)
                ->first();

            if ($existingVote) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already voted, you cannot change your vote'
                ], 400);
            }

            DB::transaction(function () use ($teamId, $programmer, $request, $candidate) {
                TeamVote::create([
                    'team_id' => $teamId,
                    'voter_id' => $programmer->id,
                    'candidate_id' => $request->candidate_id
                ]);

                $candidate->increment('votes_count');
            });

            $totalMembers = $team->activeMembers()->count();
            $totalVotes = TeamVote::where('team_id', $teamId)->count();

            if ($totalVotes >= $totalMembers) {
                return $this->processVotingResults($teamId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Your vote has been recorded successfully',
                'data' => [
                    'voted_for' => $candidate->programmer->user->name,
                    'votes_so_far' => $totalVotes,
                    'total_members' => $totalMembers,
                    'remaining_votes' => $totalMembers - $totalVotes
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error voting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while voting'
            ], 500);
        }
    }


    private function processVotingResults($teamId)
    {
        $team = Team::find($teamId);

        $members = $team->activeMembers()
            ->with('programmer.user')
            ->orderBy('votes_count', 'desc')
            ->get();

        if ($members->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'There are no members in the team!'
            ]);
        }

        $highestVotes = $members->first()->votes_count;

        $topCandidates = $members->filter(function($member) use ($highestVotes) {
            return $member->votes_count == $highestVotes;
        });

        if ($topCandidates->count() > 1) {
            return $this->handleTieVote($team, $topCandidates, $members->count());
        }

        return $this->announceWinner($teamId);
    }


    private function announceWinner($teamId)
    {
        $team = Team::find($teamId);

        $winner = $team->activeMembers()
            ->orderBy('votes_count', 'desc')
            ->first();

        if (!$winner) {
            return response()->json([
                'success' => false,
                'message' => 'No winner!'
            ]);
        }

        $winner->update(['role' => 'leader']);

        $team->update([
            'status' => 'active',
            'leader_elected_at' => now(),
            'runoff_round' => null,
            'runoff_candidates' => null,
            'is_runoff' => false,
        ]);

        Log::info('New team leader elected', [
            'team_id' => $teamId,
            'leader_id' => $winner->programmer_id,
            'votes' => $winner->votes_count,
            'runoff_rounds' => $team->runoff_round ?? 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Team leader has been selected successfully!',
            'data' => [
                'leader' => [
                    'name' => $winner->programmer->user->name,
                    'username' => $winner->programmer->user->user_name,
                    'votes' => $winner->votes_count
                ],
                'total_votes' => TeamVote::where('team_id', $teamId)->count(),
                'runoff_rounds' => $team->runoff_round ?? 0,
                'all_members' => $team->activeMembers()
                    ->with('programmer.user')
                    ->orderBy('votes_count', 'desc')
                    ->get()
                    ->map(function($member) {
                        return [
                            'name' => $member->programmer->user->name,
                            'votes' => $member->votes_count,
                            'role' => $member->role
                        ];
                    })
            ]
        ]);
    }

    public function votingStatus($teamId)
    {
        try {
            $team = Team::find($teamId);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $totalMembers = $team->activeMembers()->count();
            $totalVotes = TeamVote::where('team_id', $teamId)->count();

            $membersQuery = $team->activeMembers()->with('programmer.user');

            if ($team->is_runoff && $team->runoff_candidates) {
                $candidatesIds = json_decode($team->runoff_candidates, true);
                $membersQuery->whereIn('programmer_id', $candidatesIds);
            }

            $results = $membersQuery
                ->orderBy('votes_count', 'desc')
                ->get()
                ->map(function($member) use ($totalMembers) {
                    return [
                        'name' => $member->programmer->user->name ?? 'N/A',
                        'username' => $member->programmer->user->user_name ?? 'N/A',
                        'votes' => $member->votes_count,
                        'percentage' => $this->calculatePercentage($member->votes_count, $totalMembers)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'team_name' => $team->name,
                    'team_status' => $team->status,
                    'total_members' => $totalMembers,
                    'votes_cast' => $totalVotes,
                    'voting_complete' => $totalVotes >= $totalMembers,
                    'is_runoff' => $team->is_runoff ?? false,
                    'runoff_round' => $team->runoff_round ?? 0,
                    'runoff_candidates' => $team->is_runoff ? json_decode($team->runoff_candidates, true) : null,
                    'results' => $results,
                    'leader_elected' => $team->leader()->exists() ? $team->leader->programmer->user->name : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting voting status for team ' . $teamId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the voting status'
            ], 500);
        }
    }

}
