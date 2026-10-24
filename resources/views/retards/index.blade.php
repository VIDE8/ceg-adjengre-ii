@extends('layouts.app')

@section('title', 'Retards')

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
                    <h5 class="card-title fw-bold text-success mb-3">Nouveau retard</h5>

                    <form action="{{ route('retards.store') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Élève</label>
                            <input type="text" id="recherche" class="form-control mb-2" placeholder="Tape un nom, un prénom ou un matricule..." autocomplete="off">
                            <select name="eleve_id" id="eleve" class="form-select" size="7" required>
                                @foreach($eleves as $e)
                                <option value="{{ $e->id }}">{{ mb_strtoupper($e->nom) }} {{ $e->prenom }} &ndash; {{ $e->classe ? $e->classe->niveau . ' ' . $e->classe->nom_classe : 'sans classe' }} ({{ $e->matricule }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-bold">Date</label>
                                <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold">Heure d'arrivée</label>
                                <input type="time" name="heure" class="form-control" value="{{ now()->format('H:i') }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Motif (facultatif)</label>
                            <input type="text" name="motif" class="form-control" maxlength="150" placeholder="ex: transport, maladie, réveil tardif">
                        </div>

                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Enregistrer et imprimer le billet</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="card-title fw-bold mb-0">
                            Retards du {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ({{ $retards->count() }})
                        </h5>
                        <form action="{{ route('retards.index') }}" method="GET" class="d-flex align-items-center gap-2">
                            <label class="small fw-bold mb-0">Jour</label>
                            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()">
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Heure</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Motif</th>
                                    <th class="text-center">Retards (année)</th>
                                    <th class="text-end">Billet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($retards as $r)
                                <tr>
                                    <td class="fw-bold">{{ substr($r->heure, 0, 5) }}</td>
                                    <td>
                                        <span class="fw-bold text-uppercase">{{ $r->eleve->nom }}</span> {{ $r->eleve->prenom }}
                                        <div class="small text-muted">Enregistré par {{ $r->enregistrePar->name ?? '—' }}</div>
                                    </td>
                                    <td>{{ $r->classe ? $r->classe->niveau . ' ' . $r->classe->nom_classe : '—' }}</td>
                                    <td class="small">{{ $r->motif ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ ($totaux[$r->eleve_id] ?? 0) >= 3 ? 'bg-danger' : 'bg-secondary' }}">{{ $totaux[$r->eleve_id] ?? 1 }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('retards.billet', $r) }}" target="_blank" class="btn btn-sm btn-outline-primary">Réimprimer</a>
                                        <form action="{{ route('retards.destroy', $r) }}" method="POST" class="d-inline form-confirmer" data-message="Supprimer ce retard ?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted small py-4">Aucun retard enregistré ce jour-là.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">Un élève en rouge a au moins 3 retards depuis le début de l'année scolaire.</div>
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

    // Confirmation avant suppression
    document.querySelectorAll('form.form-confirmer').forEach(function(formulaire) {
        formulaire.addEventListener('submit', function(e) {
            if (!confirm(formulaire.dataset.message)) {
                e.preventDefault();
            }
        });
    });
</script>
@endsection