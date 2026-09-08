<?php

namespace App\Models;

use App\Models\Concerns\AppartientAChorale;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chant extends Model
{
    use AppartientAChorale;

    protected $fillable = [
        'titre', 'paroles', 'tonalite', 'audio_path', 'partition_path', 'created_by',
    ];

    // Toujours inclure audio_url dans les réponses JSON, calculée à partir d'audio_path
    protected $appends = ['audio_url'];

    protected function audioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->audio_path ? asset('storage/'.$this->audio_path) : null,
        );
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Categorie::class, 'chant_categorie');
    }

    public function pupitres(): BelongsToMany
    {
        return $this->belongsToMany(Pupitre::class, 'chant_pupitre')
            ->withPivot('audio_path')
            ->using(ChantPupitre::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ChantVersion::class)->latest();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Met à jour les paroles en archivant l'ancienne version au préalable.
     * C'est ici que la règle "on ne perd jamais une ancienne version" vit,
     * plutôt que dans chaque contrôleur qui touche aux paroles.
     */
    public function mettreAJourParoles(string $nouvellesParoles, User $auteur): void
    {
        if ($nouvellesParoles === $this->paroles) {
            return; // rien n'a changé : pas de version inutile dans l'historique
        }

        $this->versions()->create([
            'chorale_id' => $this->chorale_id,
            'paroles' => $this->paroles,
            'modifie_par' => $auteur->id,
        ]);

        $this->update(['paroles' => $nouvellesParoles]);
    }
}
