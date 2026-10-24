@extends('layouts.app')

@section('title', 'Absences de la classe')

@section('content')
@php
$parJour = config('ecole.heures_par_jour', 7);
$heureNom = fn ($n) => $n === 1 ? '1ère' : $n . 'ème';
@endphp
<div class="container my-4" style="max-width: 1000px;">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Absences de la {{ $classe->niveau }} {{ $classe->nom_classe }}</h4>
            <div class="small text-muted">{{ $parJour }} heures d'absence = 1 jour</div>
        </div>
        @if($trimestres->isNotEmpty())
        <form action="{{ route('appels.synthese', $classe) }}" method="GET" class="d-flex align-items-center gap-2">
            <label class="small fw-bold mb-0">Trimestre</label>
            <select name="trimestre_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($trimestres as $t)
                <option value="{{ $t->id }}" {{ $trimestre && $trimestre->id === $t->id ? 'selected' : '' }}>
                    {{ $t->numero == 1 ? '1er' : $t->numero . 'ème' }} trimestre &ndash; {{ $t->anneeScolaire->libelle }}
                </option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    @if(session('success'))
    <div class="alert alert-success small py-2">{{ session('success') }}</div>
    @endif

    @if(!$trimestre)
    <div class="alert alert-warning">Aucun trimestre pour l'année scolaire active.</div>
    @else

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th class="text-center">Heures d'absence</th>
                            <th class="text-center">Dont justifiées</th>
                            <th class="text-center">Retards</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eleves as $e)
                        @php
                        $mes = $parEleve->get($e->id, collect());
                        $absents = $mes->where('statut', 'absent');
                        $heures = (int) $absents->sum(fn ($l) => $l->appel->duree);
                        $justifiees = (int) $absents->filter(fn ($l) => $l->justifie_effectif)->sum(fn ($l) => $l->appel->duree);
                        $retards = $mes->where('statut', 'retard')->count();
                        @endphp
                        <tr>
                            <td class="fw-bold text-uppercase">{{ $e->nom }} <span class="text-capitalize fw-normal">{{ $e->prenom }}</span></td>
                            <td class="text-center fw-bold {{ $heures >= $parJour ? 'text-danger' : '' }}">{{ $heures > 0 ? \App\Services\BulletinService::formaterHeures($heures) : '—' }}</td>
                            <td class="text-center">{{ $justifiees > 0 ? $justifiees . ' h' : '—' }}</td>
                            <td class="text-center">{{ $retards > 0 ? $retards : '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted small py-4">Aucun élève dans cette classe.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Détail ({{ $lignes->count() }})</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Matière</th>
                            <th>Professeur</th>
                            <th>Élève</th>
                            <th>Statut</th>
                            <th class="text-end">Justification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lignes as $l)
                        <tr>
                            <td>{{ $l->appel->date->format('d/m/Y') }}</td>
                            <td>{{ $heureNom($l->appel->creneau) }}{{ $l->appel->duree > 1 ? ' (' . $l->appel->duree . ' h)' : '' }}</td>
                            <td>{{ $l->appel->matiere->nom ?? '—' }}</td>
                            <td class="small">{{ $l->appel->professeur->name ?? '—' }}</td>
                            <td class="small text-uppercase">{{ $l->eleve->nom }} <span class="text-capitalize">{{ $l->eleve->prenom }}</span></td>
                            <td>
                                @if($l->statut === 'absent')
                                <span class="badge bg-danger">Absent</span>
                                @else
                                <span class="badge bg-warning text-dark">Retard</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($l->statut === 'absent')
                                @if($l->justifie_effectif && !$l->justifie)
                                <span class="small text-success">Justifiée (permission)</span>
                                @else
                                <form action="{{ route('appels.justifier', $l) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $l->justifie ? 'btn-success' : 'btn-outline-secondary' }}">
                                        {{ $l->justifie ? 'Justifiée' : 'Justifier' }}
                                    </button>
                                </form>
                                @endif
                                @else
                                <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted small py-4">Aucune absence ni retard enregistré ce trimestre.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection