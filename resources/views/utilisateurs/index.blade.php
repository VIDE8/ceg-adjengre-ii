@extends('layouts.app')

@section('title', 'Utilisateurs')

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
                    <h5 class="card-title fw-bold text-success mb-3">Nouveau compte</h5>

                    <form action="{{ route('utilisateurs.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nom complet</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="ex: M. MAMIDI" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Adresse e-mail (identifiant)</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="ex: mamidi@ecole.tg" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Rôle</label>
                            <select name="role" class="form-select" required>
                                @foreach(\App\Models\User::ROLES_ATTRIBUABLES as $valeur => $libelle)
                                <option value="{{ $valeur }}" {{ old('role', 'professeur') === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mot de passe provisoire</label>
                            <div class="input-group">
                                <input type="text" name="password" id="mdp" class="form-control" value="{{ old('password') }}" minlength="8" required>
                                <button type="button" id="generer" class="btn btn-outline-secondary">Générer</button>
                            </div>
                            <div class="form-text">Visible pour que tu puisses le communiquer. La personne devra le changer à sa première connexion.</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Créer le compte</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Comptes ({{ $utilisateurs->count() }})</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Charge</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($utilisateurs as $u)
                                <tr class="{{ $u->actif ? '' : 'text-muted' }}">
                                    <td>
                                        <span class="fw-bold">{{ $u->name }}</span>
                                        <div class="small text-muted">{{ $u->email }}</div>
                                    </td>
                                    <td><span class="badge bg-secondary">{{ strtoupper($u->role) }}</span></td>
                                    <td>
                                        @if($u->actif)
                                        <span class="badge bg-success">Actif</span>
                                        @else
                                        <span class="badge bg-danger">Désactivé</span>
                                        @endif
                                        @if($u->doit_changer_mot_de_passe)
                                        <div class="small text-warning">Mot de passe provisoire</div>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($u->role === 'professeur')
                                        {{ $u->affectations_count }} matière(s)
                                        @if($u->classes_titulaire_count > 0)
                                        <div class="text-success">Titulaire de {{ $u->classes_titulaire_count }} classe(s)</div>
                                        @endif
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('utilisateurs.edit', $u) }}" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                        @if($u->id !== auth()->id())
                                        <form action="{{ route('utilisateurs.statut', $u) }}" method="POST" class="d-inline form-confirmer"
                                            data-message="{{ $u->actif ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $u->actif ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $u->actif ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        Un compte désactivé ne peut plus se connecter, mais ses notes et son historique sont conservés.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Demande confirmation avant de désactiver ou de réactiver un compte
    document.querySelectorAll('form.form-confirmer').forEach(function(formulaire) {
        formulaire.addEventListener('submit', function(e) {
            if (!confirm(formulaire.dataset.message)) {
                e.preventDefault();
            }
        });
    });

    document.getElementById('generer').addEventListener('click', function() {
        var caracteres = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var tirage = new Uint32Array(10);
        crypto.getRandomValues(tirage);
        var mot = '';
        for (var i = 0; i < tirage.length; i++) {
            mot += caracteres.charAt(tirage[i] % caracteres.length);
        }
        document.getElementById('mdp').value = mot;
    });
</script>
@endsection