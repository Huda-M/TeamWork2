<?php

namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Evaluation;
use App\Models\Skill;
use App\Http\Requests\UpdateProgrammerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ProfileController extends Controller
{
   public function myProfile()
{
    $user = Auth::user();
    if (!$user || $user->role !== 'programmer') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $programmer = $user->programmer;
    if (!$programmer) {
        return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id'         => $programmer->id,
            'user_name'  => $programmer->user_name,
            'full_name'  => $user->full_name,
            'email'      => $user->email,
            'bio'        => $programmer->bio,
            'track'      => $programmer->track,
            'avatar_url' => $programmer->avatar_url ? Storage::disk('public')->url($programmer->avatar_url) : null,
        ]
    ]);
}

    // 2. عرض إحصائيات المبرمج
    public function myStats()
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json(['success' => false, 'message' => 'Programmer not found'], 404);
        }

        // عدد التيمات التي انضم إليها (نشطة فقط)
        $teamsCount = $programmer->teams()
            ->wherePivotNull('left_at')
            ->count();

        // عدد التاسكات المكتملة وغير المكتملة
        $completedTasks = $programmer->tasks()
            ->where('status', 'done')
            ->count();

        $incompleteTasks = $programmer->tasks()
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->count();

        // حساب الليفل النصي
        $levelText = $programmer->calculateLevel();

        return response()->json([
            'success' => true,
            'data' => [
                'programmer_id' => $programmer->id,
                'name' => $user->full_name,
                'user_name' => $programmer->user_name,
                'level' => $levelText,
                'track' => $programmer->track,
                'total_teams' => $teamsCount,
                'completed_tasks' => $completedTasks,
                'incomplete_tasks' => $incompleteTasks,
                'total_score' => $programmer->total_score,
                'stars' => $programmer->stars,
            ]
        ]);
    }

    // 3. عرض التقييمات التي تلقيتها
    public function myEvaluations()
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $evaluations = Evaluation::where('evaluated_id', $programmer->id)
            ->with(['evaluator.user', 'project'])
            ->get()
            ->map(function($eval) {
                return [
                    'project_name' => $eval->project->title,
                    'project_description' => $eval->project->description,
                    'evaluator_name' => $eval->evaluator->user->full_name,
                    'evaluator_track' => $eval->evaluator->track,
                    'technical_skills' => $eval->technical_skills,
                    'communication' => $eval->communication,
                    'teamwork' => $eval->teamwork,
                    'problem_solving' => $eval->problem_solving,
                    'reliability' => $eval->reliability,
                    'average_score' => $eval->average_score,
                    'feedback' => $eval->feedback,
                    'strengths' => $eval->strengths,
                    'areas_for_improvement' => $eval->areas_for_improvement,
                    'submitted_at' => $eval->submitted_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    // 4. عرض أعضاء الفريق لتقييمهم
    public function teamMembersToEvaluate($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        // جلب الفريق الذي ينتمي إليه المبرمج في هذا المشروع
        $team = Team::whereHas('project', function($q) use ($projectId) {
                $q->where('id', $projectId);
            })
            ->whereHas('activeMembers', function($q) use ($programmer) {
                $q->where('programmer_id', $programmer->id);
            })
            ->first();

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not in any team for this project'], 404);
        }

        $members = $team->activeMembers()
            ->with('programmer.user')
            ->where('programmer_id', '!=', $programmer->id)
            ->get()
            ->map(function($member) {
                return [
                    'programmer_id' => $member->programmer_id,
                    'name' => $member->programmer->user->full_name,
                    'track' => $member->programmer->track,
                    'avatar_url' => $member->programmer->avatar_url ? Storage::disk('public')->url($member->programmer->avatar_url) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $projectId,
                'project_title' => $team->project->title,
                'project_description' => $team->project->description,
                'members_to_evaluate' => $members,
            ]
        ]);
    }

    // 5. تقديم تقييم لعضو فريق
    public function submitEvaluation(Request $request, $projectId, $evaluatedId)
    {
        $user = Auth::user();
        $evaluator = $user->programmer;

        $validated = $request->validate([
            'technical_skills' => 'required|integer|min:1|max:5',
            'communication' => 'required|integer|min:1|max:5',
            'teamwork' => 'required|integer|min:1|max:5',
            'problem_solving' => 'required|integer|min:1|max:5',
            'reliability' => 'required|integer|min:1|max:5',
            'code_quality' => 'nullable|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'feedback' => 'nullable|string',
        ]);

        $project = Project::findOrFail($projectId);
        $evaluated = Programmer::findOrFail($evaluatedId);

        // التحقق من أن المقيم والمقيم في نفس الفريق
        $team = Team::where('project_id', $projectId)
            ->whereHas('activeMembers', function($q) use ($evaluator) {
                $q->where('programmer_id', $evaluator->id);
            })
            ->whereHas('activeMembers', function($q) use ($evaluated) {
                $q->where('programmer_id', $evaluated->id);
            })
            ->first();

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'Both programmers are not in the same team for this project'], 400);
        }

        // حساب متوسط التقييم
        $scores = [
            $validated['technical_skills'],
            $validated['communication'],
            $validated['teamwork'],
            $validated['problem_solving'],
            $validated['reliability'],
        ];
        if (isset($validated['code_quality'])) {
            $scores[] = $validated['code_quality'];
        }
        $average = round(array_sum($scores) / count($scores), 2);

        $evaluation = Evaluation::create([
            'project_id' => $projectId,
            'team_id' => $team->id,
            'evaluator_id' => $evaluator->id,
            'evaluated_id' => $evaluated->id,
            'technical_skills' => $validated['technical_skills'],
            'communication' => $validated['communication'],
            'teamwork' => $validated['teamwork'],
            'problem_solving' => $validated['problem_solving'],
            'reliability' => $validated['reliability'],
            'code_quality' => $validated['code_quality'] ?? null,
            'average_score' => $average,
            'strengths' => $validated['strengths'] ?? null,
            'areas_for_improvement' => $validated['areas_for_improvement'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
            'submitted_at' => now(),
        ]);

        // إضافة نجوم للمقيم
        $evaluated->addStars(5);

        return response()->json([
            'success' => true,
            'message' => 'Evaluation submitted successfully',
            'data' => $evaluation
        ]);
    }
