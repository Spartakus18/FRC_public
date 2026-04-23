<?php

use App\Http\Controllers\AjustementStock\ArticleDepotController;
use App\Http\Controllers\AjustementStock\EntrerController;
use App\Http\Controllers\AjustementStock\LieuStockageController;
use App\Http\Controllers\AjustementStock\OperationAtelierMecaController;
use App\Http\Controllers\AjustementStock\SortieController;
use App\Http\Controllers\AjustementStock\StockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BC\Bon_commandeController;
use App\Http\Controllers\BC\Gasoil\BonGasoilController;
use App\Http\Controllers\BC\Huile\BonHuileController;
use App\Http\Controllers\BL\BonLivraisonController;
use App\Http\Controllers\Consommable\ArticleVersementController;
use App\Http\Controllers\Consommable\GasoilController;
use App\Http\Controllers\Gasoil\TransfertController as GasoilTransfertController;
use App\Http\Controllers\Gasoil\VersementController;
use App\Http\Controllers\Huile\TransfertController as HuileTransfertController;
use App\Http\Controllers\Huile\VersementController as HuileVersementController;
use App\Http\Controllers\Huile\VidangeController;
use App\Http\Controllers\Consommable\HuileController;
use App\Http\Controllers\Consommable\PerteGasoilController;
use App\Http\Controllers\VersementAchatController;
use App\Http\Controllers\ConsommationGasoilController;
use App\Http\Controllers\Fourniture\FournitureController;
use App\Http\Controllers\FournitureConsommable\EntreeFournitureController;
use App\Http\Controllers\FournitureConsommable\FournitureConsommableController;
use App\Http\Controllers\FournitureConsommable\SortieFournitureController;
use App\Http\Controllers\FournitureConsommable\StockFournitureController;
use App\Http\Controllers\HistoriqueFournitureController;
use App\Http\Controllers\Location\AideChauffeurController;
use App\Http\Controllers\HistoriquePneuController;
use App\Http\Controllers\Import\ExcelImportController;
use App\Http\Controllers\JourneeController;
use App\Http\Controllers\Location\ConducteurController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Location\UniteFacturationController;
use App\Http\Controllers\Materiel\MaterielController;
use App\Http\Controllers\Materiel\SubdivisionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationVehiculeController;
use App\Http\Controllers\Parametre\ArticleController;
use App\Http\Controllers\Parametre\ClientController;
use App\Http\Controllers\Parametre\DepotController;
use App\Http\Controllers\Parametre\DestinationController;
use App\Http\Controllers\Parametre\PneuController;
use App\Http\Controllers\Parametre\SubventionController;
use App\Http\Controllers\Parametre\UniteController;
use App\Http\Controllers\Parametre\UtilisateurController;
use App\Http\Controllers\PerteGasoilOperationController;
use App\Http\Controllers\Produit\CategorieController;
use App\Http\Controllers\Produit\MelangeProduitController;
use App\Http\Controllers\Produit\ProduitController;
use App\Http\Controllers\Produit\TransfertController;
use App\Http\Controllers\Produit\VenteController;
use App\Models\AjustementStock\ArticleDepot;
use App\Models\AjustementStock\Lieu_stockage;
use App\Models\FournitureConsommable\FournitureConsommable;
use App\Models\Huile\Subdivision;
use App\Models\OperationVehicule;
use App\Models\Parametre\Materiel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/reset-auth', function () {
    // Supprime tous les tokens
    DB::table('personal_access_tokens')->truncate();

    return response()->json([
        'status' => 'ok',
        'message' => 'Tokens et sessions réinitialisés'
    ]);
});

Route::get('/list-users', function () {
    $secret = request()->query('secret');

    if ($secret !== env('MIGRATE_SECRET')) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }

    $users = User::all(['id', 'nom', 'identifiant', 'role_id', 'depot_id', 'created_at']);
    return response()->json([
        'status' => 'ok',
        'users' => $users
    ]);
});

