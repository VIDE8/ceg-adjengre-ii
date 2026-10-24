<?php

namespace App\Http\Controllers;

use App\Models\Matiere;
use Illuminate\Http\Request;

class MatiereController extends Controller
{
    public function index()
    {
        $matieres = Matiere::orderBy('nom')->get();

        return view('matieres.index', compact('matieres'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom'    => 'required|string|max:100|unique:matieres,nom',
            'code'   => 'nullable|string|max:10',
            'groupe' => 'required|in:litteraire,scientifique,autre',
        ]);

        Matiere::create($data);

        return redirect()->route('matieres.index')->with('success', 'Matière ajoutée.');
    }

    public function update(Request $request, Matiere $matiere)
    {
        $data = $request->validate([
            'nom'    => 'required|string|max:100|unique:matieres,nom,' . $matiere->id,
            'code'   => 'nullable|string|max:10',
            'groupe' => 'required|in:litteraire,scientifique,autre',
        ]);

        $matiere->update($data);

        return redirect()->route('matieres.index')->with('success', 'Matière modifiée.');
    }

    public function destroy(Matiere $matiere)
    {
        $matiere->delete();

        return redirect()->route('matieres.index')->with('success', 'Matière supprimée.');
    }
}
