<?php

namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Models\Skill;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyProgrammerController extends Controller
{
    /**
     * عرض قائمة المبرمجين مع التقييمات والفلترة
     */
    public function index(Request $request)
    {
        try {
            // التحقق من أن المستخدم شركة
            $user = $request->user();
            if (!$user || $user->role !== 'company') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only companies can access this resource',
                    'errors' => null,
                    'data' => null
                ], 403);
            }

            $query = Programmer::with(['user', 'skills'])
                ->where('profile_completed', true); // فقط المبرمجين الذين أكملوا ملفهم

            // فلتر حسب التراك (track)
            if ($request->has('track') && !empty($request->track)) {
                $query->where('track', 'like', '%' . $request->track . '%');
            }

            // فلتر حسب مستوى الخبرة (experience_level)
            if ($request->has('level') && !empty($request->level)) {
                $query->where('experience_level', $request->level);
            }

            // البحث العام في الاسم أو username أو البايو
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('bio', 'like', "%{$search}%")
                      ->orWhereHas('user', function($sub) use ($search) {
                          $sub->where('full_name', 'like', "%{$search}%");
                      });
                });
            }

            // جلب البيانات مع حساب التقييمات
            $programmers = $query->paginate(15);

            // إضافة التقييمات والنجوم لكل مبرمج
            $programmers->getCollection()->transform(function ($programmer) {
                // حساب متوسط التقييم من جدول evaluations
                $avgScore = Evaluation::where('evaluated_id', $programmer->id)
                    ->avg('average_score');

                // تحويل المتوسط (1-10) إلى نجوم (0-5)
                $ratingStars = $avgScore ? round(($avgScore / 10) * 5, 1) : 0;

                // عدد التقييمات
                $evaluationsCount = Evaluation::where('evaluated_id', $programmer->id)->count();

                // جلب المهارات كمجرد أسماء
                $skills = $programmer->skills->pluck('name')->toArray();

                return [
                    'id' => $programmer->id,
                    'name' => $programmer->user->full_name,
                    'username' => $programmer->user_name,
                    'track' => $programmer->track,
                    'avatar_url' => $programmer->avatar_url,
                    'bio' => $programmer->bio,
                    'skills' => $skills,
                    'experience_level' => $programmer->experience_level,
                    'rating_stars' => $ratingStars,      // من 0 إلى 5
                    'evaluations_count' => $evaluationsCount,
                ];
            });

            // تطبيق فلتر التقييم بعد التحويل (لأنه غير موجود في قاعدة البيانات مباشرة)
            $filteredProgrammers = $programmers->getCollection();

            if ($request->has('min_rating') && is_numeric($request->min_rating)) {
                $filteredProgrammers = $filteredProgrammers->filter(function ($p) use ($request) {
                    return $p['rating_stars'] >= (float)$request->min_rating;
                });
            }

            if ($request->has('max_rating') && is_numeric($request->max_rating)) {
                $filteredProgrammers = $filteredProgrammers->filter(function ($p) use ($request) {
                    return $p['rating_stars'] <= (float)$request->max_rating;
                });
            }

            // إعادة تجميع paginator مع البيانات المفلترة
            $programmers->setCollection($filteredProgrammers);

            return response()->json([
                'success' => true,
                'message' => 'Programmers retrieved successfully',
                'errors' => null,
                'data' => $programmers
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching programmers for company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch programmers',
                'errors' => null,
                'data' => null
            ], 500);
        }
    }

    /**
     * عرض تفاصيل مبرمج واحد (للشركة)
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'company') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only companies can access',
                    'errors' => null,
                    'data' => null
                ], 403);
            }

            $programmer = Programmer::with(['user', 'skills'])->find($id);
            if (!$programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer not found',
                    'errors' => null,
                    'data' => null
                ], 404);
            }

            // حساب التقييمات
            $avgScore = Evaluation::where('evaluated_id', $programmer->id)->avg('average_score');
            $ratingStars = $avgScore ? round(($avgScore / 10) * 5, 1) : 0;
            $evaluationsCount = Evaluation::where('evaluated_id', $programmer->id)->count();

            // تفاصيل التقييمات الإضافية (معدل كل مهارة)
            $avgDetails = Evaluation::where('evaluated_id', $programmer->id)
                ->select(
                    DB::raw('AVG(technical_skills) as technical_skills'),
                    DB::raw('AVG(communication) as communication'),
                    DB::raw('AVG(teamwork) as teamwork'),
                    DB::raw('AVG(problem_solving) as problem_solving'),
                    DB::raw('AVG(reliability) as reliability'),
                    DB::raw('AVG(code_quality) as code_quality')
                )->first();

            $skills = $programmer->skills->pluck('name')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Programmer details retrieved',
                'errors' => null,
                'data' => [
                    'id' => $programmer->id,
                    'name' => $programmer->user->full_name,
                    'username' => $programmer->user_name,
                    'email' => $programmer->user->email,
                    'track' => $programmer->track,
                    'avatar_url' => $programmer->avatar_url,
                    'bio' => $programmer->bio,
                    'skills' => $skills,
                    'experience_level' => $programmer->experience_level,
                    'total_score' => $programmer->total_score,
                    'stars' => $programmer->stars,  // النجوم من نظام النقاط (اختياري)
                    'rating_stars' => $ratingStars,  // التقييم المحول إلى نجوم
                    'evaluations_count' => $evaluationsCount,
                    'average_scores' => [
                        'technical_skills' => round($avgDetails->technical_skills ?? 0, 2),
                        'communication' => round($avgDetails->communication ?? 0, 2),
                        'teamwork' => round($avgDetails->teamwork ?? 0, 2),
                        'problem_solving' => round($avgDetails->problem_solving ?? 0, 2),
                        'reliability' => round($avgDetails->reliability ?? 0, 2),
                        'code_quality' => round($avgDetails->code_quality ?? 0, 2),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing programmer for company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch programmer details',
                'errors' => null,
                'data' => null
            ], 500);
        }
    }
}
