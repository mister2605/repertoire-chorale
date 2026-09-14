<?php

namespace App\Policies;

use App\Models\Categorie;
use App\Models\User;

/**
 * Mêmes règles que les pupitres : tout le monde lit, le maître de chœur écrit.
 */
class CategoriePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->chorale_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function update(User $user, Categorie $categorie): bool
    {
        return $user->estMaitreDeChoeur() && $user->memeChoraleQue($categorie->chorale_id);
    }

    public function delete(User $user, Categorie $categorie): bool
    {
        return $this->update($user, $categorie);
    }
}
