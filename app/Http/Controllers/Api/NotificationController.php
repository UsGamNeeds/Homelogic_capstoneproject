<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Resident;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = $user->notifications();

        // Filter by read status
        if ($request->has('unread_only') && $request->get('unread_only') === 'true') {
            $query->unread();
        } elseif ($request->has('read_only') && $request->get('read_only') === 'true') {
            $query->read();
        }

        // Limit results
        $limit = $request->get('limit', 20);
        $notifications = $query->limit($limit * 2)->get(); // Get more to filter after

        // Filter by facility for non-super-admin users
        if ($user->role !== 'super_admin' && $user->facility_id) {
            $notifications = $notifications->filter(function ($notification) use ($user) {
                $metadata = $notification->metadata ?? [];
                
                // Show notification if:
                // 1. It has facility_id matching user's facility
                // 2. It's about a user in the same facility (check user_id in metadata)
                
                if (isset($metadata['facility_id'])) {
                    return (int)$metadata['facility_id'] === (int)$user->facility_id;
                }
                
                // Check if notification is about a user in the same facility
                if (isset($metadata['user_id'])) {
                    $notifiedUserId = $metadata['user_id'];
                    $notifiedUser = User::find($notifiedUserId);
                    if ($notifiedUser && $notifiedUser->facility_id && (int)$notifiedUser->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a resident in the same facility
                if (isset($metadata['resident_id'])) {
                    $resident = Resident::with('branch')->find($metadata['resident_id']);
                    if ($resident && $resident->branch && $resident->branch->facility_id && (int)$resident->branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a branch in the same facility
                if (isset($metadata['branch_id'])) {
                    $branch = Branch::find($metadata['branch_id']);
                    if ($branch && $branch->facility_id && (int)$branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Don't show notifications without facility context for non-super admins
                return false;
            })->take($limit)->values();
        } else {
            $notifications = $notifications->take($limit);
        }

        // Calculate unread count with facility filtering
        $unreadQuery = $user->unreadNotifications();
        $unreadNotifications = $unreadQuery->get();
        
        if ($user->role !== 'super_admin' && $user->facility_id) {
            $unreadNotifications = $unreadNotifications->filter(function ($notification) use ($user) {
                $metadata = $notification->metadata ?? [];
                
                if (isset($metadata['facility_id'])) {
                    return (int)$metadata['facility_id'] === (int)$user->facility_id;
                }
                
                if (isset($metadata['user_id'])) {
                    $notifiedUserId = $metadata['user_id'];
                    $notifiedUser = \App\Models\User::find($notifiedUserId);
                    if ($notifiedUser && $notifiedUser->facility_id && (int)$notifiedUser->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a resident in the same facility
                if (isset($metadata['resident_id'])) {
                    $resident = \App\Models\Resident::with('branch')->find($metadata['resident_id']);
                    if ($resident && $resident->branch && $resident->branch->facility_id && (int)$resident->branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a branch in the same facility
                if (isset($metadata['branch_id'])) {
                    $branch = \App\Models\Branch::find($metadata['branch_id']);
                    if ($branch && $branch->facility_id && (int)$branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Don't count notifications without facility context for non-super admins
                return false;
            });
        }
        
        $unreadCount = $unreadNotifications->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get count of unread notifications
     */
    public function count(): JsonResponse
    {
        $user = auth()->user();
        $unreadNotifications = $user->unreadNotifications()->get();

        // Filter by facility for non-super-admin users
        if ($user->role !== 'super_admin' && $user->facility_id) {
            $unreadNotifications = $unreadNotifications->filter(function ($notification) use ($user) {
                $metadata = $notification->metadata ?? [];
                
                if (isset($metadata['facility_id'])) {
                    return (int)$metadata['facility_id'] === (int)$user->facility_id;
                }
                
                if (isset($metadata['user_id'])) {
                    $notifiedUserId = $metadata['user_id'];
                    $notifiedUser = \App\Models\User::find($notifiedUserId);
                    if ($notifiedUser && $notifiedUser->facility_id && (int)$notifiedUser->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a resident in the same facility
                if (isset($metadata['resident_id'])) {
                    $resident = \App\Models\Resident::with('branch')->find($metadata['resident_id']);
                    if ($resident && $resident->branch && $resident->branch->facility_id && (int)$resident->branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Check if notification is about a branch in the same facility
                if (isset($metadata['branch_id'])) {
                    $branch = \App\Models\Branch::find($metadata['branch_id']);
                    if ($branch && $branch->facility_id && (int)$branch->facility_id === (int)$user->facility_id) {
                        return true;
                    }
                }
                
                // Don't count notifications without facility context for non-super admins
                return false;
            });
        }

        $unreadCount = $unreadNotifications->count();

        return response()->json([
            'count' => $unreadCount,
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->notifications()->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }
}
