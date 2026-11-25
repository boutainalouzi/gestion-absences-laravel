@extends('layouts.app')

@section('title', 'Gestion des Comptes')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Gestion des Comptes Utilisateurs</h1>
        <p style="color: var(--text-muted);">Création, modification, gestion des rôles, droits d'accès et activation/désactivation</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateUserModal()">
        <x-icon name="plus" size="16" /> Créer un Utilisateur
    </button>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form action="{{ route('admin.users') }}" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 220px;">
            <label class="form-label">Rechercher par Nom ou Email</label>
            <input type="text" name="search" class="form-control" placeholder="Nom ou email..." value="{{ request('search') }}">
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
            <label class="form-label">Rôle</label>
            <select name="role" class="form-select">
                <option value="">Tous les rôles</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Administrateur</option>
                <option value="enseignant" {{ request('role') === 'enseignant' ? 'selected' : '' }}>Enseignant</option>
                <option value="stagiaire" {{ request('role') === 'stagiaire' ? 'selected' : '' }}>Stagiaire</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 42px;">
            <x-icon name="filter" size="14" /> Filtrer
        </button>
        <a href="{{ route('admin.users') }}" class="btn btn-secondary btn-sm" style="height: 42px;">Réinitialiser</a>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Téléphone</th>
                    <th>Date Création</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <img
                                src="{{ $u->avatarUrl() }}"
                                alt="Photo de {{ $u->name }}"
                                class="user-avatar"
                                style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%;"
                                onerror="this.onerror=null; this.src='https://api.dicebear.com/7.x/initials/svg?seed={{ urlencode($u->name) }}';"
                            >
                            <div>
                                <strong>{{ $u->name }}</strong>
                                @if($u->isStagiaire() && $u->stagiaireProfile)
                                    <div style="font-size: 0.72rem; color: var(--text-dim);">
                                        CNE: {{ $u->stagiaireProfile->matricule_cne }} ({{ $u->stagiaireProfile->groupe->code_groupe ?? 'N/A' }})
                                    </div>
                                @elseif($u->isStagiaire())
                                    <div style="font-size: 0.72rem; color: var(--warning);">Profil stagiaire à compléter</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="font-family: var(--font-mono); font-size: 0.8rem;">{{ $u->email }}</td>
                    <td>
                        @if($u->role === 'admin')
                            <span class="badge badge-info" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <x-icon name="user-check" size="12" /> Administrateur
                            </span>
                        @elseif($u->role === 'enseignant')
                            <span class="badge badge-primary" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <x-icon name="users" size="12" /> Enseignant
                            </span>
                        @else
                            <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <x-icon name="graduation-cap" size="12" /> Stagiaire
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($u->statut_compte === 'actif')
                            <span class="badge badge-success">Actif</span>
                        @else
                            <span class="badge badge-danger">Désactivé</span>
                        @endif
                    </td>
                    <td>{{ $u->telephone ?? 'Non renseigné' }}</td>
                    <td>{{ $u->created_at ? $u->created_at->format('d/m/Y') : '-' }}</td>
                    <td>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary btn-sm"
                                onclick='openEditUserModal({{ $u->id }}, {{ Js::from($u->name) }}, {{ Js::from($u->email) }}, {{ Js::from($u->role) }}, {{ Js::from($u->telephone ?? "") }})'>
                                <x-icon name="edit" size="13" /> Modifier
                            </button>

                            <form action="{{ route('admin.users.toggle', $u->id) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm" {{ $u->id === auth()->id() ? 'disabled' : '' }}>
                                    {{ $u->statut_compte === 'actif' ? 'Désactiver' : 'Activer' }}
                                </button>
                            </form>

                            <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" style="margin: 0;"
                                onsubmit="return confirm('Voulez-vous vraiment supprimer définitivement cet utilisateur ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" {{ $u->id === auth()->id() ? 'disabled' : '' }}>
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $users->links() }}
    </div>
</div>

<!-- Modal Création Utilisateur -->
<div id="createUserModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Créer un Nouvel Utilisateur</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeCreateUserModal()">✕</button>
        </div>
        <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom Complet</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Karim Benjelloun" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Adresse Email</label>
                    <input type="email" name="email" class="form-control" placeholder="karim@edu.ma" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mot de passe (minimum 6 caractères)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Rôle</label>
                    <select name="role" id="createUserRole" class="form-select" onchange="toggleStagiaireFields()" required>
                        <option value="admin">Administrateur</option>
                        <option value="enseignant">Enseignant</option>
                        <option value="stagiaire" selected>Stagiaire</option>
                    </select>
                </div>

                <div id="stagiaireExtraFields" style="background: var(--bg-tertiary); padding: 0.85rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--primary); margin-bottom: 0.6rem;">
                        Dossier Stagiaire (enregistré automatiquement dans la table stagiaires)
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Groupe d'affectation</label>
                            <select name="groupe_id" class="form-select">
                                @foreach($groupes as $g)
                                    <option value="{{ $g->id }}">{{ $g->code_groupe }} - {{ $g->nom_groupe }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Matricule CNE (optionnel)</label>
                            <input type="text" name="matricule_cne" class="form-control" placeholder="Généré auto si vide">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" placeholder="+212 6 XX XX XX XX">
                </div>

                <div class="form-group">
                    <label class="form-label">Photo de profil</label>
                    <input type="file" name="avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small style="color: var(--text-muted);">
                        Formats acceptés : JPG, PNG, WEBP, GIF. Taille maximale : 3 Mo.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateUserModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer dans la Base de Données</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modification Utilisateur -->
<div id="editUserModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Modifier le Compte Utilisateur</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeEditUserModal()">✕</button>
        </div>
        <form id="editUserForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom Complet</label>
                    <input type="text" name="name" id="edit_user_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse Email</label>
                    <input type="email" name="email" id="edit_user_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle</label>
                    <select name="role" id="edit_user_role" class="form-select" required>
                        <option value="admin">Administrateur</option>
                        <option value="enseignant">Enseignant</option>
                        <option value="stagiaire">Stagiaire</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" id="edit_user_telephone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe (laisser vide pour conserver l'actuel)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateUserModal() {
        toggleStagiaireFields();
        document.getElementById('createUserModal').classList.add('active');
    }
    function closeCreateUserModal() {
        document.getElementById('createUserModal').classList.remove('active');
    }
    function toggleStagiaireFields() {
        const role = document.getElementById('createUserRole').value;
        document.getElementById('stagiaireExtraFields').style.display = role === 'stagiaire' ? 'block' : 'none';
    }
    function openEditUserModal(id, name, email, role, telephone) {
        document.getElementById('editUserForm').action = '/admin/users/' + id;
        document.getElementById('edit_user_name').value = name;
        document.getElementById('edit_user_email').value = email;
        document.getElementById('edit_user_role').value = role;
        document.getElementById('edit_user_telephone').value = telephone;
        document.getElementById('editUserModal').classList.add('active');
    }
    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.remove('active');
    }
</script>
@endsection
