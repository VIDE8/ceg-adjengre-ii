@extends('layouts.app')

@section('title', 'Saisie des notes')

@section('content')
<div class="container my-4">

    <a href="{{ route('notes.index') }}" class="small text-decoration-none">&larr; Mes classes et mes matières</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">
                {{ $affectation->classe->niveau }} {{ $affectation->classe->nom_classe }}
                &mdash; {{ $affectation->matiere->nom }}
            </h4>
            <div class="text-muted small">Coefficient {{ $affectation->coefficient }}</div>
        </div>

        @if($trimestres->isNotEmpty())
        <form action="{{ route('notes.saisie') }}" method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="classe_id" value="{{ $affectation->classe_id }}">
            <input type="hidden" name="matiere_id" value="{{ $affectation->matiere_id }}">
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

    @if($trimestres->isEmpty())
    <div class="alert alert-warning">
        Aucun trimestre pour l'année scolaire active. L'administrateur doit créer l'année scolaire, la définir comme active, puis créer ses trimestres.
    </div>
    @elseif($eleves->isEmpty())
    <div class="alert alert-warning">Aucun élève dans cette classe pour le moment.</div>
    @else
    <form action="{{ route('notes.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="classe_id" value="{{ $affectation->classe_id }}">
        <input type="hidden" name="matiere_id" value="{{ $affectation->matiere_id }}">
        <input type="hidden" name="trimestre_id" value="{{ $trimestre->id }}">

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted">
                        Moyenne de la matière = (moyenne des notes de classe + composition) &divide; 2.
                        Laisse une case vide pour ne pas saisir de note ; vider une case supprime la note.
                    </div>
                    <button type="button" id="btn-add-devoir" class="btn btn-sm btn-outline-secondary text-nowrap ms-3">+ Devoir</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="table-notes" data-max-devoirs="{{ $maxDevoirs }}" data-nb-devoirs="{{ $nbDevoirs }}">
                        <thead class="table-light">
                            <tr>
                                <th>Élève</th>
                                @for($i = 1; $i <= $nbDevoirs; $i++)
                                    <th class="text-center">Devoir {{ $i }}</th>
                                    @endfor
                                    <th class="text-center th-composition">Composition</th>
                                    <th class="text-center">Moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($eleves as $eleve)
                            @php $s = $saisies[$eleve->id] ?? []; @endphp
                            <tr data-eleve="{{ $eleve->id }}">
                                <td class="fw-bold text-uppercase">
                                    {{ $eleve->nom }} <span class="text-capitalize fw-normal">{{ $eleve->prenom }}</span>
                                    <br><small class="text-muted fw-normal">{{ $eleve->matricule }}</small>
                                </td>
                                @for($i = 1; $i <= $nbDevoirs; $i++)
                                    <td>
                                    <input type="number" step="0.25" min="0" max="20"
                                        class="form-control form-control-sm text-center note-input devoir" style="min-width: 80px"
                                        name="notes[{{ $eleve->id }}][devoir][{{ $i }}]"
                                        value="{{ $s['devoir'][$i] ?? '' }}">
                                    </td>
                                    @endfor
                                    <td>
                                        <input type="number" step="0.25" min="0" max="20"
                                            class="form-control form-control-sm text-center note-input composition" style="min-width: 80px"
                                            name="notes[{{ $eleve->id }}][composition]"
                                            value="{{ $s['composition'] ?? '' }}">
                                    </td>
                                    <td class="moyenne text-center fw-bold text-muted">&mdash;</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end pt-2">
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Enregistrer les notes</button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection

@section('scripts')
<script>
    (function() {
        var table = document.getElementById('table-notes');
        if (!table) {
            return;
        }

        var maxDevoirs = parseInt(table.dataset.maxDevoirs, 10);
        var nb = parseInt(table.dataset.nbDevoirs, 10);
        var bouton = document.getElementById('btn-add-devoir');

        function valeur(input) {
            var v = input.value.replace(',', '.').trim();
            if (v === '') {
                return null;
            }
            var n = parseFloat(v);
            return isNaN(n) ? null : n;
        }

        function calculer(tr) {
            var devoirs = [];
            tr.querySelectorAll('input.devoir').forEach(function(input) {
                var v = valeur(input);
                if (v !== null) {
                    devoirs.push(v);
                }
            });
            var compo = valeur(tr.querySelector('input.composition'));
            var cellule = tr.querySelector('.moyenne');

            if (devoirs.length === 0 || compo === null) {
                cellule.textContent = '\u2014';
                cellule.className = 'moyenne text-center fw-bold text-muted';
                return;
            }

            var mc = devoirs.reduce(function(a, b) {
                return a + b;
            }, 0) / devoirs.length;
            var moyenne = (mc + compo) / 2;
            cellule.textContent = moyenne.toFixed(2).replace('.', ',');
            cellule.className = 'moyenne text-center fw-bold ' + (moyenne >= 10 ? 'text-success' : 'text-danger');
        }

        table.addEventListener('input', function(e) {
            if (e.target.classList.contains('note-input')) {
                calculer(e.target.closest('tr'));
            }
        });

        table.querySelectorAll('tbody tr[data-eleve]').forEach(calculer);

        if (nb >= maxDevoirs) {
            bouton.disabled = true;
        }

        bouton.addEventListener('click', function() {
            if (nb >= maxDevoirs) {
                return;
            }
            nb++;

            var th = document.createElement('th');
            th.className = 'text-center';
            th.textContent = 'Devoir ' + nb;
            table.querySelector('th.th-composition').before(th);

            table.querySelectorAll('tbody tr[data-eleve]').forEach(function(tr) {
                var td = document.createElement('td');
                td.innerHTML = '<input type="number" step="0.25" min="0" max="20" ' +
                    'class="form-control form-control-sm text-center note-input devoir" style="min-width: 80px" ' +
                    'name="notes[' + tr.dataset.eleve + '][devoir][' + nb + ']">';
                tr.querySelector('input.composition').closest('td').before(td);
            });

            if (nb >= maxDevoirs) {
                bouton.disabled = true;
            }
        });
    })();
</script>
@endsection