<?php

namespace App\Models;

use App\Models\Concerns\AppartientAChorale;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du programme : un moment liturgique, et ce qu'on y chante.
 *
 * Le chant peut venir du répertoire (chant_id) ou être simplement écrit à la
 * main (titre_libre). Les deux sont légitimes : on ne bloque jamais le maître
 * de chœur parce qu'un chant n'a pas encore été saisi.
 */
class CelebrationItem extends Model
{
    use AppartientAChorale;

    protected $fillable = [
        'celebration_id', 'position', 'moment', 'chant_id',
        'titre_libre', 'notes', 'note_instrument',
    ];

    protected $appends = ['intitule'];

    public function celebration(): BelongsTo
    {
        return $this->belongsTo(Celebration::class);
    }

    public function chant(): BelongsTo
    {
        return $this->belongsTo(Chant::class);
    }

    /**
     * Ce qu'on affiche pour cette ligne, quelle que soit son origine.
     * Évite au front de refaire ce choix à chaque endroit où il l'affiche.
     */
    protected function intitule(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->chant?->titre ?? $this->titre_libre ?? '',
        );
    }
}
