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

    <form action="{{ route('appels.index') }}" method="GET" id="form-filtres" class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Classe et matière</label>
                    <select name="affectation_id" class="form-select" data-filtre>
                        @foreach($affectations as $a)
                        <option value="{{ $a->id }}" {{ $affectation && $a->id === $affectation->id ? 'selected' : '' }}>
                            {{ $a->classe->niveau }} {{ $a->classe->nom_classe }} &mdash; {{ $a->matiere->nom }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Date</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control" max="{{ now()->toDateString() }}" data-filtre>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Heure de cours</label>
                    <select name="creneau" class="form-select" data-filtre>
                        @for($h = 1; $h <= $nbCreneaux; $h++)
                            <option value="{{ $h }}" {{ $h === $creneau ? 'selected' : '' }}>{{ $heureNom($h) }}</option>
                            @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Durée</label>
                    <select name="duree" class="form-select" data-filtre>
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
    <div class="alert alert-info small py-2" id="alerte-existant">
        Appel déjà enregistré pour cette heure par <b>{{ $appel->professeur->name ?? '—' }}</b> : tu peux le corriger ci-dessous.
    </div>
    @endif

    @if($eleves->isEmpty())
    <div class="alert alert-warning">Aucun élève dans cette classe pour le moment.</div>
    @else
    <form action="{{ route('appels.enregistrer') }}" method="POST" id="form-appel">
        @csrf
        <div id="zone-message"></div>
        <input type="hidden" name="affectation_id" value="{{ $affectation->id }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="creneau" value="{{ $creneau }}">
        <input type="hidden" name="duree" value="{{ $duree }}">

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="fw-bold mb-0">
                        <span id="titre-classe">{{ $affectation->classe->niveau }} {{ $affectation->classe->nom_classe }} &mdash; {{ $affectation->matiere->nom }}</span>
                        <span id="titre-detail" class="text-muted fs-6">&middot; {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} &middot; {{ $heureNom($creneau) }}{{ $duree > 1 ? ' (' . $duree . ' h)' : '' }}</span>
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
                        <tbody id="corps-eleves">
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
    (function() {
        var ROSTERS = @json($rosters);
        var heureNom = function(n) {
            return n === 1 ? '1ère heure' : n + 'ème heure';
        };
        var filtres = document.getElementById('form-filtres');
        var form = document.getElementById('form-appel');
        var corps = document.getElementById('corps-eleves');
        var modeHorsLigneDispo = !!form && Object.keys(ROSTERS).length > 0;

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

        function champ(nom) {
            return filtres.elements[nom];
        }

        function dateFr(iso) {
            var p = String(iso).split('-');
            return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
        }

        function aujourdhui() {
            var d = new Date();
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var j = ('0' + d.getDate()).slice(-2);
            return d.getFullYear() + '-' + m + '-' + j;
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

        // ---------- Remet tous les élèves à « présent » ----------
        var boutonTous = document.getElementById('tous-presents');
        if (boutonTous) {
            boutonTous.addEventListener('click', function() {
                document.querySelectorAll('input.choix-present').forEach(function(radio) {
                    radio.checked = true;
                });
            });
        }

        // ---------- La description du comportement n'apparaît que si un type est choisi ----------
        document.addEventListener('change', function(ev) {
            var liste = ev.target;
            if (liste.classList && liste.classList.contains('type-comportement')) {
                var champDesc = liste.parentElement.querySelector('input.description-comportement');
                champDesc.style.display = liste.value ? '' : 'none';
                if (liste.value) {
                    champDesc.focus();
                }
            }
        });

        // ---------- Tableau des élèves reconstruit sans connexion ----------
        function ligne(e) {
            var id = e.id;
            var radio = function(valeur, coche, classe) {
                return '<td class="text-center"><input type="radio" class="form-check-input ' + (classe || '') + '" name="statuts[' + id + ']" value="' + valeur + '"' + (coche ? ' checked' : '') + '></td>';
            };
            return '<tr>' +
                '<td class="fw-bold text-uppercase text-nowrap">' + esc(e.nom) + ' <span class="text-capitalize fw-normal">' + esc(e.prenom) + '</span>' +
                '<div class="small text-muted fw-normal">' + esc(e.matricule) + '</div></td>' +
                radio('present', true, 'choix-present') + radio('absent', false) + radio('retard', false) +
                '<td><div class="d-flex gap-1">' +
                '<select class="form-select form-select-sm type-comportement" name="comportements[' + id + '][type]" style="max-width: 130px">' +
                '<option value="">&mdash;</option><option value="incident">Incident</option>' +
                '<option value="appreciation">Appréciation</option><option value="sanction">Sanction</option></select>' +
                '<input type="text" class="form-control form-control-sm description-comportement" name="comportements[' + id + '][description]" maxlength="500" placeholder="Description" style="display:none">' +
                '</div></td></tr>';
        }

        // Sans connexion : on change la classe, la date ou l'heure sur place, sans recharger la page
        function appliquerHorsLigne() {
            var aff = champ('affectation_id').value;
            var cible = ROSTERS[aff];
            if (!cible) {
                return;
            }
            var date = champ('date').value;
            var creneau = parseInt(champ('creneau').value, 10);
            var duree = parseInt(champ('duree').value, 10);

            form.elements['affectation_id'].value = aff;
            form.elements['date'].value = date;
            form.elements['creneau'].value = creneau;
            form.elements['duree'].value = duree;

            corps.innerHTML = cible.eleves.map(ligne).join('');
            document.getElementById('titre-classe').textContent = cible.classe;
            document.getElementById('titre-detail').textContent = '· ' + dateFr(date) + ' · ' + heureNom(creneau) + (duree > 1 ? ' (' + duree + ' h)' : '');

            var existant = document.getElementById('alerte-existant');
            if (existant) {
                existant.style.display = 'none';
            }
            message('secondary', 'Mode hors ligne : l\'appel sera enregistré sur ce téléphone puis envoyé automatiquement.');
        }

        if (filtres) {
            document.querySelectorAll('[data-filtre]').forEach(function(el) {
                el.addEventListener('change', async function() {
                    if (!modeHorsLigneDispo) {
                        filtres.submit();
                        return;
                    }
                    var ok = await OfflineQueue.enLigne();
                    if (ok) {
                        filtres.submit();
                    } else {
                        appliquerHorsLigne();
                    }
                });
            });
        }

        // Page ouverte sans connexion : on prend la date du téléphone (la copie en cache peut dater d'hier)
        if (modeHorsLigneDispo) {
            OfflineQueue.enLigne().then(function(ok) {
                if (ok) {
                    return;
                }
                var today = aujourdhui();
                var saisie = champ('date');
                if (saisie.value !== today) {
                    if (saisie._flatpickr) {
                        saisie._flatpickr.set('maxDate', today);
                        saisie._flatpickr.setDate(today, false);
                    } else {
                        saisie.value = today;
                    }
                }
                appliquerHorsLigne();
            });
        }

        // ---------- Enregistrement : file d'attente + envoi immédiat si le réseau répond ----------
        function lire() {
            var d = {
                affectation_id: form.elements['affectation_id'].value,
                date: form.elements['date'].value,
                creneau: parseInt(form.elements['creneau'].value, 10),
                duree: parseInt(form.elements['duree'].value, 10),
                statuts: {},
                comportements: {}
            };
            form.querySelectorAll('input[type=radio]:checked').forEach(function(r) {
                var m = /^statuts\[(\d+)\]$/.exec(r.name);
                if (m) {
                    d.statuts[m[1]] = r.value;
                }
            });
            form.querySelectorAll('select.type-comportement').forEach(function(s) {
                var m = /^comportements\[(\d+)\]\[type\]$/.exec(s.name);
                if (!m) {
                    return;
                }
                var desc = form.elements['comportements[' + m[1] + '][description]'];
                d.comportements[m[1]] = {
                    type: s.value,
                    description: desc ? desc.value : ''
                };
            });
            return d;
        }

        if (form) {
            form.addEventListener('submit', async function(ev) {
                ev.preventDefault();
                var bouton = form.querySelector('button[type=submit]');
                var texteBouton = bouton.textContent;
                bouton.disabled = true;
                bouton.textContent = 'Enregistrement…';
                try {
                    var payload = lire();
                    var label = 'Appel ' + document.getElementById('titre-classe').textContent.trim() + ' · ' + dateFr(payload.date) + ' · ' + heureNom(payload.creneau);
                    var id = await OfflineQueue.enqueue('appel', payload, label);
                    var resultats = await OfflineQueue.flush();
                    var r = resultats[id];

                    if (r && (r.status === 'ok' || r.status === 'duplicate')) {
                        message('success', r.message || 'Appel déjà enregistré.');
                    } else if (r && r.status === 'error') {
                        await OfflineQueue.supprimer(id);
                        message('danger', r.message);
                    } else {
                        message('warning', 'Pas de connexion : l\'appel est gardé sur ce téléphone et sera envoyé automatiquement dès que la connexion reviendra.');
                    }
                } catch (e) {
                    message('danger', 'Impossible d\'enregistrer l\'appel : ' + e.message);
                } finally {
                    bouton.disabled = false;
                    bouton.textContent = texteBouton;
                }
            });
        }
    })();
</script>
@endsection
