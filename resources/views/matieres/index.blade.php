@extends('layouts.app')

@section('title', 'Matières')

@section('content')
<div class="container my-4">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-success mb-3">Nouvelle Matière</h5>

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

                    <form action="{{ route('matieres.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nom de la matière</label>
                            <input type="text" name="nom" class="form-control" placeholder="ex: Mathématiques" value="{{ old('nom') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Code (facultatif)</label>
                            <input type="text" name="code" class="form-control" placeholder="ex: MATH" maxlength="10" value="{{ old('code') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Groupe (moyennes Littérature / Science)</label>
                            <select name="groupe" class="form-select" required>
                                @foreach(\App\Models\Matiere::GROUPES as $valeur => $libelle)
                                <option value="{{ $valeur }}" {{ old('groupe', 'autre') === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold text-white">Enregistrer la matière</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Liste des Matières</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom</th>
                                    <th style="width:110px">Code</th>
                                    <th style="width:160px">Groupe</th>
                                    <th class="text-end" style="width:200px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($matieres as $matiere)
                                <tr>
                                    <td>
                                        <input type="text" name="nom" form="edit-{{ $matiere->id }}" class="form-control form-control-sm fw-bold" value="{{ $matiere->nom }}" required>
                                    </td>
                                    <td>
                                        <input type="text" name="code" form="edit-{{ $matiere->id }}" class="form-control form-control-sm" maxlength="10" value="{{ $matiere->code }}">
                                    </td>
                                    <td>
                                        <select name="groupe" form="edit-{{ $matiere->id }}" class="form-select form-select-sm">
                                            @foreach(\App\Models\Matiere::GROUPES as $valeur => $libelle)
                                            <option value="{{ $valeur }}" {{ $matiere->groupe === $valeur ? 'selected' : '' }}>{{ $libelle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <form id="edit-{{ $matiere->id }}" action="{{ route('matieres.update', $matiere) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-outline-success">Modifier</button>
                                        </form>
                                        <form action="{{ route('matieres.destroy', $matiere) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Supprimer cette matière ? Ses affectations seront aussi supprimées.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted small py-4">Aucune matière pour le moment.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection