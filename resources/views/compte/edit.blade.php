@extends('layouts.app')

@section('title', 'Mon compte')

@section('content')
<div class="container" style="max-width: 520px;">

    @if($user->doit_changer_mot_de_passe)
    <div class="alert alert-warning small">
        Ton mot de passe est provisoire. Choisis un nouveau mot de passe pour continuer.
    </div>
    @endif

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

    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold text-center fs-5">
            Mon compte
        </div>
        <form action="{{ route('compte.update') }}" method="POST" class="card-body">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label small fw-bold">Nom</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Adresse e-mail (identifiant de connexion)</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="mb-1"><span class="badge bg-secondary">{{ strtoupper($user->role) }}</span></div>
            <hr>

            <div class="small text-muted mb-3">
                @if($user->doit_changer_mot_de_passe)
                Tape ton mot de passe provisoire, puis choisis le nouveau.
                @else
                Pour changer ton e-mail ou ton mot de passe, tape d'abord ton mot de passe actuel.
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Mot de passe actuel</label>
                <input type="password" name="password_actuel" class="form-control" autocomplete="current-password">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Nouveau mot de passe {{ $user->doit_changer_mot_de_passe ? '' : '(facultatif)' }}</label>
                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" {{ $user->doit_changer_mot_de_passe ? 'required' : '' }}>
                <div class="form-text">Au moins 8 caractères.</div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Confirmer le nouveau mot de passe</label>
                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-success w-100 fw-bold">Enregistrer</button>
        </form>
    </div>
</div>
@endsection