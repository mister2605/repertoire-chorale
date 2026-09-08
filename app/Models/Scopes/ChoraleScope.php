<?php

namespace App\Models\Scopes;

use App\Support\ChoraleCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Ajoute automatiquement "WHERE chorale_id = <chorale de l'utilisateur>"
 * à TOUTE requête sur les modèles qui utilisent le trait AppartientAChorale.
 *
 * Principe de sécurité : on échoue en mode fermé. Si on n'arrive pas à
 * déterminer la chorale courante pendant une requête HTTP, on ne renvoie
 * RIEN plutôt que de risquer de renvoyer les données de tout le monde.
 */
class ChoraleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $choraleId = app(ChoraleCourante::class)->id();

        if ($choraleId !== null) {
            $builder->where($model->getTable().'.chorale_id', $choraleId);

            return;
        }

        // Pas de chorale identifiée :
        // - en console (migrations, seeders, commandes), on laisse passer ;
        // - en HTTP, on bloque tout.
        if (! app()->runningInConsole()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
