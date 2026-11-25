@extends('layouts.app')

@section('title', 'Gestion des Enseignants')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Gestion des Enseignants & Affectations</h1>
        <p style="color: var(--text-muted);">Création des formateurs et attribution de leurs groupes et modules</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn btn-secondary" onclick="openAssignModal()">
            <x-icon name="tag" size="16" /> Affecter à un Groupe
        </button>
        <button class="btn btn-primary" onclick="openCreateEnseignantModal()">
            <x-icon name="plus" size="16" /> Nouvel Enseignant
        </button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Formateur</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Groupes & Modules Attribués</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($enseignants as $ens)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <img src="{{ $ens->avatarUrl() }}" class="user-avatar" style="width: 34px; height: 34px;">
                            <strong>{{ $ens->name }}</strong>
                        </div>
                    </td>
                    <td style="font-family: var(--font-mono); font-size: 0.82rem;">{{ $ens->email }}</td>
                    <td>{{ $ens->telephone ?? 'Non renseigné' }}</td>
                    <td>
                        <span class="badge {{ $ens->isActif() ? 'badge-success' : 'badge-danger' }}">
                            {{ $ens->isActif() ? 'Actif' : 'Désactivé' }}
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            @forelse($ens->enseignantGroupes as $eg)
                                <span class="badge badge-primary" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                    {{ $eg->groupe->code_groupe ?? 'N/A' }} ({{ $eg->matiere }})
                                    <form action="{{ route('admin.enseignants.unassign', $eg->id) }}" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Retirer cette affectation ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; color: inherit; cursor: pointer; font-weight: 800; padding: 0;">✕</button>
                                    </form>
                                </span>
                            @empty
                                <span style="color: var(--text-dim); font-size: 0.8rem;">Aucun groupe attribué</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openAssignModalFor({{ $ens->id }})">
                            <x-icon name="plus" size="13" /> Affecter groupe
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                        Aucun enseignant enregistré.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nouvel Enseignant -->
<div id="createEnseignantModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Ajouter un Enseignant</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeCreateEnseignantModal()">✕</button>
        </div>
        <form action="{{ route('admin.enseignants.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom Complet</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Rachid Bennani" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="rachid@edu.ma" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" placeholder="+212 6 XX XX XX XX">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Groupe initial (optionnel)</label>
                        <select name="groupe_id" class="form-select">
                            <option value="">-- Affecter plus tard --</option>
                            @foreach($groupes as $g)
                                <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Matière / Module</label>
                        <input type="text" name="matiere" class="form-control" placeholder="Ex: Développement Web">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateEnseignantModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer l'Enseignant</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Affectation Groupe -->
<div id="assignModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Affecter un Enseignant à un Groupe</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeAssignModal()">✕</button>
        </div>
        <form action="{{ route('admin.enseignants.assign') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Enseignant</label>
                    <select name="enseignant_id" id="assign_enseignant_id" class="form-select" required>
                        @foreach($enseignants as $ens)
                            <option value="{{ $ens->id }}">{{ $ens->name }} ({{ $ens->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Groupe</label>
                    <select name="groupe_id" class="form-select" required>
                        @foreach($groupes as $g)
                            <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Module / Matière enseignée</label>
                    <input type="text" name="matiere" class="form-control" placeholder="Ex: PHP / Laravel" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer l'Affectation</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateEnseignantModal() {
        document.getElementById('createEnseignantModal').classList.add('active');
    }
    function closeCreateEnseignantModal() {
        document.getElementById('createEnseignantModal').classList.remove('active');
    }
    function openAssignModal() {
        document.getElementById('assignModal').classList.add('active');
    }
    function openAssignModalFor(ensId) {
        document.getElementById('assign_enseignant_id').value = ensId;
        openAssignModal();
    }
    function closeAssignModal() {
        document.getElementById('assignModal').classList.remove('active');
    }
</script>
@endsection
