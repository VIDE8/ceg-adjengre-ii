@extends('layouts.app')

@section('title', 'Modifier le paiement')

@section('content')
<div class="container" style="max-width: 500px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-warning text-dark fw-bold text-center fs-5">
            Modifier le paiement
        </div>
        <form action="{{ route('paiements.update', $paiement->id) }}" method="POST" class="card-body">
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
                <label class="form-label small fw-bold text-secondary">Élève</label>
                <input type="text" class="form-control bg-light text-uppercase fw-bold" value="{{ $paiement->eleve->nom }} {{ $paiement->eleve->prenom }}" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">Référence du reçu</label>
                <input type="text" class="form-control bg-light" value="{{ $paiement->reference_recu }}" readonly>
            </div>

            <!-- Champ invisible qui force la valeur scolarite en arrière-plan -->
            <input type="hidden" name="type_frais" value="scolarite">

            <div class="mb-3">
                <label class="form-label small fw-bold">Montant enregistré (FCFA)</label>
                <input type="number" name="montant_verse" class="form-control" value="{{ old('montant_verse', $paiement->montant_verse + 0) }}" min="1" @if($plafond !==null) max="{{ $plafond }}" @endif required>
                @if($situation['du'] === null)
                <div class="form-text text-warning">L'écolage de cette classe n'est pas défini : le montant ne peut pas être contrôlé.</div>
                @else
                <div class="form-text">Écolage de la classe : {{ number_format($situation['du'], 0, ',', ' ') }} F &middot; maximum pour ce reçu : {{ number_format($plafond, 0, ',', ' ') }} F.</div>
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Date du versement</label>
                <input type="date" name="date_paiement" class="form-control" value="{{ old('date_paiement', \Carbon\Carbon::parse($paiement->date_paiement)->format('Y-m-d')) }}" required>
            </div>

            <div class="d-flex gap-2 pt-2">
                <a href="{{ route('paiements.index') }}" class="btn btn-secondary w-50 fw-bold">Annuler</a>
                <button type="submit" class="btn btn-warning w-50 fw-bold">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>
@endsection