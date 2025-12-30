<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medication;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\MailConfigurationService;
use App\Services\EmailPreferenceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyMedicationWindowOpening extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medications:notify-window-opening';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notifications to caregivers when medication administration windows open';

    protected $mailConfigService;
    protected $emailPreferenceService;

    public function __construct(
        MailConfigurationService $mailConfigService,
        EmailPreferenceService $emailPreferenceService
    ) {
        parent::__construct();
        $this->mailConfigService = $mailConfigService;
        $this->emailPreferenceService = $emailPreferenceService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now(config('app.timezone'));
        $windowMinutes = 60; // 60 minutes before and after scheduled time
        $notificationWindowMinutes = 5; // Notify 5 minutes before window opens
        
        $this->info("Checking for medication administration windows opening...");

        // Get all active medications
        $medications = Medication::where('is_active', true)
            ->where(function ($q) use ($now) {
                $dateStr = $now->format('Y-m-d');
                $q->whereNull('start_date')->orWhere('start_date', '<=', $dateStr);
            })
            ->where(function ($q) use ($now) {
                $dateStr = $now->format('Y-m-d');
                $q->whereNull('end_date')->orWhere('end_date', '>=', $dateStr);
            })
            ->with(['resident', 'drug', 'resident.branch'])
            ->get();

        $notifiedCount = 0;

        foreach ($medications as $medication) {
            if (!$medication->branch_id || !$medication->resident) {
                continue;
            }

            // Check each of the 4 possible time slots
            for ($i = 1; $i <= 4; $i++) {
                $timeField = "time_{$i}";
                $scheduledTimeStr = $medication->$timeField;

                if (!$scheduledTimeStr) {
                    continue;
                }

                // Parse scheduled time for today
                try {
                    $timeParts = explode(':', $scheduledTimeStr);
                    if (count($timeParts) !== 2) {
                        continue;
                    }

                    $scheduledTime = $now->copy();
                    $scheduledTime->setTime((int)$timeParts[0], (int)$timeParts[1], 0);

                    // Calculate administration window
                    $windowStart = $scheduledTime->copy()->subMinutes($windowMinutes);
                    $windowEnd = $scheduledTime->copy()->addMinutes($windowMinutes);

                    // Check if window is opening soon (within notification window)
                    $notificationTime = $windowStart->copy()->subMinutes($notificationWindowMinutes);
                    
                    // Only notify if we're within the notification window and window hasn't started yet
                    if ($now->greaterThanOrEqualTo($notificationTime) && $now->lessThan($windowStart)) {
                        // Create a unique key for this medication window
                        $cacheKey = "medication_window_notified_{$medication->id}_{$scheduledTime->format('Y-m-d_H-i')}";
                        
                        // Check if we've already notified for this window
                        if (!Cache::has($cacheKey)) {
                            // Get all caregivers in this branch
                            $caregivers = User::where('assigned_branch_id', $medication->branch_id)
                                ->where('role', 'caregiver')
                                ->where('is_active', true)
                                ->get();

                            if ($caregivers->count() > 0) {
                                // Get facility from resident's branch
                                $facility = $medication->resident->branch->facility ?? null;
                                
                                // Configure mail for facility if available
                                if ($facility) {
                                    $this->mailConfigService->configureForFacility($facility);
                                }

                                // Filter caregivers based on email preferences
                                $caregiversToNotify = $this->emailPreferenceService->filterUsersForEmail(
                                    $caregivers,
                                    'medication_window_opening',
                                    $facility
                                );

                                // Format times for email
                                $scheduledTimeFormatted = $scheduledTime->format('g:i A');
                                $windowStartFormatted = $windowStart->format('g:i A');
                                $windowEndFormatted = $windowEnd->format('g:i A');

                                foreach ($caregiversToNotify as $caregiver) {
                                    if ($caregiver->email) {
                                        try {
                                            \Illuminate\Support\Facades\Mail::to($caregiver->email)->send(
                                                new \App\Mail\MedicationWindowOpeningNotification(
                                                    $medication,
                                                    $scheduledTimeFormatted,
                                                    $windowStartFormatted,
                                                    $windowEndFormatted
                                                )
                                            );

                                            Log::info('Medication window opening email sent', [
                                                'to' => $caregiver->email,
                                                'medication_id' => $medication->id,
                                                'scheduled_time' => $scheduledTime->format('Y-m-d H:i'),
                                                'facility_id' => $facility?->id,
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('Failed to send medication window opening email', [
                                                'to' => $caregiver->email,
                                                'medication_id' => $medication->id,
                                                'error' => $e->getMessage(),
                                                'facility_id' => $facility?->id,
                                            ]);
                                        }
                                    }
                                }

                                // Mark this window as notified (cache for 2 hours to cover the window period)
                                Cache::put($cacheKey, true, now()->addHours(2));
                                $notifiedCount++;
                                
                                $this->info("Notified caregivers for medication ID {$medication->id} at {$scheduledTimeFormatted}");
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing medication window for medication {$medication->id}: " . $e->getMessage());
                    continue;
                }
            }
        }

        $this->info("Completed. Sent notifications for {$notifiedCount} medication windows.");
        Log::info("NotifyMedicationWindowOpening command completed. Sent notifications for {$notifiedCount} medication windows.");

        return 0;
    }
}