public function softDeleteAccount()
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // إلغاء التوكنات
    try {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    } catch (\Exception $e) {
        Log::warning('Token deletion failed: ' . $e->getMessage());
    }

    // Soft delete
    try {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account soft deleted successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Soft delete failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete account: ' . $e->getMessage()
        ], 500);
    }
}
    // 7. Zero Project - عرض تفاصيل المشروع مع إحصائيات الأعضاء
    public function zeroProject($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $project = Project::with(['teams.activeMembers.programmer', 'teams.tasks'])
            ->findOrFail($projectId);

        // جلب الفريق الخاص بالمبرمج
        $team = $project->teams->first(function($t) use ($programmer) {
            return $t->activeMembers->contains('programmer_id', $programmer->id);
        });

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this project'], 403);
        }

        $members = $team->activeMembers->map(function($member) {
            $prog = $member->programmer;
            $tasksCount = $prog->tasks()->where('team_id', $member->team_id)->count();
            $completedTasks = $prog->tasks()
                ->where('team_id', $member->team_id)
                ->where('status', 'done')
                ->count();
            $completionRate = $tasksCount > 0 ? round(($completedTasks / $tasksCount) * 100) : 0;

            return [
                'programmer_id' => $prog->id,
                'name' => $prog->user->full_name,
                'track' => $prog->track,
                'tasks_assigned' => $tasksCount,
                'tasks_completed' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        });

        $totalTasks = $team->tasks()->count();
        $totalCompleted = $team->tasks()->where('status', 'done')->count();
        $remainingTasks = $totalTasks - $totalCompleted;

        return response()->json([
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'project_description' => $project->description,
                'team_size' => $members->count(),
                'total_tasks' => $totalTasks,
                'completed_tasks' => $totalCompleted,
                'remaining_tasks' => $remainingTasks,
                'members' => $members,
            ]
        ]);
    }
    
