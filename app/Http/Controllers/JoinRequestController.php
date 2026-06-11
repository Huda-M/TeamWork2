<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\JoinRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JoinRequestController extends Controller
{
    /**
     * إرسال طلب انضمام إلى فريق
     */
    public function store(Request $request, Team $team)
    {
        try {
            $user = Auth::user();
            if ($user->role !== 'programmer') {
                return response()->json(['success' => false, 'message' => 'Only programmers can send join requests'], 403);
            }

            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            // التحقق من العضوية الحالية
            if ($team->isMember($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'You are already a member of this team'], 400);
            }

            // التحقق من وجود طلب معلق
            $existing = JoinRequest::where('team_id', $team->id)
                ->where('programmer_id', $programmer->id)
                ->where('status', 'pending')
                ->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'You already have a pending request for this team'], 400);
            }

            DB::beginTransaction();
            $joinRequest = JoinRequest::create([
                'team_id' => $team->id,
                'programmer_id' => $programmer->id,
                'status' => 'pending',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Join request sent successfully',
                'data' => $joinRequest
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending join request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send join request'], 500);
        }
    }

    /**
     * عرض طلبات الانضمام التي أرسلها المبرمج الحالي
     */
    public function index()
    {
        try {
            $programmer = Auth::user()->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            $joinRequests = $programmer->joinRequests()->with('team')->get();
            return response()->json(['success' => true, 'data' => $joinRequests]);
        } catch (\Exception $e) {
            Log::error('Error fetching join requests: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch join requests'], 500);
        }
    }

    /**
     * عرض طلبات الانضمام لفريق معين (للقائد فقط)
     */
    public function teamJoinRequests(Team $team)
    {
        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            if (!$team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only the team leader can view join requests'], 403);
            }

            $joinRequests = $team->joinRequests()
                ->with('programmer.user')
                ->where('status', 'pending')
                ->get();

            return response()->json(['success' => true, 'data' => $joinRequests]);
        } catch (\Exception $e) {
            Log::error('Error fetching team join requests: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch team join requests'], 500);
        }
    }

    /**
     * قبول أو رفض طلب الانضمام (للقائد فقط)
     */
    public function update(Request $request, JoinRequest $joinRequest)
    {
        try {
            $user = Auth::user();
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['success' => false, 'message' => 'Programmer profile not found'], 404);
            }

            $team = $joinRequest->team;
            if (!$team->isLeader($programmer->id)) {
                return response()->json(['success' => false, 'message' => 'Only the team leader can approve or reject join requests'], 403);
            }

            $validated = $request->validate([
                'status' => 'required|in:approved,rejected',
                'rejection_reason' => 'nullable|string|max:255',
            ]);

            if ($joinRequest->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'This request has already been processed'], 400);
            }

            DB::beginTransaction();

            if ($validated['status'] === 'approved') {
                // إضافة المبرمج إلى الفريق كعضو عادي
                $team->members()->attach($joinRequest->programmer_id, [
                    'role' => 'member',
                    'joined_at' => now(),
                    'joined_by' => $programmer->id,
                ]);
                $joinRequest->update(['status' => 'approved']);
                $message = 'Join request approved. You are now a member of the team.';
            } else {
                $joinRequest->update([
                    'status' => 'rejected',
                    'rejection_reason' => $validated['rejection_reason'] ?? null
                ]);
                $message = 'Join request rejected.';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $joinRequest->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating join request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update join request'], 500);
        }
    }
}
