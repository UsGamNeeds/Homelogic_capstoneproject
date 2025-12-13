<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarkMissedMedications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medications:mark-missed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark medications as missed if not administered within 5 hours of scheduled time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting missed medications check...');
        
        $now = Carbon::now(config('app.timezone'));
        $today = $now->format('Y-m-d');
        $systemUserId = 1; // Fallback to ID 1 (Admin) for system actions

        // Get all active medications
        // We need to check start/end dates as well
        $medications = Medication::where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->get();

        $count = 0;

        foreach ($medications as $medication) {
            // Check each of the 4 possible time slots
            for ($i = 1; $i <= 4; $i++) {
                $timeField = "time_{$i}";
                $scheduledTimeStr = $medication->$timeField;

                if (!$scheduledTimeStr) {
                    continue;
                }

                // Parse scheduled time for today
                try {
                    $scheduledTime = Carbon::createFromFormat('H:i', $scheduledTimeStr, config('app.timezone'));
                    // Set date to today
                    $scheduledTime->setDate($now->year, $now->month, $now->day);
                } catch (\Exception $e) {
                    Log::error("Invalid time format for medication {$medication->id}: {$scheduledTimeStr}");
                    continue;
                }

                // Calculate 5 hours past scheduled time
                $deadline = $scheduledTime->copy()->addHours(5);

                // If currently past the deadline
                if ($now->greaterThan($deadline)) {
                    // Check if a record exists for this medication, today, around this time
                    // We look for any record for this slot to avoid duplicates
                    // A "slot" is defined by the scheduled time
                    
                    // We check if ANY administration exists for this medication on this day
                    // that matches this specific scheduled time
                    // Note: In a real scenario, we might want to be more flexible with "matching time",
                    // but for "Missed" logic, we need to be sure we haven't already logged it or administered it.
                    
                    // Check for existing administration (completed, missed, refused, etc.)
                    // We look for a record with administered_at matching the scheduled time (for missed)
                    // OR a completed record close to the scheduled time.
                    
                    // Simplification: Check if ANY record exists for this medication today
                    // AND if that record's time is "close enough" to this scheduled time (e.g. within 4 hours before or 5 hours after)
                    // This is complex because a PRN might have multiple.
                    // But for scheduled meds (BID, TID), they have specific times.
                    
                    // Let's check for a record explicitly logged for this scheduled time (if we store scheduled time)
                    // OR check if we have a record within the window [Scheduled - 2h, Scheduled + 5h]
                    
                    $windowStart = $scheduledTime->copy()->subHours(2);
                    $windowEnd = $scheduledTime->copy()->addHours(5);
                    
                    $exists = MedicationAdministration::where('medication_id', $medication->id)
                        ->whereBetween('administered_at', [$windowStart, $windowEnd])
                        ->exists();

                    if (!$exists) {
                        // Create missed record
                        $this->info("Marking medication {$medication->id} as missed for {$scheduledTimeStr}");
                        
                        MedicationAdministration::create([
                            'medication_id' => $medication->id,
                            'resident_id' => $medication->resident_id,
                            'branch_id' => $medication->branch_id,
                            'administered_by' => $systemUserId,
                            'status' => 'missed',
                            'administered_at' => $scheduledTime, // Log at the scheduled time
                            'notes' => 'Automatically marked as missed (5 hours overdue)',
                        ]);
                        
                        $count++;
                    }
                }
            }
        }

        $this->info("Completed. Marked {$count} medications as missed.");
    }
}
