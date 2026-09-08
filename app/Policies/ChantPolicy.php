<?php

namespace App\Policies;

use App\Models\Chant;
use App\Models\User;

/**
 * Le seul endroit où l'on décide qui a le droit de faire quoi sur un chant.
 *
 * Deux avantages par rapport au middleware sur les routes :
 *  - on ne peut plus ajouter une route en oubliant de la protéger ;
 *  - le jour où on ajoute un rôle "chef de pupitre", on modifie ce fichier
 *    et rien d'autre.
 *
 * Chaque méthode revérifie la chorale : le filtre automatique (ChoraleScope)
 * est la première barrière, la policy est la seconde. Deux barrières
 * indépendantes valent mieux qu'une.
 */
class ChantPolicy
{
    /** Voir la liste des chants : tout membre connecté. */
    public function viewAny(User $user): bool
    {
        return $user->chorale_id !== null;
    }

    public function view(User $user, Chant $chant): bool
    {
        return $user->memeChoraleQue($chant->chorale_id);
    }

    public function create(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function update(User $user, Chant $chant): bool
    {
        return $user->memeChoraleQue($chant->chorale_id) && $user->estMaitreDeChoeur();
    }

    public function delete(User $user, Chant $chant): bool
    {
        return $user->memeChoraleQue($chant->chorale_id) && $user->estMaitreDeChoeur();
    }

    /** Consulter et restaurer l'historique des paroles. */
    public function gererVersions(User $user, Chant $chant): bool
    {
        return $user->memeChoraleQue($chant->chorale_id) && $user->estMaitreDeChoeur();
    }
}
