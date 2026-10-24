<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billet d'entrée n° {{ $retard->numero }}</title>
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
            gap: 16px;
            align-items: center;
            font-family: Arial, sans-serif;
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
            font-size: 14px;
        }

        .feuille {
            width: 148mm;
            min-height: 210mm;
            margin: 14px auto;
            background: #fff;
            padding: 8mm;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
        }

        .billet {
            height: 93mm;
            border: 1.5px solid #000;
            padding: 4mm 6mm;
            font-size: 10.5pt;
            position: relative;
        }

        .coupure {
            text-align: center;
            font-size: 8pt;
            letter-spacing: 2px;
            margin: 4mm 0;
            border-top: 1.5px dashed #555;
            padding-top: 1mm;
            color: #555;
        }

        .entete {
            display: flex;
            align-items: center;
            gap: 4mm;
            border-bottom: 1px solid #000;
            padding-bottom: 2mm;
        }

        .entete img {
            height: 15mm;
        }

        .entete .ecole {
            flex: 1;
            text-align: center;
            font-size: 8pt;
            line-height: 1.25;
        }

        .entete .ecole b {
            font-size: 10pt;
        }

        .titre {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin: 3mm 0 2mm;
            letter-spacing: 1px;
        }

        .numero {
            text-align: center;
            font-size: 9pt;
            margin-bottom: 3mm;
        }

        .ligne {
            margin: 1.6mm 0;
        }

        .ligne b {
            display: inline-block;
            min-width: 32mm;
        }

        .autorisation {
            margin-top: 3mm;
            font-weight: bold;
            text-align: center;
        }

        .signature {
            position: absolute;
            right: 6mm;
            bottom: 3mm;
            text-align: center;
            font-size: 9pt;
        }

        .exemplaire {
            position: absolute;
            left: 6mm;
            bottom: 3mm;
            font-size: 8pt;
            font-style: italic;
        }

        @page {
            size: A5 portrait;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .feuille {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body data-imprimer="{{ request('imprimer') ? '1' : '0' }}">
    @php
    $ecole = config('ecole');
    $logo = !empty($ecole['logo']) && file_exists(public_path($ecole['logo'])) ? asset($ecole['logo']) : null;
    $eleve = $retard->eleve;
    $classe = $eleve->classe;
    @endphp

    <div class="barre-outils no-print">
        <button type="button" onclick="window.print()">Imprimer le billet</button>
        <a href="{{ route('retards.index', ['date' => $retard->date->toDateString()]) }}">&larr; Retards</a>
        <span>Billet n° {{ $retard->numero }} &ndash; retard n° {{ $rang }} de l'année pour cet élève</span>
    </div>

    <div class="feuille">
        @foreach(['Exemplaire de l\'élève (à remettre au professeur)', 'Souche du surveillant'] as $exemplaire)
        <div class="billet">
            <div class="entete">
                @if($logo)<img src="{{ $logo }}" alt="Logo">@endif
                <div class="ecole">
                    {{ $ecole['republique'] }} &ndash; {{ $ecole['devise_nationale'] }}<br>
                    {{ $ecole['ministere'] }}<br>
                    <b>{{ $ecole['nom'] }}</b>
                </div>
            </div>

            <div class="titre">BILLET D'ENTRÉE</div>
            <div class="numero">N° {{ $retard->numero }}</div>

            <div class="ligne"><b>Nom et prénom(s) :</b> {{ mb_strtoupper($eleve->nom) }} {{ $eleve->prenom }}</div>
            <div class="ligne"><b>Classe :</b> {{ $classe ? $classe->niveau . ' ' . $classe->nom_classe : '—' }}</div>
            <div class="ligne"><b>Date :</b> {{ $retard->date->format('d/m/Y') }} &nbsp;&nbsp;&nbsp; <b style="min-width:0">Heure d'arrivée :</b> {{ substr($retard->heure, 0, 5) }}</div>
            <div class="ligne"><b>Motif du retard :</b> {{ $retard->motif ?: '........................................' }}</div>

            <div class="autorisation">L'élève est autorisé(e) à entrer en classe.</div>

            <div class="exemplaire">{{ $exemplaire }}</div>
            <div class="signature">Le surveillant<br><br>{{ $retard->enregistrePar->name ?? '' }}</div>
        </div>
        @if($loop->first)
        <div class="coupure">&#9986; &nbsp; - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
        @endif
        @endforeach
    </div>

    <script>
        // Ouvre directement la fenêtre d'impression juste après l'enregistrement du retard
        if (document.body.dataset.imprimer === '1') {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 300);
            });
        }
    </script>
</body>

</html>