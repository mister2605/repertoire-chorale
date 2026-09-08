<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Répond à une seule question : "de quelle chorale parle-t-on, là, maintenant ?"
 *
 * En temps normal la réponse vient de l'utilisateur connecté. En console
 * (seeders, commandes artisan, tests), on peut la forcer avec forcer().
 */
class ChoraleCourante
{
    protected ?int $forcee = null;

    /** Force la chorale courante (seeders, commandes, tests). */
    public function forcer(?int $choraleId): void
    {
        $this->forcee = $choraleId;
    }

    /** Exécute un bloc de code "dans" une chorale donnée, puis restaure l'état. */
    public function pour(int $choraleId, callable $callback): mixed
    {
        $precedente = $this->forcee;
        $this->forcee = $choraleId;

        try {
            return $callback();
        } finally {
            $this->forcee = $precedente;
        }
    }

    public function id(): ?int
    {
        if ($this->forcee !== null) {
            return $this->forcee;
        }

        return Auth::user()?->chorale_id;
    }
}
