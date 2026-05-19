<?php

namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Evaluation;
use App\Http\Requests\UpdateProgrammerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
            'avatar_url' => $programmer->avatar_url,
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
                    'avatar_url' => $member->programmer->avatar ? asset('storage/'.$member->programmer->avatar) : null,
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
    
    // 6. Soft Delete للحساب
    public function softDeleteAccount()
    {
        $user = Auth::user();
        
        // إلغاء التوكنات الحالية
        $user->tokens()->delete();
        
        // Soft delete للمستخدم
        $user->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Account soft deleted successfully'
        ]);
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
public function updateProfile(Request $request)
    {
        try {
            // 1. التحقق من صلاحية المستخدم
            $user = Auth::user();
            if (!$user || $user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can update their profile'], 403);
            }

            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            // 2. قواعد التحقق الأساسية
            $rules = [
                'full_name'    => 'sometimes|required|string|max:255',
                'bio'          => 'nullable|string|max:1000',
                'track'        => 'nullable|string|max:100',
                'avatar'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // أقصى حجم 2MB
                'avatar_url'   => 'nullable|url|max:255', // رابط خارجي أو رابط تخزين
            ];

            // تحقق من user_name إذا ورد وتغير
            if ($request->has('user_name')) {
                $newUserName = $request->input('user_name');
                if ($newUserName !== $programmer->user_name) {
                    $rules['user_name'] = [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('programmers', 'user_name')->ignore($programmer->id)
                    ];
                } else {
                    $rules['user_name'] = 'sometimes|required|string|max:255';
                }
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 3. تحديث جدول users (full_name, email لا يمكن تغييره هنا)
            if ($request->has('full_name')) {
                $user->full_name = $request->input('full_name');
                $user->save();
                Log::info('User full_name updated to: ' . $user->full_name);
            }

            // 4. تحديث جدول programmers
            $programmerUpdated = false;
            if ($request->has('user_name')) {
                $programmer->user_name = $request->input('user_name');
                $programmerUpdated = true;
            }
            if ($request->has('bio')) {
                $programmer->bio = $request->input('bio');
                $programmerUpdated = true;
            }
            if ($request->has('track')) {
                $programmer->track = $request->input('track');
                $programmerUpdated = true;
            }

            // 5. معالجة الصورة - الأولوية للملف المرفوع (avatar)
            $avatarChanged = false;
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');

                // التحقق الإضافي من صحة الملف
                if (!$file->isValid()) {
                    Log::warning('Uploaded avatar file is not valid', [
                        'error' => $file->getError(),
                        'programmer_id' => $programmer->id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'The uploaded file is not valid. Please try again.'
                    ], 400);
                }

                // حذف الصورة القديمة إذا كانت موجودة (وتخزين محلي)
                if ($programmer->avatar_url && !filter_var($programmer->avatar_url, FILTER_VALIDATE_URL) === false && strpos($programmer->avatar_url, '/storage/') === 0) {
                    $oldPath = str_replace('/storage/', '', $programmer->avatar_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                        Log::info('Old avatar deleted: ' . $oldPath);
                    }
                } elseif ($programmer->avatar_url && strpos($programmer->avatar_url, 'http') !== 0) {
                    // في حال كان الرابط غير صالح، نحاول حذفه كمسار نسبي (احتياطي)
                    if (Storage::disk('public')->exists($programmer->avatar_url)) {
                        Storage::disk('public')->delete($programmer->avatar_url);
                    }
                }

                // إنشاء اسم فريد للملف لمنع التكرار
                $extension = $file->getClientOriginalExtension();
                $fileName = 'avatar_' . $programmer->id . '_' . time() . '.' . $extension;
                $path = $file->storeAs('avatars', $fileName, 'public');
                
                if ($path === false) {
                    Log::error('Failed to store avatar file for programmer: ' . $programmer->id);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save avatar image.'
                    ], 500);
                }

                $programmer->avatar_url = Storage::url($path); // ينتج /storage/avatars/avatar_4_1234567890.jpg
                $programmerUpdated = true;
                $avatarChanged = true;
                Log::info('New avatar uploaded and stored: ' . $programmer->avatar_url);
            }
            // إذا لم يتم رفع ملف، لكن ورد رابط avatar_url، نستخدمه (بشرط ألا يكون هناك ملف)
            elseif ($request->has('avatar_url') && $request->filled('avatar_url')) {
                $newUrl = $request->input('avatar_url');
                // لا نحذف الصورة القديمة إن كانت تخزين محلي، لأن الرابط الجديد قد يكون مختلفًا
                $programmer->avatar_url = $newUrl;
                $programmerUpdated = true;
                $avatarChanged = true;
                Log::info('Avatar URL manually set to: ' . $newUrl);
            }

            // حفظ التغييرات في جدول programmers
            if ($programmerUpdated) {
                $programmer->save();
                Log::info('Programmer profile saved', ['programmer_id' => $programmer->id, 'fields_changed' => $programmer->getChanges()]);
            }

            // إعادة تحميل البيانات
            $programmer->refresh();
            $user->refresh();

            // رسالة النجاح مع تفصيل إذا تغيرت الصورة
            $message = 'Profile updated successfully';
            if ($avatarChanged) {
                $message .= ' and avatar has been updated.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id'          => $programmer->id,
                    'user_name'   => $programmer->user_name,
                    'full_name'   => $user->full_name,
                    'email'       => $user->email,
                    'bio'         => $programmer->bio,
                    'track'       => $programmer->track,
                    'avatar_url'  => $programmer->avatar_url,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating profile. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
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
                'avatar_url' => $prog->avatar ? asset('storage/'.$prog->avatar) : null,
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
}
