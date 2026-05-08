<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ProgrammerController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes - Public (No Authentication)
|--------------------------------------------------------------------------
*/
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
    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);

    // Programmers
    Route::get('/programmers', [ProgrammerController::class, 'index']);
    Route::get('/programmers/{id}', [ProgrammerController::class, 'show']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::get('/projects/{id}/teams', [ProjectController::class, 'teams']);

    // Skills
    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/skills/popular', [SkillController::class, 'popular']);
    Route::get('/skills/{id}', [SkillController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // User profile & general
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/register/complete-profile', [RegisteredUserController::class, 'completeProfile']);
    Route::get('/profile/status', [RegisteredUserController::class, 'profileStatus']);
    Route::post('/change-password', [NewPasswordController::class, 'changePassword']);

    // Programmer specific
    Route::get('/my/statistics', [ProgrammerController::class, 'myStatistics']);
    Route::get('/programmers/{id}/statistics', [ProgrammerController::class, 'programmerStatistics']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [UserController::class, 'getNotifications']);
        Route::get('/unread-count', [UserController::class, 'getUnreadCount']);
        Route::post('/{notificationId}/read', [UserController::class, 'markNotificationAsRead']);
        Route::post('/read-all', [UserController::class, 'markAllNotificationsAsRead']);
        Route::delete('/{notificationId}', [UserController::class, 'deleteNotification']);
        Route::delete('/read/all', [UserController::class, 'deleteReadNotifications']);
    });

    // Projects related (authenticated)
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    Route::get('/my-projects/{projectId}/details', [ProjectController::class, 'myProjectDetails']);
    Route::get('/projects/{projectId}/tasks', [ProjectController::class, 'projectTasks']);
    Route::get('/users/{userId}/projects', [ProjectController::class, 'getUserProjects']);
    Route::patch('/projects/{projectId}/complete', [ProjectController::class, 'markAsCompleted'])->middleware('role:admin');

    // Tasks
    Route::prefix('tasks')->group(function () {
        Route::get('/my', [TaskController::class, 'getMyTasks']);
        Route::get('/completed', [TaskController::class, 'completedTasks']);
        Route::get('/in-progress', [TaskController::class, 'inProgressTasks']);
        Route::get('/team/{team}', [TaskController::class, 'getTeamTasks']);
        Route::get('/team/{team}/stats', [TaskController::class, 'getTeamTaskStats']);
        Route::post('/team/{team}', [TaskController::class, 'store']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::get('/{task}/history', [TaskController::class, 'getTaskHistory']);
        Route::put('/{task}', [TaskController::class, 'update']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);
        Route::post('/{task}/assign', [TaskController::class, 'assignTask']);
        Route::post('/{task}/update-status', [TaskController::class, 'updateStatus']);
    });

Route::prefix('profile')->group(function () {
    Route::put('/update', [ProfileController::class, 'updateProfile']);
    Route::get('/my-stats', [ProfileController::class, 'myStats']);
    Route::get('/my-evaluations', [ProfileController::class, 'myEvaluations']);
    Route::get('/team-members/{projectId}/to-evaluate', [ProfileController::class, 'teamMembersToEvaluate']);
    Route::post('/evaluate/{projectId}/{evaluatedId}', [ProfileController::class, 'submitEvaluation']);
    Route::delete('/soft-delete', [ProfileController::class, 'softDeleteAccount']);
    Route::get('/zero-project/{projectId}', [ProfileController::class, 'zeroProject']);
    Route::get('/project-details/{projectId}', [ProfileController::class, 'projectDetails']);
});

    // Teams
    Route::prefix('teams')->group(function () {
        // General team operations
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{id}', [TeamController::class, 'showTeam']);
        Route::get('/{id}/details', [TeamController::class, 'getTeamDetails']);
        Route::put('/{id}', [TeamController::class, 'updateTeam']);
        Route::delete('/{id}', [TeamController::class, 'destroyTeam']);               // hard delete
        Route::delete('/{id}/soft', [TeamController::class, 'softDeleteTeam']);     // soft delete

        // Members management
        Route::get('/{id}/members', [TeamController::class, 'teamMembers']);
        Route::post('/{id}/leave', [TeamController::class, 'leaveTeam']);
        Route::post('/{id}/update-member-role/{programmerId}', [TeamController::class, 'updateMemberRole']);
        Route::delete('/{id}/remove-member/{programmerId}', [TeamController::class, 'removeMember']);
        Route::post('/{teamId}/swap-leader/{programmerId}', [TeamController::class, 'swapLeader']);
        Route::post('/{teamId}/change-leader/{programmerId}', [TeamController::class, 'swapLeader']); // alias

        // Invitations
        Route::post('/{id}/invite-by-username', [TeamController::class, 'inviteByUsername']);
        Route::post('/{id}/bulk-invite', [TeamController::class, 'bulkInviteByUsernames']);
        Route::get('/my/invitations', [TeamController::class, 'getMyInvitations']);
        Route::post('/invitations/{invitationId}/accept', [TeamController::class, 'acceptInvitationById']);
        Route::post('/invitations/{invitationId}/decline', [TeamController::class, 'declineInvitationById']);
        Route::get('/{id}/invitations', [TeamController::class, 'invitations']);

        // Join requests
        Route::post('/{id}/request-to-join', [TeamController::class, 'requestToJoin']);
        Route::get('/{id}/join-requests', [TeamController::class, 'joinRequests']);

        // AI & recommendations
        Route::get('/ai/recommendations', [TeamController::class, 'getAIRandomRecommendations']);
        Route::get('/recommendations', [TeamController::class, 'getRecommendations']);
        Route::post('/join/ai', [TeamController::class, 'joinViaAIRecommendation']);

        // Mixed teams
        Route::get('/mixed/options', [TeamController::class, 'mixedTeamJoining']);
        Route::post('/join/mixed', [TeamController::class, 'joinViaMixedMethod']);

        // Statistics
        Route::get('/{id}/statistics', [TeamController::class, 'teamStatistics']);
    });

    // Evaluations
    Route::prefix('evaluations')->group(function () {
        Route::post('/projects/{projectId}/teams/{teamId}/start', [EvaluationController::class, 'startEvaluation']);
        Route::post('/projects/{projectId}/teams/{teamId}', [EvaluationController::class, 'store']);
        Route::get('/projects/{projectId}', [EvaluationController::class, 'index']);
        Route::get('/my/as-evaluator', [EvaluationController::class, 'myEvaluationsAsEvaluator']);
        Route::get('/my/as-evaluated', [EvaluationController::class, 'myEvaluationsAsEvaluated']);
        Route::get('/programmer/{programmerId}/stats', [EvaluationController::class, 'programmerStats']);
    });

    // Reports (with status check)
    Route::prefix('reports')->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/my', [ReportController::class, 'myReports']);
        Route::get('/against-me', [ReportController::class, 'reportsAgainstMe']);
        Route::get('/check-status', [ReportController::class, 'checkUserStatus']);
    });

    // Admin only reports management
    Route::prefix('reports')->middleware('role:admin')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/statistics', [ReportController::class, 'statistics']);
        Route::get('/{report}', [ReportController::class, 'show']);
        Route::put('/{report}', [ReportController::class, 'update']);
        Route::delete('/{report}', [ReportController::class, 'destroy']);
    });

    // CRUD operations v1 (mostly for admins or owners)
    Route::prefix('v1')->group(function () {
        // Users (admin only recommended)
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Programmers
        Route::put('/programmers/{id}', [ProgrammerController::class, 'update']);
        Route::delete('/programmers/{id}', [ProgrammerController::class, 'destroy']);

        // Projects
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::put('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

        // Skills
        Route::post('/skills', [SkillController::class, 'store']);
        Route::put('/skills/{id}', [SkillController::class, 'update']);
        Route::delete('/skills/{id}', [SkillController::class, 'destroy']);
    });

});
