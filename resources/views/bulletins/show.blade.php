<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>Bulletins &ndash; {{ $classe ? $classe->niveau . ' ' . $classe->nom_classe : "Toute l'école" }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9ecef;
            font-family: "Times New Roman", Times, serif;
            color: #000;
        }

        .barre-outils {
            background: #212529;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .barre-outils a,
        .barre-outils button,
        .barre-outils select {
            font-size: 14px;
        }

        .barre-outils a {
            color: #ffc107;
            text-decoration: none;
        }

        .barre-outils button {
            background: #ffc107;
            border: 0;
            padding: 7px 16px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }

        .bulletin {
            width: 210mm;
            min-height: 297mm;
            margin: 14px auto;
            background: #fff;
            padding: 8mm 9mm;
            font-size: 8.3pt;
            line-height: 1.2;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .sans-bordure td,
        .sans-bordure th {
            border: 0;
            padding: 0;
        }

        .notes th,
        .notes td,
        .petit th,
        .petit td {
            border: 1px solid #000;
            padding: 1.5px 3px;
            text-align: center;
        }

        .notes th {
            font-size: 7.3pt;
        }

        .gauche {
            text-align: left !important;
        }

        .gras {
            font-weight: bold;
        }

        .centre {
            text-align: center;
        }

        .entete td {
            vertical-align: top;
            font-size: 7.6pt;
        }

        .entete .g {
            width: 36%;
            text-align: center;
        }

        .entete .c {
            width: 28%;
            text-align: center;
        }

        .entete .d {
            width: 36%;
            text-align: center;
        }

        .entete img {
            height: 24mm;
        }

        .titre {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            margin: 5px 0 3px;
            letter-spacing: .5px;
        }

        .identite {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .bas {
            display: flex;
            gap: 12px;
            margin-top: 7px;
            align-items: flex-start;
        }

        .bas .col {
            flex: 1;
        }

        .petit {
            width: auto;
        }

        .stat {
            margin: 2px 0;
            font-weight: bold;
        }

        .ligne {
            margin: 4px 0;
        }

        .observations {
            margin-top: 6px;
            text-align: center;
        }

        .observations .t {
            font-weight: bold;
            text-decoration: underline;
        }

        .observations .obs {
            font-style: italic;
            margin-top: 2px;
            font-size: 10pt;
        }

        .observations .dec {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 2px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            min-height: 28mm;
        }

        .signatures .s {
            width: 45%;
            text-align: center;
        }

        .pied {
            margin-top: 8px;
            font-size: 7pt;
            text-align: center;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .bulletin {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }

            .bulletin:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>

<body>
    @php
    $ecole = config('ecole');
    $fmt = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', ' ');
    $rangMatiere = fn ($rang, $ex) => $rang === null ? '—' : ($rang === 1 ? '1er' : $rang . 'è') . ($ex ? ' Ex' : '');
    $rangMoyenne = fn ($rang, $ex) => $rang === null ? '—' : ($rang === 1 ? '1er' : $rang . 'ème') . ($ex ? ' ex' : '');
    $nomTrimestre = [1 => 'Premier', 2 => 'Deuxième', 3 => 'Troisième'][$trimestre->numero] ?? $trimestre->numero . 'ème';
    $totalBulletins = collect($groupes)->sum(fn ($g) => count($g['eleves']));
    $logo = !empty($ecole['logo']) && file_exists(public_path($ecole['logo'])) ? asset($ecole['logo']) : null;
    $ouiNon = fn ($d, $champ) => $d && $d->{$champ} ? 'Oui' : 'Non';
    @endphp

    <div class="barre-outils no-print">
        <button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
        @if($classe)
        <a href="{{ route('recapitulatif.show', ['classe' => $classe->id, 'trimestre_id' => $trimestre->id]) }}">&larr; Résultats de la classe</a>
        @else
        <a href="{{ route('home') }}">&larr; Gestion des classes</a>
        @endif

        @if($trimestres->count() > 1)
        <form method="GET" action="{{ $eleveId ? route('bulletins.eleve', [$classe, $eleveId]) : ($classe ? route('bulletins.classe', $classe) : route('bulletins.ecole')) }}">
            Trimestre :
            <select name="trimestre_id" onchange="this.form.submit()">
                @foreach($trimestres as $t)
                <option value="{{ $t->id }}" {{ $t->id === $trimestre->id ? 'selected' : '' }}>{{ $t->numero == 1 ? '1er' : $t->numero . 'ème' }} trimestre</option>
                @endforeach
            </select>
        </form>
        @endif

        <span>{{ $totalBulletins }} bulletin(s) &ndash; {{ $classe ? $classe->niveau . ' ' . $classe->nom_classe : "Toute l'école" }} &ndash; {{ $nomTrimestre }} trimestre</span>
        <span style="color:#adb5bd">Astuce : dans la fenêtre d'impression, choisis « Enregistrer au format PDF », format A4, et désactive « En-têtes et pieds de page ».</span>
    </div>

    @if($totalBulletins === 0 || collect($groupes)->every(fn ($g) => $g['matieresActives']->isEmpty()))
    <div class="bulletin" style="min-height:auto">
        <p>Aucune note n'a encore été saisie pour ce trimestre dans cette classe : il n'y a pas encore de bulletin à imprimer.</p>
    </div>
    @endif

    @foreach($groupes as $g)
    @php
    $classe = $g['classe'];
    $resultats = $g['resultats'];
    $eleves = $g['eleves'];
    $decisions = $g['decisions'];
    $maxDevoirs = $g['maxDevoirs'];
    $matieresActives = $g['matieresActives'];
    $classeLabel = $classe->niveau . ' ' . $classe->nom_classe;
    $stats = $resultats['stats'];
    @endphp
    @foreach($eleves as $id => $e)
    @php
    $eleve = $e['eleve'];
    $d = $decisions->get($id);
    $abs = $e['absences'];
    $absenceTexte = $abs['heures'] > 0
    ? $abs['libelle'] . ($abs['heures_justifiees'] > 0 ? ', dont ' . $abs['heures_justifiees'] . ' h justifiée(s)' : '')
    : '---';
    $annuel = $e['annuel'];
    @endphp

    <section class="bulletin">

        <table class="sans-bordure entete">
            <tr>
                <td class="g">
                    {{ $ecole['ministere'] }}<br><br>
                    {{ $ecole['direction_regionale'] }}<br><br>
                    {{ $ecole['inspection'] }}<br><br>
                    <b style="font-size: 9pt">{{ $ecole['nom'] }}</b><br>
                    {{ $ecole['devise'] }}
                </td>
                <td class="c">
                    @if($logo)<img src="{{ $logo }}" alt="Logo">@endif
                </td>
                <td class="d">
                    {{ $ecole['republique'] }}<br>
                    {{ $ecole['devise_nationale'] }}<br><br>
                    Année scolaire : {{ $trimestre->anneeScolaire->libelle }}<br>
                    <b>{{ $nomTrimestre }} trimestre</b><br><br>
                    <b>Classe : {{ $classeLabel }}</b>
                    <table class="petit" style="margin: 3px auto 0; width: 100%">
                        <tr>
                            <th colspan="3">Effectif</th>
                        </tr>
                        <tr>
                            <td>G : {{ $stats['effectif_g'] }}</td>
                            <td>F : {{ $stats['effectif_f'] }}</td>
                            <td>T : {{ $stats['effectif'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="titre">BULLETIN DE NOTES</div>
        <div class="identite">
            <span>Nom et prénom(s) : {{ mb_strtoupper($eleve->nom) }} {{ $eleve->prenom }}</span>
            <span>Sexe : {{ $eleve->sexe ?? '—' }}</span>
            <span>Statut : {{ $eleve->statut ?? '—' }}</span>
        </div>

        <table class="notes">
            <thead>
                <tr>
                    <th>MATIERES</th>
                    <th colspan="{{ $maxDevoirs }}">NOTES DE CLASSE</th>
                    <th>MC</th>
                    <th>Compo</th>
                    <th>Moy sur 20</th>
                    <th>COEF</th>
                    <th>Note définitive</th>
                    <th>Rang</th>
                    <th>Appréciation</th>
                    <th>Note Forte sur 20</th>
                    <th>Note Faible sur 20</th>
                    <th>Moy. Classe Matière</th>
                    <th>Chargé(e) de cours</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matieresActives as $aff)
                @php
                $mid = (int) $aff->matiere_id;
                $l = $e['lignes'][$mid];
                $st = $resultats['stats_matieres'][$mid];
                @endphp
                <tr>
                    <td class="gauche">{{ $aff->matiere->nom }}</td>
                    @for($i = 0; $i < $maxDevoirs; $i++)
                        <td>{{ isset($l['devoirs'][$i]) ? $fmt($l['devoirs'][$i]) : '' }}</td>
                        @endfor
                        <td>{{ $fmt($l['mc']) }}</td>
                        <td>{{ $fmt($l['composition']) }}</td>
                        <td>{{ $fmt($l['moyenne']) }}</td>
                        <td>{{ $aff->coefficient }}</td>
                        <td>{{ $fmt($l['note_definitive']) }}</td>
                        <td>{{ $rangMatiere($l['rang'], $l['ex']) }}</td>
                        <td>{{ $l['appreciation'] }}</td>
                        <td>{{ $fmt($st['forte']) }}</td>
                        <td>{{ $fmt($st['faible']) }}</td>
                        <td>{{ $fmt($st['moyenne']) }}</td>
                        <td class="gauche">{{ $aff->professeur->name ?? '' }}</td>
                </tr>
                @endforeach
                <tr class="gras">
                    <td class="gauche" colspan="{{ $maxDevoirs + 4 }}">Totaux</td>
                    <td>{{ $e['total_coef'] }}</td>
                    <td>{{ $fmt($e['total_points']) }}</td>
                    <td colspan="6"></td>
                </tr>
            </tbody>
        </table>

        <div class="bas">
            <div class="col">
                <table class="petit">
                    <tr>
                        <th></th>
                        <th>Moyenne</th>
                        <th>Rang</th>
                    </tr>
                    @foreach([1 => 'Premier trimestre', 2 => 'Deuxième trimestre', 3 => 'Troisième trimestre'] as $n => $libelle)
                    @php $h = $e['historique'][$n] ?? null; @endphp
                    <tr class="{{ $n == $trimestre->numero ? 'gras' : '' }}">
                        <td class="gauche">{{ $libelle }}</td>
                        <td>{{ $fmt($h['moyenne'] ?? null) }}</td>
                        <td>{{ $h ? $rangMoyenne($h['rang'], $h['ex']) : '—' }}</td>
                    </tr>
                    @endforeach
                    @if($trimestre->numero == 3)
                    <tr class="gras">
                        <td class="gauche">Annuel</td>
                        <td>{{ $fmt($annuel['moyenne']) }}</td>
                        <td>{{ $rangMoyenne($annuel['rang'], $annuel['ex']) }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            <div class="col">
                <div class="stat">Moyenne la plus forte : {{ $fmt($stats['plus_forte']) }}</div>
                <div class="stat">Moyenne la plus faible : {{ $fmt($stats['plus_faible']) }}</div>
                <div class="stat">Moyenne générale trimestrielle de la classe : {{ $fmt($stats['moyenne_classe']) }}</div>
                @if($trimestre->numero == 3)
                <div class="stat">Moyenne générale annuelle de la classe : {{ $fmt($stats['moyenne_classe_annuelle']) }}</div>
                @endif
            </div>
        </div>

        @if($trimestre->numero == 3)
        <div class="ligne gras">Moyenne annuelle en lettres : {{ \App\Support\NombreEnLettres::moyenne($annuel['moyenne']) }}</div>
        @endif
        <div class="ligne">Moy.Trim.Littérature : <b>{{ $fmt($e['moy_litterature']) }}</b> &nbsp;&nbsp;&nbsp; Moy.Trim.Science : <b>{{ $fmt($e['moy_science']) }}</b></div>
        <div class="ligne">Absence : <b>{{ $absenceTexte }}</b></div>

        <div class="bas">
            <table class="petit" style="width: 38%">
                @foreach(\App\Models\DecisionConseil::MENTIONS as $champ => $libelle)
                <tr>
                    <td class="gauche gras">{{ $libelle }}</td>
                    <td>{{ $ouiNon($d, $champ) }}</td>
                </tr>
                @endforeach
            </table>
            <table class="petit" style="width: 58%">
                <tr>
                    <th></th>
                    <th colspan="2">Avertissement</th>
                    <th colspan="2">Blâme</th>
                </tr>
                <tr>
                    <th></th>
                    <th>Travail</th>
                    <th>Discipline</th>
                    <th>Travail</th>
                    <th>Discipline</th>
                </tr>
                <tr>
                    <td></td>
                    <td>{{ $ouiNon($d, 'avertissement_travail') }}</td>
                    <td>{{ $ouiNon($d, 'avertissement_discipline') }}</td>
                    <td>{{ $ouiNon($d, 'blame_travail') }}</td>
                    <td>{{ $ouiNon($d, 'blame_discipline') }}</td>
                </tr>
            </table>
        </div>

        <div class="observations">
            <div class="t">Observations et décisions du conseil</div>
            <div class="obs">{{ $d->observation ?? '' }}</div>
            <div class="dec">{{ $d->decision ?? '' }}</div>
        </div>

        <div class="signatures">
            <div class="s">
                <b>Signature et nom<br>du professeur titulaire</b><br><br><br>
                {{ $classe->professeur->name ?? '' }}
            </div>
            <div class="s">
                Fait à {{ $ecole['ville'] }}, le {{ now()->format('d/m/Y') }}<br>
                <b>Le Directeur</b><br><br><br>
                {{ $ecole['directeur'] }}
            </div>
        </div>

        <div class="pied">
            @if(!empty($ecole['contact_developpeur']))Contact du développeur : {{ $ecole['contact_developpeur'] }}<br>@endif
            <i>Le présent bulletin ne peut être délivré qu'en un seul exemplaire</i>
        </div>
    </section>
    @endforeach
    @endforeach

    <script src="/offline-avertir.js"></script>
</body>

</html>