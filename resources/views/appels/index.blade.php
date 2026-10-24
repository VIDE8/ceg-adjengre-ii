@extends('layouts.app')

@section('title', 'Appel')

@section('content')
@php
$heureNom = fn ($n) => $n === 1 ? '1ère heure' : $n . 'ème heure';
$parJour = config('ecole.heures_par_jour', 7);
@endphp
<div class="container my-4" style="max-width: 980px;">

    <h4 class="fw-bold mb-1">Appel</h4>
    <p class="text-muted small mb-3">
        À chaque heure de cours, fais l'appel de ta classe. Les absences se comptent en heures : {{ $parJour }} heures d'absence font 1 jour.
    </p>

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

    @if($affectations->isEmpty())
    <div class="alert alert-warning">Aucune matière ne t'est affectée pour le moment. Contacte le directeur.</div>
    @else

    <form action="{{ route('appels.index') }}" method="GET" class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Classe et matière</label>
                    <select name="affectation_id" class="form-select" onchange="this.form.submit()">
                        @foreach($affectations as $a)
                        <option value="{{ $a->id }}" {{ $affectation && $a->id === $affectation->id ? 'selected' : '' }}>
                            {{ $a->classe->niveau }} {{ $a->classe->nom_classe }} &mdash; {{ $a->matiere->nom }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Date</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control" max="{{ now()->toDateString() }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Heure de cours</label>
                    <select name="creneau" class="form-select" onchange="this.form.submit()">
                        @for($h = 1; $h <= $nbCreneaux; $h++)
                            <option value="{{ $h }}" {{ $h === $creneau ? 'selected' : '' }}>{{ $heureNom($h) }}</option>
                            @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Durée</label>
                    <select name="duree" class="form-select" onchange="this.form.submit()">
                        @for($d = 1; $d <= 4; $d++)
                            <option value="{{ $d }}" {{ $d === $duree ? 'selected' : '' }}>{{ $d }} h{{ $d === 2 ? ' (double cours)' : '' }}</option>
                            @endfor
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100">OK</button>
                </div>
            </div>
        </div>
    </form>

    @if($affectation)
    @if($appel)
    <div class="alert alert-info small py-2">
        Appel déjà enregistré pour cette heure par <b>{{ $appel->professeur->name ?? '—' }}</b> : tu peux le corriger ci-dessous.
    </div>
    @endif

    @if($eleves->isEmpty())
    <div class="alert alert-warning">Aucun élève dans cette classe pour le moment.</div>
    @else
    <form action="{{ route('appels.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="affectation_id" value="{{ $affectation->id }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="creneau" value="{{ $creneau }}">
        <input type="hidden" name="duree" value="{{ $duree }}">

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="fw-bold mb-0">
                        {{ $affectation->classe->niveau }} {{ $affectation->classe->nom_classe }} &mdash; {{ $affectation->matiere->nom }}
                        <span class="text-muted fs-6">&middot; {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} &middot; {{ $heureNom($creneau) }}{{ $duree > 1 ? ' (' . $duree . ' h)' : '' }}</span>
                    </h5>
                    <button type="button" id="tous-presents" class="btn btn-sm btn-outline-success">Tous présents</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Élève</th>
                                <th class="text-center">Présent</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Retard</th>
                                <th style="min-width: 260px">Comportement (facultatif)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($eleves as $e)
                            @php
                            $statut = old('statuts.' . $e->id, $statuts[$e->id] ?? 'present');
                            $c = $comportements->get($e->id);
                            $typeC = old('comportements.' . $e->id . '.type', $c->type ?? '');
                            $descC = old('comportements.' . $e->id . '.description', $c->description ?? '');
                            @endphp
                            <tr>
                                <td class="fw-bold text-uppercase text-nowrap">
                                    {{ $e->nom }} <span class="text-capitalize fw-normal">{{ $e->prenom }}</span>
                                    <div class="small text-muted fw-normal">{{ $e->matricule }}</div>
                                </td>
                                <td class="text-center"><input type="radio" class="form-check-input choix-present" name="statuts[{{ $e->id }}]" value="present" {{ $statut === 'present' ? 'checked' : '' }}></td>
                                <td class="text-center"><input type="radio" class="form-check-input" name="statuts[{{ $e->id }}]" value="absent" {{ $statut === 'absent' ? 'checked' : '' }}></td>
                                <td class="text-center"><input type="radio" class="form-check-input" name="statuts[{{ $e->id }}]" value="retard" {{ $statut === 'retard' ? 'checked' : '' }}></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <select class="form-select form-select-sm type-comportement" name="comportements[{{ $e->id }}][type]" style="max-width: 130px">
                                            <option value="">&mdash;</option>
                                            <option value="incident" {{ $typeC === 'incident' ? 'selected' : '' }}>Incident</option>
                                            <option value="appreciation" {{ $typeC === 'appreciation' ? 'selected' : '' }}>Appréciation</option>
                                            <option value="sanction" {{ $typeC === 'sanction' ? 'selected' : '' }}>Sanction</option>
                                        </select>
                                        <input type="text" class="form-control form-control-sm description-comportement" name="comportements[{{ $e->id }}][description]" maxlength="500" value="{{ $descC }}" placeholder="Description" style="{{ $typeC ? '' : 'display:none' }}">
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end pt-3">
                    <button type="submit" class="btn btn-success fw-bold px-4">Enregistrer l'appel</button>
                </div>
            </div>
        </div>
    </form>
    @endif

    @if($appelsDuJour->isNotEmpty())
    <div class="card shadow-sm border-0 mt-3">
        <div class="card-body py-2">
            <div class="small fw-bold mb-1">Appels déjà faits ce jour pour cette classe</div>
            @foreach($appelsDuJour as $a)
            <div class="small text-muted">
                {{ $heureNom($a->creneau) }}{{ $a->duree > 1 ? ' (' . $a->duree . ' h)' : '' }} &ndash; {{ $a->matiere->nom ?? '—' }} &ndash; {{ $a->professeur->name ?? '—' }}
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endif
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Remet tous les élèves à « présent »
    var boutonTous = document.getElementById('tous-presents');
    if (boutonTous) {
        boutonTous.addEventListener('click', function() {
            document.querySelectorAll('input.choix-present').forEach(function(radio) {
                radio.checked = true;
            });
        });
    }

    // La description du comportement n'apparaît que si un type est choisi
    document.querySelectorAll('select.type-comportement').forEach(function(liste) {
        liste.addEventListener('change', function() {
            var champ = liste.parentElement.querySelector('input.description-comportement');
            champ.style.display = liste.value ? '' : 'none';
            if (liste.value) {
                champ.focus();
            }
        });
    });
</script>
@endsection