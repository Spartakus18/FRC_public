<?php

namespace App\Models;

use App\Models\Parametre\Materiel;
use App\Models\Location\Conducteur;
use App\Models\Location\AideChauffeur;
use App\Models\Parametre\Destination;
use App\Models\BL\BonLivraison;
use App\Models\Produit\Categorie;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperationVehicule extends Model
{
    use HasFactory;

    // Table associée
    protected $table = 'operation_vehicules';

    // Champs remplissables
    protected $fillable = [
        'heure_depart',
        'heure_arrive',
        'vehicule_id',
        'gasoil_depart',
        'gasoil_arrive',
        'distance_km',
        'compteur_depart',
        'compteur_arrive',
        'nbr_voyage',
        'heure_machine',
        'chauffeur_id',
        'heure_chauffeur',
        'aide_chauffeur_id',
        'date_livraison',
        'date_arriver',
        'observation',
        'categorie_travail_id',
        'consommation_reelle_par_heure',
        'consommation_horaire_reference',
        'ecart_consommation_horaire',
        'statut_consommation_horaire',
        'consommation_totale',
        'consommation_destination_reference',
        'ecart_consommation_destination',
        'statut_consommation_destination'
    ];

    // Cast des champs
    protected $casts = [
        'heure_depart' => 'datetime:H:i',
        'heure_arrive' => 'datetime:H:i',
        'gasoil_depart' => 'decimal:2',
        'gasoil_arrive' => 'decimal:2',
        'compteur_depart' => 'decimal:2',
        'compteur_arrive' => 'decimal:2',
        'heure_machine' => 'decimal:2',
        'heure_chauffeur' => 'decimal:2',
        'date_livraison' => 'date',
        'date_arriver' => 'date',
        'consommation_reelle_par_heure' => 'decimal:2',
        'consommation_horaire_reference' => 'decimal:2',
        'ecart_consommation_horaire' => 'decimal:2',
        'consommation_totale' => 'decimal:2',
        'consommation_destination_reference' => 'decimal:2',
        'ecart_consommation_destination' => 'decimal:2',
    ];

    /*
     |--------------------------------------------------------------------------
     | Règles de validation
     |--------------------------------------------------------------------------
    */

    /**
     * Règles de validation pour la création d'une opération
     */
    public static function validationRules($isUpdate = false, $operationId = null)
    {
        $rules = [
            'heure_depart' => 'required|date_format:H:i',
            'heure_arrive' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $heureDepart = request('heure_depart');
                    $dateLivraison = request('date_livraison');
                    $dateArriver = request('date_arriver');

                    // On valide seulement si toutes les infos sont présentes
                    if (!$heureDepart || !$dateLivraison || !$dateArriver) {
                        return;
                    }
                    // Si les dates sont identiques
                    if ($dateLivraison === $dateArriver) {

                        $heureDepartCarbon = Carbon::createFromFormat('H:i', $heureDepart);
                        $heureArriveCarbon = Carbon::createFromFormat('H:i', $value);

                        if ($heureArriveCarbon->lessThanOrEqualTo($heureDepartCarbon)) {
                            $fail(
                                "L'heure d'arrivée doit être postérieure à l'heure de départ lorsque la livraison se fait le même jour."
                            );
                        }
                    }
                }
            ],
            'vehicule_id' => 'required|exists:materiels,id',
            'gasoil_depart' => 'required|numeric|min:0|max:10000',
            'gasoil_arrive' => [
                'nullable',
                'numeric',
                'min:0',
                'max:10000',
                function ($attribute, $value, $fail) {
                    $gasoilDepart = request('gasoil_depart');
                    if ($value && $gasoilDepart && $value > $gasoilDepart) {
                        $fail('Le gasoil d\'arrivée ne peut pas être supérieur au gasoil de départ.');
                    }
                }
            ],
            'distance_km' => 'required|numeric|min:0',
            'compteur_depart' => 'nullable|numeric|min:0',
            'compteur_arrive' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $compteurDepart = request('compteur_depart');
                    if ($value && $compteurDepart && $value < $compteurDepart) {
                        $fail('Le compteur d\'arrivée ne peut pas être inférieur au compteur de départ.');
                    }
                }
            ],
            'nbr_voyage' => 'nullable|integer|min:1|max:100',
            'heure_machine' => 'nullable|numeric|min:0|max:10000',
            'chauffeur_id' => 'required|exists:conducteurs,id',
            'heure_chauffeur' => 'nullable|numeric|min:0|max:1000',
            'aide_chauffeur_id' => 'nullable|exists:aide_chauffeurs,id',
            'categorie_travail_id' => 'nullable|integer|exists:categories,id',
            'date_livraison' => 'required|date',
            'date_arriver' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    $dateLivraison = request('date_livraison');
                    if ($value && $dateLivraison && strtotime($value) < strtotime($dateLivraison)) {
                        $fail('La date d\'arrivée ne peut pas être antérieure à la date de livraison.');
                    }
                }
            ],
            'observation' => 'nullable|string|max:1000',
            'destination_id' => 'nullable|exists:destinations,id',
        ];

        // Pour la mise à jour, rendre certains champs optionnels
        if ($isUpdate) {
            $rules = array_map(function ($rule) {
                if (is_string($rule) && strpos($rule, 'required') === 0) {
                    // Remplacer "required" par "nullable" pour la mise à jour
                    return str_replace('required', 'nullable', $rule);
                }
                return $rule;
            }, $rules);

            // Mais garder vehicule_id et chauffeur_id comme required pour la mise à jour aussi
            $rules['vehicule_id'] = 'required|exists:materiels,id';
            $rules['chauffeur_id'] = 'required|exists:conducteurs,id';
            $rules['distance_km'] = 'required|numeric|min:0';
            $rules['date_livraison'] = 'required|date';
        }

        return $rules;
    }

    /**
     * Messages de validation personnalisés
     */
    public static function validationMessages()
    {
        return [
            'heure_depart.required' => 'L\'heure de départ est obligatoire.',
            'heure_depart.date_format' => 'Le format de l\'heure de départ doit être HH:mm.',
            'heure_arrive.date_format' => 'Le format de l\'heure d\'arrivée doit être HH:mm.',
            'vehicule_id.required' => 'La sélection d\'un véhicule est obligatoire.',
            'vehicule_id.exists' => 'Le véhicule sélectionné n\'existe pas.',
            'gasoil_depart.required' => 'La quantité de gasoil au départ est obligatoire.',
            'gasoil_depart.numeric' => 'Le gasoil de départ doit être un nombre.',
            'gasoil_depart.min' => 'Le gasoil de départ ne peut pas être négatif.',
            'gasoil_arrive.numeric' => 'Le gasoil d\'arrivée doit être un nombre.',
            'gasoil_arrive.min' => 'Le gasoil d\'arrivée ne peut pas être négatif.',
            'distance_km.required' => 'La distance parcourus est requis',
            'distance_km.min' => 'La distance doit être supérieur à 0',
            'compteur_depart.numeric' => 'Le compteur de départ doit être un nombre.',
            'compteur_depart.min' => 'Le compteur de départ ne peut pas être négatif.',
            'compteur_arrive.numeric' => 'Le compteur d\'arrivée doit être un nombre.',
            'compteur_arrive.min' => 'Le compteur d\'arrivée ne peut pas être négatif.',
            'nbr_voyage.integer' => 'Le nombre de voyages doit être un nombre entier.',
            'nbr_voyage.min' => 'Le nombre de voyages doit être au moins de 1.',
            'chauffeur_id.required' => 'La sélection d\'un chauffeur est obligatoire.',
            'chauffeur_id.exists' => 'Le chauffeur sélectionné n\'existe pas.',
            'heure_chauffeur.numeric' => 'Les heures de chauffeur doivent être un nombre.',
            'heure_chauffeur.min' => 'Les heures de chauffeur ne peuvent pas être négatives.',
            'categorie_travail_id.exists' => 'Le categoris de travail n\'existe pas.',
            'aide_chauffeur_id.exists' => 'L\'aide-chauffeur sélectionné n\'existe pas.',
            'date_livraison.required' => 'La date de livraison est obligatoire.',
            'date_livraison.date' => 'La date de livraison doit être une date valide.',
            'date_arriver.date' => 'La date d\'arrivée doit être une date valide.',
            'observation.max' => 'L\'observation ne peut pas dépasser 1000 caractères.',
            'destination_id.exists' => 'La destination sélectionnée n\'existe pas.',
        ];
    }

    /**
     * Valider les données d'une opération
     */
    public static function validateOperationData(array $data, $isUpdate = false, $operationId = null)
    {
        $validator = Validator::make(
            $data,
            self::validationRules($isUpdate, $operationId),
            self::validationMessages()
        );

        // Validation des règles métier supplémentaires
        $validator->after(function ($validator) use ($data) {
            self::validateBusinessRules($validator, $data);
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validation des règles métier
     */
    private static function validateBusinessRules($validator, $data)
    {
        // Vérifier que le véhicule existe et est disponible
        if (isset($data['vehicule_id'])) {
            $vehicule = Materiel::find($data['vehicule_id']);

            if (!$vehicule) {
                $validator->errors()->add('vehicule_id', 'Le véhicule sélectionné n\'existe pas.');
                return;
            }

            // Vérifier si le véhicule est disponible
            /* if ($vehicule->status === false) {
                $validator->errors()->add('vehicule_id', 'Le véhicule sélectionné est indisponible.');
            } */

            // Vérifier que le gasoil de départ n'excède pas la capacité
            if (isset($data['gasoil_depart']) && $vehicule->capaciteL) {
                if ($data['gasoil_depart'] > $vehicule->capaciteL) {
                    $validator->errors()->add(
                        'gasoil_depart',
                        'Le gasoil de départ ne peut pas dépasser la capacité du véhicule (' . $vehicule->capaciteL . 'L).'
                    );
                }
            }
        }

        // Vérifier que le chauffeur existe
        if (isset($data['chauffeur_id'])) {
            $chauffeur = Conducteur::find($data['chauffeur_id']);
            if (!$chauffeur) {
                $validator->errors()->add('chauffeur_id', 'Le chauffeur sélectionné n\'existe pas.');
            }
        }

        // Vérifier que l'aide-chauffeur existe si fourni
        if (!empty($data['aide_chauffeur_id'])) {
            $aideChauffeur = AideChauffeur::find($data['aide_chauffeur_id']);
            if (!$aideChauffeur) {
                $validator->errors()->add('aide_chauffeur_id', 'L\'aide-chauffeur sélectionné n\'existe pas.');
            }
        }

        // Vérifier la cohérence des données de consommation
        if (isset($data['gasoil_depart']) && isset($data['gasoil_arrive'])) {
            $consommation = $data['gasoil_depart'] - $data['gasoil_arrive'];

            if ($consommation < 0) {
                $validator->errors()->add('gasoil_arrive', 'La consommation ne peut pas être négative.');
            }

            if ($consommation > 1000) {
                $validator->errors()->add(
                    'gasoil_depart',
                    'La consommation semble trop élevée (> 1000L). Veuillez vérifier les valeurs.'
                );
            }
        }

        // Vérifier la cohérence des données de compteur
        if (isset($data['compteur_depart']) && isset($data['compteur_arrive'])) {
            $distance = $data['compteur_arrive'] - $data['compteur_depart'];

            if ($distance < 0) {
                $validator->errors()->add('compteur_arrive', 'La distance parcourue ne peut pas être négative.');
            }

            if ($distance > 5000) {
                $validator->errors()->add(
                    'compteur_arrive',
                    'La distance parcourue semble trop élevée (> 5000 km). Veuillez vérifier les valeurs.'
                );
            }
        }

        // Vérifier que les dates sont dans une plage raisonnable
        if (isset($data['date_livraison'])) {
            $dateLivraison = Carbon::parse($data['date_livraison']);

            if ($dateLivraison->isFuture()) {
                $validator->errors()->add('date_livraison', 'La date de livraison ne peut pas être dans le futur.');
            }

            if ($dateLivraison->isBefore(Carbon::now()->subYears(1))) {
                $validator->errors()->add('date_livraison', 'La date de livraison ne peut pas être antérieure à un an.');
            }
        }

        if (isset($data['date_arriver'])) {
            $dateArriver = Carbon::parse($data['date_arriver']);

            if ($dateArriver->isFuture()) {
                $validator->errors()->add('date_arriver', 'La date d\'arrivée ne peut pas être dans le futur.');
            }
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Relations
     |--------------------------------------------------------------------------
    */

    public function vehicule()
    {
        return $this->belongsTo(Materiel::class, 'vehicule_id');
    }

    public function chauffeur()
    {
        return $this->belongsTo(Conducteur::class, 'chauffeur_id');
    }

    public function aideChauffeur()
    {
        return $this->belongsTo(AideChauffeur::class, 'aide_chauffeur_id');
    }

    public function consommationGasoils()
    {
        return $this->hasMany(ConsommationGasoil::class, 'operation_vehicule_id');
    }

    public function categoriesTravail()
    {
        return $this->belongsTo(Categorie::class, 'categorie_travail_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Méthode de calcul de consommation (adaptée pour OperationVehicule)
     |--------------------------------------------------------------------------
    */

    /**
     * Calcule tous les calculs de consommation pour l'opération de véhicule
     */
    public function calculerTousLesCalculs(array $requestData, ?int $destinationId = null)
    {
        // 1. MISE À JOUR DU MATÉRIEL AVEC LES VALEURS D'ARRIVÉE
        if (isset($requestData['vehicule_id']) && $requestData['vehicule_id']) {
            $vehicule = Materiel::find($requestData['vehicule_id']);
            if ($vehicule) {
                // Mettre à jour avec les valeurs d'arrivée
                if (isset($requestData['compteur_arrive']) && $requestData['compteur_arrive']) {
                    $vehicule->compteur_actuel = $requestData['compteur_arrive'];
                }

                if (isset($requestData['gasoil_arrive']) && $requestData['gasoil_arrive']) {
                    $vehicule->actuelGasoil = $requestData['gasoil_arrive'];
                }

                // Vérifier si le gasoil actuel est en dessous du seuil
                if ($vehicule->actuelGasoil <= $vehicule->seuil) {
                    $admin = User::where('role_id', 1)->first();

                    if ($admin) {
                        Notification::send($admin, new GasoilSeuilAtteint($vehicule));
                    }
                }

                $vehicule->save();
            }
        }

        // 2. RÉCUPÉRER LA DESTINATION SI SPÉCIFIÉE
        $consommationDestinationReference = 0;
        if ($destinationId) {
            $destination = Destination::find($destinationId);
            if ($destination) {
                $consommationDestinationReference = $destination->consommation_reference;
            }
        }

        // 3. CALCULS DE CONSOMMATION
        $gasoilDepart = $requestData['gasoil_depart'] ?? 0;
        $gasoilArrive = $requestData['gasoil_arrive'] ?? 0;
        $compteurDepart = $requestData['compteur_depart'] ?? 0;
        $compteurArrive = $requestData['compteur_arrive'] ?? 0;

        // Calcul de la consommation totale
        $consommationTotale = max(0, $gasoilDepart - $gasoilArrive);

        // Calcul des heures de travail
        $heuresTravail = 0;
        $consommationReelleParHeure = 0;

        if (isset($requestData['heure_depart']) && isset($requestData['heure_arrive'])) {
            $heureDepart = Carbon::parse($requestData['heure_depart']);
            $heureArrive = Carbon::parse($requestData['heure_arrive']);
            $heuresTravail = $heureDepart->diffInHours($heureArrive);

            if ($heuresTravail > 0) {
                $consommationReelleParHeure = $consommationTotale / $heuresTravail;
            }
        }

        // Récupérer les informations du véhicule
        $consommationHoraireReference = 0;
        if (isset($requestData['vehicule_id']) && $requestData['vehicule_id']) {
            $vehicule = Materiel::find($requestData['vehicule_id']);
            $consommationHoraireReference = $vehicule ? $vehicule->consommation_horaire : 0;
        }

        // Calcul écart consommation horaire
        $ecartConsommationHoraire = 0;
        $statutConsommationHoraire = 'normal';

        if ($consommationHoraireReference > 0) {
            $ecartConsommationHoraire = $consommationReelleParHeure - $consommationHoraireReference;
            $pourcentageEcartHoraire = ($ecartConsommationHoraire / $consommationHoraireReference) * 100;

            if ($pourcentageEcartHoraire > 15) {
                $statutConsommationHoraire = 'trop_elevee';
            } elseif ($pourcentageEcartHoraire < -15) {
                $statutConsommationHoraire = 'trop_basse';
            } else {
                $statutConsommationHoraire = 'normale';
            }
        }

        // Calcul écart consommation par destination
        $ecartConsommationDestination = 0;
        $statutConsommationDestination = 'normal';

        if ($consommationDestinationReference > 0) {
            $ecartConsommationDestination = $consommationTotale - $consommationDestinationReference;
            $pourcentageEcartDestination = ($ecartConsommationDestination / $consommationDestinationReference) * 100;

            if ($pourcentageEcartDestination > 15) {
                $statutConsommationDestination = 'trop_elevee';
            } elseif ($pourcentageEcartDestination < -15) {
                $statutConsommationDestination = 'trop_basse';
            } else {
                $statutConsommationDestination = 'normale';
            }
        }

        // Calcul de l'heure machine
        $heureMachine = 0;
        if ($compteurDepart && $compteurArrive) {
            $heureMachine = $compteurArrive - $compteurDepart;
        }

        // 4. METTRE À JOUR L'OPERATION_VEHICULE
        $this->update([
            'heure_machine' => $heureMachine,
            'consommation_totale' => $consommationTotale,
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'consommation_horaire_reference' => $consommationHoraireReference,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'consommation_destination_reference' => $consommationDestinationReference,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
        ]);

        // 5. AJOUTER À LA CONSOMMATION TOTALE DU VÉHICULE
        if (isset($vehicule) && $consommationTotale > 0) {
            $vehicule->gasoil_consommation += $consommationTotale;
            $vehicule->save();
        }

        // 6. ENREGISTRER DANS CONSOMMATIONGASOIL
        $consommationExistante = ConsommationGasoil::where('operation_vehicule_id', $this->id)->first();

        $distanceKm = $requestData['distance_km'] ?? $heureMachine;

        $consommationData = [
            'vehicule_id' => $requestData['vehicule_id'] ?? null,
            'quantite' => $consommationTotale,
            'distance_km' => $distanceKm,
            'date_consommation' => $requestData['date_livraison'] ?? now()->format('Y-m-d'),
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'consommation_horaire_reference' => $consommationHoraireReference,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'consommation_totale' => $consommationTotale,
            'consommation_destination_reference' => $consommationDestinationReference,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
            'destination_id' => $destinationId,
            'operation_vehicule_id' => $this->id,
            'bon_livraison_id' => null,
            'transfert_produit_id' => null,
            'production_materiel_id' => null,
        ];

        if ($consommationExistante) {
            $consommationExistante->update($consommationData);
        } else {
            ConsommationGasoil::create($consommationData);
        }

        return [
            'consommation_totale' => $consommationTotale,
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
            'heure_machine' => $heureMachine,
            'heures_travail' => $heuresTravail,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
    */

    public function scopeEnCours($query)
    {
        return $query->whereNull('heure_arrive');
    }

    public function scopeTerminees($query)
    {
        return $query->whereNotNull('heure_arrive');
    }

    public function scopeParDate($query, $date)
    {
        return $query->whereDate('date_livraison', $date);
    }

    public function scopeParVehicule($query, $vehiculeId)
    {
        return $query->where('vehicule_id', $vehiculeId);
    }

    public function scopeParChauffeur($query, $chauffeurId)
    {
        return $query->where('chauffeur_id', $chauffeurId);
    }

    /*
     |--------------------------------------------------------------------------
     | Méthodes utilitaires
     |--------------------------------------------------------------------------
    */

    public function calculerDuree()
    {
        if ($this->heure_depart && $this->heure_arrive) {
            $depart = Carbon::parse($this->heure_depart);
            $arrive = Carbon::parse($this->heure_arrive);
            return $depart->diffInHours($arrive);
        }
        return null;
    }

    public function calculerConsommationGasoil()
    {
        if ($this->gasoil_depart && $this->gasoil_arrive) {
            return max(0, $this->gasoil_depart - $this->gasoil_arrive);
        }
        return null;
    }

    public function calculerDistance()
    {
        if ($this->compteur_depart && $this->compteur_arrive) {
            return $this->compteur_arrive - $this->compteur_depart;
        }
        return null;
    }

    public function estEnCours()
    {
        return is_null($this->heure_arrive);
    }

    public function terminer(array $donnees = [])
    {
        $this->update(array_merge([
            'heure_arrive' => now()->format('H:i'),
            'date_arriver' => now()->format('Y-m-d'),
        ], $donnees));
    }

    /*
     |--------------------------------------------------------------------------
     | Événements du modèle
     |--------------------------------------------------------------------------
    */

    /* protected static function booted()
    {
        static::creating(function ($operation) {
            if ($operation->consommation_reelle_par_heure && $operation->consommation_horaire_reference) {
                $operation->ecart_consommation_horaire =
                    $operation->consommation_reelle_par_heure - $operation->consommation_horaire_reference;

                $operation->statut_consommation_horaire = $operation->determinerStatutConsommation(
                    $operation->ecart_consommation_horaire
                );
            }

            if ($operation->consommation_totale && $operation->consommation_destination_reference) {
                $operation->ecart_consommation_destination =
                    $operation->consommation_totale - $operation->consommation_destination_reference;

                $operation->statut_consommation_destination = $operation->determinerStatutConsommation(
                    $operation->ecart_consommation_destination
                );
            }
        });

        static::updating(function ($operation) {
            if ($operation->isDirty(['consommation_reelle_par_heure', 'consommation_horaire_reference'])) {
                if ($operation->consommation_reelle_par_heure && $operation->consommation_horaire_reference) {
                    $operation->ecart_consommation_horaire =
                        $operation->consommation_reelle_par_heure - $operation->consommation_horaire_reference;
                    $operation->statut_consommation_horaire = $operation->determinerStatutConsommation(
                        $operation->ecart_consommation_horaire
                    );
                }
            }

            if ($operation->isDirty(['consommation_totale', 'consommation_destination_reference'])) {
                if ($operation->consommation_totale && $operation->consommation_destination_reference) {
                    $operation->ecart_consommation_destination =
                        $operation->consommation_totale - $operation->consommation_destination_reference;
                    $operation->statut_consommation_destination = $operation->determinerStatutConsommation(
                        $operation->ecart_consommation_destination
                    );
                }
            }
        });
    } */

    /*
     |--------------------------------------------------------------------------
     | Méthodes privées
     |--------------------------------------------------------------------------
    */

    private function determinerStatutConsommation($ecart)
    {
        if ($ecart > 0) {
            return 'supérieur';
        } elseif ($ecart < 0) {
            return 'inférieur';
        } else {
            return 'normal';
        }
    }
}
