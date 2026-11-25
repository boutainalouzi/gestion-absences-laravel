@extends('layouts.app')

@section('title', 'Gestion des Séances')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Gestion des Séances & Créneaux Horaires</h1>
        <p style="color: var(--text-muted);">Configuration des créneaux de formation (S1, S2, S3, S4...) utilisés lors du pointage des absences</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateSeanceModal()">
        <x-icon name="plus" size="16" /> Ajouter une Séance
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code Séance</th>
                    <th>Libellé</th>
                    <th>Heure Début</th>
                    <th>Heure Fin</th>
                    <th>Absences liées</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($seances as $s)
                <tr>
                    <td><span class="badge badge-primary">{{ $s->code_seance }}</span></td>
                    <td><strong>{{ $s->libelle }}</strong></td>
                    <td style="font-family: var(--font-mono);">{{ $s->heure_debut ?? '-' }}</td>
                    <td style="font-family: var(--font-mono);">{{ $s->heure_fin ?? '-' }}</td>
                    <td><span class="badge badge-secondary">{{ $s->absences_count }}</span></td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary btn-sm"
                                onclick='openEditSeanceModal({{ $s->id }}, {{ Js::from($s->code_seance) }}, {{ Js::from($s->libelle) }}, {{ Js::from($s->heure_debut ? substr($s->heure_debut, 0, 5) : "") }}, {{ Js::from($s->heure_fin ? substr($s->heure_fin, 0, 5) : "") }})'>
                                <x-icon name="edit" size="14" /> Modifier
                            </button>
                            <form action="{{ route('admin.seances.destroy', $s->id) }}" method="POST" style="margin: 0;" onsubmit="return confirm('Supprimer cette séance ?');">
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
                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                        Aucune séance configurée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Créer Séance -->
<div id="createSeanceModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Ajouter une Séance</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeCreateSeanceModal()">✕</button>
        </div>
        <form action="{{ route('admin.seances.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Code Séance</label>
                    <input type="text" name="code_seance" class="form-control" placeholder="Ex: S5" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Libellé</label>
                    <input type="text" name="libelle" class="form-control" placeholder="Ex: 18h30 - 20h30" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Heure Début</label>
                        <input type="time" name="heure_debut" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Heure Fin</label>
                        <input type="time" name="heure_fin" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateSeanceModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer la Séance</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier Séance -->
<div id="editSeanceModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.25rem; font-weight: 700;">Modifier la Séance</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeEditSeanceModal()">✕</button>
        </div>
        <form id="editSeanceForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Code Séance</label>
                    <input type="text" name="code_seance" id="edit_s_code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Libellé</label>
                    <input type="text" name="libelle" id="edit_s_libelle" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Heure Début</label>
                        <input type="time" name="heure_debut" id="edit_s_debut" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Heure Fin</label>
                        <input type="time" name="heure_fin" id="edit_s_fin" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditSeanceModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateSeanceModal() {
        document.getElementById('createSeanceModal').classList.add('active');
    }
    function closeCreateSeanceModal() {
        document.getElementById('createSeanceModal').classList.remove('active');
    }
    function openEditSeanceModal(id, code, libelle, debut, fin) {
        document.getElementById('editSeanceForm').action = '/admin/seances/' + id;
        document.getElementById('edit_s_code').value = code;
        document.getElementById('edit_s_libelle').value = libelle;
        document.getElementById('edit_s_debut').value = debut;
        document.getElementById('edit_s_fin').value = fin;
        document.getElementById('editSeanceModal').classList.add('active');
    }
    function closeEditSeanceModal() {
        document.getElementById('editSeanceModal').classList.remove('active');
    }
</script>
@endsection
