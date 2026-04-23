<?php

namespace App\Listeners;

use App\Events\GasoilSeuilAtteint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendGasoilNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\GasoilSeuilAtteint  $event
     * @return void
     */
    public function handle(GasoilSeuilAtteint $event)
    {
        //
    }
}