Route::get('/run-migrate-fresh', function (Request $request) {

    // Vérification du token secret dans la query string
    $token = $request->query('key');
    if ($token !== env('MIGRATE_SECRET')) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized: invalid token'
        ], 401);
    }

    try {
        // Nettoyage cache avant migration (optionnel)
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Exécution des migrations fresh avec seed
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        // Récupération de la sortie complète
        $output = Artisan::output();

        return response()->json([
            'status' => 'ok',
            'message' => 'Database refreshed and seeders executed successfully',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/db-check', function () {
    try {
        $database = DB::connection()->getDatabaseName(); // récupère le nom
        $driver = DB::connection()->getDriverName(); // récupère le driver
        $host = DB::connection()->getConfig('host'); // récupère l’hôte
        return response()->json([
            'status' => 'ok',
            'driver' => $driver,
            'database' => $database,
            'host' => $host,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


//------------ LOGIN ------------
Route::get('/index/register', [AuthController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['role:1'])->group(function () {
        Route::post('/register', [AuthController::class, 'store']);
    });

    Route::post('logout', [AuthController::class, 'logout']);

    /*-*************************************************-*/
    /*                 Notification                      */
    /*-*************************************************-*/
    // Routes pour les notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/delete-all', [NotificationController::class, 'deleteAll']);


    /*-*************************************************-*/
    /*                     PARAMETRE                     */
    /*-*************************************************-*/
    // ------------- User ---------------
    Route::get('/index/utilisateur', [UtilisateurController::class, 'index']);
    Route::get('/show/utilisateur/{id}', [UtilisateurController::class, 'show']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::get('/create/utilisateur', [UtilisateurController::class, 'create']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/utilisateur/{id}', [UtilisateurController::class, 'update']);
        Route::delete('/destroy/utilisateur/{id}', [UtilisateurController::class, 'destroy']);
    });
    Route::put('/my-account/update', [UtilisateurController::class, 'updateMyAccount']);

    // ------------- Client ---------------
    Route::get('/index/client', [ClientController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/client', [ClientController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/client/{id}', [ClientController::class, 'edit']);
        Route::put('/update/client/{id}', [ClientController::class, 'update']);
        Route::delete('/destroy/client/{id}', [ClientController::class, 'destroy']);
    });

    // ------------- Unité ---------------
    Route::get('/index/unite', [UniteController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/unite', [UniteController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/unite/{id}', [UniteController::class, 'edit']);
        Route::put('/update/unite/{id}', [UniteController::class, 'update']);
        Route::delete('/destroy/unite/{id}', [UniteController::class, 'destroy']);
    });

    // ------------- Destination ---------------
    Route::get('/index/destination', [DestinationController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/destination', [DestinationController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/destination/{id}', [DestinationController::class, 'edit']);
        Route::put('/update/destination/{id}', [DestinationController::class, 'update']);
        Route::delete('/destroy/destination/{id}', [DestinationController::class, 'destroy']);
    });

    // ------------- Dépot ---------------
    Route::get('/index/depot', [DepotController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/depot', [DepotController::class, 'store']);
    });
    Route::get('/edit/depot/{id}', [DepotController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/depot/{id}', [DepotController::class, 'update']);
        Route::delete('/destroy/depot/{id}', [DepotController::class, 'destroy']);
    });

    /*
            ARTICLE
    */
    /* Route::get('/index/article', [ArticleController::class, 'index']);
    Route::post('/store/article', [ArticleController::class, 'store']);
    Route::get('/edit/article/{id}', [ArticleController::class, 'show']);
    Route::put('/update/article/{id}', [ArticleController::class, 'update']);
    Route::delete('/destroy/article/{id}', [ArticleController::class, 'destroy']); */



    // ------------- Subvention ---------------
    Route::get('/index/subvention', [SubventionController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/subvention', [SubventionController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/subvention/{id}', [SubventionController::class, 'edit']);
        Route::put('/update/subvention/{id}', [SubventionController::class, 'update']);
        Route::delete('/destroy/subvention/{id}', [SubventionController::class, 'destroy']);
    });

    /*-*************************************************-*/
    /*            Gestion des consommable                */
    /*-*************************************************-*/
    // ------------- Pneu ---------------
    Route::get('/index/pneus', [PneuController::class, 'index']);
    Route::get('/create/pneus', [PneuController::class, 'create']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/pneu', [PneuController::class, 'store']);
        Route::post('/action/pneu', [PneuController::class, 'actionPneu']);
    });
    Route::get('/show/pneu/{id}', [PneuController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/pneu/{id}', [PneuController::class, 'edit']);
        Route::put('/update/pneu/{id}', [PneuController::class, 'update']);
        Route::delete('/destroy/pneu/{id}', [PneuController::class, 'destroy']);
    });
    Route::get('export/pneu', [PneuController::class, 'exportExcel']);

    //------------- historique pneu ----------------------
    Route::get('/historique-pneus/{pneuId}', [HistoriquePneuController::class, 'show']);
    Route::get('/historique-pneus', [HistoriquePneuController::class, 'index']);


    // ------------ BonGasoil -----------
    // obtenir un numéro de bon
    Route::get('/get-num-bon-gasoil/{type_bon}', [BonGasoilController::class, 'generateBon']);
    Route::get("/index/bon-gasoil", [BonGasoilController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/bon-gasoil', [BonGasoilController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/bon-gasoil/{bonGasoil}', [BonGasoilController::class, 'update']);
        Route::get('/bons/gasoil/autocomplete', [BonGasoilController::class, 'getBon']);
        Route::delete('/destroy/bon-gasoil/{bonGasoil}', [BonGasoilController::class, 'destroy']);
    });
    Route::get('/bon-gasoil/gasoil', [BonGasoilController::class, 'bonGasoilWithGasoil']);

    // ------------ Gasoil --------------
    Route::get('/index/gasoils', [GasoilController::class, 'index']);
    Route::get('/index/consommation', [ConsommationGasoilController::class, 'index']);
    Route::get('export/gasoil', [GasoilController::class, 'exportExcel']);
    Route::get('/byMachine/consommation/', [ConsommationGasoilController::class, 'byMachine']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/gasoil', [GasoilController::class, 'store']);
        Route::delete('/gasoil/{gasoil}', [GasoilController::class, 'destroy']);
        Route::patch('/gasoil/{id}', [GasoilController::class, 'updateQuantite']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::delete('/destroy/gasoil/{gasoil}', [GasoilController::class, 'destroy']);
        Route::put('/gasoil/{id}', [GasoilController::class, 'updateGasoil']);
        Route::post('/gasoil/transfert', [GasoilController::class, 'transfert']);
    });


    /* Gasoil confirmation versemment */
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::patch('gasoil/{gasoil}/confirm', [GasoilController::class, 'confirm']);
    });

    Route::post('gasoil/versement-achat', [VersementAchatController::class, 'storeGasoil']);

    Route::post('huile/versement-achat', [VersementAchatController::class, 'storeHuile']);

    /* Ajustement gasoil */
    Route::post('/materiel/ajuster-gasoil', [PerteGasoilOperationController::class, 'ajustement']);

    // ------------- BonHuile ------------
    Route::get("/index/bon-huile", [BonHuileController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/bon-huile', [BonHuileController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/bon-huile/{bonHuile}', [BonHuileController::class, 'update']);
        Route::delete("/destroy/bon-huile/{bonHuile}", [BonHuileController::class, "destroy"]);
    });
    Route::get('/get-num-bon-huile/{type_bon}', [BonHuileController::class, 'generateBon']);
    // ----------- huile -----------------
    Route::get('/index/huiles', [HuileController::class, 'index']);
    Route::get('/export/huiles', [HuileController::class, 'exportExcel']);
    Route::get('/huile/article', [ArticleController::class, 'getHuileVersement']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/huile', [HuileController::class, 'store']);
        Route::post('/huile/transfert', [HuileController::class, 'transfert']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/huile/{huile}', [HuileController::class, 'update']);
        Route::delete('/destroy/huile/{huile}', [HuileController::class, 'destroy']);
    });

    /* huile confirmation versemment */
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::patch('huile/{huile}/confirm', [HuileController::class, 'confirm']);
    });
    Route::get('huile/{huile}/confirmation-details', [HuileController::class, 'showForConfirmation']);

    /*-*************************************************-*/
    /*                  AJUSTEMENT STOCK                 */
    /*-*************************************************-*/

    //--------------- Entrée ------------------
    // Route::get('/index/entre', [EntrerController::class, 'index']);
    Route::get('/index/gasoil/entre', [EntrerController::class, 'indexGasoil']);
    Route::get('/index/huile/entre', [EntrerController::class, 'indexHuile']);
    Route::get('/index/produit/entre', [EntrerController::class, 'indexProduit']);
    Route::get('/create/entre', [EntrerController::class, 'create']);
    Route::get('/create/entre/gasoil', [EntrerController::class, 'createGasoil']);
    Route::get('/create/entre/huile', [EntrerController::class, 'createHuile']);
    Route::get('/create/entre/produit', [EntrerController::class, 'createProduit']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/entre', [EntrerController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/entre/{id}', [EntrerController::class, 'edit']);
        Route::put('/update/entre/{id}', [EntrerController::class, 'update']);
        Route::delete('/destroy/entre/{id}', [EntrerController::class, 'destroy']);
    });

    //--------------- Sortie ------------------
    // Route::get('/index/sortie', [SortieController::class, 'index']);
    Route::get('/index/sortie/gasoil', [SortieController::class, 'indexGasoil']);
    Route::get('/index/sortie/huile', [SortieController::class, 'indexHuile']);
    Route::get('/index/sortie/produit', [SortieController::class, 'indexProduit']);
    Route::get('/create/sortie', [SortieController::class, 'create']);
    Route::get('/create/sortie/gasoil', [SortieController::class, 'createGasoil']);
    Route::get('/create/sortie/huile', [SortieController::class, 'createHuile']);
    Route::get('/create/sortie/produit', [SortieController::class, 'createProduit']);
    Route::get('export/sortie', [SortieController::class, 'export']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/sortie', [SortieController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/sortie/{id}', [SortieController::class, 'update']);
        Route::delete('/destroy/sortie/{id}', [SortieController::class, 'destroy']);
    });

    //--------------- Stock ------------------
    Route::get('/index/stock', [StockController::class, 'index']);
    Route::get('export/stock', [StockController::class, 'exportExcel']);
    Route::get('/stock/gasoil', [StockController::class, 'getStockGasoil']);
    Route::get('/stock/huile', [StockController::class, 'getStockHuile']);
    Route::get('/stock/gasoil/atelier-meca', [StockController::class, 'getStockGasoilAtelierMeca']);

    //--------------- Atelier Mécanique ------------------
    Route::get('/index/atelier-meca/operations', [OperationAtelierMecaController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/atelier-meca/operation', [OperationAtelierMecaController::class, 'store']);
        Route::post('/atelier-meca/operation/{operation}/remettre', [OperationAtelierMecaController::class, 'remettre']);
    });

    //--------------- Lieu de Stockage ------------------
    Route::get('/index/lieuStockage', [LieuStockageController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/lieuStockage', [LieuStockageController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/lieuStockage/{id}', [LieuStockageController::class, 'edit']);
        Route::put('/update/lieuStockage/{id}', [LieuStockageController::class, 'update']);
        Route::delete('/destroy/lieuStockage/{id}', [LieuStockageController::class, 'destroy']);
    });

    // ------------- Article ---------------
    Route::get('/index/article', [ArticleDepotController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/article', [ArticleDepotController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/article/{id}', [ArticleDepotController::class, 'edit']);
        Route::put('/update/article/{id}', [ArticleDepotController::class, 'update']);
        Route::delete('/destroy/article/{id}', [ArticleDepotController::class, 'destroy']);
    });

    Route::get('/unites', [ArticleDepotController::class, 'getUnites']);
    Route::get('/categories', [ArticleDepotController::class, 'getCategories']);

    /*-*************************************************-*/
    /*                     LOCATION                      */
    /*-*************************************************-*/

    //--------------- Matériel ------------------
    Route::get('/index/materiels', [MaterielController::class, 'index']);
    Route::get('/export/materiel', [MaterielController::class, 'exportExcel']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/materiel', [MaterielController::class, 'store']);
    });
    Route::get('/edit/materiel/{id}', [MaterielController::class, 'show']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::patch('/materiel/{id}/compteur-actuel', [MaterielController::class, 'updateCompteurActuel']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/materiel/{id}', [MaterielController::class, 'update']);
        Route::delete('/destroy/materiel/{id}', [MaterielController::class, 'destroy']);
    });

    Route::get('/materiel/{id}/meta-info', [MaterielController::class, 'getMetaInfo']);
    Route::get('/materiels', [MaterielController::class, 'getMaterielGasoil']);

    //--------------- Subdivision ------------------
    Route::get('index/subdivision', [SubdivisionController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('store/subdivision', [SubdivisionController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('edit/subdivision/{id}', [SubdivisionController::class, 'edit']);
        Route::put('update/subdivision/{id}', [SubdivisionController::class, 'update']);
        Route::delete('destroy/subdivision/{id}', [SubdivisionController::class, 'destroy']);
    });

    //--------------- Conducteur ------------------
    Route::get('/index/conducteur', [ConducteurController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/conducteur', [ConducteurController::class, 'store']);
    });
    Route::get('/edit/conducteur/{id}', [ConducteurController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/conducteur/{id}', [ConducteurController::class, 'update']);
        Route::delete('/destroy/conducteur/{id}', [ConducteurController::class, 'destroy']);
    });

    //--------------- Conducteur ------------------
    Route::get('/index/aideChauffeur', [AideChauffeurController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/aideChauffeur', [AideChauffeurController::class, 'store']);
    });
    Route::get('/edit/aideChauffeur/{id}', [AideChauffeurController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/aideChauffeur/{id}', [AideChauffeurController::class, 'update']);
        Route::delete('/destroy/aideChauffeur/{id}', [AideChauffeurController::class, 'destroy']);
    });

    //--------------- Unité ------------------
    Route::get('/index/uniteFacturation', [UniteFacturationController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/uniteFacturation', [UniteFacturationController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/uniteFacturation/{id}', [UniteFacturationController::class, 'edit']);
        Route::put('/update/uniteFacturation/{id}', [UniteFacturationController::class, 'update']);
        Route::delete('/destroy/uniteFacturation/{id}', [UniteFacturationController::class, 'destroy']);
    });

    //--------------- Location ------------------
    Route::get('/index/location', [LocationController::class, 'index']);
    Route::get('/create/location', [LocationController::class, 'create']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/location', [LocationController::class, 'store']);
    });
    Route::get('/edit/location/{id}', [LocationController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/location/{id}', [LocationController::class, 'update']);
        Route::delete('/destroy/location/{id}', [LocationController::class, 'destroy']);
    });

    /*-*************************************************-*/
    /*                     PRODUCTION                    */
    /*-*************************************************-*/

    //--------------- Produit ------------------
    Route::get('/index/produit', [ProduitController::class, 'index']);
    Route::get('/export/produit', [ProduitController::class, 'exportExcel']);
    Route::get('/productions/summary', [ProduitController::class, 'getProductionSummary']);
    Route::get('/productions/latest', [ProduitController::class, 'latest']);
    Route::get('/productions/by-product', [ProduitController::class, 'getProductionByProduct']);
    Route::get('/create/produit', [ProduitController::class, 'create']);
    Route::middleware(['role:1,3'])->group(function () {
        Route::post('/store/produit', [ProduitController::class, 'store']);
        Route::put('/valider/produit/{id}', [ProduitController::class, 'validerProd']);
    });
    Route::get('/edit/produit/{id}', [ProduitController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/produit/{id}', [ProduitController::class, 'update']);
        Route::delete('/destroy/produit/{id}', [ProduitController::class, 'destroy']);
    });

    //--------------- Vente ------------------
    Route::get('/index/vente', [VenteController::class, 'index']);
    Route::get('/create/vente', [VenteController::class, 'create']);
    Route::get('/export/vente', [VenteController::class, 'exportExcel']);
    Route::middleware(['role:1,4'])->group(function () {
        Route::post('/store/vente', [VenteController::class, 'store']);
    });
    Route::get('/edit/vente/{id}', [VenteController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/vente/{id}', [VenteController::class, 'update']);
        Route::delete('/destroy/vente/{id}', [VenteController::class, 'destroy']);
    });

    //--------------- Transfert ------------------
    Route::get('/index/transfert/produit', [TransfertController::class, 'index']);
    Route::get('/create/transfert/produit', [TransfertController::class, 'create']);
    Route::get('/transferts/export', [TransfertController::class, 'exportTransfert']);
    Route::get('/show/transfert/produit/{id}', [TransfertController::class, 'show']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/transfert/produit', [TransfertController::class, 'store']);
        Route::post('/validate-arrival/{id}', [TransfertController::class, 'validerArrivee']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/transfert/produit/{id}', [TransfertController::class, 'edit']);
        Route::put('/update/transfert/produit/{id}', [TransfertController::class, 'update']);
        Route::delete('/destroy/transfert/produit/{id}', [TransfertController::class, 'destroy']);
    });

    //--------------- Bon Transfert ------------------
    Route::get('/index/bon-transfert', [TransfertController::class, 'indexBonsTransfert']);
    Route::get('/create/bon-transfert', [TransfertController::class, 'createBonsTransfert']);
    Route::get('/get/bon-transfert', [TransfertController::class, 'getBonsTransfert']);
    Route::get('/bons-transfert/export', [TransfertController::class, 'exportBonsTransferts']);
    Route::get('/show/bon-transfert/{id}', [TransfertController::class, 'showBonTransfert']);
    // Stock disponible
    Route::get('/transfert/stock/{produitId}/{lieuStockageId}', [TransfertController::class, 'getStockDisponible']);
    // Informations du bon
    Route::get('/transfert/bon-transfert/{id}/info', [TransfertController::class, 'getBonTransfertInfo']);
    // Numéro de bon auto-généré
    Route::get('/transfert/next-bon-number', [TransfertController::class, 'getNextBonNumber']);
    Route::middleware(['role:1,3,4'])->group(function () {
        // Création de bon de transfert
        Route::post('/store/bon-transfert', [TransfertController::class, 'storeBonTransfert']);
    });
    Route::middleware(['role:1'])->group(function () {
        // Mise à jour de bon de transfert
        Route::put('/update/bon-transfert/{id}', [TransfertController::class, 'updateBonTransfert']);
        Route::delete('/destroy/bon-transfert/{id}', [TransfertController::class, 'destroyBonTransfert']);
    });

    //--------------- Mélange ------------------
    Route::get('/index/melange', [MelangeProduitController::class, 'index']);
    Route::get('/create/melange', [MelangeProduitController::class, 'create']);
    Route::get('/export/melange', [MelangeProduitController::class, 'export']);
    Route::get('/stock/check/{produitId}/{lieuStockageId}', [MelangeProduitController::class, 'getStockDisponible']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/melange', [MelangeProduitController::class, 'store']);
    });
    Route::get('/edit/melange/{id}', [MelangeProduitController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/melange/{id}', [MelangeProduitController::class, 'update']);
        Route::delete('/destroy/melange/{id}', [MelangeProduitController::class, 'destroy']);
    });

    /*-*************************************************-*/
    /*                   CATEGORIE TRAVAUX               */
    /*-*************************************************-*/
    Route::get('/index/categorie', [CategorieController::class, 'index']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/categorie', [CategorieController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/categorie/{id}', [CategorieController::class, 'edit']);
        Route::put('/update/categorie/{id}', [CategorieController::class, 'update']);
        Route::delete('/destroy/categorie/{id}', [CategorieController::class, 'destroy']);
    });

    //--------------- Transfert ------------------
    /* Route::get('/index/transfert/gasoil', [GasoilTransfertController::class, 'index']);
    Route::get('/create/transfert/gasoil', [GasoilTransfertController::class, 'create']);
    Route::post('/store/transfert/gasoil', [GasoilTransfertController::class, 'store']);
    Route::get('/edit/transfert/gasoil/{id}', [GasoilTransfertController::class, 'show']);
    Route::put('/update/transfert/gasoil/{id}', [GasoilTransfertController::class, 'update']);
    Route::delete('/destroy/transfert/gasoil/{id}', [GasoilTransfertController::class, 'destroy']); */

    /*-*************************************************-*/
    /*                       HUILE                       */
    /*-*************************************************-*/

    //--------------- Versement ------------------
    Route::get('/index/versement', [HuileVersementController::class, 'index']);
    Route::get('/create/versement', [HuileVersementController::class, 'create']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/versement', [HuileVersementController::class, 'store']);
    });
    Route::get('/edit/versement/{id}', [HuileVersementController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/versement/{id}', [HuileVersementController::class, 'update']);
        Route::delete('/destroy/versement/{id}', [HuileVersementController::class, 'destroy']);
    });

    //--------------- Transfert ------------------
    Route::get('/index/transfert/huile', [HuileTransfertController::class, 'index']);
    Route::get('/create/transfert/huile', [HuileTransfertController::class, 'create']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/transfert/huile', [HuileTransfertController::class, 'store']);
    });
    Route::get('/edit/transfert/huile/{id}', [HuileTransfertController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/transfert/huile/{id}', [HuileTransfertController::class, 'update']);
        Route::delete('/destroy/transfert/huile/{id}', [HuileTransfertController::class, 'destroy']);
    });

    //--------------- Vidange ------------------
    Route::get('/index/vidange', [VidangeController::class, 'index']);
    Route::get('/create/vidange', [VidangeController::class, 'create']);
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/store/vidange', [VidangeController::class, 'store']);
    });
    Route::get('/edit/vidange/{id}', [VidangeController::class, 'show']);
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/vidange/{id}', [VidangeController::class, 'update']);
        Route::delete('/destroy/vidange/{id}', [VidangeController::class, 'destroy']);
    });

    /*-*************************************************-*/
    /*                   BON DE COMMANDE                 */
    /*-*************************************************-*/
    Route::get('/index/bon_commande', [Bon_commandeController::class, 'index']);
    Route::get('stock/{article}/{depot}', [Bon_commandeController::class, 'getStock']);
    Route::get('/export/bon_commande', [Bon_commandeController::class, 'exportExcel']);
    Route::middleware(['role:1,4'])->group(function () {
        Route::get('/create/bon_commande', [Bon_commandeController::class, 'create']);
        Route::post('/store/bon_commande', [Bon_commandeController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::get('/edit/bon_commande/{id}', [Bon_commandeController::class, 'edit']);
        Route::put('/update/bon_commande/{id}', [Bon_commandeController::class, 'update']);
        Route::delete('/destroy/bon_commande/{id}', [Bon_commandeController::class, 'destroy']);
    });

    /*-*************************************************-*/
    /*                   BON DE LIVRAISON                */
    /*-*************************************************-*/
    Route::prefix('bon_livraison')->group(function () {
        Route::get('/index', [BonLivraisonController::class, 'index']);
        Route::get('/create', [BonLivraisonController::class, 'create']);
        Route::middleware(['role:1,3'])->group(function () {
            Route::post('/store', [BonLivraisonController::class, 'store']);
            Route::put('/valider/{id}', [BonLivraisonController::class, 'validerBl']);
        });
        Route::get('/show/{id}', [BonLivraisonController::class, 'show']);
        Route::middleware(['role:1'])->group(function () {
            Route::put('/update/{id}', [BonLivraisonController::class, 'update']);
            Route::delete('/destroy/{id}', [BonLivraisonController::class, 'destroy']);
        });
        Route::get('/next-num-for-year', [BonLivraisonController::class, 'getNextNumBLForYear']);
        Route::get('/filter-data', [BonLivraisonController::class, 'filterData']);
        Route::get('/export', [BonLivraisonController::class, 'exportExcel']);
    });

    /*-*************************************************-*/
    /*                CONSOMMATION                        */
    /*-*************************************************-*/
    Route::get('/index/materiels/consommation', [ConsommationGasoilController::class, 'materiel']);
    Route::get('/index/materiels/operation/consommation', [ConsommationGasoilController::class, 'materielConsommation']);
    Route::get('/consommations/graphique/{vehicule}', [ConsommationGasoilController::class, 'consommationGraphique']);
    Route::get('/gasoil/rapport', [ConsommationGasoilController::class, 'rapport']);

    /*-*************************************************-*/
    /*                OPERATION V                        */
    /*-*************************************************-*/
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::get('/create/operation-vehicule', [OperationVehiculeController::class, 'create']);
        Route::post('/store/operation-vehicule', [OperationVehiculeController::class, 'store']);
    });
    Route::middleware(['role:1'])->group(function () {
        Route::put('/update/operation-vehicule/{id}', [OperationVehiculeController::class, 'update']);
    });

    /*-*************************************************-*/
    /*                JOURNEE                            */
    /*-*************************************************-*/
    Route::middleware(['role:1,3,4'])->group(function () {
        Route::post('/pertes-gasoil', [PerteGasoilController::class, 'store']);
        Route::post('/pertes-gasoil/soir', [PerteGasoilController::class, 'storeSoir']);
    });
    Route::get('/journee/{journee}/gasoil', [JourneeController::class, 'gasoilJournee']);
    Route::get('/journee/{journee}/materiel/{materiel}/operations', [JourneeController::class, 'operationsMaterielJournee']);


    // Routes pour la gestion des journées
    Route::prefix('journee')->group(function () {
        Route::get('/statut', [JourneeController::class, 'statut']);
        Route::get('/historique', [JourneeController::class, 'historique']);
        Route::get('/{journee}/gasoil/fin', [JourneeController::class, 'gasoilFin']);
        Route::middleware(['role:1,3,4'])->group(function () {
            Route::post('/demarrer', [JourneeController::class, 'demarrer']);
            Route::post('/{journee}/terminer', [JourneeController::class, 'terminerJournee']);
            Route::post('/reactivate', [JourneeController::class, 'reactiver']);
        });
    });

    /*-*************************************************-*/
    /* 1. FOURNITURE CONSOMMABLE (ENTRÉES / SORTIES)     */
    /* PLACÉ EN PREMIER POUR ÉVITER LE CONFLIT 404       */
    /*-*************************************************-*/

    Route::prefix('fournitures')->group(function () {
        // -- Entrées --
        Route::get('/entrees', [EntreeFournitureController::class, 'index']);
        Route::get('/entrees/create', [EntreeFournitureController::class, 'create']);
        Route::post('/entrees', [EntreeFournitureController::class, 'store']);
        Route::get('/entrees/{id}', [EntreeFournitureController::class, 'show']);
        Route::middleware(['role:1'])->group(function () {
            Route::put('/entrees/{id}', [EntreeFournitureController::class, 'update']);
            Route::delete('/entrees/{id}', [EntreeFournitureController::class, 'destroy']);
        });

        // -- Sorties --
        Route::get('/sorties', [SortieFournitureController::class, 'index']);
        Route::get('/sorties/create', [SortieFournitureController::class, 'create']);
        Route::post('/sorties', [SortieFournitureController::class, 'store']);
        Route::get('/sorties/{id}', [SortieFournitureController::class, 'show']);
        Route::middleware(['role:1'])->group(function () {
            Route::put('/sorties/{id}', [SortieFournitureController::class, 'update']);
            Route::delete('/sorties/{id}', [SortieFournitureController::class, 'destroy']);
        });

        // -- Stocks --
        Route::get('/stocks/fournitures', [StockFournitureController::class, 'index']);
        Route::get('/stocks/fournitures/agreges', [StockFournitureController::class, 'indexAggregated']);

        // Route supprimée : Route::get('/consommable', [FournitureConsommableController::class, 'index']);
    });

    /*-*************************************************-*/
    /*  FOURNITURES CONSOMMABLES (CATALOGUE)             */
    /*-*************************************************-*/
    Route::prefix('fournitures-consommables')->group(function () {
        // Consultables par tous les utilisateurs authentifiés
        Route::get('/', [FournitureConsommableController::class, 'index']);
        Route::get('/create', [FournitureConsommableController::class, 'create']);
        Route::get('/{id}', [FournitureConsommableController::class, 'show']);
        Route::get('/{id}/edit', [FournitureConsommableController::class, 'edit']);

        // Actions d'écriture avec restrictions de rôles
        Route::middleware(['role:1,3,4'])->group(function () {
            Route::post('/', [FournitureConsommableController::class, 'store']);
        });

        Route::middleware(['role:1'])->group(function () {
            Route::put('/{id}', [FournitureConsommableController::class, 'update']);
            Route::delete('/{id}', [FournitureConsommableController::class, 'destroy']);
        });
    });

    /*-*************************************************-*/
    /* 2. FOURNITURES (BLOC D'ORIGINE)                   */
    /*-*************************************************-*/

    Route::prefix('fournitures')->group(function () {
        Route::get('/', [FournitureController::class, 'index']);
        Route::get('/create', [FournitureController::class, 'create']);
        Route::get('/export/fournitures', [FournitureController::class, 'exportExcel']);

        Route::middleware(['role:1,3,4'])->group(function () {
            Route::post('/', [FournitureController::class, 'store']);
            Route::post('/action', [FournitureController::class, 'actionFourniture']);
        });

        // Cette route /{id} capturait "entrees" car elle était placée trop haut
        Route::get('/{id}', [FournitureController::class, 'show']);

        Route::middleware(['role:1'])->group(function () {
            Route::put('/{id}', [FournitureController::class, 'update']);
            Route::delete('/{id}', [FournitureController::class, 'destroy']);
        });
    });

    /*-*************************************************-*/
    /* 3. AUTRES ROUTES DE FOURNITURES                  */
    /*-*************************************************-*/

    Route::prefix('historique-fournitures')->group(function () {
        Route::get('/{fournitureId}', [HistoriqueFournitureController::class, 'show']);
        Route::get('/', [HistoriqueFournitureController::class, 'index']);
    });






    // Route pour mettre à jour une opération d'huile
    Route::put('/huile/{id}', [BonHuileController::class, 'updateHuile']);

    // Import Excel générique depuis l'interface Paramètres
    Route::post('/imports/excel', [ExcelImportController::class, 'store']);
    Route::get('/imports/excel/templates', [ExcelImportController::class, 'templates']);

    /* // Routes pour les données de référence
    Route::get('/materiels', function () {
        return response()->json(Materiel::select('id', 'nom_materiel', 'actuelGasoil', 'capaciteL', )->orderBy('nom_materiel')->get());
    }); */

    Route::get('/subdivisions', function () {
        return response()->json(Subdivision::select('id', 'nom_subdivision')->orderBy('nom_subdivision')->get());
    });

    Route::get('/article-depots', function () {
        return response()->json(
            ArticleDepot::select('id', 'nom_article')
                ->whereHas('categorie', function ($query) {
                    $query->whereRaw('LOWER(nom_categorie) = ?', ['huile']);
                })
                ->orderBy('nom_article')
                ->get()
        );
    });

    Route::get('/lieu-stockages', function () {
        return response()->json(Lieu_stockage::select('id', 'nom')->orderBy('nom')->get());
    });
});
