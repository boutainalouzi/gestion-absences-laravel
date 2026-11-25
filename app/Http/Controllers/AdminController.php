<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Groupe;
use App\Models\Stagiaire;
use App\Models\EnseignantGroupe;
use App\Models\Seance;
use App\Models\Absence;
use App\Models\Justificatif;
use App\Models\DeclarationEnseignant;
use App\Models\Notification;
use App\Models\HistoriqueCorrection;
use App\Models\ConseilDiscipline;
use App\Models\SignalementComportement;

class AdminController extends Controller
{
    /**
     * Tableau de bord Administrateur
     */
    public function dashboard(Request $request)
    {
        $totalStagiaires = Stagiaire::count();
        $totalEnseignants = User::where('role', 'enseignant')->count();
        $totalGroupes = Groupe::count();
        $totalSeances = Seance::count();
        $totalAbsences = Absence::count();
        $absencesNonJustifiees = Absence::where('statut_absence', 'NON_JUSTIFIEE')->count();
        $justificatifsAcceptes = Justificatif::where('statut_decision', 'ACCEPTE')->count();
        $certificatsEnAttente = Justificatif::where('statut_decision', 'EN_ATTENTE')->count();
        $declarationsEnseignants = DeclarationEnseignant::where('est_consultee', false)->count();

        // Conseils de discipline & Signalements
        $totalConseils = ConseilDiscipline::count();
        $conseilsConvoques = ConseilDiscipline::where('statut', 'CONVOQUE')->count();
        $signalementsEnAttente = SignalementComportement::where('statut', 'EN_ATTENTE')->count();

        // Récentes déclarations des enseignants
        $dernieresDeclarations = DeclarationEnseignant::with(['enseignant', 'seance', 'groupe'])
            ->latest('date_declaration')
            ->take(5)
            ->get();

        // Récents justificatifs à valider
        $justificatifsAValider = Justificatif::with(['absence.stagiaire.user', 'absence.seance', 'absence.enseignant'])
            ->where('statut_decision', 'EN_ATTENTE')
            ->latest('date_depot')
            ->get();

        // Absences récentes
        $dernieresAbsences = Absence::with(['stagiaire.user', 'stagiaire.groupe', 'enseignant', 'seance', 'justificatif'])
            ->latest('created_at')
            ->take(8)
            ->get();

        // Derniers conseils de discipline
        $derniersConseils = ConseilDiscipline::with(['stagiaire.user', 'stagiaire.groupe', 'createur'])
            ->latest('date_conseil')
            ->take(5)
            ->get();

        // Derniers signalements de comportement
        $derniersSignalements = SignalementComportement::with(['enseignant', 'stagiaire.user', 'stagiaire.groupe', 'seance'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalStagiaires',
            'totalEnseignants',
            'totalGroupes',
            'totalSeances',
            'totalAbsences',
            'absencesNonJustifiees',
            'justificatifsAcceptes',
            'certificatsEnAttente',
            'declarationsEnseignants',
            'totalConseils',
            'conseilsConvoques',
            'signalementsEnAttente',
            'dernieresDeclarations',
            'justificatifsAValider',
            'dernieresAbsences',
            'derniersConseils',
            'derniersSignalements'
        ));
    }

