<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Chant;
use App\Models\Chorale;
use App\Models\Pupitre;
use App\Models\User;
use App\Support\ChoraleCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Réglages : renommer la chorale, gérer pupitres et catégories.
 *
 * Deux choses à protéger : les droits (un choriste ne configure rien) et
 * les données (supprimer un pupitre utilisé effacerait en cascade le lien
 * vers les enregistrements audio de cette voix).
 */
class ReglagesChoraleTest extends TestCase
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
            'active_le' => now(),
        ]);
    }

    protected function dans(Chorale $chorale, callable $callback): mixed
    {
        return app(ChoraleCourante::class)->pour($chorale->id, $callback);
    }

    // --- Droits ---

    public function test_un_choriste_ne_peut_rien_configurer(): void
    {
        $pupitre = $this->dans($this->choraleA, fn () => Pupitre::create(['nom' => 'Soprano']));
        Sanctum::actingAs($this->membre($this->choraleA, 'choriste'), ['*']);

        $this->putJson('/api/chorale', ['nom' => 'Pirate'])->assertStatus(403);
        $this->postJson('/api/pupitres', ['nom' => 'Soliste'])->assertStatus(403);
        $this->putJson("/api/pupitres/{$pupitre->id}", ['nom' => 'Autre'])->assertStatus(403);
        $this->deleteJson("/api/pupitres/{$pupitre->id}")->assertStatus(403);
        $this->postJson('/api/categories', ['nom' => 'Veillée'])->assertStatus(403);
    }

    public function test_un_choriste_peut_quand_meme_lire_les_listes(): void
    {
        $this->dans($this->choraleA, fn () => Pupitre::create(['nom' => 'Soprano']));
        Sanctum::actingAs($this->membre($this->choraleA, 'choriste'), ['*']);

        $this->getJson('/api/pupitres')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/categories')->assertOk();
    }

    // --- Isolation ---

    public function test_on_ne_touche_pas_aux_pupitres_d_une_autre_chorale(): void
    {
        $pupitreB = $this->dans($this->choraleB, fn () => Pupitre::create(['nom' => 'Basse']));
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        // Le filtre automatique masque le pupitre : il est introuvable, pas interdit
        $this->putJson("/api/pupitres/{$pupitreB->id}", ['nom' => 'Pirate'])->assertStatus(404);
        $this->deleteJson("/api/pupitres/{$pupitreB->id}")->assertStatus(404);
    }

    public function test_deux_chorales_peuvent_avoir_un_pupitre_du_meme_nom(): void
    {
        $this->dans($this->choraleB, fn () => Pupitre::create(['nom' => 'Soprano']));
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        $this->postJson('/api/pupitres', ['nom' => 'Soprano'])->assertStatus(201);
    }

    public function test_un_nom_en_double_dans_la_meme_chorale_est_refuse(): void
    {
        $this->dans($this->choraleA, fn () => Pupitre::create(['nom' => 'Soprano']));
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        $this->postJson('/api/pupitres', ['nom' => 'Soprano'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nom');
    }

    // --- Garde-fous de suppression ---

    public function test_un_pupitre_utilise_par_un_chant_ne_peut_pas_etre_supprime(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');

        $pupitre = $this->dans($this->choraleA, function () use ($maitre) {
            $p = Pupitre::create(['nom' => 'Soprano']);
            $chant = Chant::create(['titre' => 'Un chant', 'paroles' => '...', 'created_by' => $maitre->id]);
            $chant->pupitres()->attach($p->id);

            return $p;
        });

        Sanctum::actingAs($maitre, ['*']);

        $this->deleteJson("/api/pupitres/{$pupitre->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('nom');

        $this->assertDatabaseHas('pupitres', ['id' => $pupitre->id]);
    }

    public function test_un_pupitre_libre_peut_etre_supprime(): void
    {
        $pupitre = $this->dans($this->choraleA, fn () => Pupitre::create(['nom' => 'Soliste']));
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        $this->deleteJson("/api/pupitres/{$pupitre->id}")->assertOk();
        $this->assertDatabaseMissing('pupitres', ['id' => $pupitre->id]);
    }

    public function test_une_categorie_utilisee_ne_peut_pas_etre_supprimee(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');

        $categorie = $this->dans($this->choraleA, function () use ($maitre) {
            $c = Categorie::create(['nom' => 'Avent']);
            $chant = Chant::create(['titre' => 'Un chant', 'paroles' => '...', 'created_by' => $maitre->id]);
            $chant->categories()->attach($c->id);

            return $c;
        });

        Sanctum::actingAs($maitre, ['*']);

        $this->deleteJson("/api/categories/{$categorie->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('nom');
    }

    // --- Renommage ---

    public function test_le_maitre_de_choeur_renomme_sa_chorale(): void
    {
        Sanctum::actingAs($this->membre($this->choraleA, 'maitre_choeur'), ['*']);

        $this->putJson('/api/chorale', ['nom' => 'Chorale Sainte-Cécile', 'ville' => 'Bobo-Dioulasso'])
            ->assertOk()
            ->assertJsonPath('nom', 'Chorale Sainte-Cécile');

        // Le slug ne bouge pas : il sert d'identifiant stable
        $this->assertDatabaseHas('chorales', ['id' => $this->choraleA->id, 'slug' => 'a']);
    }

    public function test_renommer_un_pupitre_ne_casse_pas_ses_chants(): void
    {
        $maitre = $this->membre($this->choraleA, 'maitre_choeur');

        $pupitre = $this->dans($this->choraleA, function () use ($maitre) {
            $p = Pupitre::create(['nom' => 'Tenor']);
            $chant = Chant::create(['titre' => 'Un chant', 'paroles' => '...', 'created_by' => $maitre->id]);
            $chant->pupitres()->attach($p->id, ['audio_path' => 'chants/audio/x.mp3']);

            return $p;
        });

        Sanctum::actingAs($maitre, ['*']);

        $this->putJson("/api/pupitres/{$pupitre->id}", ['nom' => 'Ténor'])
            ->assertOk()
            ->assertJsonPath('nom', 'Ténor')
            ->assertJsonPath('chants_count', 1);

        // L'enregistrement audio rattaché à ce pupitre est toujours là
        $this->assertDatabaseHas('chant_pupitre', [
            'pupitre_id' => $pupitre->id,
            'audio_path' => 'chants/audio/x.mp3',
        ]);
    }
}
