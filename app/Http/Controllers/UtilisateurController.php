<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UtilisateurController extends Controller
{
    public function index()
    {
        $ordre = ['admin', 'professeur', 'comptable', 'surveillant'];

        $utilisateurs = User::withCount(['affectations', 'classesTitulaire'])
            ->get()
            ->sortBy(fn ($u) => (array_search($u->role, $ordre) === false ? 9 : array_search($u->role, $ordre)) . '-' . $u->name)
            ->values();

        return view('utilisateurs.index', compact('utilisateurs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(array_keys(User::ROLES_ATTRIBUABLES))],
            'password' => ['required', Password::min(8)],
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'password.min' => 'Le mot de passe provisoire doit contenir au moins 8 caractères.',
        ]);

        User::create($data + ['actif' => true, 'doit_changer_mot_de_passe' => true]);

        return redirect()->route('utilisateurs.index')->with('success',
            "Compte créé pour {$data['name']}. Communique-lui son identifiant ({$data['email']}) et son mot de passe provisoire : "
            . 'il devra en choisir un nouveau à sa première connexion.'
        );
    }

    public function edit(User $utilisateur)
    {
        $autresProfesseurs = User::where('role', 'professeur')
            ->where('actif', true)
            ->where('id', '!=', $utilisateur->id)
            ->orderBy('name')
            ->get();

        $utilisateur->loadCount(['affectations', 'classesTitulaire']);

        return view('utilisateurs.edit', compact('utilisateur', 'autresProfesseurs'));
    }

    public function update(Request $request, User $utilisateur)
    {
        $estMoi = $utilisateur->id == Auth::id();

        $regles = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'password' => ['nullable', Password::min(8)],
        ];

        // Le rôle d'un administrateur ne se change pas ici
        if (!$utilisateur->hasRole('admin')) {
            $regles['role'] = ['required', Rule::in(array_keys(User::ROLES_ATTRIBUABLES))];
        }

        $data = $request->validate($regles, [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'password.min' => 'Le mot de passe provisoire doit contenir au moins 8 caractères.',
        ]);

        $utilisateur->name = $data['name'];
        $utilisateur->email = $data['email'];

        if (isset($data['role'])) {
            $utilisateur->role = $data['role'];
        }

        $message = 'Compte modifié avec succès.';

        // Réinitialisation du mot de passe (pas pour soi-même : on utilise « Mon compte »)
        if (!empty($data['password']) && !$estMoi) {
            $utilisateur->password = $data['password'];
            $utilisateur->doit_changer_mot_de_passe = true;
            $message = 'Compte modifié. Communique-lui son nouveau mot de passe provisoire : il devra le changer à sa prochaine connexion.';
        }

        $utilisateur->save();

        return redirect()->route('utilisateurs.index')->with('success', $message);
    }

    // Désactiver ou réactiver un compte (on ne supprime jamais, pour garder l'historique)
    public function basculerStatut(User $utilisateur)
    {
        if ($utilisateur->id == Auth::id()) {
            return back()->withErrors(['statut' => 'Tu ne peux pas désactiver ton propre compte.']);
        }

        if ($utilisateur->actif && $utilisateur->hasRole('admin')
            && User::where('role', 'admin')->where('actif', true)->count() <= 1) {
            return back()->withErrors(['statut' => 'Impossible de désactiver le dernier administrateur.']);
        }

        $utilisateur->actif = !$utilisateur->actif;
        $utilisateur->save();

        $message = $utilisateur->actif ? "Le compte de {$utilisateur->name} est réactivé." : "Le compte de {$utilisateur->name} est désactivé.";

        if (!$utilisateur->actif && $utilisateur->hasRole('professeur')
            && ($utilisateur->affectations()->exists() || $utilisateur->classesTitulaire()->exists())) {
            $message .= ' Pense à transférer ses matières et ses classes à un autre professeur.';
        }

        return back()->with('success', $message);
    }

    // Remplacement d'un professeur : ses matières et ses classes passent à un autre professeur
    public function transferer(Request $request, User $utilisateur)
    {
        abort_unless($utilisateur->hasRole('professeur'), 404);

        $data = $request->validate([
            'cible_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'professeur')->where('actif', true),
            ],
        ], [
            'cible_id.required' => 'Choisis le professeur qui prend la suite.',
            'cible_id.exists' => 'Ce professeur est introuvable ou désactivé.',
        ]);

        if ((int) $data['cible_id'] === $utilisateur->id) {
            return back()->withErrors(['cible_id' => 'Choisis un autre professeur.']);
        }

        $cible = User::findOrFail($data['cible_id']);
        $nbMatieres = 0;
        $nbClasses = 0;

        DB::transaction(function () use ($utilisateur, $cible, &$nbMatieres, &$nbClasses) {
            $nbMatieres = Affectation::where('professeur_id', $utilisateur->id)->update(['professeur_id' => $cible->id]);
            $nbClasses = Classe::where('professeur_id', $utilisateur->id)->update(['professeur_id' => $cible->id]);
        });

        return redirect()->route('utilisateurs.edit', $utilisateur)->with('success',
            "Transfert terminé : {$nbMatieres} matière(s) et {$nbClasses} classe(s) en tant que titulaire sont maintenant confiées à {$cible->name}."
        );
    }
}