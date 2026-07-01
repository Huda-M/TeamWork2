<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyProgrammerController;
use App\Http\Controllers\EvaluationController;
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
use App\Http\Controllers\JoinRequestController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\RateLimiter;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="TeamWork2 API",
 *     description="Documentation for TeamWork2",
 *     @OA\Contact(
 *         email="support@teamwork2.com",
 *         name="Support Team"
 *     )
 * )
 *
 * @OA\Server(
 *     url="https://teamwork2-production-ucr9dn.laravel.cloud/api",
 *     description="Production Server"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 */

Route::post('/auth/google/mobile', [SocialAuthController::class, 'handleGoogleMobile'])
    ->middleware('throttle:5,1');
Route::post('/auth/github/mobile', [SocialAuthController::class, 'handleGitHubMobile'])
    ->middleware('throttle:5,1');


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

require_once __DIR__.'/chat.routes.php';
require_once __DIR__.'/notifications.routes.php';

// ─── Join Requests (by project_id) ───
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/projects/{projectId}/ai-evaluate', [TeamController::class, 'evaluateProjectWithAI']);
    
    // ✅ عرض تقييمات AI (GET)
    Route::get('/projects/{projectId}/ai-evaluations', [TeamController::class, 'getProjectAIEvaluations']);

    Route::get('/project/{projectId}/team', [TeamController::class, 'getProjectTeamMembers']);
    // إرسال join request لمشروع (التيم المرتبط بالمشروع)
    Route::post('/projects/{projectId}/join-request', [JoinRequestController::class, 'storeByProject']);
    Route::get('/join-requests/{joinRequestId}', [JoinRequestController::class, 'show']);
    // الليدر يشوف كل join requests اللي جاتله (للتيمات اللي هو ليدر فيها)
    Route::get('/my/join-requests', [JoinRequestController::class, 'myJoinRequests']);
    
    // الليدر يقبل/يرفض join request
    Route::put('/join-requests/{joinRequestId}/respond', [JoinRequestController::class, 'respond']);
});
// ─── Projects (كل حاجة بـ project_id) ───
Route::middleware('auth:sanctum')->prefix('projects')->group(function () {
    Route::post('/{projectId}/change-leader/{programmerId}', [ProjectController::class, 'changeProjectLeader']);
    // 1. كل المشاريع
    Route::get('/', [ProjectController::class, 'myProjects']);
    
    
    // 3. تاسكات المشروع (الجديد)
    Route::get('/{projectId}/tasks', [TaskController::class, 'getProjectTasks']);
    
    // 4. تفاصيل فريق المشروع
    Route::get('/{projectId}/team', [TeamController::class, 'getProjectTeamDetails']);
    
    // 5. إنشاء task في المشروع
    Route::post('/{projectId}/tasks', [TaskController::class, 'storeProjectTask']);
    
    // 6. Zero project
    Route::get('/{projectId}/zero', [ProjectController::class, 'zeroProject']);
    
    // 7. تعليم مشروع كمكتمل
    Route::patch('/{projectId}/complete', [ProjectController::class, 'markAsCompleted']);
    
    // 8. تغيير قائد الفريق
    Route::post('/{projectId}/change-leader/{programmerId}', [TeamController::class, 'swapProjectLeader']);
    
    // 9. تحديث فريق المشروع
    Route::put('/{projectId}/team', [TeamController::class, 'updateProjectTeam']);
    
    // 10. حذف فريق المشروع
    Route::delete('/{projectId}/team', [TeamController::class, 'softDeleteProjectTeam']);
    
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/{projectId}/tasks', [TaskController::class, 'storeProjectTask']);
    Route::post('/projects/{projectId}/evaluate-all', [TeamController::class, 'evaluateProjectTeamMembers']);
    Route::get('/projects/{projectId}/basic-details', [TeamController::class, 'getProjectBasicDetails']);
    Route::get('/my-projects/{projectId}/details', [ProjectController::class, 'myProjectDetails']);
    Route::get('/projects/{projectId}/tasks', [TaskController::class, 'getProjectTasks']);
    Route::get('/invitations', [TeamController::class, 'getAllMyInvitations']);
    Route::get('/invitations/{invitationId}/details', [TeamController::class, 'getInvitationDetails']);
Route::post('/teams/{team}/join-requests', [JoinRequestController::class, 'store']); 
Route::get('/teams/join-requests', [JoinRequestController::class, 'index']); 
Route::get('/teams/{team}/join-requests', [JoinRequestController::class, 'teamJoinRequests']); 
Route::put('/join-requests/{joinRequest}', [JoinRequestController::class, 'update']); 
    Route::get('/search/programmers', [ProgrammerController::class, 'searchByUsername']);
    Route::post('/teams/{teamId}/evaluate-all', [TeamController::class, 'evaluateTeamMembers']);
    Route::get('/teams/{teamId}/my-ratings', [TeamController::class, 'getTeamMembersWithMyRatings']);
    Route::get('/teams/{id}/basic-details', [TeamController::class, 'getTeamBasicDetails']);
    Route::get('/my/level-progression', [ProgrammerController::class, 'levelProgression']);
    Route::get('/my/dashboard', [ProgrammerController::class, 'dashboard']);
    Route::get('/teams/{teamId}/members-with-ratings', [TeamController::class, 'getTeamMembersWithRatings']);
    Route::get('/teams/{teamId}/members-list', [TeamController::class, 'getTeamMembersList']);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'markAsCompleted']);
    Route::get('/team/{teamId}/full-details', [TeamController::class, 'getFullTeamDetails']);
    Route::get('/programmer/{programmerId}/report-info', [ReportController::class, 'getUserReportInfo']);
    Route::prefix('company')->group(function () {
        Route::get('/profile', [CompanyController::class, 'showProfile']);
        Route::put('/profile', [CompanyController::class, 'updateProfile']);
        Route::delete('/soft-delete', [CompanyController::class, 'softDelete']);
        Route::get('/programmers', [CompanyProgrammerController::class, 'index']);
        Route::get('/programmers/{id}', [CompanyProgrammerController::class, 'show']);
    });

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects/{projectId}/my-ratings', [TeamController::class, 'getProjectMembersWithMyRatings']);
    Route::get('/projects/{projectId}/full-details', [TeamController::class, 'getProjectFullDetails']);
    Route::post('/{task}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::post('/{task}/mark-as-done', [TaskController::class, 'markAsDone']);
    Route::get('/zero-project/{projectId}', [ProjectController::class, 'zeroProject']);
    Route::get('/user/{id}/report-info', [ReportController::class, 'getUserReportInfo']);
    Route::post('/company/complete-profile', [RegisteredUserController::class, 'completeCompanyProfile']);
    // User profile & general
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/register/complete-profile', [RegisteredUserController::class, 'completeProfile']);
    Route::get('/profile/status', [RegisteredUserController::class, 'profileStatus']);
    Route::post('/change-password', [NewPasswordController::class, 'changePassword']);

    // Programmer specific
    Route::get('/my/statistics', [ProgrammerController::class, 'myStatistics']);
    Route::get('/programmers/{id}/statistics', [ProgrammerController::class, 'programmerStatistics']);


    // Projects related (authenticated)
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    // Route::get('/my-projects/{projectId}/details', [ProjectController::class, 'myProjectDetails']);
    // Route::get('/projects/{projectId}/tasks', [ProjectController::class, 'projectTasks']);
    Route::get('/users/{userId}/projects', [ProjectController::class, 'getUserProjects']);
    Route::patch('/projects/{projectId}/complete', [ProjectController::class, 'markAsCompleted']);

    // Tasks
    Route::prefix('tasks')->group(function () {
        Route::post('/{task}/mark-as-done', [TaskController::class, 'markAsDone']);
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

    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/skills-experience', [ProfileController::class, 'getSkillsAndExperience']);
        Route::post('/skills-experience', [ProfileController::class, 'updateSkillsAndExperience']);
        Route::get('/', [ProfileController::class, 'myProfile']);           // ✅ صحيح
        Route::post('/update', [ProfileController::class, 'updateProfile']);
        Route::get('/my-stats', [ProfileController::class, 'myStats']);
        Route::get('/my-evaluations', [ProfileController::class, 'myEvaluations']);
        Route::get('/team-members/{projectId}/to-evaluate', [ProfileController::class, 'teamMembersToEvaluate']);
        Route::post('/evaluate/{projectId}/{evaluatedId}', [ProfileController::class, 'submitEvaluation']);
        Route::delete('/soft-delete', [ProfileController::class, 'softDeleteAccount']);
        Route::get('/project-details/{projectId}', [ProfileController::class, 'projectDetails']);
    });

    // Teams
    Route::prefix('teams')->group(function () {
        // General team operations
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{id}', [TeamController::class, 'showTeam']);
        Route::get('/projects/{projectId}/team-details', [TeamController::class, 'getTeamDetails']);
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

});

Broadcast::routes(['middleware' => ['auth:sanctum']]);

require_once __DIR__.'/ai.routes.php';
require_once __DIR__.'/Companies/auth.routes.php';
require_once __DIR__.'/Companies/programmer.routes.php';
require_once __DIR__.'/Companies/offer.routes.php';
