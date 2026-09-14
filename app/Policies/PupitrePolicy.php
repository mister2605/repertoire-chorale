<?php

namespace App\Policies;

use App\Models\Pupitre;
use App\Models\User;

/**
 * Les pupitres se consultent par tout membre (la liste sert aux filtres),
 * mais ne se modifient que par le maître de chœur.
 *
 * Le filtre automatique (ChoraleScope) empêche déjà de voir un pupitre
 * d'une autre chorale ; on revérifie ici, comme pour les chants.
 */
class PupitrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->chorale_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function update(User $user, Pupitre $pupitre): bool
    {
        return $user->estMaitreDeChoeur() && $user->memeChoraleQue($pupitre->chorale_id);
    }

    public function delete(User $user, Pupitre $pupitre): bool
    {
        return $this->update($user, $pupitre);
    }
}
