<?php

namespace App\Policies;

use App\Models\User;

/**
 * Qui a le droit de gérer les comptes des membres.
 *
 * Deux garde-fous en plus du rôle :
 *  - on ne gère que des membres de SA chorale ;
 *  - on ne peut ni se supprimer soi-même, ni retirer le dernier maître de chœur
 *    (sinon plus personne ne peut administrer la chorale).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function create(User $user): bool
    {
        return $user->chorale_id !== null && $user->estMaitreDeChoeur();
    }

    public function update(User $user, User $membre): bool
    {
        return $user->estMaitreDeChoeur() && $user->memeChoraleQue($membre->chorale_id);
    }

    /** Régénérer un lien d'invitation pour un membre qui a perdu le sien. */
    public function inviter(User $user, User $membre): bool
    {
        return $this->update($user, $membre);
    }

    public function delete(User $user, User $membre): bool
    {
        if ($user->id === $membre->id) {
            return false; // on ne se supprime pas soi-même
        }

        return $this->update($user, $membre);
    }
}
