<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CompteurJournee;
use App\Models\GasoilJournee;
use App\Models\Parametre\Materiel;
use Illuminate\Support\Facades\DB;

class Journee extends Model
{
    use HasFactory;

    protected $table = 'journees';

    protected $fillable = [
        'user_id_start',
        'user_id_end',
        'isBegin',
        'isEnd',
        'date',
        'notes',
    ];

    protected $casts = [
        'isBegin' => 'boolean',
        'isEnd' => 'boolean',
        'date' => 'date',
    ];

    public function gasoilJournees()
    {
        return $this->hasMany(GasoilJournee::class);
    }

    public function compteurJournees()
    {
        return $this->hasMany(CompteurJournee::class);
    }

    /**
     * Relation avec l'utilisateur qui a démarré la journée
     */
    public function userStart()
    {
        return $this->belongsTo(User::class, 'user_id_start');
    }

    /**
     * Relation avec l'utilisateur qui a terminé la journée
     */
    public function userEnd()
    {
        return $this->belongsTo(User::class, 'user_id_end');
    }

    /**
     * Vérifie si une journée existe pour aujourd'hui
     *
     * @return bool
     */
    public static function journeeExisteAujourdhui(): bool
    {
        return self::whereDate('date', now()->toDateString())->exists();
    }

    /**
     * Récupère la journée d'aujourd'hui
     *
     * @return Journee|null
     */
    public static function journeeAujourdhui()
    {
        return self::whereDate('date', now()->toDateString())->first();
    }

    /**
     * Vérifie si la journée d'aujourd'hui est démarrée
     *
     * @return bool
     */
    public static function estDemarreeAujourdhui(): bool
    {
        $journee = self::journeeAujourdhui();
        return $journee && $journee->isBegin;
    }

    /**
     * Vérifie si la journée d'aujourd'hui est terminée
     *
     * @return bool
     */
    public static function estTermineeAujourdhui(): bool
    {
        $journee = self::journeeAujourdhui();
        return $journee && $journee->isEnd;
    }

    /**
     * Démarre une nouvelle journée
     *
     * @param int $userId
     * @param string|null $notes
     * @return Journee
     */
    public static function demarrerJournee(int $userId, ?string $notes = null): Journee
    {
        // Vérifier si une journée existe déjà pour aujourd'hui
        if (self::journeeExisteAujourdhui()) {
            throw new \Exception('Une journée a déjà été créée pour aujourd\'hui.');
        }

        return DB::transaction(function () use ($userId, $notes) {

            $journee = self::create([
                'user_id_start' => $userId,
                'isBegin'       => true,
                'isEnd'         => false,
                'date'          => now()->toDateString(),
                'notes'         => $notes,
            ]);

            // SNAPSHOT GASOIL MATIN
            $materiels = Materiel::all();

            foreach ($materiels as $materiel) {
                GasoilJournee::create([
                    'journee_id'   => $journee->id,
                    'materiel_id'  => $materiel->id,
                    'gasoil_id'    => null,
                    'gasoil_matin' => $materiel->actuelGasoil ?? 0,
                ]);

                CompteurJournee::create([
                    'journee_id'      => $journee->id,
                    'materiel_id'     => $materiel->id,
                    'compteur_matin'  => $materiel->compteur_actuel ?? 0,
                ]);
            }

            return $journee;
        });
    }

    /**
     * Termine la journée d'aujourd'hui
     *
     * @param int $userId
     * @param string|null $notes
     * @return bool
     */
    public static function terminerJournee(int $userId, ?string $notes = null): bool
    {
        $journee = self::journeeAujourdhui();

        if (!$journee) {
            throw new \Exception('Aucune journée trouvée pour aujourd\'hui.');
        }

        if ($journee->isEnd) {
            throw new \Exception('La journée est déjà terminée.');
        }

        return $journee->update([
            'user_id_end' => $userId,
            'isEnd' => true,
            'notes' => $journee->notes ? $journee->notes . "\n" . $notes : $notes,
        ]);
    }

    /**
     * Récupère les journées dans une période donnée
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getJourneesParPeriode(string $dateDebut, string $dateFin)
    {
        return self::with(['userStart:id,name,email', 'userEnd:id,name,email'])
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Vérifie si on peut démarrer une journée aujourd'hui
     *
     * @return array
     */
    public static function peutDemarrerJournee(): array
    {
        $journee = self::journeeAujourdhui();

        if (!$journee) {
            return ['peut_demarrer' => true, 'message' => null];
        }

        if ($journee->isEnd) {
            return ['peut_demarrer' => false, 'message' => 'La journée est déjà terminée.'];
        }

        return ['peut_demarrer' => false, 'message' => 'La journée est déjà démarrée.'];
    }

    /**
     * Vérifie si on peut terminer une journée aujourd'hui
     *
     * @return array
     */
    public static function peutTerminerJournee(): array
    {
        $journee = self::journeeAujourdhui();

        if (!$journee) {
            return ['peut_terminer' => false, 'message' => 'Aucune journée démarrée trouvée pour aujourd\'hui.'];
        }

        if ($journee->isEnd) {
            return ['peut_terminer' => false, 'message' => 'La journée est déjà terminée.'];
        }

        return ['peut_terminer' => true, 'message' => null];
    }


    /**
     * Réactive la journée d'aujourd'hui (met isEnd à false)
     *
     * @return bool
     * @throws \Exception
     */
    public static function reactiverJourneeAujourdhui(): bool
    {
        $journee = self::journeeAujourdhui();

        if (!$journee) {
            throw new \Exception('Aucune journée trouvée pour aujourd\'hui.');
        }

        if (!$journee->isEnd) {
            throw new \Exception('La journée n\'est pas terminée, elle ne peut pas être réactivée.');
        }

        return $journee->update([
            'isEnd' => false,
            'user_id_end' => null,
        ]);
    }

    /**
     * Vérifie si on peut réactiver la journée d'aujourd'hui
     *
     * @return array
     */
    public static function peutReactiverJournee(): array
    {
        $journee = self::journeeAujourdhui();

        if (!$journee) {
            return ['peut_reactiver' => false, 'message' => 'Aucune journée trouvée pour aujourd\'hui.'];
        }

        if (!$journee->isEnd) {
            return ['peut_reactiver' => false, 'message' => 'La journée n\'est pas terminée.'];
        }

        return ['peut_reactiver' => true, 'message' => null];
    }
}
