<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Groupe;
use App\Models\Stagiaire;
use App\Models\Seance;
use App\Models\Absence;
use App\Models\Justificatif;
use App\Models\DeclarationEnseignant;
use App\Models\Notification;
use App\Models\SignalementComportement;

class EnseignantController extends Controller
{
    /**
     * Tableau de bord Enseignant
     */
    public function dashboard()
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with('groupe.stagiaires')->get();
        $groupeIds = $groupesAttribués->pluck('groupe_id');

        $totalStagiaires = Stagiaire::whereIn('groupe_id', $groupeIds)->count();
        $mesAbsencesSaisies = Absence::where('enseignant_id', $user->id)->count();
        $mesDeclarations = DeclarationEnseignant::where('enseignant_id', $user->id)->count();

        $justificatifsCount = Justificatif::whereHas('absence', function ($q) use ($user, $groupeIds) {
            $q->where('enseignant_id', $user->id)
              ->orWhereHas('stagiaire', fn ($sq) => $sq->whereIn('groupe_id', $groupeIds));
        })->count();

        $recentesAbsences = Absence::with(['stagiaire.user', 'stagiaire.groupe', 'seance', 'justificatif'])
            ->where('enseignant_id', $user->id)
            ->latest()
            ->take(6)
            ->get();

        return view('enseignant.dashboard', compact(
            'groupesAttribués',
            'totalStagiaires',
            'mesAbsencesSaisies',
            'mesDeclarations',
            'justificatifsCount',
            'recentesAbsences'
        ));
    }

    /**
     * Tâche 9: Consultation des listes de stagiaires des groupes attribués
     */
    public function stagiaires(Request $request)
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with('groupe')->get();
        $groupeIds = $groupesAttribués->pluck('groupe_id');

        $query = Stagiaire::with(['user', 'groupe', 'absences'])
            ->whereIn('groupe_id', $groupeIds);

        if ($request->filled('groupe_id')) {
            $query->where('groupe_id', $request->groupe_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $stagiaires = $query->paginate(15)->withQueryString();

        return view('enseignant.stagiaires', compact('stagiaires', 'groupesAttribués'));
    }

    /**
     * Consultation et gestion des absences des groupes attribués
     */
    public function absences(Request $request)
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with('groupe')->get();
        $groupeIds = $groupesAttribués->pluck('groupe_id');

        $query = Absence::with(['stagiaire.user', 'stagiaire.groupe', 'seance', 'justificatif', 'enseignant'])
            ->where(function ($q) use ($user, $groupeIds) {
                $q->where('enseignant_id', $user->id)
                  ->orWhereHas('stagiaire', fn ($sq) => $sq->whereIn('groupe_id', $groupeIds));
            });

        if ($request->filled('groupe_id')) {
            $query->whereHas('stagiaire', fn ($sq) => $sq->where('groupe_id', $request->groupe_id));
        }

        if ($request->filled('statut_absence')) {
            $query->where('statut_absence', $request->statut_absence);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('stagiaire', function ($q) use ($search) {
                $q->where('matricule_cne', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($qu) => $qu->where('name', 'like', "%{$search}%"));
            });
        }

        $absences = $query->latest('date_absence')->paginate(15)->withQueryString();

        return view('enseignant.absences', compact('absences', 'groupesAttribués'));
    }

    /**
     * Modification du motif d'une absence saisie par l'enseignant (si non validée)
     */
    public function updateAbsence(Request $request, $id)
    {
        $request->validate([
            'motif_enseignant' => 'nullable|string|max:500',
        ]);

        $absence = Absence::where('id', $id)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        $absence->update([
            'motif_enseignant' => $request->motif_enseignant,
        ]);

        return back()->with('success', 'Remarque d\'absence mise à jour avec succès.');
    }

    /**
     * Suppression d'une absence saisie par erreur par l'enseignant (si aucun justificatif n'a été déposé)
     */
    public function destroyAbsence($id)
    {
        $absence = Absence::with(['stagiaire', 'justificatif'])
            ->where('id', $id)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        if ($absence->justificatif || $absence->statut_absence !== 'NON_JUSTIFIEE') {
            return back()->with('error', 'Impossible de supprimer cette absence car un justificatif ou une décision administrative y est associé.');
        }

        $stagiaire = $absence->stagiaire;
        $absence->delete();
        if ($stagiaire) {
            $stagiaire->recalculerNoteDiscipline();
        }

        return back()->with('success', 'Absence annulée et supprimée de la base de données.');
    }

    /**
     * Consultation des justificatifs des stagiaires des groupes attribués
     */
    public function justificatifs(Request $request)
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with('groupe')->get();
        $groupeIds = $groupesAttribués->pluck('groupe_id');

        $query = Justificatif::with(['absence.stagiaire.user', 'absence.stagiaire.groupe', 'absence.seance', 'absence.enseignant', 'adminValidateur'])
            ->whereHas('absence', function ($q) use ($user, $groupeIds) {
                $q->where('enseignant_id', $user->id)
                  ->orWhereHas('stagiaire', fn ($sq) => $sq->whereIn('groupe_id', $groupeIds));
            });

        if ($request->filled('statut_decision')) {
            $query->where('statut_decision', $request->statut_decision);
        }

        if ($request->filled('groupe_id')) {
            $query->whereHas('absence.stagiaire', fn ($sq) => $sq->where('groupe_id', $request->groupe_id));
        }

        $justificatifs = $query->latest('date_depot')->paginate(15)->withQueryString();

        return view('enseignant.justificatifs', compact('justificatifs', 'groupesAttribués'));
    }

    /**
     * Formulaire d'enregistrement d'absence (Tâche 10)
     */
    public function createAbsence()
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with(['groupe.stagiaires.user'])->get();
        $seances = Seance::all();

        return view('enseignant.saisir-absence', compact('groupesAttribués', 'seances'));
    }

    /**
     * Tâche 10: Enregistrement d'une absence dans la base de données
     * Tâche 18: Notification immédiate au stagiaire concerné
     */
    public function storeAbsence(Request $request)
    {
        $request->validate([
            'stagiaire_id' => 'required|exists:stagiaires,id',
            'seance_id' => 'required|exists:seances,id',
            'date_absence' => 'required|date',
            'motif_enseignant' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $groupeIds = $user->enseignantGroupes()->pluck('groupe_id');

        $stagiaire = Stagiaire::with('user')->findOrFail($request->stagiaire_id);
        if (!$groupeIds->contains($stagiaire->groupe_id)) {
            return back()->with('error', 'Ce stagiaire n\'appartient pas à l\'un de vos groupes attribués.');
        }

        $seance = Seance::findOrFail($request->seance_id);

        $absence = Absence::create([
            'stagiaire_id' => $stagiaire->id,
            'enseignant_id' => $user->id,
            'seance_id' => $seance->id,
            'date_absence' => $request->date_absence,
            'heure_enregistrement' => Carbon::now(),
            'statut_absence' => 'NON_JUSTIFIEE',
            'motif_enseignant' => $request->motif_enseignant ?? 'Absence constatée lors de l\'appel en cours.',
            'est_corrigee' => false,
        ]);

        // Notification envoyée au stagiaire (Tâche 18)
        Notification::create([
            'destinataire_id' => $stagiaire->user_id,
            'type_notification' => 'ABSENCE_STAGIAIRE',
            'titre' => 'Nouvelle absence enregistrée',
            'message' => "Une absence a été signalée à votre nom le {$absence->date_absence->format('d/m/Y')} ({$seance->code_seance} - {$seance->libelle}) par M./Mme {$user->name}. Vous disposez d'un délai strict de 48 heures pour téléverser votre pièce justificative.",
            'lien_action' => '/stagiaire/absences',
        ]);

        // Recalcul automatique et vérification du seuil critique des 15h
        $stagiaire->recalculerNoteDiscipline();

        return redirect()->route('enseignant.dashboard')->with('success', "Absence enregistrée avec succès pour le stagiaire " . ($stagiaire->user->name ?? 'Stagiaire') . ". Une alerte avec le délai de 48h lui a été envoyée.");
    }

    /**
     * Formulaire de signalement de comportement d'un stagiaire par le professeur
     */
    public function createDeclarationComportement()
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with(['groupe.stagiaires.user'])->get();
        $seances = Seance::all();

        $mesSignalements = SignalementComportement::with(['stagiaire.user', 'stagiaire.groupe', 'seance'])
            ->where('enseignant_id', $user->id)
            ->latest('date_incident')
            ->paginate(10);

        return view('enseignant.declarer-comportement', compact('groupesAttribués', 'seances', 'mesSignalements'));
    }

    /**
     * Enregistrement du signalement de comportement
     * La déclaration s'affiche dans les notifications de l'administration
     */
    public function storeDeclarationComportement(Request $request)
    {
        $request->validate([
            'stagiaire_id' => 'required|exists:stagiaires,id',
            'seance_id' => 'nullable|exists:seances,id',
            'date_incident' => 'required|date',
            'motif' => 'required|string|min:4|max:255',
            'description' => 'required|string|min:10|max:1500',
            'gravite' => 'required|in:FAIBLE,MOYENNE,GRAVE',
        ]);

        $prof = auth()->user();
        $groupeIds = $prof->enseignantGroupes()->pluck('groupe_id');
        $stagiaire = Stagiaire::with(['user', 'groupe'])->findOrFail($request->stagiaire_id);

        if (!$groupeIds->contains($stagiaire->groupe_id)) {
            return back()->with('error', 'Ce stagiaire n\'appartient pas à l\'un de vos groupes attribués.');
        }

        $signalement = SignalementComportement::create([
            'enseignant_id' => $prof->id,
            'stagiaire_id' => $stagiaire->id,
            'seance_id' => $request->seance_id,
            'date_incident' => $request->date_incident,
            'motif' => $request->motif,
            'description' => $request->description,
            'gravite' => $request->gravite,
            'statut' => 'EN_ATTENTE',
        ]);

        // Notifier tous les administrateurs
        $admins = User::where('role', 'admin')->get();
        $codeGroupe = $stagiaire->groupe ? $stagiaire->groupe->code_groupe : 'N/A';
        $nomStagiaire = $stagiaire->user->name ?? 'Stagiaire';
        foreach ($admins as $admin) {
            Notification::create([
                'destinataire_id' => $admin->id,
                'type_notification' => 'SIGNALEMENT_COMPORTEMENT',
                'titre' => "Signalement comportement : {$nomStagiaire}",
                'message' => "Le formateur {$prof->name} a signalé un comportement inacceptable pour {$nomStagiaire} ({$stagiaire->matricule_cne}, Groupe {$codeGroupe}) : \"{$signalement->motif}\". Gravité : {$signalement->gravite}.",
                'lien_action' => '/admin/conseils-discipline',
            ]);
        }

        return redirect()->route('enseignant.declarer-comportement')->with('success', "Le signalement de comportement pour le stagiaire {$nomStagiaire} a été transmis à l'administration.");
    }

    /**
     * Formulaire de déclaration d'absence de l'enseignant (Tâche 11)
     */
    public function createDeclaration()
    {
        $user = auth()->user();
        $groupesAttribués = $user->enseignantGroupes()->with('groupe')->get();
        $seances = Seance::all();

        return view('enseignant.declarer-absence', compact('groupesAttribués', 'seances'));
    }

    /**
     * Tâche 11: Déclaration d'absence de l'enseignant
     * Tâche 17: Notification automatique transmise à l'administration
     */
    public function storeDeclaration(Request $request)
    {
        $request->validate([
            'groupe_id' => 'required|exists:groupes,id',
            'seance_id' => 'required|exists:seances,id',
            'date_absence' => 'required|date',
            'motif' => 'required|string|min:5|max:500',
            'remarques_rattrapage' => 'nullable|string|max:500',
        ]);

        $prof = auth()->user();
        $groupeIds = $prof->enseignantGroupes()->pluck('groupe_id');
        if (!$groupeIds->contains((int) $request->groupe_id)) {
            return back()->with('error', 'Vous ne pouvez déclarer une absence que pour l\'un de vos groupes attribués.');
        }

        $groupe = Groupe::findOrFail($request->groupe_id);
        $seance = Seance::findOrFail($request->seance_id);

        $declaration = DeclarationEnseignant::create([
            'enseignant_id' => $prof->id,
            'seance_id' => $seance->id,
            'groupe_id' => $groupe->id,
            'date_absence' => $request->date_absence,
            'motif' => $request->motif,
            'remarques_rattrapage' => $request->remarques_rattrapage ?? 'Séance de rattrapage à fixer selon le planning disponible.',
            'date_declaration' => Carbon::now(),
            'est_consultee' => false,
        ]);

        // Notifier tous les administrateurs (Tâche 17)
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'destinataire_id' => $admin->id,
                'type_notification' => 'ABSENCE_ENSEIGNANT',
                'titre' => "Alerte : Déclaration d'absence de M. {$prof->name}",
                'message' => "L'enseignant {$prof->name} a déclaré son absence pour le {$declaration->date_absence->format('d/m/Y')} ({$seance->code_seance}) avec le groupe {$groupe->code_groupe}. Motif : {$declaration->motif}",
                'lien_action' => '/admin/declarations',
            ]);
        }

        return redirect()->route('enseignant.dashboard')->with('success', "Votre déclaration d'absence a été transmise à l'administration. Les administrateurs ont été notifiés.");
    }
}
