<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Programmer;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Project;
use App\Services\TeamMatchingService;
use App\Services\AITeamRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use App\Notifications\SendInvitationNotification;


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

    /**
     * @OA\Get(
     *     path="/api/teams",
     *     operationId="getTeams",
     *     tags={"Teams"},
     *     summary="List all teams with filtering",
     *     description="Returns a paginated list of teams. Supports filtering by status, project, experience level, and publicity.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Team status (active, closed, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", example="active")
     *     ),
     *     @OA\Parameter(
     *         name="project_id",
     *         in="query",
     *         description="Filter by project ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="experience_level",
     *         in="query",
     *         description="Required experience level",
     *         required=false,
     *         @OA\Schema(type="string", enum={"beginner","intermediate","advanced","expert"})
     *     ),
     *     @OA\Parameter(
     *         name="is_public",
     *         in="query",
     *         description="Filter public/private teams",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */

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
/**
     * @OA\Get(
     *     path="/api/teams/{id}",
     *     operationId="showTeam",
     *     tags={"Teams"},
     *     summary="Get a single team by ID",
     *     description="Returns detailed information about a specific team, including members count and available slots.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Team not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
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
/**
     * @OA\Get(
     *     path="/api/teams/{id}/details",
     *     operationId="getTeamDetails",
     *     tags={"Teams"},
     *     summary="Get simplified team details",
     *     description="Returns a lightweight version of team info: name, project title, and member list.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Team not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
public function getTeamDetails($id)
{
    try {
        $team = Team::with(['project', 'activeMembers.programmer.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'github_link' => $team->project->github_url ?? null,  // رابط الـ GitHub بدلاً من project_title
                'project_description' => $team->project->description,
                'members' => $team->activeMembers->map(function($member) {
                    return [
                        'programmer_id' => $member->programmer_id,
                        'name' => $member->programmer->user->full_name,
                        'track' => $member->programmer->track ?? 'general', // التراك بدلاً من role
                        'avatar_url' => $member->programmer->avatar_url,   // إضافة الصورة
                    ];
                })
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching team details: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch team details'], 500);
    }
}
/**
     * @OA\Post(
     *     path="/api/teams/{teamId}/change-leader/{programmerId}",
     *     operationId="swapLeader",
     *     tags={"Teams"},
     *     summary="Transfer leadership to another team member",
     *     description="Allows the current leader (or admin) to assign leadership to a different programmer in the same team.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="programmerId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leadership transferred",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Team or programmer not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
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
/**
     * @OA\Delete(
     *     path="/api/teams/{id}/soft-delete",
     *     operationId="softDeleteTeam",
     *     tags={"Teams"},
     *     summary="Soft‑delete a team",
     *     description="Marks the team as deleted (soft delete) and records the leave time for all members. Only the leader or admin can perform this.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team soft‑deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Team not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
public function softDeleteTeam($id)
{
    $user = Auth::user();
    $team = Team::findOrFail($id);
    $isLeader = $team->isLeader($user->programmer->id);
    if (!$isLeader && $user->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    $team->delete(); // soft delete (بفضل SoftDeletes في الموديل)
    $team->activeMembers()->update(['left_at' => now()]);
    return response()->json(['success' => true, 'message' => 'Team soft deleted']);
}
/**
 * @OA\Post(
 *     path="/api/teams",
 *     operationId="createTeam",
 *     tags={"Teams"},
 *     summary="Create a new team",
 *     description="Create a new team with full configuration including type (public/private), team details, categories, required skills, and optional invitations for private teams.",
 *     security={{"Bearer": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "description", "is_public", "github_url", "categories", "skills"},
 *             @OA\Property(
 *                 property="name",
 *                 type="string",
 *                 maxLength=255,
 *                 example="Frontend Squad",
 *                 description="Team name"
 *             ),
 *             @OA\Property(
 *                 property="description",
 *                 type="string",
 *                 minLength=10,
 *                 example="A team focused on React and Vue.js development",
 *                 description="Team description (minimum 10 characters)"
 *             ),
 *             @OA\Property(
 *                 property="is_public",
 *                 type="boolean",
 *                 example=true,
 *                 description="Team visibility: true for public, false for private"
 *             ),
 *             @OA\Property(
 *                 property="github_url",
 *                 type="string",
 *                 format="url",
 *                 example="https://github.com/org/team-repo",
 *                 description="GitHub repository URL"
 *             ),
 *             @OA\Property(
 *                 property="categories",
 *                 type="array",
 *                 minItems=1,
 *                 example={"Frontend", "UI/UX"},
 *                 description="Team categories (can select multiple)",
 *                 @OA\Items(
 *                     type="string",
 *                     maxLength=100
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="skills",
 *                 type="array",
 *                 minItems=1,
 *                 example={1, 2, 5},
 *                 description="Required skills (array of skill IDs)",
 *                 @OA\Items(
 *                     type="integer"
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="max_members",
 *                 type="integer",
 *                 minimum=2,
 *                 maximum=20,
 *                 example=5,
 *                 description="Maximum team members (optional, default: 5)"
 *             ),
 *             @OA\Property(
 *                 property="experience_level",
 *                 type="string",
 *                 enum={"beginner", "intermediate", "advanced", "expert"},
 *                 example="intermediate",
 *                 description="Required experience level (optional)"
 *             ),
 *             @OA\Property(
 *                 property="invitations",
 *                 type="array",
 *                 example={7, 12, 15},
 *                 description="Programmer IDs to invite (only for private teams, required if is_public=false)",
 *                 @OA\Items(
 *                     type="integer"
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Team created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Team created successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="team",
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="description", type="string"),
 *                     @OA\Property(property="is_public", type="boolean"),
 *                     @OA\Property(property="github_url", type="string"),
 *                     @OA\Property(property="status", type="string"),
 *                     @OA\Property(property="max_members", type="integer"),
 *                     @OA\Property(property="experience_level", type="string"),
 *                     @OA\Property(
 *                         property="categories",
 *                         type="array",
 *                         @OA\Items(type="string")
 *                     ),
 *                     @OA\Property(
 *                         property="created_by",
 *                         type="object",
 *                         @OA\Property(property="id", type="integer"),
 *                         @OA\Property(property="name", type="string"),
 *                         @OA\Property(property="role", type="string", example="leader")
 *                     )
 *                 ),
 *                 @OA\Property(
 *                     property="skills",
 *                     type="array",
 *                     description="Required skills names",
 *                     @OA\Items(type="string")
 *                 ),
 *                 @OA\Property(
 *                     property="invitations_sent",
 *                     type="array",
 *                     description="List of sent invitations (for private teams)",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="programmer_id", type="integer"),
 *                         @OA\Property(property="invitation_id", type="integer")
 *                     )
 *                 ),
 *                 @OA\Property(property="members_count", type="integer", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Only programmers can create teams",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Server error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string")
 *         )
 *     )
 * )
 */
