<?php

// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


use App\Http\Controllers\CohorteController;

// Route::middleware('jwt.admin')->group(function () {
    Route::group(['prefix' => 'cohortes'], function () {
        Route::get('/', [CohorteController::class, 'index']);
        Route::post('/', [CohorteController::class, 'store']);
        Route::get('/{id}', [CohorteController::class, 'show']);
        Route::put('/{id}', [CohorteController::class, 'update']);
        Route::delete('/{id}', [CohorteController::class, 'destroy']);
        Route::get('/{cohorteId}/apprenants', [CohorteController::class, 'getApprenantsByCohorte']);
    });
// });


//authetification

use App\Http\Controllers\AuthController;
Route::post('register', [AuthController::class, 'register']);


Route::prefix('utilisateurs')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('card', [AuthController::class, 'cardLogin']);


    // Route::middleware('jwt.admin')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        // Route::post('register', [AuthController::class, 'register']);
        Route::post('creerUser', [AuthController::class, 'creerUser']);


    });
    Route::post('logout', [AuthController::class, 'logout']);

// });



///UTilisateurs
use App\Http\Controllers\UtilisateurController;

Route::get('/utilisateurs/verify-card', [UtilisateurController::class, 'verifyCard']);

// Route::middleware('jwt.admin')->group(function () {
    Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
    Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
    Route::get('/utilisateurs/{id}', [UtilisateurController::class, 'show']);
    Route::put('/utilisateurs/{id}', [UtilisateurController::class, 'update']);
    Route::delete('/utilisateurs/{id}', [UtilisateurController::class, 'destroy']);
    // Route::post('/utilisateurs/import', [UtilisateurController::class, 'import']);
    Route::post('/utilisateurs/import/departement/{departement}', [UtilisateurController::class, 'import']);
    Route::post('/utilisateurs/import/cohorte/{cohorte}', [UtilisateurController::class, 'importCohorte']);
    Route::post('/utilisateurs/{id}/assign-card', [UtilisateurController::class, 'assignCard']);
    // Route::get('/utilisateurs/verify-card', [UtilisateurController::class, 'verifyCard']);
    Route::post('/utilisateurs/profile', [UtilisateurController::class, 'updateProfile']);
    Route::post('/utilisateurs/bulk-status-update', [UtilisateurController::class, 'bulkStatusUpdate']);
    Route::post('/utilisateurs/bulk-destroy', [UtilisateurController::class, 'bulkDestroy']);
    Route::post('/utilisateurs/bulk-toggle-status', [UtilisateurController::class, 'bulkToggleStatus']);
    Route::put('/utilisateurs/{id}/toggle-status', [UtilisateurController::class, 'toggleStatus']);
    Route::get('/count-employes', [UtilisateurController::class, 'countEmployes']);
    Route::get('/count-apprenants', [UtilisateurController::class, 'countApprenants']);

// });

//Departement ok
 use App\Http\Controllers\DepartementController;

// Route::middleware('jwt.admin')->group(function () {
    Route::get('/departements', [DepartementController::class, 'index']);
    Route::post('/departements', [DepartementController::class, 'store']);
    Route::get('/departements/{id}', [DepartementController::class, 'show']);
    Route::put('/departements/{id}', [DepartementController::class, 'update']);
    Route::delete('/departements/{id}', [DepartementController::class, 'destroy']);
    Route::get('/{departementId}/employes', [DepartementController::class, 'getEmployesByDepartement']);
//  });

 use App\Http\Controllers\PointageController;

// // // Routes Pointage
// // // Route::prefix('pointages')->group(function () {
// //     // Route publique pour pointer
// //     Route::post('/pointer', [PointageController::class, 'pointer']);

// //     // Routes communes Vigile et Admin
// //     // Route::middleware('jwt.vigile.admin')->group(function () {
// //         Route::get('/', [PointageController::class, 'index']);
// //         Route::get('/historique', [PointageController::class, 'historique']);
// //     // });

