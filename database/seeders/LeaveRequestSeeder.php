<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->info('No users found. Please run user seeders first.');
            return;
        }

        $defaultBranchId = Branch::value('id');

        $sampleRequests = [
            [
                'staff_id' => $users->first()->id,
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(9),
                'leave_type' => 'medical',
                'branch_id' => $users->first()->assigned_branch_id ?? $defaultBranchId,
                'reason' => 'I would like to bring to your attention that, I have medical referral appointment on 10/28/25 at Swedish hospital at 9:30am. I will be grateful to leave on said date at 8:00am in the morning and be back at 11am. I hope my request would meet your kind consideration and approval. Thank you.',
                'status' => 'pending',
            ],
            [
                'staff_id' => $users->skip(1)->first()?->id ?? $users->first()->id,
                'start_date' => now()->addDays(14),
                'end_date' => now()->addDays(16),
                'leave_type' => 'personal',
                'branch_id' => optional($users->skip(1)->first())->assigned_branch_id ?? $defaultBranchId,
                'reason' => 'Family emergency - need to attend to urgent family matter out of state. Will return as soon as possible.',
                'status' => 'approved',
                'approved_by' => $users->first()->id,
                'approved_at' => now()->subDays(2),
                'approval_notes' => 'Approved due to valid reason.',
            ],
            [
                'staff_id' => $users->skip(2)->first()?->id ?? $users->first()->id,
                'start_date' => now()->addDays(21),
                'end_date' => now()->addDays(28),
                'leave_type' => 'vacation',
                'branch_id' => optional($users->skip(2)->first())->assigned_branch_id ?? $defaultBranchId,
                'reason' => 'Annual vacation leave. Planning to visit family and take some time off for rest and relaxation.',
                'status' => 'declined',
                'approved_by' => $users->first()->id,
                'approved_at' => now()->subDays(1),
                'approval_notes' => 'Unable to approve due to staffing constraints during this period. Please consider alternative dates.',
            ],
            [
                'staff_id' => $users->skip(3)->first()?->id ?? $users->first()->id,
                'start_date' => now()->addDays(3),
                'end_date' => now()->addDays(3),
                'leave_type' => 'personal',
                'branch_id' => optional($users->skip(3)->first())->assigned_branch_id ?? $defaultBranchId,
                'reason' => 'Personal appointment - dental checkup and cleaning. Will return to work the same day.',
                'status' => 'pending',
            ],
            [
                'staff_id' => $users->skip(4)->first()?->id ?? $users->first()->id,
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(12),
                'leave_type' => 'bereavement',
                'branch_id' => optional($users->skip(4)->first())->assigned_branch_id ?? $defaultBranchId,
                'reason' => 'Bereavement leave - attending funeral services for immediate family member.',
                'status' => 'approved',
                'approved_by' => $users->first()->id,
                'approved_at' => now()->subHours(6),
                'approval_notes' => 'Approved with condolences.',
            ],
        ];

        foreach ($sampleRequests as $requestData) {
            LeaveRequest::create($requestData);
        }

        $this->command->info('Sample leave requests created successfully.');
    }
}
