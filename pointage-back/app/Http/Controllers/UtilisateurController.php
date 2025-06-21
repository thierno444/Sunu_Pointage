<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use App\Models\Journal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\Utilisateur\CreateUtilisateurRequest;
use App\Http\Requests\Utilisateur\UpdateUtilisateurRequest;
use App\Http\Requests\Utilisateur\ImportRequest;
use App\Models\Cohorte;
use App\Models\Departement;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;


/**
 * Gère les opérations CRUD et autres fonctionnalités liées aux utilisateurs
 */
class UtilisateurController extends Controller
{
    /**
     * Récupère la liste des utilisateurs avec filtrage optionnel par type
     * @param Request $request Requête HTTP contenant les paramètres de filtrage
     * @return \Illuminate\Http\JsonResponse Liste des utilisateurs filtrée
     */
    public function index(Request $request)
    {
        try {
            $query = Utilisateur::query()
                ->with(['departement', 'cohorte']);

            //Déclaration
                $comptage = [];

            // Ajout du filtre par type
            if ($request->query('type')) {
                $query->where('type', $request->query('type'));
            }
            // Ajout du filtre par département
            if ($request->query('departement_id')) {
                $query->where('departement_id', $request->query('departement_id'));
                $comptage = [
                    'departement' => [
                        'total_employes' => $query->clone()->where('type', 'employe')->count(),
                        'nom_departement' => Departement::find($request->query('departement_id'))->nom
                    ]
                ];
            }
            // Ajout du filtre par cohorte
            if ($request->query('cohorte_id')) {
                $query->where('cohorte_id', $request->query('cohorte_id'));
                $comptage = [
                    'cohorte' => [
                        'total_apprenants' => $query->clone()->where('type', 'apprenant')->count(),
                        'nom_cohorte' => Cohorte::find($request->query('cohorte_id'))->nom
                    ]
                ];
            }


            $utilisateurs = $query->get();

            // Log de consultation de la liste
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'consultation_liste_utilisateurs',
                'details' => [
                    'type_filtre' => $request->query('type'),
                    'nombre_resultats' => $utilisateurs->count(),
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'statistiques' => $comptage,
                'data' => $utilisateurs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Crée un nouvel utilisateur
     * @param CreateUtilisateurRequest $request Requête validée de création
     * @return \Illuminate\Http\JsonResponse Utilisateur créé
     */
    public function store(CreateUtilisateurRequest $request)
    {
        try {
            $data = $request->validated();

            if (isset($data['photo']) && $data['photo']) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            $data['password'] = Hash::make($data['password']);
            unset($data['password']);

            $utilisateur = Utilisateur::create($data);

            // Log de création
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'creation_utilisateur',
                'details' => [
                    'utilisateur_id' => $utilisateur->id,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $utilisateur
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
    /**
     * Récupère les détails d'un utilisateur spécifique
     * @param string $id Identifiant de l'utilisateur
     * @return \Illuminate\Http\JsonResponse Détails de l'utilisateur
     */
    public function show($id)
    {
        try {
            $utilisateur = Utilisateur::with(['departement', 'cohorte', 'pointages'])
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $utilisateur
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Met à jour un utilisateur existant
     * @param UpdateUtilisateurRequest $request Requête validée de mise à jour
     * @param string $id Identifiant de l'utilisateur
     * @return \Illuminate\Http\JsonResponse Utilisateur mis à jour
     */
    

 public function update(UpdateUtilisateurRequest $request, $id)
{
    try {
        logger('=== DÉBUT UPDATE CONTROLLER ===');
        logger('Content-Type: ' . $request->header('Content-Type'));
        logger('Method: ' . $request->method());
        logger('Has file photo: ' . ($request->hasFile('photo') ? 'OUI' : 'NON'));
        logger('All input keys: ', array_keys($request->all()));
        logger('Request has _method: ' . ($request->has('_method') ? $request->get('_method') : 'NON'));
        
        // 🔧 Vérifier si l'utilisateur existe
        $utilisateur = Utilisateur::find($id);
        if (!$utilisateur) {
            logger('❌ Utilisateur non trouvé avec ID: ' . $id);
            return response()->json([
                'status' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
        
        logger('✅ Utilisateur trouvé: ' . $utilisateur->nom);
        
        // 🔧 Récupérer les données validées
        $data = $request->validated();
        logger('Data validées reçues: ', array_keys($data));
        
        // 🔧 Si pas de données validées, essayer de récupérer manuellement
        if (empty($data)) {
            logger('⚠️ Aucune donnée validée, récupération manuelle...');
            $data = $request->except(['_method', '_token']);
            logger('Data manuelles: ', array_keys($data));
        }
        
        // 🔧 Gestion de la photo avec plus de sécurité
        if ($request->hasFile('photo')) {
            logger('📸 Traitement de la photo...');
            
            $photo = $request->file('photo');
            logger('Photo info: ', [
                'name' => $photo->getClientOriginalName(),
                'size' => $photo->getSize(),
                'type' => $photo->getMimeType(),
                'valid' => $photo->isValid(),
                'path' => $photo->path()
            ]);
            
            // Vérifications de sécurité
            if (!$photo->isValid()) {
                logger('❌ Photo invalide');
                return response()->json([
                    'status' => false,
                    'message' => 'Photo invalide'
                ], 400);
            }
            
            if ($photo->getSize() > 2048000) { // 2MB
                logger('❌ Photo trop volumineuse: ' . $photo->getSize());
                return response()->json([
                    'status' => false,
                    'message' => 'Photo trop volumineuse (max 2MB)'
                ], 400);
            }
            
            // Vérifier le type MIME
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!in_array($photo->getMimeType(), $allowedTypes)) {
                logger('❌ Type de fichier non autorisé: ' . $photo->getMimeType());
                return response()->json([
                    'status' => false,
                    'message' => 'Type de fichier non autorisé'
                ], 400);
            }
            
            // Encodage en base64
            $photoPath = $photo->path();
            logger('Photo path: ' . $photoPath);
            
            if (!file_exists($photoPath)) {
                logger('❌ Fichier photo introuvable: ' . $photoPath);
                return response()->json([
                    'status' => false,
                    'message' => 'Erreur lors du traitement de la photo'
                ], 500);
            }
            
            $photoContent = file_get_contents($photoPath);
            if ($photoContent === false) {
                logger('❌ Impossible de lire le contenu de la photo');
                return response()->json([
                    'status' => false,
                    'message' => 'Erreur lors de la lecture de la photo'
                ], 500);
            }
            
            $data['photo'] = [
                'data' => base64_encode($photoContent),
                'mime_type' => $photo->getMimeType(),
                'name' => $photo->getClientOriginalName()
            ];
            
            logger('✅ Photo encodée, taille base64: ' . strlen($data['photo']['data']));
        }

        // 🔧 Gestion du mot de passe
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
            logger('🔐 Mot de passe crypté');
        } else {
            unset($data['password']);
            logger('🔐 Pas de mot de passe');
        }

        logger('📝 Données finales à sauvegarder: ', array_keys($data));
        
        // 🔧 Sauvegarde avec gestion d'erreur
        $originalData = $utilisateur->toArray();
        
        try {
            $utilisateur->update($data);
            logger('✅ Update MongoDB réussi');
        } catch (\Exception $updateException) {
            logger('❌ Erreur update MongoDB: ' . $updateException->getMessage());
            logger('Stack trace: ' . $updateException->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Erreur lors de la mise à jour en base de données: ' . $updateException->getMessage()
            ], 500);
        }

        // 🔧 Journal des modifications (VERSION CORRIGÉE)
        try {
            // Créer une version safe pour la comparaison
            $dataSafeForComparison = $data;
            $originalSafeForComparison = $originalData;
            
            // Exclure la photo de la comparaison car elle a des formats différents
            if (isset($dataSafeForComparison['photo'])) {
                unset($dataSafeForComparison['photo']);
            }
            if (isset($originalSafeForComparison['photo'])) {
                unset($originalSafeForComparison['photo']);
            }
            
            // Comparer seulement les champs texte
            $modificationsTexte = array_diff_assoc($dataSafeForComparison, $originalSafeForComparison);
            
            // Créer la liste finale des modifications
            $modifications = array_keys($modificationsTexte);
            
            // Ajouter la photo aux modifications si elle existe
            if (isset($data['photo'])) {
                $modifications[] = 'photo';
            }
            
            logger('📝 Modifications détectées: ', $modifications);
            
            if (!empty($modifications)) {
                Journal::create([
                    'user_id' => Auth::id(),
                    'action' => 'modification_utilisateur',
                    'details' => [
                        'utilisateur_id' => $utilisateur->_id,
                        'modifications' => $modifications,
                        'timestamp' => now()
                    ]
                ]);
                logger('📄 Journal créé');
            }
        } catch (\Exception $journalException) {
            logger('⚠️ Erreur journal (non bloquante): ' . $journalException->getMessage());
        }

        // 🔧 Préparer la réponse
        $utilisateurFresh = $utilisateur->fresh();
        
        if ($utilisateurFresh->photo && is_array($utilisateurFresh->photo)) {
            $utilisateurFresh->photo = [
                'url' => 'data:' . $utilisateurFresh->photo['mime_type'] . ';base64,' . $utilisateurFresh->photo['data'],
                'name' => $utilisateurFresh->photo['name']
            ];
            logger('🖼️ Photo transformée pour réponse');
        } else {
            logger('📷 Pas de photo ou format incorrect: ', [$utilisateurFresh->photo]);
        }

        logger('✅ === FIN UPDATE CONTROLLER SUCCÈS ===');

        return response()->json([
            'status' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $utilisateurFresh
        ]);
        
    } catch (\Throwable $e) {
        logger('💥 ERREUR CRITIQUE: ' . $e->getMessage());
        logger('Fichier: ' . $e->getFile() . ':' . $e->getLine());
        logger('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'status' => false,
            'message' => 'Erreur serveur: ' . $e->getMessage(),
            'debug' => config('app.debug') ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ] : null
        ], 500);
    }
}

    /**
     * Supprime un utilisateur
     * @param string $id Identifiant de l'utilisateur
     * @return \Illuminate\Http\JsonResponse Message de confirmation
     */
    public function destroy($id)
    {
        try {
            $utilisateur = Utilisateur::findOrFail($id);

            if ($utilisateur->photo) {
                Storage::delete($utilisateur->photo);
            }

            $utilisateur->delete();

            // Log de suppression
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'suppression_utilisateur',
                'details' => [
                    'utilisateur_id' => $utilisateur->id,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }


    /**
     * Importe des utilisateurs depuis un fichier CSV
     * @param ImportRequest $request Requête contenant le fichier CSV
     * @return \Illuminate\Http\JsonResponse Résultat de l'importation
     */
    public function importCohorte(ImportRequest $request,  $cohorte = null)
    {
        try {
            $importedUsers = [];
            $errors = [];

            $csvData = array_map('str_getcsv', file($request->file('file')->getPathname()));
            $headers = array_shift($csvData);

            // Validation de l'en-tête (Important)
            $expectedHeaders = [ // Ajustez selon vos besoins
                'nom', 'prenom', 'email', 'telephone', 'photo',
                'matricule',  'adresse',
            ];

            if (array_diff($expectedHeaders, $headers) || array_diff($headers, $expectedHeaders)) {
                return response()->json(['status' => false, 'message' => 'En-tête CSV invalide. Les colonnes attendues sont : ' . implode(', ', $expectedHeaders)], 400);
            }

            foreach ($csvData as $key => $row) {
                try {
                    $userData = array_combine($headers, $row);

                    // Validation des données (Crucial)
                    $validator = Validator::make($userData, [
                        'nom' => 'required|string|max:255',
                        'prenom' => 'required|string|max:255',
                        'email' => 'required|email|unique:utilisateurs,email',
                        'telephone' => 'nullable|string|max:20',
                        'photo' => 'nullable|string|max:255',
                        'matricule' => 'nullable|string|max:255',
                        'adresse' => 'nullable|string|max:255',


                    ]);


                    if ($validator->fails()) {
                        $errors[] = "Ligne " . ($key + 2) . ": " . $validator->errors()->first();
                        continue; // Passe à la ligne suivante
                    }
                    $userData['password'] = Hash::make($userData['password'] ?? 'password123');

                    if($cohorte){
                        $userData['cohorte_id'] = $cohorte;
                        $userData['type'] = 'apprenant';
                    }
                    $user = Utilisateur::create($userData);
                    $importedUsers[] = $user;

                    Journal::create([
                        'user_id' => Auth::id(),
                        'action' => 'import_utilisateur',
                        'details' => [
                            'utilisateur_id' => $user->id,
                            'source' => 'import_csv',
                            'timestamp' => now(),
                            'row_data' => $userData
                        ]
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Ligne " . ($key + 2) . ": " . $e->getMessage();
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Importation réussie',
                'data' => [
                    'imported' => $importedUsers,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function import(ImportRequest $request, $departement = null )
{
    try {
        $importedUsers = [];
        $errors = [];

        $csvData = array_map('str_getcsv', file($request->file('file')->getPathname()));
        $headers = array_shift($csvData);

        // Validation de l'en-tête (Important)
        $expectedHeaders = [ // Ajustez selon vos besoins
            'nom', 'prenom', 'email', 'password', 'telephone', 'photo',
            'matricule',  'adresse', 'fonction',
        ];

        if (array_diff($expectedHeaders, $headers) || array_diff($headers, $expectedHeaders)) {
            return response()->json(['status' => false, 'message' => 'En-tête CSV invalide. Les colonnes attendues sont : ' . implode(', ', $expectedHeaders)], 400);
        }

        foreach ($csvData as $key => $row) {
            try {
                $userData = array_combine($headers, $row);

                // Validation des données (Crucial)
                $validator = Validator::make($userData, [
                    'nom' => 'required|string|max:255',
                    'prenom' => 'required|string|max:255',
                    'email' => 'required|email|unique:utilisateurs,email',
                    'password' => 'nullable|string|min:8',
                    'telephone' => 'nullable|string|max:20',
                    'photo' => 'nullable|string|max:255',
                    'matricule' => 'nullable|string|max:255',
                    'adresse' => 'nullable|string|max:255',
                    'fonction' => 'nullable|string|max:255',

                ]);


                if ($validator->fails()) {
                    $errors[] = "Ligne " . ($key + 2) . ": " . $validator->errors()->first();
                    continue; // Passe à la ligne suivante
                }
                $userData['password'] = Hash::make($userData['password'] ?? 'password123');
                if($departement) {
                    $userData['departement_id'] = $departement;
                    $userData['type'] = 'employe';
                }

                $user = Utilisateur::create($userData);
                $importedUsers[] = $user;

                Journal::create([
                    'user_id' => Auth::id(),
                    'action' => 'import_utilisateur',
                    'details' => [
                        'utilisateur_id' => $user->id,
                        'source' => 'import_csv',
                        'timestamp' => now(),
                        'row_data' => $userData
                    ]
                ]);
            } catch (\Exception $e) {
                $errors[] = "Ligne " . ($key + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Importation réussie',
            'data' => [
                'imported' => $importedUsers,
                'errors' => $errors
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], $e->getCode() ?: 500);
    }
}

    /**
     * Assigne une carte RFID à un utilisateur
     * @param Request $request Requête contenant l'ID de la carte
     * @param string $id Identifiant de l'utilisateur
     * @return \Illuminate\Http\JsonResponse Utilisateur avec carte assignée
     */
    public function assignCard(Request $request, $id)
    {
        try {
            $utilisateur = Utilisateur::findOrFail($id);

            if (Utilisateur::where('cardId', $request->cardId)->exists()) {
                throw new \Exception('Cette carte est déjà assignée à un autre utilisateur', 400);
            }

            $utilisateur->cardId = $request->cardId;
            $utilisateur->save();

            // Log d'assignation de carte
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'assignation_carte',
                'details' => [
                    'utilisateur_id' => $utilisateur->id,
                    'card_id' => $request->cardId,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Carte assignée avec succès',
                'data' => $utilisateur
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Vérifie la validité d'une carte RFID
     * @param Request $request Requête contenant l'ID de la carte
     * @return \Illuminate\Http\JsonResponse Statut de la carte et informations utilisateur
     */
    public function verifyCard(Request $request)
    {
        try {
            // Validation de la requête
            $request->validate([
                'cardId' => 'required|string'
            ], [
                'cardId.required' => 'L\'identifiant de la carte est requis',
                'cardId.string' => 'L\'identifiant de la carte doit être une chaîne de caractères'
            ]);

            // Vérification de l'existence de la carte
            $utilisateur = Utilisateur::where('cardId', $request->cardId)->first();

            if (!$utilisateur) {
                throw new \Exception('Carte non reconnue dans le système', 403);
            }

            // Vérification du statut de l'utilisateur
            if ($utilisateur->statut !== 'actif') {
                // Log de tentative d'accès avec carte inactive
                Journal::create([
                    'user_id' => $utilisateur->id,
                    'action' => 'verification_carte',
                    'details' => [
                        'cardId' => $request->cardId,
                        'timestamp' => now(),
                        'success' => false,
                        'raison' => 'carte_inactive'
                    ]
                ]);

                throw new \Exception('Accès refusé : Carte désactivée', 403);
            }

            // Chargement des relations après vérifications
            $utilisateur->load(['departement', 'cohorte']);

            // Log de succès
            Journal::create([
                'user_id' => $utilisateur->id,
                'action' => 'verification_carte',
                'details' => [
                    'cardId' => $request->cardId,
                    'timestamp' => now(),
                    'success' => true,
                    'departement' => $utilisateur->departement ? $utilisateur->departement->nom : null,
                    'cohorte' => $utilisateur->cohorte ? $utilisateur->cohorte->nom : null
                ]
            ]);

            // Réponse de succès
            return response()->json([
                'status' => true,
                'message' => 'Carte valide - Accès autorisé',
                'data' => [
                    'utilisateur' => [
                        'id' => $utilisateur->id,
                        'nom' => $utilisateur->nom,
                        'prenom' => $utilisateur->prenom,
                        'email' => $utilisateur->email,
                        'type' => $utilisateur->type,
                        'departement' => $utilisateur->departement,
                        'cohorte' => $utilisateur->cohorte
                    ],
                    'access' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            // Erreur de validation
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'access' => false,
                    'errors' => $e->errors()
                ]
            ], 422);

        } catch (\Exception $e) {
            // Log d'erreur
            if (isset($utilisateur)) {
                Journal::create([
                    'user_id' => $utilisateur->id,
                    'action' => 'verification_carte',
                    'details' => [
                        'cardId' => $request->cardId,
                        'timestamp' => now(),
                        'success' => false,
                        'error' => $e->getMessage()
                    ]
                ]);
            }

            // Réponse d'erreur
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'access' => false
                ]
            ], $e->getCode() ?: 403);
        }
    }


    /**
     * Met à jour le profil de l'utilisateur connecté
     * @param Request $request Données de mise à jour du profil
     * @return \Illuminate\Http\JsonResponse Profil mis à jour
     */
    public function updateProfile(UpdateUtilisateurRequest $request)
    {
        try {
            $utilisateur = Utilisateur::findOrFail(Auth::user()->_id);
            $data = $request->validated();

            if (isset($data['photo']) && $data['photo']) {
                if ($utilisateur->photo) {
                    Storage::delete($utilisateur->photo);
                }
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                unset($data['password']);
            }

            $utilisateur->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $utilisateur->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Met à jour le statut de plusieurs utilisateurs en même temps
     * @param Request $request Requête contenant les IDs et le nouveau statut
     * @return \Illuminate\Http\JsonResponse Nombre d'utilisateurs mis à jour
     */
    public function bulkStatusUpdate(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:utilisateurs,_id',
                'statut' => 'required|in:actif,inactif'
            ]);

            $count = Utilisateur::whereIn('_id', $request->ids)
                ->update(['statut' => $request->statut]);

            // Log de mise à jour massive des statuts
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'mise_a_jour_massive_statuts',
                'details' => [
                    'utilisateurs_ids' => $request->ids,
                    'nouveau_statut' => $request->statut,
                    'nombre_utilisateurs' => $count,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => "$count Utilisateurs Inactifs",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
    /**
     * Supprime plusieurs utilisateurs en même temps
     * @param Request $request Requête contenant les IDs des utilisateurs à supprimer
     * @return \Illuminate\Http\JsonResponse Message de confirmation
     */
    public function bulkDestroy(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:utilisateurs,_id'
            ]);

            // Récupérer les infos des utilisateurs avant suppression pour le log
            $utilisateurs = Utilisateur::whereIn('_id', $request->ids)->get();

            // Supprimer les photos si elles existent
            foreach($utilisateurs as $utilisateur) {
                if ($utilisateur->photo) {
                    Storage::delete($utilisateur->photo);
                }
            }

            // Supprimer les utilisateurs
            $deletedCount = Utilisateur::whereIn('_id', $request->ids)->delete();

            // Log de suppression massive
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'suppression_massive_utilisateurs',
                'details' => [
                    'utilisateurs_ids' => $request->ids,
                    'nombre_supprimes' => $deletedCount,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Utilisateurs supprimés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
    /**
     * Active/Désactive plusieurs utilisateurs en même temps
     * @param Request $request Requête contenant les IDs des utilisateurs
     * @return \Illuminate\Http\JsonResponse Message de confirmation
     */
    public function bulkToggleStatus(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:utilisateurs,_id'
            ]);

            $utilisateurs = Utilisateur::whereIn('_id', $request->ids)->get();
            $statusChanges = [];

            foreach($utilisateurs as $utilisateur) {
                $oldStatus = $utilisateur->statut;
                $utilisateur->statut = $utilisateur->statut === 'actif' ? 'inactif' : 'actif';
                $utilisateur->save();

                $statusChanges[] = [
                    'utilisateur_id' => $utilisateur->id,
                    'ancien_statut' => $oldStatus,
                    'nouveau_statut' => $utilisateur->statut
                ];
            }

            // Log des changements de statut en masse
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'toggle_statut_masse',
                'details' => [
                    'modifications' => $statusChanges,
                    'nombre_utilisateurs' => count($utilisateurs),
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Statuts modifiés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Méthode utilitaire pour upload de photo
     * @param UploadedFile $photo
     * @return string Chemin de la photo enregistrée
     */
    private function uploadPhoto($photo)
    {
        $filename = Str::random(32) . '.' . $photo->getClientOriginalExtension();
        $path = $photo->storeAs('photos', $filename, 'public');
        return $path;
    }



    /**
     * Active/Désactive un utilisateur
     * @param Request $request Requête contenant les IDs des utilisateurs
     * @return \Illuminate\Http\JsonResponse Message de confirmation
     */
    public function toggleStatus($id)
    {
        try {
            $utilisateur = Utilisateur::findOrFail($id);

            // Sauvegarde l'ancien statut
            $oldStatus = $utilisateur->statut;

            // Bascule le statut
            $utilisateur->statut = ($utilisateur->statut === 'actif') ? 'inactif' : 'actif';
            $utilisateur->save();

            // Journalisation
            Journal::create([
                'user_id' => Auth::id(),
                'action' => 'toggle_statut',
                'details' => [
                    'utilisateur_id' => $id,
                    'ancien_statut' => $oldStatus,
                    'nouveau_statut' => $utilisateur->statut,
                    'timestamp' => now()
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Statut modifié avec succès',
                'data' => $utilisateur
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function countEmployes()
    {
        // Compter les utilisateurs dont le type est 'employe'
        $count = Utilisateur::where('type', 'employe')->count();

        return response()->json([
            'count' => $count
        ]);
    }



    public function countApprenants()
    {
        // Compter les utilisateurs dont le type est 'employe'
        $count = Utilisateur::where('type', 'apprenant')->count();

        return response()->json([
            'count' => $count
        ]);
    }

}
