@extends('layouts.app')

@section('title', 'Permissions')

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
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-success mb-3">Nouvelle permission</h5>

                    <form action="{{ route('permissions.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="type-sortie" value="sortie" {{ old('type', 'sortie') === 'sortie' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-bold" for="type-sortie">Sortie pendant les cours</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="type-absence" value="absence" {{ old('type') === 'absence' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-bold" for="type-absence">Absence d'un ou plusieurs jours</label>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Élève</label>
                            <input type="text" id="recherche" class="form-control mb-2" placeholder="Tape un nom, un prénom ou un matricule..." autocomplete="off">
                            <select name="eleve_id" id="eleve" class="form-select" size="6" required>
                                @foreach($eleves as $e)
                                <option value="{{ $e->id }}">{{ mb_strtoupper($e->nom) }} {{ $e->prenom }} &ndash; {{ $e->classe ? $e->classe->niveau . ' ' . $e->classe->nom_classe : 'sans classe' }} ({{ $e->matricule }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold" id="label-date">Date</label>
                            <input type="date" name="date_debut" class="form-control" value="{{ old('date_debut', now()->toDateString()) }}" required>
                        </div>

                        <div id="bloc-absence" style="display:none">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Jusqu'au (inclus)</label>
                                <input type="date" name="date_fin" class="form-control" value="{{ old('date_fin', now()->toDateString()) }}">
                            </div>
                        </div>

                        <div id="bloc-sortie">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Heure de sortie</label>
                                    <input type="time" name="heure_sortie" class="form-control" value="{{ old('heure_sortie', now()->format('H:i')) }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Retour prévu</label>
                                    <input type="time" name="heure_retour_prevue" class="form-control" value="{{ old('heure_retour_prevue') }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Accompagné(e) par</label>
                                <input type="text" name="accompagnateur" class="form-control" maxlength="100" value="{{ old('accompagnateur') }}" placeholder="ex: son père, sa tutrice">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Motif</label>
                            <input type="text" name="motif" class="form-control" maxlength="150" value="{{ old('motif') }}" placeholder="ex: rendez-vous médical, deuil familial" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Accorder et imprimer</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="card-title fw-bold mb-0">
                            Permissions du {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ({{ $permissions->count() }})
                        </h5>
                        <form action="{{ route('permissions.index') }}" method="GET" class="d-flex align-items-center gap-2">
                            <label class="small fw-bold mb-0">Jour</label>
                            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()">
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Élève</th>
                                    <th>Type</th>
                                    <th>Détail</th>
                                    <th>Statut</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($permissions as $p)
                                <tr>
                                    <td>
                                        <span class="fw-bold text-uppercase">{{ $p->eleve->nom }}</span> {{ $p->eleve->prenom }}
                                        <div class="small text-muted">{{ $p->classe ? $p->classe->niveau . ' ' . $p->classe->nom_classe : '—' }}</div>
                                    </td>
                                    <td>
                                        @if($p->type === 'sortie')
                                        <span class="badge bg-info text-dark">Sortie</span>
                                        @else
                                        <span class="badge bg-warning text-dark">Absence</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($p->type === 'sortie')
                                        Sortie à {{ substr($p->heure_sortie, 0, 5) }}
                                        @if($p->heure_retour_prevue) &middot; retour prévu {{ substr($p->heure_retour_prevue, 0, 5) }}@endif
                                        @if($p->accompagnateur)<div>Avec {{ $p->accompagnateur }}</div>@endif
                                        @else
                                        Du {{ $p->date_debut->format('d/m/Y') }} au {{ $p->date_fin->format('d/m/Y') }}
                                        @endif
                                        <div class="text-muted">{{ $p->motif }}</div>
                                    </td>
                                    <td class="small">
                                        @if($p->type === 'sortie')
                                        @if($p->heure_retour)
                                        <span class="text-success fw-bold">Revenu à {{ substr($p->heure_retour, 0, 5) }}</span>
                                        @elseif($p->date_debut->isToday())
                                        <span class="text-danger fw-bold">Sorti, pas encore revenu</span>
                                        @else
                                        <span class="text-muted">&mdash;</span>
                                        @endif
                                        @else
                                        <span class="text-muted">Absences justifiées</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($p->type === 'sortie' && !$p->heure_retour)
                                        <form action="{{ route('permissions.retour', $p) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Marquer le retour</button>
                                        </form>
                                        @endif
                                        <a href="{{ route('permissions.billet', $p) }}" target="_blank" class="btn btn-sm btn-outline-primary">Imprimer</a>
                                        <form action="{{ route('permissions.destroy', $p) }}" method="POST" class="d-inline form-confirmer" data-message="Supprimer cette permission ?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted small py-4">Aucune permission ce jour-là.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        Une permission d'absence rend « justifiées » les absences de l'élève pendant la période : elles sont comptées comme telles sur le bulletin.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Recherche instantanée dans la liste des élèves
    (function() {
        var champ = document.getElementById('recherche');
        var liste = document.getElementById('eleve');
        var tous = Array.prototype.slice.call(liste.options).map(function(o) {
            return {
                valeur: o.value,
                texte: o.text
            };
        });

        function afficher(filtre) {
            var mot = filtre.toLowerCase().trim();
            liste.innerHTML = '';
            tous.filter(function(e) {
                    return mot === '' || e.texte.toLowerCase().indexOf(mot) !== -1;
                })
                .slice(0, 60)
                .forEach(function(e) {
                    var option = document.createElement('option');
                    option.value = e.valeur;
                    option.textContent = e.texte;
                    liste.appendChild(option);
                });
            if (liste.options.length === 1) {
                liste.selectedIndex = 0;
            }
        }

        champ.addEventListener('input', function() {
            afficher(champ.value);
        });
        afficher('');
    })();

    // Champs différents selon le type de permission
    (function() {
        var sortie = document.getElementById('type-sortie');
        var absence = document.getElementById('type-absence');
        var blocSortie = document.getElementById('bloc-sortie');
        var blocAbsence = document.getElementById('bloc-absence');
        var etiquette = document.getElementById('label-date');

        function basculer() {
            var estAbsence = absence.checked;
            blocSortie.style.display = estAbsence ? 'none' : '';
            blocAbsence.style.display = estAbsence ? '' : 'none';
            etiquette.textContent = estAbsence ? 'Du' : 'Date';
        }

        sortie.addEventListener('change', basculer);
        absence.addEventListener('change', basculer);
        basculer();
    })();

    // Confirmation avant suppression
    document.querySelectorAll('form.form-confirmer').forEach(function(formulaire) {
        formulaire.addEventListener('submit', function(e) {
            if (!confirm(formulaire.dataset.message)) {
                e.preventDefault();
            }
        });
    });
</script>

@php
$ecoleImpression = [
    'republique'       => config('ecole.republique'),
    'devise_nationale' => config('ecole.devise_nationale'),
    'ministere'        => config('ecole.ministere'),
    'nom'              => config('ecole.nom'),
    'logo'             => config('ecole.logo') && file_exists(public_path(config('ecole.logo'))) ? '/' . config('ecole.logo') : null,
    'utilisateur'      => auth()->user()->name,
];
@endphp
<script>
    // Informations nécessaires pour imprimer un billet sans connexion
    window.ECOLE = @json($ecoleImpression);
</script>
<script src="/offline-surveillant.js"></script>
@endsection
