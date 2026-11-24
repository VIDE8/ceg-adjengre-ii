@extends('layouts.app')

@section('title', 'Saisie des notes')

@section('content')
<div class="container my-4">
    <h4 class="fw-bold mb-1">Saisie des notes</h4>
    <p class="text-muted small mb-4">Choisis une classe et une matière pour saisir les notes de classe et la composition.</p>

    @if(session('success'))
    <div class="alert alert-success small py-2">{{ session('success') }}</div>
    @endif

    @if($affectations->isEmpty())
    <div class="alert alert-warning">
        Aucune matière ne vous est affectée pour le moment. Contactez l'administrateur.
    </div>
    @else
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th class="text-center d-none d-md-table-cell">Coefficient</th>
                            <th class="d-none d-md-table-cell"></th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($affectations as $a)
                        <tr style="cursor: pointer" onclick="window.location.href='{{ route('notes.saisie', ['classe_id' => $a->classe_id, 'matiere_id' => $a->matiere_id]) }}'">
                            <td class="fw-bold">{{ $a->classe->niveau }} {{ $a->classe->nom_classe }}</td>
                            <td>{{ $a->matiere->nom }}</td>
                            <td class="text-center d-none d-md-table-cell"><span class="badge bg-secondary">{{ $a->coefficient }}</span></td>
                            <td class="d-none d-md-table-cell">
                                @if($a->classe->professeur_id == auth()->id())
                                <span class="badge bg-success">Professeur titulaire</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('notes.saisie', ['classe_id' => $a->classe_id, 'matiere_id' => $a->matiere_id]) }}" class="btn btn-sm btn-warning fw-bold text-dark text-nowrap">
                                    Saisir les notes
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
@php
$pagesHorsLigne = array_merge(
    [route('notes.index', [], false), route('appels.index', [], false)],
    $affectations->map(fn ($a) => route('notes.saisie', ['classe_id' => $a->classe_id, 'matiere_id' => $a->matiere_id], false))->all()
);
@endphp
<script>
    // Avec Internet, on garde ces pages dans le téléphone pour pouvoir saisir les notes et faire l'appel sans connexion
    OfflineQueue.precharger(@json($pagesHorsLigne));
</script>
@endsection
