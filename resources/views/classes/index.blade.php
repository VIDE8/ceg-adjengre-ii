@extends('layouts.app')

@section('title', 'Gestion des Classes')

@section('content')
<div class="container my-4">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-success mb-3">Nouvelle Classe</h5>

                    @if(session('success'))
                    <div class="alert alert-success small py-2">{{ session('success') }}</div>
                    @endif

                    @if($errors->any())
                    <div class="alert alert-danger small py-2">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('classes.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Niveau</label>
                            <select name="niveau" class="form-select" required>
                                @foreach(['6ème' => 'Sixième (6ème)', '5ème' => 'Cinquième (5ème)', '4ème' => 'Quatrième (4ème)', '3ème' => 'Troisième (3ème)'] as $valeur => $libelle)
                                <option value="{{ $valeur }}" {{ old('niveau') === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nom / Section de la classe</label>
                            <input type="text" name="nom_classe" class="form-control" placeholder="ex: A, B, C ou Groupe 1" maxlength="20" value="{{ old('nom_classe') }}" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Enregistrer la classe</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h5 class="card-title fw-bold mb-0">Liste des Classes</h5>
                        <a href="{{ route('bulletins.ecole') }}" target="_blank" class="btn btn-sm btn-primary fw-bold">Imprimer tous les bulletins</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Niveau</th>
                                    <th>Nom / Section</th>
                                    <th>Professeur titulaire</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($classes as $classe)
                                <tr>
                                    <td><span class="badge bg-primary text-white px-2 py-1">{{ $classe->niveau }}</span></td>
                                    <td class="fw-bold">{{ $classe->nom_classe }}</td>
                                    <td>
                                        @if($classe->professeur)
                                        {{ $classe->professeur->name }}
                                        @else
                                        <span class="text-muted small fst-italic">Non assigné</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ url('/eleves?classe_id=' . $classe->id) }}" class="btn btn-sm btn-outline-secondary">Voir Élèves</a>
                                        <a href="{{ route('recapitulatif.show', $classe) }}" class="btn btn-sm btn-outline-primary">Résultats</a>
                                        <a href="{{ route('conseil.show', $classe) }}" class="btn btn-sm btn-outline-dark">Conseil</a>
                                        <a href="{{ route('classes.edit', $classe->id) }}" class="btn btn-sm btn-outline-success">Modifier</a>
                                        <form action="{{ route('classes.destroy', $classe) }}" method="POST" class="d-inline form-confirmer"
                                            data-message="Supprimer la classe {{ $classe->niveau }} {{ $classe->nom_classe }} ? Ses affectations de matières seront aussi supprimées.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted small py-4">Aucune classe pour le moment.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Demande confirmation avant de supprimer une classe
    document.querySelectorAll('form.form-confirmer').forEach(function(formulaire) {
        formulaire.addEventListener('submit', function(e) {
            if (!confirm(formulaire.dataset.message)) {
                e.preventDefault();
            }
        });
    });
</script>
@endsection