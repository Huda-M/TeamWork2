<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluateTeamRequest;
use App\Models\Evaluation;
use App\Models\Programmer;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\InvitationAcceptedNotification;
use App\Notifications\InvitationDeclinedNotification;
use App\Notifications\SendInvitationNotification;
use App\Notifications\SwapLeaderNotification;
use App\Notifications\TeamCreatedNotification;
use App\Notifications\TeamUpdatedNotification;
use App\Services\AITeamRecommendationService;
use App\Services\FCM\PushNotify;
use App\Services\TeamMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class TeamController extends Controller
{
    protected $teamMatchingService;
    protected $aiRecommendationService;

    public function __construct(TeamMatchingService $teamMatchingService, AITeamRecommendationService $aiRecommendationService)
    {
        $this->teamMatchingService = $teamMatchingService;
        $this->aiRecommendationService = $aiRecommendationService;
    }

    public function index(Request $request)
    {
        try {
            $query = Team::query()->with(['project', 'leader.programmer.user']);
            if ($request->has('status')) $query->where('status', $request->status);
            if ($request->has('project_id')) $query->where('project_id', $request->project_id);
            if ($request->has('experience_level')) $query->where('experience_level', $request->experience_level);
            if ($request->has('is_public')) $query->where('is_public', $request->boolean('is_public'));
            $teams = $query->paginate(15);
            return response()->json(['success' => true, 'data' => $teams, 'message' => 'Teams fetched successfully']);
        } catch (\Exception $e) {
            Log::error('Error fetching teams: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch teams'], 500);
        }
    }

    public function showTeam($id)
    {
        try {
            $team = Team::with(['project', 'activeMembers.programmer.user', 'leader.programmer.user', 'invitations' => function ($q) { $q->where('status', 'pending'); }])->find($id);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            return response()->json(['success' => true, 'data' => ['team' => $team, 'members_count' => $team->activeMembers()->count(), 'available_slots' => $team->max_members - $team->activeMembers()->count(), 'is_full' => ! $team->hasVacancy(), 'stage' => $team->status]]);
        } catch (\Exception $e) {
            Log::error('Error showing team: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to show team'], 500);
        }
    }

    public function getTeamDetails($projectId)
    {
        try {
            $team = Team::with(['project', 'activeMembers.programmer.user'])->where('project_id', $projectId)->firstOrFail();
            return response()->json(['success' => true, 'data' => [
                'project_id' => $team->project_id,
                'project_name' => $team->project->title ?? null,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'github_link' => $team->project->github_url ?? null,
                'project_description' => $team->project->description,
                'members' => $team->activeMembers->map(function ($member) {
                    $prog = $member->programmer;
                    return [
                        'programmer_id' => $member->programmer_id,
                        'name' => $prog->user->full_name,
                        'track' => $prog->track ?? 'general',
                        'avatar_url' => $prog->avatar_url ?: null,
                    ];
                }),
            ]]);
        } catch (\Exception $e) {
            Log::error('Error fetching team details: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
        }
    }

    public function swapProjectLeader(Request $request, $projectId, $programmerId)
    {
        try {
            $user = Auth::user();
            $currentLeader = $user->programmer;
            
            $project = Project::with('teams')->findOrFail($projectId);
            $team = $project->teams->first();
            
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
            }
            
            if (!$team->isLeader($currentLeader->id) && $user->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $newLeader = Programmer::findOrFail($programmerId);
            
            if (!$team->isMember($newLeader->id)) {
                return response()->json(['success' => false, 'message' => 'Programmer is not a member of this team'], 400);
            }
            
            DB::transaction(function () use ($team, $currentLeader, $newLeader) {
                TeamMember::where('team_id', $team->id)
                    ->where('programmer_id', $currentLeader->id)
                    ->update(['role' => 'member']);
                    
                TeamMember::where('team_id', $team->id)
                    ->where('programmer_id', $newLeader->id)
                    ->update(['role' => 'leader']);
            });
            
            $team->load('activeMembers.programmer.user');
            $tokens = [];
            foreach ($team->activeMembers as $member) {
                $prog = $member->programmer;
                if ($prog && $prog->user) {
                    $u = $prog->user;
                    if ($u->id !== $newLeader->user->id) {
                        $u->notify(new SwapLeaderNotification($newLeader));
                        if ($u->fcm_token) $tokens[] = $u->fcm_token;
                    }
                }
            }
            
            $tokens = array_unique($tokens);
            if (! empty($tokens)) {
                $pushNotify = new PushNotify;
                $pushNotify->sendBulkNotification($tokens, 'New Team Leader', "{$newLeader->user->full_name} is now the leader of team {$team->name}.", ['team_id' => $team->id, 'new_leader_id' => $newLeader->id]);
            }
            
            return response()->json(['success' => true, 'message' => "Leader role transferred from {$currentLeader->user->full_name} to {$newLeader->user->full_name}"]);
            
        } catch (\Exception $e) {
            Log::error('Error swapping leader: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to swap leader'], 500);
        }
    }

    public function softDeleteProjectTeam($projectId)
    {
        $project = Project::with('teams')->findOrFail($projectId);
        $team = $project->teams->first();
        
        if (!$team) {
            return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
        }
        
        $user = Auth::user();
        $isLeader = false;
        if ($user->programmer) $isLeader = $team->isLeader($user->programmer->id);
        if (! $isLeader && $user->role !== 'admin') return response()->json(['message' => 'Unauthorized'], 403);
        
        $team->delete();
        $team->activeMembers()->update(['left_at' => now()]);
        
        return response()->json(['success' => true, 'message' => 'Team soft deleted']);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (! $user || $user->role !== 'programmer') return response()->json(['success' => false, 'message' => 'Only programmers can create teams'], 403);
            $programmer = $user->programmer;
            if (! $programmer) return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string|min:10',
                'is_public' => 'required|boolean',
                'github_url' => 'required|url',
                'categories' => 'required|array|min:1',
                'categories.*' => 'string|max:100',
                'required_tracks' => 'required|array|min:1',
                'required_tracks.*' => 'string|max:50',
                'invitations' => 'nullable|array',
                'invitations.*' => 'string|exists:programmers,user_name',
            ]);
            if (! $validated['is_public'] && empty($validated['invitations'])) return response()->json(['success' => false, 'message' => 'Private teams must include at least one invitation'], 422);
            $project = Project::create([
                'title' => $validated['name'],
                'description' => $validated['description'],
                'github_url' => $validated['github_url'],
                'categories' => json_encode($validated['categories']),
                'required_tracks' => json_encode($validated['required_tracks']),
                'category_name' => $validated['categories'][0] ?? null,
                'status' => 'pending',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 30,
                'max_members'     => 10,
                'max_team_size' => 5,
                'num_of_team' => 1,
                'user_id' => $programmer->user_id,
                'team_size' => 10,
                'min_team_size' => 2,
                'max_teams' => 1,
            ]);
            $team = Team::create([
                'name' => $validated['name'],
                'project_id' => $project->id,
                'is_public' => $validated['is_public'],
                'status' => 'active',
                'formation_type' => 'manual',
                'created_by' => $programmer->id,
                'max_members' => $project->max_members,
                'join_code' => $validated['is_public'] ? null : strtoupper(substr(md5(uniqid()), 0, 8)),
            ]);
            TeamMember::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'role' => 'leader',
                'joined_at' => now(),
                'joined_by' => $programmer->id,
            ]);
            $invitationsSent = [];
            if (! empty($validated['invitations'])) {
                foreach ($validated['invitations'] as $username) {
                    if ($username === $programmer->user_name) continue;
                    $invitedProgrammer = Programmer::where('user_name', $username)->first();
                    if (! $invitedProgrammer) { Log::warning("Programmer not found: $username"); continue; }
                    $existing = TeamInvitation::where('team_id', $team->id)->where('programmer_id', $invitedProgrammer->id)->where('status', 'pending')->first();
                    if ($existing) continue;
                    $invitation = TeamInvitation::create([
                        'team_id' => $team->id,
                        'programmer_id' => $invitedProgrammer->id,
                        'invited_by' => $programmer->id,
                        'message' => "You've been invited to join team '{$team->name}'",
                        'status' => 'pending',
                        'expires_at' => now()->addDays(7),
                    ]);
                    $invitedProgrammer->user->notify(new SendInvitationNotification($invitation));
                    $invitationsSent[] = ['username' => $username, 'programmer_id' => $invitedProgrammer->id, 'invitation_id' => $invitation->id];
                    $fcmToken = $invitedProgrammer->user->fcm_token;
                    if ($fcmToken) {
                        $pushNotify = new PushNotify;
                        $pushNotify->sendPushNotification($fcmToken, 'New Team Invitation', "You've been invited to join team '{$team->name}'.", ['team_id' => $team->id, 'invitation_id' => $invitation->id]);
                    }
                    $programmer->user->notify(new TeamCreatedNotification($team));
                    $leaderfcm = $programmer->user->fcm_token;
                    if ($leaderfcm) {
                        $pushNotify = new PushNotify;
                        $pushNotify->sendPushNotification($leaderfcm, 'Team Created', "Team '{$team->name}' has been created successfully.", ['team_id' => $team->id]);
                    }
                }
            }
            $team->chatRoom()->create();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Team created successfully', 'data' => ['project' => $project->fresh(), 'team' => $team, 'invitations_sent' => $invitationsSent]], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating team: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create team', 'error' => $e->getMessage()], 500);
        }
    }

    public function inviteByUsername(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $team = Team::find($id);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found', 'requested_id' => $id], 404);
            if ($team->status !== 'active') return response()->json(['success' => false, 'message' => 'Cannot invite now. Team is in '.$team->status.' stage. Only active teams can accept new members.'], 400);
            $user = Auth::user();
            if ($user->role !== 'programmer') return response()->json(['success' => false, 'message' => 'Only programmers can send invitations'], 403);
            if (! $user->programmer) return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            $inviter = $user->programmer;
            if (! $team->isLeader($inviter->id)) return response()->json(['success' => false, 'message' => 'Only the team leader can send invitations'], 403);
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|exists:users,user_name',
                'message' => 'nullable|string|max:500',
                'expires_at' => 'nullable|date|after:now',
            ]);
            if ($validator->fails()) return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            $invitedUser = User::where('user_name', $request->username)->first();
            if (! $invitedUser) return response()->json(['success' => false, 'message' => 'User not found with username: '.$request->username], 404);
            if ($invitedUser->role !== 'programmer') return response()->json(['success' => false, 'message' => 'User is not a programmer'], 400);
            if (! $invitedUser->programmer) {
                $invitedProgrammer = Programmer::create(['user_id' => $invitedUser->id, 'specialty' => 'Not specified', 'total_score' => 0, 'github_username' => '', 'is_available' => true]);
            } else {
                $invitedProgrammer = $invitedUser->programmer;
            }
            if (! $invitedUser->profile_completed) return response()->json(['success' => false, 'message' => 'Programmer profile is not completed'], 400);
            $currentMembers = $team->activeMembers()->count();
            $maxMembers = $team->max_members;
            $availableSlots = $maxMembers - $currentMembers;
            if ($availableSlots <= 0) return response()->json(['success' => false, 'message' => 'Team has reached maximum capacity'], 400);
            if ($team->isMember($invitedProgrammer->id)) return response()->json(['success' => false, 'message' => 'Programmer is already a team member'], 400);
            if ($invitedProgrammer->is_in_team) return response()->json(['success' => false, 'message' => 'Programmer is already in another team'], 400);
            $existingInvitation = TeamInvitation::where('team_id', $team->id)->where('programmer_id', $invitedProgrammer->id)->where('status', 'pending')->first();
            if ($existingInvitation) return response()->json(['success' => false, 'message' => 'An invitation is already pending for this programmer'], 400);
            $invitation = TeamInvitation::create([
                'team_id' => $team->id,
                'programmer_id' => $invitedProgrammer->id,
                'invited_by' => $inviter->id,
                'message' => $request->message ?? "You've been invited to join team '{$team->name}' by @{$user->user_name}",
                'status' => 'pending',
                'expires_at' => $request->expires_at ?: now()->addDays(7),
            ]);
            Log::info('Team invitation sent by username', ['team_id' => $team->id, 'inviter_id' => $inviter->id, 'invited_username' => $request->username, 'invitation_id' => $invitation->id]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Invitation sent successfully to @'.$request->username, 'data' => ['invitation' => $invitation, 'invited_programmer' => ['id' => $invitedProgrammer->id, 'name' => $invitedUser->name, 'username' => $invitedUser->user_name], 'team' => ['id' => $team->id, 'name' => $team->name, 'current_members' => $currentMembers, 'max_members' => $maxMembers]]]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send invitation', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to send invitation'], 500);
        }
    }

    public function acceptInvitationById(Request $request, $invitationId)
    {
        DB::beginTransaction();
        try {
            $invitation = TeamInvitation::find($invitationId);
            if (! $invitation) return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
            $user = Auth::user();
            $programmer = $user->programmer;
            if ($invitation->programmer_id !== $programmer->id) return response()->json(['success' => false, 'message' => 'This invitation is not for you'], 403);
            if ($invitation->status !== 'pending') return response()->json(['success' => false, 'message' => "This invitation is already {$invitation->status}."], 400);
            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);
                return response()->json(['success' => false, 'message' => 'Invitation has expired'], 400);
            }
            $team = $invitation->team;
            $activeTeamsCount = $programmer->teams()->wherePivotNull('left_at')->count();
    if ($activeTeamsCount >= 10) {
        return response()->json(['success' => false, 'message' => 'You have reached the maximum limit of 10 teams'], 400);
    }
            if ($team->status !== 'active') return response()->json(['success' => false, 'message' => 'This team is not active.'], 400);
            if (! $team->hasVacancy()) return response()->json(['success' => false, 'message' => 'Team is full'], 400);
            if ($programmer->is_in_team) return response()->json(['success' => false, 'message' => 'You are already in another team'], 400);
            $teamMember = TeamMember::create(['team_id' => $team->id, 'programmer_id' => $programmer->id, 'role' => 'member', 'joined_at' => now(), 'joined_by' => $invitation->invited_by, 'invitation_id' => $invitation->id]);
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            Log::info('Invitation accepted', ['invitation_id' => $invitation->id, 'team_id' => $team->id, 'programmer_id' => $programmer->id]);
            DB::commit();
            $inviter = $invitation->invitedBy;
            $pushNotifyService = new PushNotify;
            if ($inviter && $inviter->user) {
                if ($inviter->user->fcm_token) {
                    $pushNotifyService->sendPushNotification($inviter->user->fcm_token, 'Invitation Accepted', "{$user->user_name} accepted your invitation to join your team.", ['team_id' => $team->id, 'invitation_id' => $invitation->id]);
                }
                $inviter->user->notify(new InvitationAcceptedNotification($invitation, $user));
            }
            return response()->json(['success' => true, 'message' => 'Invitation accepted successfully', 'data' => ['team' => ['id' => $team->id, 'name' => $team->name, 'current_members' => $team->activeMembers()->count(), 'max_members' => $team->max_members], 'member' => ['id' => $teamMember->id, 'role' => $teamMember->role, 'joined_at' => $teamMember->joined_at]]]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept invitation', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to accept invitation'], 500);
        }
    }

    public function declineInvitationById(Request $request, $invitationId)
    {
        DB::beginTransaction();
        try {
            $invitation = TeamInvitation::find($invitationId);
            if (! $invitation) return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
            $user = Auth::user();
            $programmer = $user->programmer;
            if ($invitation->programmer_id !== $programmer->id) return response()->json(['success' => false, 'message' => 'This invitation is not for you'], 403);
            if ($invitation->status !== 'pending') return response()->json(['success' => false, 'message' => "This invitation is already {$invitation->status}."], 400);
            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);
                return response()->json(['success' => false, 'message' => 'Invitation has expired'], 400);
            }
            $invitation->update(['status' => 'declined', 'declined_at' => now()]);
            Log::info('Invitation declined', ['invitation_id' => $invitation->id, 'programmer_id' => $programmer->id]);
            DB::commit();
            $pushService = new PushNotify;
            $inviter = $invitation->invitedBy;
            if ($inviter && $inviter->user) {
                if ($inviter->user->fcm_token) {
                    $pushService->sendPushNotification($inviter->user->fcm_token, 'Invitation Declined', "{$user->user_name} declined your invitation to join your team.", ['team_id' => $invitation->team_id, 'invitation_id' => $invitation->id]);
                }
                $inviter->user->notify(new InvitationDeclinedNotification($invitation, $user));
            }
            return response()->json(['success' => true, 'message' => 'Invitation declined successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error declining invitation: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to decline invitation'], 500);
        }
    }

    public function getMyInvitations(Request $request)
    {
        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            if (! $programmer) return response()->json(['success' => false, 'message' => 'Programmer profile not found.'], 404);
            $sentInvitations = TeamInvitation::where('invited_by', $programmer->id)->with(['team', 'programmer.user'])->orderBy('created_at', 'desc')->get()->map(function ($invitation) {
                return ['id' => $invitation->id, 'type' => 'sent', 'status' => $invitation->status, 'team' => $invitation->team ? ['id' => $invitation->team->id, 'name' => $invitation->team->name] : null, 'to_programmer' => $invitation->programmer ? ['id' => $invitation->programmer->id, 'name' => $invitation->programmer->user->name ?? 'N/A', 'username' => $invitation->programmer->user->user_name ?? 'N/A'] : null, 'message' => $invitation->message, 'created_at' => $invitation->created_at, 'expires_at' => $invitation->expires_at];
            });
            $receivedInvitations = TeamInvitation::where('programmer_id', $programmer->id)->with(['team', 'inviter.user'])->orderBy('created_at', 'desc')->get()->map(function ($invitation) {
                return ['id' => $invitation->id, 'type' => 'received', 'status' => $invitation->status, 'team' => $invitation->team ? ['id' => $invitation->team->id, 'name' => $invitation->team->name] : null, 'from' => $invitation->inviter ? ['name' => $invitation->inviter->user->name ?? 'N/A', 'username' => $invitation->inviter->user->user_name ?? 'N/A'] : null, 'message' => $invitation->message, 'created_at' => $invitation->created_at, 'expires_at' => $invitation->expires_at];
            });
            $allInvitations = $sentInvitations->concat($receivedInvitations);
            return response()->json(['success' => true, 'message' => 'Invitations fetched successfully.', 'data' => $allInvitations]);
        } catch (\Exception $e) {
            Log::error('Error in getMyInvitations: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch invitations.'], 500);
        }
    }

    public function teamMembers($id)
    {
        try {
            $team = Team::find($id);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            $members = $team->activeMembers()->with(['programmer.user', 'inviter.user', 'invitation'])->orderByRaw("FIELD(role, 'leader', 'member')")->orderBy('joined_at', 'asc')->get();
            return response()->json(['success' => true, 'data' => ['team' => ['id' => $team->id, 'name' => $team->name, 'status' => $team->status, 'current_members' => $members->count(), 'max_members' => $team->max_members], 'members' => $members->map(function ($member) {
                $prog = $member->programmer;
                return ['id' => $member->id, 'role' => $member->role, 'joined_at' => $member->joined_at, 'programmer' => $prog ? ['id' => $prog->id, 'name' => $prog->user->name, 'username' => $prog->user->user_name, 'specialty' => $prog->specialty, 'total_score' => $prog->total_score, 'avatar_url' => $prog->avatar_url ?: null] : null, 'invited_by' => $member->inviter ? ['name' => $member->inviter->user->name, 'username' => $member->inviter->user->user_name] : null];
            })]]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch team members', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch team members'], 500);
        }
    }

    public function getAIRandomRecommendations(Request $request)
    {
        try {
            $user = Auth::user();
            if ($user->role !== 'programmer') return response()->json(['success' => false, 'message' => 'Only programmers can get AI recommendations'], 403);
            $programmer = $user->programmer;
            if (! $programmer) return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            $recommendations = $this->aiRecommendationService->getRecommendations($programmer, 10);
            return response()->json(['success' => true, 'data' => $recommendations, 'message' => 'AI recommendations fetched successfully']);
        } catch (\Exception $e) {
            Log::error('Error getting AI recommendations: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get recommendations'], 500);
        }
    }

    public function updateProjectTeam(Request $request, $projectId)
    {
        try {
            $user = Auth::user();
            
            $project = Project::with('teams')->findOrFail($projectId);
            $team = $project->teams->first();
            
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
            }
            
            $isLeader = $team->isLeader($user->programmer->id);
            if (!$isLeader && $user->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Only team leader can update team settings'], 403);
            }
            
            $validated = $request->validate(['name' => 'sometimes|string|max:255', 'description' => 'nullable|string', 'github_url' => 'nullable|url', 'avatar_url' => 'nullable|url', 'is_public' => 'nullable|boolean', 'category' => 'nullable|array', 'required_role' => 'nullable|array', 'experience_level' => 'nullable|in:beginner,intermediate,advanced,expert']);
            $team->update($validated);
            $pushNotification = new PushNotify;
            $members = $team->activeMembers()->with('programmer.user')->get();
            foreach ($members as $member) {
                $member->programmer->user->notify(new TeamUpdatedNotification($team));
                $pushNotification->sendPushNotification($member->programmer->user->fcm_token, 'Team Updated', 'The team '.$team->name.' has been updated successfully');
            }
            if ($request->has('is_public') && ! $request->is_public && ! $team->join_code) {
                $team->join_code = strtoupper(substr(md5(uniqid()), 0, 8));
                $team->save();
            }
            return response()->json(['success' => true, 'message' => 'Team settings updated successfully', 'data' => $team->fresh(['activeMembers.programmer.user'])]);
        } catch (\Exception $e) {
            Log::error('Error updating team: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update team'], 500);
        }
    }

    

    public function getFullTeamDetails($teamId, Request $request)
    {
        try {
            $user = auth()->user();
            if (! $user || $user->role !== 'programmer') return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            $programmer = $user->programmer;
            if (! $programmer) return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            $team = Team::with(['project', 'activeMembers.programmer.user', 'tasks.programmer.user'])->find($teamId);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            if (! $team->isMember($programmer->id)) return response()->json(['success' => false, 'message' => 'You are not a member of this team'], 403);
            $myTrack = $programmer->track ?? 'general';
            $projectDescription = $team->project->description ?? null;
            $githubLink = $team->project->github_url ?? null;
            $members = $team->activeMembers->map(function ($member) {
                $prog = $member->programmer;
                return ['id' => $prog->id, 'name' => $prog->user->full_name, 'avatar_url' => $prog->avatar_url ?: null, 'track' => $prog->track ?? 'general', 'role' => $member->role];
            });
            $tasksView = $request->query('tasks_view', 'my');
            $tasks = [];
            if ($tasksView === 'my') {
                $tasks = $team->tasks->where('programmer_id', $programmer->id)->map(function ($task) {
                    return ['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'due_date' => $task->deadline ? $task->deadline->toDateString() : null, 'priority' => $task->priority, 'created_at' => $task->created_at->toDateTimeString()];
                })->values();
            } else {
                $tasks = $team->tasks->map(function ($task) {
                    return ['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'due_date' => $task->deadline ? $task->deadline->toDateString() : null, 'priority' => $task->priority, 'assigned_to' => ['id' => $task->programmer->id, 'name' => $task->programmer->user->full_name, 'avatar_url' => $task->programmer->avatar_url ?: null, 'track' => $task->programmer->track ?? 'general'], 'created_at' => $task->created_at->toDateTimeString()];
                })->values();
            }
            return response()->json(['success' => true, 'data' => ['team_id' => $team->id, 'team_name' => $team->name, 'project_description' => $projectDescription, 'github_link' => $githubLink, 'my_track' => $myTrack, 'members' => $members, 'tasks_view' => $tasksView, 'tasks' => $tasks]]);
        } catch (\Exception $e) {
            Log::error('Error in getFullTeamDetails: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
        }
    }

    public function joinViaAIRecommendation(Request $request)
    {
        $validator = Validator::make($request->all(), ['team_id' => 'required|exists:teams,id']);
        if ($validator->fails()) return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            $team = Team::find($request->team_id);
            if ($team->status !== 'active') return response()->json(['success' => false, 'message' => 'This team is not active.'], 400);
            if (! $team->is_public) return response()->json(['success' => false, 'message' => 'This team is private. Use join code instead.'], 400);
            if (! $team->hasVacancy()) return response()->json(['success' => false, 'message' => 'Team is full'], 400);
            if ($programmer->is_in_team) return response()->json(['success' => false, 'message' => 'You are already in a team'], 400);
            TeamMember::create(['team_id' => $team->id, 'programmer_id' => $programmer->id, 'role' => 'member', 'joined_at' => now(), 'joined_by' => $programmer->id]);
            Log::info('Programmer joined via AI recommendation', ['programmer_id' => $programmer->id, 'team_id' => $team->id]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Successfully joined team via AI recommendation', 'data' => ['team' => $team->fresh(['activeMembers']), 'joined_via' => 'ai_recommendation']]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error joining via AI: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to join team'], 500);
        }
    }

    public function getTeamMembersList($teamId)
    {
        try {
            $team = Team::with('activeMembers.programmer.user')->find($teamId);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            $members = $team->activeMembers->map(function ($member) {
                $prog = $member->programmer;
                return ['programmer_id' => $prog->id, 'name' => $prog->user->full_name, 'track' => $prog->track ?? 'general', 'avatar_url' => $prog->avatar_url ?: null];
            });
            return response()->json(['success' => true, 'data' => $members]);
        } catch (\Exception $e) {
            Log::error('Error fetching team members list: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team members'], 500);
        }
    }

    public function getTeamMembersWithRatings($teamId)
    {
        try {
            $team = Team::with(['project', 'activeMembers.programmer.user'])->findOrFail($teamId);
            $members = $team->activeMembers->map(function ($member) {
                $prog = $member->programmer;
                $avgScore = Evaluation::where('evaluated_id', $prog->id)->where('team_id', $team->id)->avg('average_score') ?? 0;
                $stars = round($avgScore / 2, 1);
                $latestFeedback = Evaluation::where('evaluated_id', $prog->id)->where('team_id', $team->id)->whereNotNull('feedback')->orderBy('created_at', 'desc')->value('feedback');
                return ['programmer_id' => $prog->id, 'name' => $prog->user->full_name, 'avatar_url' => $prog->avatar_url ?: null, 'track' => $prog->track ?? 'general', 'average_rating' => $stars, 'latest_feedback' => $latestFeedback];
            });
            return response()->json(['success' => true, 'data' => ['project_name' => $team->project->title, 'project_description' => $team->project->description, 'members' => $members]]);
        } catch (\Exception $e) {
            Log::error('Error in getTeamMembersWithRatings: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch data'], 500);
        }
    }

    public function getTeamBasicDetails($id)
    {
        try {
            $team = Team::with(['project', 'activeMembers.programmer.user'])->findOrFail($id);
            return response()->json(['success' => true, 'data' => ['team_name' => $team->name, 'project_description' => $team->project->description, 'members' => $team->activeMembers->map(function ($member) {
                $prog = $member->programmer;
                return ['programmer_id' => $member->programmer_id, 'name' => $prog->user->full_name, 'track' => $prog->track ?? 'general', 'avatar_url' => $prog->avatar_url ?: null];
            })]]);
        } catch (\Exception $e) {
            Log::error('Error fetching team basic details: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
        }
    }

    public function getTeamMembersWithMyRatings($teamId)
    {
        try {
            $user = auth()->user();
            if (! $user || $user->role !== 'programmer') return response()->json(['success' => false, 'message' => 'Only programmers can access'], 403);
            $currentProgrammer = $user->programmer;
            if (! $currentProgrammer) return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            $team = Team::with(['project', 'activeMembers.programmer.user'])->find($teamId);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            if (! $team->isMember($currentProgrammer->id)) return response()->json(['success' => false, 'message' => 'You are not a member of this team'], 403);
            $members = $team->activeMembers->map(function ($member) use ($currentProgrammer) {
                $prog = $member->programmer;
                $evaluation = Evaluation::where('evaluator_id', $prog->id)->where('evaluated_id', $currentProgrammer->id)->first();
                $starsGiven = null;
                $feedbackGiven = null;
                if ($evaluation) {
                    $starsGiven = round($evaluation->average_score / 2, 1);
                    $feedbackGiven = $evaluation->feedback;
                }
                return ['programmer_id' => $prog->id, 'name' => $prog->user->full_name, 'track' => $prog->track ?? 'general', 'avatar_url' => $prog->avatar_url ?: null, 'stars_given_to_me' => $starsGiven, 'feedback_from_them' => $feedbackGiven];
            });
            return response()->json(['success' => true, 'data' => ['team_name' => $team->name, 'project_description' => $team->project->description, 'members' => $members]]);
        } catch (\Exception $e) {
            Log::error('Error in getTeamMembersWithMyRatings: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team members with ratings'], 500);
        }
    }

    public function evaluateTeamMembers($teamId, EvaluateTeamRequest $request)
    {
        try {
            $user = auth()->user();
            $evaluator = $user->programmer;
            $team = Team::with(['project', 'activeMembers'])->find($teamId);
            if (! $team) return response()->json(['success' => false, 'message' => 'Team not found'], 404);
            if (! $team->isMember($evaluator->id)) return response()->json(['success' => false, 'message' => 'You are not a member of this team'], 403);
            $project = $team->project;
            if (! $project || $project->status !== 'completed') return response()->json(['success' => false, 'message' => 'You can only evaluate team members after the project is completed'], 400);
            $validated = $request->validated();
            $evaluationsData = $validated['evaluations'];
            $errors = [];
            $successCount = 0;
            DB::beginTransaction();
            foreach ($evaluationsData as $eval) {
                $evaluatedId = $eval['evaluated_id'];
                $rating = $eval['rating'];
                $feedback = $eval['feedback'] ?? null;
                if ($evaluatedId == $evaluator->id) { $errors[] = "You cannot evaluate yourself (ID: $evaluatedId)"; continue; }
                if (! $team->isMember($evaluatedId)) { $errors[] = "Programmer ID $evaluatedId is not a member of this team"; continue; }
                $existing = Evaluation::where('project_id', $project->id)->where('team_id', $team->id)->where('evaluator_id', $evaluator->id)->where('evaluated_id', $evaluatedId)->first();
                if ($existing) { $errors[] = "You have already evaluated programmer ID $evaluatedId"; continue; }
                $averageScore = $rating * 2;
                Evaluation::create(['project_id' => $project->id, 'team_id' => $team->id, 'evaluator_id' => $evaluator->id, 'evaluated_id' => $evaluatedId, 'technical_skills' => $rating, 'communication' => $rating, 'teamwork' => $rating, 'problem_solving' => $rating, 'reliability' => $rating, 'code_quality' => $rating, 'average_score' => $averageScore, 'strengths' => null, 'areas_for_improvement' => null, 'feedback' => $feedback, 'is_anonymous' => false, 'is_completed' => true, 'submitted_at' => now()]);
                $evaluatedProgrammer = Programmer::find($evaluatedId);
                if ($evaluatedProgrammer && method_exists($evaluatedProgrammer, 'addStars')) {
                    $points = $rating * 10;
                    $evaluatedProgrammer->addStars($points, 'Received peer evaluation', ['project_id' => $project->id, 'rating' => $rating]);
                }
                $successCount++;
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => "Successfully submitted $successCount evaluation(s).", 'errors' => $errors, 'total_submitted' => $successCount, 'total_requested' => count($evaluationsData)], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error evaluating team members: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit evaluations: '.$e->getMessage()], 500);
        }
    }
    /**
 * عرض تفاصيل دعوة معينة (خاصة بالمبرمج المدعو)
 */
