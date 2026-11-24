<?php

namespace App\Http\Controllers;

use App\Models\OfflineAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OfflineSyncController extends Controller
{
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
        $request->validate([
            'actions'           => 'required|array|max:50',
            'actions.*.uuid'    => 'required|uuid',
            'actions.*.type'    => 'required|string|in:appel',
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
                $message = match ($action['type']) {
                    'appel' => $this->traiterAppel($user, $action['payload']),
                };

                OfflineAction::create(['uuid' => $uuid, 'user_id' => $user->id, 'type' => $action['type']]);
                $resultats[] = ['uuid' => $uuid, 'status' => 'ok', 'message' => $message];
            } catch (ValidationException $e) {
                // Refus définitif : on explique pourquoi au professeur
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
}
