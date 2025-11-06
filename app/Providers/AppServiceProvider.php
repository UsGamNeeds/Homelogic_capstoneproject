<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Models\Appointment;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Assessment;
use App\Observers\AppointmentObserver;
use App\Observers\MedicationObserver;
use App\Observers\MedicationAdministrationObserver;
use App\Observers\AssessmentObserver;
use App\Listeners\LogAuthentication;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
        Medication::observe(MedicationObserver::class);
        MedicationAdministration::observe(MedicationAdministrationObserver::class);
        Assessment::observe(AssessmentObserver::class);
        
        // Register authentication event listeners
        Event::listen(Login::class, LogAuthentication::class);
        Event::listen(Logout::class, LogAuthentication::class);
    }
}
