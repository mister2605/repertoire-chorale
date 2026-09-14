<?php

namespace App\Models;

use App\Models\Concerns\AppartientAChorale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un rendez-vous de la chorale et son programme.
 *
 * "Célébration" plutôt que "Messe" : le modèle couvre aussi les répétitions,
 * mariages, funérailles et concerts. Les renommer plus tard aurait coûté une
 * migration et un passage dans tout le code ; le champ `type` ne coûte rien
 * aujourd'hui.
 */
class Celebration extends Model
{
    use AppartientAChorale;

    /** Les types connus. Ajouter une valeur ici suffit : rien à migrer. */
    public const TYPES = ['messe', 'repetition', 'mariage', 'funerailles', 'concert', 'autre'];

    protected $fillable = ['type', 'titre', 'debut_le', 'lieu', 'notes', 'publiee_le', 'created_by'];

    protected function casts(): array
    {
        return [
            'debut_le' => 'datetime',
            'publiee_le' => 'datetime',
        ];
    }

    protected $appends = ['est_publiee'];

    public function items(): HasMany
    {
        return $this->hasMany(CelebrationItem::class)->orderBy('position');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getEstPublieeAttribute(): bool
    {
        return $this->publiee_le !== null;
    }

    /**
     * Ce que voit un membre qui n'est pas maître de chœur : uniquement les
     * programmes publiés. Le filtre vit ici et pas dans le contrôleur, pour
     * qu'on ne puisse pas l'oublier en ajoutant un écran.
     */
    public function scopeVisiblesPar(Builder $query, User $utilisateur): Builder
    {
        if ($utilisateur->estMaitreDeChoeur()) {
            return $query;
        }

        return $query->whereNotNull('publiee_le');
    }

    /** Les célébrations à venir, la plus proche en premier. */
    public function scopeAVenir(Builder $query): Builder
    {
        return $query->where('debut_le', '>=', now()->startOfDay())->orderBy('debut_le');
    }

    /** Les célébrations passées, la plus récente en premier. */
    public function scopePassees(Builder $query): Builder
    {
        return $query->where('debut_le', '<', now()->startOfDay())->orderByDesc('debut_le');
    }
}