    /**
     * Tâche 21: Recherche et filtres multi-critères des absences
     */
    public function absences(Request $request)
    {
        $query = Absence::with(['stagiaire.user', 'stagiaire.groupe', 'enseignant', 'seance', 'justificatif']);

        // Filtre par groupe
        if ($request->filled('groupe_id')) {
            $query->whereHas('stagiaire', function ($q) use ($request) {
                $q->where('groupe_id', $request->groupe_id);
            });
        }

        // Filtre par stagiaire (recherche nom ou CNE)
        if ($request->filled('search_stagiaire')) {
            $search = $request->search_stagiaire;
            $query->whereHas('stagiaire', function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filtre par enseignant
        if ($request->filled('enseignant_id')) {
            $query->where('enseignant_id', $request->enseignant_id);
        }

        // Filtre par statut d'absence
        if ($request->filled('statut_absence')) {
            $query->where('statut_absence', $request->statut_absence);
        }

        // Filtre par période
        if ($request->filled('date_debut')) {
            $query->whereDate('date_absence', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('date_absence', '<=', $request->date_fin);
        }

        // Export CSV si demandé
        if ($request->get('export') === 'csv') {
            return $this->exportAbsencesCsv($query->get());
        }

        $absences = $query->latest('date_absence')->paginate(15)->withQueryString();
        $groupes = Groupe::all();
        $enseignants = User::where('role', 'enseignant')->get();
        $seances = Seance::all();

        return view('admin.absences', compact('absences', 'groupes', 'enseignants', 'seances'));
    }

    /**
     * Tâche 15: Validation des justificatifs par l'administrateur
     */
    public function justificatifs(Request $request)
    {
        $query = Justificatif::with(['absence.stagiaire.user', 'absence.stagiaire.groupe', 'absence.seance', 'absence.enseignant', 'adminValidateur']);

        if ($request->filled('statut_decision')) {
            $query->where('statut_decision', $request->statut_decision);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('absence.stagiaire', function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($qu) => $qu->where('name', 'like', "%{$search}%"));
            });
        }

        $justificatifs = $query->latest('date_depot')->paginate(15)->withQueryString();

        return view('admin.justificatifs', compact('justificatifs'));
    }

    public function validerJustificatif(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|in:ACCEPTE,REFUSE',
            'motif_decision' => 'nullable|string|max:500',
        ]);

        $justif = Justificatif::with('absence.stagiaire')->findOrFail($id);
        $decision = $request->decision;

        $justif->update([
            'statut_decision' => $decision,
            'admin_validateur_id' => auth()->id(),
            'date_decision' => Carbon::now(),
            'motif_decision' => $request->motif_decision ?? ($decision === 'ACCEPTE' ? 'Certificat conforme et accepté.' : 'Pièce non recevable.'),
        ]);

        $absence = $justif->absence;
        $absence->update([
            'statut_absence' => $decision === 'ACCEPTE' ? 'JUSTIFIEE_ACCEPTEE' : 'JUSTIFIEE_REFUSEE',
        ]);

        // Recalculer automatiquement la note de discipline du stagiaire
        $stagiaire = $absence->stagiaire;
        $statsDiscipline = $stagiaire->recalculerNoteDiscipline();

        // Notification envoyée au stagiaire (Tâche 16 & 18)
        Notification::create([
            'destinataire_id' => $stagiaire->user_id,
            'type_notification' => 'JUSTIFICATIF_VALIDE',
            'titre' => $decision === 'ACCEPTE' ? 'Justificatif validé avec succès ✅' : 'Justificatif refusé ❌',
            'message' => $decision === 'ACCEPTE'
                ? "Votre justificatif pour l'absence du {$absence->date_absence->format('d/m/Y')} a été accepté. Aucune déduction sur votre note de discipline."
                : "Votre justificatif a été refusé. Motif : \"{$justif->motif_decision}\". Une pénalité de -0.5 pt a été appliquée.",
            'lien_action' => '/stagiaire/absences',
        ]);

