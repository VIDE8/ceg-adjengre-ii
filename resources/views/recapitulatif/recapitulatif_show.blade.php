@extends('layouts.app')

@section('title', 'Résultats de la classe')

@section('content')
@php
$fmt = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', ' ');
$rangLabel = fn ($rang, $ex) => $rang === null ? '—' : ($rang === 1 ? '1er' : $rang . 'ème') . ($ex ? ' ex' : '');
$classeLabel = $classe->niveau . ' ' . $classe->nom_classe;
@endphp

<div class="container-fluid px-4 my-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Résultats de la {{ $classeLabel }}</h4>
            <div class="text-muted small">
                Professeur titulaire : <span class="fw-bold">{{ $classe->professeur->name ?? 'non assigné' }}</span>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('conseil.show', ['classe' => $classe->id, 'trimestre_id' => $trimestre?->id]) }}" class="btn btn-sm btn-outline-primary fw-bold">Conseil de classe</a>
            @if($trimestre)
            <a href="{{ route('bulletins.classe', ['classe' => $classe->id, 'trimestre_id' => $trimestre->id]) }}" target="_blank" class="btn btn-sm btn-primary fw-bold">Bulletins de la classe</a>
            @endif

            @if($trimestres->isNotEmpty())
            <form action="{{ route('recapitulatif.show', $classe) }}" method="GET" class="d-flex align-items-center gap-2">
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
    </div>

    @if(!$resultats)
    <div class="alert alert-warning">
        Aucun trimestre pour l'année scolaire active. L'administrateur doit créer l'année scolaire, la définir comme active, puis créer ses trimestres.
    </div>
    @elseif(empty($resultats['eleves']))
    <div class="alert alert-warning">Aucun élève dans cette classe pour le moment.</div>
    @else
    @php
    $stats = $resultats['stats'];
    $affectations = $resultats['affectations'];
    $actives = $resultats['matieres_actives'];
    $lignesEleves = collect($resultats['eleves'])->sortBy(
    fn ($r) => sprintf('%03d-%s', $r['rang'] ?? 999, $r['eleve']->nom . $r['eleve']->prenom)
    );
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Effectif</div>
                    <div class="fw-bold fs-5">{{ $stats['effectif'] }}</div>
                    <div class="small text-muted">G : {{ $stats['effectif_g'] }} &middot; F : {{ $stats['effectif_f'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Moyenne générale de la classe</div>
                    <div class="fw-bold fs-5">{{ $fmt($stats['moyenne_classe']) }}</div>
                    <div class="small text-muted">Annuelle : {{ $fmt($stats['moyenne_classe_annuelle']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Moyenne la plus forte</div>
                    <div class="fw-bold fs-5 text-success">{{ $fmt($stats['plus_forte']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Moyenne la plus faible</div>
                    <div class="fw-bold fs-5 text-danger">{{ $fmt($stats['plus_faible']) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(empty($actives))
    <div class="alert alert-info small">
        Aucune note n'a encore été saisie pour ce trimestre dans cette classe. Les résultats apparaîtront dès que les professeurs auront saisi leurs notes.
    </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">Rang</th>
                            <th>Élève</th>
                            @foreach($affectations as $aff)
                            @php $active = in_array((int) $aff->matiere_id, $actives); @endphp
                            <th class="text-center {{ $active ? '' : 'text-muted' }}" title="{{ $aff->professeur->name ?? 'Aucun professeur' }}">
                                {{ $aff->matiere->nom }}
                                <div class="small fw-normal">coef {{ $aff->coefficient }}</div>
                                @if(!$active)<div class="small fw-normal">non saisie</div>@endif
                            </th>
                            @endforeach
                            <th class="text-center">Total</th>
                            <th class="text-center">Moyenne</th>
                            <th class="text-center">Moy. annuelle</th>
                            <th class="text-center">Rang annuel</th>
                            <th class="text-center">Bulletin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lignesEleves as $r)
                        <tr>
                            <td class="text-center fw-bold">{{ $rangLabel($r['rang'], $r['ex']) }}</td>
                            <td class="fw-bold text-uppercase text-nowrap">
                                {{ $r['eleve']->nom }} <span class="text-capitalize fw-normal">{{ $r['eleve']->prenom }}</span>
                                <div class="small text-muted fw-normal text-capitalize">{{ $r['eleve']->sexe === 'M' ? 'Masculin' : ($r['eleve']->sexe === 'F' ? 'Féminin' : 'Sexe non renseigné') }}</div>
                            </td>
                            @foreach($affectations as $aff)
                            @php $l = $r['lignes'][(int) $aff->matiere_id] ?? null; @endphp
                            <td class="text-center {{ $l && $l['moyenne'] < 10 ? 'text-danger' : '' }}">
                                @if($l)
                                {{ $fmt($l['moyenne']) }}
                                @else
                                <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            @endforeach
                            <td class="text-center">{{ $r['total_coef'] > 0 ? $fmt($r['total_points']) : '—' }}</td>
                            <td class="text-center fw-bold {{ $r['moyenne'] !== null && $r['moyenne'] < 10 ? 'text-danger' : 'text-success' }}">{{ $fmt($r['moyenne']) }}</td>
                            <td class="text-center">{{ $fmt($r['annuel']['moyenne']) }}</td>
                            <td class="text-center">{{ $rangLabel($r['annuel']['rang'], $r['annuel']['ex']) }}</td>
                            <td class="text-center">
                                <a href="{{ route('bulletins.eleve', ['classe' => $classe->id, 'eleve' => $r['eleve']->id, 'trimestre_id' => $trimestre->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">Voir</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light small">
                        <tr>
                            <td colspan="2" class="fw-bold">Moyenne de la classe</td>
                            @foreach($affectations as $aff)
                            <td class="text-center">{{ $fmt($resultats['stats_matieres'][(int) $aff->matiere_id]['moyenne'] ?? null) }}</td>
                            @endforeach
                            <td colspan="5"></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="fw-bold">Note la plus forte</td>
                            @foreach($affectations as $aff)
                            <td class="text-center">{{ $fmt($resultats['stats_matieres'][(int) $aff->matiere_id]['forte'] ?? null) }}</td>
                            @endforeach
                            <td colspan="5"></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="fw-bold">Note la plus faible</td>
                            @foreach($affectations as $aff)
                            <td class="text-center">{{ $fmt($resultats['stats_matieres'][(int) $aff->matiere_id]['faible'] ?? null) }}</td>
                            @endforeach
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="small text-muted mt-3">
                Moyenne d'une matière = (moyenne des notes de classe + composition) &divide; 2. Moyenne du trimestre = total des notes pondérées &divide; total des coefficients.
                Une note manquante compte 0 ; une matière où personne n'a encore de note n'est pas comptée.
            </div>
        </div>
    </div>
    @endif
</div>
@endsection