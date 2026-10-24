@extends('layouts.app')

@section('title', 'Suivi des Paiements')

@section('content')
@php
$f = fn ($n) => number_format($n, 0, ',', ' ') . ' F';
@endphp
<div class="container my-4">

    @if(!$annee)
    <div class="alert alert-warning">Aucune année scolaire n'existe. Le directeur doit d'abord en créer une et l'activer.</div>
    @endif

    {{-- Bilan de l'année --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Écolage soldé</div>
                    <div class="fw-bold fs-5 text-success">{{ $bilan->get('solde', 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Paiement en cours</div>
                    <div class="fw-bold fs-5 text-warning">{{ $bilan->get('partiel', 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-2">
                    <div class="small text-muted">Rien payé</div>
                    <div class="fw-bold fs-5 text-danger">{{ $bilan->get('non_paye', 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100 {{ $bilan->get('surplus', 0) > 0 ? 'border border-danger' : '' }}">
                <div class="card-body py-2">
                    <div class="small text-muted">Surplus à vérifier</div>
                    <div class="fw-bold fs-5 {{ $bilan->get('surplus', 0) > 0 ? 'text-danger' : '' }}">{{ $bilan->get('surplus', 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($bilan->get('non_defini', 0) > 0)
    <div class="alert alert-warning small py-2">
        L'écolage n'est pas défini pour {{ $bilan->get('non_defini') }} élève(s) : les paiements de leur classe sont bloqués tant que le montant n'est pas fixé dans la page
        <a href="{{ route('ecolage.index') }}">Écolage</a>.
    </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form action="{{ url('/paiements') }}" method="GET" class="d-flex gap-2">
                        @if($annee)<input type="hidden" name="annee_id" value="{{ $annee->id }}">@endif
                        <input type="text" name="search" class="form-control form-control-lg" placeholder="Rechercher un reçu par nom ou numéro d'élève..." value="{{ request('search', '2026-EP-') }}">
                        <button type="submit" class="btn btn-danger px-4 fw-bold text-white">Rechercher</button>
                        @if(request('search') && request('search') != '2026-EP-')
                        <a href="{{ url('/paiements') }}" class="btn btn-outline-secondary d-flex align-items-center">Effacer</a>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-danger mb-3">Enregistrer un Reçu</h5>

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

                    <form action="{{ route('paiements.store') }}" method="POST" id="form-paiement">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Élève</label>
                            <input type="text" id="recherche-eleve" class="form-control mb-2" placeholder="Tape un nom ou un matricule..." autocomplete="off">
                            <select name="eleve_id" id="eleve" class="form-select" size="6" required>
                                @foreach($eleves as $eleve)
                                @php $s = $situations[$eleve->id]; @endphp
                                <option value="{{ $eleve->id }}"
                                    data-statut="{{ $s['statut'] }}" data-du="{{ $s['du'] }}" data-paye="{{ $s['paye'] }}" data-reste="{{ $s['reste'] }}" data-surplus="{{ $s['surplus'] }}"
                                    {{ (string) old('eleve_id') === (string) $eleve->id ? 'selected' : '' }}>
                                    {{ $eleve->matricule }} - {{ mb_strtoupper($eleve->nom) }} {{ $eleve->prenom }}
                                    ({{ $eleve->classe ? $eleve->classe->niveau . ' ' . $eleve->classe->nom_classe : 'sans classe' }})
                                    &mdash;
                                    @switch($s['statut'])
                                    @case('solde') SOLDÉ @break
                                    @case('surplus') SURPLUS @break
                                    @case('partiel') reste {{ $f($s['reste']) }} @break
                                    @case('non_paye') rien payé @break
                                    @default écolage non défini
                                    @endswitch
                                </option>
                                @endforeach
                            </select>
                            <div id="info-ecolage" class="small mt-2"></div>
                        </div>
                        <input type="hidden" name="type_frais" value="scolarite">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Montant Versé (FCFA)</label>
                            <input type="number" name="montant_verse" id="montant" class="form-control" placeholder="ex: 25000" min="1" value="{{ old('montant_verse') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Date du versement</label>
                            <input type="date" name="date_paiement" class="form-control" value="{{ old('date_paiement', date('Y-m-d')) }}" required>
                        </div>
                        <button type="submit" id="bouton-valider" class="btn btn-danger w-100 fw-bold text-white">Valider l'encaissement</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="card-title fw-bold mb-0">Historique des Écolages ({{ count($paiements) }})</h5>
                        @if($annees->count() > 0)
                        <form action="{{ url('/paiements') }}" method="GET" class="d-flex align-items-center gap-2">
                            <label class="small fw-bold mb-0">Année</label>
                            <select name="annee_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach($annees as $a)
                                <option value="{{ $a->id }}" {{ $annee && $a->id === $annee->id ? 'selected' : '' }}>{{ $a->libelle }}{{ $a->active ? ' (active)' : '' }}</option>
                                @endforeach
                            </select>
                        </form>
                        @endif
                    </div>

                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>N° Reçu</th>
                                    <th>Élève</th>
                                    <th>Versé</th>
                                    <th>Situation</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paiements as $paiement)
                                @php $s = $situations[$paiement->eleve_id] ?? null; @endphp
                                <tr>
                                    <td class="text-muted small fw-bold">{{ $paiement->reference_recu }}</td>
                                    <td class="fw-bold text-uppercase">{{ $paiement->eleve->nom }} <span class="text-capitalize fw-normal">{{ $paiement->eleve->prenom }}</span> <br> <small class="text-muted">{{ $paiement->eleve->matricule }}</small></td>
                                    <td class="fw-bold text-success">{{ number_format($paiement->montant_verse, 0, ',', ' ') }} F</td>
                                    <td class="small">
                                        @if(!$s || $s['statut'] === 'non_defini')
                                        <span class="badge bg-secondary">Écolage non défini</span>
                                        @elseif($s['statut'] === 'solde')
                                        <span class="badge bg-success">Soldé</span>
                                        <div class="text-muted">sur {{ $f($s['du']) }}</div>
                                        @elseif($s['statut'] === 'surplus')
                                        <span class="badge bg-danger">Surplus {{ $f($s['surplus']) }}</span>
                                        <div class="text-danger">à vérifier (écolage {{ $f($s['du']) }})</div>
                                        @else
                                        <span class="badge bg-warning text-dark">Reste {{ $f($s['reste']) }}</span>
                                        <div class="text-muted">sur {{ $f($s['du']) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('paiements.edit', $paiement->id) }}" class="btn btn-sm btn-warning fw-bold px-3">Modifier</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted small py-4">Aucun versement ne correspond à votre recherche.</td>
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
    (function() {
        var champ = document.getElementById('recherche-eleve');
        var liste = document.getElementById('eleve');
        var info = document.getElementById('info-ecolage');
        var montant = document.getElementById('montant');
        var bouton = document.getElementById('bouton-valider');

        var tous = Array.prototype.slice.call(liste.options).map(function(o) {
            return {
                valeur: o.value,
                texte: o.text,
                data: Object.assign({}, o.dataset),
                choisi: o.selected
            };
        });

        function francs(n) {
            return Number(n).toLocaleString('fr-FR') + ' F';
        }

        function afficher(filtre) {
            var mot = filtre.toLowerCase().trim();
            liste.innerHTML = '';
            tous.filter(function(e) {
                    return mot === '' || e.texte.toLowerCase().indexOf(mot) !== -1;
                })
                .slice(0, 80)
                .forEach(function(e) {
                    var option = document.createElement('option');
                    option.value = e.valeur;
                    option.textContent = e.texte.replace(/\s+/g, ' ').trim();
                    Object.keys(e.data).forEach(function(cle) {
                        option.dataset[cle] = e.data[cle];
                    });
                    option.selected = e.choisi && mot === '';
                    liste.appendChild(option);
                });
            if (liste.options.length === 1) {
                liste.selectedIndex = 0;
            }
            majInfo();
        }

        // Montre l'écolage, ce qui est payé et ce qui reste ; bloque le bouton si on ne peut plus payer
        function majInfo() {
            var option = liste.options[liste.selectedIndex];
            montant.removeAttribute('max');
            bouton.disabled = false;
            info.className = 'small mt-2';

            if (!option) {
                info.textContent = '';
                return;
            }

            var statut = option.dataset.statut;
            var du = option.dataset.du,
                paye = option.dataset.paye,
                reste = option.dataset.reste,
                surplus = option.dataset.surplus;

            if (statut === 'non_defini') {
                info.className = 'small mt-2 text-warning fw-bold';
                info.textContent = "L'écolage de cette classe n'est pas défini : définis-le d'abord dans la page « Écolage ».";
                bouton.disabled = true;
            } else if (statut === 'solde') {
                info.className = 'small mt-2 text-success fw-bold';
                info.textContent = 'Écolage déjà soldé (' + francs(du) + ') : aucun versement supplémentaire possible.';
                bouton.disabled = true;
            } else if (statut === 'surplus') {
                info.className = 'small mt-2 text-danger fw-bold';
                info.textContent = 'Surplus de ' + francs(surplus) + ' déjà enregistré (écolage ' + francs(du) + ') : à vérifier. Aucun versement possible.';
                bouton.disabled = true;
            } else {
                info.textContent = 'Écolage ' + francs(du) + ' · déjà payé ' + francs(paye) + ' · reste à payer ' + francs(reste);
                montant.max = reste;
            }
        }

        champ.addEventListener('input', function() {
            afficher(champ.value);
        });
        liste.addEventListener('change', majInfo);
        afficher('');
    })();
</script>
@endsection