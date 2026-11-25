@extends('layouts.app')

@section('title', 'Gestion des Absences - Enseignant')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Absences de Mes Groupes</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Suivi, modification et consultation des absences et justificatifs de vos groupes</p>
    </div>
    <a href="{{ route('enseignant.saisir-absence') }}" class="btn btn-primary btn-sm">
        <x-icon name="user-x" size="16" /> Pointer une Absence
    </a>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <form action="{{ route('enseignant.absences') }}" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Groupe</label>
            <select name="groupe_id" class="form-select">
                <option value="">Tous mes groupes</option>
                @foreach($groupesAttribués->unique('groupe_id') as $eg)
                    <option value="{{ $eg->groupe_id }}" {{ request('groupe_id') == $eg->groupe_id ? 'selected' : '' }}>
                        {{ $eg->groupe->code_groupe ?? 'N/A' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 190px;">
            <label class="form-label">Statut</label>
            <select name="statut_absence" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="NON_JUSTIFIEE" {{ request('statut_absence') === 'NON_JUSTIFIEE' ? 'selected' : '' }}>Non justifiée</option>
                <option value="EN_ATTENTE_VALIDATION" {{ request('statut_absence') === 'EN_ATTENTE_VALIDATION' ? 'selected' : '' }}>En attente</option>
                <option value="JUSTIFIEE_ACCEPTEE" {{ request('statut_absence') === 'JUSTIFIEE_ACCEPTEE' ? 'selected' : '' }}>Acceptée</option>
                <option value="JUSTIFIEE_REFUSEE" {{ request('statut_absence') === 'JUSTIFIEE_REFUSEE' ? 'selected' : '' }}>Refusée</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label class="form-label">Rechercher Stagiaire</label>
            <input type="text" name="search" class="form-control" placeholder="Nom ou CNE..." value="{{ request('search') }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 42px;">Filtrer</button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Séance</th>
                    <th>Stagiaire</th>
                    <th>Groupe</th>
                    <th>Statut</th>
                    <th>Justificatif</th>
                    <th>Motif</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($absences as $abs)
                <tr>
                    <td>{{ $abs->date_absence->format('d/m/Y') }}</td>
                    <td><span class="badge badge-primary">{{ $abs->seance->code_seance ?? 'N/A' }}</span></td>
                    <td>
                        <strong>{{ $abs->stagiaire->user->name ?? 'N/A' }}</strong>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">{{ $abs->stagiaire->matricule_cne ?? '' }}</div>
                    </td>
                    <td><span class="badge badge-info">{{ $abs->stagiaire->groupe->code_groupe ?? 'N/A' }}</span></td>
                    <td>
                        @if($abs->statut_absence === 'JUSTIFIEE_ACCEPTEE')
                            <span class="badge badge-success">Validée</span>
                        @elseif($abs->statut_absence === 'EN_ATTENTE_VALIDATION')
                            <span class="badge badge-warning">En attente</span>
                        @elseif($abs->statut_absence === 'JUSTIFIEE_REFUSEE')
                            <span class="badge badge-danger">Refusée</span>
                        @else
                            <span class="badge badge-danger">Non justifiée</span>
                        @endif
                    </td>
                    <td>
                        @if($abs->justificatif)
                            <a href="{{ route('justificatifs.file', $abs->justificatif->id) }}" target="_blank" class="badge badge-info">
                                <x-icon name="eye" size="12" /> {{ Str::limit($abs->justificatif->nom_fichier, 18) }}
                            </a>
                        @else
                            <span style="color: var(--text-dim); font-size: 0.8rem;">Aucun</span>
                        @endif
                    </td>
                    <td style="font-size: 0.82rem; color: var(--text-muted); max-width: 220px;">
                        {{ $abs->motif_enseignant }}
                    </td>
                    <td>
                        @if($abs->enseignant_id === auth()->id())
                            <div style="display: flex; gap: 0.3rem;">
                                <button type="button" class="btn btn-secondary btn-sm"
                                    onclick='openMotifModal({{ $abs->id }}, {{ Js::from($abs->motif_enseignant ?? "") }})'>
                                    <x-icon name="edit" size="13" />
                                </button>
                                @if(!$abs->justificatif && $abs->statut_absence === 'NON_JUSTIFIEE')
                                    <form action="{{ route('enseignant.absences.destroy', $abs->id) }}" method="POST" style="margin: 0;" onsubmit="return confirm('Annuler cette absence ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <x-icon name="trash" size="13" />
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <span style="font-size: 0.75rem; color: var(--text-dim);">Lecture seule</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                        Aucune absence trouvée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1.5rem;">
        {{ $absences->links() }}
    </div>
</div>

<!-- Modal Modifier Motif -->
<div id="motifModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-size: 1.15rem; font-weight: 700;">Modifier la Remarque d'Absence</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeMotifModal()">✕</button>
        </div>
        <form id="motifForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Motif / Remarque</label>
                    <textarea name="motif_enseignant" id="edit_motif_text" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeMotifModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMotifModal(id, motif) {
        document.getElementById('motifForm').action = '/enseignant/absences/' + id;
        document.getElementById('edit_motif_text').value = motif;
        document.getElementById('motifModal').classList.add('active');
    }
    function closeMotifModal() {
        document.getElementById('motifModal').classList.remove('active');
    }
</script>
@endsection
