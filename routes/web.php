<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\AnneeScolaireController;
use App\Http\Controllers\TrimestreController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\EleveApiController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ComportementController;
use App\Http\Controllers\RecapitulatifController;
use App\Http\Controllers\ConseilClasseController;
use App\Http\Controllers\BulletinController;
use App\Http\Controllers\MatiereController;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\RetardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\EcolageController;
use App\Http\Controllers\AppelController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Middleware\VerifierCompte;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Test de vraie connexion (utilisé par le mode hors ligne)
Route::get('/offline/ping', fn () => response()->noContent());

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', VerifierCompte::class])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Mode hors ligne : jeton frais et réception des actions faites sans connexion
    Route::get('/offline/token', [OfflineSyncController::class, 'token']);
    Route::post('/offline/sync', [OfflineSyncController::class, 'sync']);

    // Mon compte : chaque utilisateur modifie son nom, son e-mail et son mot de passe
    Route::get('/mon-compte', [CompteController::class, 'edit'])->name('compte.edit');
    Route::put('/mon-compte', [CompteController::class, 'update'])->name('compte.update');

    Route::get('/', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && $user->hasRole('admin')) {
            return app(ClasseController::class)->index();
        }

        if ($user && $user->hasRole('comptable')) {
            return redirect()->route('eleves.index');
        }

        if ($user && $user->hasRole('professeur')) {
            return redirect()->route('notes.index');
        }

        if ($user && $user->hasRole('surveillant')) {
            return redirect()->route('retards.index');
        }

        abort(403, 'Action non autorisée pour votre profil.');
    })->name('home');

    Route::middleware(['role:admin'])->group(function () {
        Route::post('/classes', [ClasseController::class, 'store'])->name('classes.store');
        Route::get('/classes/{classe}/edit', [ClasseController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{classe}', [ClasseController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{classe}', [ClasseController::class, 'destroy'])->name('classes.destroy');

        Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index'])->name('annees_scolaires.index');
        Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store'])->name('annees_scolaires.store');
        Route::post('/annees-scolaires/{id}/activer', [AnneeScolaireController::class, 'activer'])->name('annees_scolaires.activer');
        Route::get('/annees-scolaires/{annee}/edit', [AnneeScolaireController::class, 'edit'])->name('annees_scolaires.edit');
        Route::put('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'update'])->name('annees_scolaires.update');
        Route::delete('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'destroy'])->name('annees_scolaires.destroy');

        Route::get('/trimestres', [TrimestreController::class, 'index'])->name('trimestres.index');
        Route::post('/trimestres', [TrimestreController::class, 'store'])->name('trimestres.store');
        Route::get('/trimestres/{trimestre}/edit', [TrimestreController::class, 'edit'])->name('trimestres.edit');
        Route::put('/trimestres/{trimestre}', [TrimestreController::class, 'update'])->name('trimestres.update');
        Route::delete('/trimestres/{trimestre}', [TrimestreController::class, 'destroy'])->name('trimestres.destroy');

        Route::get('/comportements', [ComportementController::class, 'indexGlobal'])->name('comportements.index_global');
        Route::post('/comportements', [ComportementController::class, 'storeGlobal'])->name('comportements.store_global');

        // Directeur : choisir une classe puis voir ses statistiques de présence
        Route::get('/statistiques', [PresenceController::class, 'choisirClasse'])->name('presences.statistiques.index');

        // Gestion des comptes par le directeur
        // Tous les bulletins de toute l'école, en un seul clic
        Route::get('/bulletins', [BulletinController::class, 'ecole'])->name('bulletins.ecole');

        Route::get('/utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::post('/utilisateurs', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
        Route::get('/utilisateurs/{utilisateur}/edit', [UtilisateurController::class, 'edit'])->name('utilisateurs.edit');
        Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])->name('utilisateurs.update');
        Route::post('/utilisateurs/{utilisateur}/statut', [UtilisateurController::class, 'basculerStatut'])->name('utilisateurs.statut');
        Route::post('/utilisateurs/{utilisateur}/transferer', [UtilisateurController::class, 'transferer'])->name('utilisateurs.transferer');

        // Collège : matières (liste, ajout, modification, suppression)
        Route::get('/matieres', [MatiereController::class, 'index'])->name('matieres.index');
        Route::post('/matieres', [MatiereController::class, 'store'])->name('matieres.store');
        Route::put('/matieres/{matiere}', [MatiereController::class, 'update'])->name('matieres.update');
        Route::delete('/matieres/{matiere}', [MatiereController::class, 'destroy'])->name('matieres.destroy');

        // Collège : affectation d'un professeur et d'un coefficient à chaque matière d'une classe
        Route::get('/affectations', [AffectationController::class, 'index'])->name('affectations.index');
        Route::post('/affectations', [AffectationController::class, 'store'])->name('affectations.store');
        Route::delete('/affectations/{affectation}', [AffectationController::class, 'destroy'])->name('affectations.destroy');
    });

    // Écolage de base par classe et par année scolaire (comptable et directeur)
    Route::middleware(['role:admin,comptable'])->group(function () {
        Route::get('/ecolage', [EcolageController::class, 'index'])->name('ecolage.index');
        Route::post('/ecolage', [EcolageController::class, 'enregistrer'])->name('ecolage.enregistrer');
    });

    // Surveillant (et directeur) : retards et billets d'entrée
    Route::middleware(['role:admin,surveillant'])->group(function () {
        Route::get('/retards', [RetardController::class, 'index'])->name('retards.index');
        Route::post('/retards', [RetardController::class, 'store'])->name('retards.store');
        Route::get('/retards/{retard}/billet', [RetardController::class, 'billet'])->name('retards.billet');
        Route::delete('/retards/{retard}', [RetardController::class, 'destroy'])->name('retards.destroy');

        // Permissions : sorties pendant les cours et absences autorisées
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}/billet', [PermissionController::class, 'billet'])->name('permissions.billet');
        Route::post('/permissions/{permission}/retour', [PermissionController::class, 'retour'])->name('permissions.retour');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    });

    Route::middleware(['role:admin,comptable'])->group(function () {
        Route::get('/eleves', function () {
            return view('eleves.react');
        })->name('eleves.index');

        Route::prefix('api')->group(function () {
            Route::get('/eleves', [EleveApiController::class, 'index']);
            Route::post('/eleves', [EleveApiController::class, 'store']);
            Route::put('/eleves/{id}', [EleveApiController::class, 'update']);
            Route::delete('/eleves/{id}', [EleveApiController::class, 'destroy']);
        });
    });

    Route::middleware(['role:comptable'])->group(function () {
        Route::get('/paiements', [PaiementController::class, 'index'])->name('paiements.index');
        Route::post('/paiements', [PaiementController::class, 'store'])->name('paiements.store');

        Route::get('/paiements/{id}/edit', [PaiementController::class, 'edit'])->name('paiements.edit');
        Route::put('/paiements/{id}', [PaiementController::class, 'update'])->name('paiements.update');

        Route::get('/depenses', [DepenseController::class, 'index'])->name('depenses.index');
        Route::post('/depenses', [DepenseController::class, 'store'])->name('depenses.store');
    });

    Route::middleware(['role:professeur'])->group(function () {
        // Saisie des notes : le professeur ne voit que ses classes et ses matières (affectations)
        Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
        Route::get('/notes/saisie', [NoteController::class, 'saisie'])->name('notes.saisie');
        Route::post('/notes/saisie', [NoteController::class, 'enregistrer'])->name('notes.enregistrer');

        // Retrouve automatiquement la classe dont le professeur est titulaire
        Route::get('/ma-classe/appel', function () {
            $classe = Classe::where('professeur_id', Auth::id())->firstOrFail();
            return redirect()->route('presences.appel', $classe);
        })->name('presences.ma_classe');

        Route::get('/ma-classe/comportements', function () {
            $classe = Classe::where('professeur_id', Auth::id())->firstOrFail();
            return redirect()->route('comportements.index', $classe);
        })->name('comportements.ma_classe');

        Route::get('/ma-classe/statistiques', function () {
            $classe = Classe::where('professeur_id', Auth::id())->firstOrFail();
            return redirect()->route('presences.statistiques', $classe);
        })->name('presences.statistiques.ma_classe');
    });

    Route::middleware(['role:admin,professeur'])->group(function () {
        Route::get('/classes/{classe}/appel', [PresenceController::class, 'appel'])->name('presences.appel');
        Route::post('/classes/{classe}/appel', [PresenceController::class, 'enregistrer'])->name('presences.enregistrer');

        Route::get('/classes/{classe}/comportements', [ComportementController::class, 'index'])->name('comportements.index');
        Route::post('/classes/{classe}/comportements', [ComportementController::class, 'store'])->name('comportements.store');

        Route::get('/classes/{classe}/statistiques', [PresenceController::class, 'statistiquesClasse'])->name('presences.statistiques');

        // Résultats de la classe (moyennes, rangs) : admin et professeur titulaire de la classe
        Route::get('/classes/{classe}/recapitulatif', [RecapitulatifController::class, 'show'])->name('recapitulatif.show');

        // Appel de chaque heure de cours par les professeurs, et synthèse des absences de la classe
        Route::get('/appels', [AppelController::class, 'index'])->name('appels.index');
        Route::post('/appels', [AppelController::class, 'enregistrer'])->name('appels.enregistrer');
        Route::get('/classes/{classe}/absences', [AppelController::class, 'synthese'])->name('appels.synthese');
        Route::post('/absences-heures/{absence}/justifier', [AppelController::class, 'basculerJustification'])->name('appels.justifier');

        // Conseil de classe : mentions, observation et décision pour chaque élève
        Route::get('/classes/{classe}/conseil', [ConseilClasseController::class, 'show'])->name('conseil.show');
        Route::post('/classes/{classe}/conseil', [ConseilClasseController::class, 'store'])->name('conseil.store');

        // Bulletins imprimables : toute la classe, ou un seul élève
        Route::get('/classes/{classe}/bulletins', [BulletinController::class, 'classe'])->name('bulletins.classe');
        Route::get('/classes/{classe}/bulletins/{eleve}', [BulletinController::class, 'eleve'])->name('bulletins.eleve');
    });
});
