<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\ConseilDiscipline;
use App\Models\EnseignantGroupe;
use App\Models\Groupe;
use App\Models\Justificatif;
use App\Models\Seance;
use App\Models\Stagiaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enseignant;
    private Groupe $groupe;
    private Seance $seance;
    private Stagiaire $stagiaire;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.ma',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'statut_compte' => 'actif',
        ]);

        $this->enseignant = User::create([
            'name' => 'Prof Test',
            'email' => 'prof@test.ma',
            'password' => Hash::make('secret123'),
            'role' => 'enseignant',
            'statut_compte' => 'actif',
        ]);

        $this->groupe = Groupe::create([
            'code_groupe' => 'DEV101',
            'nom_groupe' => 'Dev Digital',
            'filiere' => 'Dev',
            'annee_formation' => 1,
            'id_responsable' => $this->enseignant->id,
        ]);

        EnseignantGroupe::create([
            'enseignant_id' => $this->enseignant->id,
            'groupe_id' => $this->groupe->id,
            'matiere' => 'Laravel',
        ]);

        $this->seance = Seance::create([
            'code_seance' => 'S1',
            'libelle' => '08h30 - 11h00',
            'heure_debut' => '08:30:00',
            'heure_fin' => '11:00:00',
        ]);

        $stagiaireUser = User::create([
            'name' => 'Stagiaire Test',
            'email' => 'stagiaire@test.ma',
            'password' => Hash::make('secret123'),
            'role' => 'stagiaire',
            'statut_compte' => 'actif',
        ]);

        $this->stagiaire = Stagiaire::create([
            'user_id' => $stagiaireUser->id,
            'matricule_cne' => 'M100001',
            'groupe_id' => $this->groupe->id,
            'note_discipline' => 20.00,
        ]);
    }

    public function test_admin_can_create_edit_and_delete_user_and_stagiaire_profile_auto_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Nouveau Stagiaire',
            'email' => 'nouveau.stagiaire@test.ma',
            'password' => 'password123',
            'role' => 'stagiaire',
            'groupe_id' => $this->groupe->id,
            'matricule_cne' => 'M999999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'nouveau.stagiaire@test.ma', 'role' => 'stagiaire']);
        $createdUser = User::where('email', 'nouveau.stagiaire@test.ma')->firstOrFail();
        $this->assertDatabaseHas('stagiaires', ['user_id' => $createdUser->id, 'matricule_cne' => 'M999999']);

        // Edit user
        $this->actingAs($this->admin)->put(route('admin.users.update', $createdUser->id), [
            'name' => 'Nouveau Stagiaire Modifie',
            'email' => 'nouveau.stagiaire@test.ma',
            'role' => 'stagiaire',
            'telephone' => '+212600000000',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $createdUser->id, 'name' => 'Nouveau Stagiaire Modifie']);

        // Delete user
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $createdUser->id))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $createdUser->id]);
        $this->assertDatabaseMissing('stagiaires', ['user_id' => $createdUser->id]);
    }

    public function test_stagiaire_without_profile_renders_gracefully_instead_of_crashing(): void
    {
        $orphanUser = User::create([
            'name' => 'Orphan Stagiaire',
            'email' => 'orphan@test.ma',
            'password' => Hash::make('secret123'),
            'role' => 'stagiaire',
            'statut_compte' => 'actif',
        ]);

        $this->actingAs($orphanUser)->get(route('stagiaire.dashboard'))->assertOk()->assertSee('Dossier Stagiaire');
        $this->actingAs($orphanUser)->get(route('stagiaire.absences'))->assertOk()->assertSee('Dossier Stagiaire');
        $this->actingAs($orphanUser)->get(route('stagiaire.note-discipline'))->assertOk()->assertSee('Dossier Stagiaire');
    }

    public function test_admin_can_manage_seances_groupes_and_enseignants(): void
    {
        $this->actingAs($this->admin)->get(route('admin.seances'))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.seances.store'), [
            'code_seance' => 'S5',
            'libelle' => '18h30 - 20h30',
            'heure_debut' => '18:30',
            'heure_fin' => '20:30',
        ])->assertRedirect();

        $seance = Seance::where('code_seance', 'S5')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.seances.update', $seance->id), [
            'code_seance' => 'S5',
            'libelle' => '18h30 - 20h00',
            'heure_debut' => '18:30',
            'heure_fin' => '20:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'libelle' => '18h30 - 20h00']);

        $this->actingAs($this->admin)->delete(route('admin.seances.destroy', $seance->id))->assertRedirect();
        $this->assertDatabaseMissing('seances', ['id' => $seance->id]);

        $this->actingAs($this->admin)->get(route('admin.enseignants'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.groupes'))->assertOk();
    }

    public function test_enseignant_records_absence_and_stagiaire_uploads_secure_justificatif_and_permissions(): void
    {
        // Enseignant records an absence
        $this->actingAs($this->enseignant)->post(route('enseignant.saisir-absence.store'), [
            'stagiaire_id' => $this->stagiaire->id,
            'seance_id' => $this->seance->id,
            'date_absence' => now()->toDateString(),
            'motif_enseignant' => 'Absent au TP',
        ])->assertRedirect(route('enseignant.dashboard'));

        $absence = Absence::where('stagiaire_id', $this->stagiaire->id)->firstOrFail();

        // Stagiaire uploads justificatif PDF
        $file = UploadedFile::fake()->create('certificat.pdf', 120, 'application/pdf');
        $this->actingAs($this->stagiaire->user)->post(route('stagiaire.absences.justificatif', $absence->id), [
            'fichier' => $file,
            'commentaire' => 'Certificat Dr Alami',
        ])->assertRedirect();

        $justif = Justificatif::where('absence_id', $absence->id)->firstOrFail();
        $this->assertEquals('Certificat Dr Alami', $justif->commentaire_stagiaire);
        Storage::disk('local')->assertExists($justif->chemin_fichier);

        // Permissions on secure file route
        $this->actingAs($this->admin)->get(route('justificatifs.file', $justif->id))->assertOk();
        $this->actingAs($this->enseignant)->get(route('justificatifs.file', $justif->id))->assertOk();
        $this->actingAs($this->stagiaire->user)->get(route('justificatifs.file', $justif->id))->assertOk();

        // Another stagiaire cannot access it
        $otherUser = User::create([
            'name' => 'Autre Stagiaire',
            'email' => 'autre@test.ma',
            'password' => Hash::make('secret123'),
            'role' => 'stagiaire',
            'statut_compte' => 'actif',
        ]);
        Stagiaire::create([
            'user_id' => $otherUser->id,
            'matricule_cne' => 'M200002',
            'groupe_id' => $this->groupe->id,
            'note_discipline' => 20.00,
        ]);
        $this->actingAs($otherUser)->get(route('justificatifs.file', $justif->id))->assertForbidden();

        // Enseignant views justificatifs & absences pages
        $this->actingAs($this->enseignant)->get(route('enseignant.justificatifs'))->assertOk()->assertSee('certificat.pdf');
        $this->actingAs($this->enseignant)->get(route('enseignant.absences'))->assertOk();

        // Admin validates justificatif
        $this->actingAs($this->admin)->post(route('admin.justificatifs.valider', $justif->id), [
            'decision' => 'ACCEPTE',
            'motif_decision' => 'Conforme',
        ])->assertRedirect();

        $this->assertDatabaseHas('justificatifs', ['id' => $justif->id, 'statut_decision' => 'ACCEPTE']);
        $this->assertDatabaseHas('absences', ['id' => $absence->id, 'statut_absence' => 'JUSTIFIEE_ACCEPTEE']);
    }

    public function test_justificatif_deletion_removes_file_and_resets_absence(): void
    {
        $absence = Absence::create([
            'stagiaire_id' => $this->stagiaire->id,
            'enseignant_id' => $this->enseignant->id,
            'seance_id' => $this->seance->id,
            'date_absence' => now()->toDateString(),
            'heure_enregistrement' => now(),
            'statut_absence' => 'NON_JUSTIFIEE',
        ]);

        $file = UploadedFile::fake()->create('justif.png', 100, 'image/png');
        $this->actingAs($this->stagiaire->user)->post(route('stagiaire.absences.justificatif', $absence->id), [
            'fichier' => $file,
        ])->assertRedirect();

        $justif = Justificatif::where('absence_id', $absence->id)->firstOrFail();
        $path = $justif->chemin_fichier;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->stagiaire->user)->delete(route('stagiaire.absences.justificatif.destroy', $absence->id))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('justificatifs', ['id' => $justif->id]);
        $this->assertDatabaseHas('absences', ['id' => $absence->id, 'statut_absence' => 'NON_JUSTIFIEE']);
    }
}
