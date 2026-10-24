<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CEG ADJENGRE II')</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#006a4e">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CEG ADJENGRE II">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CDN Principal -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Sélecteur de date au format jj/mm/aaaa -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fa;
        }

        .text-violet {
            color: #6f42c1 !important;
        }

        .navbar .nav-link {
            font-weight: 600;
        }

        .navbar .nav-link.active {
            color: #198754 !important;
            font-weight: 700;
        }
    </style>

    @yield('head')
</head>

<body>
    <div id="app">
        @php
        $user = auth()->user();
        $estAdmin = $user && $user->hasRole('admin');
        $estComptable = $user && $user->hasRole('comptable');
        $estProfesseur = $user && $user->hasRole('professeur');
        $estSurveillant = $user && $user->hasRole('surveillant');

        // Classes dont le professeur connecté est titulaire
        $mesClasses = $estProfesseur
        ? \App\Models\Classe::where('professeur_id', $user->id)->orderBy('niveau')->orderBy('nom_classe')->get()
        : collect();

        $scolariteActive = request()->routeIs('annees_scolaires.*', 'trimestres.*', 'matieres.*', 'affectations.*', 'ecolage.*');
        $vieScolaireActive = request()->routeIs('comportements.index_global', 'comportements.store_global', 'presences.statistiques.index', 'retards.*', 'permissions.*');
        @endphp

        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 mb-4">
            <div class="container">
                <a class="navbar-brand fw-bold text-success" href="{{ url('/') }}">
                    🏫 CEG ADJENGRE II
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                @auth
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                        {{-- ADMINISTRATEUR --}}
                        @if($estAdmin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home', 'classes.*') ? 'active' : '' }}" href="{{ route('home') }}">Gestion des Classes</a>
                        </li>
                        @endif

                        {{-- ADMINISTRATEUR et COMPTABLE --}}
                        @if($estAdmin || $estComptable)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('eleves.*') ? 'active' : '' }}" href="{{ route('eleves.index') }}">Registre des Élèves</a>
                        </li>
                        @endif

                        @if($estAdmin)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $scolariteActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Scolarité</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item {{ request()->routeIs('annees_scolaires.*') ? 'active' : '' }}" href="{{ route('annees_scolaires.index') }}">Années Scolaires</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('trimestres.*') ? 'active' : '' }}" href="{{ route('trimestres.index') }}">Trimestres</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('matieres.*') ? 'active' : '' }}" href="{{ route('matieres.index') }}">Matières</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('affectations.*') ? 'active' : '' }}" href="{{ route('affectations.index') }}">Affectations</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('ecolage.*') ? 'active' : '' }}" href="{{ route('ecolage.index') }}">Écolage</a></li>
                            </ul>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $vieScolaireActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Vie scolaire</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item {{ request()->routeIs('comportements.index_global') ? 'active' : '' }}" href="{{ route('comportements.index_global') }}">Comportement</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('presences.statistiques.index') ? 'active' : '' }}" href="{{ route('presences.statistiques.index') }}">Statistiques de présence</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('retards.*') ? 'active' : '' }}" href="{{ route('retards.index') }}">Retards</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}" href="{{ route('permissions.index') }}">Permissions</a></li>
                            </ul>
                        </li>
                        @endif

                        @if($estAdmin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('utilisateurs.*') ? 'active' : '' }}" href="{{ route('utilisateurs.index') }}">Utilisateurs</a>
                        </li>
                        @endif

                        {{-- COMPTABLE --}}
                        @if($estComptable)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('paiements.*') ? 'active' : '' }}" href="{{ route('paiements.index') }}">Comptabilité / Paiements</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('ecolage.*') ? 'active' : '' }}" href="{{ route('ecolage.index') }}">Écolage</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('depenses.*') ? 'active' : '' }}" href="{{ route('depenses.index') }}">Dépenses</a>
                        </li>
                        @endif

                        {{-- SURVEILLANT --}}
                        @if($estSurveillant)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('retards.*') ? 'active' : '' }}" href="{{ route('retards.index') }}">Retards</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}" href="{{ route('permissions.index') }}">Permissions</a>
                        </li>
                        @endif

                        {{-- PROFESSEUR --}}
                        @if($estProfesseur)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notes.*') ? 'active' : '' }}" href="{{ route('notes.index') }}">Notes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('appels.index', 'appels.enregistrer') ? 'active' : '' }}" href="{{ route('appels.index') }}">Appel</a>
                        </li>

                        {{-- Professeur titulaire : un menu par classe dont il est titulaire --}}
                        @foreach($mesClasses as $classe)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Ma classe : {{ $classe->niveau }} {{ $classe->nom_classe }}
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item {{ request()->routeIs('recapitulatif.*') ? 'active' : '' }}" href="{{ route('recapitulatif.show', $classe) }}">Résultats de la classe</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('conseil.*') ? 'active' : '' }}" href="{{ route('conseil.show', $classe) }}">Conseil de classe</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('bulletins.*') ? 'active' : '' }}" href="{{ route('bulletins.classe', $classe) }}" target="_blank">Bulletins</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('appels.synthese') ? 'active' : '' }}" href="{{ route('appels.synthese', $classe) }}">Absences de la classe</a></li>
                                <li><a class="dropdown-item" href="{{ route('comportements.index', $classe) }}">Comportement</a></li>
                                <li><a class="dropdown-item" href="{{ route('presences.statistiques', $classe) }}">Statistiques</a></li>
                            </ul>
                        </li>
                        @endforeach
                        @endif
                    </ul>

                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('compte.edit') }}" class="text-muted small text-decoration-none" title="Mon compte">
                            {{ $user->name }}
                            <span class="badge bg-secondary">{{ strtoupper($user->role) }}</span>
                        </a>
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>
        </nav>

        <main>
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap 5 JavaScript CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Sélecteur de date (flatpickr) en français : affichage jj/mm/aaaa, envoi aaaa-mm-jj au serveur -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/fr.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sans internet, on garde simplement le champ date du navigateur
            if (typeof flatpickr === 'undefined') {
                return;
            }

            flatpickr.localize(flatpickr.l10ns.fr);

            document.querySelectorAll('input[type="date"]').forEach(function(champ) {
                flatpickr(champ, {
                    altInput: true,
                    altFormat: 'd/m/Y',
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    locale: 'fr'
                });
            });
        });
    </script>

    @yield('scripts')

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').catch(function() {});
            });
        }
    </script>
</body>

</html>