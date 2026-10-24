@extends('layouts.app')

@section('title', 'Modifier le trimestre')

@section('content')
<div class="container" style="max-width: 500px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold text-center fs-5">
            Modifier le trimestre
        </div>
        <form action="{{ route('trimestres.update', $trimestre) }}" method="POST" class="card-body">
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
                <label class="form-label small fw-bold">Année scolaire</label>
                <select name="annee_scolaire_id" class="form-select" required>
                    @foreach($anneesScolaires as $annee)
                    <option value="{{ $annee->id }}" {{ (string) old('annee_scolaire_id', $trimestre->annee_scolaire_id) === (string) $annee->id ? 'selected' : '' }}>{{ $annee->libelle }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Numéro du trimestre</label>
                <select name="numero" class="form-select" required>
                    @foreach([1 => '1er trimestre', 2 => '2ème trimestre', 3 => '3ème trimestre'] as $valeur => $libelle)
                    <option value="{{ $valeur }}" {{ (int) old('numero', $trimestre->numero) === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Date de début</label>
                <input type="date" name="date_debut" class="form-control" value="{{ old('date_debut', $trimestre->date_debut->format('Y-m-d')) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Date de fin</label>
                <input type="date" name="date_fin" class="form-control" value="{{ old('date_fin', $trimestre->date_fin->format('Y-m-d')) }}" required>
            </div>

            <div class="d-flex gap-2 pt-2">
                <a href="{{ route('trimestres.index') }}" class="btn btn-secondary w-50 fw-bold">Annuler</a>
                <button type="submit" class="btn btn-success w-50 fw-bold">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection