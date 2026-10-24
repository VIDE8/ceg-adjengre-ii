@extends('layouts.app')

@section('title', 'Écolage')

@section('content')
<div class="container my-4" style="max-width: 860px;">

    <h4 class="fw-bold mb-1">Écolage de base par classe</h4>
    <p class="text-muted small mb-4">
        Montant total de l'écolage d'un élève pour l'année scolaire. Il change d'une année à l'autre : on le définit ici pour chaque classe.
        Un élève ne pourra pas verser plus que ce montant.
    </p>

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

    @if(!$annee)
    <div class="alert alert-warning">Aucune année scolaire n'existe. Le directeur doit d'abord en créer une.</div>
    @elseif($classes->isEmpty())
    <div class="alert alert-warning">Aucune classe n'existe pour le moment.</div>
    @else

    <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
        <form action="{{ route('ecolage.index') }}" method="GET" class="d-flex align-items-center gap-2">
            <label class="small fw-bold mb-0">Année scolaire</label>
            <select name="annee_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($annees as $a)
                <option value="{{ $a->id }}" {{ $a->id === $annee->id ? 'selected' : '' }}>{{ $a->libelle }}{{ $a->active ? ' (active)' : '' }}</option>
                @endforeach
            </select>
        </form>

        @if($annees->count() > 1)
        <form action="{{ route('ecolage.index') }}" method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="annee_id" value="{{ $annee->id }}">
            <label class="small fw-bold mb-0">Reprendre les montants de</label>
            <select name="reprendre" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">&mdash;</option>
                @foreach($annees->where('id', '!=', $annee->id) as $a)
                <option value="{{ $a->id }}" {{ $reprise && $reprise->id === $a->id ? 'selected' : '' }}>{{ $a->libelle }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    @if($reprise)
    <div class="alert alert-info small py-2">
        Les cases vides sont préremplies avec les montants de <b>{{ $reprise->libelle }}</b>. Modifie ce qui a changé, puis enregistre.
    </div>
    @endif

    <form action="{{ route('ecolage.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="annee_scolaire_id" value="{{ $annee->id }}">

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Classe</th>
                                <th class="text-center">Élèves</th>
                                <th style="width: 260px">Écolage de base (F)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classes as $c)
                            @php
                            $enregistre = $tarifs->get($c->id);
                            $propose = $enregistre === null ? $montantsReprise->get($c->id) : null;
                            $valeur = old('montants.' . $c->id, $enregistre ?? $propose);
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $c->niveau }} {{ $c->nom_classe }}</td>
                                <td class="text-center">{{ $effectifs->get($c->id, 0) }}</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" inputmode="numeric" name="montants[{{ $c->id }}]"
                                            class="form-control text-end {{ $enregistre === null && $propose === null ? 'border-warning' : '' }}"
                                            value="{{ $valeur !== null && $valeur !== '' ? number_format((int) $valeur, 0, ',', ' ') : '' }}"
                                            placeholder="Non défini">
                                        <span class="input-group-text">F</span>
                                    </div>
                                    @if($enregistre === null && $propose === null)
                                    <div class="small text-warning">Écolage non défini : aucun paiement d'écolage possible pour cette classe.</div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end pt-3">
                    <button type="submit" class="btn btn-success fw-bold px-4">Enregistrer l'écolage</button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection