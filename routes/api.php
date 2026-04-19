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


Route::post('/register', [RegisteredUserController::class, 'register']);
Route::post('/register/verify', [RegisteredUserController::class, 'verifyAndCreate']);
Route::post('/register/resend-code', [RegisteredUserController::class, 'resendCode']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password/verify', [NewPasswordController::class, 'verifyResetCode']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);
Route::post('/email/verify', [VerifyEmailController::class, 'verify']);

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
Route::post('/auth/social/complete', [SocialAuthController::class, 'completeSocialRegistration']);

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

Route::middleware('auth:sanctum', 'check.user.status')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/register/complete-profile', [RegisteredUserController::class, 'completeProfile']);
    Route::get('/profile/status', [RegisteredUserController::class, 'profileStatus']);
    Route::post('/change-password', [NewPasswordController::class, 'changePassword']);

    Route::prefix('notifications')->group(function () {
        Route::get('/', [UserController::class, 'getNotifications']);
        Route::get('/unread-count', [UserController::class, 'getUnreadCount']);
        Route::post('/{notificationId}/read', [UserController::class, 'markNotificationAsRead']);
        Route::post('/read-all', [UserController::class, 'markAllNotificationsAsRead']);
        Route::delete('/{notificationId}', [UserController::class, 'deleteNotification']);
        Route::delete('/read/all', [UserController::class, 'deleteReadNotifications']);
    });

    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{id}', [TeamController::class, 'showTeam']);
        Route::put('/{id}', [TeamController::class, 'updateTeam']);
        Route::delete('/{id}', [TeamController::class, 'destroyTeam']);

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
        Route::post('/join/ai', [TeamController::class, 'joinViaAIRecommendation']);
        Route::get('/recommendations', [TeamController::class, 'getRecommendations']);

        Route::get('/mixed/options', [TeamController::class, 'mixedTeamJoining']);
        Route::post('/join/mixed', [TeamController::class, 'joinViaMixedMethod']);


        Route::post('/{id}/start-voting', [TeamController::class, 'startVoting']);
        Route::post('/{id}/vote', [TeamController::class, 'vote']);
        Route::get('/{id}/voting-status', [TeamController::class, 'votingStatus']);

        Route::get('/{id}/statistics', [TeamController::class, 'teamStatistics']);
    });

Route::prefix('tasks')->group(function () {
    Route::get('/team/{team}', [TaskController::class, 'getTeamTasks']);
    Route::get('/team/{team}/stats', [TaskController::class, 'getTeamTaskStats']);
    Route::post('/team/{team}', [TaskController::class, 'store']);

    Route::get('/my', [TaskController::class, 'getMyTasks']);
    Route::get('/{task}/history', [TaskController::class, 'getTaskHistory']);

    Route::put('/{task}', [TaskController::class, 'update']);
    Route::delete('/{task}', [TaskController::class, 'destroy']);
    Route::post('/{task}/assign', [TaskController::class, 'assignTask']);
    Route::post('/{task}/update-status', [TaskController::class, 'updateStatus']);

});

    Route::prefix('v1')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::put('/programmers/{id}', [ProgrammerController::class, 'update']);
        Route::delete('/programmers/{id}', [ProgrammerController::class, 'destroy']);

        Route::post('/projects', [ProjectController::class, 'store']);
        Route::put('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
        Route::get('/users/{userId}/projects', [ProjectController::class, 'getUserProjects']);

        Route::post('/skills', [SkillController::class, 'store']);
        Route::put('/skills/{id}', [SkillController::class, 'update']);
        Route::delete('/skills/{id}', [SkillController::class, 'destroy']);
    });
});
Route::prefix('evaluations')->group(function () {
    Route::post('/projects/{projectId}/teams/{teamId}/start', [EvaluationController::class, 'startEvaluation']);

    Route::post('/projects/{projectId}/teams/{teamId}', [EvaluationController::class, 'store']);

    Route::get('/projects/{projectId}', [EvaluationController::class, 'index']);

    Route::get('/my/as-evaluator', [EvaluationController::class, 'myEvaluationsAsEvaluator']);
    Route::get('/my/as-evaluated', [EvaluationController::class, 'myEvaluationsAsEvaluated']);

    Route::get('/programmer/{programmerId}/stats', [EvaluationController::class, 'programmerStats']);
});

Route::prefix('reports')->group(function () {
    Route::middleware(['auth:sanctum', 'check.user.status'])->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/my', [ReportController::class, 'myReports']);
        Route::get('/against-me', [ReportController::class, 'reportsAgainstMe']);
        Route::get('/check-status', [ReportController::class, 'checkUserStatus']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/statistics', [ReportController::class, 'statistics']);
        Route::get('/{report}', [ReportController::class, 'show']);
        Route::put('/{report}', [ReportController::class, 'update']);
        Route::delete('/{report}', [ReportController::class, 'destroy']);
    });
});
