@extends('layouts.app')

@section('title', "Modifier l'année scolaire")

@section('content')
<div class="container" style="max-width: 500px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold text-center fs-5">
            Modifier l'année scolaire
        </div>
        <form action="{{ route('annees_scolaires.update', $annee) }}" method="POST" class="card-body">
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
                <label class="form-label small fw-bold">Libellé</label>
                <input type="text" name="libelle" class="form-control" value="{{ old('libelle', $annee->libelle) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Date de début</label>
                <input type="date" name="date_debut" class="form-control" value="{{ old('date_debut', $annee->date_debut->format('Y-m-d')) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Date de fin</label>
                <input type="date" name="date_fin" class="form-control" value="{{ old('date_fin', $annee->date_fin->format('Y-m-d')) }}" required>
            </div>

            @if($annee->active)
            <div class="mb-3"><span class="badge bg-success">Année active</span></div>
            @else
            <div class="form-check mb-3">
                <input type="checkbox" name="active" value="1" class="form-check-input" id="activeCheck" {{ old('active') ? 'checked' : '' }}>
                <label class="form-check-label small" for="activeCheck">Définir comme année active</label>
            </div>
            @endif

            <div class="d-flex gap-2 pt-2">
                <a href="{{ route('annees_scolaires.index') }}" class="btn btn-secondary w-50 fw-bold">Annuler</a>
                <button type="submit" class="btn btn-success w-50 fw-bold">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection