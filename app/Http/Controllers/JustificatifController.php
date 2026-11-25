<?php

namespace App\Http\Controllers;

use App\Models\Justificatif;
use Illuminate\Http\Request;

class JustificatifController extends Controller
{
    /**
     * Consultation ou téléchargement sécurisé d'un justificatif selon les permissions.
     */
    public function show(Request $request, $id)
    {
        $user = auth()->user();
        $justificatif = Justificatif::with(['absence.stagiaire'])->findOrFail($id);
        $absence = $justificatif->absence;

        $autorise = false;

        if ($user->isAdmin()) {
            $autorise = true;
        } elseif ($user->isEnseignant()) {
            $groupeIds = $user->enseignantGroupes()->pluck('groupe_id');
            if (
                $absence->enseignant_id === $user->id
                || ($absence->stagiaire && $groupeIds->contains($absence->stagiaire->groupe_id))
            ) {
                $autorise = true;
            }
        } elseif ($user->isStagiaire()) {
            $stagiaire = $user->stagiaireProfile;
            if ($stagiaire && $absence->stagiaire_id === $stagiaire->id) {
                $autorise = true;
            }
        }

        if (!$autorise) {
            abort(403, 'Vous n\'êtes pas autorisé à consulter ce justificatif.');
        }

        $fullPath = $justificatif->resolveFilePath();
        if (!$fullPath) {
            abort(404, 'Le fichier justificatif est introuvable sur le serveur.');
        }

        if ($request->boolean('download')) {
            return response()->download($fullPath, $justificatif->nom_fichier);
        }

        return response()->file($fullPath, [
            'Content-Type' => $justificatif->type_fichier ?: mime_content_type($fullPath),
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $justificatif->nom_fichier) . '"',
        ]);
    }
}
