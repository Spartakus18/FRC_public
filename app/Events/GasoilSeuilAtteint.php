<?php

namespace App\Events;

use App\Models\Parametre\Materiel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GasoilSeuilAtteint
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $materiel;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Materiel $materiel)
    {
        $this->$materiel = $materiel;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // return new PrivateChannel('channel-name');
        return new Channel('gasoil-seuil');
    }

    public function broadcastWith()
    {
        return [
            'message' => "Le matériel {$this->materiel->nom_materiel} a atteint son seuil de gasoil!",
            'materiel' => $this->materiel
        ];
    }
}