/**
 * تحديث الملف الشخصي للمبرمج (مع تتبع كامل)
 */
// public function updateProfile(Request $request)
// {
//     try {
//         $user = Auth::user();

//         if (!$user || $user->role !== 'programmer') {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Only programmers can update their profile'
//             ], 403);
//         }

//         $programmer = $user->programmer;

//         if (!$programmer) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Programmer profile not found'
//             ], 404);
//         }

//         $rules = [
//             'full_name' => 'sometimes|required|string|max:255',
//             'bio'       => 'nullable|string|max:1000',
//             'track'     => 'nullable|string|max:100',
//             'avatar'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//         ];

//         if ($request->filled('user_name')) {
//             $rules['user_name'] = [
//                 'required',
//                 'string',
//                 'max:255',
//                 \Illuminate\Validation\Rule::unique('programmers', 'user_name')
//                     ->ignore($programmer->id)
//             ];
//         }

//         if ($request->filled('email')) {
//             $rules['email'] = [
//                 'required',
//                 'email',
//                 \Illuminate\Validation\Rule::unique('users', 'email')
//                     ->ignore($user->id)
//             ];
//         }

//         $validator = Validator::make($request->all(), $rules);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         // Update users table
//         $userUpdated = false;

//         if ($request->filled('full_name')) {
//             $user->full_name = $request->full_name;
//             $userUpdated = true;
//         }

//         if ($request->filled('email')) {
//             $user->email = $request->email;
//             $userUpdated = true;
//         }

//         if ($userUpdated) {
//             $user->save();
//         }

//         // Update programmers table
//         $programmerUpdated = false;

//         if ($request->filled('user_name')) {
//             $programmer->user_name = $request->user_name;
//             $programmerUpdated = true;
//         }

//         if ($request->has('bio')) {
//             $programmer->bio = $request->bio;
//             $programmerUpdated = true;
//         }

//         if ($request->has('track')) {
//             $programmer->track = $request->track;
//             $programmerUpdated = true;
//         }

//         // Handle avatar upload
//         if ($request->hasFile('avatar')) {
//             $file = $request->file('avatar');

//             if ($file->isValid()) {
//                 // حذف الصورة القديمة
//                 if (
//                     $programmer->avatar_url &&
//                     str_contains($programmer->avatar_url, '/storage/')
//                 ) {
//                     $oldPath = str_replace(
//                         '/storage/',
//                         '',
//                         $programmer->avatar_url
//                     );

//                     if (Storage::disk('public')->exists($oldPath)) {
//                         Storage::disk('public')->delete($oldPath);
//                     }
//                 }

//                 $fileName = 'avatar_' . time() . '.' .
//                             $file->getClientOriginalExtension();

//                 $path = $file->storeAs(
//                     'avatars',
//                     $fileName,
//                     'public'
//                 );

//                 $programmer->avatar_url = $path;
//                 $programmerUpdated = true;

//             } else {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Invalid image file'
//                 ], 400);
//             }
//         }

//         if ($programmerUpdated) {
//             $programmer->save();
//         }

//         $programmer->refresh();
//         $user->refresh();

//         $avatarUrl = $programmer->avatar_url
//             ? Storage::disk('public')->url($programmer->avatar_url)
//             : null;

//         return response()->json([
//             'success' => true,
//             'message' => 'Profile updated successfully',
//             'data' => [
//                 'id'         => $programmer->id,
//                 'user_name'  => $programmer->user_name,
//                 'full_name'  => $user->full_name,
//                 'email'      => $user->email,
//                 'bio'        => $programmer->bio,
//                 'track'      => $programmer->track,
//                 'avatar_url' => $avatarUrl,
//             ]
//         ]);

//     } catch (\Exception $e) {
//         Log::error(
//             'Profile update error: ' . $e->getMessage(),
//             [
//                 'user_id' => Auth::id(),
//                 'request_data' => $request->all(),
//             ]
//         );

