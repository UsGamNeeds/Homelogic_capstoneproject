<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class MarkMissedMedications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medications:mark-missed {--date= : Date to check (Y-m-d format, defaults to yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark medications as missed if not administered by end of day (runs daily at end of day)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the date to check - default to yesterday if running at end of day, or today if running manually
        $checkDate = $this->option('date');
        if ($checkDate) {
            try {
                $targetDate = Carbon::createFromFormat('Y-m-d', $checkDate, config('app.timezone'));
            } catch (\Exception $e) {
                $this->error("Invalid date format. Use Y-m-d format (e.g., 2025-12-25)");
                return 1;
            }
        } else {
            // Default to yesterday if running at end of day (checking the day that just ended)
            // But if running during the day, check today's missed doses from previous scheduled times
            $targetDate = Carbon::now(config('app.timezone'));
            // If it's early in the day (before 1 AM), check yesterday, otherwise check today
            if ($targetDate->hour < 1) {
                $targetDate->subDay();
            }
        }

        $this->info("Checking missed medications for date: {$targetDate->format('Y-m-d')}");
        
        $dateStr = $targetDate->format('Y-m-d');
        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();

        // Get system user (first admin user, or create a system user)
        $systemUser = User::whereIn('role', ['super_admin', 'administrator', 'admin'])->first();
        if (!$systemUser) {
            // Fallback to user ID 1 if no admin exists
            $systemUserId = 1;
        } else {
            $systemUserId = $systemUser->id;
        }

        // Get all active medications that were active on the target date
        $medications = Medication::where('is_active', true)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $dateStr);
            })
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $dateStr);
            })
            ->get();

        $count = 0;
        $windowMinutes = 60; // 60 minutes before and after scheduled time

        foreach ($medications as $medication) {
            // Check each of the 4 possible time slots
            for ($i = 1; $i <= 4; $i++) {
                $timeField = "time_{$i}";
                $scheduledTimeStr = $medication->$timeField;

                if (!$scheduledTimeStr) {
                    continue;
                }

                // Parse scheduled time for the target date
                try {
                    // Parse time in H:i format
                    $timeParts = explode(':', $scheduledTimeStr);
                    if (count($timeParts) !== 2) {
                        Log::error("Invalid time format for medication {$medication->id}: {$scheduledTimeStr}");
                        continue;
                    }

                    $scheduledTime = $targetDate->copy();
                    $scheduledTime->setTime((int)$timeParts[0], (int)$timeParts[1], 0);
                } catch (\Exception $e) {
                    Log::error("Error parsing time for medication {$medication->id}: {$scheduledTimeStr} - " . $e->getMessage());
                    continue;
                }

                // Calculate administration window (60 minutes before and after scheduled time)
                $windowStart = $scheduledTime->copy()->subMinutes($windowMinutes);
                $windowEnd = $scheduledTime->copy()->addMinutes($windowMinutes);

                // Check if there's already an administration record for this medication on this date
                // within the administration window
                $hasAdministration = MedicationAdministration::where('medication_id', $medication->id)
                    ->whereDate('administered_at', $dateStr)
                    ->whereBetween('administered_at', [$windowStart, $windowEnd])
                    ->exists();

                if (!$hasAdministration) {
                    // Check if a missed record already exists for this time slot on this date
                    $hasMissedRecord = MedicationAdministration::where('medication_id', $medication->id)
                        ->whereDate('administered_at', $dateStr)
                        ->whereTime('administered_at', '=', $scheduledTime->format('H:i:s'))
                        ->where('status', 'missed')
                        ->exists();

                    if (!$hasMissedRecord) {
                        // Create missed record at the scheduled time
                        try {
                            MedicationAdministration::create([
                                'medication_id' => $medication->id,
                                'resident_id' => $medication->resident_id,
                                'branch_id' => $medication->branch_id,
                                'administered_by' => $systemUserId,
                                'status' => 'missed',
                                'administered_at' => $scheduledTime,
                                'notes' => 'Automatically marked as missed at end of day',
                            ]);

                            $this->info("Marked medication ID {$medication->id} ({$medication->name}) as missed for {$scheduledTime->format('Y-m-d H:i')}");
                            $count++;
                        } catch (\Exception $e) {
                            Log::error("Error creating missed medication record for medication {$medication->id}: " . $e->getMessage());
                            $this->warn("Failed to mark medication ID {$medication->id} as missed: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $this->info("Completed. Marked {$count} medication doses as missed for {$dateStr}.");
        Log::info("MarkMissedMedications command completed. Marked {$count} medication doses as missed for {$dateStr}.");

        return 0;
    }
}
