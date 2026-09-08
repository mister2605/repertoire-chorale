<?php

namespace App\Models\Concerns;

use App\Models\Chorale;
use App\Models\Scopes\ChoraleScope;
use App\Support\ChoraleCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * À poser sur tout modèle qui appartient à une chorale.
 *
 * Fait deux choses :
 *  1. filtre automatiquement les lectures sur la chorale courante ;
 *  2. remplit automatiquement chorale_id à la création.
 *
 * Résultat : on ne peut plus oublier le filtre dans un contrôleur.
 */
trait AppartientAChorale
{
    public static function bootAppartientAChorale(): void
    {
        static::addGlobalScope(new ChoraleScope);

        static::creating(function ($model) {
            if (! empty($model->chorale_id)) {
                return;
            }

            $choraleId = app(ChoraleCourante::class)->id();

            if ($choraleId === null) {
                throw new RuntimeException(
                    'Impossible de créer un '.class_basename($model).
                    ' : aucune chorale courante. Utilisez ChoraleCourante::pour($id, fn () => ...).'
                );
            }

            $model->chorale_id = $choraleId;
        });
    }

    public function chorale(): BelongsTo
    {
        return $this->belongsTo(Chorale::class);
    }

    /**
     * Échappatoire explicite, à n'utiliser qu'en console ou en administration.
     * Le fait qu'elle soit nommée rend son usage visible dans une relecture de code.
     */
    public function scopeToutesChorales(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ChoraleScope::class);
    }
}