//         return response()->json([
//             'success' => false,
//             'message' => 'An error occurred while updating profile',
//             'error'   => $e->getMessage()
//         ], 500);
//     }
// }  
    /**
 * تحديث الملف الشخصي للمبرمج (يدعم JSON و form-data)
 */
public function updateProfile(Request $request)
{
    try {
        $user = Auth::user();

        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can update their profile'
            ], 403);
        }

        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }

        // ============================================================
        // 1. قراءة البيانات بغض النظر عن نوع الطلب
        // ============================================================
        // استخدم $request->input() للحصول على جميع البيانات (تعمل مع JSON و form-data)
        $fullName = $request->input('full_name');
        $email = $request->input('email');
        $userName = $request->input('user_name');
        $bio = $request->input('bio');
        $track = $request->input('track');

        // تسجيل البيانات المستلمة
        Log::info('Profile update - form-data/JSON', [
            'full_name' => $fullName,
            'email' => $email,
            'user_name' => $userName,
            'bio' => $bio,
            'track' => $track,
            'has_file' => $request->hasFile('avatar'),
            'content_type' => $request->header('Content-Type'),
        ]);

        // ============================================================
        // 2. التحقق من صحة البيانات (لن نطبق unique على user_name إذا لم يتغير)
        // ============================================================
        $rules = [
            'full_name' => 'sometimes|required|string|max:255',
            'bio'       => 'nullable|string|max:1000',
            'track'     => 'nullable|string|max:100',
            'avatar'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // إذا أرسل user_name وهو مختلف عن الحالي، نتحقق من عدم تكراره
        if ($userName !== null && $userName !== $programmer->user_name) {
            $rules['user_name'] = [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('programmers', 'user_name')
                    ->ignore($programmer->id)
            ];
        } elseif ($userName !== null) {
            // إذا كان نفس القيمة الحالية، نسمح به دون تحقق
            $rules['user_name'] = 'sometimes|string|max:255';
        }

        // إذا أرسل email وهو مختلف عن الحالي، نتحقق من عدم تكراره
        if ($email !== null && $email !== $user->email) {
            $rules['email'] = [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('users', 'email')
                    ->ignore($user->id)
            ];
        } elseif ($email !== null) {
            $rules['email'] = 'sometimes|email';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // ============================================================
        // 3. تحديث جدول users
        // ============================================================
        $userUpdated = false;

        if ($fullName !== null && $fullName !== $user->full_name) {
            $user->full_name = $fullName;
            $userUpdated = true;
        }

        if ($email !== null && $email !== $user->email) {
            $user->email = $email;
            $userUpdated = true;
        }

        if ($userUpdated) {
            $user->save();
            Log::info('User updated successfully');
        }

        // ============================================================
        // 4. تحديث جدول programmers
        // ============================================================
        $programmerUpdated = false;

        if ($userName !== null && $userName !== $programmer->user_name) {
            $programmer->user_name = $userName;
            $programmerUpdated = true;
        }

        if ($bio !== null && $bio !== $programmer->bio) {
            $programmer->bio = $bio;
            $programmerUpdated = true;
        }

        if ($track !== null && $track !== $programmer->track) {
            $programmer->track = $track;
            $programmerUpdated = true;
        }

        // ============================================================
        // 5. معالجة رفع الصورة
        // ============================================================
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            if ($file->isValid()) {
                // حذف الصورة القديمة
                if ($programmer->avatar_url && str_contains($programmer->avatar_url, '/storage/')) {
                    $oldPath = str_replace('/storage/', '', $programmer->avatar_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $fileName = 'avatar_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('avatars', $fileName, 'public');
                $programmer->avatar_url = $path;
                $programmerUpdated = true;
                Log::info('Avatar uploaded successfully: ' . $path);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image file'
                ], 400);
            }
        }

        // ============================================================
        // 6. التحقق من وجود تغييرات
        // ============================================================
        if (!$programmerUpdated && !$userUpdated && !$request->hasFile('avatar')) {
            Log::info('No changes detected');
            return response()->json([
                'success' => true,
                'message' => 'No changes detected',
                'data' => [
                    'id' => $programmer->id,
                    'user_name' => $programmer->user_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'bio' => $programmer->bio,
                    'track' => $programmer->track,
                    'avatar_url' => $programmer->avatar_url ? Storage::disk('public')->url($programmer->avatar_url) : null,
                ]
            ], 200);
        }

        // ============================================================
        // 7. حفظ التغييرات
        // ============================================================
        if ($programmerUpdated) {
            $programmer->save();
            Log::info('Programmer updated successfully');
        }

        $programmer->refresh();
        $user->refresh();

        // ============================================================
        // 8. الرد النهائي
        // ============================================================
        $avatarUrl = $programmer->avatar_url
            ? Storage::disk('public')->url($programmer->avatar_url)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id'         => $programmer->id,
                'user_name'  => $programmer->user_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'bio'        => $programmer->bio,
                'track'      => $programmer->track,
                'avatar_url' => $avatarUrl,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Profile update error: ' . $e->getMessage(), [
            'user_id' => Auth::id(),
            'request_data' => $request->all(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating profile',
            'error'   => $e->getMessage()
        ], 500);
    }
}
    
    // 8. تفاصيل المشروع (لو لسه شغال أو خلص)
    public function projectDetails($projectId)
    {
        $user = Auth::user();
        $programmer = $user->programmer;

        $project = Project::with([
            'teams.activeMembers.programmer.user',
            'teams.activeMembers.programmer.tasks',
            'evaluations' => function($q) use ($programmer) {
                $q->where('evaluated_id', $programmer->id);
            }
        ])->findOrFail($projectId);

        $team = $project->teams->first(function($t) use ($programmer) {
            return $t->activeMembers->contains('programmer_id', $programmer->id);
        });

        if (!$team) {
            return response()->json(['success' => false, 'message' => 'You are not a member of this project'], 403);
        }

        $memberRole = $team->activeMembers->firstWhere('programmer_id', $programmer->id)->role;

        $members = $team->activeMembers->map(function($member) {
            $prog = $member->programmer;
            return [
                'id' => $prog->id,
                'name' => $prog->user->full_name,
                'track' => $prog->track,
                'avatar_url' => $prog->avatar_url ? Storage::disk('public')->url($prog->avatar_url) : null,
                'role' => $member->role,
            ];
        });

        $isCompleted = $project->status === 'completed';

        $response = [
            'success' => true,
            'data' => [
                'team_name' => $team->name,
                'project_status' => $project->status,
                'project_description' => $project->description,
                'my_role' => $memberRole,
                'github_url' => $team->github_url ?? null,
                'members' => $members,
            ]
        ];

        if ($isCompleted) {
            // إذا كان المشروع مكتملاً، أضف التقييمات وتاريخ الانتهاء
            $evaluations = $project->evaluations->map(function($eval) {
                return [
                    'evaluator_name' => $eval->evaluator->user->full_name,
                    'average_score' => $eval->average_score,
                    'feedback' => $eval->feedback,
                    'strengths' => $eval->strengths,
                    'areas_for_improvement' => $eval->areas_for_improvement,
                ];
            });

            $response['data']['evaluations'] = $evaluations;
            $response['data']['completion_date'] = $project->updated_at->toDateString();
            $response['data']['duration_days'] = $project->estimated_duration_days;
        } else {
            // إذا كان المشروع قيد التنفيذ، أضف حالة المهام
            $tasks = $team->tasks()->with('programmer.user')->get();
            $tasksStats = [
                'total' => $tasks->count(),
                'todo' => $tasks->where('status', 'todo')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'review' => $tasks->where('status', 'review')->count(),
                'done' => $tasks->where('status', 'done')->count(),
            ];
            $response['data']['tasks_status'] = $tasksStats;
            $response['data']['personal_tasks'] = $tasks->where('programmer_id', $programmer->id)->values();
        }

        return response()->json($response);
    }