        return back()->with('success', "Le justificatif #{$justif->id} a été statué comme [{$decision}]. La note du stagiaire est maintenant de {$statsDiscipline['note']}/20.00 pts.");
    }

    /**
     * Suppression d'un justificatif par l'administrateur (synchronisé fichier + MySQL)
     */
    public function destroyJustificatif($id)
    {
        $justif = Justificatif::with('absence.stagiaire')->findOrFail($id);
        $absence = $justif->absence;
        $stagiaire = $absence?->stagiaire;

        DB::transaction(function () use ($justif, $absence) {
            $justif->deleteStoredFile();
            $justif->delete();
            if ($absence) {
                $absence->update(['statut_absence' => 'NON_JUSTIFIEE']);
            }
        });

        if ($stagiaire) {
            $stagiaire->recalculerNoteDiscipline();
        }

        return back()->with('success', 'Le justificatif et son fichier associé ont été supprimés avec succès.');
    }

    /**
     * Tâche 22: Correction d'une absence
     */
    public function corrigerAbsence(Request $request, $id)
    {
        $absence = Absence::with(['stagiaire.user', 'seance', 'justificatif'])->findOrFail($id);

        if ($request->has('action_supprimer')) {
            $stagiaire = $absence->stagiaire;
            $nomStagiaire = $stagiaire->user->name ?? 'Stagiaire';
            $codeSeance = $absence->seance->code_seance ?? 'N/A';
            $oldInfo = "Absence du {$absence->date_absence->format('Y-m-d')} (Séance {$codeSeance}) pour {$nomStagiaire}";

            DB::transaction(function () use ($absence, $oldInfo, $request) {
                HistoriqueCorrection::create([
                    'absence_id' => $absence->id,
                    'admin_id' => auth()->id(),
                    'champ_modifie' => 'ANNULATION_ABSENCE',
                    'ancienne_valeur' => $oldInfo,
                    'nouvelle_valeur' => 'SUPPRIMÉE',
                    'motif_correction' => $request->motif_correction ?? 'Absence enregistrée par erreur, supprimée par l\'administration.',
                ]);

                if ($absence->justificatif) {
                    $absence->justificatif->deleteStoredFile();
                }

                $absence->delete();
            });

            if ($stagiaire) {
                $stagiaire->recalculerNoteDiscipline();
            }

            return redirect()->route('admin.absences')->with('success', 'L\'absence a été annulée et supprimée. La note de comportement a été recalculée.');
        }

        $request->validate([
            'date_absence' => 'required|date',
            'seance_id' => 'required|exists:seances,id',
            'motif_enseignant' => 'nullable|string',
            'motif_correction' => 'required|string|min:5',
        ]);

        $modifs = [];
        if ($absence->date_absence->format('Y-m-d') !== $request->date_absence) {
            $modifs[] = "Date: {$absence->date_absence->format('Y-m-d')} -> {$request->date_absence}";
        }
        if ($absence->seance_id != $request->seance_id) {
            $modifs[] = "Séance: {$absence->seance_id} -> {$request->seance_id}";
        }

        $absence->update([
            'date_absence' => $request->date_absence,
            'seance_id' => $request->seance_id,
            'motif_enseignant' => $request->motif_enseignant,
            'est_corrigee' => true,
        ]);

        HistoriqueCorrection::create([
            'absence_id' => $absence->id,
            'admin_id' => auth()->id(),
            'champ_modifie' => 'MODIFICATION_CHAMPS',
            'ancienne_valeur' => implode(' | ', $modifs) ?: 'Détails modifiés',
            'nouvelle_valeur' => 'Mis à jour par ' . auth()->user()->name,
            'motif_correction' => $request->motif_correction,
        ]);

        if ($absence->stagiaire) {
            $absence->stagiaire->recalculerNoteDiscipline();
        }

        return back()->with('success', 'Absence corrigée avec succès. L\'historique d\'audit a été enregistré.');
    }

    /**
     * Tâche 5: Gestion des comptes utilisateurs
     */
    public function users(Request $request)
    {
        $query = User::with('stagiaireProfile.groupe');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $groupes = Groupe::all();

        return view('admin.users', compact('users', 'groupes'));
    }

    public function storeUser(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,enseignant,stagiaire',
            'telephone' => 'nullable|string|max:30',
            'avatar_file' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:3072',
        ];

        if ($request->role === 'stagiaire') {
            $rules['matricule_cne'] = 'nullable|string|max:50|unique:stagiaires,matricule_cne';
            $rules['groupe_id'] = 'nullable|exists:groupes,id';
            $rules['date_naissance'] = 'nullable|date';
        }

        $request->validate($rules, [
            'avatar_file.image' => 'Le fichier doit être une image valide.',
            'avatar_file.mimes' => 'Formats acceptés : JPG, PNG, WEBP, GIF.',
            'avatar_file.max' => 'La taille maximale autorisée est de 3 Mo.',
        ]);

        if ($request->role === 'stagiaire') {
            $targetGroupeId = $request->groupe_id ?: Groupe::query()->value('id');
            if (!$targetGroupeId) {
                return back()->with('error', 'Veuillez créer au moins un groupe avant de créer un compte stagiaire.');
            }
        }

        $avatarPath = null;
        if ($request->hasFile('avatar_file')) {
            $file = $request->file('avatar_file');
            $fileName = 'avatar_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $destination = public_path('uploads/avatars');
            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $fileName);
            $avatarPath = 'uploads/avatars/' . $fileName;
        }

        $user = DB::transaction(function () use ($request, $avatarPath) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'statut_compte' => 'actif',
                'telephone' => $request->telephone,
                'avatar' => $avatarPath,
            ]);

            if ($user->isStagiaire()) {
                $groupeId = $request->groupe_id ?: Groupe::query()->value('id');
                $cne = $request->filled('matricule_cne')
                    ? strtoupper(trim($request->matricule_cne))
                    : 'CNE' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);

                Stagiaire::create([
                    'user_id' => $user->id,
                    'matricule_cne' => $cne,
                    'groupe_id' => $groupeId,
                    'date_naissance' => $request->date_naissance,
                    'note_discipline' => 20.00,
                ]);
            }

            return $user;
        });

        return back()->with(
            'success',
            "Le compte de {$user->name} ({$user->role}) a été créé avec succès dans la base de données !"
        );
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,enseignant,stagiaire',
            'telephone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:6',
        ]);

        if ($user->id === auth()->id() && $request->role !== 'admin') {
            return back()->with('error', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'telephone' => $request->telephone,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', "Le compte de {$user->name} a été mis à jour avec succès.");
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->statut_compte = $user->statut_compte === 'actif' ? 'desactive' : 'actif';
        $user->save();

        return back()->with('success', "Le statut du compte de {$user->name} est maintenant : {$user->statut_compte}.");
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        try {
            DB::transaction(function () use ($user) {
                $user->load('stagiaireProfile.absences.justificatif');
                if ($user->stagiaireProfile) {
                    foreach ($user->stagiaireProfile->absences as $abs) {
                        $abs->justificatif?->deleteStoredFile();
                    }
                    $user->stagiaireProfile->delete();
                }

                if ($user->avatar && str_starts_with($user->avatar, 'uploads/avatars/')) {
                    $avatarFull = public_path($user->avatar);
                    if (is_file($avatarFull)) {
                        @unlink($avatarFull);
                    }
                }

                $user->delete();
            });

            return back()->with('success', 'Le compte utilisateur a été supprimé avec succès.');
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Impossible de supprimer ce compte car il possède encore des données liées.');
        }
    }

    /**
     * Gestion des Enseignants et de leurs affectations aux groupes
     */
    public function enseignants()
    {
        $enseignants = User::where('role', 'enseignant')
            ->with(['enseignantGroupes.groupe'])
            ->latest()
            ->get();
        $groupes = Groupe::all();

        return view('admin.enseignants', compact('enseignants', 'groupes'));
    }

    public function storeEnseignant(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'telephone' => 'nullable|string|max:30',
            'groupe_id' => 'nullable|exists:groupes,id',
            'matiere' => 'nullable|string|max:150',
        ]);

        $enseignant = DB::transaction(function () use ($request) {
            $enseignant = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'enseignant',
                'statut_compte' => 'actif',
                'telephone' => $request->telephone,
            ]);

            if ($request->filled('groupe_id')) {
                EnseignantGroupe::create([
                    'enseignant_id' => $enseignant->id,
                    'groupe_id' => $request->groupe_id,
                    'matiere' => $request->matiere ?: 'Module principal',
                ]);
            }

            return $enseignant;
        });

        return back()->with('success', "L'enseignant {$enseignant->name} a été créé avec succès.");
    }

    public function assignEnseignantGroupe(Request $request)
    {
        $request->validate([
            'enseignant_id' => 'required|exists:users,id',
            'groupe_id' => 'required|exists:groupes,id',
            'matiere' => 'required|string|max:150',
        ]);

        EnseignantGroupe::create([
            'enseignant_id' => $request->enseignant_id,
            'groupe_id' => $request->groupe_id,
            'matiere' => $request->matiere,
        ]);

        return back()->with('success', 'Affectation enseignant-groupe enregistrée dans la base de données.');
    }

    public function removeEnseignantGroupe($id)
    {
        EnseignantGroupe::findOrFail($id)->delete();
        return back()->with('success', 'Affectation enseignant-groupe supprimée.');
    }

    /**
     * Tâche 7: Gestion des groupes
     */
    public function groupes()
    {
        $groupes = Groupe::with(['responsable', 'stagiaires', 'enseignantGroupes.enseignant'])->get();
        $enseignants = User::where('role', 'enseignant')->get();
        return view('admin.groupes', compact('groupes', 'enseignants'));
    }

    public function storeGroupe(Request $request)
    {
        $validated = $request->validate([
            'code_groupe' => 'required|string|max:30|unique:groupes,code_groupe',
            'nom_groupe' => 'required|string|max:255',
            'filiere' => 'required|string|max:150',
            'annee_formation' => 'required|integer|in:1,2',
            'id_responsable' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        Groupe::create($validated);

        return back()->with('success', 'Nouveau groupe créé avec succès dans la base de données.');
    }

    public function updateGroupe(Request $request, $id)
    {
        $groupe = Groupe::findOrFail($id);
        $validated = $request->validate([
            'code_groupe' => 'required|string|max:30|unique:groupes,code_groupe,' . $groupe->id,
            'nom_groupe' => 'required|string|max:255',
            'filiere' => 'required|string|max:150',
            'annee_formation' => 'required|integer|in:1,2',
            'id_responsable' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $groupe->update($validated);

        return back()->with('success', "Le groupe {$groupe->code_groupe} a été mis à jour.");
    }

    public function destroyGroupe($id)
    {
        $groupe = Groupe::withCount('stagiaires')->findOrFail($id);
        if ($groupe->stagiaires_count > 0) {
            return back()->with('error', "Impossible de supprimer le groupe {$groupe->code_groupe} car {$groupe->stagiaires_count} stagiaire(s) y sont inscrits.");
        }

        $groupe->delete();
        return back()->with('success', "Le groupe {$groupe->code_groupe} a été supprimé.");
    }

    /**
     * Gestion des Séances
     */
    public function seances()
    {
        $seances = Seance::withCount('absences')->orderBy('heure_debut')->get();
        return view('admin.seances', compact('seances'));
    }

    public function storeSeance(Request $request)
    {
        $validated = $request->validate([
            'code_seance' => 'required|string|max:20|unique:seances,code_seance',
            'libelle' => 'required|string|max:100',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin' => 'nullable|date_format:H:i|after:heure_debut',
        ]);

        Seance::create($validated);

        return back()->with('success', 'Nouvelle séance créée et enregistrée dans MySQL.');
    }

    public function updateSeance(Request $request, $id)
    {
        $seance = Seance::findOrFail($id);
        $validated = $request->validate([
            'code_seance' => 'required|string|max:20|unique:seances,code_seance,' . $seance->id,
            'libelle' => 'required|string|max:100',
            'heure_debut' => 'nullable',
            'heure_fin' => 'nullable',
        ]);

        $seance->update($validated);

        return back()->with('success', "La séance {$seance->code_seance} a été mise à jour.");
    }

    public function destroySeance($id)
    {
        $seance = Seance::withCount('absences')->findOrFail($id);
        if ($seance->absences_count > 0) {
            return back()->with('error', "Impossible de supprimer la séance {$seance->code_seance} car {$seance->absences_count} absence(s) y sont rattachées.");
        }

        $seance->delete();
        return back()->with('success', "La séance {$seance->code_seance} a été supprimée.");
    }

    /**
     * Tâche 8: Gestion des stagiaires
     */
    public function stagiaires(Request $request)
    {
        $query = Stagiaire::with(['user', 'groupe', 'absences']);

        if ($request->filled('groupe_id')) {
            $query->where('groupe_id', $request->groupe_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $stagiaires = $query->paginate(15)->withQueryString();
        $groupes = Groupe::all();
        $stagiairesSansProfil = User::where('role', 'stagiaire')->doesntHave('stagiaireProfile')->get();

        return view('admin.stagiaires', compact('stagiaires', 'groupes', 'stagiairesSansProfil'));
    }

    public function storeStagiaire(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'matricule_cne' => 'required|string|max:50|unique:stagiaires,matricule_cne',
            'groupe_id' => 'required|exists:groupes,id',
            'telephone' => 'nullable|string|max:30',
            'date_naissance' => 'nullable|date',
            'adresse' => 'nullable|string|max:255',
            'password' => 'required|min:6',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'stagiaire',
                'statut_compte' => 'actif',
                'telephone' => $request->telephone,
            ]);

            Stagiaire::create([
                'user_id' => $user->id,
                'matricule_cne' => strtoupper(trim($request->matricule_cne)),
                'groupe_id' => $request->groupe_id,
                'date_naissance' => $request->date_naissance,
                'adresse' => $request->adresse,
                'note_discipline' => 20.00,
            ]);

            return $user;
        });

        return back()->with('success', "Le dossier du stagiaire {$user->name} a été enregistré dans la base de données.");
    }

    public function linkStagiaireProfile(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|unique:stagiaires,user_id',
            'matricule_cne' => 'required|string|max:50|unique:stagiaires,matricule_cne',
            'groupe_id' => 'required|exists:groupes,id',
            'date_naissance' => 'nullable|date',
            'adresse' => 'nullable|string|max:255',
        ]);

        $user = User::where('role', 'stagiaire')->findOrFail($request->user_id);

        Stagiaire::create([
            'user_id' => $user->id,
            'matricule_cne' => strtoupper(trim($request->matricule_cne)),
            'groupe_id' => $request->groupe_id,
            'date_naissance' => $request->date_naissance,
            'adresse' => $request->adresse,
            'note_discipline' => 20.00,
        ]);

        return back()->with('success', "Le profil académique de {$user->name} a été créé et relié au groupe.");
    }

    public function updateStagiaire(Request $request, $id)
    {
        $stagiaire = Stagiaire::with('user')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $stagiaire->user_id,
            'matricule_cne' => 'required|string|max:50|unique:stagiaires,matricule_cne,' . $stagiaire->id,
            'groupe_id' => 'required|exists:groupes,id',
            'telephone' => 'nullable|string|max:30',
            'date_naissance' => 'nullable|date',
            'adresse' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $stagiaire) {
            if ($stagiaire->user) {
                $stagiaire->user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'telephone' => $request->telephone,
                ]);
            }

            $stagiaire->update([
                'matricule_cne' => strtoupper(trim($request->matricule_cne)),
                'groupe_id' => $request->groupe_id,
                'date_naissance' => $request->date_naissance,
                'adresse' => $request->adresse,
            ]);
        });

        return back()->with('success', 'Le dossier du stagiaire a été mis à jour avec succès.');
    }

    public function destroyStagiaire($id)
    {
        $stagiaire = Stagiaire::with(['user', 'absences.justificatif'])->findOrFail($id);
        $name = $stagiaire->user->name ?? $stagiaire->matricule_cne;

        DB::transaction(function () use ($stagiaire) {
            foreach ($stagiaire->absences as $abs) {
                $abs->justificatif?->deleteStoredFile();
            }
            $user = $stagiaire->user;
            $stagiaire->delete();
            if ($user) {
                $user->delete();
            }
        });

        return back()->with('success', "Le dossier et le compte du stagiaire {$name} ont été supprimés.");
    }

    /**
     * Tâche 17: Consultation des déclarations des enseignants
     */
    public function declarations()
    {
        $declarations = DeclarationEnseignant::with(['enseignant', 'seance', 'groupe'])
            ->latest('date_declaration')
            ->paginate(15);

        // Marquer comme consultées
        DeclarationEnseignant::where('est_consultee', false)->update(['est_consultee' => true]);

        return view('admin.declarations', compact('declarations'));
    }

    /**
     * Journal d'audit des corrections (Tâche 22)
     */
    public function auditCorrections()
    {
        $corrections = HistoriqueCorrection::with(['admin', 'absence.stagiaire.user'])
            ->latest()
            ->paginate(20);

        return view('admin.audit', compact('corrections'));
    }

    /**
     * Tâche Spéciale : Conseils de Discipline (15h automatiques et sanctions de comportement)
     */
    public function conseilsDiscipline(Request $request)
    {
        // Déclencher la vérification automatique du seuil de 15h sur l'ensemble des stagiaires
        $stagiairesNonJustifies = Stagiaire::with(['absences.seance', 'user', 'groupe'])->get();
        foreach ($stagiairesNonJustifies as $st) {
            $st->verifierEtDeclencherConseil15h();
        }

        $query = ConseilDiscipline::with(['stagiaire.user', 'stagiaire.groupe', 'createur', 'signalement.enseignant']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search_stagiaire')) {
            $search = $request->search_stagiaire;
            $query->whereHas('stagiaire', function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $conseils = $query->latest('date_conseil')->paginate(15)->withQueryString();

        // Récupérer les signalements des professeurs
        $signalements = SignalementComportement::with(['enseignant', 'stagiaire.user', 'stagiaire.groupe', 'seance'])
            ->latest('date_incident')
            ->take(10)
            ->get();

        $stagiaires = Stagiaire::with(['user', 'groupe'])->get();

        return view('admin.conseils-discipline', compact('conseils', 'signalements', 'stagiaires'));
    }

    public function storeConseilDiscipline(Request $request)
    {
        $request->validate([
            'stagiaire_id' => 'required|exists:stagiaires,id',
            'type' => 'required|in:COMPORTEMENT,ABSENCE_15H,INFRACTION_REGLEMENT',
            'date_conseil' => 'required|date',
            'motif' => 'required|string|min:4|max:255',
            'description' => 'nullable|string|max:1000',
            'signalement_id' => 'nullable|exists:signalements_comportement,id',
        ]);

        $stagiaire = Stagiaire::with(['user', 'groupe'])->findOrFail($request->stagiaire_id);

        ConseilDiscipline::create([
            'stagiaire_id' => $stagiaire->id,
            'type' => $request->type,
            'motif' => $request->motif,
            'description' => $request->description,
            'date_conseil' => $request->date_conseil,
            'statut' => 'CONVOQUE',
            'reduction_points' => 5.00,
            'cree_par_id' => auth()->id(),
            'signalement_id' => $request->signalement_id,
        ]);

        if ($request->filled('signalement_id')) {
            SignalementComportement::where('id', $request->signalement_id)->update(['statut' => 'CONSEIL_PROGRAMME']);
        }

        // Recalculer automatiquement la note de comportement (-5 points déduits)
        $statsDiscipline = $stagiaire->recalculerNoteDiscipline();

        // Notification envoyée au stagiaire
        Notification::create([
            'destinataire_id' => $stagiaire->user_id,
            'type_notification' => 'CONSEIL_DISCIPLINE_ADMIN',
            'titre' => 'Convocation au Conseil de Discipline (Comportement)',
            'message' => "L'administration vous convoque à un conseil de discipline pour le " . Carbon::parse($request->date_conseil)->format('d/m/Y') . ". Motif : \"{$request->motif}\". Une déduction automatique de -5 points a été appliquée à votre note de comportement.",
            'lien_action' => '/stagiaire/note-discipline',
        ]);

        $nomStagiaire = $stagiaire->user->name ?? 'Stagiaire';
        return back()->with('success', "Conseil de discipline pour comportement programmé pour {$nomStagiaire}. Une réduction automatique de -5 points a été appliquée à sa note de comportement (Nouvelle note : {$statsDiscipline['note']}/20.00).");
    }

    public function updateConseilStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:CONVOQUE,TENU,SANCTIONNE,CLOTURE',
        ]);

        $conseil = ConseilDiscipline::with('stagiaire')->findOrFail($id);
        $conseil->update(['statut' => $request->statut]);

        return back()->with('success', "Le statut du conseil de discipline #{$conseil->id} a été mis à jour : [{$request->statut}].");
    }

    public function destroyConseil($id)
    {
        $conseil = ConseilDiscipline::with('stagiaire')->findOrFail($id);
        $stagiaire = $conseil->stagiaire;
        $conseil->delete();

        // Recalculer la note (restitue automatiquement les 5 points au stagiaire)
        $stats = $stagiaire->recalculerNoteDiscipline();

        return back()->with('success', "Le conseil de discipline a été supprimé. Les 5 points ont été restitués au stagiaire (Note de comportement : {$stats['note']}/20.00).");
    }

    /**
     * Export CSV des absences filtrées
     */
    private function exportAbsencesCsv($absences)
    {
        $filename = 'absences_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($absences) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['ID Absence', 'Date', 'Séance', 'Stagiaire', 'CNE', 'Groupe', 'Enseignant', 'Statut', 'Motif']);

            foreach ($absences as $abs) {
                fputcsv($handle, [
                    $abs->id,
                    $abs->date_absence->format('d/m/Y'),
                    $abs->seance->code_seance ?? 'N/A',
                    $abs->stagiaire->user->name ?? 'N/A',
                    $abs->stagiaire->matricule_cne ?? 'N/A',
                    $abs->stagiaire->groupe->code_groupe ?? 'N/A',
                    $abs->enseignant->name ?? 'N/A',
                    $abs->statut_absence,
                    $abs->motif_enseignant,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
