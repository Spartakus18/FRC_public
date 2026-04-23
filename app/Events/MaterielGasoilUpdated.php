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

class MaterielGasoilUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Materiel $materiel;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Materiel $materiel)
    {
        $this->materiel = $materiel;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('materiels');
    }

    public function broadcastAs(): string
    {
        return 'materiel.gasoil.updated';
    }
}
