<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogAuthentication
{
    /**
     * Handle the event.
     */
    public function handle(Login|Logout $event): void
    {
        if ($event instanceof Login) {
            ActivityLogService::login($event->user, [
                'guard' => $event->guard,
            ]);
        } elseif ($event instanceof Logout) {
            ActivityLogService::logout($event->user, [
                'guard' => $event->guard,
            ]);
        }
    }
}


