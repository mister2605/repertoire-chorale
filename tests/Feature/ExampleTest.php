<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Test de fumée : est-ce que l'application démarre ?
 *
 * Le test livré par défaut avec Laravel appelait GET / en attendant une page
 * d'accueil. Ce backend est une API : il n'a pas de page d'accueil, donc ce
 * test échouait depuis le premier jour. On vérifie plutôt la route de santé
 * /up, déclarée dans bootstrap/app.php — c'est aussi elle qu'un service
 * d'hébergement interrogera pour savoir si l'appli répond.
 */
class ExampleTest extends TestCase
{
    public function test_la_route_de_sante_repond(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_l_api_refuse_les_visiteurs_non_connectes(): void
    {
        $this->getJson('/api/chants')->assertStatus(401);
    }
}
