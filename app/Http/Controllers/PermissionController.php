<?php

namespace App\Http\Controllers;

use App\Models\Eleve;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    // Permissions en vigueur un jour donné (aujourd'hui par défaut) + formulaire
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = $request->filled('date') ? Carbon::parse($request->date)->toDateString() : today()->toDateString();

        $permissions = Permission::with(['eleve', 'classe', 'accordeePar'])
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->orderBy('type')
            ->orderByDesc('heure_sortie')
            ->get();

        $eleves = Eleve::with('classe')->orderBy('nom')->orderBy('prenom')->get();

        return view('permissions.index', compact('date', 'permissions', 'eleves'));
    }

    // Accorde la permission puis ouvre le document, prêt à imprimer
    public function store(Request $request)
    {
        $data = $request->validate([
            'eleve_id'            => 'required|exists:eleves,id',
            'type'                => 'required|in:sortie,absence',
            'date_debut'          => 'required|date',
            'date_fin'            => 'nullable|required_if:type,absence|date|after_or_equal:date_debut',
            'heure_sortie'        => 'nullable|required_if:type,sortie|date_format:H:i',
            'heure_retour_prevue' => 'nullable|date_format:H:i',
            'accompagnateur'      => 'nullable|string|max:100',
            'motif'               => 'required|string|max:150',
        ], [
            'eleve_id.required'          => 'Choisis l\'élève dans la liste.',
            'date_fin.required_if'       => 'Indique la date de fin de l\'absence.',
            'date_fin.after_or_equal'    => 'La date de fin doit être égale ou postérieure à la date de début.',
            'heure_sortie.required_if'   => 'Indique l\'heure de sortie.',
            'motif.required'             => 'Indique le motif de la permission.',
        ]);

        $eleve = Eleve::findOrFail($data['eleve_id']);

        if ($data['type'] === 'sortie') {
            $data['date_fin'] = $data['date_debut']; // une sortie dure un seul jour
        } else {
            $data['heure_sortie'] = $data['heure_retour_prevue'] = $data['accompagnateur'] = null;
        }

        $permission = Permission::create($data + [
            'classe_id'    => $eleve->classes_id,
            'accordee_par' => Auth::id(),
        ]);

        return redirect()->route('permissions.billet', ['permission' => $permission->id, 'imprimer' => 1]);
    }

    public function billet(Permission $permission)
    {
        $permission->load(['eleve.classe', 'accordeePar']);

        return view('permissions.billet', compact('permission'));
    }

    // L'élève sorti revient : on note l'heure d'entrée
    public function retour(Permission $permission)
    {
        abort_unless($permission->type === 'sortie', 404);

        $permission->heure_retour = now()->format('H:i');
        $permission->save();

        return back()->with('success', 'Retour enregistré à ' . $permission->heure_retour . '.');
    }

    // Annulation d'une erreur : par celui qui l'a accordée ou par le directeur
    public function destroy(Permission $permission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        abort_unless($user->hasRole('admin') || $permission->accordee_par == $user->id, 403, 'Tu ne peux supprimer que les permissions que tu as accordées.');

        $date = $permission->date_debut->toDateString();
        $permission->delete();

        return redirect()->route('permissions.index', ['date' => $date])->with('success', 'Permission supprimée.');
    }
}