<?php

namespace App\Listeners;

use App\Events\RequestOtpEvent;
use App\Models\OtpVerification;
use App\Notifications\OtpVerificationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RequestOtpListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RequestOtpEvent $event): void
    {
        $event->user->notify(new OtpVerificationNotification($event->otpVerification));
    }
}
