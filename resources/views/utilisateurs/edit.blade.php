@extends('layouts.app')

@section('title', "Modifier l'utilisateur")

@section('content')
<div class="container" style="max-width: 560px;">

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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white fw-bold text-center fs-5">
            Modifier le compte
        </div>
        <form action="{{ route('utilisateurs.update', $utilisateur) }}" method="POST" class="card-body">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label small fw-bold">Nom complet</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $utilisateur->name) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Adresse e-mail (identifiant)</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $utilisateur->email) }}" required>
            </div>

            @if($utilisateur->hasRole('admin'))
            <div class="mb-3"><span class="badge bg-secondary">ADMIN</span> <span class="small text-muted">Le rôle d'un administrateur ne se modifie pas ici.</span></div>
            @else
            <div class="mb-3">
                <label class="form-label small fw-bold">Rôle</label>
                <select name="role" class="form-select" required>
                    @foreach(\App\Models\User::ROLES_ATTRIBUABLES as $valeur => $libelle)
                    <option value="{{ $valeur }}" {{ old('role', $utilisateur->role) === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($utilisateur->id !== auth()->id())
            <div class="mb-3">
                <label class="form-label small fw-bold">Nouveau mot de passe provisoire (facultatif)</label>
                <input type="text" name="password" class="form-control" minlength="8" autocomplete="off" placeholder="Laisse vide pour ne pas le changer">
                <div class="form-text">À remplir si la personne a oublié son mot de passe. Elle devra le changer à sa prochaine connexion.</div>
            </div>
            @else
            <div class="small text-muted mb-3">Pour changer ton propre mot de passe, utilise la page « Mon compte ».</div>
            @endif

            <div class="d-flex gap-2 pt-2">
                <a href="{{ route('utilisateurs.index') }}" class="btn btn-secondary w-50 fw-bold">Retour</a>
                <button type="submit" class="btn btn-success w-50 fw-bold">Enregistrer</button>
            </div>
        </form>
    </div>

    @if($utilisateur->hasRole('professeur'))
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-2">Remplacer ce professeur</h5>
            <p class="small text-muted mb-3">
                {{ $utilisateur->name }} enseigne {{ $utilisateur->affectations_count }} matière(s)
                et est titulaire de {{ $utilisateur->classes_titulaire_count }} classe(s).
                Le transfert confie tout cela, en une fois, à un autre professeur. Les notes déjà saisies restent inchangées.
            </p>

            @if($utilisateur->affectations_count + $utilisateur->classes_titulaire_count === 0)
            <div class="small text-muted">Rien à transférer : ce professeur n'a ni matière ni classe.</div>
            @elseif($autresProfesseurs->isEmpty())
            <div class="alert alert-warning small py-2 mb-0">Crée d'abord le compte du nouveau professeur.</div>
            @else
            <form action="{{ route('utilisateurs.transferer', $utilisateur) }}" method="POST"
                onsubmit="return confirm('Transférer toutes les matières et les classes de ce professeur ?')">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-bold">Professeur qui prend la suite</label>
                    <select name="cible_id" class="form-select" required>
                        <option value="">Choisir...</option>
                        @foreach($autresProfesseurs as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold">Transférer ses matières et ses classes</button>
            </form>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection