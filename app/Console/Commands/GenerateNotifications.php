<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use App\Models\Appointment;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\User;
use Carbon\Carbon;

class GenerateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate notifications for upcoming appointments and medication administrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating notifications...');

        // Generate appointment notifications
        $appointmentsCreated = $this->generateAppointmentNotifications();
        $this->info("Created {$appointmentsCreated} appointment notifications");

        // Generate medication notifications
        $medicationsCreated = $this->generateMedicationNotifications();
        $this->info("Created {$medicationsCreated} medication notifications");

        $this->info('Notification generation complete!');

        return Command::SUCCESS;
    }

    /**
     * Generate notifications for upcoming appointments
     */
    private function generateAppointmentNotifications(): int
    {
        $count = 0;
        $now = now();

        // Get appointments in the next 7 days
        $appointments = Appointment::with(['resident.assignments.caregiver'])
            ->where('status', 'scheduled')
            ->whereBetween('appointment_date', [$now->toDateString(), $now->copy()->addDays(7)->toDateString()])
            ->get();

        foreach ($appointments as $appointment) {
            // Get assigned caregivers for this resident
            $caregivers = $appointment->resident?->assignments
                ->where('is_active', true)
                ->pluck('caregiver')
                ->filter();
            
            // If no caregivers, notify all admins/managers
            if ($caregivers->isEmpty()) {
                $caregivers = User::whereIn('role', ['administrator', 'admin', 'manager'])
                    ->where('is_active', true)
                    ->get();
            }

            foreach ($caregivers as $caregiver) {
                // Check if notification already exists
                $exists = Notification::where('user_id', $caregiver->id)
                    ->where('type', 'appointment_upcoming')
                    ->whereJsonContains('metadata->appointment_id', $appointment->id)
                    ->where('created_at', '>=', $now->subDay())
                    ->exists();

                if (!$exists) {
                    $daysUntil = Carbon::parse($appointment->appointment_date)->diffInDays($now);
                    $title = $daysUntil == 0 ? 'Appointment Today' : ($daysUntil == 1 ? 'Appointment Tomorrow' : "Appointment in {$daysUntil} days");
                    
                    $appointmentType = $appointment->appointmentType?->name ?? 'General';
                    $time = $appointment->appointment_time ? Carbon::parse($appointment->appointment_time)->format('g:i A') : 'TBD';
                    
                    Notification::create([
                        'user_id' => $caregiver->id,
                        'type' => 'appointment_upcoming',
                        'title' => $title,
                        'message' => "{$appointment->resident->name} has a {$appointmentType} appointment on " . 
                                   Carbon::parse($appointment->appointment_date)->format('M d, Y') . 
                                   " at {$time}",
                        'icon' => 'calendar',
                        'icon_color' => 'text-green-600',
                        'action_url' => '/appointments',
                        'metadata' => [
                            'appointment_id' => $appointment->id,
                            'resident_id' => $appointment->resident_id,
                            'days_until' => $daysUntil,
                        ],
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Generate notifications for upcoming medication administrations
     */
    private function generateMedicationNotifications(): int
    {
        $count = 0;
        $now = now();

        // Get active medications
        $medications = Medication::with(['resident.assignments.caregiver', 'drug'])
            ->where('is_active', true)
            ->where(function($query) use ($now) {
                $query->where(function($q) use ($now) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
                })
                ->where(function($q) use ($now) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
                });
            })
            ->get();

        foreach ($medications as $medication) {
            // Get assigned caregivers for this resident
            $caregivers = $medication->resident?->assignments
                ->where('is_active', true)
                ->pluck('caregiver')
                ->filter();
            
            // If no caregivers, notify all admins/managers
            if ($caregivers->isEmpty()) {
                $caregivers = User::whereIn('role', ['administrator', 'admin', 'manager'])
                    ->where('is_active', true)
                    ->get();
            }

            foreach ($caregivers as $caregiver) {
                // Get all scheduled administration times for today
                $adminTimes = $this->getScheduledAdminTimes($medication);

                foreach ($adminTimes as $adminTime) {
                    // Check if already administered today
                    $alreadyAdministered = MedicationAdministration::where('medication_id', $medication->id)
                        ->whereDate('administered_at', $now->toDateString())
                        ->where('status', 'completed')
                        ->exists();

                    if (!$alreadyAdministered) {
                        // Check if notification already exists for this time today
                        $exists = Notification::where('user_id', $caregiver->id)
                            ->where('type', 'medication_due')
                            ->whereJsonContains('metadata->medication_id', $medication->id)
                            ->whereDate('created_at', $now->toDateString())
                            ->whereJsonContains('metadata->scheduled_time', $adminTime)
                            ->exists();

                        if (!$exists) {
                            $drugName = $medication->drug?->name ?? $medication->name;
                            
                            Notification::create([
                                'user_id' => $caregiver->id,
                                'type' => 'medication_due',
                                'title' => 'Medication Due',
                                'message' => "Give {$drugName} to {$medication->resident->name} at {$adminTime}",
                                'icon' => 'pill',
                                'icon_color' => 'text-red-600',
                                'action_url' => '/medications',
                                'metadata' => [
                                    'medication_id' => $medication->id,
                                    'resident_id' => $medication->resident_id,
                                    'scheduled_time' => $adminTime,
                                ],
                            ]);
                            $count++;
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Get scheduled administration times for a medication
     */
    private function getScheduledAdminTimes($medication): array
    {
        $times = [];
        
        // First check if medication has explicit time fields (time_1, time_2, etc.)
        if ($medication->time_1) {
            $times[] = Carbon::parse($medication->time_1)->format('g:i A');
        }
        if ($medication->time_2) {
            $times[] = Carbon::parse($medication->time_2)->format('g:i A');
        }
        if ($medication->time_3) {
            $times[] = Carbon::parse($medication->time_3)->format('g:i A');
        }
        if ($medication->time_4) {
            $times[] = Carbon::parse($medication->time_4)->format('g:i A');
        }

        // If no explicit times, parse the instruction
        if (empty($times)) {
            $instruction = $medication->instructions ?? '';
            
            // Common patterns
            if (stripos($instruction, 'three times') !== false || stripos($instruction, 'three times daily') !== false) {
                // 8 AM, 2 PM, 8 PM
                $times = ['8:00 AM', '2:00 PM', '8:00 PM'];
            } elseif (stripos($instruction, 'two times') !== false || stripos($instruction, 'twice daily') !== false) {
                // 9 AM, 9 PM
                $times = ['9:00 AM', '9:00 PM'];
            } elseif (stripos($instruction, 'once daily') !== false || stripos($instruction, 'once a day') !== false) {
                // 9 AM
                $times = ['9:00 AM'];
            } elseif (stripos($instruction, 'four times') !== false || stripos($instruction, 'every 6 hours') !== false) {
                // 6 AM, 12 PM, 6 PM, 12 AM
                $times = ['6:00 AM', '12:00 PM', '6:00 PM', '12:00 AM'];
            } elseif (stripos($instruction, 'every 8 hours') !== false || stripos($instruction, 'three times daily') !== false) {
                // 8 AM, 4 PM, 12 AM
                $times = ['8:00 AM', '4:00 PM', '12:00 AM'];
            } else {
                // Default: once in the morning
                $times = ['9:00 AM'];
            }
        }

        return $times;
    }
}
