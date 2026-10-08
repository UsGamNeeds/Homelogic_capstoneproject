<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountDeletionRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountDeletionRequestController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $hasPending = AccountDeletionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return $this->error('You already have a deletion request awaiting review.', 409);
        }

        $deletionRequest = AccountDeletionRequest::create([
            'user_id' => $user->id,
            'facility_id' => $user->facility_id,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        // User::getNameAttribute() already falls back to the full name, then the
        // email address, so this is never blank.
        $staffName = $user->name;

        $admins = User::where('facility_id', $user->facility_id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'administrator')
                    ->orWhereHas('roles', function ($r) {
                        $r->where('name', 'administrator');
                    });
            })
            ->get();

        foreach ($admins as $admin) {
            Notification::createForUser([
                'user_id' => $admin->id,
                'type' => 'account_deletion_request',
                'title' => 'Account Deletion Request',
                'message' => "{$staffName} has requested that their account be deleted.",
                'icon' => 'user-x',
                'icon_color' => 'text-red-600',
                'action_url' => "/team/users/{$user->id}",
                'metadata' => [
                    'account_deletion_request_id' => $deletionRequest->id,
                    'user_id' => $user->id,
                ],
            ], $admin);
        }

        try {
            app(NotificationService::class)->sendAccountDeletionRequestEmail($deletionRequest, $admins);
        } catch (\Exception $e) {
            Log::error('Failed to send account deletion request notification emails', [
                'account_deletion_request_id' => $deletionRequest->id,
                'error' => $e->getMessage(),
                'facility_id' => $user->facility_id,
            ]);
        }

        return $this->success([
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status,
            'created_at' => $deletionRequest->created_at,
        ], 'Account deletion request submitted.', 201);
    }
}
