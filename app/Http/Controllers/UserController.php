<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
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
            'data' => $users
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
            'data' => $user
        ], 201);
    }

    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User Fetched Successfully',
            'data' => $user
        ]);
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
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
            'data' => $user->fresh()
        ]);
    }

    public function destroy(string $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found']
                , 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User Deleted Successfully',
        ]);
    }

public function getNotifications(Request $request)
{
    try {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $user->unreadNotifications->count()
            ],
            'message' => 'Notifications fetched successfully'
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching notifications: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch notifications'
        ], 500);
    }
}

public function markNotificationAsRead($notificationId)
{
    try {
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);

    } catch (\Exception $e) {
        Log::error('Error marking notification as read: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to mark notification as read'
        ], 500);
    }
}

public function markAllNotificationsAsRead()
{
    try {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);

    } catch (\Exception $e) {
        Log::error('Error marking all notifications as read: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to mark all notifications as read'
        ], 500);
    }
}

    public function getNotifications(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $notifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $formattedNotifications = $notifications->through(function ($notification) {
                $data = $notification->data;

                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'general',
                    'team_id' => $data['team_id'] ?? null,
                    'team_name' => $data['team_name'] ?? null,
                    'message' => $data['message'] ?? $notification->data['message'] ?? 'New notification',
                    'action_url' => $data['action_url'] ?? null,
                    'action_text' => $data['action_text'] ?? null,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'is_read' => !is_null($notification->read_at),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $formattedNotifications,
                    'unread_count' => $user->unreadNotifications->count(),
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                ],
                'message' => 'Notifications fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    public function markNotificationAsRead($notificationId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $notification = $user->notifications()->where('id', $notificationId)->first();

            if ($notification) {
                $notification->markAsRead();

                return response()->json([
                    'success' => true,
                    'message' => 'Notification marked as read',
                    'data' => [
                        'notification_id' => $notificationId,
                        'read_at' => $notification->read_at
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    public function markAllNotificationsAsRead()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = $user->unreadNotifications->count();
            $user->unreadNotifications->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }

    public function deleteNotification($notificationId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $notification = $user->notifications()->where('id', $notificationId)->first();

            if ($notification) {
                $notification->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Notification deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    public function deleteReadNotifications()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = $user->notifications()->whereNotNull('read_at')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Read notifications deleted successfully',
                'data' => [
                    'deleted_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting read notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete read notifications'
            ], 500);
        }
    }

    public function getUnreadCount()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $user->unreadNotifications->count()
                ],
                'message' => 'Unread count fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count'
            ], 500);
        }
    }
}
