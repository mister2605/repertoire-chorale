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

    /*
     * Lien RELATIF, volontairement — pas asset().
     *
     * asset() fabrique une adresse complète à partir d'APP_URL. Tant que
     * l'application change d'adresse (tunnel de test, puis vrai domaine),
     * cette adresse est périmée dès qu'on oublie de mettre APP_URL à jour :
     * tout le reste continue de marcher, et seul l'audio tombe en panne.
     * On a perdu deux soirées là-dessus.
     *
     * Laravel sert l'API ET le PWA depuis le même domaine : un lien relatif
     * est donc toujours juste, quelle que soit l'adresse du jour. Le jour où
     * les audios partiront sur un stockage externe, on repassera par
     * Storage::url() avec le disque correspondant.
     */
    protected function audioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->audio_path ? '/storage/'.ltrim($this->audio_path, '/') : null,
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
