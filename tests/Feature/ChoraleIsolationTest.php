<?php

namespace Tests\Feature;

use App\Models\Chant;
use App\Models\Chorale;
use App\Models\User;
use App\Support\ChoraleCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les 5 tests qui comptent : ils vérifient qu'aucune chorale ne voit
 * les données d'une autre, et qu'un choriste ne peut pas modifier le répertoire.
 */
class ChoraleIsolationTest extends TestCase
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

    protected function membre(Chorale $chorale, string $role): User
    {
        return User::create([
            'name' => "Membre {$role}",
            'email' => $role.'-'.$chorale->slug.'@test.local',
            'password' => 'motdepasse',
            'role' => $role,
            'chorale_id' => $chorale->id,
        ]);
    }

    protected function chant(Chorale $chorale, User $auteur, string $titre): Chant
    {
        return app(ChoraleCourante::class)->pour($chorale->id, fn () => Chant::create([
            'titre' => $titre,
            'paroles' => 'Des paroles.',
            'created_by' => $auteur->id,
        ]));
    }

    public function test_un_visiteur_non_connecte_est_refuse(): void
    {
        $this->getJson('/api/chants')->assertStatus(401);
    }

    public function test_un_choriste_ne_peut_pas_creer_un_chant(): void
    {
        Sanctum::actingAs($this->membre($this->choraleA, 'choriste'), ['*']);

        $this->postJson('/api/chants', [
            'titre' => 'Chant interdit',
            'paroles' => 'Des paroles.',
        ])->assertStatus(403);
    }

    public function test_un_maitre_de_choeur_peut_creer_un_chant_dans_sa_chorale(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');
        Sanctum::actingAs($maitre, ['*']);

        $this->postJson('/api/chants', [
            'titre' => 'Nouveau chant',
            'paroles' => 'Des paroles.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('chants', [
            'titre' => 'Nouveau chant',
            'chorale_id' => $this->choraleA->id,
        ]);
    }

    public function test_la_liste_ne_montre_que_les_chants_de_sa_chorale(): void
    {
        $maitreA = $this->membre($this->choraleA, 'maitre_choeur');
        $maitreB = $this->membre($this->choraleB, 'maitre_choeur');

        $this->chant($this->choraleA, $maitreA, 'Chant de A');
        $this->chant($this->choraleB, $maitreB, 'Chant de B');

        Sanctum::actingAs($maitreA, ['*']);

        $this->getJson('/api/chants')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['titre' => 'Chant de A'])
            ->assertJsonMissing(['titre' => 'Chant de B']);
    }

    public function test_un_chant_d_une_autre_chorale_est_introuvable(): void
    {
        $maitreA = $this->membre($this->choraleA, 'maitre_choeur');
        $maitreB = $this->membre($this->choraleB, 'maitre_choeur');

        $chantDeB = $this->chant($this->choraleB, $maitreB, 'Chant de B');

        Sanctum::actingAs($maitreA, ['*']);

        $this->getJson("/api/chants/{$chantDeB->id}")->assertStatus(404);
        $this->putJson("/api/chants/{$chantDeB->id}", ['paroles' => 'Piraté'])->assertStatus(404);
        $this->deleteJson("/api/chants/{$chantDeB->id}")->assertStatus(404);
    }
}
