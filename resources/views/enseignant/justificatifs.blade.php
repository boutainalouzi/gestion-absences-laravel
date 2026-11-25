@extends('layouts.app')

@section('title', 'Consultation des Justificatifs - Enseignant')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;">Justificatifs des Stagiaires</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Consultation des certificats déposés par les stagiaires de vos groupes (validation réservée à l'administration)
        </p>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <form action="{{ route('enseignant.justificatifs') }}" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
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
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Décision</label>
            <select name="statut_decision" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="EN_ATTENTE" {{ request('statut_decision') === 'EN_ATTENTE' ? 'selected' : '' }}>En attente</option>
                <option value="ACCEPTE" {{ request('statut_decision') === 'ACCEPTE' ? 'selected' : '' }}>Accepté</option>
                <option value="REFUSE" {{ request('statut_decision') === 'REFUSE' ? 'selected' : '' }}>Refusé</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 42px;">Filtrer</button>
        <a href="{{ route('enseignant.justificatifs') }}" class="btn btn-secondary btn-sm" style="height: 42px;">Réinitialiser</a>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date Dépôt</th>
                    <th>Stagiaire</th>
                    <th>Groupe</th>
                    <th>Absence</th>
                    <th>Pièce Justificative</th>
                    <th>Statut Décision</th>
                    <th>Motif / Remarque</th>
                </tr>
            </thead>
            <tbody>
                @forelse($justificatifs as $j)
                <tr>
                    <td>{{ $j->date_depot->format('d/m/Y H:i') }}</td>
                    <td>
                        <strong>{{ $j->absence->stagiaire->user->name ?? 'N/A' }}</strong>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">CNE: {{ $j->absence->stagiaire->matricule_cne ?? '' }}</div>
                    </td>
                    <td><span class="badge badge-primary">{{ $j->absence->stagiaire->groupe->code_groupe ?? 'N/A' }}</span></td>
                    <td>
                        <strong>{{ $j->absence->date_absence->format('d/m/Y') }}</strong>
                        ({{ $j->absence->seance->code_seance ?? 'N/A' }})
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                            <a href="{{ route('justificatifs.file', $j->id) }}" target="_blank" class="btn btn-secondary btn-sm">
                                <x-icon name="eye" size="14" /> Consulter
                            </a>
                            <a href="{{ route('justificatifs.file', ['id' => $j->id, 'download' => 1]) }}" class="btn btn-secondary btn-sm">
                                <x-icon name="download" size="14" />
                            </a>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 0.2rem;">
                            {{ $j->nom_fichier }} ({{ $j->tailleLisible() }})
                        </div>
                    </td>
                    <td>
                        @if($j->statut_decision === 'ACCEPTE')
                            <span class="badge badge-success">Accepté</span>
                        @elseif($j->statut_decision === 'REFUSE')
                            <span class="badge badge-danger">Refusé</span>
                        @else
                            <span class="badge badge-warning">En attente admin</span>
                        @endif
                    </td>
                    <td style="font-size: 0.82rem; color: var(--text-muted);">
                        @if($j->commentaire_stagiaire)
                            <div><strong>Stagiaire :</strong> {{ $j->commentaire_stagiaire }}</div>
                        @endif
                        @if($j->motif_decision)
                            <div><strong>Admin :</strong> {{ $j->motif_decision }}</div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                        Aucun justificatif disponible pour vos groupes.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1.5rem;">
        {{ $justificatifs->links() }}
    </div>
</div>
@endsection
