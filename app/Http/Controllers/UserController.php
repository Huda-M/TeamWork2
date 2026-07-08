<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Users fetched successfully',
            'data' => $users,
        ]);
    }
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'User Created Successfully',
            'data' => $user,
        ], 201);
    }
    public function show(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'User Fetched Successfully',
            'data' => $user,
        ]);
    }
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }
        $validated = $request->validated();
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        $user->update($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'User Updated Successfully',
            'data' => $user->fresh(),
        ]);
    }
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $targetUser = User::find($id);
            if (! $targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }
            if ($user->id !== $targetUser->id && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
            $targetUser->delete();
            $targetUser->tokens()->delete();
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
            ], 500);
        }
    }
}
