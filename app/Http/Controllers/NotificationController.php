<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use App\Http\Resources\NotificationResource;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $query = QueryBuilder::for(Notification::class)
            ->allowedFilters(['read_at', 'type'])
            ->where('notifiable_id', $user->id);

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        $resource = NotificationResource::collection($notifications)->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => 'Notifications fetched successfully',
            'notifications' => $resource['data'],
            'pagination' => [
                'total' => $resource['meta']['total'] ?? null,
                'per_page' => $resource['meta']['per_page'] ?? null,
                'current_page' => $resource['meta']['current_page'] ?? null,
                'last_page' => $resource['meta']['last_page'] ?? null,
            ],
            'links' => [
                'next' => $resource['links']['next'] ?? null,
                'prev' => $resource['links']['prev'] ?? null,
            ],
        ]);
    }

    public function unreadCount()
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $unreadCount = Notification::query()->where('notifiable_id', $user->id)
            ->where('read_at', null)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread count fetched successfully',
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markAsRead($id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $notification = Notification::query()->where('notifiable_id', $user->id)->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read successfully',
            'data' => $notification,
        ]);
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        Notification::query()->where('notifiable_id', $user->id)
            ->where('read_at', null)
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read successfully',
        ]);
    }

    //     public function markNotificationAsRead($notificationId)
    // {
    //     try {
    //         $user = Auth::user();

    //         $notification = $user->notifications()->where('id', $notificationId)->first();

    //         if ($notification) {
    //             $notification->markAsRead();
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Notification marked as read'
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Error marking notification as read: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to mark notification as read'
    //         ], 500);
    //     }
    // }

    // public function markAllNotificationsAsRead()
    // {
    //     try {
    //         $user = Auth::user();
    //         $user->unreadNotifications->markAsRead();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'All notifications marked as read'
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Error marking all notifications as read: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to mark all notifications as read'
    //         ], 500);
    //     }
    // }

    //     public function getNotifications(Request $request)
    //     {
    //         try {
    //             $user = Auth::user();

    //             if (!$user) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'User not authenticated'
    //                 ], 401);
    //             }

    //             $notifications = $user->notifications()
    //                 ->orderBy('created_at', 'desc')
    //                 ->paginate(20);

    //             $formattedNotifications = $notifications->through(function ($notification) {
    //                 $data = $notification->data;

    //                 return [
    //                     'id' => $notification->id,
    //                     'type' => $data['type'] ?? 'general',
    //                     'team_id' => $data['team_id'] ?? null,
    //                     'team_name' => $data['team_name'] ?? null,
    //                     'message' => $data['message'] ?? $notification->data['message'] ?? 'New notification',
    //                     'action_url' => $data['action_url'] ?? null,
    //                     'action_text' => $data['action_text'] ?? null,
    //                     'read_at' => $notification->read_at,
    //                     'created_at' => $notification->created_at,
    //                     'is_read' => !is_null($notification->read_at),
    //                 ];
    //             });

    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [
    //                     'notifications' => $formattedNotifications,
    //                     'unread_count' => $user->unreadNotifications->count(),
    //                     'total' => $notifications->total(),
    //                     'per_page' => $notifications->perPage(),
    //                     'current_page' => $notifications->currentPage(),
    //                     'last_page' => $notifications->lastPage(),
    //                 ],
    //                 'message' => 'Notifications fetched successfully'
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('Error fetching notifications: ' . $e->getMessage(), [
    //                 'file' => $e->getFile(),
    //                 'line' => $e->getLine()
    //             ]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to fetch notifications'
    //             ], 500);
    //         }
    //     }

    //     public function deleteNotification($notificationId)
    //     {
    //         try {
    //             $user = Auth::user();

    //             if (!$user) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'User not authenticated'
    //                 ], 401);
    //             }

    //             $notification = $user->notifications()->where('id', $notificationId)->first();

    //             if ($notification) {
    //                 $notification->delete();

    //                 return response()->json([
    //                     'success' => true,
    //                     'message' => 'Notification deleted successfully'
    //                 ]);
    //             }

    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Notification not found'
    //             ], 404);

    //         } catch (\Exception $e) {
    //             Log::error('Error deleting notification: ' . $e->getMessage());
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to delete notification'
    //             ], 500);
    //         }
    //     }

    //     public function deleteReadNotifications()
    //     {
    //         try {
    //             $user = Auth::user();

    //             if (!$user) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'User not authenticated'
    //                 ], 401);
    //             }

    //             $count = $user->notifications()->whereNotNull('read_at')->delete();

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Read notifications deleted successfully',
    //                 'data' => [
    //                     'deleted_count' => $count
    //                 ]
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('Error deleting read notifications: ' . $e->getMessage());
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to delete read notifications'
    //             ], 500);
    //         }
    //     }

    //     public function getUnreadCount()
    //     {
    //         try {
    //             $user = Auth::user();

    //             if (!$user) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'User not authenticated'
    //                 ], 401);
    //             }

    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [
    //                     'unread_count' => $user->unreadNotifications->count()
    //                 ],
    //                 'message' => 'Unread count fetched successfully'
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('Error fetching unread count: ' . $e->getMessage());
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to fetch unread count'
    //             ], 500);
    //         }
    //     }
}