public function store(Request $request)
{
    DB::beginTransaction();
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can create teams'
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
            'name'             => 'required|string|max:255',
            'description'      => 'required|string|min:10',
            'is_public'        => 'required|boolean',
            'github_url'       => 'required|url',
            'categories'       => 'required|array|min:1',
            'categories.*'     => 'string|max:100',
            'required_tracks'  => 'required|array|min:1',
            'required_tracks.*'=> 'string|max:50',
            'invitations'      => 'nullable|array',
            'invitations.*'    => 'string|exists:programmers,user_name',
        ]);

        if (!$validated['is_public'] && empty($validated['invitations'])) {
            return response()->json([
                'success' => false,
                'message' => 'Private teams must include at least one invitation'
            ], 422);
        }

        // إنشاء مشروع بالأعمدة الجديدة
        $project = Project::create([
            'title'             => $validated['name'],
            'description'       => $validated['description'],
            'github_url'        => $validated['github_url'],
            'categories'        => json_encode($validated['categories']),
            'required_tracks'   => json_encode($validated['required_tracks']),
            'category_name'     => $validated['categories'][0] ?? null, // للإبقاء على التوافق
            'status'            => 'pending',
            'difficulty'        => 'intermediate', // يمكن إضافته للطلب لاحقاً
            'estimated_duration_days' => 30,
            'max_team_size'     => 5,
            'num_of_team'       => 1,
            'user_id'           => $programmer->user_id,
            'team_size'         => 5,
            'min_team_size'     => 2,
            'max_teams'         => 1,
        ]);

        // إنشاء فريق
        $team = Team::create([
            'name'            => $validated['name'],
            'project_id'      => $project->id,
            'is_public'       => $validated['is_public'],
            'status'          => 'active',
            'formation_type'  => 'manual',
            'created_by'      => $programmer->id,
            'join_code'       => $validated['is_public'] ? null : strtoupper(substr(md5(uniqid()), 0, 8)),
        ]);

        // إضافة المنشئ كقائد
        TeamMember::create([
            'team_id'       => $team->id,
            'programmer_id' => $programmer->id,
            'role'          => 'leader',
            'joined_at'     => now(),
            'joined_by'     => $programmer->id,
        ]);

       // معالجة الدعوات (بعد إزالة القيود)
