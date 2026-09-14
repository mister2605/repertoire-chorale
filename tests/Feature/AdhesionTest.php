<?php

namespace Tests\Feature;

use App\Models\Chorale;
use App\Models\Pupitre;
use App\Models\User;
use App\Support\ChoraleCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Adhésion en libre-service par lien unique.
 *
 * Le test le plus important du fichier est
 * test_le_role_ne_peut_pas_etre_choisi_par_le_visiteur : c'est lui qui garantit
 * qu'un lien WhatsApp transféré ne fabrique pas un administrateur.
 */
class AdhesionTest extends TestCase
{
    use RefreshDatabase;

    protected Chorale $chorale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chorale = Chorale::create([
            'nom' => 'Chorale A',
            'slug' => 'a',
            'adhesion_ouverte' => true,
        ]);
    }

    protected function code(): string
    {
        return $this->chorale->fresh()->code_adhesion;
    }

    protected function donnees(array $extra = []): array
    {
        return array_merge([
            'name' => 'Nouveau choriste',
            'email' => 'nouveau@test.local',
            'password' => 'unMotDePasseSolide',
            'password_confirmation' => 'unMotDePasseSolide',
        ], $extra);
    }

    public function test_le_lien_donne_le_nom_de_la_chorale_et_ses_pupitres(): void
    {
        app(ChoraleCourante::class)->pour($this->chorale->id, function () {
            Pupitre::create(['nom' => 'Soprano']);
            Pupitre::create(['nom' => 'Basse']);
        });

        $this->getJson('/adhesion/'.$this->code())
            ->assertOk()
            ->assertJsonPath('chorale.nom', 'Chorale A')
            ->assertJsonCount(2, 'pupitres');
    }

    public function test_un_code_inconnu_renvoie_404(): void
    {
        $this->getJson('/adhesion/code-invente')->assertStatus(404);
    }

    public function test_le_lien_ne_marche_pas_quand_l_adhesion_est_fermee(): void
    {
        $code = $this->code();
        $this->chorale->update(['adhesion_ouverte' => false]);

        $this->getJson('/adhesion/'.$code)->assertStatus(404);
        $this->postJson('/adhesion/'.$code, $this->donnees())->assertStatus(404);
    }

    public function test_un_choriste_cree_son_compte_et_est_connecte(): void
    {
        $this->postJson('/adhesion/'.$this->code(), $this->donnees())
            ->assertStatus(201)
            ->assertJsonPath('role', 'choriste');

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@test.local',
            'role' => 'choriste',
            'chorale_id' => $this->chorale->id,
        ]);

        $this->assertAuthenticated();
    }

    /**
     * LE test de sécurité de ce fichier.
     * Un lien d'adhésion circule dans WhatsApp : il se transfère, se copie,
     * sort du groupe. Si le rôle venait du formulaire, n'importe quel
     * destinataire deviendrait administrateur du répertoire.
     */
    public function test_le_role_ne_peut_pas_etre_choisi_par_le_visiteur(): void
    {
        $this->postJson('/adhesion/'.$this->code(), $this->donnees([
            'role' => 'maitre_choeur',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@test.local',
            'role' => 'choriste', // et surtout pas maitre_choeur
        ]);
    }

    public function test_on_ne_peut_pas_se_rattacher_a_une_autre_chorale(): void
    {
        $autre = Chorale::create(['nom' => 'Chorale B', 'slug' => 'b']);

        $this->postJson('/adhesion/'.$this->code(), $this->donnees([
            'chorale_id' => $autre->id,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@test.local',
            'chorale_id' => $this->chorale->id,
        ]);
    }

    public function test_un_pupitre_d_une_autre_chorale_est_ignore(): void
    {
        $autre = Chorale::create(['nom' => 'Chorale B', 'slug' => 'b']);
        $pupitreAilleurs = app(ChoraleCourante::class)->pour(
            $autre->id,
            fn () => Pupitre::create(['nom' => 'Soprano'])
        );

        $this->postJson('/adhesion/'.$this->code(), $this->donnees([
            'pupitre_id' => $pupitreAilleurs->id,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@test.local',
            'pupitre_id' => null,
        ]);
    }

    public function test_regenerer_le_lien_invalide_l_ancien(): void
    {
        $ancienCode = $this->code();

        Sanctum::actingAs(User::create([
            'name' => 'Maitre',
            'email' => 'maitre@test.local',
            'password' => 'motdepasse',
            'role' => 'maitre_choeur',
            'chorale_id' => $this->chorale->id,
        ]), ['*']);

        $reponse = $this->postJson('/api/chorale/adhesion/regenerer')->assertOk();

        $this->assertStringNotContainsString($ancienCode, $reponse->json('lien_adhesion'));
        $this->getJson('/adhesion/'.$ancienCode)->assertStatus(404);
    }

    public function test_un_choriste_ne_peut_pas_voir_ni_changer_le_lien(): void
    {
        Sanctum::actingAs(User::create([
            'name' => 'Choriste',
            'email' => 'choriste@test.local',
            'password' => 'motdepasse',
            'role' => 'choriste',
            'chorale_id' => $this->chorale->id,
        ]), ['*']);

        $this->getJson('/api/chorale')->assertStatus(403);
        $this->postJson('/api/chorale/adhesion', ['ouverte' => true])->assertStatus(403);
        $this->postJson('/api/chorale/adhesion/regenerer')->assertStatus(403);
    }
}