public function getInvitationDetails($invitationId)
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }

        // جلب الدعوة مع العلاقات
        $invitation = TeamInvitation::with([
            'team.project',
            'team.activeMembers.programmer.user',
            'team.leader.programmer.user',
            'inviter.user'
        ])->find($invitationId);

        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation not found'], 404);
        }

        // التحقق من أن الدعوة موجهة لهذا المبرمج
        if ($invitation->programmer_id !== $programmer->id) {
            return response()->json(['success' => false, 'message' => 'This invitation is not for you'], 403);
        }

        $team = $invitation->team;
        $project = $team ? $team->project : null;

        // ----- معالجة المرسل (inviter) بأمان -----
        $inviter = $invitation->inviter;
        $inviterData = null;
        if ($inviter && $inviter->user) {
            $inviterData = [
                'name'       => $inviter->user->full_name ?? 'Deleted User',
                'track'      => $inviter->track ?? 'general',
                'avatar_url' => $inviter->avatar_url,
            ];
        } else {
            $inviterData = [
                'name'       => 'Deleted User',
                'track'      => 'general',
                'avatar_url' => null,
            ];
        }

        // ----- معالجة القائد بأمان -----
        $leader = $team ? $team->leader?->programmer : null;
        $leaderData = null;
        if ($leader && $leader->user) {
            $leaderData = [
                'name'       => $leader->user->full_name,
                'track'      => $leader->track ?? 'general',
                'avatar_url' => $leader->avatar_url,
            ];
        } else {
            // إذا كان القائد محذوفاً، نعرض بيانات جزئية
            $leaderData = [
                'name'       => 'Deleted Leader',
                'track'      => 'general',
                'avatar_url' => null,
            ];
        }

        // ----- أعضاء الفريق (تجاهل المحذوفين) -----
        $members = $team ? $team->activeMembers
            ->filter(function ($member) {
                return $member->programmer && $member->programmer->user;
            })
            ->map(function ($member) {
                $prog = $member->programmer;
                return [
                    'name'       => $prog->user->full_name,
                    'avatar_url' => $prog->avatar_url,
                    'track'      => $prog->track ?? 'general',
                ];
            })->values() : collect();

        // ----- بيانات المشروع بأمان -----
        $projectData = $project ? [
            'title'       => $project->title ?? 'Deleted Project',
            'category'    => $project->category_name ?? null,
            'description' => $project->description ?? null,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'invitation_id' => $invitation->id,
                'status'        => $invitation->status,
                'invited_by'    => $inviterData,
                'team' => [
                    'name'          => $team?->name ?? 'Deleted Team',
                    'members_count' => $team ? $team->activeMembers()->count() : 0,
                    'project'       => $projectData,
                    'leader'        => $leaderData,
                    'members'       => $members,
                ],
                'expires_at' => $invitation->expires_at,
                'created_at' => $invitation->created_at,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching invitation details: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch invitation details'], 500);
    }
}
    /**
 * عرض جميع الدعوات المستلمة للمبرمج الحالي مع تفاصيل كل فريق
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function getAllMyInvitations()
{
    try {
        $user = auth()->user();
        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
        }

        $invitations = TeamInvitation::where('programmer_id', $programmer->id)
            ->with([
                'team.project',
                'team.activeMembers.programmer.user',
                'team.leader.programmer.user',
                'inviter.user'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $invitations->map(function ($invitation) {
            $team = $invitation->team;
            $project = $team ? $team->project : null;

            // --- معالجة المرسل (inviter) بأمان ---
            $inviter = $invitation->inviter;
            $inviterData = null;
            if ($inviter && $inviter->user) {
                $inviterData = [
                    'name'       => $inviter->user->full_name ?? 'Deleted User',
                    'track'      => $inviter->track ?? 'general',
                    'avatar_url' => $inviter->avatar_url,
                ];
            } else {
                $inviterData = [
                    'name'       => 'Deleted User',
                    'track'      => 'general',
                    'avatar_url' => null,
                ];
            }

            // --- معالجة القائد بأمان ---
            $leader = $team ? $team->leader?->programmer : null;
            $leaderAvatar = $leader?->avatar_url ?? null;

            // --- معالجة الأعضاء (تجاهل المحذوفين) ---
            $membersAvatars = $team ? $team->activeMembers
                ->filter(function ($member) {
                    return $member->programmer && $member->programmer->user;
                })
                ->map(function ($member) {
                    return $member->programmer->avatar_url;
                })
                ->filter()
                ->values() : collect();

            return [
                'invitation_id' => $invitation->id,
                'status'        => $invitation->status,
                'sent_at'       => $invitation->created_at,
                'expires_at'    => $invitation->expires_at,
                'invited_by'    => $inviterData,
                'team' => [
                    'name'          => $team?->name ?? 'Deleted Team',
                    'members_count' => $team ? $team->activeMembers()->count() : 0,
                    'project' => $project ? [
                        'title'       => $project->title ?? 'Deleted Project',
                        'category'    => $project->category_name ?? null,
                        'description' => $project->description ?? null,
                        'github_url'  => $project->github_url ?? null,
                    ] : null,
                    'leader_avatar'   => $leaderAvatar,
                    'members_avatars' => $membersAvatars,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching all invitations: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch invitations'], 500);
    }
}

public function getProjectTeamDetails($projectId, Request $request)
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

        // جلب المشروع مع الفريق
        $project = Project::with(['teams.activeMembers.programmer.user', 'teams.tasks.programmer.user'])
            ->find($projectId);

        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        // جلب الفريق الأول (أو الفريق اللي فيه المبرمج)
        $team = $project->teams->first(function ($t) use ($programmer) {
            return $t->isMember($programmer->id);
        });

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this project'], 403);
        }

        $myTrack = $programmer->track ?? 'general';
        $projectDescription = $project->description;
        $githubLink = $project->github_url ?? $team->github_url ?? null;

        // أعضاء الفريق
        $members = $team->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            return [
                'id' => $prog->id,
                'name' => $prog->user->full_name,
                'avatar_url' => $prog->avatar_url 
                    ? Storage::disk('public')->url($prog->avatar_url) 
                    : null,
                'track' => $prog->track ?? 'general',
                'role' => $member->role,
            ];
        });

        // التاسكات
        $tasksView = $request->query('tasks_view', 'my');
        $tasks = [];

        if ($tasksView === 'my') {
            $tasks = $team->tasks->where('programmer_id', $programmer->id)->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->deadline ? $task->deadline->toDateString() : null,
                    'priority' => $task->priority,
                    'created_at' => $task->created_at->toDateTimeString(),
                ];
            })->values();
        } else {
            $tasks = $team->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->deadline ? $task->deadline->toDateString() : null,
                    'priority' => $task->priority,
                    'assigned_to' => [
                        'id' => $task->programmer->id,
                        'name' => $task->programmer->user->full_name,
                        'avatar_url' => $task->programmer->avatar_url 
                            ? Storage::disk('public')->url($task->programmer->avatar_url) 
                            : null,
                        'track' => $task->programmer->track ?? 'general',
                    ],
                    'created_at' => $task->created_at->toDateTimeString(),
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => (int) $projectId,
                'project_name' => $project->title,
                'team_id' => $team->id,              // ← للـ reference بس
                'team_name' => $team->name,
                'project_description' => $projectDescription,
                'github_link' => $githubLink,
                'my_track' => $myTrack,
                'members' => $members,
                'tasks_view' => $tasksView,
                'tasks' => $tasks,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error in getProjectTeamDetails: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
    }
}
    /**
 * عرض تفاصيل كاملة للفريق المرتبط بمشروع معين
 * (نفس سلوك getFullTeamDetails لكن بـ projectId)
 */
