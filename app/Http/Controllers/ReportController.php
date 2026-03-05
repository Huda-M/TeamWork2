<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $query = Report::with(['targetUser', 'reporterUser', 'admin'])
                ->orderBy('created_at', 'desc');

            if ($request->has('status')) {
                $query->where('admin_action', $request->status);
            }

            if ($request->has('report_type')) {
                $query->where('report_type', $request->report_type);
            }

            $reports = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $reports,
                'message' => 'Reports fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reports'
            ], 500);
        }
    }

    public function store(StoreReportRequest $request)
    {
        try {
            $validated = $request->validated();
            $reporter = auth()->user();

            $targetUser = User::find($validated['target_user_id']);
            if ($targetUser->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot report admin users'
                ], 400);
            }

            $existingReport = Report::where('reporter_user_id', $reporter->id)
                ->where('target_user_id', $validated['target_user_id'])
                ->where('admin_action', 'pending')
                ->first();

            if ($existingReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending report against this user'
                ], 400);
            }

            DB::beginTransaction();

            $report = Report::create([
                'target_user_id' => $validated['target_user_id'],
                'reporter_user_id' => $reporter->id,
                'report_type' => $validated['report_type'],
                'description' => $validated['description'],
                'evidence' => $validated['evidence'] ?? null,
                'admin_action' => 'pending',
            ]);

            Log::info('New report created', [
                'report_id' => $report->id,
                'reporter' => $reporter->id,
                'target' => $validated['target_user_id']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully. Admin will review it soon.',
                'data' => $report->load(['targetUser', 'reporterUser'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report'
            ], 500);
        }
    }

    public function show(Report $report)
    {
        try {
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $report->load(['targetUser', 'reporterUser', 'admin']),
                'message' => 'Report fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch report'
            ], 500);
        }
    }

    public function update(UpdateReportRequest $request, Report $report)
    {
        try {
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validated = $request->validated();

            DB::beginTransaction();

            $report->update([
                'admin_action' => $validated['admin_action'],
                'admin_notes' => $validated['admin_notes'] ?? null,
                'admin_id' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ($validated['admin_action'] === 'approved') {
                $this->applyPenalties($report->targetUser);
            }

            Log::info('Report updated', [
                'report_id' => $report->id,
                'action' => $validated['admin_action'],
                'admin_id' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report updated successfully',
                'data' => $report->fresh(['targetUser', 'reporterUser', 'admin'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report'
            ], 500);
        }
    }

    private function applyPenalties(User $user)
    {
        $user->increment('reports_count');

        $reportsCount = $user->reports_count;

        if ($reportsCount >= 5) {
            $user->update([
                'is_banned' => true,
                'banned_at' => now(),
                'is_suspended' => false,
                'suspended_until' => null,
            ]);

            Log::warning('User banned permanently', [
                'user_id' => $user->id,
                'reports_count' => $reportsCount
            ]);
        }
        else {
            $suspendedUntil = now()->addDays(5);

            $user->update([
                'is_suspended' => true,
                'suspended_until' => $suspendedUntil,
            ]);

            Report::where('target_user_id', $user->id)
                ->where('admin_action', 'approved')
                ->latest()
                ->first()
                ->update([
                    'suspended_until' => $suspendedUntil,
                    'suspension_count' => $reportsCount,
                ]);

            Log::info('User suspended for 5 days', [
                'user_id' => $user->id,
                'suspended_until' => $suspendedUntil,
                'report_count' => $reportsCount
            ]);
        }
    }

    public function statistics()
    {
        try {
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $stats = [
                'total_reports' => Report::count(),
                'pending_reports' => Report::where('admin_action', 'pending')->count(),
                'approved_reports' => Report::where('admin_action', 'approved')->count(),
                'rejected_reports' => Report::where('admin_action', 'rejected')->count(),
                'reports_by_type' => Report::select('report_type', DB::raw('count(*) as count'))
                    ->groupBy('report_type')
                    ->get(),
                'top_reported_users' => User::where('reports_count', '>', 0)
                    ->orderBy('reports_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'name', 'user_name', 'reports_count', 'is_suspended', 'is_banned']),
                'suspended_users' => User::where('is_suspended', true)->count(),
                'banned_users' => User::where('is_banned', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching report statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    public function myReports(Request $request)
    {
        try {
            $user = auth()->user();

            $reports = Report::where('reporter_user_id', $user->id)
                ->with(['targetUser'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $reports,
                'message' => 'Your reports fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching my reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your reports'
            ], 500);
        }
    }

    public function reportsAgainstMe(Request $request)
    {
        try {
            $user = auth()->user();

            $reports = Report::where('target_user_id', $user->id)
                ->with(['reporterUser'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $reports,
                'message' => 'Reports against you fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching reports against me: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reports against you'
            ], 500);
        }
    }

    public function destroy(Report $report)
    {
        try {
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Report deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete report'
            ], 500);
        }
    }

    public function checkUserStatus()
    {
        try {
            $user = auth()->user();

            if ($user->is_banned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been permanently banned due to multiple reports',
                    'data' => [
                        'status' => 'banned',
                        'banned_at' => $user->banned_at,
                    ]
                ], 403);
            }

            if ($user->is_suspended && $user->suspended_until && now()->lt($user->suspended_until)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is suspended until ' . $user->suspended_until->format('Y-m-d H:i:s'),
                    'data' => [
                        'status' => 'suspended',
                        'suspended_until' => $user->suspended_until,
                        'days_remaining' => now()->diffInDays($user->suspended_until),
                    ]
                ], 403);
            }

            if ($user->is_suspended && $user->suspended_until && now()->gte($user->suspended_until)) {
                $user->update([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Account is active',
                'data' => [
                    'status' => 'active',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking user status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check account status'
            ], 500);
        }
    }
}
