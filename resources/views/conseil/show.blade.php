@extends('layouts.app')

@section('title', 'Conseil de classe')

@section('content')
@php
$fmt = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', ' ');
$rangLabel = fn ($rang, $ex) => $rang === null ? '—' : ($rang === 1 ? '1er' : $rang . 'ème') . ($ex ? ' ex' : '');
$classeLabel = $classe->niveau . ' ' . $classe->nom_classe;
@endphp

<div class="container-fluid px-4 my-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Conseil de classe &mdash; {{ $classeLabel }}</h4>
            <a href="{{ route('recapitulatif.show', ['classe' => $classe->id, 'trimestre_id' => $trimestre->id ?? null]) }}" class="small text-decoration-none">&larr; Résultats de la classe</a>
        </div>

        @if($trimestres->isNotEmpty())
        <form action="{{ route('conseil.show', $classe) }}" method="GET" class="d-flex align-items-center gap-2">
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

    @if($errors->any())
    <div class="alert alert-danger small py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(!$resultats)
    <div class="alert alert-warning">
        Aucun trimestre pour l'année scolaire active. L'administrateur doit créer l'année scolaire, la définir comme active, puis créer ses trimestres.
    </div>
    @elseif(empty($resultats['eleves']))
    <div class="alert alert-warning">Aucun élève dans cette classe pour le moment.</div>
    @else
    <form action="{{ route('conseil.store', $classe) }}" method="POST">
        @csrf
        <input type="hidden" name="trimestre_id" value="{{ $trimestre->id }}">

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Coche les mentions et les sanctions décidées par le conseil, puis ajoute l'observation et la décision.
                    L'observation est proposée d'après la moyenne ; tu peux la modifier. La colonne « Abs. » donne les heures d'absence relevées à l'appel pendant le trimestre ({{ config('ecole.heures_par_jour', 7) }} h = 1 jour).
                </div>

                @if($estTroisieme)
                <div class="alert alert-info small py-2">
                    <b>Décision de fin d'année.</b> Le passage en classe supérieure dépend de la <b>moyenne annuelle</b> (moyenne des trois trimestres), et non de la moyenne du 3ème trimestre.
                    La décision est proposée d'après cette moyenne annuelle ({{ $moyennePassage }}/20 pour passer) ; tu peux la changer.
                </div>
                @else
                <div class="small text-muted mb-3">La décision de passage se prend au 3ème trimestre, d'après la moyenne annuelle.</div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="text-center">Rang</th>
                                <th rowspan="2">Élève</th>
                                <th rowspan="2" class="text-center">Moyenne</th>
                                @if($estTroisieme)
                                <th rowspan="2" class="text-center">Moy. annuelle</th>
                                <th rowspan="2" class="text-center">Rang annuel</th>
                                @endif
                                <th rowspan="2" class="text-center">Abs.</th>
                                <th colspan="3" class="text-center border-start">Mentions</th>
                                <th colspan="4" class="text-center border-start">Avertissements et blâmes</th>
                                <th rowspan="2" class="border-start" style="min-width: 190px">Observation</th>
                                <th rowspan="2" style="min-width: 170px">Décision</th>
                            </tr>
                            <tr class="small">
                                @foreach(\App\Models\DecisionConseil::MENTIONS as $champ => $libelle)
                                <th class="text-center {{ $loop->first ? 'border-start' : '' }}">{{ $libelle }}</th>
                                @endforeach
                                @foreach(\App\Models\DecisionConseil::SANCTIONS as $champ => $libelle)
                                <th class="text-center {{ $loop->first ? 'border-start' : '' }}">{{ $libelle }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($resultats['eleves'] as $id => $r)
                            @php
                            $d = $decisions->get($id);
                            $observation = $d
                            ? $d->observation
                            : ($r['moyenne'] !== null ? \App\Services\BulletinService::observation($r['moyenne']) : '');

                            // Décision proposée d'après la moyenne annuelle (3ème trimestre seulement)
                            $decisionPropose = null;
                            if ($estTroisieme && $r['annuel']['moyenne'] !== null) {
                            $decisionPropose = $r['annuel']['moyenne'] >= $moyennePassage ? $choixDecision[0] : $choixDecision[1];
                            }
                            $decisionActuelle = $d ? $d->decision : $decisionPropose;
                            @endphp
                            <tr>
                                <td class="text-center fw-bold">{{ $rangLabel($r['rang'], $r['ex']) }}</td>
                                <td class="fw-bold text-uppercase text-nowrap">
                                    {{ $r['eleve']->nom }} <span class="text-capitalize fw-normal">{{ $r['eleve']->prenom }}</span>
                                </td>
                                <td class="text-center fw-bold {{ $r['moyenne'] !== null && $r['moyenne'] < 10 ? 'text-danger' : 'text-success' }}">{{ $fmt($r['moyenne']) }}</td>
                                @if($estTroisieme)
                                <td class="text-center fw-bold {{ $r['annuel']['moyenne'] !== null && $r['annuel']['moyenne'] < $moyennePassage ? 'text-danger' : 'text-success' }}">{{ $fmt($r['annuel']['moyenne']) }}</td>
                                <td class="text-center">{{ $rangLabel($r['annuel']['rang'], $r['annuel']['ex']) }}</td>
                                @endif
                                <td class="text-center small text-nowrap">{{ $r['absences']['heures'] > 0 ? $r['absences']['libelle'] : '—' }}</td>

                                @foreach(\App\Models\DecisionConseil::MENTIONS as $champ => $libelle)
                                <td class="text-center {{ $loop->first ? 'border-start' : '' }}">
                                    <input type="checkbox" class="form-check-input" name="decisions[{{ $id }}][{{ $champ }}]" value="1" {{ $d && $d->{$champ} ? 'checked' : '' }}>
                                </td>
                                @endforeach
                                @foreach(\App\Models\DecisionConseil::SANCTIONS as $champ => $libelle)
                                <td class="text-center {{ $loop->first ? 'border-start' : '' }}">
                                    <input type="checkbox" class="form-check-input" name="decisions[{{ $id }}][{{ $champ }}]" value="1" {{ $d && $d->{$champ} ? 'checked' : '' }}>
                                </td>
                                @endforeach

                                <td class="border-start">
                                    <input type="text" class="form-control form-control-sm" name="decisions[{{ $id }}][observation]" maxlength="150" value="{{ $observation }}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="decisions[{{ $id }}][decision]" {{ $estTroisieme ? '' : 'disabled' }}>
                                        <option value="">&mdash;</option>
                                        @foreach($choixDecision as $choix)
                                        <option value="{{ $choix }}" {{ $decisionActuelle === $choix ? 'selected' : '' }}>{{ $choix }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end pt-3">
                    <button type="submit" class="btn btn-success fw-bold px-4">Enregistrer le conseil de classe</button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection