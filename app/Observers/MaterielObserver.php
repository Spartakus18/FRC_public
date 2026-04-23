<?php

namespace App\Observers;

use App\Events\MaterielGasoilUpdated;
use App\Models\CompteurJournee;
use App\Models\Journee;
use App\Models\Parametre\Materiel;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MaterielObserver
{
    /**
     * Handle the Materiel "created" event.
     *
     * @param  \App\Models\Materiel  $materiel
     * @return void
     */
    public function created(Materiel $materiel)
    {
        //
    }

    /* Empêche les controller de faire des operation sur le gasoil d'un matériel quand la journée n'as pas encore commencer */
    public function updating(Materiel $materiel)
    {
        // Si le champ 'actuelGasoil' est modifié et qu'on est autoriser
        if ($materiel->isDirty('actuelGasoil') && !Materiel::isSkippingJourneeCheck()) {
            // Récupérer la journée d'aujourd'hui
            $journee = Journee::journeeAujourdhui();

            // Vérifier si la journée existe et a démarré
            if (! $journee || ! $journee->isBegin) {
                throw new \Exception('Impossible de modifier le gasoil d’un matériel avant le démarrage de la journée.');
            }

            // bloquer après la fin de la journée
            if ($journee->isEnd) {
                throw new \Exception('La journée est déjà terminée, modification impossible.');
            }
        }
    }

    public function updated(Materiel $materiel)
    {
        if ($materiel->wasChanged('compteur_actuel')) {
            CompteurJournee::markHasCompteur($materiel->id);
        }

        if ($materiel->wasChanged('actuelGasoil')) {

            $ancien = $materiel->getOriginal('actuelGasoil');
            $nouveau = $materiel->actuelGasoil;

            Log::info('Materiel gasoil updated', [
                'materiel_id' => $materiel->id,
                'ancien' => $ancien,
                'nouveau' => $nouveau,
            ]);

            /* -------------------------------------------------
            | CAS 1 : passage en dessous du seuil (ALERTE)
            ------------------------------------------------- */
            if ($nouveau <= $materiel->seuil) {

                if (! $materiel->seuil_notif) {

                    $users = User::whereHas('role', function ($query) {
                        $query->whereIn('nom_role', ['Administrateur', 'Logistique']);
                    })->get();


                    Notification::send(
                        $users,
                        new GasoilSeuilAtteint($materiel)
                    );

                    // Marquer alerte envoyée
                    $materiel->updateQuietly([
                        'seuil_notif' => true,
                    ]);
                }
            }

            /* -------------------------------------------------
            | CAS 2 : retour à la normale (RESET)
            ------------------------------------------------- */
            if ($nouveau > $materiel->seuil && $materiel->seuil_notif) {

                $materiel->updateQuietly([
                    'seuil_notif' => false,
                ]);

                Log::info('Materiel gasoil revenu à la normale', [
                    'materiel_id' => $materiel->id,
                    'gasoil' => $nouveau,
                ]);
            }

            event(new MaterielGasoilUpdated($materiel));
        }
    }

    /**
     * Handle the Materiel "deleted" event.
     *
     * @param  \App\Models\Materiel  $materiel
     * @return void
     */
    public function deleted(Materiel $materiel)
    {
        //
    }

    /**
     * Handle the Materiel "restored" event.
     *
     * @param  \App\Models\Materiel  $materiel
     * @return void
     */
    public function restored(Materiel $materiel)
    {
        //
    }

    /**
     * Handle the Materiel "force deleted" event.
     *
     * @param  \App\Models\Materiel  $materiel
     * @return void
     */
    public function forceDeleted(Materiel $materiel)
    {
        //
    }
}
