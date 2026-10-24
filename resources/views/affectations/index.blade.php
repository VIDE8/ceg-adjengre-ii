@extends('layouts.app')

@section('title', 'Affectations')

@section('content')
<div class="container my-4">

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

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Classe</h5>
                    <form action="{{ route('affectations.index') }}" method="GET">
                        <select name="classe_id" class="form-select" onchange="this.form.submit()">
                            @foreach($classes as $c)
                            <option value="{{ $c->id }}" @selected($classe && $classe->id === $c->id)>{{ $c->niveau }} {{ $c->nom_classe }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            @if($classe)
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-success mb-3">Affecter une matière</h5>

                    <form action="{{ route('affectations.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="classe_id" value="{{ $classe->id }}">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Matière</label>
                            <select name="matiere_id" class="form-select" required>
                                <option value="">— Choisir —</option>
                                @foreach($matieres as $m)
                                <option value="{{ $m->id }}">{{ $m->nom }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Professeur</label>
                            <select name="professeur_id" class="form-select">
                                <option value="">— Aucun pour l'instant —</option>
                                @foreach($professeurs as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Coefficient</label>
                            <input type="number" name="coefficient" class="form-control" min="1" max="10" value="1" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Enregistrer</button>
                        <div class="form-text">Si la matière est déjà dans cette classe, le professeur et le coefficient sont mis à jour.</div>
                    </form>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">
                        Matières de {{ $classe ? $classe->niveau . ' ' . $classe->nom_classe : '—' }}
                    </h5>
                    @if($classe)
                    <div class="small text-muted mb-3">
                        Professeur titulaire : <span class="fw-bold">{{ $classe->professeur->name ?? 'non assigné' }}</span>
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Matière</th>
                                    <th>Professeur</th>
                                    <th class="text-center">Coefficient</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($affectations as $a)
                                <tr>
                                    <td class="fw-bold">{{ $a->matiere->nom }}</td>
                                    <td>{{ $a->professeur->name ?? '—' }}</td>
                                    <td class="text-center"><span class="badge bg-secondary">{{ $a->coefficient }}</span></td>
                                    <td class="text-end">
                                        <form action="{{ route('affectations.destroy', $a) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Retirer cette matière de la classe ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted small py-4">Aucune matière affectée à cette classe.</td>
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