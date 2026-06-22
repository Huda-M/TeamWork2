<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyProgrammerController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\JoinRequestController;  // ← أضف ده
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgrammerController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────
// PUBLIC ROUTES
// ─────────────────────────────────────────
Route::post('/register', [RegisteredUserController::class, 'register']);
Route::post('/register/verify', [RegisteredUserController::class, 'verifyAndCreate']);
Route::post('/register/resend-code', [RegisteredUserController::class, 'resendCode']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password/verify', [NewPasswordController::class, 'verifyResetCode']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);
Route::post('/email/verify', [VerifyEmailController::class, 'verify']);

// Social Authentication (with session middleware)
Route::middleware('start.session')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
    Route::post('/auth/social/complete', [SocialAuthController::class, 'completeSocialRegistration']);
});

// Public API v1 (Read-only)
Route::prefix('v1')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/programmers', [ProgrammerController::class, 'index']);
    Route::get('/programmers/{id}', [ProgrammerController::class, 'show']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::get('/projects/{id}/teams', [ProjectController::class, 'teams']);
    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/skills/popular', [SkillController::class, 'popular']);
    Route::get('/skills/{id}', [SkillController::class, 'show']);
});

// ─────────────────────────────────────────
// AUTHENTICATED ROUTES (Sanctum)
// ─────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ─── External route files ───
    require_once __DIR__.'/chat.routes.php';
    require_once __DIR__.'/notifications.routes.php';

    // ─── Projects (كل حاجة بـ project_id) ───
    Route::prefix('projects')->group(function () {
        // كل المشاريع
        Route::get('/', [ProjectController::class, 'myProjects']);
        
        // تفاصيل مشروع
        Route::get('/{projectId}/details', [ProjectController::class, 'myProjectDetails']);
        
        // تاسكات المشروع
        Route::get('/{projectId}/tasks', [TaskController::class, 'getProjectTasks']);
        
        // تفاصيل فريق المشروع
        Route::get('/{projectId}/team', [TeamController::class, 'getProjectTeamDetails']);
        
        // إنشاء task في المشروع
        Route::post('/{projectId}/tasks', [TaskController::class, 'storeProjectTask']);
        
        // Zero project
        Route::get('/{projectId}/zero', [ProjectController::class, 'zeroProject']);
        
        // تعليم مشروع كمكتمل
        Route::patch('/{projectId}/complete', [ProjectController::class, 'markAsCompleted']);
        
        // تغيير قائد الفريق
        Route::post('/{projectId}/change-leader/{programmerId}', [TeamController::class, 'swapProjectLeader']);
        
        // تحديث فريق المشروع
        Route::put('/{projectId}/team', [TeamController::class, 'updateProjectTeam']);
        
        // حذف فريق المشروع
        Route::delete('/{projectId}/team', [TeamController::class, 'softDeleteProjectTeam']);
    });

    // ─── Invitations ───
    Route::get('/invitations', [TeamController::class, 'getAllMyInvitations']);
    Route::get('/invitations/{invitationId}/details', [TeamController::class, 'getInvitationDetails']);

    // ─── Join Requests ───
    Route::post('/teams/{team}/join-requests', [JoinRequestController::class, 'store']);
    Route::get('/teams/join-requests', [JoinRequestController::class, 'index']);
    Route::get('/teams/{team}/join-requests', [JoinRequestController::class, 'teamJoinRequests']);
    Route::put('/join-requests/{joinRequest}', [JoinRequestController::class, 'update']);

    // ─── Search ───
    Route::get('/search/programmers', [ProgrammerController::class, 'searchByUsername']);

    // ─── Evaluations ───
    Route::post('/teams/{teamId}/evaluate-all', [TeamController::class, 'evaluateTeamMembers']);
    Route::get('/teams/{teamId}/my-ratings', [TeamController::class, 'getTeamMembersWithMyRatings']);
    Route::get('/teams/{teamId}/members-with-ratings', [TeamController::class, 'getTeamMembersWithRatings']);
    Route::get('/teams/{teamId}/members-list', [TeamController::class, 'getTeamMembersList']);

    // ─── Profile & Dashboard ───
    Route::get('/my/level-progression', [ProgrammerController::class, 'levelProgression']);
    Route::get('/my/dashboard', [ProgrammerController::class, 'dashboard']);
    Route::get('/my/statistics', [ProgrammerController::class, 'myStatistics']);

    // ─── Tasks (general) ───
    Route::prefix('tasks')->group(function () {
        Route::get('/my', [TaskController::class, 'getMyTasks']);
        Route::get('/completed', [TaskController::class, 'completedTasks']);
        Route::get('/in-progress', [TaskController::class, 'inProgressTasks']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::get('/{task}/history', [TaskController::class, 'getTaskHistory']);
        Route::put('/{task}', [TaskController::class, 'update']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);
        Route::post('/{task}/assign', [TaskController::class, 'assignTask']);
        Route::post('/{task}/update-status', [TaskController::class, 'updateStatus']);
    });

    // ─── Task actions (outside prefix) ───
    Route::post('/{task}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::post('/{task}/mark-as-done', [TaskController::class, 'markAsDone']);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'markAsCompleted']);

    // ─── Profile ───
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'myProfile']);
        Route::post('/update', [ProfileController::class, 'updateProfile']);
        Route::get('/my-stats', [ProfileController::class, 'myStats']);
        Route::get('/my-evaluations', [ProfileController::class, 'myEvaluations']);
        Route::get('/team-members/{projectId}/to-evaluate', [ProfileController::class, 'teamMembersToEvaluate']);
        Route::post('/evaluate/{projectId}/{evaluatedId}', [ProfileController::class, 'submitEvaluation']);
        Route::delete('/soft-delete', [ProfileController::class, 'softDeleteAccount']);
        Route::get('/project-details/{projectId}', [ProfileController::class, 'projectDetails']);
    });

    // ─── Teams (legacy — للـ operations اللي لسه محتاجة team_id) ───
    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{id}', [TeamController::class, 'showTeam']);
        Route::get('/{id}/members', [TeamController::class, 'teamMembers']);
        Route::post('/{id}/leave', [TeamController::class, 'leaveTeam']);
        Route::post('/{id}/update-member-role/{programmerId}', [TeamController::class, 'updateMemberRole']);
        Route::delete('/{id}/remove-member/{programmerId}', [TeamController::class, 'removeMember']);
        Route::post('/{id}/invite-by-username', [TeamController::class, 'inviteByUsername']);
        Route::post('/{id}/bulk-invite', [TeamController::class, 'bulkInviteByUsernames']);
        Route::get('/my/invitations', [TeamController::class, 'getMyInvitations']);
        Route::post('/invitations/{invitationId}/accept', [TeamController::class, 'acceptInvitationById']);
        Route::post('/invitations/{invitationId}/decline', [TeamController::class, 'declineInvitationById']);
        Route::get('/{id}/invitations', [TeamController::class, 'invitations']);
        Route::post('/{id}/request-to-join', [TeamController::class, 'requestToJoin']);
        Route::get('/{id}/join-requests', [TeamController::class, 'joinRequests']);
        Route::get('/ai/recommendations', [TeamController::class, 'getAIRandomRecommendations']);
        Route::get('/recommendations', [TeamController::class, 'getRecommendations']);
        Route::post('/join/ai', [TeamController::class, 'joinViaAIRecommendation']);
        Route::get('/mixed/options', [TeamController::class, 'mixedTeamJoining']);
        Route::post('/join/mixed', [TeamController::class, 'joinViaMixedMethod']);
        Route::get('/{id}/statistics', [TeamController::class, 'teamStatistics']);
    });

    // ─── Company ───
    Route::prefix('company')->group(function () {
        Route::get('/profile', [CompanyController::class, 'showProfile']);
        Route::put('/profile', [CompanyController::class, 'updateProfile']);
        Route::delete('/soft-delete', [CompanyController::class, 'softDelete']);
        Route::get('/programmers', [CompanyProgrammerController::class, 'index']);
        Route::get('/programmers/{id}', [CompanyProgrammerController::class, 'show']);
    });

    // ─── User & Auth ───
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/register/complete-profile', [RegisteredUserController::class, 'completeProfile']);
    Route::get('/profile/status', [RegisteredUserController::class, 'profileStatus']);
    Route::post('/change-password', [NewPasswordController::class, 'changePassword']);

    // ─── Evaluations ───
    Route::prefix('evaluations')->group(function () {
        Route::post('/projects/{projectId}/teams/{teamId}/start', [EvaluationController::class, 'startEvaluation']);
        Route::post('/projects/{projectId}/teams/{teamId}', [EvaluationController::class, 'store']);
        Route::get('/projects/{projectId}', [EvaluationController::class, 'index']);
        Route::get('/my/as-evaluator', [EvaluationController::class, 'myEvaluationsAsEvaluator']);
        Route::get('/my/as-evaluated', [EvaluationController::class, 'myEvaluationsAsEvaluated']);
        Route::get('/programmer/{programmerId}/stats', [EvaluationController::class, 'programmerStats']);
    });

    // ─── Reports ───
    Route::prefix('reports')->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/my', [ReportController::class, 'myReports']);
        Route::get('/against-me', [ReportController::class, 'reportsAgainstMe']);
        Route::get('/check-status', [ReportController::class, 'checkUserStatus']);
    });

    // ─── Admin Reports ───
    Route::prefix('reports')->middleware('role:admin')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/statistics', [ReportController::class, 'statistics']);
        Route::get('/{report}', [ReportController::class, 'show']);
        Route::put('/{report}', [ReportController::class, 'update']);
        Route::delete('/{report}', [ReportController::class, 'destroy']);
    });

    // ─── Programmer Stats ───
    Route::get('/programmers/{id}/statistics', [ProgrammerController::class, 'programmerStatistics']);

    // ─── User Projects ───
    Route::get('/users/{userId}/projects', [ProjectController::class, 'getUserProjects']);

    // ─── Report Info ───
    Route::get('/user/{id}/report-info', [ReportController::class, 'getUserReportInfo']);

    // ─── Company Profile ───
    Route::post('/company/complete-profile', [RegisteredUserController::class, 'completeCompanyProfile']);

    // ─── v1 CRUD (Admin mostly) ───
    Route::prefix('v1')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/programmers/{id}', [ProgrammerController::class, 'update']);
        Route::delete('/programmers/{id}', [ProgrammerController::class, 'destroy']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::put('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
        Route::post('/skills', [SkillController::class, 'store']);
        Route::put('/skills/{id}', [SkillController::class, 'update']);
        Route::delete('/skills/{id}', [SkillController::class, 'destroy']);
    });

});

Broadcast::routes(['middleware' => ['auth:sanctum']]);

require_once __DIR__.'/ai.routes.php';
require_once __DIR__.'/Companies/auth.routes.php';
require_once __DIR__.'/Companies/programmer.routes.php';
require_once __DIR__.'/Companies/offer.routes.php';
