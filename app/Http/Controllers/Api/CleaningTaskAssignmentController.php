<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\CleaningTaskAssignment;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CleaningTaskAssignmentController extends BaseApiController
{
    public function index(Request $request, CleaningTask $cleaningTask)
    {
        $this->authorizeAssignments($request->user(), $cleaningTask);

        $assignments = $cleaningTask->assignments()
            ->with('user:id,name')
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_date', $request->input('date')))
            ->orderBy('scheduled_date')
            ->get();

        return response()->json($assignments);
    }

    public function store(Request $request, CleaningTask $cleaningTask)
    {
        try {
            $this->authorizeAssignments($request->user(), $cleaningTask);

            $data = $request->validate([
                'user_id' => 'required|exists:users,id',
                'scheduled_date' => 'required|date',
            ]);

            $scheduledDate = Carbon::parse($data['scheduled_date'])->toDateString();

            $keys = [
                'cleaning_task_id' => $cleaningTask->id,
                'user_id' => $data['user_id'],
                'scheduled_date' => $scheduledDate,
            ];

            // Use database-level upsert to avoid race-condition duplicate key errors
            CleaningTaskAssignment::upsert(
                [[
                    'cleaning_task_id' => $keys['cleaning_task_id'],
                    'user_id' => $keys['user_id'],
                    'scheduled_date' => $keys['scheduled_date'],
                    'status' => 'assigned',
                    'notified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['cleaning_task_id', 'user_id', 'scheduled_date'],
                ['status', 'notified_at', 'updated_at']
            );

            // Retrieve the upserted record
            $assignment = CleaningTaskAssignment::where($keys)->firstOrFail();

            // Load relationships for notification
            $assignment->load('user', 'task.area');

            // Try to send notification, but don't fail the assignment if it fails
            try {
                $this->notifyCaregiverAssignment($assignment);
            } catch (\Exception $e) {
                // Log the notification error but don't fail the assignment
                \Log::warning('Failed to send notification for caregiver assignment', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Caregiver assigned.',
                'data' => $assignment,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error assigning caregiver to task', [
                'task_id' => $cleaningTask->id,
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => config('app.debug') 
                    ? $e->getMessage() 
                    : 'Failed to assign caregiver. Please try again.',
            ], 500);
        }
    }

    public function destroy(Request $request, $cleaningTaskAssignment)
    {
        $assignment = CleaningTaskAssignment::findOrFail($cleaningTaskAssignment);
        $this->authorizeAssignments($request->user(), $assignment->task);

        $assignment->delete();

        return response()->json([
            'message' => 'Assignment removed.',
        ]);
    }

    private function authorizeAssignments($user, CleaningTask $task): void
    {
        if (!$user || !$user->hasPermission('edit_cleaning_areas')) {
            abort(403, 'You do not have permission to assign housekeeping tasks.');
        }

        // Load area relationship if not already loaded
        if (!$task->relationLoaded('area')) {
            $task->load('area');
        }

        // Check branch access if user has an assigned branch
        if ($user->assigned_branch_id) {
            if (!$task->area) {
                abort(422, 'This task is not associated with a cleaning area. Please contact support.');
            }

            if ($user->assigned_branch_id !== $task->area->branch_id) {
                abort(403, 'You cannot assign tasks for another branch.');
            }
        }
    }

    private function notifyCaregiverAssignment(CleaningTaskAssignment $assignment): void
    {
        $user = $assignment->user;
        if (!$user) {
            return;
        }

        // Ensure task and area are loaded
        if (!$assignment->relationLoaded('task')) {
            $assignment->load('task.area');
        }

        $task = $assignment->task;
        if (!$task) {
            return;
        }

        $areaName = $task->area?->name ?? 'Housekeeping';

        Notification::create([
            'user_id' => $user->id,
            'type' => 'housekeeping_assignment',
            'title' => 'New Housekeeping Task Assigned',
            'message' => sprintf(
                '%s (%s) on %s.',
                $task->title,
                $areaName,
                Carbon::parse($assignment->scheduled_date)->toFormattedDateString()
            ),
            'icon' => 'heroicon-o-sparkles',
            'icon_color' => '#059669',
            'action_url' => '/app/housekeeping',
            'metadata' => [
                'task_id' => $task->id,
                'scheduled_date' => $assignment->scheduled_date,
            ],
        ]);
    }
}
