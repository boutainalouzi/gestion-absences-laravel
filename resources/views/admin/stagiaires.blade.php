@extends('layouts.app')

@section('title', 'Gestion des Stagiaires')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Gestion des Dossiers Stagiaires</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Enregistrement, affectation aux groupes, CNE et suivi de la discipline</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateStagiaireModal()">
        <x-icon name="plus" size="16" /> Inscrire un Stagiaire
    </button>
</div>

@if($stagiairesSansProfil->count() > 0)
<div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid var(--warning);">
    <div class="card-title">
        <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--warning);">
            <x-icon name="alert-triangle" size="18" />
            Comptes Stagiaires sans Dossier Académique ({{ $stagiairesSansProfil->count() }})
        </span>
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
        Ces comptes ont le rôle <strong>stagiaire</strong> mais ne possèdent pas encore d'entrée dans la table <code>stagiaires</code>. Associez-les à un groupe ci-dessous :
    </p>
    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
        @foreach($stagiairesSansProfil as $orphan)
        <form action="{{ route('admin.stagiaires.link') }}" method="POST" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; background: var(--bg-tertiary); padding: 0.65rem; border-radius: var(--radius-md);">
            @csrf
            <input type="hidden" name="user_id" value="{{ $orphan->id }}">
            <div style="min-width: 200px;">
                <strong>{{ $orphan->name }}</strong>
                <div style="font-size: 0.75rem; color: var(--text-dim);">{{ $orphan->email }}</div>
            </div>
            <input type="text" name="matricule_cne" class="form-control" placeholder="Matricule CNE (ex: M139999)" value="CNE{{ str_pad($orphan->id, 6, '0', STR_PAD_LEFT) }}" style="max-width: 180px;" required>
            <select name="groupe_id" class="form-select" style="max-width: 220px;" required>
                @foreach($groupes as $g)
                    <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Compléter le profil</button>
        </form>
        @endforeach
    </div>
</div>
@endif

<!-- Filtres -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form action="{{ route('admin.stagiaires') }}" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 220px;">
            <label class="form-label">Rechercher (Nom, Email ou CNE)</label>
            <input type="text" name="search" class="form-control" placeholder="Nom, email, CNE..." value="{{ request('search') }}">
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
            <label class="form-label">Groupe</label>
            <select name="groupe_id" class="form-select">
                <option value="">Tous les groupes</option>
                @foreach($groupes as $g)
                    <option value="{{ $g->id }}" {{ request('groupe_id') == $g->id ? 'selected' : '' }}>
                        {{ $g->code_groupe }} - {{ $g->nom_groupe }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 42px;">
            <x-icon name="filter" size="14" /> Filtrer
        </button>
        <a href="{{ route('admin.stagiaires') }}" class="btn btn-secondary btn-sm" style="height: 42px;">Réinitialiser</a>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Stagiaire</th>
                    <th>CNE / Matricule</th>
                    <th>Groupe</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Note Discipline (sur 20)</th>
                    <th>Total Absences</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stagiaires as $st)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <img src="{{ $st->user ? $st->user->avatarUrl() : 'https://api.dicebear.com/7.x/initials/svg?seed=Stagiaire' }}" class="user-avatar" style="width: 32px; height: 32px;">
                            <div>
                                <strong>{{ $st->user->name ?? 'N/A' }}</strong>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-info" style="font-family: var(--font-mono);">{{ $st->matricule_cne }}</span></td>
                    <td><span class="badge badge-primary">{{ $st->groupe->code_groupe ?? 'N/A' }}</span></td>
                    <td style="font-size: 0.8rem; font-family: var(--font-mono);">{{ $st->user->email ?? 'N/A' }}</td>
                    <td>{{ $st->user->telephone ?? 'Non renseigné' }}</td>
                    <td>
                        <strong style="color: {{ $st->note_discipline >= 16.0 ? 'var(--ofppt-green)' : ($st->note_discipline >= 12.0 ? 'var(--info)' : 'var(--danger)') }}; font-size: 1rem;">
                            {{ number_format($st->note_discipline, 2) }} / 20.00
                        </strong>
                    </td>
                    <td>
                        <span class="badge badge-secondary">{{ $st->absences->count() }} absences</span>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                            <a href="{{ route('admin.conseils.index', ['search_stagiaire' => $st->matricule_cne]) }}" class="btn btn-secondary btn-sm" title="Voir les conseils de discipline">
                                <x-icon name="scale" size="14" />
                            </a>
                            <button type="button" class="btn btn-secondary btn-sm"
                                onclick='openEditStagiaireModal({{ $st->id }}, {{ Js::from($st->user->name ?? "") }}, {{ Js::from($st->user->email ?? "") }}, {{ Js::from($st->matricule_cne) }}, {{ $st->groupe_id }}, {{ Js::from($st->user->telephone ?? "") }}, {{ Js::from($st->date_naissance ? $st->date_naissance->format("Y-m-d") : "") }}, {{ Js::from($st->adresse ?? "") }})'>
                                <x-icon name="edit" size="14" />
                            </button>
                            <form action="{{ route('admin.stagiaires.destroy', $st->id) }}" method="POST" style="margin: 0;" onsubmit="return confirm('Supprimer ce stagiaire et son compte utilisateur ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <x-icon name="trash" size="14" />
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                        Aucun stagiaire trouvé.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $stagiaires->links() }}
    </div>
