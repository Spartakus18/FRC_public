<?php

namespace App\Http\Controllers\Produit;

use App\Exports\BonTransfertExport;
use App\Exports\TransfertExport;
use App\Http\Controllers\Controller;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Entrer;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\AjustementStock\Sortie;
use App\Models\AjustementStock\Stock;
use App\Models\Location\AideChauffeur;
use App\Models\Location\Conducteur;
use App\Models\Parametre\Materiel;
use App\Models\Produit\TransfertProduit;
use App\Models\Produit\BonTransfert;
use App\Models\ConsommationGasoil;
use App\Models\Parametre\Destination;
use App\Models\User;
use App\Notifications\GasoilSeuilAtteint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Parametre\Unite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransfertController extends Controller
{
    public function index(Request $request)
    {
        $query = TransfertProduit::with(['materiel', 'chauffeur', 'aideChauffeur', 'lieuStockageDepart', 'lieuStockageArrive', 'produit', 'bonTransfert', 'unite']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('produit', function ($q2) use ($search) {
                    $q2->where('nom_article', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('chauffeur', function ($q3) use ($search) {
                        $q3->where('nom_conducteur', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('materiel', function ($q4) use ($search) {
                        $q4->where('nom_materiel', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('bonTransfert', function ($q5) use ($search) {
                        $q5->where('numero_bon', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        if ($request->has('produit_id') && !empty($request->produit_id)) {
            $query->where('produit_id', $request->produit_id);
        }

        if ($request->has('lieu_depart_id') && !empty($request->lieu_depart_id)) {
            $query->where('lieu_stockage_depart_id', $request->lieu_depart_id);
        }

        if ($request->has('lieu_arrive_id') && !empty($request->lieu_arrive_id)) {
            $query->where('lieu_stockage_arrive_id', $request->lieu_arrive_id);
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->per_page ?? 10;
        $transferts = $query->paginate($perPage);

        return response()->json($transferts);
    }

    public function create()
    {
        $materiel = Materiel::all();
        $chauffeur = Conducteur::all();
        $aideChauffeur = AideChauffeur::all();
        $produit = ArticleDepot::with(['uniteLivraison'])->get();
        $lieuDepart = Lieu_stockage::all();
        $lieuArrive = Lieu_stockage::all();

        $bonsTransfert = BonTransfert::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'unite', 'transferts'])
            ->get()
            ->filter(function ($bon) {
                $quantiteTransferee = $bon->transferts->where('isDelivred', 1)->sum('quantite');
                $quantiteRestante = $bon->quantite - $quantiteTransferee;
                return $quantiteRestante > 0;
            })
            ->values();

        return response()->json([
            'materiel' => $materiel,
            'chauffeur' => $chauffeur,
            'AideChauffeur' => $aideChauffeur,
            'produit' => $produit,
            'lieuDepart' => $lieuDepart,
            'lieuArrive' => $lieuArrive,
            'bonsTransfert' => $bonsTransfert,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'bon_transfert_id' => 'required|exists:bon_transferts,id',
            'date' => 'required|date',
            'heure_depart' => 'required',
            'materiel_id' => 'required|exists:materiels,id',
            'gasoil_depart' => 'required|numeric|min:0.1',
            'compteur_depart' => 'nullable|numeric|min:0.1',
            'chauffeur_id' => 'required|exists:conducteurs,id',
            'aideChauffeur_id' => 'nullable|exists:aide_chauffeurs,id',
            'quantite' => 'required|numeric|min:0.1',
            'remarque' => 'nullable|string',
        ]);

        $bonTransfert = BonTransfert::findOrFail($request->bon_transfert_id);

        $quantiteDejaTransferee = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
            ->where('isDelivred', 1)
            ->sum('quantite');

        $quantiteRestante = $bonTransfert->quantite - $quantiteDejaTransferee;

        if ($request->quantite > $quantiteRestante) {
            return response()->json([
                'message' => 'La quantité demandée (' . $request->quantite .
                    ') dépasse la quantité restante du bon (' . $quantiteRestante . ')'
            ], 422);
        }

        $article = ArticleDepot::with('uniteLivraison')->find($bonTransfert->produit_id);

        if ($article && $article->hasQuantiteMaxLivraison()) {
            $quantiteEnUniteLivraison = $request->quantite;
            $uniteBon = $bonTransfert->unite;
            $uniteLivraison = $article->uniteLivraison;

            if ($uniteBon && $uniteLivraison && $uniteBon->nom_unite !== $uniteLivraison->nom_unite) {
                if ($uniteBon->nom_unite === 'Fu' && $uniteLivraison->nom_unite === 'm³') {
                    $quantiteEnUniteLivraison = $request->quantite * 0.2;
                } elseif ($uniteBon->nom_unite === 'm³' && $uniteLivraison->nom_unite === 'Fu') {
                    $quantiteEnUniteLivraison = $request->quantite / 0.2;
                }
            }

            if ($quantiteEnUniteLivraison > $article->quantite_max_livraison) {
                return response()->json([
                    'message' => 'La quantité de ce transfert dépasse la quantité maximale de livraison de ' .
                        $article->quantite_max_livraison . ' ' .
                        $article->uniteLivraison->nom_unite
                ], 422);
            }
        }

        $stockDepart = Stock::where([
            'article_id' => $bonTransfert->produit_id,
            'lieu_stockage_id' => $bonTransfert->lieu_stockage_depart_id
        ])->first();

        if (!$stockDepart || $stockDepart->quantite < $request->quantite) {
            return response()->json([
                'message' => 'Stock insuffisant dans le lieu de départ pour effectuer ce transfert'
            ], 422);
        }

        $transfert = TransfertProduit::create([
            'date' => $request->date,
            'heure_depart' => $request->heure_depart,
            'heure_arrivee' => null,
            'materiel_id' => $request->materiel_id,
            'gasoil_depart' => $request->gasoil_depart,
            'gasoil_arrive' => null,
            'compteur_depart' => $request->compteur_depart,
            'compteur_arrive' => null,
            'distance' => null,
            'chauffeur_id' => $request->chauffeur_id,
            'aideChauffeur_id' => $request->aideChauffeur_id,
            'remarque' => $request->remarque,
            'produit_id' => $bonTransfert->produit_id,
            'unite_id' => $bonTransfert->unite_id,
            'lieu_stockage_depart_id' => $bonTransfert->lieu_stockage_depart_id,
            'lieu_stockage_arrive_id' => $bonTransfert->lieu_stockage_arrive_id,
            'quantite' => $request->quantite,
            'bon_transfert_id' => $request->bon_transfert_id,
            'isDelivred' => 0
        ]);

        return response()->json([
            'message' => 'Transfert créé avec succès (en attente de validation)',
            'transfert' => $transfert,
            'progression_bon' => [
                'quantite_totale' => $bonTransfert->quantite,
                'quantite_transferee' => $quantiteDejaTransferee,
                'quantite_restante' => $quantiteRestante,
            ],
        ], 201);
    }

    public function validerArrivee(Request $request, $id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'heure_arrivee' => 'required',
            'gasoil_arrive' => 'required|numeric|min:0',
            'compteur_arrive' => 'nullable|numeric|min:0.1',
            'distance' => 'nullable|numeric|min:0',
            'quantite' => 'required|numeric|min:0.1',
        ]);

        // Tout le reste est exécuté dans une transaction
        return DB::transaction(function () use ($request, $id, $user) {
            $transfert = TransfertProduit::with(['bonTransfert', 'produit', 'unite', 'lieuStockageDepart', 'lieuStockageArrive', 'materiel'])->find($id);

            if (!$transfert) {
                // Il est préférable de lancer une exception pour que la transaction rollback
                throw new \Exception('Transfert non trouvé', 404);
            }

            if ($transfert->isDelivred) {
                throw new \Exception('Ce transfert a déjà été validé', 422);
            }

            $bonTransfert = $transfert->bonTransfert;

            $quantiteDejaTransferee = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
                ->where('isDelivred', 1)
                ->sum('quantite');

            $quantiteRestante = $bonTransfert->quantite - $quantiteDejaTransferee;

            if ($request->quantite > $quantiteRestante) {
                throw new \Exception(
                    'La quantité demandée (' . $request->quantite .
                        ') dépasse la quantité restante du bon (' . $quantiteRestante . ')',
                    422
                );
            }

            $transfert->update([
                'heure_arrivee' => $request->heure_arrivee,
                'gasoil_arrive' => $request->gasoil_arrive,
                'compteur_arrive' => $request->compteur_arrive,
                'distance' => $request->distance,
                'quantite' => $request->quantite,
                'isDelivred' => 1
            ]);

            // Cette méthode contient de multiples opérations (stocks, entrées, sorties, mise à jour du matériel, etc.)
            $this->appliquerValidationTransfert($transfert, $user);

            // Si tout s'est bien passé, la transaction est automatiquement commitée
            return response()->json([
                'message' => 'Transfert validé avec succès',
                'transfert' => $transfert,
                'progression_bon' => [
                    'quantite_totale' => $bonTransfert->quantite,
                    'quantite_transferee' => $quantiteDejaTransferee + $request->quantite,
                    'quantite_restante' => $bonTransfert->quantite - ($quantiteDejaTransferee + $request->quantite),
                    'est_complet' => ($quantiteDejaTransferee + $request->quantite) >= $bonTransfert->quantite,
                ],
            ], 200);
        });
    }

    private function appliquerValidationTransfert(TransfertProduit $transfert, $user)
    {
        $bonTransfert = $transfert->bonTransfert;

        $article = ArticleDepot::with('categorie')->where('id', $transfert->produit_id)->firstOrFail();
        $categorie = $article->categorie_id;

        $stockArrive = Stock::firstOrCreate(
            [
                'article_id' => $transfert->produit_id,
                'lieu_stockage_id' => $transfert->lieu_stockage_arrive_id,
                'categorie_article_id' => $categorie,
            ],
            ['quantite' => 0]
        );


        $stockArrive->quantite += $transfert->quantite;
        $stockArrive->save();

        Entrer::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $transfert->produit_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $transfert->lieu_stockage_arrive_id,
            'quantite' => $transfert->quantite,
            'unite_id' => $transfert->unite_id,
            'entre' => now()->toDateString(),
            'motif' => 'Transfert n°' . $transfert->id . ' (Bon: ' . $bonTransfert->numero_bon . ')',
        ]);

        $stockDepart = Stock::where([
            'article_id' => $transfert->produit_id,
            'lieu_stockage_id' => $transfert->lieu_stockage_depart_id
        ])->first();

        if ($stockDepart) {
            $stockDepart->quantite -= $transfert->quantite;
            $stockDepart->save();
        }

        Sortie::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $transfert->produit_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $transfert->lieu_stockage_depart_id,
            'quantite' => $transfert->quantite,
            'unite_id' => $transfert->unite_id,
            'sortie' => now()->toDateString(),
            'motif' => 'Transfert n°' . $transfert->id . ' (Bon: ' . $bonTransfert->numero_bon . ')',
        ]);

        // Calcul de la consommation en litres selon la formule
        $consommationCm = $transfert->gasoil_depart - $transfert->gasoil_arrive;
        $vehicule = Materiel::with('pneus')->find($transfert->materiel_id);
        $consommationTotale = $vehicule->convertirCmEnLitres($consommationCm);

        $heureDepart = Carbon::parse($transfert->heure_depart);
        $heureArrivee = Carbon::parse($transfert->heure_arrivee);
        $heuresTravail = $heureDepart->diffInHours($heureArrivee);
        $consommationReelleParHeure = $heuresTravail > 0 ? $consommationTotale / $heuresTravail : 0;

        $consommationHoraireReference = $vehicule->consommation_horaire;
        $statutConsommationHoraire = 'normal';
        $ecartConsommationHoraire = 0;

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

        $destination = Destination::where('nom_destination', $transfert->lieuStockageArrive->nom_lieu)->first();
        $consommationDestinationReference = $destination ? $destination->consommation_reference : null;
        $statutConsommationDestination = 'normal';
        $ecartConsommationDestination = 0;

        if ($consommationDestinationReference > 0) {
            $ecartConsommationDestination = $consommationTotale - $consommationDestinationReference;
            $pourcentageEcartDestination = ($ecartConsommationDestination / $consommationDestinationReference) * 100;

            if ($pourcentageEcartDestination > 15) {
                $statutConsommationDestination = 'trop_elevee';
            } elseif ($pourcentageEcartDestination < 15) {
                $statutConsommationDestination = 'trop_basse';
            } else {
                $statutConsommationDestination = 'normale';
            }
        }

        $transfert->update([
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'consommation_horaire_reference' => $consommationHoraireReference,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'consommation_totale' => $consommationTotale,
            'consommation_destination_reference' => $consommationDestinationReference,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
        ]);

        if ($vehicule && $vehicule->pneus && $transfert->distance) {
            foreach ($vehicule->pneus as $pneu) {
                $nouveauKilometrage = $pneu->kilometrage + $transfert->distance;
                $pneu->update(['kilometrage' => $nouveauKilometrage]);
            }
        }

        if ($vehicule) {
            $vehicule->update([
                'gasoil_consommation' => $vehicule->gasoil_consommation + $consommationTotale,
                'actuelGasoil' => $transfert->gasoil_arrive,
                'compteur_actuel' => $transfert->compteur_arrive,
            ]);

            /* if ($vehicule->actuelGasoil <= $vehicule->seuil && !$vehicule->seuil_notified) {
                $admin = User::whereHas('role', function ($query) {
                    $query->where('id', 1);
                })->first();

                if ($admin) {
                    Notification::send($admin, new GasoilSeuilAtteint($vehicule));
                }

                $vehicule->update(['seuil_notified' => true]);
            } */
        }

        ConsommationGasoil::create([
            'vehicule_id' => $transfert->materiel_id,
            'quantite' => $consommationTotale,
            'distance_km' => $transfert->distance ?? 0,
            'date_consommation' => Carbon::now()->toDateString(),
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'consommation_horaire_reference' => $consommationHoraireReference,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'consommation_totale' => $consommationTotale,
            'consommation_destination_reference' => $consommationDestinationReference,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
            'bon_livraison_id' => null,
            'production_materiel_id' => null,
            'transfert_produit_id' => $transfert->id,
            'destination_id' => $destination ? $destination->id : null,
        ]);

        $quantiteTotaleTransferee = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
            ->where('isDelivred', 1)
            ->sum('quantite');

        if ($quantiteTotaleTransferee >= $bonTransfert->quantite) {
            $bonTransfert->update(['est_utilise' => true]);
        }

        return response()->json([
            'message' => 'Transfert validé avec succès',
            'transfert' => $transfert,
            'progression_bon' => [
                'quantite_totale' => $bonTransfert->quantite,
                'quantite_transferee' => $quantiteTotaleTransferee,
                'quantite_restante' => $bonTransfert->quantite - $quantiteTotaleTransferee,
                'est_complet' => $quantiteTotaleTransferee >= $bonTransfert->quantite,
            ],
        ], 200);
    }

    public function show($id)
    {
        $transfert = TransfertProduit::with(['materiel', 'chauffeur', 'aideChauffeur', 'lieuStockageDepart', 'lieuStockageArrive', 'produit', 'bonTransfert'])->find($id);

        if (!$transfert) {
            return response()->json(['message' => 'Transfert non trouvé'], 404);
        }

        return $transfert;
    }

    public function edit($id)
    {
        $transfert = TransfertProduit::with(['materiel', 'chauffeur', 'aideChauffeur', 'lieuStockageDepart', 'lieuStockageArrive', 'produit', 'bonTransfert'])->find($id);

        if (!$transfert) {
            return response()->json(['message' => 'Transfert non trouvé'], 404);
        }

        return response()->json($transfert);
    }

    public function exportTransfert(Request $request)
    {
        return Excel::download(new TransfertExport($request), 'transferts-' . date('Y-m-d') . '.xlsx');
    }

    public function exportBonsTransferts(Request $request)
    {
        return Excel::download(new BonTransfertExport($request), 'bons-transferts-' . date('Y-m-d') . '.xlsx');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'date' => 'required|date',
            'heure_depart' => 'required',
            'heure_arrivee' => 'nullable',
            'materiel_id' => 'required|exists:materiels,id',
            'gasoil_depart' => 'required|numeric|min:0.1',
            'gasoil_arrive' => 'nullable|numeric|min:0',
            'compteur_depart' => 'nullable|numeric|min:0.1',
            'compteur_arrive' => 'nullable|numeric|min:0.1',
            'distance' => 'nullable|numeric|min:0',
            'chauffeur_id' => 'required|exists:conducteurs,id',
            'aideChauffeur_id' => 'nullable|exists:aide_chauffeurs,id',
            'quantite' => 'required|numeric|min:0.1',
            'remarque' => 'nullable|string',
        ]);

        $transfert = TransfertProduit::find($id);

        if (!$transfert) {
            return response()->json(['message' => 'Transfert non trouvé'], 404);
        }

        if ($transfert->isDelivred) {
            $this->annulerTransfert($transfert);
        }

        $bonTransfert = $transfert->bonTransfert;

        $quantiteDejaTransfereeSansCeTransfert = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
            ->where('id', '!=', $transfert->id)
            ->where('isDelivred', 1)
            ->sum('quantite');

        $quantiteRestante = $bonTransfert->quantite - $quantiteDejaTransfereeSansCeTransfert;

        if ($request->quantite > $quantiteRestante) {
            return response()->json([
                'message' => 'La nouvelle quantité (' . $request->quantite .
                    ') dépasse la quantité restante du bon (' . $quantiteRestante . ')'
            ], 422);
        }

        $transfert->update([
            'date' => $request->date,
            'heure_depart' => $request->heure_depart,
            'heure_arrivee' => $request->heure_arrivee,
            'materiel_id' => $request->materiel_id,
            'gasoil_depart' => $request->gasoil_depart,
            'gasoil_arrive' => $request->gasoil_arrive,
            'compteur_depart' => $request->compteur_depart,
            'compteur_arrive' => $request->compteur_arrive,
            'distance' => $request->distance,
            'chauffeur_id' => $request->chauffeur_id,
            'aideChauffeur_id' => $request->aideChauffeur_id,
            'quantite' => $request->quantite,
            'remarque' => $request->remarque,
        ]);

        if ($transfert->isDelivred) {
            $this->appliquerValidationTransfert($transfert, $user);
        }

        return response()->json([
            'message' => 'Transfert mis à jour avec succès',
            'transfert' => $transfert
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $transfert = TransfertProduit::find($id);

        if (!$transfert) {
            return response()->json(['message' => 'Transfert non trouvé'], 404);
        }

        $bonTransfert = $transfert->bonTransfert;

        if ($transfert->isDelivred) {
            $this->annulerTransfert($transfert);
        }

        ConsommationGasoil::where('transfert_produit_id', $transfert->id)->delete();

        $transfert->delete();

        $quantiteTransferee = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
            ->where('isDelivred', 1)
            ->sum('quantite');

        if ($quantiteTransferee >= $bonTransfert->quantite) {
            $bonTransfert->update(['est_utilise' => true]);
        } else {
            $bonTransfert->update(['est_utilise' => false]);
        }

        return response()->json([
            'message' => 'Transfert supprimé avec succès'
        ]);
    }

    public function validerTransfert(Request $request, $id)
    {
        return response()->json([
            'message' => 'Utilisez la nouvelle méthode /validate-arrival/{id} pour valider l\'arrivée'
        ], 400);
    }

    public function filterData()
    {
        return response()->json([
            'produits' => ArticleDepot::select('id', 'nom_article')->get(),
            'lieuStockages' => Lieu_stockage::select('id', 'nom_lieu')->get(),
            'materiels' => Materiel::select('id', 'nom_materiel')->get(),
            'chauffeurs' => Conducteur::select('id', 'nom_conducteur')->get(),
        ]);
    }

    public function getTransfertStats(Request $request)
    {
        $period = $request->input('period', '7days');

        $query = TransfertProduit::query();

        if ($period === '7days') {
            $query->where('date', '>=', now()->subDays(7));
        } elseif ($period === 'last_week') {
            $query->whereBetween('date', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ]);
        } elseif ($period === 'this_month') {
            $query->whereBetween('date', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $totalTransferts = $query->count();
        $quantiteTotale = $query->sum('quantite');
        $transfertsValides = $query->where('isDelivred', 1)->count();

        return response()->json([
            'total_transferts' => $totalTransferts,
            'quantite_totale' => $quantiteTotale,
            'transferts_valides' => $transfertsValides,
            'taux_validation' => $totalTransferts > 0 ? ($transfertsValides / $totalTransferts) * 100 : 0,
        ]);
    }

    public function getLatestTransferts()
    {
        $transferts = TransfertProduit::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'bonTransfert'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($transferts);
    }

    public function indexBonsTransfert(Request $request)
    {
        $query = BonTransfert::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'user', 'unite', 'transferts']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_bon', 'like', '%' . $search . '%')
                    ->orWhereHas('produit', function ($q2) use ($search) {
                        $q2->where('nom_article', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->has('est_utilise') && $request->est_utilise !== '') {
            $query->where('est_utilise', $request->est_utilise);
        }

        if ($request->has('date_start') && !empty($request->date_start)) {
            $query->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->has('date_end') && !empty($request->date_end)) {
            $query->whereDate('created_at', '<=', $request->date_end);
        }

        $perPage = $request->per_page ?? 10;
        $bons = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $bons->getCollection()->transform(function ($bon) {
            $quantiteTransferee = $bon->transferts->where('isDelivred', 1)->sum('quantite');
            $bon->quantite_transferee = $quantiteTransferee;
            $bon->quantite_restante = max(0, $bon->quantite - $quantiteTransferee);
            $bon->est_complet = ($bon->quantite_restante <= 0);
            $bon->pourcentage_completion = $bon->quantite > 0 ?
                ($quantiteTransferee / $bon->quantite) * 100 : 0;

            return $bon;
        });

        return response()->json($bons);
    }

    public function createBonsTransfert()
    {
        $produits = ArticleDepot::with(['uniteLivraison'])->get();
        $lieuStockages = Lieu_stockage::all();
        $unites = Unite::all();

        return response()->json([
            'produits' => $produits,
            'lieuStockages' => $lieuStockages,
            'unites' => $unites,
        ]);
    }

    public function getNextBonNumber()
    {
        try {
            $currentYear = date('Y');

            $lastBon = BonTransfert::whereYear('created_at', $currentYear)
                ->orderByRaw('CAST(numero_bon AS INTEGER) DESC')
                ->first();

            if ($lastBon && is_numeric($lastBon->numero_bon)) {
                $nextNumber = (int)$lastBon->numero_bon + 1;
            } else {
                $nextNumber = 1;
            }

            Log::info('Numéro de bon généré', [
                'année' => $currentYear,
                'dernier_numéro' => $lastBon ? $lastBon->numero_bon : 'Aucun',
                'prochain_numéro' => $nextNumber
            ]);

            return response()->json([
                'next_number' => $nextNumber,
                'current_year' => $currentYear,
                'message' => 'Numéro suggéré récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du numéro de bon', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la génération du numéro',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function storeBonTransfert(Request $request)
    {
        $user = Auth::user();

        Log::info('Début création bon de transfert', [
            'user' => $user->id,
            'data' => $request->all()
        ]);

        $request->validate([
            'numero_bon' => 'nullable|unique:bon_transferts,numero_bon',
            'date_transfert' => 'required|date',
            'produit_id' => 'required|exists:article_depots,id',
            'quantite' => 'required|numeric|min:0.1',
            'lieu_stockage_depart_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_arrive_id' => 'required|exists:lieu_stockages,id',
            'unite_id' => 'required|exists:unites,id',
            'commentaire' => 'nullable|string',
        ]);

        try {
            // Vérifier si le numéro est fourni, sinon le générer
            $numeroBon = $request->numero_bon;
            if (empty($numeroBon) || trim($numeroBon) === '') {
                // Générer le prochain numéro
                $currentYear = date('Y');
                $lastBon = BonTransfert::whereYear('created_at', $currentYear)
                    ->orderByRaw('CAST(numero_bon AS UNSIGNED) DESC')
                    ->first();

                if ($lastBon && is_numeric($lastBon->numero_bon)) {
                    $numeroBon = (int)$lastBon->numero_bon + 1;
                } else {
                    $numeroBon = 1;
                }

                Log::info('Numéro auto-généré', ['numero' => $numeroBon]);
            }

            // Validation des lieux identiques
            if ($request->lieu_stockage_depart_id == $request->lieu_stockage_arrive_id) {
                Log::warning('Lieux identiques rejetés', ['depart' => $request->lieu_stockage_depart_id]);
                return response()->json([
                    'message' => 'Le lieu de départ et le lieu d\'arrivée doivent être différents'
                ], 422);
            }

            // Vérifier le stock disponible (pour la quantité totale)
            $stockDepart = Stock::where([
                'article_id' => $request->produit_id,
                'lieu_stockage_id' => $request->lieu_stockage_depart_id
            ])->first();

            if (!$stockDepart) {
                Log::warning('Stock non trouvé', [
                    'produit' => $request->produit_id,
                    'lieu' => $request->lieu_stockage_depart_id
                ]);
                return response()->json([
                    'message' => 'Stock non trouvé dans le lieu de départ'
                ], 422);
            }

            if ($stockDepart->quantite < $request->quantite) {
                Log::warning('Stock insuffisant', [
                    'disponible' => $stockDepart->quantite,
                    'demande' => $request->quantite
                ]);
                return response()->json([
                    'message' => "Stock insuffisant dans le lieu de départ. Disponible: {$stockDepart->quantite} m³"
                ], 422);
            }

            $bonTransfert = BonTransfert::create([
                'numero_bon' => $numeroBon,
                'date_transfert' => $request->date_transfert,
                'produit_id' => $request->produit_id,
                'quantite' => $request->quantite,
                'lieu_stockage_depart_id' => $request->lieu_stockage_depart_id,
                'lieu_stockage_arrive_id' => $request->lieu_stockage_arrive_id,
                'unite_id' => $request->unite_id,
                'commentaire' => $request->commentaire,
                'user_id' => $user->id,
                'est_utilise' => false
            ]);

            Log::info('Nouveau bon de transfert créé avec succès', [
                'numéro' => $numeroBon,
                'utilisateur' => $user->nom,
                'année' => date('Y')
            ]);

            return response()->json([
                'message' => 'Bon de transfert créé avec succès',
                'bon_transfert' => $bonTransfert
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du bon de transfert', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $user->id,
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du bon de transfert.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function showBonTransfert($id)
    {
        $bonTransfert = BonTransfert::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'user', 'unite', 'transferts'])->find($id);

        if (!$bonTransfert) {
            return response()->json(['message' => 'Bon de transfert non trouvé'], 404);
        }

        $quantiteTransferee = $bonTransfert->transferts->where('isDelivred', 1)->sum('quantite');
        $bonTransfert->quantite_transferee = $quantiteTransferee;
        $bonTransfert->quantite_restante = max(0, $bonTransfert->quantite - $quantiteTransferee);
        $bonTransfert->est_complet = ($bonTransfert->quantite_restante <= 0);

        return response()->json($bonTransfert);
    }

    public function updateBonTransfert(Request $request, $id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $bonTransfert = BonTransfert::find($id);

        if (!$bonTransfert) {
            return response()->json(['message' => 'Bon de transfert non trouvé'], 404);
        }

        // Vérifier si le bon a déjà été utilisé partiellement
        $quantiteTransferee = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)
            ->where('isDelivred', 1)
            ->sum('quantite');

        if ($quantiteTransferee > 0) {
            return response()->json([
                'message' => 'Impossible de modifier un bon de transfert qui a déjà des transferts validés'
            ], 422);
        }

        $request->validate([
            'numero_bon' => ['required', Rule::unique('bon_transferts')->ignore($id)],
            'date_transfert' => 'required|date',
            'produit_id' => 'required|exists:article_depots,id',
            'quantite' => 'required|numeric|min:0.1',
            'lieu_stockage_depart_id' => 'required|exists:lieu_stockages,id',
            'lieu_stockage_arrive_id' => 'required|exists:lieu_stockages,id',
            'unite_id' => 'required|exists:unites,id',
            'commentaire' => 'nullable|string',
        ]);

        if ($request->lieu_stockage_depart_id == $request->lieu_stockage_arrive_id) {
            return response()->json([
                'message' => "Le lieu de départ et le lieu d'arrivée doivent être différents"
            ], 422);
        }

        // Vérifier le stock disponible
        $stockDepart = Stock::where([
            'article_id' => $request->produit_id,
            'lieu_stockage_id' => $request->lieu_stockage_depart_id
        ])->first();

        if (!$stockDepart || $stockDepart->quantite < $request->quantite) {
            return response()->json([
                'message' => 'Stock insuffisant dans le lieu de départ'
            ], 422);
        }

        $bonTransfert->update([
            'numero_bon' => $request->numero_bon,
            'date_transfert' => $request->date_transfert,
            'produit_id' => $request->produit_id,
            'quantite' => $request->quantite,
            'unite_id' => $request->unite_id,
            'lieu_stockage_depart_id' => $request->lieu_stockage_depart_id,
            'lieu_stockage_arrive_id' => $request->lieu_stockage_arrive_id,
            'commentaire' => $request->commentaire,
        ]);

        return response()->json([
            'message' => 'Bon de transfert mis à jour avec succès',
            'bon_transfert' => $bonTransfert
        ]);
    }

    public function destroyBonTransfert($id)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3])) {
            return response(['message' => 'Action non autorisée'], 403);
        }

        $bonTransfert = BonTransfert::find($id);

        if (!$bonTransfert) {
            return response()->json(['message' => 'Bon de transfert non trouvé'], 404);
        }

        $hasTransferts = TransfertProduit::where('bon_transfert_id', $bonTransfert->id)->exists();
        if ($hasTransferts) {
            return response()->json([
                'message' => 'Impossible de supprimer un bon de transfert qui a déjà des transferts'
            ], 422);
        }

        $bonTransfert->delete();

        return response()->json([
            'message' => 'Bon de transfert supprimé avec succès'
        ]);
    }

    public function getBonsTransfertDisponibles()
    {
        $bons = BonTransfert::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'unite', 'transferts'])
            ->where('est_utilise', false)
            ->get()
            ->filter(function ($bon) {
                $quantiteTransferee = $bon->transferts->where('isDelivred', 1)->sum('quantite');
                $quantiteRestante = $bon->quantite - $quantiteTransferee;
                return $quantiteRestante > 0;
            })
            ->values();

        return response()->json($bons);
    }

    public function getBonTransfertInfo($id)
    {
        try {
            $bonTransfert = BonTransfert::with(['produit', 'lieuStockageDepart', 'lieuStockageArrive', 'unite'])->findOrFail($id);

            $quantiteDejaTransferee = TransfertProduit::where('bon_transfert_id', $id)
                ->where('isDelivred', 1)
                ->sum('quantite');

            $article = ArticleDepot::with('uniteLivraison')->find($bonTransfert->produit_id);

            $quantiteMaxLivraison = null;
            $uniteLivraison = null;
            if ($article && $article->hasQuantiteMaxLivraison()) {
                $quantiteMaxLivraison = $article->quantite_max_livraison;
                $uniteLivraison = $article->uniteLivraison->nom_unite;
            }

            return response()->json([
                'bon' => $bonTransfert,
                'quantite_deja_transferee' => $quantiteDejaTransferee,
                'quantite_restante' => max(0, $bonTransfert->quantite - $quantiteDejaTransferee),
                'quantite_max_livraison' => $quantiteMaxLivraison,
                'unite_livraison' => $uniteLivraison,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des informations du bon', [
                'bon_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la récupération des informations du bon',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getStockDisponible($produitId, $lieuStockageId)
    {
        $stock = Stock::where('article_id', $produitId)
            ->where('lieu_stockage_id', $lieuStockageId)
            ->with(['articleDepot.uniteLivraison'])
            ->first();

        return response()->json([
            'quantite_disponible' => $stock ? $stock->quantite : 0,
            'unite' => $stock && $stock->articleDepot && $stock->articleDepot->uniteLivraison
                ? $stock->articleDepot->uniteLivraison->nom_unite : null,
        ]);
    }

    private function annulerTransfert(TransfertProduit $transfert)
    {
        // Re-créditer le stock du lieu de départ
        $stockDepart = Stock::where([
            'article_id' => $transfert->produit_id,
            'lieu_stockage_id' => $transfert->lieu_stockage_depart_id
        ])->first();

        if ($stockDepart) {
            $stockDepart->quantite += $transfert->quantite;
            $stockDepart->save();
        }

        // Débiter le stock du lieu d'arrivée
        $stockArrive = Stock::where([
            'article_id' => $transfert->produit_id,
            'lieu_stockage_id' => $transfert->lieu_stockage_arrive_id
        ])->first();

        if ($stockArrive) {
            $stockArrive->quantite -= $transfert->quantite;
            $stockArrive->save();
        }

        // Supprimer les mouvements d'entrée et de sortie associés
        Entrer::where('motif', 'like', 'Transfert n°' . $transfert->id . '%')->delete();
        Sortie::where('motif', 'like', 'Transfert n°' . $transfert->id . '%')->delete();

        // Supprimer la consommation associée
        ConsommationGasoil::where('transfert_produit_id', $transfert->id)->delete();

        // Réinitialiser le gasoil du véhicule
        $vehicule = Materiel::find($transfert->materiel_id);
        if ($vehicule) {
            // Convertir la consommation en cm pour restaurer le gasoil
            $consommationCm = $transfert->gasoil_depart - $transfert->gasoil_arrive;
            $gasoilRestaurer = $vehicule->convertirLitresEnCm($consommationCm);

            $vehicule->update([
                'actuelGasoil' => $vehicule->actuelGasoil + $gasoilRestaurer,
                'gasoil_consommation' => $vehicule->gasoil_consommation - ($vehicule->convertirCmEnLitres($consommationCm)),
            ]);
        }
    }

    private function appliquerTransfertComplet(TransfertProduit $transfert, $user, $distance)
    {
        // Débiter le stock du lieu de départ
        $stockDepart = Stock::firstOrCreate(
            [
                'article_id' => $transfert->produit_id,
                'lieu_stockage_id' => $transfert->lieu_stockage_depart_id
            ],
            ['quantite' => 0]
        );

        $stockDepart->quantite -= $transfert->quantite;
        $stockDepart->save();

        // Créditer le stock du lieu d'arrivée
        $stockArrive = Stock::firstOrCreate(
            [
                'article_id' => $transfert->produit_id,
                'lieu_stockage_id' => $transfert->lieu_stockage_arrive_id
            ],
            ['quantite' => 0]
        );

        $stockArrive->quantite += $transfert->quantite;
        $stockArrive->save();

        // Créer les mouvements d'entrée et de sortie
        $bonNumero = $transfert->bonTransfert ? $transfert->bonTransfert->numero_bon : 'N/A';

        $article = ArticleDepot::with('categorie')->where('id', $transfert->produit_id)->firstOrFail();
        $categorie = $article->categorie_id;

        Entrer::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $transfert->produit_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $transfert->lieu_stockage_arrive_id,
            'quantite' => $transfert->quantite,
            'unite_id' => $transfert->unite_id,
            'entre' => now()->toDateString(),
            'motif' => 'Transfert n°' . $transfert->id . ' (Bon: ' . $bonNumero . ')',
        ]);

        Sortie::create([
            'user_name' => $user->nom ? $user->nom : 'Système',
            'article_id' => $transfert->produit_id,
            'categorie_article_id' => $categorie,
            'lieu_stockage_id' => $transfert->lieu_stockage_depart_id,
            'quantite' => $transfert->quantite,
            'unite_id' => $transfert->unite_id,
            'sortie' => now()->toDateString(),
            'motif' => 'Transfert n°' . $transfert->id . ' (Bon: ' . $bonNumero . ')',
        ]);

        // Calcul de la consommation en litres selon la formule
        $consommationCm = $transfert->gasoil_depart - $transfert->gasoil_arrive;
        $vehicule = Materiel::with('pneus')->find($transfert->materiel_id);
        $consommationTotale = $vehicule->convertirCmEnLitres($consommationCm);

        $heureDepart = Carbon::parse($transfert->heure_depart);
        $heureArrivee = Carbon::parse($transfert->heure_arrivee);
        $heuresTravail = $heureDepart->diffInHours($heureArrivee);

        $consommationReelleParHeure = $heuresTravail > 0
            ? $consommationTotale / $heuresTravail
            : 0;

        $consommationHoraireReference = $vehicule->consommation_horaire;
        $statutConsommationHoraire = 'normal';
        $ecartConsommationHoraire = 0;

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

        $destination = Destination::where('nom_destination', $transfert->lieuStockageArrive->nom_lieu)->first();
        $consommationDestinationReference = $destination ? $destination->consommation_reference : null;
        $statutConsommationDestination = 'normal';
        $ecartConsommationDestination = 0;

        if ($consommationDestinationReference > 0) {
            $ecartConsommationDestination = $consommationTotale - $consommationDestinationReference;
            $pourcentageEcartDestination = ($ecartConsommationDestination / $consommationDestinationReference) * 100;

            if ($pourcentageEcartDestination > 15) {
                $statutConsommationDestination = 'trop_elevee';
            } elseif ($pourcentageEcartDestination < 15) {
                $statutConsommationDestination = 'trop_basse';
            } else {
                $statutConsommationDestination = 'normale';
            }
        }

        $transfert->update([
            'consommation_reelle_par_heure' => $consommationReelleParHeure,
            'consommation_horaire_reference' => $consommationHoraireReference,
            'ecart_consommation_horaire' => $ecartConsommationHoraire,
            'statut_consommation_horaire' => $statutConsommationHoraire,
            'consommation_totale' => $consommationTotale,
            'consommation_destination_reference' => $consommationDestinationReference,
            'ecart_consommation_destination' => $ecartConsommationDestination,
            'statut_consommation_destination' => $statutConsommationDestination,
        ]);

        // Mettre à jour le kilométrage des pneus
        if ($vehicule && $vehicule->pneus) {
            foreach ($vehicule->pneus as $pneu) {
                $nouveauKilometrage = $pneu->kilometrage + $distance;
                $pneu->update(['kilometrage' => $nouveauKilometrage]);
            }
        }

        // Mettre à jour le gasoil du véhicule
        if ($vehicule) {
            $vehicule->update([
                'gasoil_consommation' => $vehicule->gasoil_consommation + $consommationTotale,
                'actuelGasoil' => $transfert->gasoil_arrive,
                'compteur_actuel' => $transfert->compteur_arrive,
            ]);
        }

        // Mettre à jour la consommation
        $consommation = ConsommationGasoil::where('transfert_produit_id', $transfert->id)->first();
        if ($consommation) {
            $consommation->update([
                'quantite' => $consommationTotale,
                'distance_km' => $distance,
                'consommation_reelle_par_heure' => $consommationReelleParHeure,
                'consommation_horaire_reference' => $consommationHoraireReference,
                'ecart_consommation_horaire' => $ecartConsommationHoraire,
                'statut_consommation_horaire' => $statutConsommationHoraire,
                'consommation_totale' => $consommationTotale,
                'consommation_destination_reference' => $consommationDestinationReference,
                'ecart_consommation_destination' => $ecartConsommationDestination,
                'statut_consommation_destination' => $statutConsommationDestination,
            ]);
        }
    }
}