$invitationsSent = [];
if (!$validated['is_public'] && !empty($validated['invitations'])) {
    foreach ($validated['invitations'] as $username) {
        if ($username === $programmer->user_name) continue;

        $invitedProgrammer = Programmer::where('user_name', $username)->first();
        
        // ✅ فقط نتحقق من وجود المستخدم
        if (!$invitedProgrammer) {
            Log::warning("Programmer not found: $username");
            continue;
        }
        
        // منع الدعوات المكررة المعلقة
        $existing = TeamInvitation::where('team_id', $team->id)
            ->where('programmer_id', $invitedProgrammer->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) continue;

        $invitation = TeamInvitation::create([
    'team_id'      => $team->id,
    'programmer_id'=> $invitedProgrammer->id,
    'invited_by'   => $programmer->id,
    'message'      => "You've been invited to join team '{$team->name}'",
    'status'       => 'pending',
    'expires_at'   => now()->addDays(7),
]);

// إرسال الإشعار
$invitedProgrammer->user->notify(new SendInvitationNotification($invitation));

        $invitationsSent[] = [
            'username'      => $username,
            'programmer_id' => $invitedProgrammer->id,
            'invitation_id' => $invitation->id,
        ];
    }
}

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully',
            'data' => [
                'project' => $project->fresh(),
                'team' => $team,
                'invitations_sent' => $invitationsSent,
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error creating team: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to create team',
            'error'   => $e->getMessage()
        ], 500);
    }
}
    /**
     * @OA\Post(
     *     path="/api/teams/{id}/invite",
     *     operationId="inviteByUsername",
     *     tags={"Teams"},
     *     summary="Invite a programmer by username",
     *     description="Team leader can invite a programmer (by username) to join the team. Invitation expires after 7 days by default.",
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username"},
     *             @OA\Property(property="username", type="string", example="john_doe"),
     *             @OA\Property(property="message", type="string", example="We'd love to have you on board!"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-06-01T12:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invitation sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Only leader can invite"),
     *     @OA\Response(response=404, description="Team or user not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
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

    // في TeamController.php
public function updateTeam(Request $request, $id)
{
    try {
        $user = Auth::user();
        $team = Team::findOrFail($id);

        // التحقق من الصلاحية: فقط قائد الفريق أو الأدمن
        $isLeader = $team->isLeader($user->programmer->id);
        if (!$isLeader && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only team leader can update team settings'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'github_url' => 'nullable|url',
            'avatar_url' => 'nullable|url',
            'is_public' => 'nullable|boolean',
            'category' => 'nullable|array',
            'required_role' => 'nullable|array',
            'experience_level' => 'nullable|in:beginner,intermediate,advanced,expert',
        ]);

        $team->update($validated);

        // إذا تم تحديث is_public وتم جعله خاصاً، يمكن إعادة توليد join_code
        if ($request->has('is_public') && !$request->is_public && !$team->join_code) {
            $team->join_code = strtoupper(substr(md5(uniqid()), 0, 8));
            $team->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Team settings updated successfully',
            'data' => $team->fresh(['activeMembers.programmer.user'])
        ]);
    } catch (\Exception $e) {
        Log::error('Error updating team: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to update team'], 500);
    }
}
    /**
 * عرض تفاصيل فريق معين للمبرمج الحالي (مشروع، أعضاء، تاسكات)
 * 
 * @param int $teamId
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getFullTeamDetails($teamId, Request $request)
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

        // جلب الفريق مع المشروع والأعضاء والمهام
        $team = Team::with([
            'project',
            'activeMembers.programmer.user',
            'tasks.programmer.user' // لجلب المهام مع البرمجة المسند إليها
        ])->find($teamId);

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        // التحقق من أن المبرمج الحالي عضو في هذا الفريق
        if (!$team->isMember($programmer->id)) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this team'], 403);
        }

        // التراك الخاص بي
        $myTrack = $programmer->track ?? 'general';

        // وصف المشروع
        $projectDescription = $team->project->description ?? null;

        // رابط GitHub (من المشروع)
        $githubLink = $team->project->github_url ?? null;

        // أعضاء الفريق (الاسم، الصورة، التراك)
        $members = $team->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            return [
                'id'         => $prog->id,
                'name'       => $prog->user->full_name,
                'avatar_url' => $prog->avatar_url,
                'track'      => $prog->track ?? 'general',
                'role'       => $member->role,
            ];
        });

        // تجهيز التاسكات حسب الطلب
        $tasksView = $request->query('tasks_view', 'my'); // my أو team
        $tasks = [];

        if ($tasksView === 'my') {
            // تاسكات المبرمج الحالي فقط في هذا الفريق
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
            // تاسكات جميع أعضاء الفريق
            $tasks = $team->tasks->map(function ($task) {
                return [
                    'id'             => $task->id,
                    'title'          => $task->title,
                    'description'    => $task->description,
                    'status'         => $task->status,
                    'due_date'       => $task->deadline ? $task->deadline->toDateString() : null,
                    'priority'       => $task->priority,
                    'assigned_to'    => [
                        'id'         => $task->programmer->id,
                        'name'       => $task->programmer->user->full_name,
                        'avatar_url' => $task->programmer->avatar_url,
                        'track'      => $task->programmer->track ?? 'general',
                    ],
                    'created_at'     => $task->created_at->toDateTimeString(),
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
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
        Log::error('Error in getFullTeamDetails: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch team details'
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
    /**
 * عرض أعضاء الفريق فقط (مع الاسم، التراك، الصورة)
 * 
 * @param int $teamId
 * @return \Illuminate\Http\JsonResponse
 */
