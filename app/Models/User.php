<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'prenom',
        'nom',
        'email',
        'password',
        'role',
        'statut_compte',
        'telephone',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return strtolower($this->role) === 'admin';
    }

    public function isEnseignant(): bool
    {
        return strtolower($this->role) === 'enseignant';
    }

    public function isStagiaire(): bool
    {
        return strtolower($this->role) === 'stagiaire';
    }

    public function isActif(): bool
    {
        return strtolower($this->statut_compte) === 'actif';
    }

    public function avatarUrl(): string
    {
        $avatar = $this->avatar;
        if (!$avatar) {
            return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($this->name);
        }

        if (preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?/(uploads/.+)$#i', $avatar, $m)) {
            return asset($m[3]);
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return asset(ltrim($avatar, '/'));
    }

    public function stagiaireProfile()
    {
        return $this->hasOne(Stagiaire::class, 'user_id');
    }

    public function enseignantGroupes()
    {
        return $this->hasMany(EnseignantGroupe::class, 'enseignant_id');
    }

    public function absencesEnregistrees()
    {
        return $this->hasMany(Absence::class, 'enseignant_id');
    }

    public function justificatifsValides()
    {
        return $this->hasMany(Justificatif::class, 'admin_validateur_id');
    }

    public function declarations()
    {
        return $this->hasMany(DeclarationEnseignant::class, 'enseignant_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'destinataire_id')->orderBy('created_at', 'desc');
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class, 'destinataire_id')->where('est_lue', false)->orderBy('created_at', 'desc');
    }

    public function signalementsComportement()
    {
        return $this->hasMany(SignalementComportement::class, 'enseignant_id');
    }

    public function conseilsDisciplineCrees()
    {
        return $this->hasMany(ConseilDiscipline::class, 'cree_par_id');
    }
}
