<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClasseController extends Controller
{
    // Niveaux du collège, dans l'ordre d'affichage
    private const NIVEAUX = ['6ème', '5ème', '4ème', '3ème'];

    public function index()
    {
        $ordre = array_flip(self::NIVEAUX);

        $classes = Classe::with('professeur')->get()
            ->sortBy(fn($c) => ($ordre[$c->niveau] ?? 99) . '-' . $c->nom_classe)
            ->values();

        return view('classes.index', compact('classes'));
    }

    public function store(Request $request)
    {
        $this->nettoyerNom($request);

        $validated = $request->validate([
            'niveau' => ['required', Rule::in(self::NIVEAUX)],
            'nom_classe' => [
                'required',
                'string',
                'max:20',
                // Interdit de créer deux fois la même classe (ex. deux « 6ème A »)
                Rule::unique('classes', 'nom_classe')->where('niveau', $request->niveau),
            ],
        ], [
            'nom_classe.unique' => 'Cette classe existe déjà (même niveau et même nom).',
        ]);

        Classe::create($validated);

        return redirect()->back()->with('success', 'Classe créée avec succès !');
    }

    public function edit(Classe $classe)
    {
        $professeurs = User::where('role', 'professeur')->orderBy('name')->get();

        return view('classes.edit', compact('classe', 'professeurs'));
    }

    public function update(Request $request, Classe $classe)
    {
        $this->nettoyerNom($request);

        $validated = $request->validate([
            'niveau' => ['required', Rule::in(self::NIVEAUX)],
            'nom_classe' => [
                'required',
                'string',
                'max:20',
                Rule::unique('classes', 'nom_classe')
                    ->where('niveau', $request->niveau)
                    ->ignore($classe->id),
            ],
            // Le professeur titulaire doit avoir le rôle professeur
            'professeur_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', 'professeur'),
            ],
        ], [
            'nom_classe.unique' => 'Cette classe existe déjà (même niveau et même nom).',
        ]);

        $classe->update($validated);

        return redirect()->route('home')->with('success', 'Classe modifiée avec succès !');
    }

    // Suppression d'une classe : refusée tant qu'elle contient des élèves
    public function destroy(Classe $classe)
    {
        $nom = $classe->niveau . ' ' . $classe->nom_classe;
        $nbEleves = Eleve::where('classes_id', $classe->id)->count();

        if ($nbEleves > 0) {
            return redirect()->route('home')->withErrors([
                'suppression' => "Suppression impossible : la classe {$nom} contient {$nbEleves} élève(s). Déplace-les dans une autre classe d'abord.",
            ]);
        }

        $classe->delete(); // ses affectations de matières et son écolage disparaissent avec elle

        return redirect()->route('home')->with('success', "La classe {$nom} a été supprimée.");
    }

    // Enlève les espaces en trop pour que « A », « A  » et « a » comptent comme un doublon
    private function nettoyerNom(Request $request): void
    {
        if ($request->has('nom_classe')) {
            $request->merge([
                'nom_classe' => trim(preg_replace('/\s+/', ' ', (string) $request->nom_classe)),
            ]);
        }
    }
}
