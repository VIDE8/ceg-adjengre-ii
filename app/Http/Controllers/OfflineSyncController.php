<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\EleveApiController;
use App\Models\AbsenceHeure;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\OfflineAction;
use App\Models\Permission;
use App\Services\BulletinService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OfflineSyncController extends Controller
{
    // Formulaires simples du directeur : type => [contrôleur, libellé]
    // Chaque action rejoue la méthode store() du contrôleur, avec les mêmes règles de validation.
    private const FORMULAIRES = [
        'trimestre'   => [TrimestreController::class, 'Trimestre'],
        'annee'       => [AnneeScolaireController::class, 'Année scolaire'],
        'classe'      => [ClasseController::class, 'Classe'],
        'matiere'     => [MatiereController::class, 'Matière'],
        'affectation' => [AffectationController::class, 'Affectation'],
    ];

    // Jeton CSRF frais + identité : le téléphone les demande juste avant d'envoyer sa file d'attente
    public function token(Request $request)
    {
        return response()
            ->json(['token' => csrf_token(), 'user_id' => $request->user()->id])
            ->header('Cache-Control', 'no-store');
    }

    // Reçoit les actions faites sans connexion et les enregistre une par une
    public function sync(Request $request)
    {
        $types = implode(',', array_merge(['appel', 'note', 'eleve', 'retard', 'permission', 'permission_retour', 'presence', 'conseil', 'comportement', 'comportement_global', 'justification'], array_keys(self::FORMULAIRES)));

        $request->validate([
            'actions'           => 'required|array|max:50',
            'actions.*.uuid'    => 'required|uuid',
            'actions.*.type'    => 'required|string|in:' . $types,
            'actions.*.payload' => 'required|array',
        ]);

        $user = $request->user();
        $resultats = [];

        foreach ($request->input('actions') as $action) {
            $uuid = $action['uuid'];

            // Déjà reçue (envoi répété après une coupure) : on ne la refait pas
            if (OfflineAction::where('uuid', $uuid)->exists()) {
                $resultats[] = ['uuid' => $uuid, 'status' => 'duplicate'];
                continue;
            }

            try {
                $retour = DB::transaction(function () use ($user, $action, $uuid) {
                    $retour = match (true) {
                        $action['type'] === 'appel'             => $this->traiterAppel($user, $action['payload']),
                        $action['type'] === 'note'              => $this->traiterNote($user, $action['payload']),
                        $action['type'] === 'eleve'             => $this->traiterEleve($user, $action['payload']),
                        $action['type'] === 'retard'            => $this->traiterRetard($user, $action['payload']),
                        $action['type'] === 'permission'        => $this->traiterPermission($user, $action['payload']),
                        $action['type'] === 'permission_retour' => $this->traiterPermissionRetour($user, $action['payload']),
                        in_array($action['type'], ['presence', 'conseil', 'comportement', 'comportement_global', 'justification'], true)
                                                                => $this->traiterFormulaireClasse($user, $action['type'], $action['payload']),
                        default                                 => $this->traiterFormulaire($user, $action['type'], $action['payload']),
                    };

                    // L'uuid est noté dans la même transaction que l'action : jamais l'un sans l'autre
                    OfflineAction::create(['uuid' => $uuid, 'user_id' => $user->id, 'type' => $action['type']]);

                    return $retour;
                });

                // Une action renvoie un texte, ou un texte + des données utiles au téléphone (ex. adresse du billet)
                $retour = is_array($retour) ? $retour : ['message' => $retour];

                $resultats[] = [
                    'uuid'    => $uuid,
                    'status'  => 'ok',
                    'message' => $retour['message'],
                    'data'    => $retour['data'] ?? null,
                ];
            } catch (ValidationException $e) {
                // Refus définitif : on explique pourquoi à l'utilisateur
                $resultats[] = ['uuid' => $uuid, 'status' => 'error', 'message' => collect($e->errors())->flatten()->first()];
            } catch (ModelNotFoundException $e) {
                // Classe, élève ou absence supprimé entre-temps : inutile de réessayer
                $resultats[] = ['uuid' => $uuid, 'status' => 'error', 'message' => "L'élément concerné n'existe plus (classe, élève ou absence supprimé)."];
            } catch (HttpExceptionInterface | \DomainException $e) {
                $resultats[] = ['uuid' => $uuid, 'status' => 'error', 'message' => $e->getMessage() ?: 'Action refusée.'];
            } catch (\Throwable $e) {
                // Panne côté serveur : on ne perd rien, le téléphone réessaiera
                Log::error('Synchronisation hors ligne : ' . $e->getMessage());
                $resultats[] = ['uuid' => $uuid, 'status' => 'retry'];
            }
        }

        return response()->json(['results' => $resultats]);
    }

    private function traiterAppel($user, array $payload): string
    {
        $donnees = Validator::make($payload, AppelController::regles(), AppelController::messages())->validate();

        $resultat = app(AppelController::class)->appliquer($user, $donnees);

        if ($resultat['erreurs']) {
            throw new \DomainException(implode(' ', $resultat['erreurs']));
        }

        return $resultat['message'];
    }

    private function traiterNote($user, array $payload): string
    {
        $controleur = app(NoteController::class);

        // Accepte la virgule comme séparateur décimal (12,5)
        $payload['notes'] = $controleur->normaliser($payload['notes'] ?? []);

        $donnees = Validator::make($payload, NoteController::regles(), NoteController::messages())->validate();

        $resultat = $controleur->appliquer($user, $donnees);
        $affectation = $resultat['affectation'];

        return 'Notes enregistrées : ' . $affectation->classe->niveau . ' ' . $affectation->classe->nom_classe . ' — ' . $affectation->matiere->nom . '.';
    }

    // Inscription d'un élève : le matricule est attribué ici, à l'arrivée, pour éviter les doublons
    private function traiterEleve($user, array $payload): string
    {
        abort_unless($user->hasRole('admin') || $user->hasRole('comptable'), 403, "Tu n'as pas le droit d'inscrire un élève.");

        $donnees = Validator::make($payload, [
            'nom'            => 'required|string|max:255',
            'prenom'         => 'required|string|max:255',
            'sexe'           => 'required|in:M,F',
            'date_naissance' => 'required|date',
            'classes_id'     => 'required|exists:classes,id',
            'statut'         => 'nullable|string|max:10',
        ])->validate();

        $donnees['matricule'] = EleveApiController::prochainMatricule();

        $eleve = Eleve::create($donnees);

        return "Élève inscrit : {$eleve->nom} {$eleve->prenom} (matricule {$eleve->matricule}).";
    }

    // Retard d'un élève : le billet officiel s'ouvre ensuite à l'adresse renvoyée
    private function traiterRetard($user, array $payload): array
    {
        abort_unless($user->hasRole('admin') || $user->hasRole('surveillant'), 403, "Seul le surveillant peut enregistrer un retard.");

        $donnees = Validator::make($payload, RetardController::regles(), RetardController::messages())->validate();

        $retard = app(RetardController::class)->creer($user, $donnees);

        return [
            'message' => 'Retard enregistré (billet n° ' . $retard->numero . ').',
            'data'    => ['billet_url' => route('retards.billet', ['retard' => $retard->id], false)],
        ];
    }

    private function traiterPermission($user, array $payload): array
    {
        abort_unless($user->hasRole('admin') || $user->hasRole('surveillant'), 403, "Seul le surveillant peut accorder une permission.");

        $donnees = Validator::make($payload, PermissionController::regles(), PermissionController::messages())->validate();

        $permission = app(PermissionController::class)->creer($user, $donnees);

        return [
            'message' => 'Permission accordée (n° ' . $permission->numero . ').',
            'data'    => ['billet_url' => route('permissions.billet', ['permission' => $permission->id], false)],
        ];
    }

    // Retour d'un élève sorti : on garde l'heure réelle du retour, pas l'heure de l'envoi
    private function traiterPermissionRetour($user, array $payload): string
    {
        abort_unless($user->hasRole('admin') || $user->hasRole('surveillant'), 403, "Seul le surveillant peut noter un retour.");

        $donnees = Validator::make($payload, [
            'permission_id' => 'required|exists:permissions,id',
            'heure'         => 'nullable|date_format:H:i',
        ])->validate();

        $permission = Permission::findOrFail($donnees['permission_id']);
        app(PermissionController::class)->enregistrerRetour($permission, $donnees['heure'] ?? null);

        return 'Retour enregistré à ' . $permission->heure_retour . '.';
    }

    // Appel du titulaire, conseil de classe, comportements, justification : on rejoue la méthode du contrôleur
    // concerné. Ses règles de validation et ses droits d'accès (titulaire de la classe...) s'appliquent donc
    // exactement comme sur le site.
    private function traiterFormulaireClasse($user, string $type, array $payload): string
    {
        $requete = Request::create('/', 'POST', $payload['champs'] ?? []);
        $requete->setUserResolver(fn () => $user);

        switch ($type) {
            case 'presence':
                $classe = Classe::findOrFail($payload['classe_id'] ?? 0);
                app(PresenceController::class)->enregistrer($requete, $classe);
                $message = 'Appel de la classe enregistré.';
                break;

            case 'conseil':
                $classe = Classe::findOrFail($payload['classe_id'] ?? 0);
                app(ConseilClasseController::class)->store($requete, $classe, app(BulletinService::class));
                $message = 'Conseil de classe enregistré.';
                break;

            case 'comportement':
                $classe = Classe::findOrFail($payload['classe_id'] ?? 0);
                app(ComportementController::class)->store($requete, $classe);
                $message = 'Comportement enregistré.';
                break;

            case 'comportement_global':
                abort_unless($user->hasRole('admin'), 403, 'Seul le directeur peut faire cette action.');
                app(ComportementController::class)->storeGlobal($requete);
                $message = 'Comportement enregistré.';
                break;

            default: // justification : on inverse l'état, une seule fois grâce à l'uuid de l'action
                $absence = AbsenceHeure::findOrFail($payload['absence_id'] ?? 0);
                app(AppelController::class)->basculerJustification($absence);
                $message = $absence->fresh()->justifie ? 'Absence justifiée.' : 'Justification retirée.';
        }

        // Le contrôleur prépare un message pour la page suivante : il ne doit pas s'afficher par erreur plus tard
        session()->forget(['success', 'error', 'errors']);

        return $message;
    }

    // Formulaires simples : on rejoue la méthode store() du contrôleur concerné
    private function traiterFormulaire($user, string $type, array $payload): string
    {
        abort_unless($user->hasRole('admin'), 403, "Seul le directeur peut faire cette action.");

        [$classe, $libelle] = self::FORMULAIRES[$type];

        $requete = Request::create('/', 'POST', array_map(fn ($v) => $v === '' ? null : $v, $payload));
        $requete->setUserResolver(fn () => $user);

        app($classe)->store($requete);

        // store() prépare un message pour la page suivante : il ne doit pas s'afficher par erreur plus tard
        session()->forget(['success', 'error', 'errors']);

        return $libelle . ' enregistré(e).';
    }
}
