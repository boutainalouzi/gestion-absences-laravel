<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Justificatif extends Model
{
    use HasFactory;

    protected $fillable = [
        'absence_id',
        'nom_fichier',
        'chemin_fichier',
        'type_fichier',
        'taille_octets',
        'date_depot',
        'statut_decision',
        'admin_validateur_id',
        'date_decision',
        'motif_decision',
        'commentaire_stagiaire',
    ];

    protected $casts = [
        'date_depot' => 'datetime',
        'date_decision' => 'datetime',
    ];

    public function absence()
    {
        return $this->belongsTo(Absence::class, 'absence_id');
    }

    public function adminValidateur()
    {
        return $this->belongsTo(User::class, 'admin_validateur_id');
    }

    public function tailleLisible(): string
    {
        $bytes = (int) $this->taille_octets;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' Mo';
        }
        return max(1, round($bytes / 1024)) . ' Ko';
    }

    /**
     * Retourne le chemin absolu réel du fichier (stockage privé Laravel en priorité, puis fallback public legacy).
     */
    public function resolveFilePath(): ?string
    {
        $path = $this->chemin_fichier;
        if (!$path) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        $publicFull = public_path(ltrim($path, '/'));
        if (is_file($publicFull)) {
            return $publicFull;
        }

        return null;
    }

    public function fileExists(): bool
    {
        return $this->resolveFilePath() !== null;
    }

    public function deleteStoredFile(): void
    {
        $path = $this->chemin_fichier;
        if (!$path) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $publicFull = public_path(ltrim($path, '/'));
        if (is_file($publicFull)) {
            @unlink($publicFull);
        }
    }
}
