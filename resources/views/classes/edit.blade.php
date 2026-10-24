@extends('layouts.app')

@section('title', 'Modifier la classe')

@section('content')
<div class="container" style="max-width: 500px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold text-center fs-5">
            Modifier la classe
        </div>
        <form action="{{ route('classes.update', $classe->id) }}" method="POST" class="card-body">
            @csrf
            @method('PUT')

            @if($errors->any())
            <div class="alert alert-danger small py-2">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="mb-3">
                <label class="form-label small fw-bold">Niveau</label>
                <select name="niveau" class="form-select" required>
                    @foreach(['6ème' => 'Sixième (6ème)', '5ème' => 'Cinquième (5ème)', '4ème' => 'Quatrième (4ème)', '3ème' => 'Troisième (3ème)'] as $valeur => $libelle)
                    <option value="{{ $valeur }}" {{ old('niveau', $classe->niveau) === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Nom / Section de la classe</label>
                <input type="text" name="nom_classe" class="form-control" maxlength="20" value="{{ old('nom_classe', $classe->nom_classe) }}" placeholder="ex: A, B, C ou Groupe 1" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Professeur titulaire</label>
                <select name="professeur_id" class="form-select">
                    <option value="">Aucun professeur assigné</option>
                    @foreach($professeurs as $professeur)
                    <option value="{{ $professeur->id }}" {{ (string) old('professeur_id', $classe->professeur_id) === (string) $professeur->id ? 'selected' : '' }}>{{ $professeur->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Le professeur titulaire gère la classe et son bulletin.</div>
            </div>

            <div class="d-flex gap-2 pt-2">
                <a href="{{ route('home') }}" class="btn btn-secondary w-50 fw-bold">Annuler</a>
                <button type="submit" class="btn btn-success w-50 fw-bold">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection