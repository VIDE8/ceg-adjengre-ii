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
        <form action="{{ route('notes.saisie') }}" method="GET" id="form-trimestre" class="d-flex align-items-center gap-2">
            <input type="hidden" name="classe_id" value="{{ $affectation->classe_id }}">
            <input type="hidden" name="matiere_id" value="{{ $affectation->matiere_id }}">
            <label class="small fw-bold mb-0">Trimestre</label>
            <select name="trimestre_id" id="select-trimestre" class="form-select form-select-sm">
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
    <form action="{{ route('notes.enregistrer') }}" method="POST" id="form-notes">
        @csrf
        <div id="zone-message"></div>
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

        // ---------- Mode hors ligne ----------
        var form = document.getElementById('form-notes');
        var selectTrim = document.getElementById('select-trimestre');

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c];
            });
        }

        function message(type, texte) {
            var zone = document.getElementById('zone-message');
            if (zone) {
                zone.innerHTML = '<div class="alert alert-' + type + ' small py-2">' + esc(texte) + '</div>';
                zone.scrollIntoView({
                    block: 'nearest'
                });
            }
        }

        // Changer de trimestre recharge la page : impossible sans connexion
        if (selectTrim) {
            var trimestreInitial = selectTrim.value;
            selectTrim.addEventListener('change', async function() {
                var ok = await OfflineQueue.enLigne();
                if (ok) {
                    selectTrim.form.submit();
                } else {
                    selectTrim.value = trimestreInitial;
                    message('warning', 'Changer de trimestre demande une connexion Internet.');
                }
            });
        }

        if (!form) {
            return;
        }

        function lire() {
            var d = {
                classe_id: form.elements['classe_id'].value,
                matiere_id: form.elements['matiere_id'].value,
                trimestre_id: form.elements['trimestre_id'].value,
                notes: {}
            };
            form.querySelectorAll('input.note-input').forEach(function(inp) {
                var m = /^notes\[(\d+)\]\[devoir\]\[(\d+)\]$/.exec(inp.name);
                if (m) {
                    d.notes[m[1]] = d.notes[m[1]] || {};
                    d.notes[m[1]].devoir = d.notes[m[1]].devoir || {};
                    d.notes[m[1]].devoir[m[2]] = inp.value;
                    return;
                }
                m = /^notes\[(\d+)\]\[composition\]$/.exec(inp.name);
                if (m) {
                    d.notes[m[1]] = d.notes[m[1]] || {};
                    d.notes[m[1]].composition = inp.value;
                }
            });
            return d;
        }

        function remplir(nom, valeur) {
            var champ = form.elements[nom];
            if (champ) {
                champ.value = valeur == null ? '' : valeur;
                champ.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
            }
        }

        // Notes saisies sans connexion et pas encore envoyées : on les remet dans la grille
        // (la copie de la page gardée dans le téléphone peut être plus ancienne)
        OfflineQueue.toutes().then(function(items) {
            var attente = items.filter(function(i) {
                return i.type === 'note' && !i.error &&
                    String(i.payload.classe_id) === String(form.elements['classe_id'].value) &&
                    String(i.payload.matiere_id) === String(form.elements['matiere_id'].value) &&
                    String(i.payload.trimestre_id) === String(form.elements['trimestre_id'].value);
            });
            if (!attente.length) {
                return;
            }
            var maxOrdre = 0;
            attente.forEach(function(i) {
                Object.keys(i.payload.notes || {}).forEach(function(e) {
                    Object.keys((i.payload.notes[e] || {}).devoir || {}).forEach(function(o) {
                        maxOrdre = Math.max(maxOrdre, parseInt(o, 10));
                    });
                });
            });
            while (nb < Math.min(maxOrdre, maxDevoirs)) {
                bouton.click();
            }
            attente.forEach(function(i) {
                Object.keys(i.payload.notes || {}).forEach(function(e) {
                    var s = i.payload.notes[e] || {};
                    Object.keys(s.devoir || {}).forEach(function(o) {
                        remplir('notes[' + e + '][devoir][' + o + ']', s.devoir[o]);
                    });
                    if ('composition' in s) {
                        remplir('notes[' + e + '][composition]', s.composition);
                    }
                });
            });
            message('warning', 'Des notes saisies sans connexion attendent leur envoi : elles sont affichées dans la grille.');
        });

        form.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            var boutonEnvoi = form.querySelector('button[type=submit]');
            var texteBouton = boutonEnvoi.textContent;
            boutonEnvoi.disabled = true;
            boutonEnvoi.textContent = 'Enregistrement…';
            try {
                var payload = lire();
                var titre = document.querySelector('h4').textContent.replace(/\s+/g, ' ').trim();
                var trim = selectTrim ? selectTrim.options[selectTrim.selectedIndex].text.replace(/\s+/g, ' ').trim() : '';
                var label = 'Notes ' + titre + (trim ? ' · ' + trim : '');
                var id = await OfflineQueue.enqueue('note', payload, label);
                var resultats = await OfflineQueue.flush();
                var r = resultats[id];

                if (r && (r.status === 'ok' || r.status === 'duplicate')) {
                    message('success', 'Notes enregistrées avec succès !');
                } else if (r && r.status === 'error') {
                    await OfflineQueue.supprimer(id);
                    message('danger', r.message);
                } else {
                    message('warning', 'Pas de connexion : les notes sont gardées sur ce téléphone et seront envoyées automatiquement dès que la connexion reviendra.');
                }
            } catch (e) {
                message('danger', 'Impossible d\'enregistrer les notes : ' + e.message);
            } finally {
                boutonEnvoi.disabled = false;
                boutonEnvoi.textContent = texteBouton;
            }
        });
    })();
</script>
@endsection
