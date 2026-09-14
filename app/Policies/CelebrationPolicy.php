<?php

namespace App\Policies;

use App\Models\Celebration;
use App\Models\User;

/**
 * Qui a le droit de faire quoi sur un programme.
 *
 * La règle décidée :
 *  - le maître de chœur est maître du programme (créer, modifier, publier,
 *    supprimer) ;
 *  - l'instrumentiste ANNOTE seulement : il ajoute la tonalité jouée, l'intro,
 *    le tempo. Il ne crée rien et ne supprime rien. Un organiste qui efface le
 *    programme du dimanche le samedi soir n'est pas un scénario théorique ;
 *  - le choriste lit, et uniquement ce qui est publié.
 *
 * Comme pour ChantPolicy, chaque méthode revérifie la chorale : le filtre
 * automatique est la première barrière, la policy est la seconde.
 */
class CelebrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->chorale_id !== null;
    }

    public function view(User $user, Celebration $celebration): bool
    {
        if (! $user->memeChoraleQue($celebration->chorale_id)) {
            return false;
        }

        // Un brouillon n'appartient qu'à celui qui le prépare.
        return $celebration->est_publiee || $user->estMaitreDeChoeur();
    }

    public function create(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function update(User $user, Celebration $celebration): bool
    {
        return $user->memeChoraleQue($celebration->chorale_id) && $user->estMaitreDeChoeur();
    }

    public function delete(User $user, Celebration $celebration): bool
    {
        return $user->memeChoraleQue($celebration->chorale_id) && $user->estMaitreDeChoeur();
    }

    /**
     * Écrire la note réservée aux instrumentistes sur une ligne du programme.
     * Le maître de chœur peut le faire aussi : il joue parfois lui-même, et
     * lui interdire d'écrire dans son propre programme n'aurait aucun sens.
     */
    public function annoter(User $user, Celebration $celebration): bool
    {
        if (! $user->memeChoraleQue($celebration->chorale_id)) {
            return false;
        }

        // On n'annote pas un brouillon qu'on n'est pas censé voir.
        if (! $celebration->est_publiee && ! $user->estMaitreDeChoeur()) {
            return false;
        }

        return $user->estMaitreDeChoeur() || $user->estInstrumentiste();
    }
}