</div>

<!-- Modal Inscription Stagiaire -->
<div id="createStagiaireModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Inscrire un Nouveau Stagiaire</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeCreateStagiaireModal()">✕</button>
        </div>
        <form action="{{ route('admin.stagiaires.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom et Prénom</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Youssef El Mansouri" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Numéro CNE / Matricule</label>
                        <input type="text" name="matricule_cne" class="form-control" placeholder="Ex: M139045812" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Groupe d'affectation</label>
                        <select name="groupe_id" class="form-select" required>
                            @foreach($groupes as $g)
                                <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Adresse Email</label>
                        <input type="email" name="email" class="form-control" placeholder="youssef@edu.ma" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mot de passe initial (enregistré en BDD)</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" class="form-control" placeholder="+212 6 XX XX XX XX">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date de Naissance</label>
                        <input type="date" name="date_naissance" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Ville, Maroc">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateStagiaireModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer le Dossier & Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modification Stagiaire -->
<div id="editStagiaireModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Modifier le Dossier Stagiaire</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeEditStagiaireModal()">✕</button>
        </div>
        <form id="editStagiaireForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom et Prénom</label>
                    <input type="text" name="name" id="edit_st_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Numéro CNE / Matricule</label>
                        <input type="text" name="matricule_cne" id="edit_st_cne" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Groupe d'affectation</label>
                        <select name="groupe_id" id="edit_st_groupe" class="form-select" required>
                            @foreach($groupes as $g)
                                <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_st_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" id="edit_st_tel" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date de Naissance</label>
                        <input type="date" name="date_naissance" id="edit_st_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="adresse" id="edit_st_adresse" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditStagiaireModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateStagiaireModal() {
        document.getElementById('createStagiaireModal').classList.add('active');
    }
    function closeCreateStagiaireModal() {
        document.getElementById('createStagiaireModal').classList.remove('active');
    }
    function openEditStagiaireModal(id, name, email, cne, groupeId, tel, dob, adresse) {
        document.getElementById('editStagiaireForm').action = '/admin/stagiaires/' + id;
        document.getElementById('edit_st_name').value = name;
        document.getElementById('edit_st_email').value = email;
        document.getElementById('edit_st_cne').value = cne;
        document.getElementById('edit_st_groupe').value = groupeId;
        document.getElementById('edit_st_tel').value = tel;
        document.getElementById('edit_st_dob').value = dob;
        document.getElementById('edit_st_adresse').value = adresse;
        document.getElementById('editStagiaireModal').classList.add('active');
    }
    function closeEditStagiaireModal() {
        document.getElementById('editStagiaireModal').classList.remove('active');
    }
</script>
@endsection
