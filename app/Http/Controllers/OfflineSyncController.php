<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\EleveApiController;
use App\Models\Eleve;
use App\Models\OfflineAction;
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
        $types = implode(',', array_merge(['appel', 'note', 'eleve'], array_keys(self::FORMULAIRES)));

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
                $message = DB::transaction(function () use ($user, $action, $uuid) {
                    $message = match (true) {
                        $action['type'] === 'appel' => $this->traiterAppel($user, $action['payload']),
                        $action['type'] === 'note'  => $this->traiterNote($user, $action['payload']),
                        $action['type'] === 'eleve' => $this->traiterEleve($user, $action['payload']),
                        default                     => $this->traiterFormulaire($user, $action['type'], $action['payload']),
                    };

                    // L'uuid est noté dans la même transaction que l'action : jamais l'un sans l'autre
                    OfflineAction::create(['uuid' => $uuid, 'user_id' => $user->id, 'type' => $action['type']]);

                    return $message;
                });

                $resultats[] = ['uuid' => $uuid, 'status' => 'ok', 'message' => $message];
            } catch (ValidationException $e) {
                // Refus définitif : on explique pourquoi à l'utilisateur
                $resultats[] = ['uuid' => $uuid, 'status' => 'error', 'message' => collect($e->errors())->flatten()->first()];
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
