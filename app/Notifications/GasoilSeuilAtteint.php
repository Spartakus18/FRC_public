<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\Parametre\Materiel;

class GasoilSeuilAtteint extends Notification
{
    use Queueable;

    protected Materiel $materiel;

    public function __construct(Materiel $materiel)
    {
        $this->materiel = $materiel;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'materiel_id'   => $this->materiel->id,
            'materiel_name' => $this->materiel->nom_materiel,
            'message'       => "Le matériel « {$this->materiel->nom_materiel} » a atteint son seuil de gasoil.",
            'seuil'         => $this->materiel->seuil,
            'actuelGasoil'  => $this->materiel->actuelGasoil,
            'triggered_at'  => now()->toDateTimeString(),
            'type'          => 'gasoil_seuil',
        ];
    }
}