// //     // Routes spécifiques Vigile
// //     // Route::middleware('jwt.verifie.vigile')->group(function () {
//     Route::post('/pointages/cartes/{cardId}/valider', [PointageController::class, 'validerPointage']);
// //     // });

// //     // Routes spécifiques Admin
// //     // Route::middleware('jwt.admin')->group(function () {
// //         Route::post('/generer-absences', [PointageController::class, 'genererAbsences']);
// //         Route::put('/{id}', [PointageController::class, 'modifierPointage']);
// //         Route::get('/presences/filtrer', [PointageController::class, 'filtrerPresences']);
// //         Route::get('/presences/recuperer', [PointageController::class, 'recupererPresences']);
// //     // });
// // // });


   // Route de validation par le vigile (avec cardId)
//    Route::post('/pointages/cartes/{cardId}/valider', [PointageController::class, 'validerPointage'])
//    ->middleware('jwt.verifie.vigile');

// Routes Pointage
Route::prefix('pointages')->group(function () {
    // Route publique pour pointer
    Route::post('/pointer', [PointageController::class, 'pointer']);

    // Routes communes Vigile et Admin
    Route::get('/', [PointageController::class, 'index']);
    Route::get('/historique', [PointageController::class, 'historique']);

//     Exemple sans filtre utilisateur ni type :
// json
// Copier
// Modifier
// {
//   "debut": "2024-06-01",
//   "fin": "2024-06-30"
// }
// 💡 Exemple avec tous les filtres :
// json
// Copier
// Modifier
// {
//   "debut": "2024-06-01",
//   "fin": "2024-06-30",
//   "user_id": "666a5d4c6b6a0b17dcb71e2e",
//   "type": "retard",
//   "per_page": 10
// }
// 🔁 Tu peux remplacer "retard" par "absence" selon le test voulu.

    // Routes spécifiques Vigile
    Route::post('/cartes/{cardId}/valider', [PointageController::class, 'validerPointage']);

    // Routes spécifiques Admin
    Route::post('/generer-absences', [PointageController::class, 'genererAbsences']);
    Route::put('/{id}', [PointageController::class, 'modifierPointage']);
    Route::get('/presences/filtrer', [PointageController::class, 'filtrerPresences']);
    Route::get('/presences/recuperer', [PointageController::class, 'recupererPresences']);
});

Route::get('/pointages/jour', [PointageController::class, 'getPointagesJour']);
Route::get('pointages/utilisateurs', [PointageController::class, 'getUtilisateursPointes']);

Route::post('/absences/enregistrer', [PointageController::class, 'enregistrerAbsences']);
Route::get('/absences/verifier', [PointageController::class, 'verifierAbsence']);

// Routes pour les graphiques de pointage
Route::prefix('pointages')->group(function () {
    Route::get('graphique-presences-jour', [PointageController::class, 'graphiquePresencesParJour']);
    Route::get('graphique-presence-globale', [PointageController::class, 'graphiquePresenceGlobale']);
    Route::get('graphique-top-retards', [PointageController::class, 'graphiqueTopRetards']);
});



//oubli mot de passe
use App\Http\Controllers\MailSettingController;

Route::post('/forgot-password', [MailSettingController::class, 'sendPasswordResetLink']);
Route::post('/reset-password', [MailSettingController::class, 'resetPassword']); //tester



use App\Http\Controllers\CongeController;

// Route::middleware(['jwt.admin'])->group(function () {
    Route::prefix('conges')->group(function () {
        // Liste des congés
        Route::get('/', [CongeController::class, 'index']);
        // Détails d'un congé
        Route::get('/{id}', [CongeController::class, 'show']);
        // Créer un nouveau congé
        Route::post('/', [CongeController::class, 'store']);
        // Modifier un congé
        Route::put('/{id}', [CongeController::class, 'update']);
        // Supprimer un congé
        Route::delete('/{id}', [CongeController::class, 'destroy']);
        });
    // });
