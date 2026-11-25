@extends('layouts.app')

@section('title', 'Profil Stagiaire Incomplet')

@section('content')
<div style="max-width: 640px; margin: 2rem auto;">
    <div class="card" style="text-align: center; padding: 2.5rem 2rem; border-top: 4px solid var(--warning);">
        <div style="width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%; background: rgba(217, 119, 6, 0.15); color: var(--warning); display: flex; align-items: center; justify-content: center;">
            <x-icon name="alert-triangle" size="32" />
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">
            Dossier Stagiaire en Attente d'Affectation
        </h1>
        <p style="color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; margin-bottom: 1.5rem;">
            Votre compte utilisateur (<strong>{{ auth()->user()->email }}</strong>) est bien actif, mais votre fiche académique (matricule CNE et groupe de formation) n'a pas encore été rattachée dans la table des stagiaires.
        </p>

        <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; text-align: left;">
            <strong>Que faire ?</strong><br>
            Veuillez contacter l'administrateur de l'établissement afin qu'il associe votre compte à un groupe depuis l'espace <em>Gestion des Stagiaires</em>.
        </div>

        <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('profile.index') }}" class="btn btn-secondary">
                <x-icon name="user" size="16" /> Mon Profil
            </a>
            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-primary">Se déconnecter</button>
            </form>
        </div>
    </div>
</div>
@endsection
