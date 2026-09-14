<?php

namespace Tests\Feature;

use App\Models\Chant;
use App\Models\Chorale;
use App\Models\User;
use App\Support\ChoraleCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gestion des comptes et historique des paroles.
 *
 * On teste surtout ce qui fait mal quand ça casse : les droits, l'isolation
 * entre chorales, et les deux verrous qui évitent de se retrouver enfermé
 * dehors (dernier maître de chœur, suppression de soi-même).
 */
class MembresEtVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected Chorale $choraleA;

    protected Chorale $choraleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->choraleA = Chorale::create(['nom' => 'Chorale A', 'slug' => 'a']);
        $this->choraleB = Chorale::create(['nom' => 'Chorale B', 'slug' => 'b']);
    }

    protected function membre(Chorale $chorale, string $role, string $suffixe = ''): User
    {
        return User::create([
            'name' => "Membre {$role}{$suffixe}",
            'email' => $role.$suffixe.'-'.$chorale->slug.'@test.local',
            'password' => 'motdepasse',
            'role' => $role,
            'chorale_id' => $chorale->id,
        ]);
    }

    // --- Comptes ---

    public function test_un_choriste_ne_peut_pas_lister_les_membres(): void
    {
        Sanctum::actingAs($this->membre($this->choraleA, 'choriste'), ['*']);

        $this->getJson('/api/membres')->assertStatus(403);
    }

    public function test_la_liste_des_membres_est_limitee_a_sa_chorale(): void
    {
        $maitreA = $this->membre($this->choraleA, 'maitre_choeur');
        $this->membre($this->choraleB, 'choriste');

        Sanctum::actingAs($maitreA, ['*']);

        $this->getJson('/api/membres')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['email' => $maitreA->email]);
    }

    public function test_le_maitre_de_choeur_cree_un_compte_et_recoit_un_lien(): void
    {
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        $reponse = $this->postJson('/api/membres', [
            'name' => 'Nouvelle choriste',
            'email' => 'nouvelle@test.local',
            'role' => 'choriste',
        ])->assertStatus(201);

        $this->assertStringContainsString('invitation=', $reponse->json('lien_invitation'));

        // Le compte est rattaché à la chorale du créateur, jamais à une autre
        $this->assertDatabaseHas('users', [
            'email' => 'nouvelle@test.local',
            'chorale_id' => $this->choraleA->id,
        ]);
    }

    public function test_on_ne_peut_pas_modifier_un_membre_d_une_autre_chorale(): void
    {
        $maitreA = $this->membre($this->choraleA, 'maitre_choeur');
        $choristeB = $this->membre($this->choraleB, 'choriste');

        Sanctum::actingAs($maitreA, ['*']);

        $this->putJson("/api/membres/{$choristeB->id}", ['role' => 'maitre_choeur'])->assertStatus(403);
        $this->deleteJson("/api/membres/{$choristeB->id}")->assertStatus(403);
    }

    public function test_on_ne_peut_pas_supprimer_son_propre_compte(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');
        Sanctum::actingAs($maitre, ['*']);

        $this->deleteJson("/api/membres/{$maitre->id}")->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $maitre->id]);
    }

    public function test_on_ne_peut_pas_retrograder_le_dernier_maitre_de_choeur(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');
        $autre = $this->membre($this->choraleA, 'maitre_choeur', '-2');

        Sanctum::actingAs($maitre, ['*']);

        // Tant qu'il y en a deux, c'est permis
        $this->putJson("/api/membres/{$autre->id}", ['role' => 'choriste'])->assertOk();

        // Il n'en reste qu'un : on refuse, sinon plus personne n'administre la chorale
        $this->putJson("/api/membres/{$maitre->id}", ['role' => 'choriste'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_le_lien_d_invitation_permet_de_definir_son_mot_de_passe(): void
    {
        $choriste = $this->membre($this->choraleA, 'choriste');
        $token = Password::broker('invitations')->createToken($choriste);

        $this->postJson('/invitation/definir-mot-de-passe', [
            'token' => $token,
            'email' => $choriste->email,
            'password' => 'monNouveauMotDePasse',
            'password_confirmation' => 'monNouveauMotDePasse',
        ])->assertOk();

        // Le nouveau mot de passe fonctionne
        $this->postJson('/login', [
            'email' => $choriste->email,
            'password' => 'monNouveauMotDePasse',
        ])->assertOk();

        // Et le jeton ne resservira pas
        $this->postJson('/invitation/definir-mot-de-passe', [
            'token' => $token,
            'email' => $choriste->email,
            'password' => 'encoreUnAutreMotDePasse',
            'password_confirmation' => 'encoreUnAutreMotDePasse',
        ])->assertStatus(422);
    }

    // --- Historique des paroles ---

    protected function chantAvecHistorique(Chorale $chorale, User $auteur): Chant
    {
        return app(ChoraleCourante::class)->pour($chorale->id, function () use ($auteur) {
            $chant = Chant::create([
                'titre' => 'Chant test',
                'paroles' => 'Paroles version 1',
                'created_by' => $auteur->id,
            ]);

            $chant->mettreAJourParoles('Paroles version 2', $auteur);

            return $chant->fresh();
        });
    }

    public function test_un_choriste_ne_peut_pas_restaurer_une_version(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');
        $choriste = $this->membre($this->choraleA, 'choriste');
        $chant = $this->chantAvecHistorique($this->choraleA, $maitre);
        $version = $chant->versions()->first();

        Sanctum::actingAs($choriste, ['*']);

        $this->postJson("/api/chants/{$chant->id}/versions/{$version->id}/restaurer")->assertStatus(403);
        $this->assertSame('Paroles version 2', $chant->fresh()->paroles);
    }

    public function test_restaurer_une_version_archive_la_version_courante(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');
        $chant = $this->chantAvecHistorique($this->choraleA, $maitre);
        $version = $chant->versions()->first(); // contient "Paroles version 1"

        Sanctum::actingAs($maitre, ['*']);

        $this->postJson("/api/chants/{$chant->id}/versions/{$version->id}/restaurer")->assertOk();

        $chant->refresh();
        $this->assertSame('Paroles version 1', $chant->paroles);

        // Rien n'est perdu : la version 2 est maintenant dans l'historique
        $this->assertSame(2, $chant->versions()->count());
        $this->assertTrue($chant->versions->contains('paroles', 'Paroles version 2'));
    }
}