/**
 * @OA\Get(
 *     path="/api/profile/skills-experience",
 *     tags={"Profile"},
 *     summary="عرض المهارات والخبرات",
 *     description="الحصول على مهارات وخبرات المبرمج الحالي (بدون تعديل Experience Level)",
 *     security={{"Bearer": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="بيانات المهارات والخبرات",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="experience_level", type="string", example="junior", description="للعرض فقط - لا يمكن تعديله من هنا"),
 *                 @OA\Property(
 *                     property="skills",
 *                     type="array",
 *                     description="قائمة المهارات التقنية",
 *                     @OA\Items(type="string", example="Flutter")
 *                 ),
 *                 @OA\Property(property="experience", type="string", example="Experienced mobile developer with a focus on creating performant, beautiful cross-platform applications...")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="ممنوع - فقط المبرمجين"),
 *     @OA\Response(response=404, description="ملف المبرمج غير موجود"),
 *     @OA\Response(response=500, description="خطأ في السيرفر")
 * )
 */
public function getSkillsAndExperience()
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can access'
            ], 403);
        }

        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }

        // ✅ جلب Skills من الـ relation
        $skills = $programmer->skills()->pluck('name')->toArray();

        // ✅ جلب Experience من الـ bio أو column جديد
        $experience = $programmer->bio; // أو $programmer->experience لو ضفت column

        return response()->json([
            'success' => true,
            'data' => [
                'experience_level' => $programmer->experience_level,  // "junior", "senior", etc.
                'skills' => $skills,  // ["Flutter", "React", "UI/UX"]
                'experience' => $experience,  // النص الطويل
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Get skills/experience error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch skills and experience'
        ], 500);
    }
}
/**
 * @OA\Post(
 *     path="/api/profile/skills-experience",
 *     tags={"Profile"},
 *     summary="تحديث المهارات والخبرات",
 *     description="تحديث مهارات وخبرات المبرمج الحالي (Experience Level للعرض فقط)",
 *     security={{"Bearer": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="skills",
 *                 type="array",
 *                 description="قائمة المهارات الجديدة",
 *                 @OA\Items(type="string", example="Flutter"),
 *                 example={"Flutter", "React", "UI/UX", "Laravel"}
 *             ),
 *             @OA\Property(
 *                 property="experience",
 *                 type="string",
 *                 description="نص الخبرات والوصف المهني",
 *                 example="Experienced mobile developer with 3 years in Flutter and React..."
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="تم التحديث بنجاح",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Skills and experience updated successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="skills",
 *                     type="array",
 *                     @OA\Items(type="string", example="Flutter")
 *                 ),
 *                 @OA\Property(property="experience", type="string", example="Updated experience text...")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="ممنوع - فقط المبرمجين"),
 *     @OA\Response(response=404, description="ملف المبرمج غير موجود"),
 *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
 *     @OA\Response(response=500, description="خطأ في السيرفر")
 * )
 */
public function updateSkillsAndExperience(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only programmers can update'
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
            'skills' => 'nullable|array',           // ["Flutter", "React", "UI/UX"]
            'skills.*' => 'string|max:50',
            'experience' => 'nullable|string|max:5000',  // النص الطويل
        ]);

        // ✅ تحديث Skills (sync with pivot table)
        if (isset($validated['skills'])) {
            $skillIds = [];
            foreach ($validated['skills'] as $skillName) {
                $skill = Skill::firstOrCreate(['name' => trim($skillName)]);
                $skillIds[] = $skill->id;
            }
            $programmer->skills()->sync($skillIds);
        }

        // ✅ تحديث Experience (في bio أو column جديد)
        if (isset($validated['experience'])) {
            $programmer->bio = $validated['experience']; // أو $programmer->experience
            $programmer->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Skills and experience updated successfully',
            'data' => [
                'skills' => $programmer->skills()->pluck('name')->toArray(),
                'experience' => $programmer->bio, // أو $programmer->experience
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Update skills/experience error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update skills and experience'
        ], 500);
    }
}
}