public function getProjectFullDetails($projectId, Request $request)
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

        // جلب الفريق المرتبط بالمشروع
        $team = Team::with([
            'project',
            'activeMembers.programmer.user',
            'tasks.programmer.user'
        ])->where('project_id', $projectId)->first();

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'No team found for this project'], 404);
        }

        // التحقق من أن المبرمج عضو في هذا الفريق
        if (!$team->isMember($programmer->id)) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this team'], 403);
        }

        $myTrack = $programmer->track ?? 'general';
        $projectDescription = $team->project->description ?? null;
        $githubLink = $team->project->github_url ?? null;

        // أعضاء الفريق
        $members = $team->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            return [
                'id'         => $prog->id,
                'name'       => $prog->user->full_name,
                'avatar_url' => $prog->avatar_url ?: null,
                'track'      => $prog->track ?? 'general',
                'role'       => $member->role,
            ];
        });

        // تجهيز التاسكات حسب الطلب
        $tasksView = $request->query('tasks_view', 'my');
        $tasks = [];

        if ($tasksView === 'my') {
            $tasks = $team->tasks
                ->where('programmer_id', $programmer->id)
                ->map(function ($task) {
                    return [
                        'id'          => $task->id,
                        'title'       => $task->title,
                        'description' => $task->description,
                        'status'      => $task->status,
                        'due_date'    => $task->deadline ? $task->deadline->toDateString() : null,
                        'priority'    => $task->priority,
                        'created_at'  => $task->created_at->toDateTimeString(),
                    ];
                })->values();
        } else {
            $tasks = $team->tasks->map(function ($task) {
                return [
                    'id'          => $task->id,
                    'title'       => $task->title,
                    'description' => $task->description,
                    'status'      => $task->status,
                    'due_date'    => $task->deadline ? $task->deadline->toDateString() : null,
                    'priority'    => $task->priority,
                    'assigned_to' => [
                        'id'         => $task->programmer->id,
                        'name'       => $task->programmer->user->full_name,
                        'avatar_url' => $task->programmer->avatar_url ?: null,
                        'track'      => $task->programmer->track ?? 'general',
                    ],
                    'created_at'  => $task->created_at->toDateTimeString(),
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'project_id'         => (int) $projectId,
                'project_name'       => $team->project->title,
                'team_id'            => $team->id,
                'team_name'          => $team->name,
                'project_description'=> $projectDescription,
                'github_link'        => $githubLink,
                'my_track'           => $myTrack,
                'members'            => $members,
                'tasks_view'         => $tasksView,
                'tasks'              => $tasks,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error in getProjectFullDetails: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
    }
}
}