public function getTeamMembersList($teamId)
{
    try {
        $team = Team::with('activeMembers.programmer.user')->find($teamId);
        
        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found'
            ], 404);
        }
        
        $members = $team->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            return [
                'programmer_id' => $prog->id,
                'name' => $prog->user->full_name,
                'track' => $prog->track ?? 'general',
                'avatar_url' => $prog->avatar_url,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $members
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error fetching team members list: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch team members'
        ], 500);
    }
}
    public function getTeamMembersWithRatings($teamId)
{
    try {
        $team = Team::with(['project', 'activeMembers.programmer.user'])->findOrFail($teamId);
        
        $members = $team->activeMembers->map(function ($member) {
            $prog = $member->programmer;
            // حساب متوسط التقييمات (من 1 إلى 10) وتحويله إلى نجوم من 5
            $avgScore = Evaluation::where('evaluated_id', $prog->id)
                ->where('team_id', $team->id) // تقييمات هذا الفريق فقط
                ->avg('average_score') ?? 0;
            $stars = round($avgScore / 2, 1); // تحويل 1-10 إلى 0.5-5
            
            // جلب أحدث feedback (اختياري)
            $latestFeedback = Evaluation::where('evaluated_id', $prog->id)
                ->where('team_id', $team->id)
                ->whereNotNull('feedback')
                ->orderBy('created_at', 'desc')
                ->value('feedback');
            
            return [
                'programmer_id' => $prog->id,
                'name' => $prog->user->full_name,
                'avatar_url' => $prog->avatar_url,
                'track' => $prog->track ?? 'general',
                'average_rating' => $stars, // من 5
                'latest_feedback' => $latestFeedback,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'project_name' => $team->project->title,
                'project_description' => $team->project->description,
                'members' => $members,
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error in getTeamMembersWithRatings: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch data'], 500);
    }
}
}

   
